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

    // Get query parameters
    $categories = isset($_GET['categories']) && is_array($_GET['categories']) ? array_map('trim', $_GET['categories']) : [];
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], ['10', '25', '50', '100']) ? (int)$_GET['per_page'] : 25;

    // Fetch available categories with active videos
    $stmt = $pdo->query("SELECT DISTINCT c.name 
                         FROM categories c 
                         JOIN video_categories vc ON c.id = vc.category_id 
                         JOIN youtube_videos yv ON vc.video_id = yv.video_id 
                         WHERE yv.status = 'active' 
                         ORDER BY c.name");
    $available_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $videos = [];
    $total_videos = 0;
    $total_pages = 1;

    if (!empty($categories)) {
        // Validate categories
        $valid_categories = array_intersect($categories, $available_categories);
        if (empty($valid_categories)) {
            $message = 'No valid categories selected.';
            $message_type = 'error';
        } else {
            // Build video query
            $placeholders = implode(',', array_fill(0, count($valid_categories), '?'));
            $query = "SELECT DISTINCT yv.video_id, yv.title, yv.artist, yv.length_seconds, yv.thumbnail_link 
                      FROM youtube_videos yv 
                      JOIN video_categories vc ON yv.video_id = vc.video_id 
                      JOIN categories c ON vc.category_id = c.id 
                      WHERE yv.status = 'active' AND c.name IN ($placeholders) 
                      ORDER BY yv.title";
            
            // Count query
            $count_query = "SELECT COUNT(DISTINCT yv.video_id) 
                            FROM youtube_videos yv 
                            JOIN video_categories vc ON yv.video_id = vc.video_id 
                            JOIN categories c ON vc.category_id = c.id 
                            WHERE yv.status = 'active' AND c.name IN ($placeholders)";
            $count_stmt = $pdo->prepare($count_query);
            $count_stmt->execute($valid_categories);
            $total_videos = $count_stmt->fetchColumn();
            $total_pages = max(1, ceil($total_videos / $per_page));
            $page = min($page, $total_pages);

            // Fetch videos
            $offset = ($page - 1) * $per_page;
            $query .= " LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($query);
            $params = array_merge($valid_categories, [$per_page, $offset]);
            $stmt->execute($params);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all categories for each video
            foreach ($videos as &$video) {
                $cat_stmt = $pdo->prepare("SELECT c.name 
                                           FROM categories c 
                                           JOIN video_categories vc ON c.id = vc.category_id 
                                           WHERE vc.video_id = ? 
                                           ORDER BY c.name");
                $cat_stmt->execute([$video['video_id']]);
                $video['categories'] = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            unset($video);
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $videos = [];
    $available_categories = [];
    $total_pages = 1;
    $page = 1;

    // Fallback: Try a simple query
    try {
        $stmt = $pdo->query("SELECT video_id, title, artist, length_seconds, thumbnail_link 
                             FROM youtube_videos 
                             WHERE status = 'active' 
                             ORDER BY title 
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
    <title>Category Videos - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Category Videos</h2>
        <div class="content-section">
            <div class="form-container">
                <form method="GET" action="category.php">
                    <div class="form-group">
                        <label>Select Categories:</label>
                        <div class="categories-container">
                            <?php if (empty($available_categories)): ?>
                                <p>No categories available.</p>
                            <?php else: ?>
                                <?php foreach ($available_categories as $cat): ?>
                                    <div class="category-item">
                                        <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" id="cat-<?php echo htmlspecialchars($cat); ?>" <?php echo in_array($cat, $categories) ? 'checked' : ''; ?>>
                                        <label for="cat-<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="form-button">Show Videos</button>
                </form>
            </div>

            <?php if ($message): ?>
                <p class="form-message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <p class="no-videos"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (!empty($categories)): ?>
                <?php if (empty($videos)): ?>
                    <p class="no-videos">No videos found for selected categories.</p>
                <?php else: ?>
                    <form method="GET" action="category.php" class="form-container">
                        <?php foreach ($categories as $cat): ?>
                            <input type="hidden" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                        <div class="form-group">
                            <label for="per_page">Results per page:</label>
                            <select id="per_page" name="per_page" onchange="this.form.submit()">
                                <?php foreach ([10, 25, 50, 100] as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <div class="video-list">
                        <?php foreach ($videos as $video): ?>
                            <?php
                            $minutes = floor($video['length_seconds'] / 60);
                            $seconds = $video['length_seconds'] % 60;
                            $duration = sprintf("%d:%02d", $minutes, $seconds);
                            $categories_list = implode(', ', array_map('htmlspecialchars', $video['categories']));
                            ?>
                            <div class="video-item">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_link']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                <h4><a href="https://youtu.be/<?php echo htmlspecialchars($video['video_id']); ?>" target="_blank"><?php echo htmlspecialchars($video['title']); ?></a></h4>
                                <p><?php echo htmlspecialchars($video['artist']); ?></p>
                                <p class="categories">Categories: <?php echo $categories_list ?: 'None'; ?></p>
                                <p class="duration"><?php echo $duration; ?></p>
                                <button class="copy-command-btn" data-youtube-link="https://youtu.be/<?php echo htmlspecialchars($video['video_id']); ?>">Copy Song Request Command</button>
                                <?php if (isset($_SESSION['twitch_user']['is_streamer']) && $_SESSION['twitch_user']['is_streamer'] || isset($_SESSION['twitch_user']['is_moderator']) && $_SESSION['twitch_user']['is_moderator']): ?>
                                    <div class="button-container">
                                        <button class="edit-btn" data-video-id="<?php echo htmlspecialchars($video['video_id']); ?>">Edit</button>
                                        <button class="refresh-btn" data-video-id="<?php echo htmlspecialchars($video['video_id']); ?>"><span class="refresh-icon">↻</span></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="category.php?<?php echo http_build_query(array_merge(['categories' => $categories, 'per_page' => $per_page, 'page' => $page - 1])); ?>" class="pagination-link">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="category.php?<?php echo http_build_query(array_merge(['categories' => $categories, 'per_page' => $per_page, 'page' => $i])); ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="category.php?<?php echo http_build_query(array_merge(['categories' => $categories, 'per_page' => $per_page, 'page' => $page + 1])); ?>" class="pagination-link">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-videos">Select categories to view videos.</p>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>