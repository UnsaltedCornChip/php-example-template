<?php
// Start the session
session_start();

// Restrict access to streamer or moderators
if (!isset($_SESSION['twitch_user']['is_streamer']) || !$_SESSION['twitch_user']['is_streamer']) {
    if (!isset($_SESSION['twitch_user']['is_moderator']) || !$_SESSION['twitch_user']['is_moderator']) {
        header('Location: index.php?error=info');
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

$videos = [];
$message = '';
$message_class = '';

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle activate video
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate' && isset($_POST['video_id'])) {
        $video_id = trim($_POST['video_id']);
        // Verify video exists and is inactive
        $stmt = $pdo->prepare("SELECT title FROM youtube_videos WHERE video_id = :video_id AND status = 'inactive'");
        $stmt->execute([':video_id' => $video_id]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($video) {
            // Update video to active
            $stmt = $pdo->prepare("UPDATE youtube_videos SET status = 'active' WHERE video_id = :video_id");
            $stmt->execute([':video_id' => $video_id]);
            $message = "Video '{$video['title']}' activated successfully!";
            $message_class = 'success-message';
        } else {
            $message = "Video not found or already active.";
            $message_class = 'error-message';
        }
    }

    // Fetch inactive videos with categories
    $stmt = $pdo->query("
        SELECT yv.video_id, yv.title, yv.artist, yv.length_seconds, STRING_AGG(c.name, ', ') as categories
        FROM youtube_videos yv
        LEFT JOIN video_categories vc ON yv.video_id = vc.video_id
        LEFT JOIN categories c ON vc.category_id = c.id
        WHERE yv.status = 'inactive'
        GROUP BY yv.video_id, yv.title, yv.artist, yv.length_seconds
        ORDER BY yv.title
    ");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_class = 'error-message';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inactive Videos - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Manage Inactive Videos</h2>
        <div class="content-section">
            <?php if ($message): ?>
                <p class="form-message <?php echo $message_class; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <!-- Inactive Videos List -->
            <h3>Inactive Videos</h3>
            <?php if (empty($videos)): ?>
                <p class="no-videos">No inactive videos found.</p>
            <?php else: ?>
                <div class="video-grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="video-item">
                            <div class="video-details">
                                <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                                <p><strong>Artist:</strong> <?php echo htmlspecialchars($video['artist'] ?? 'Unknown'); ?></p>
                                <p><strong>Duration:</strong> <?php echo sprintf('%02d:%02d', intdiv($video['length_seconds'], 60), $video['length_seconds'] % 60); ?></p>
                                <p class="categories"><strong>Categories:</strong> <?php echo htmlspecialchars($video['categories'] ?? 'None'); ?></p>
                            </div>
                            <form action="manage_inactive_videos.php" method="POST" onsubmit="return confirm('Are you sure you want to activate the video \'<?php echo htmlspecialchars($video['title']); ?>\'?');">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($video['video_id']); ?>">
                                <button type="submit" class="form-button activate-form-button">Activate</button>
                            </form>
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