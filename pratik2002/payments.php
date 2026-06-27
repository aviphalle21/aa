<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';

// Handle Plan Price Edit
if (isset($_POST['update_price'])) {
    $plan_id = $_POST['plan_id'];
    $new_price = $_POST['new_price'];
    
    // Check if there are ANY active subscriptions using this plan
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as active_count FROM user_subscriptions WHERE plan_id = ? AND subscription_status = 'Active'");
    $checkStmt->execute([$plan_id]);
    $activeCount = $checkStmt->fetchColumn();
    
    if ($activeCount > 0) {
        $alertMessage = "Cannot change price. There is currently $activeCount active subscription(s) on this plan.";
        $alertType = "alert-error";
    } else {
        // Update the price safely
        $updateStmt = $pdo->prepare("UPDATE subscription_plans SET price = ? WHERE plan_id = ?");
        if ($updateStmt->execute([$new_price, $plan_id])) {
            $alertMessage = "Price updated successfully!";
            $alertType = "alert-success";
        }
    }
}

// Fetch Plans
$plansStmt = $pdo->query("SELECT * FROM subscription_plans ORDER BY duration_days ASC");
$plans = $plansStmt->fetchAll();

// Fetch Payments / Subscriptions
$query = "
    SELECT 
        us.subscription_id,
        us.start_date,
        us.expiry_date,
        us.amount_paid,
        us.payment_status,
        us.subscription_status,
        u.full_name,
        u.email,
        u.phone,
        t.table_number,
        sp.plan_name
    FROM user_subscriptions us
    JOIN users u ON us.user_id = u.user_id
    JOIN library_tables t ON us.table_id = t.table_id
    JOIN subscription_plans sp ON us.plan_id = sp.plan_id
    ORDER BY us.start_date DESC
";
$subStmt = $pdo->query($query);
$subscriptions = $subStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments & Plans - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<?php 
$pageTitle = 'Payments & Plans';
$showBackButton = true;
?>
<body>
    <?php include 'header.php'; ?>

    <div style="max-width: 1200px; margin: 0 auto;">
        <?php if ($alertMessage): ?>
            <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
        <?php endif; ?>

        <!-- SECTION 1: PLANS MANAGEMENT -->
        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
                <div class="plan-card">
                    <h3><?= htmlspecialchars($plan['plan_name']) ?></h3>
                    <div class="price">₹<?= number_format($plan['price'], 2) ?></div>
                    <p style="color: var(--text-muted); font-size:0.9rem; margin-bottom: 10px;">Duration: <?= $plan['duration_days'] ?> Days</p>
                    
                    <form method="POST" class="price-edit-form">
                        <input type="hidden" name="plan_id" value="<?= $plan['plan_id'] ?>">
                        <input type="number" name="new_price" value="<?= floor($plan['price']) ?>" min="0" required>
                        <button type="submit" name="update_price" class="btn-action" style="padding: 8px 16px;">Save Price</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- SECTION 2: PAYMENTS & SUBSCRIPTIONS -->
        <div class="table-container">
            <div class="table-header">
                <h2>Payment Records & Subscriptions</h2>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Table No.</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Expiry Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($subscriptions) > 0): ?>
                            <?php foreach ($subscriptions as $sub): ?>
                                <?php 
                                    // Calculate expiry status logically
                                    $expiryTime = strtotime($sub['expiry_date']);
                                    $currentTime = time();
                                    $daysRemaining = ($expiryTime - $currentTime) / (60 * 60 * 24);
                                    
                                    $expiryBadge = '';
                                    if ($daysRemaining < 0) {
                                        $expiryBadge = '<span class="badge badge-danger">Expired</span>';
                                    } elseif ($daysRemaining <= 7) {
                                        $expiryBadge = '<span class="badge badge-warning">Expiring Soon</span>';
                                    } else {
                                        $expiryBadge = '<span class="badge badge-active">Active</span>';
                                    }
                                ?>
                                <tr>
                                    <td data-label="User Details">
                                        <strong><?= htmlspecialchars($sub['full_name']) ?></strong><br>
                                        <span class="text-sm"><?= htmlspecialchars($sub['email']) ?> | <?= htmlspecialchars($sub['phone']) ?></span>
                                    </td>
                                    <td data-label="Table No."><strong>T-<?= htmlspecialchars($sub['table_number']) ?></strong></td>
                                    <td data-label="Plan"><?= htmlspecialchars($sub['plan_name']) ?></td>
                                    <td data-label="Amount"><strong>₹<?= number_format($sub['amount_paid'], 2) ?></strong></td>
                                    <td data-label="Payment Status">
                                        <?php if ($sub['payment_status'] === 'Paid'): ?>
                                            <span class="badge badge-active">Paid</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending"><?= htmlspecialchars($sub['payment_status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Expiry Status">
                                        <?= $expiryBadge ?><br>
                                        <span class="text-sm">Ends: <?= htmlspecialchars($sub['expiry_date']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No payment or subscription records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
<?php include 'footer.php'; ?>
