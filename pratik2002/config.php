<?php
// Admin config.php

// Production Error Reporting (Hide from users, log to file)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error_log.txt');
$host = 'localhost';
$dbname = 'Library';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Admin Database connection failed: " . $e->getMessage());
    exit("A critical system error occurred. Please try again later.");
}

require_once __DIR__ . '/../includes/SessionManager.php';
SessionManager::startSecureSession();
?>
