<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateStmt = $pdo->prepare("UPDATE system_notifications SET is_read = 1 WHERE is_read = 0");
    $updateStmt->execute();
    echo "OK";
}
?>
