<?php
// Start the session if needed in the future
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newest Additions</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content">
        <img src="images/tvc.png" alt="TVC Logo" class="main-image">
        <h2>Newest Additions</h2>
        <p>Check out the latest songs added to our collection.</p>
        <div class="content-section">
            <h3>Recent Tracks</h3>
            <p>New songs will be listed here soon!</p>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>