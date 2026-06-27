<?php
require_once 'config.php';
require_once 'payment_config.php';

header('Content-Type: application/json');

$secret = $_POST['secret'] ?? $_GET['secret'] ?? '';
$reference = $_POST['reference'] ?? $_GET['reference'] ?? '';
$status = $_POST['status'] ?? $_GET['status'] ?? 'Paid';

if (!hash_equals(PAYMENT_WEBHOOK_SECRET, $secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid webhook secret.']);
    exit;
}

if (empty($reference) || !in_array($status, ['Paid', 'Failed', 'Refunded'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Provide a valid reference and status.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE payments p JOIN user_subscriptions us ON p.subscription_id = us.subscription_id SET p.payment_status = ?, p.payment_date = NOW(), us.payment_status = ? WHERE p.payment_reference = ?");
$stmt->execute([$status, $status, $reference]);

echo json_encode([
    'ok' => $stmt->rowCount() > 0,
    'message' => $stmt->rowCount() > 0 ? 'Payment status updated. The user page will auto-complete on its next check.' : 'Payment reference not found.',
]);
?>
