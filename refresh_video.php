<?php
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

    // Get video_id and return_to
    $video_id = isset($_GET['video_id']) ? $_GET['video_id'] : '';
    $return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'originals.php';
    $valid_return_pages = ['originals.php', 'covers.php', 'loops.php', 'uke-vocals.php'];
    if (!in_array($return_to, $valid_return_pages)) {
        $return_to = 'originals.php';
    }

    if (!$video_id) {
        header("Location: $return_to?error=" . urlencode("No video ID provided."));
        exit;
    }

    // Fetch YouTube API key
    $api_key = getenv('YOUTUBE_API_KEY');
    if (!$api_key) {
        header("Location: $return_to?error=" . urlencode("YouTube API key not configured."));
        exit;
    }

    // Call YouTube API
    $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id=$video_id&key=$api_key";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        header("Location: $return_to?error=" . urlencode("Failed to fetch YouTube data."));
        exit;
    }

    $data = json_decode($response, true);
    if (empty($data['items'])) {
        header("Location: $return_to?error=" . urlencode("Video not found on YouTube."));
        exit;
    }

    // Extract metadata
    $item = $data['items'][0];
    $title = $item['snippet']['title'];
    $artist = $item['snippet']['channelTitle']; // Using channelTitle as artist
    $thumbnail_link = $item['snippet']['thumbnails']['default']['url'] ?? '';
    $duration = $item['contentDetails']['duration']; // e.g., PT3M45S

    // Convert duration to seconds
    $interval = new DateInterval($duration);
    $length_seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;

    // Update database
    $stmt = $pdo->prepare("
        UPDATE youtube_videos
        SET title = ?, artist = ?, thumbnail_link = ?, length_seconds = ?
        WHERE video_id = ?
    ");
    $stmt->execute([$title, $artist, $thumbnail_link, $length_seconds, $video_id]);

    header("Location: $return_to?success=" . urlencode("Video metadata refreshed successfully."));
    exit;
} catch (PDOException $e) {
    header("Location: $return_to?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
} catch (Exception $e) {
    header("Location: $return_to?error=" . urlencode("Error: " . $e->getMessage()));
    exit;
}
?>