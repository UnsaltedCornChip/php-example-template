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
    $artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], ['10', '25', '50', '100']) ? (int)$_GET['per_page'] : 25;

    if ($artist) {
        // Fetch videos for the selected artist
        $query = "SELECT video_id, title, artist, length_seconds, thumbnail_link 
                  FROM youtube_videos 
                  WHERE status = 'active' AND artist = :artist 
                  ORDER BY title";
        $params = [':artist' => $artist];

        // Get total count for pagination
        $count_query = "SELECT COUNT(*) 
                        FROM youtube_videos 
                        WHERE status = 'active' AND artist = :artist";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($params);
        $total_videos = $count_stmt->fetchColumn();
        $total_pages = max(1, ceil($total_videos / $per_page));
        $page = min($page, $total_pages);

        // Fetch videos for current page
        $offset = ($page - 1) * $per_page;
        $query .= " LIMIT :per_page OFFSET :offset";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':artist', $artist);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fetch all unique artists
        $stmt = $pdo->query("SELECT DISTINCT artist 
                             FROM youtube_videos 
                             WHERE status = 'active' 
                             ORDER BY artist");
        $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $videos = [];
    $artists = [];
    $total_pages = 1;
    $page = 1;

    // Fallback: Try a simple query
    try {
        if ($artist) {
            $stmt = $pdo->query("SELECT video_id, title, artist, length_seconds, thumbnail_link 
                                 FROM youtube_videos 
                                 WHERE status = 'active' 
                                 ORDER BY title 
                                 LIMIT 10");
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT artist 
                                 FROM youtube_videos 
                                 WHERE status = 'active' 
                                 ORDER BY artist 
                                 LIMIT 10");
            $artists = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
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
    <title>Artists - TVC Twitch Song List</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="main-content">
        <h2>Artists</h2>
        <div class="content-section">
            <?php if ($message): ?>
                <p class="form-message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="no-videos"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif ($artist): ?>
                <p><a href="artists.php" class="back-link">Back to Artists</a></p>
                <h3>Songs by <?php echo htmlspecialchars($artist); ?></h3>
                <form method="GET" action="artists.php" class="form-container">
                    <input type="hidden" name="artist" value="<?php echo htmlspecialchars($artist); ?>">
                    <div class="form-group">
                        <label for="per_page">Results per page:</label>
                        <select id="per_page" name="per_page" onchange="this.form.submit()">
                            <?php foreach ([10, 25, 50, 100] as $option): ?>
                                <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php if (empty($videos)): ?>
                    <p class="no-videos">No songs found for "<?php echo htmlspecialchars($artist); ?>".</p>
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
                            <a href="artists.php?artist=<?php echo urlencode($artist); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="artists.php?artist=<?php echo urlencode($artist); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="artists.php?artist=<?php echo urlencode($artist); ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($artists)): ?>
                    <p class="no-videos">No artists found.</p>
                <?php else: ?>
                    <ul class="artist-list">
                        <?php foreach ($artists as $artist_name): ?>
                            <li><a href="artists.php?artist=<?php echo urlencode($artist_name); ?>"><?php echo htmlspecialchars($artist_name); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>