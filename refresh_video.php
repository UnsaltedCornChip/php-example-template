<?php
$videoId = $_POST['video_id'] ?? '';

if (!$videoId) {
    header("Location: originals.php?status=error&message=" . urlencode("No video ID provided."));
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

    // Fetch metadata from YouTube using curl
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
            throw new Exception("API request failed: HTTP $httpCode, Error: $curlError");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        if (empty($data['items'])) {
            header("Location: originals.php?status=error&message=" . urlencode("Video not found."));
            exit;
        }
    } catch (Exception $e) {
        header("Location: originals.php?status=error&message=" . urlencode("Error fetching video data: " . $e->getMessage()));
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

    // Update video metadata
    $stmt = $pdo->prepare("
        UPDATE youtube_videos 
        SET title = :title, artist = :artist, thumbnail_link = :thumbnail_link, length_seconds = :length_seconds
        WHERE video_id = :video_id
    ");
    $stmt->execute([
        ':title' => $title,
        ':artist' => $artist,
        ':thumbnail_link' => $thumbnail_link,
        ':length_seconds' => $lengthSeconds,
        ':video_id' => $videoId
    ]);

    header("Location: originals.php?status=success&message=" . urlencode("Video metadata refreshed successfully!"));
    exit;

} catch (PDOException $e) {
    header("Location: originals.php?status=error&message=" . urlencode("Database error: " . $e->getMessage()));
    exit;
} catch (Exception $e) {
    header("Location: originals.php?status=error&message=" . urlencode("Unexpected error: " . $e->getMessage()));
    exit;
}
?>