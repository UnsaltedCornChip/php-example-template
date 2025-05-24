<?php
// Start the session if needed in the future
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pick 10 Random Songs</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content">
        <img src="images/tvc.png" alt="TVC Logo" class="main-image">
        <h2>Pick 10 Random Songs</h2>
        <p>Discover a random selection of 10 songs from our collection.</p>
        <div class="content-section">
            <h3>Random Song Selection</h3>
            <p>Random song picker coming soon!</p>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>