<?php
session_start();

// Connect to PostgreSQL
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$sslmode = 'require';
$dsn = "pgsql:host=$host;dbname=$db;sslmode=$sslmode";

$video = null;
$message = '';
$videoId = $_GET['video_id'] ?? '';

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $thumbnail_link = trim($_POST['thumbnail_link'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($title && $artist && $thumbnail_link && in_array($status, ['active', 'inactive'])) {
            $stmt = $pdo->prepare("
                UPDATE youtube_videos 
                SET title = :title, artist = :artist, thumbnail_link = :thumbnail_link, status = :status
                WHERE video_id = :video_id
            ");
            $stmt->execute([
                ':title' => $title,
                ':artist' => $artist,
                ':thumbnail_link' => $thumbnail_link,
                ':status' => $status,
                ':video_id' => $videoId
            ]);
            $message = "Video updated successfully!";
        } else {
            $message = "Please fill all fields correctly.";
        }
    }

    if ($videoId) {
        // Fetch video details (always fetch after POST to get updated data)
        $stmt = $pdo->prepare("SELECT video_id, title, artist, thumbnail_link, status FROM youtube_videos WHERE video_id = :video_id");
        $stmt->execute([':video_id' => $videoId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) {
            $message = "Video not found.";
        }
    } else {
        $message = "No video ID provided.";
    }
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Edit Video</h2>
        <div class="content-section">
            <?php if ($message): ?>
                <p class="form-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <?php if ($video): ?>
                <form action="edit_video.php?video_id=<?php echo htmlspecialchars($videoId); ?>" method="POST" class="form-container">
                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="artist">Artist:</label>
                        <input type="text" id="artist" name="artist" value="<?php echo htmlspecialchars($video['artist']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="thumbnail_link">Thumbnail Link:</label>
                        <input type="text" id="thumbnail_link" name="thumbnail_link" value="<?php echo htmlspecialchars($video['thumbnail_link']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo $video['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $video['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="form-button">Save Changes</button>
                </form>
            <?php else: ?>
                <p class="no-videos">No video data available.</p>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>