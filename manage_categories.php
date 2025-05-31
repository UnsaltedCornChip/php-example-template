<?php
// Start the session
session_start();

// Restrict access to streamer or moderators
if (!isset($_SESSION['twitch_user']['is_streamer']) || !$_SESSION['twitch_user']['is_streamer']) {
    if (!isset($_SESSION['twitch_user']['is_moderator']) || !$_SESSION['twitch_user']['is_moderator']) {
        header('Location: index.php?error=access_denied');
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

$categories = [];
$message = '';
$message_class = '';

try {
    $pdo = new PDO("$dsn", "$user", "$pass");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle add category
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
        $category_name = trim($_POST['category_name'] ?? '');
        if (empty($category_name)) {
            $message = "Category name cannot be empty.";
            $message_class = 'error-message';
        } else {
            // Check for duplicate category name
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name");
            $stmt->execute([':name' => $category_name]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Category '$category_name' already exists.";
                $message_class = 'error-message';
            } else {
                // Insert new category
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
                $stmt->execute([':name' => $category_name]);
                $message = "Category '$category_name' added successfully!";
                $message_class = '';
            }
        }
    }

    // Handle delete category
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['category_id'])) {
        $category_id = (int)$_POST['category_id'];
        // Verify category exists
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :id");
        $stmt->execute([':id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($category) {
            // Delete from video_categories first
            $stmt = $pdo->prepare("DELETE FROM video_categories WHERE category_id = :category_id");
            $stmt->execute([':category_id' => $category_id]);
            // Delete from categories
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $category_id]);
            $message = "Category '{$category['name']}' deleted successfully!";
            $message_class = '';
        } else {
            $message = "Category not found.";
            $message_class = 'error-message';
        }
    }

    // Fetch categories with video counts
    $stmt = $pdo->query("
        SELECT c.id, c.name, COUNT(vc.video_id) as youtube_video_count
        FROM categories c
        LEFT JOIN video_categories vc ON c.id = vc.category_id
        LEFT JOIN youtube_videos yv ON vc.video_id = yv.video_id
        GROUP BY c.id, c.name
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Categories - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Manage Categories</h2>
        <div class="content-section">
            <?php if ($message): ?>
                <p class="form-message <?php echo $message_class; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <!-- Add Category Form -->
            <h3>Add New Category</h3>
            <form action="manage_categories.php" method="POST" class="form-container">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="category_name">Category Name:</label>
                    <input type="text" id="category_name" name="category_name" required>
                </div>
                <button type="submit" class="form-button">Add Category</button>
            </form>

            <!-- Category List with Delete -->
            <h3>Existing Categories</h3>
            <?php if (empty($categories)): ?>
                <p class="no-videos">No categories found.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Video Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $category['youtube_video_count']; ?></td>
                                    <td>
                                        <form action="manage_categories.php" method="POST" onsubmit="return confirm('Are you sure you want to delete the category \'<?php echo htmlspecialchars($category['name']); ?>\'? This will remove it from all associated videos.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" class="form-button delete-button">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>