<?php
// User/config.php

// Production Error Reporting (Hide from users, log to file)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error_log.txt');
$host = '127.0.0.1';
$db   = 'Library';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log error securely server-side
    error_log("Database connection failed: " . $e->getMessage());
    // Do not expose database errors to users
    exit("A critical system error occurred. Please try again later.");
}

require_once __DIR__ . '/../includes/SessionManager.php';
SessionManager::startSecureSession();

// Session Timeout Logic (5 Minutes Inactivity)
if (isset($_SESSION['user_id'])) {
    $timeout_duration = 300; // 5 minutes in seconds
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        // Session expired
        session_unset();
        session_destroy();
        // Clear auth cookies
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        header("Location: index.php?expired=1");
        exit;
    }
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}
?>
