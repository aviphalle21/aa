<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $table_id = $_POST['table_id'];
    $plan_id = $_POST['plan_id'];
    $amount_paid = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    
    // Fetch plan details to calculate expiry
    $planStmt = $pdo->prepare("SELECT duration_days, price FROM subscription_plans WHERE plan_id = ?");
    $planStmt->execute([$plan_id]);
    $plan = $planStmt->fetch();
    $duration = $plan['duration_days'];
    $plan_price = $plan['price'];
    
    $start_date = date('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime("+$duration days"));

    try {
        $pdo->beginTransaction();

        // 1. Insert Subscription
        $subStmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, table_id, plan_id, start_date, expiry_date, amount_paid, payment_status, subscription_status) VALUES (?, ?, ?, ?, ?, ?, 'Paid', 'Active')");
        $subStmt->execute([$user_id, $table_id, $plan_id, $start_date, $expiry_date, $amount_paid]);
        $subscription_id = $pdo->lastInsertId();

        // 2. Insert Payment
        $payment_ref = 'PAY-' . strtoupper(uniqid());
        $payStmt = $pdo->prepare("INSERT INTO payments (payment_reference, user_id, subscription_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, 'Paid')");
        $payStmt->execute([$payment_ref, $user_id, $subscription_id, $amount_paid, $payment_method]);

        // 3. Update Table Status
        $updateTable = $pdo->prepare("UPDATE library_tables SET status = 'Booked', current_user_id = ? WHERE table_id = ?");
        $updateTable->execute([$user_id, $table_id]);
        
        // 4. Update Booking Status
        $chkBooking = $pdo->query("SHOW TABLES LIKE 'bookings'");
        if($chkBooking->rowCount() > 0) {
            $bRef = "BK-" . mt_rand(100000, 999999);
            $bookStmt = $pdo->prepare("INSERT INTO bookings (user_id, table_id, start_date, expiry_date, booking_status, booking_reference, plan_price, booking_price) VALUES (?, ?, ?, ?, 'Active', ?, ?, ?)");
            $bookStmt->execute([$user_id, $table_id, $start_date, $expiry_date, $bRef, $plan_price, $amount_paid]);
        }
        
        // 5. Notifications
        // Fetch User & Table details for notification
        $uStmt = $pdo->prepare("SELECT full_name, phone FROM users WHERE user_id = ?");
        $uStmt->execute([$user_id]);
        $uData = $uStmt->fetch();
        
        $tStmt = $pdo->prepare("SELECT table_number FROM library_tables WHERE table_id = ?");
        $tStmt->execute([$table_id]);
        $tData = $tStmt->fetch();
        
        // Booking Notification
        $bookNotif = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'Booking')");
        $bookNotif->execute(["New Table Booking", "User {$uData['full_name']} (Phone: {$uData['phone']}) booked Table T-{$tData['table_number']}."]);
        
        // Payment Notification
        $payNotif = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'Payment')");
        $payNotif->execute(["New Payment Received", "Amount of ₹{$amount_paid} received from {$uData['full_name']} via {$payment_method}."]);

        $pdo->commit();
        $alertMessage = "Subscription and Payment added successfully!";
        $alertType = "alert-success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $alertMessage = "Error: " . $e->getMessage();
        $alertType = "alert-error";
    }
}

// Fetch form data
$users = $pdo->query("SELECT user_id, full_name, unique_user_id FROM users ORDER BY full_name")->fetchAll();
$tables = $pdo->query("SELECT table_id, table_number FROM library_tables WHERE status = 'Available' ORDER BY table_number")->fetchAll();
$plans = $pdo->query("SELECT plan_id, plan_name, price FROM subscription_plans WHERE active = 1 ORDER BY duration_days")->fetchAll();

$pageTitle = 'Add Subscription';
$showBackButton = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subscription - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--navy-blue);
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: inherit;
        }
        .btn-submit {
            background: var(--brand-crimson);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background: var(--brand-crimson-dark);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="form-container">
        <h2>Assign Table & Plan</h2>
        <?php if ($alertMessage): ?>
            <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="user_id">Select User</label>
                <select name="user_id" id="user_id" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= $u['unique_user_id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="table_id">Select Available Table</label>
                <select name="table_id" id="table_id" required>
                    <option value="">-- Choose Table --</option>
                    <?php foreach($tables as $t): ?>
                        <option value="<?= $t['table_id'] ?>">Table T-<?= $t['table_number'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="plan_id">Select Subscription Plan</label>
                <select name="plan_id" id="plan_id" required onchange="updateAmount()">
                    <option value="" data-price="0">-- Choose Plan --</option>
                    <?php foreach($plans as $p): ?>
                        <option value="<?= $p['plan_id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['plan_name']) ?> - ₹<?= $p['price'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="amount_paid">Amount Paid (₹)</label>
                <input type="number" id="amount_paid" name="amount_paid" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                    <option value="Card">Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Confirm Booking & Payment</button>
        </form>
    </div>

    <script>
        function updateAmount() {
            var planSelect = document.getElementById('plan_id');
            var selectedOption = planSelect.options[planSelect.selectedIndex];
            var price = selectedOption.getAttribute('data-price');
            document.getElementById('amount_paid').value = price;
        }
    </script>
<?php include 'footer.php'; ?>
