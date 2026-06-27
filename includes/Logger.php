<?php
// includes/Logger.php

class Logger {
    
    // Log Audit Event
    public static function logAudit($pdo, $action, $result, $userId = null, $adminId = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, admin_id, action, result, ip_address, browser) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $adminId, $action, $result, $ip, $userAgent]);
    }
}
?>
