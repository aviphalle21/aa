<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $deleteStmt = $pdo->prepare("DELETE FROM system_notifications WHERE notification_id = ?");
    $deleteStmt->execute([$id]);
    echo "OK";
}
?>
