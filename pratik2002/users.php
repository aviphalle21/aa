<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Search Query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = "";
$queryParams = [];

if ($searchQuery !== '') {
    $searchCondition = " WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.unique_user_id LIKE ? ";
    $likeQuery = "%" . $searchQuery . "%";
    $queryParams = [$likeQuery, $likeQuery, $likeQuery, $likeQuery];
}

// Fetch users with their table numbers and active booking status
$query = "
    SELECT 
        u.unique_user_id, 
        u.full_name, 
        u.email, 
        u.address, 
        u.phone, 
        t.table_number, 
        u.last_login,
        u.registration_date,
        COALESCE(b.booking_status, 'None') as booking_status
    FROM users u
    LEFT JOIN library_tables t ON u.user_id = t.current_user_id
    LEFT JOIN bookings b ON u.user_id = b.user_id AND b.booking_status = 'Active'
    $searchCondition
    GROUP BY u.user_id
    ORDER BY u.registration_date DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($queryParams);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<?php 
$pageTitle = 'User Management';
$showBackButton = true;
?>
<body>
    <?php include 'header.php'; ?>

    <div class="table-container">
        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <h2>All Users</h2>
            
            <form method="GET" action="users.php" style="display: flex; gap: 10px; flex-grow: 1; max-width: 400px;">
                <input type="text" name="search" placeholder="Search Name, Email, Phone, or ID..." value="<?= htmlspecialchars($searchQuery) ?>" style="flex-grow: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; outline: none;">
                <button type="submit" class="btn-primary" style="padding: 8px 16px;">Search</button>
                <?php if($searchQuery): ?>
                    <a href="users.php" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">Clear</a>
                <?php endif; ?>
            </form>

            <a href="add_user.php" class="btn-primary" style="text-decoration:none;">+ Add User</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Unique ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Address</th>
                        <th>Table Number</th>
                        <th>Account Created</th>
                        <th>Last Login</th>
                        <th>Booking Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="Unique ID"><strong><?= htmlspecialchars($user['unique_user_id']) ?></strong></td>
                            <td data-label="Name"><?= htmlspecialchars($user['full_name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($user['email']) ?></td>
                            <td data-label="Phone Number"><?= htmlspecialchars($user['phone']) ?></td>
                            <td data-label="Address"><?= htmlspecialchars($user['address']) ?></td>
                            <td data-label="Table Number">
                                <?php if ($user['table_number']): ?>
                                    <span class="badge badge-table">T-<?= htmlspecialchars($user['table_number']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-none">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Account Created">
                                <span style="font-size: 0.85rem;"><?= date('M d, Y', strtotime($user['registration_date'])) ?></span>
                            </td>
                            <td data-label="Last Login">
                                <?php if ($user['last_login']): ?>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?= date('M d, Y h:i A', strtotime($user['last_login'])) ?></span>
                                <?php else: ?>
                                    <span style="font-size: 0.85rem; color: #cbd5e1;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Booking Status">
                                <?php if ($user['booking_status'] === 'Active'): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><?= htmlspecialchars($user['booking_status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No users found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
<?php include 'footer.php'; ?>
