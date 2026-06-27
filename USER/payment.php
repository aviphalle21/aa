<?php
require_once 'config.php';
require_once 'payment_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$table_id = isset($_GET['table_id']) ? $_GET['table_id'] : '';

if (empty($table_id)) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$plansStmt = $pdo->query("SELECT plan_id, plan_name, price, duration_days FROM subscription_plans WHERE active = 1");
$plans = $plansStmt->fetchAll();

$success = isset($_GET['success']) && $_GET['success'] == 1;
$failed = isset($_GET['failed']) && $_GET['failed'] == 1;
$error = '';

function jsonResponse(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function buildUpiUrl(string $reference, float $amount): string
{
    return 'upi://pay?' . http_build_query([
        'pa' => PAYMENT_UPI_ID,
        'pn' => PAYMENT_PAYEE_NAME,
        'am' => number_format($amount, 2, '.', ''),
        'cu' => PAYMENT_CURRENCY,
        'tn' => 'Library table booking ' . $reference,
        'tr' => $reference,
    ]);
}

function completePaidBooking(PDO $pdo, int $user_id, string $table_number, string $payment_reference, array $user): array
{
    $pdo->beginTransaction();
    try {
        $paymentStmt = $pdo->prepare("\n            SELECT p.payment_id, p.subscription_id, p.amount, p.payment_status, p.payment_date, us.plan_id, us.table_id, us.expiry_date, sp.duration_days\n            FROM payments p\n            JOIN user_subscriptions us ON p.subscription_id = us.subscription_id\n            JOIN subscription_plans sp ON us.plan_id = sp.plan_id\n            WHERE p.payment_reference = ? AND p.user_id = ?\n            FOR UPDATE\n        ");
        $paymentStmt = $pdo->prepare("\n            SELECT p.payment_id, p.subscription_id, p.amount, p.payment_status, us.plan_id, us.table_id, us.expiry_date, sp.duration_days\n            FROM payments p\n            JOIN user_subscriptions us ON p.subscription_id = us.subscription_id\n            JOIN subscription_plans sp ON us.plan_id = sp.plan_id\n            WHERE p.payment_reference = ? AND p.user_id = ?\n            FOR UPDATE\n        ");
        $paymentStmt->execute([$payment_reference, $user_id]);
        $payment = $paymentStmt->fetch();

        if (!$payment) {
            throw new Exception('Payment request was not found.');
        }

        if (PAYMENT_AUTO_CONFIRM_DEMO && $payment['payment_status'] === 'Pending') {
            $createdAt = strtotime($payment['payment_date']);
            $secondsSinceCreated = $createdAt ? time() - $createdAt : PAYMENT_AUTO_CONFIRM_AFTER_SECONDS;
            if ($secondsSinceCreated >= PAYMENT_AUTO_CONFIRM_AFTER_SECONDS) {
                $demoPay = $pdo->prepare("UPDATE payments SET payment_status = 'Paid', payment_date = NOW() WHERE payment_id = ?");
                $demoPay->execute([$payment['payment_id']]);
                $payment['payment_status'] = 'Paid';
            } else {
                $pdo->commit();
                $remainingSeconds = PAYMENT_AUTO_CONFIRM_AFTER_SECONDS - $secondsSinceCreated;
                return ['completed' => false, 'message' => "Please finish the UPI payment. Booking will continue automatically in about {$remainingSeconds} seconds."];
            }
        }

        if ($payment['payment_status'] === 'Failed' || $payment['payment_status'] === 'Refunded') {
            $pdo->commit();
            return ['completed' => false, 'failed' => true, 'redirect' => 'payment.php?table_id=' . urlencode($table_number) . '&failed=1', 'message' => 'Payment failed or was refunded.'];
            $demoPay = $pdo->prepare("UPDATE payments SET payment_status = 'Paid', payment_date = NOW() WHERE payment_id = ?");
            $demoPay->execute([$payment['payment_id']]);
            $payment['payment_status'] = 'Paid';
        }

        if ($payment['payment_status'] !== 'Paid') {
            $pdo->commit();
            return ['completed' => false, 'message' => 'Waiting for bank confirmation.'];
        }

        $activeStmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE user_id = ? AND table_id = ? AND booking_status = 'Active'");
        $activeStmt->execute([$user_id, $payment['table_id']]);
        if (!$activeStmt->fetch()) {
            $tableStmt = $pdo->prepare("SELECT status FROM library_tables WHERE table_id = ? FOR UPDATE");
            $tableStmt->execute([$payment['table_id']]);
            $table = $tableStmt->fetch();
            if (!$table || $table['status'] === 'Booked') {
                throw new Exception('This table is no longer available. Please contact admin for refund/support.');
            }

            $subUpdate = $pdo->prepare("UPDATE user_subscriptions SET payment_status = 'Paid', subscription_status = 'Active', start_date = CURDATE(), expiry_date = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE subscription_id = ?");
            $subUpdate->execute([$payment['duration_days'], $payment['subscription_id']]);

            $bookingRef = 'BK-' . mt_rand(100000, 999999);
            $bookStmt = $pdo->prepare("INSERT INTO bookings (user_id, table_id, start_date, expiry_date, booking_status, booking_reference, plan_price, booking_price) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'Active', ?, ?, ?)");
            $bookStmt->execute([$user_id, $payment['table_id'], $payment['duration_days'], $bookingRef, $payment['amount'], $payment['amount']]);

            $updTable = $pdo->prepare("UPDATE library_tables SET status = 'Booked', current_user_id = ? WHERE table_id = ?");
            $updTable->execute([$user_id, $payment['table_id']]);

            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('payment', 'New Payment & Booking', ?)");
            $notifStmt->execute([$user['full_name'] . " paid Rs " . $payment['amount'] . " and booked Table T-$table_number."]);

            @mail($user['email'], 'Booking Confirmed - Saraswati Abhyasika', "Hello {$user['full_name']},\n\nYour booking for Table T-$table_number is confirmed!\n\nBooking Reference: $bookingRef\nAmount Paid: ₹{$payment['amount']}\n\nThank you for choosing Saraswati Abhyasika!", "From: noreply@saraswatiabhyasika.com\r\nReply-To: noreply@saraswatiabhyasika.com\r\nX-Mailer: PHP/" . phpversion());
            file_put_contents('notification_logs.txt', '[' . date('Y-m-d H:i:s') . "] SMS queued for {$user['phone']}: 'Your booking for Table T-$table_number is confirmed. Amount Paid: Rs {$payment['amount']}.'\n", FILE_APPEND);
        }

        $pdo->commit();
        return ['completed' => true, 'redirect' => 'payment.php?table_id=' . urlencode($table_number) . '&success=1'];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'init_payment') {
            $plan_id = $_POST['plan_id'] ?? '';
            if (empty($plan_id)) {
                throw new Exception('Please select a plan.');
            }

            $checkSub = $pdo->prepare("SELECT subscription_id FROM user_subscriptions WHERE user_id = ? AND subscription_status = 'Active' AND payment_status = 'Paid'");
            $checkSub->execute([$user_id]);
            if ($checkSub->fetch()) {
                throw new Exception('You already have an active table booking. You cannot book multiple tables.');
            }

            $pStmt = $pdo->prepare("SELECT price, duration_days FROM subscription_plans WHERE plan_id = ? AND active = 1");
            $pStmt->execute([$plan_id]);
            $selectedPlan = $pStmt->fetch();
            if (!$selectedPlan) {
                throw new Exception('Invalid plan selected.');
            }

            $tStmt = $pdo->prepare("SELECT table_id, status FROM library_tables WHERE table_number = ?");
            $tStmt->execute([$table_id]);
            $tableRow = $tStmt->fetch();
            if (!$tableRow || $tableRow['status'] === 'Booked') {
                throw new Exception('This table is no longer available.');
            }

            $pdo->beginTransaction();
            $reference = 'UPI-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
            $subStmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, table_id, start_date, expiry_date, amount_paid, payment_status, subscription_status) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 'Pending', 'Active')");
            $subStmt->execute([$user_id, $plan_id, $tableRow['table_id'], $selectedPlan['duration_days'], $selectedPlan['price']]);
            $subscription_id = $pdo->lastInsertId();
            $payStmt = $pdo->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, payment_status, payment_reference, payment_date) VALUES (?, ?, ?, 'UPI', 'Pending', ?, NOW())");
            $payStmt->execute([$user_id, $subscription_id, $selectedPlan['price'], $reference]);
            $pdo->commit();

            jsonResponse(['ok' => true, 'reference' => $reference, 'amount' => $selectedPlan['price'], 'upi_url' => buildUpiUrl($reference, (float) $selectedPlan['price'])]);
        }

        if ($_POST['action'] === 'check_payment') {
            $reference = $_POST['reference'] ?? '';
            if (empty($reference)) {
                throw new Exception('Payment reference is missing.');
            }
            jsonResponse(['ok' => true] + completePaidBooking($pdo, $user_id, $table_id, $reference, $user));
        }
    } catch (Exception $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Renewal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .checkout-card { max-width: 600px; margin: 40px auto; }
        .success-box {
            text-align: center; padding: 40px;
        }
        .success-box h1 { color: var(--success, #10b981); font-size: 2.5rem; margin-bottom: 20px; }
        .form-control {
            width: 100%; padding: 12px 15px; margin-bottom: 20px;
            border-radius: 12px; border: 2px solid var(--border-color);
            background: var(--bg-primary); color: var(--text-main); font-size: 1rem;
        }
        .payment-status {
            display: none; padding: 16px; border-radius: 12px; margin-bottom: 20px;
            background: #eff6ff; color: #1d4ed8; text-align: center; font-weight: 600;
        }
        .payment-error { background: #fee2e2; color: #dc2626; }
        .upi-pay-link { display: inline-block; margin-top: 15px; text-decoration: none; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Logo">
        <h1>सरस्वती अभ्यासिका - Checkout</h1>
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="btn-primary" style="text-decoration:none; background: var(--bg-primary); color: var(--text-main); border: 1px solid var(--border-color);">Cancel & Back</a>
    </div>
</nav>

<div class="dashboard-container" style="display:block;">
    <div class="card checkout-card">
        
        <?php if($success): ?>
            <div class="success-box">
                <h1>✅ Booking Confirmed!</h1>
                <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 20px;">Your payment was successful and Table <strong style="color:var(--text-main);">T-<?= htmlspecialchars($table_id) ?></strong> is now officially yours!</p>
                <p style="margin-bottom: 30px; font-size: 0.9rem; color: var(--text-muted);"><em>We have sent a confirmation SMS to <?= htmlspecialchars($user['phone']) ?> and an email to <?= htmlspecialchars($user['email']) ?>.</em></p>
                <a href="dashboard.php" class="btn-primary" style="text-decoration:none;">Go to Dashboard</a>
            </div>
        <?php elseif($failed): ?>
            <div class="success-box">
                <h1 style="color:#dc2626;">❌ Payment Failed</h1>
                <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 20px;">Your payment for Table <strong style="color:var(--text-main);">T-<?= htmlspecialchars($table_id) ?></strong> was not completed.</p>
                <a href="payment.php?table_id=<?= urlencode($table_id) ?>" class="btn-primary" style="text-decoration:none;">Try Again</a>
                <a href="dashboard.php" class="btn-primary" style="text-decoration:none; background:#64748b; margin-left:10px;">Back to Dashboard</a>
            </div>
        <?php else: ?>
        
            <h2 style="text-align: center;">Complete Your Booking</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 30px;">You are booking Table <strong style="color:#3b82f6;">T-<?= htmlspecialchars($table_id) ?></strong></p>
            
            <?php if ($error): ?>
                <div style="background:#fee2e2; color:#dc2626; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form id="paymentForm" method="POST" action="payment.php?table_id=<?= urlencode($table_id) ?>">
                <label style="font-weight:600; margin-bottom:8px; display:block;">1. Select Plan</label>
                <select class="form-control" name="plan_id" id="planSelect" required>
                    <option value="">-- Choose a Plan --</option>
                    <?php foreach($plans as $plan): ?>
                        <option value="<?= htmlspecialchars($plan['plan_id']) ?>" data-price="<?= htmlspecialchars($plan['price']) ?>">
                            <?= htmlspecialchars($plan['plan_name']) ?> - ₹<?= htmlspecialchars($plan['price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="qr-container" id="qrContainer" style="display:none; background:var(--bg-primary); padding:20px; border-radius:15px; border:1px solid var(--border-color); margin-bottom:25px;">
                    <label style="font-weight:600; display:block; margin-bottom:15px;">2. Scan & Pay</label>
                    <div class="price-tag" id="priceDisplay"></div>
                    <img id="dynamicQrImg" src="" alt="Payment QR Code">
                    <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-muted);">Scan with GPay, PhonePe, or Paytm. Keep this page open; it will check the payment and move forward automatically.</p>
                    <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-muted);">Scan with GPay, PhonePe, or Paytm.</p>
                    <a id="upiPayLink" class="btn-primary upi-pay-link" href="#">Open UPI App</a>
                </div>

                <div id="paymentStatus" class="payment-status"></div>
            </form>

        <?php endif; ?>
    </div>
</div>

<script>
    const planSelect = document.getElementById('planSelect');
    const qrContainer = document.getElementById('qrContainer');
    const priceDisplay = document.getElementById('priceDisplay');
    const statusBox = document.getElementById('paymentStatus');
    const upiPayLink = document.getElementById('upiPayLink');
    const dynamicQrImg = document.getElementById('dynamicQrImg');
    let activeReference = '';
    let pollTimer = null;

    function showStatus(message, isError = false) {
        statusBox.textContent = message;
        statusBox.className = 'payment-status' + (isError ? ' payment-error' : '');
        statusBox.style.display = 'block';
    }

    async function postPaymentAction(action, extra = {}) {
        const body = new URLSearchParams({ action, ...extra });
        const response = await fetch('payment.php?table_id=<?= urlencode($table_id) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        return response.json();
    }

    async function pollPaymentStatus() {
        if (!activeReference) return;

        try {
            const result = await postPaymentAction('check_payment', { reference: activeReference });
            if (!result.ok) {
                showStatus(result.message || 'Unable to verify payment right now.', true);
                return;
            }

            if (result.completed) {
                showStatus('Payment confirmed. Redirecting to your booking...');
                window.location.href = result.redirect;
                return;
            }

            if (result.failed) {
                showStatus(result.message || 'Payment failed. Redirecting to failed report...', true);
                window.location.href = result.redirect;
                return;
            }

            showStatus(result.message || 'Waiting for bank confirmation. Please complete payment in your UPI app.');
        } catch (error) {
            showStatus('Payment verification is temporarily unavailable. We will keep checking automatically.', true);
        }
    }

    async function createPaymentRequest() {
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        const planId = planSelect.value;

        clearInterval(pollTimer);
        activeReference = '';

        if (!price || !planId) {
            qrContainer.style.display = 'none';
            statusBox.style.display = 'none';
            return;
        }

        priceDisplay.textContent = 'Amount to Pay: ₹' + price;
        qrContainer.style.display = 'block';
        showStatus('Creating secure payment request...');

        try {
            const result = await postPaymentAction('init_payment', { plan_id: planId });
            if (!result.ok) {
                qrContainer.style.display = 'none';
                showStatus(result.message || 'Could not start payment.', true);
                return;
            }

            activeReference = result.reference;
            dynamicQrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(result.upi_url)}`;
            showStatus(`Payment request ${activeReference} is ready. Scan the QR; this page will fetch payment status and go forward automatically.`);
            pollPaymentStatus();
            pollTimer = setInterval(pollPaymentStatus, <?= PAYMENT_POLL_SECONDS * 1000 ?>);
        } catch (error) {
            qrContainer.style.display = 'none';
            showStatus('Could not create payment request. Please try again.', true);
        }
            qrContainer.style.display = 'none';
            statusBox.style.display = 'none';
            return;
        }

        priceDisplay.textContent = 'Amount to Pay: ₹' + price;
        qrContainer.style.display = 'block';
        showStatus('Creating secure payment request...');

        try {
            const result = await postPaymentAction('init_payment', { plan_id: planId });
            if (!result.ok) {
                qrContainer.style.display = 'none';
                showStatus(result.message || 'Could not start payment.', true);
                return;
            }

            activeReference = result.reference;
            dynamicQrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(result.upi_url)}`;
            upiPayLink.href = result.upi_url;
            showStatus(`Payment request ${activeReference} is ready. After your bank confirms payment, booking will continue automatically.`);
            pollPaymentStatus();
            pollTimer = setInterval(pollPaymentStatus, <?= PAYMENT_POLL_SECONDS * 1000 ?>);
        } catch (error) {
            qrContainer.style.display = 'none';
            showStatus('Could not create payment request. Please try again.', true);
        }
    }

    if (document.getElementById('paymentForm')) {
        document.getElementById('paymentForm').addEventListener('submit', function(event) {
            event.preventDefault();
        });
    }

    if (planSelect) {
        planSelect.addEventListener('change', createPaymentRequest);
    }
</script>

</body>
</html>
