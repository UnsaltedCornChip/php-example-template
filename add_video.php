<?php
session_start();

// Connect to database to fetch categories
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$sslmode = 'require';
$dsn = "pgsql:host=$host;dbname=$db;sslmode=$sslmode";

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: Unable to fetch categories.";
    $categories = [];
}

// Check for success/error messages in query string
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = 'Video added successfully!';
    } elseif ($_GET['status'] === 'exists') {
        $message = 'Video already exists in the database.';
    } elseif ($_GET['status'] === 'error') {
        $message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'An error occurred while adding the video.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add YouTube Video</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <img src="images/tvc.png" alt="TVC Logo" class="main-image">
        <h2>Add YouTube Video</h2>
        <div class="content-section">
            <h3>Enter Video Details</h3>
            <?php if ($message): ?>
                <p class="form-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <form action="add_video_to_db.php" method="POST" class="form-container">
                <div class="form-group">
                    <label for="video_id">YouTube Video ID:</label>
                    <input type="text" id="video_id" name="video_id" required placeholder="e.g., dQw4w9WgXcQ">
                </div>
                <div class="form-group">
                    <label>Categories (optional):</label>
                    <?php if (empty($categories)): ?>
                        <p>No categories available.</p>
                    <?php else: ?>
                        <div class="categories-container">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-item">
                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="cat_<?php echo $category['id']; ?>">
                                    <label for="cat_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="form-button">Add Video</button>
            </form>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>