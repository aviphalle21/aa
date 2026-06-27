<?php
// db.php - Database Initialization Script
// Runs on first install to set up the database and tables.

$host = '127.0.0.1'; // or localhost
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server without specifying a database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read the SQL file
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        die("Error: database.sql file not found.");
    }

    $sql = file_get_contents($sqlFile);

    // Execute the SQL to create DB, Tables, and insert default data
    $pdo->exec($sql);
    
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #10b981;'>";
    echo "<h2>✅ Database Initialized Successfully!</h2>";
    echo "<p>The 'Library' database has been created and populated.</p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px;'>Go to Website</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #ef4444;'>";
    echo "<h2>❌ Database Initialization Failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
