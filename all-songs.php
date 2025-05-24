<?php
// Start the session if needed in the future
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Songs</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content">
        <img src="images/tvc.png" alt="TVC Logo" class="main-image">
        <h2>All Songs</h2>
        <p>Browse our complete catalog of songs across all categories.</p>
        <div class="content-section">
            <h3>Song Catalog</h3>
            <p>Full song list coming soon!</p>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>