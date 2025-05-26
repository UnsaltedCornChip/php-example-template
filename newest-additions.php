<?php
// Start the session
session_start();

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

    // Check for success/error messages
    $message = '';
    $message_type = '';
    if (isset($_GET['status'])) {
        $message_type = $_GET['status'] === 'success' ? 'success' : 'error';
        $message = $_GET['message'] ?? ($message_type === 'success' ? 'Video metadata refreshed successfully!' : 'An error occurred.');
        $message = htmlspecialchars($message);
    }

    // Fetch the 30 most recent videos
    $query = "SELECT video_id, title, artist, length_seconds, thumbnail_link, created_at 
              FROM youtube_videos 
              WHERE status = 'active' 
              ORDER BY created_at DESC 
              LIMIT 30";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $videos = [];

    // Fallback: Try a simple query
    try {
        $stmt = $pdo->query("SELECT video_id, title, artist, length_seconds, thumbnail_link, created_at 
                             FROM youtube_videos 
                             WHERE status = 'active' 
                             ORDER BY created_at DESC 
                             LIMIT 10");
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $message = "Fallback query used due to error in main query.";
        $message_type = 'error';
    } catch (PDOException $e) {
        $error .= " | Fallback query failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newest Additions - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Newest Additions</h2>
        <div class="content-section">
            <?php if ($message): ?>
                <p class="form-message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="no-videos"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (empty($videos)): ?>
                <p class="no-videos">No songs found.</p>
            <?php else: ?>
                <div class="video-list">
                    <?php foreach ($videos as $video): ?>
                        <?php
                        $minutes = floor($video['length_seconds'] / 60);
                        $seconds = $video['length_seconds'] % 60;
                        $duration = sprintf("%d:%02d", $minutes, $seconds);
                        // Format created_at as YYYY-MM-DD
                        $created_at = (new DateTime($video['created_at']))->format('Y-m-d');
                        ?>
                        <div class="video-item">
                            <img src="<?php echo htmlspecialchars($video['thumbnail_link']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                            <h4>
                                <a href="https://youtu.be/<?php echo htmlspecialchars($video['video_id']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($video['title']); ?><br>
                                    (Added: <?php echo $created_at; ?>)
                                </a>
                            </h4>
                            <p><?php echo htmlspecialchars($video['artist']); ?></p>
                            <p class="duration"><?php echo $duration; ?></p>
                            <button class="copy-command-btn" data-youtube-link="https://youtu.be/<?php echo htmlspecialchars($video['video_id']); ?>">Copy Song Request Command</button>
                            <div class="button-container">
                                <button class="edit-btn" data-video-id="<?php echo htmlspecialchars($video['video_id']); ?>">Edit</button>
                                <button class="refresh-btn" data-video-id="<?php echo htmlspecialchars($video['video_id']); ?>"><span class="refresh-icon">↻</span></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>