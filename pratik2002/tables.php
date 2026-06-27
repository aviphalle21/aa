<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle "Add New Table" action
if (isset($_POST['add_table'])) {
    // Get highest current table number
    $stmt = $pdo->query("SELECT MAX(table_number) as max_num FROM library_tables");
    $row = $stmt->fetch();
    $next_number = ($row['max_num'] ?? 0) + 1;
    
    $unique = 'TBL-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    $insert = $pdo->prepare("INSERT INTO library_tables (unique_table_id, table_number) VALUES (?, ?)");
    $insert->execute([$unique, $next_number]);
    header("Location: tables.php");
    exit;
}

// Handle "Make Available" action
if (isset($_POST['make_available']) && isset($_POST['table_id'])) {
    $table_id = $_POST['table_id'];
    $stmt = $pdo->prepare("SELECT status, current_user_id FROM library_tables WHERE table_id = ?");
    $stmt->execute([$table_id]);
    $row = $stmt->fetch();
    
    if ($row && $row['status'] === 'Maintenance' && !empty($row['current_user_id'])) {
        $updateTable = $pdo->prepare("UPDATE library_tables SET status = 'Booked' WHERE table_id = ?");
    } else {
        $updateTable = $pdo->prepare("UPDATE library_tables SET status = 'Available', current_user_id = NULL WHERE table_id = ?");
    }
    $updateTable->execute([$table_id]);
    header("Location: tables.php");
    exit;
}

// Handle "Mark Maintenance" action
if (isset($_POST['mark_maintenance']) && isset($_POST['table_id'])) {
    $table_id = $_POST['table_id'];
    $updateTable = $pdo->prepare("UPDATE library_tables SET status = 'Maintenance' WHERE table_id = ?");
    $updateTable->execute([$table_id]);
    header("Location: tables.php");
    exit;
}

// Handle "Delete Table" action
if (isset($_POST['delete_table']) && isset($_POST['table_id'])) {
    $table_id = $_POST['table_id'];
    $deleteTable = $pdo->prepare("DELETE FROM library_tables WHERE table_id = ?");
    $deleteTable->execute([$table_id]);
    header("Location: tables.php");
    exit;
}

// Fetch tables with user info and latest subscription plan
$query = "
    SELECT 
        t.table_id,
        t.table_number, 
        t.status as table_status, 
        u.full_name, 
        u.email,
        (SELECT p.plan_name FROM user_subscriptions s JOIN subscription_plans p ON s.plan_id = p.plan_id WHERE s.table_id = t.table_id ORDER BY s.expiry_date DESC LIMIT 1) as plan_name,
        (SELECT s.expiry_date FROM user_subscriptions s WHERE s.table_id = t.table_id ORDER BY s.expiry_date DESC LIMIT 1) as expiry_date
    FROM library_tables t
    LEFT JOIN users u ON t.current_user_id = u.user_id
    ORDER BY t.table_number ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$tables = $stmt->fetchAll();

// Auto-seed 39 tables if the database is completely empty
if (count($tables) == 0) {
    for ($i = 1; $i <= 39; $i++) {
        $unique = 'TBL-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $insert = $pdo->prepare("INSERT INTO library_tables (unique_table_id, table_number) VALUES (?, ?)");
        $insert->execute([$unique, $i]);
    }
    header("Location: tables.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tables - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
    <style>
        .btn-action {
            padding: 8px 14px;
            background: var(--brand-crimson);
            color: var(--pure-white);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-action:hover { 
            background: var(--brand-crimson-dark); 
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(153,27,27,0.3);
        }
        .text-sm { font-size: 0.85rem; color: var(--text-muted); }
        .text-danger { color: #dc2626; font-weight: 600; font-size: 0.85rem; }
    </style>
</head>
<?php 
$pageTitle = 'Tables Management';
$showBackButton = true;
?>
<body>
    <?php include 'header.php'; ?>

    <div class="table-container">
        <div class="table-header">
            <h2>All Tables (Total: <?= count($tables) ?>)</h2>
            <form method="POST" style="margin:0; display:flex; gap:10px;">
                <a href="add_subscription.php" class="btn-primary" style="text-decoration:none;">+ Assign Table</a>
                <button type="submit" name="add_table" class="btn-primary" style="background:#111827;">+ Add New Table</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Table No.</th>
                        <th>User Details</th>
                        <th>Status</th>
                        <th>Subscription Plan</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                    <?php 
                        $isExpired = false;
                        if ($table['expiry_date'] && strtotime($table['expiry_date']) < strtotime(date('Y-m-d'))) {
                            $isExpired = true;
                        }
                    ?>
                    <tr>
                        <td data-label="Table No."><strong>T-<?= htmlspecialchars($table['table_number']) ?></strong></td>
                        <td data-label="User Details">
                            <?php if ($table['full_name']): ?>
                                <?= htmlspecialchars($table['full_name']) ?><br>
                                <span class="text-sm"><?= htmlspecialchars($table['email']) ?></span>
                            <?php else: ?>
                                <span class="text-sm">No active user</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($table['table_status'] === 'Available'): ?>
                                <span class="badge badge-active">Available</span>
                            <?php else: ?>
                                <span class="badge badge-pending"><?= htmlspecialchars($table['table_status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Subscription Plan">
                            <?php if ($table['table_status'] === 'Booked' && $table['plan_name']): ?>
                                <strong><?= htmlspecialchars($table['plan_name']) ?></strong><br>
                                <span class="text-sm">Exp: <?= htmlspecialchars($table['expiry_date']) ?></span>
                                <?php if($isExpired): ?> <br><span class="text-danger">(Expired)</span> <?php endif; ?>
                            <?php else: ?>
                                <span class="text-sm">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <form method="POST" style="margin:0; display:flex; gap:6px;">
                                <input type="hidden" name="table_id" value="<?= $table['table_id'] ?>">
                                
                                <?php if ($table['table_status'] !== 'Available'): ?>
                                    <button type="submit" name="make_available" class="btn-action" title="<?= $table['table_status'] === 'Maintenance' && $table['full_name'] ? 'Restore Booking' : 'Clear & Make Available' ?>" onclick="return confirm('<?= $table['table_status'] === 'Maintenance' && $table['full_name'] ? 'Restore this table back to the user?' : 'Clear the user and make this table available?' ?>');">✔️</button>
                                <?php endif; ?>
                                
                                <?php if ($table['table_status'] !== 'Maintenance'): ?>
                                    <button type="submit" name="mark_maintenance" class="btn-action" style="background:#d97706;" title="Mark for Maintenance" onclick="return confirm('Mark table for maintenance? User booking will be preserved.');">🔧</button>
                                <?php endif; ?>
                                
                                <button type="submit" name="delete_table" class="btn-action" style="background:#111827;" title="Delete Table" onclick="return confirm('Are you sure you want to permanently delete this table?');">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
<?php include 'footer.php'; ?>
