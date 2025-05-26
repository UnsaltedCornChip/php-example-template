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

$video = null;
$categories = [];
$video_categories = [];
$message = '';
$videoId = $_GET['video_id'] ?? '';

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all categories
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $thumbnail_link = trim($_POST['thumbnail_link'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $selected_categories = $_POST['categories'] ?? [];

        if ($title && $artist && $thumbnail_link && in_array($status, ['active', 'inactive'])) {
            // Update video metadata
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

            // Update video categories
            // First, delete existing category assignments
            $stmt = $pdo->prepare("DELETE FROM video_categories WHERE video_id = :video_id");
            $stmt->execute([':video_id' => $videoId]);

            // Insert new category assignments
            if (!empty($selected_categories)) {
                $stmt = $pdo->prepare("
                    INSERT INTO video_categories (video_id, category_id)
                    VALUES (:video_id, :category_id)
                ");
                foreach ($selected_categories as $categoryId) {
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

            $message = "Video updated successfully!";
        } else {
            $message = "Please fill all required fields correctly.";
        }
    }

    if ($videoId) {
        // Fetch video details
        $stmt = $pdo->prepare("SELECT video_id, title, artist, thumbnail_link, status FROM youtube_videos WHERE video_id = :video_id");
        $stmt->execute([':video_id' => $videoId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch current video categories
        if ($video) {
            $stmt = $pdo->prepare("SELECT category_id FROM video_categories WHERE video_id = :video_id");
            $stmt->execute([':video_id' => $videoId]);
            $video_categories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');
        } else {
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
                    <div class="form-group">
                        <label>Categories (optional):</label>
                        <?php if (empty($categories)): ?>
                            <p>No categories available.</p>
                        <?php else: ?>
                            <div class="categories-container">
                                <?php foreach ($categories as $category): ?>
                                    <div class="category-item">
                                        <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="cat_<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $video_categories) ? 'checked' : ''; ?>>
                                        <label for="cat_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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