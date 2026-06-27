<?php
// cron.php - Booking Status Engine for automatic updates
// This should be called daily via cron or Windows Task Scheduler
require_once __DIR__ . '/USER/config.php';
require_once __DIR__ . '/includes/Logger.php';

try {
    $pdo->beginTransaction();

    // 1. Find all expired subscriptions
    $stmt = $pdo->prepare("SELECT subscription_id, table_id, user_id FROM user_subscriptions WHERE expiry_date < CURDATE() AND subscription_status = 'Active'");
    $stmt->execute();
    $expiredSubs = $stmt->fetchAll();

    foreach ($expiredSubs as $sub) {
        // Update subscription
        $updSub = $pdo->prepare("UPDATE user_subscriptions SET subscription_status = 'Completed' WHERE subscription_id = ?");
        $updSub->execute([$sub['subscription_id']]);

        // Update bookings related to this user and table that are active
        $updBook = $pdo->prepare("UPDATE bookings SET booking_status = 'Completed' WHERE user_id = ? AND table_id = ? AND booking_status = 'Active'");
        $updBook->execute([$sub['user_id'], $sub['table_id']]);

        // Release Table if it's not marked as maintenance and the current user is this user
        $updTable = $pdo->prepare("UPDATE library_tables SET status = 'Available', current_user_id = NULL WHERE table_id = ? AND current_user_id = ? AND status != 'Maintenance'");
        $updTable->execute([$sub['table_id'], $sub['user_id']]);
        
        Logger::logAudit($pdo, 'System Cron', 'Subscription Expired', $sub['user_id'], null);
        
        // Notify admin
        $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('General', 'Subscription Expired', ?)");
        $notifStmt->execute(["Subscription ID " . $sub['subscription_id'] . " expired. Table T-" . $sub['table_id'] . " released."]);
    }

    $pdo->commit();
    echo "Cron completed successfully. " . count($expiredSubs) . " subscriptions updated.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Cron Error: " . $e->getMessage());
    echo "Cron Error: " . $e->getMessage() . "\n";
}
?>
