<?php
// Start the session
session_start();

// Restrict access to streamer or moderators
if (!isset($_SESSION['twitch_user']['is_streamer']) || !$_SESSION['twitch_user']['is_streamer']) {
    if (!isset($_SESSION['twitch_user']['is_moderator']) || !$_SESSION['twitch_user']['is_moderator']) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

// Connect to PostgreSQL
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$sslmode = 'require';
$dsn = "pgsql:host=$host;dbname=$db;sslmode=$sslmode";

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = '';
    $message_type = '';
    $progress = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
        set_time_limit(0); // Allow long processing

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = 'File upload error: ' . $file['error'];
            $message_type = 'error';
        } elseif ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            $message = 'Please upload a valid CSV file.';
            $message_type = 'error';
        } else {
            // YouTube API Key
            $apiKey = getenv('YOUTUBE_API_KEY');
            $caCertPath = 'cacert.pem';
            $batch_size = 500;
            $api_batch_size = 50;

            // Validate categories
            $stmt = $pdo->query("SELECT id FROM categories");
            $valid_category_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

            // Read CSV
            $rows = [];
            if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                $header = fgetcsv($handle); // Skip header
                if ($header !== ['youtube_id', 'category_id']) {
                    $message = 'Invalid CSV format. Expected headers: youtube_id,category_id';
                    $message_type = 'error';
                    fclose($handle);
                } else {
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) === 2 && !empty($data[0]) && is_numeric($data[1])) {
                            $rows[] = ['youtube_id' => trim($data[0]), 'category_id' => (int)$data[1]];
                        }
                    }
                    fclose($handle);
                }
            }

            if (empty($rows)) {
                $message = 'No valid data found in CSV.';
                $message_type = 'error';
            } else {
                $total_rows = count($rows);
                $inserted = 0;
                $skipped = 0;
                $errors = 0;

                // Process in batches
                for ($i = 0; $i < $total_rows; $i += $batch_size) {
                    $batch = array_slice($rows, $i, $batch_size);
                    $video_ids = array_column($batch, 'youtube_id');
                    $category_map = array_combine($video_ids, array_column($batch, 'category_id'));

                    // Validate category_ids
                    foreach ($category_map as $video_id => $category_id) {
                        if (!in_array($category_id, $valid_category_ids)) {
                            $skipped++;
                            $progress[] = "Skipped video_id: $video_id (invalid category_id: $category_id)";
                            unset($category_map[$video_id]);
                        }
                    }

                    // Fetch metadata in API batches
                    $video_data = [];
                    for ($j = 0; $j < count($video_ids); $j += $api_batch_size) {
                        $batch_ids = array_slice($video_ids, $j, $api_batch_size);
                        $ids_string = implode(',', $batch_ids);
                        $apiUrl = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id=${ids_string}&key=${apiKey}";

                        try {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $apiUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                            curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $curlError = curl_error($ch);
                            curl_close($ch);

                            if ($response === false || $httpCode !== 200) {
                                $progress[] = "API error for batch " . ($i / $batch_size + 1) . ": HTTP $httpCode, $curlError";
                                $errors += count($batch_ids);
                                continue;
                            }

                            $data = json_decode($response, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $progress[] = "Invalid JSON for batch " . ($i / $batch_size + 1) . ": " . json_last_error_msg();
                                $errors += count($batch_ids);
                                continue;
                            }

                            // Check for unavailable videos
                            $returned_ids = array_column($data['items'], 'id');
                            $missing_ids = array_diff($batch_ids, $returned_ids);
                            foreach ($missing_ids as $missing_id) {
                                $progress[] = "Video unavailable: $missing_id";
                                $errors++;
                            }

                            foreach ($data['items'] as $video) {
                                $video_data[$video['id']] = $video;
                            }
                        } catch (Exception $e) {
                            $progress[] = "API error for batch " . ($i / $batch_size + 1) . ": " . $e->getMessage();
                            $errors += count($batch_ids);
                            continue;
                        }
                    }

                    // Insert into youtube_videos
                    $pdo->beginTransaction();
                    try {
                        $insert_video = $pdo->prepare("
                            INSERT INTO youtube_videos (video_id, title, artist, thumbnail_link, length_seconds, status)
                            VALUES (:video_id, :title, :artist, :thumbnail_link, :length_seconds, 'active')
                            ON CONFLICT (video_id) DO NOTHING
                            RETURNING video_id
                        ");

                        $insert_category = $pdo->prepare("
                            INSERT INTO video_categories (video_id, category_id)
                            VALUES (:video_id, :category_id)
                            ON CONFLICT (video_id, category_id) DO NOTHING
                        ");

                        foreach ($video_ids as $video_id) {
                            if (!isset($video_data[$video_id])) {
                                $skipped++;
                                $progress[] = "Skipped video_id: $video_id (missing data)";
                                continue;
                            }

                            if (!isset($category_map[$video_id])) {
                                $skipped++;
                                $progress[] = "Skipped video_id: $video_id (invalid category)";
                                continue;
                            }

                            $video = $video_data[$video_id];
                            $title = $video['snippet']['title'];
                            $artist = $video['snippet']['channelTitle'];
                            $thumbnail_link = $video['snippet']['thumbnails']['medium']['url'];
                            $durationISO = $video['contentDetails']['duration'];

                            // Convert ISO 8601 duration to seconds
                            try {
                                $interval = new DateInterval($durationISO);
                                $length_seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                            } catch (Exception $e) {
                                $progress[] = "Invalid duration for video_id: $video_id";
                                $skipped++;
                                continue;
                            }

                            $insert_video->execute([
                                ':video_id' => $video_id,
                                ':title' => $title,
                                ':artist' => $artist,
                                ':thumbnail_link' => $thumbnail_link,
                                ':length_seconds' => $length_seconds
                            ]);

                            $result = $insert_video->fetch(PDO::FETCH_ASSOC);
                            if ($result) {
                                $insert_category->execute([
                                    ':video_id' => $video_id,
                                    ':category_id' => $category_map[$video_id]
                                ]);
                                $inserted++;
                            } else {
                                $skipped++;
                                $progress[] = "Skipped video_id: $video_id (duplicate or insert failed)";
                            }
                        }

                        $pdo->commit();
                        $progress[] = "Processed batch " . ($i / $batch_size + 1) . ": $inserted inserted, $skipped skipped, $errors errors";
                        ob_flush();
                        flush();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $progress[] = "Database error in batch " . ($i / $batch_size + 1) . ": " . $e->getMessage();
                        $errors += count($batch);
                    }
                }

                $message = "Processing complete: $inserted videos inserted, $skipped skipped, $errors errors.";
                $message_type = $errors > 0 ? 'error' : 'success';
            }
        }
    }
} catch (PDOException $e) {
    $message = "Database connection error: " . $e->getMessage();
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload Videos - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Bulk Upload Videos</h2>
        <div class="content-section">
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Upload CSV File:</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                        <p>CSV format: <code>youtube_id,category_id</code> (e.g., <code>dQw4w9WgXcQ,1</code>)</p>
                    </div>
                    <button type="submit" class="form-button">Upload and Process</button>
                </form>
            </div>

            <?php if ($message): ?>
                <p class="form-message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($progress)): ?>
                <div class="progress-container">
                    <h3>Processing Progress</h3>
                    <?php foreach ($progress as $log): ?>
                        <p class="progress-log"><?php echo htmlspecialchars($log); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>