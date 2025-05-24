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

    // Query videos with "TVC Uke & Vocals" category
    $stmt = $pdo->prepare("
        SELECT yv.video_id, yv.title, yv.artist, yv.length_seconds, yv.thumbnail_link
        FROM youtube_videos yv
        JOIN video_categories vc ON yv.video_id = vc.video_id
        JOIN categories c ON vc.category_id = c.id
        WHERE c.name = 'TVC Uke & Vocals' AND yv.status = 'active'
        ORDER BY yv.title
    ");
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TVC Uke & Vocals - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>TVC Uke & Vocals</h2>
        <?php if (isset($error)): ?>
            <p class="no-videos"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (empty($videos)): ?>
            <p class="no-videos">No videos found in TVC Uke & Vocals category.</p>
        <?php else: ?>
            <div class="video-list">
                <?php foreach ($videos as $video): ?>
                    <?php
                    // Convert length_seconds to MM:SS
                    $minutes = floor($video['length_seconds'] / 60);
                    $seconds = $video['length_seconds'] % 60;
                    $duration = sprintf("%d:%02d", $minutes, $seconds);
                    ?>
                    <div class="video-item">
                        <img src="<?php echo htmlspecialchars($video['thumbnail_link']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                        <h4><a href="https://youtu.be/<?php echo htmlspecialchars($video['video_id']); ?>" target="_blank"><?php echo htmlspecialchars($video['title']); ?></a></h4>
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
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>