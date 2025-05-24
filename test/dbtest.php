<?php
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$sslmode = 'require';

$dsn = "pgsql:host=$host;dbname=$db;sslmode=$sslmode";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->query("SELECT * FROM miketesttable");

    echo "<h2>Results:</h2>";

    $rowsFound = false;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowsFound = true;
        $id = htmlspecialchars($row['id']);
        $name = htmlspecialchars($row['name']);
        echo "<p>ID: $id<br>Name: $name</p><hr>";
    }

    if (!$rowsFound) {
        echo "<p>No rows found in the table.</p>";
    }

} catch (PDOException $e) {
    echo "Connection failed: " . htmlspecialchars($e->getMessage());
}
?>

