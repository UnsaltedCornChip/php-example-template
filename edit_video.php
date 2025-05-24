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

    // Get video_id and return_to from query
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

    // Fetch video metadata
    $stmt = $pdo->prepare("SELECT title, artist, thumbnail_link, status FROM youtube_videos WHERE video_id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        header("Location: $return_to?error=" . urlencode("Video not found."));
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $thumbnail_link = trim($_POST['thumbnail_link'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($title) || empty($artist)) {
            $error = "Title and artist are required.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE youtube_videos
                SET title = ?, artist = ?, thumbnail_link = ?, status = ?
                WHERE video_id = ?
            ");
            $stmt->execute([$title, $artist, $thumbnail_link, $status, $video_id]);
            header("Location: $return_to?success=" . urlencode("Video updated successfully."));
            exit;
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
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
        <?php if (isset($error)): ?>
            <p class="form-message error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="artist">Artist</label>
                    <input type="text" id="artist" name="artist" value="<?php echo htmlspecialchars($video['artist']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="thumbnail_link">Thumbnail Link</label>
                    <input type="text" id="thumbnail_link" name="thumbnail_link" value="<?php echo htmlspecialchars($video['thumbnail_link']); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo $video['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $video['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="form-button">Save Changes</button>
            </form>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>