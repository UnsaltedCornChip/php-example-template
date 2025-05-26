<?php
// Start the session
session_start();

$videoId = trim($_POST['video_id'] ?? '');
$categories = $_POST['categories'] ?? []; // Optional categories array

if (!$videoId) {
    header("Location: add_video.php?status=error&message=" . urlencode("No video ID provided."));
    exit;
}

// YouTube API Key
$apiKey = getenv('YOUTUBE_API_KEY');
$apiUrl = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id={$videoId}&key={$apiKey}";

// Path to CA certificate bundle
$caCertPath = 'cacert.pem';

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

    // Check if video_id already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM youtube_videos WHERE video_id = :video_id");
    $stmt->execute([':video_id' => $videoId]);
    if ($stmt->fetchColumn() > 0) {
        header("Location: add_video.php?status=exists");
        exit;
    }

    // Fetch metadata from YouTube using curl
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath); // Use local CA bundle
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log response for debugging (commented out)
        // file_put_contents('api_response.log', "HTTP Code: $httpCode\nResponse: $response\nError: $curlError\n", FILE_APPEND);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("API request failed: HTTP $httpCode, Error: $curlError");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        if (empty($data['items'])) {
            header("Location: add_video.php?status=error&message=" . urlencode("Video not found."));
            exit;
        }
    } catch (Exception $e) {
        header("Location: add_video.php?status=error&message=" . urlencode("Error fetching video data: " . $e->getMessage()));
        exit;
    }

    // Parse metadata
    $video = $data['items'][0];
    $title = $video['snippet']['title'];
    $artist = $video['snippet']['channelTitle'];
    $durationISO = $video['contentDetails']['duration'];
    $thumbnail_link = $video['snippet']['thumbnails']['medium']['url'];

    // Convert ISO 8601 duration to seconds
    function iso8601ToSeconds($duration) {
        $interval = new DateInterval($duration);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
    $lengthSeconds = iso8601ToSeconds($durationISO);

    // Insert video metadata
    $stmt = $pdo->prepare("
        INSERT INTO youtube_videos (video_id, title, artist, thumbnail_link, length_seconds, created_at, status)
        VALUES (:video_id, :title, :artist, :thumbnail_link, :length_seconds, CURRENT_TIMESTAMP, 'active')
    ");
    $stmt->execute([
        ':video_id' => $videoId,
        ':title' => $title,
        ':artist' => $artist,
        ':thumbnail_link' => $thumbnail_link,
        ':length_seconds' => $lengthSeconds
    ]);

    // Insert category assignments (if any)
    if (!empty($categories)) {
        $stmt = $pdo->prepare("
            INSERT INTO video_categories (video_id, category_id)
            VALUES (:video_id, :category_id)
        ");
        foreach ($categories as $categoryId) {
            // Validate category_id exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = :category_id");
            $checkStmt->execute([':category_id' => $categoryId]);
            if ($checkStmt->fetchColumn() > 0) {
                $stmt->execute([
                    ':video_id' => $videoId,
                    ':category_id' => $categoryId
                ]);
            }
        }
    }

    header("Location: add_video.php?status=success");
    exit;

} catch (PDOException $e) {
    header("Location: add_video.php?status=error&message=" . urlencode("Database error: " . $e->getMessage()));
    exit;
} catch (Exception $e) {
    header("Location: add_video.php?status=error&message=" . urlencode("Unexpected error: " . $e->getMessage()));
    exit;
}
?>