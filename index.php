<?php
// Start the session
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TVC Twitch Song List</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content">
        <img src="images/tvc.png" alt="TVC Logo" class="main-image">
        <h2>TVC Stream Song List</h2>
        <p>Here is a page that lists all of the songs that can be requested during a music stream.</p>
        <p>Standard Requests includes uke singing | subs, 5.00/500 bits = up to 5 min limit<br>
            Extended Requests | $10/1000 bits = up to 10 min limit<br>
            Looping Requests | $15/1500 bits
        </p>
        <div class="content-section">
            <h3>Select below to begin searching for songs!</h3>
            <div class="song-links-container">
                <ul class="song-links">
                    <li><a href="originals.php">TVC Originals</a></li>
                    <li><a href="loops.php">TVC Loops</a></li>
                    <li><a href="covers.php">TVC Covers</a></li>
                    <li><a href="uke-vocals.php">Uke & Vocals</a></li>
                </ul>
                <ul class="song-links">
                    <li><a href="category.php">Search by Category</a></li>
                    <li><a href="all-songs.php">Search All Songs</a></li>
                    <li><a href="artists.php">Search by Artist</a></li>
                    <li><a href="random.php">Pick 20 Random Songs</a></li>
                </ul>
            </div>
        </div>
        <div class="content-section">
            <h3>Connect with TVC</h3>
            <ul class="connect-links">
                <li><a href="https://jams.eliz.live/c/ThatViolinChick" target="_blank">Jamsbot Song Queue</a></li>
                <li><a href="https://discord.gg/thatviolinchick" target="_blank">Discord Community</a></li>
                <li><a href="https://www.patreon.com/thatviolinchick" target="_blank">ThatViolinChick Patreon</a></li>
                <li><a href="https://twitch.tv/thatviolinchick" target="_blank">ThatViolinChick Twitch Channel</a></li>
                <li><a href="https://www.youtube.com/thatviolinchick" target="_blank">ThatViolinChick YouTube Channel</a></li>
            </ul>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>