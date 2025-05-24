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

// Determine the referring page and query parameters for redirect
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$valid_pages = ['originals.php', 'covers.php', 'loops.php', 'uke-vocals.php', 'all-songs.php'];
$redirect_page = 'originals.php';
$redirect_params = '';

if ($referer) {
    $parsed_url = parse_url($referer);
    $path = basename($parsed_url['path'] ?? '');
    if (in_array($path, $valid_pages)) {
        $redirect_page = $path;
        // Preserve query parameters if all-songs.php
        if ($path === 'all-songs.php' && !empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            $params_to_keep = [];
            if (isset($query_params['search'])) {
                $params_to_keep['search'] = $query_params['search'];
            }
            if (isset($query_params['page'])) {
                $params_to_keep['page'] = $query_params['page'];
            }
            if (isset($query_params['per_page'])) {
                $params_to_keep['per_page'] = $query_params['per_page'];
            }
            if (!empty($params_to_keep)) {
                $redirect_params = '?' . http_build_query($params_to_keep);
            }
        }
    }
}

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
            header("Location: $redirect_page$redirect_params?status=error&message=" . urlencode("Video not found."));
            exit;
        }
    } catch (Exception $e) {
        header("Location: $redirect_page$redirect_params?status=error&message=" . urlencode("Error fetching video data: " . $e->getMessage()));
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

    header("Location: $redirect_page$redirect_params?status=success&message=" . urlencode("Video metadata refreshed successfully!"));
    exit;

} catch (PDOException $e) {
    header("Location: $redirect_page$redirect_params?status=error&message=" . urlencode("Database error: " . $e->getMessage()));
    exit;
} catch (Exception $e) {
    header("Location: $redirect_page$redirect_params?status=error&message=" . urlencode("Unexpected error: " . $e->getMessage()));
    exit;
}
?>