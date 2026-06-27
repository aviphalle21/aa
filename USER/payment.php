<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$table_id = isset($_GET['table_id']) ? $_GET['table_id'] : '';

// Handle Missing Table ID
if (empty($table_id)) {
    header("Location: dashboard.php");
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch Plans
$plansStmt = $pdo->query("SELECT plan_id, plan_name, price, duration_days FROM subscription_plans WHERE active = 1");
$plans = $plansStmt->fetchAll();

$success = false;
$error = '';

// Process Simulated Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    
    if (empty($plan_id)) {
        $error = 'Please select a plan.';
    } else {
        try {
            // Check if user already has an active booking
            $checkSub = $pdo->prepare("SELECT subscription_id FROM user_subscriptions WHERE user_id = ? AND subscription_status = 'Active'");
            $checkSub->execute([$user_id]);
            if ($checkSub->fetch()) {
                throw new Exception("You already have an active table booking. You cannot book multiple tables.");
            }

            $pdo->beginTransaction();
            
            // Generate simulated transaction ID
            $txn_id = "SIM-UPI-" . mt_rand(1000000, 9999999);
            
            // 1. Get Plan Details
            $pStmt = $pdo->prepare("SELECT price, duration_days FROM subscription_plans WHERE plan_id = ?");
            $pStmt->execute([$plan_id]);
            $selectedPlan = $pStmt->fetch();
            
            if (!$selectedPlan) {
                throw new Exception("Invalid plan selected.");
            }
            
            // 2. Fetch actual Table ID (we have table_number in GET, need table_id)
            $tStmt = $pdo->prepare("SELECT table_id, status FROM library_tables WHERE table_number = ?");
            $tStmt->execute([$table_id]);
            $tableRow = $tStmt->fetch();
            
            if (!$tableRow || $tableRow['status'] === 'Booked') {
                throw new Exception("This table is no longer available.");
            }
            $real_table_id = $tableRow['table_id'];
            
            // 3. Create User Subscription first to get subscription_id
            $subStmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, table_id, start_date, expiry_date, amount_paid, subscription_status) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 'Active')");
            $subStmt->execute([$user_id, $plan_id, $real_table_id, $selectedPlan['duration_days'], $selectedPlan['price']]);
            $subscription_id = $pdo->lastInsertId();

            // 4. Create Payment Record (Simulated as Paid)
            $payStmt = $pdo->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, payment_status, payment_reference, payment_date) VALUES (?, ?, ?, 'UPI', 'Paid', ?, NOW())");
            $payStmt->execute([$user_id, $subscription_id, $selectedPlan['price'], $txn_id]);
            $payment_id = $pdo->lastInsertId();
            
            // 5. Create Booking
            $bRef = "BK-" . mt_rand(100000, 999999);
            $bookStmt = $pdo->prepare("INSERT INTO bookings (user_id, table_id, start_date, expiry_date, booking_status, booking_reference, plan_price, booking_price) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'Active', ?, ?, ?)");
            $bookStmt->execute([$user_id, $real_table_id, $selectedPlan['duration_days'], $bRef, $selectedPlan['price'], $selectedPlan['price']]);
            
            // 6. Update Table Status
            $updTable = $pdo->prepare("UPDATE library_tables SET status = 'Booked', current_user_id = ? WHERE table_id = ?");
            $updTable->execute([$user_id, $real_table_id]);
            
            $pdo->commit();
            
            // 7. Insert Notification for Admin
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('payment', 'New Payment & Booking', ?)");
            $notifMsg = $user['full_name'] . " paid Rs " . $selectedPlan['price'] . " and booked Table T-$table_id.";
            $notifStmt->execute([$notifMsg]);

            // 8. SEND REAL EMAIL AND LOG SMS
            $to = $user['email'];
            $subject = "Booking Confirmed - Saraswati Abhyasika";
            $emailMessage = "Hello " . $user['full_name'] . ",\n\nYour booking for Table T-$table_id is confirmed!\n\nBooking Reference: $bRef\nAmount Paid: ₹" . $selectedPlan['price'] . "\n\nThank you for choosing Saraswati Abhyasika!";
            $headers = "From: noreply@saraswatiabhyasika.com\r\nReply-To: noreply@saraswatiabhyasika.com\r\nX-Mailer: PHP/" . phpversion();
            @mail($to, $subject, $emailMessage, $headers);

            $logFile = 'notification_logs.txt';
            $logMsg = "[" . date('Y-m-d H:i:s') . "] SMS queued for " . $user['phone'] . ": 'Your booking for Table T-$table_id is confirmed. Amount Paid: Rs " . $selectedPlan['price'] . ".'\n";
            file_put_contents($logFile, $logMsg, FILE_APPEND);
            
            header("Location: payment.php?table_id=" . urlencode($table_id) . "&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
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
        <?php else: ?>
        
            <h2 style="text-align: center;">Complete Your Booking</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 30px;">You are booking Table <strong style="color:#3b82f6;">T-<?= htmlspecialchars($table_id) ?></strong></p>
            
            <?php if ($error): ?>
                <div style="background:#fee2e2; color:#dc2626; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="payment.php?table_id=<?= urlencode($table_id) ?>">
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
                    <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-muted);">Scan with GPay, PhonePe, or Paytm.</p>
                </div>

                <div id="verificationSection" style="display:none;">
                    <label style="font-weight:600; margin-bottom:8px; display:block;">3. Confirm Booking</label>
                    <button type="submit" class="btn-primary" style="width:100%; padding: 15px; font-size: 1.1rem; background: #10b981; border:none; box-shadow: 0 4px 15px rgba(16,185,129,0.3);">I Have Paid - Confirm Booking</button>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>

<script>
    const planSelect = document.getElementById('planSelect');
    const qrContainer = document.getElementById('qrContainer');
    const verificationSection = document.getElementById('verificationSection');
    const priceDisplay = document.getElementById('priceDisplay');

    planSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        
        if (price) {
            priceDisplay.textContent = 'Amount to Pay: ₹' + price;
            
            // Generate dynamic UPI QR Code
            const upiId = 'pratikshingare2002@okicici';
            const payeeName = 'Saraswati Abhyasika';
            const upiString = `upi://pay?pa=${upiId}&pn=${encodeURIComponent(payeeName)}&am=${price}&cu=INR`;
            const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiString)}`;
            
            document.getElementById('dynamicQrImg').src = qrApiUrl;
            
            qrContainer.style.display = 'block';
            verificationSection.style.display = 'block';
        } else {
            qrContainer.style.display = 'none';
            verificationSection.style.display = 'none';
        }
    });
</script>

</body>
</html>
