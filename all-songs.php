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

    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], ['10', '25', '50', '100']) ? (int)$_GET['per_page'] : 25;

    // Build the query
    $query = "SELECT video_id, title, artist, length_seconds, thumbnail_link 
              FROM youtube_videos 
              WHERE status = 'active'";
    $params = [];
    
    if ($search) {
        $query .= " AND (LOWER(title) LIKE LOWER(:search) OR LOWER(artist) LIKE LOWER(:search))";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query .= " ORDER BY title";
    
    // Get total count for pagination
    $count_query = str_replace('video_id, title, artist, length_seconds, thumbnail_link', 'COUNT(*)', $query);
    $count_query = str_replace(' ORDER BY title', '', $count_query); // Remove ORDER BY for count
    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_videos = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_videos / $per_page));
    $page = min($page, $total_pages);
    
    // Fetch videos for current page
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT :per_page OFFSET :offset";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check for messages
    $message = '';
    if (isset($_GET['status'])) {
        if ($_GET['status'] === 'success') {
            $message = $_GET['message'] ?? 'Video metadata refreshed successfully!';
        } elseif ($_GET['status'] === 'error') {
            $message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'An error occurred.';
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $videos = [];
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
    <title>All Songs - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>All Songs</h2>
        <div class="content-section">
            <form method="GET" action="all-songs.php" class="form-container">
                <div class="form-group">
                    <label for="search">Search by Title or Artist:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter title or artist">
                </div>
                <div class="form-group">
                    <label for="per_page">Results per page:</label>
                    <select id="per_page" name="per_page" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50, 100] as $option): ?>
                            <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="form-button">Search</button>
            </form>
            <?php if ($message): ?>
                <p class="form-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="no-videos"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (empty($videos)): ?>
                <p class="no-videos">No songs found<?php echo $search ? ' for "' . htmlspecialchars($search) . '"' : ''; ?>.</p>
            <?php else: ?>
                <div class="video-list">
                    <?php foreach ($videos as $video): ?>
                        <?php
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
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="all-songs.php?search=<?php echo urlencode($search); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="all-songs.php?search=<?php echo urlencode($search); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="all-songs.php?search=<?php echo urlencode($search); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>