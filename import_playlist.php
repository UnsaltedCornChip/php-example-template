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
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$sslmode = 'require';
$dsn = "pgsql:host=$host;dbname=$db;sslmode=$sslmode";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = '';
    $message_type = '';
    $progress_logs = [];
    $playlist_url = $_POST['playlist_url'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($playlist_url)) {
        // Validate and extract playlist ID
        $parsed_url = parse_url($playlist_url, PHP_URL_QUERY);
        parse_str($parsed_url, $query_params);
        $playlist_id = $query_params['list'] ?? '';
        
        if (empty($playlist_id)) {
            $message = 'Invalid playlist URL. Please provide a valid YouTube playlist URL.';
            $message_type = 'error';
        } else {
            $api_key = getenv('YOUTUBE_API_KEY');
            if (!$api_key) {
                $message = 'YouTube API key not configured.';
                $message_type = 'error';
            } else {
                try {
                    // Fetch playlist details
                    $playlist_url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=${playlist_id}&key=${api_key}";
                    $ch = curl_init($playlist_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $playlist_response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code !== 200) {
                        throw new Exception('Failed to fetch playlist details. HTTP Code: ' . $http_code);
                    }

                    $playlist_data = json_decode($playlist_response, true);
                    if (empty($playlist_data['items'])) {
                        throw new Exception('Playlist not found or is private.');
                    }

                    $playlist_name = $playlist_data['items'][0]['snippet']['title'];
                    $progress_logs[] = "Fetched playlist: $playlist_name";

                    // Check if category exists or create new
                    $stmt = $pdo->prepare('SELECT id FROM categories WHERE "name" = ?');
                    $stmt->execute([$playlist_name]);
                    $category_id = $stmt->fetchColumn();

                    if (!$category_id) {
                        $stmt = $pdo->prepare('INSERT INTO categories ("name") VALUES (?) RETURNING id');
                        $stmt->execute([$playlist_name]);
                        $category_id = $stmt->fetchColumn();
                        $progress_logs[] = "Created new category: $playlist_name";
                    } else {
                        $progress_logs[] = "Using existing category: $playlist_name";
                    }

                    // Fetch playlist items
                    $videos = [];
                    $next_page_token = '';
                    $total_videos = 0;
                    do {
                        $items_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&playlistId=${playlist_id}&maxResults=50&key=${api_key}";
                        if ($next_page_token) {
                            $items_url .= "&pageToken=$next_page_token";
                        }

                        $ch = curl_init($items_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $items_response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code !== 200) {
                            throw new Exception('Failed to fetch playlist items. HTTP Code: ' . $http_code);
                        }

                        $items_data = json_decode($items_response, true);
                        $video_ids = array_column($items_data['items'], 'contentDetails');
                        $video_ids = array_column($video_ids, 'videoId');
                        $videos = array_merge($videos, $video_ids);
                        $next_page_token = $items_data['nextPageToken'] ?? '';
                        $total_videos += count($video_ids);
                    } while ($next_page_token);

                    $progress_logs[] = "Found $total_videos videos in playlist.";

                    // Fetch video details
                    $imported = 0;
                    $skipped = 0;
                    foreach (array_chunk($videos, 50) as $video_id_chunk) {
                        $video_ids_str = implode(',', $video_id_chunk);
                        $video_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id=${video_ids_str}&key=${api_key}";
                        $ch = curl_init($video_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $video_response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code !== 200) {
                            $progress_logs[] = "Failed to fetch details for some videos. HTTP Code: $http_code";
                            continue;
                        }

                        $video_data = json_decode($video_response, true);
                        foreach ($video_data['items'] as $video) {
                            $video_id = $video['id'];
                            $title = $video['snippet']['title'];
                            $artist = $video['snippet']['channelTitle'];
                            $thumbnail = $video['snippet']['thumbnails']['medium']['url'] ?? '';
                            $duration_iso = $video['contentDetails']['duration'];

                            // Convert ISO 8601 duration to seconds
                            $interval = new DateInterval($duration_iso);
                            $seconds = ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;

                            // Skip if thumbnail is empty
                            if (empty($thumbnail)) {
                                $progress_logs[] = "Skipped video $video_id: No medium thumbnail ($title).";
                                $skipped++;
                                continue;
                            }

                            // Check if video exists
                            $stmt = $pdo->prepare("SELECT video_id FROM youtube_videos WHERE video_id = ?");
                            $stmt->execute([$video_id]);
                            $is_existing = $stmt->fetchColumn();

                            if ($is_existing) {
                                $progress_logs[] = "Video $video_id already exists ($title). Assigning category.";
                            } else {
                                // Insert video
                                $stmt = $pdo->prepare("
                                    INSERT INTO youtube_videos (video_id, title, artist, thumbnail_link, length_seconds, created_at, status)
                                    VALUES (?, ?, ?, ?, ?, NOW(), 'active')
                                ");
                                $stmt->execute([$video_id, $title, $artist, $thumbnail, $seconds]);
                                $progress_logs[] = "Imported video $video_id: $title";
                                $imported++;
                            }

                            // Link to category (for both new and existing videos)
                            $stmt = $pdo->prepare("
                                INSERT INTO video_categories (video_id, category_id)
                                VALUES (?, ?)
                                ON CONFLICT (video_id, category_id) DO NOTHING
                            ");
                            $stmt->execute([$video_id, $category_id]);

                            if ($is_existing) {
                                $skipped++;
                            }
                        }
                    }

                    $message = "Imported $imported new videos. Processed $skipped existing videos.";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error: ' . htmlspecialchars($e->getMessage());
                    $message_type = 'error';
                    $progress_logs[] = $message;
                }
            }
        }
    }
} catch (PDOException $e) {
    $message = 'Database error: ' . htmlspecialchars($e->getMessage());
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Playlist - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Import YouTube Playlist</h2>
        <div class="content-section">
            <div class="form-container">
                <form method="POST" action="import_playlist.php">
                    <div class="form-group">
                        <label for="playlist_url">YouTube Playlist URL:</label>
                        <input type="text" id="playlist_url" name="playlist_url" value="<?php echo htmlspecialchars($playlist_url); ?>" placeholder="e.g., https://www.youtube.com/playlist?list=PLlUZ3i-FUgHqk9-C-Fw_C6YsvTyx2c8nc" required>
                    </div>
                    <button type="submit" class="form-button">Import Playlist</button>
                </form>
            </div>
            <?php if ($message): ?>
                <p class="form-message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($progress_logs)): ?>
                <div class="progress-container">
                    <h3>Import Progress</h3>
                    <?php foreach ($progress_logs as $log): ?>
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