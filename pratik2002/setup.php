<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB
    $pdo->exec("CREATE DATABASE IF NOT EXISTS Library");
    $pdo->exec("USE Library");

    // Create admin table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        username VARCHAR(50) UNIQUE,
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('Payment','Booking','Expiry','General') DEFAULT 'General',
        is_read TINYINT(1) DEFAULT 0,
        related_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if admin exists
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = 'admin@gmail.com'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admin (name, email, username, password) VALUES ('Super Admin', 'admin@gmail.com', 'admin', ?)");
        $insert->execute([$hash]);
        echo "Admin created successfully.<br>";
    } else {
        echo "Admin already exists.<br>";
    }

    echo "Database setup complete.";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
