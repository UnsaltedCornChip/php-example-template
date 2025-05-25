<?php
// Header content inspired by tvcsonglist.com
?>
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
            <li class="dropdown">
                <a href="#" class="dropbtn">Admin</a>
                <div class="dropdown-content">
                    <a href="add_video.php">Add Video</a>
                    <a href="bulk_upload.php">Bulk Upload</a>
                </div>
            </li>
            <li>
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme">
                    <span class="toggle-icon">☾</span>
                </button>
            </li>
        </ul>
    </nav>
</header>