<header class="titlebar">
    <div class="logo">
        <h1>TVC Twitch Song List</h1>
    </div>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li class="dropdown">
                <a href="#" class="dropbtn">TVC Songs</a>
                <div class="dropdown-content">
                    <a href="originals.php">Originals</a>
                    <a href="covers.php">Covers</a>
                    <a href="loops.php">Loops</a>
                    <a href="uke-vocals.php">Uke/Vocals</a>
                </div>
            </li>
            <li><a href="all-songs.php">All Songs</a></li>
            <li><a href="newest-additions.php">Newest Additions</a></li>
            <li><a href="artists.php">Artists</a></li>
            <li><a href="random.php">Random</a></li>

            <?php
                $user = $_SESSION['twitch_user'] ?? null;
                $isStreamer = $user['is_streamer'] ?? false;
                $isModerator = $user['is_moderator'] ?? false;
                $isViewer = $user['is_viewer'] ?? false;

                $role = 'Unknown';
                if ($isStreamer) {
                    $role = 'Streamer';
                } elseif ($isModerator) {
                    $role = 'Moderator';
                } elseif ($isViewer) {
                    $role = 'Viewer';
                }
            ?>
            <?php if ($isStreamer || $isModerator): ?>
            <li class="dropdown">
                <a href="#" class="dropbtn">Admin</a>
                <div class="dropdown-content">
                    <a href="add_video.php">Add Video</a>
                    <a href="bulk_upload.php">Bulk Upload</a>
                    <a href="manage_categories.php">Categories</a>
                    <a href="import_playlist.php">Import Playlist</a>
                    <a href="manage_inactive_videos.php">Inactive Videos</a>
                </div>
            </li>
            <?php endif; ?>
            <li class="dropdown">
                <?php if ($user): ?>
                    <a href="#" class="dropbtn">
                        Welcome, <?php echo htmlspecialchars($user['login']); ?> (<?php echo $role; ?>)
                    </a>
                    <div class="dropdown-content">
                        <a href="logout.php">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="twitch_login.php" class="auth-link">Login</a>
                <?php endif; ?>
            </li>
            <li>
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme">
                    <span class="toggle-icon">☾</span>
                </button>
            </li>
        </ul>
    </nav>
</header>