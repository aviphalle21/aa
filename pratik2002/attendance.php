<?php
// pratik2002/attendance.php
require_once 'config.php';
require_once 'header.php'; // handles auth and includes sidebar/header HTML

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'Full Name', 'Date', 'Check-in Time', 'Status']);

    $q = "SELECT u.unique_user_id, u.full_name, a.attendance_date, a.check_in_time, a.status FROM attendance a JOIN users u ON a.user_id = u.user_id ORDER BY a.attendance_date DESC";
    $stmt = $pdo->prepare($q);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['unique_user_id'], $row['full_name'], $row['attendance_date'], $row['check_in_time'], $row['status']]);
    }
    fclose($output);
    exit;
}

// Analytics
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

try {
    // Today's total attendance
    $td = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'Present'");
    $td->execute([$today]);
    $todayTotal = $td->fetchColumn();

    // Monthly total
    $mo = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date BETWEEN ? AND ? AND status = 'Present'");
    $mo->execute([$monthStart, $monthEnd]);
    $monthTotal = $mo->fetchColumn();

    // Total Users
    $tu = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'Active'")->fetchColumn();

    // Fetch records
    $search = $_GET['search'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $monthFilter = $_GET['month'] ?? '';

    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(u.full_name LIKE ? OR u.unique_user_id LIKE ? OR a.attendance_date LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($statusFilter) {
        $where[] = "a.status = ?";
        $params[] = $statusFilter;
    }
    if ($monthFilter) {
        $where[] = "DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
        $params[] = $monthFilter;
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    $query = "SELECT a.*, u.full_name, u.unique_user_id FROM attendance a JOIN users u ON a.user_id = u.user_id $whereClause ORDER BY a.attendance_date DESC, a.check_in_time DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    // schema not updated yet
    $records = [];
    $todayTotal = 0;
    $monthTotal = 0;
    $tu = 0;
}
?>

<div class="content-body">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
        <h2>Attendance Management</h2>
        <a href="attendance.php?export=csv" class="btn-primary" style="background:#10b981; border:none; text-decoration:none; padding:10px 20px;">Export to CSV</a>
    </div>

    <!-- Dashboard Widgets -->
    <div class="dashboard-cards" style="margin-bottom: 25px;">
        <div class="card">
            <h3>Today's Attendance</h3>
            <p><?= $todayTotal ?> Users Present</p>
        </div>
        <div class="card">
            <h3>Monthly Attendance</h3>
            <p><?= $monthTotal ?> Check-ins</p>
        </div>
        <div class="card">
            <h3>Total Active Users</h3>
            <p><?= $tu ?></p>
        </div>
        <div class="card">
            <h3>Attendance Rate (Today)</h3>
            <p><?= $tu > 0 ? round(($todayTotal / $tu) * 100, 1) : 0 ?>%</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 25px; padding: 15px;">
        <form method="GET" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search user or date..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="padding:10px; border-radius:5px; border:1px solid #ccc; flex:1; min-width:200px;">
            <select name="status" style="padding:10px; border-radius:5px; border:1px solid #ccc;">
                <option value="">All Statuses</option>
                <option value="Present" <?= (($_GET['status']??'') == 'Present') ? 'selected' : '' ?>>Present</option>
                <option value="Absent" <?= (($_GET['status']??'') == 'Absent') ? 'selected' : '' ?>>Absent</option>
                <option value="Late" <?= (($_GET['status']??'') == 'Late') ? 'selected' : '' ?>>Late</option>
            </select>
            <input type="month" name="month" value="<?= htmlspecialchars($_GET['month'] ?? '') ?>" style="padding:10px; border-radius:5px; border:1px solid #ccc;">
            <button type="submit" class="btn-primary" style="padding:10px 20px; border:none;">Filter</button>
            <a href="attendance.php" class="btn-secondary" style="padding:10px 20px; text-decoration:none;">Clear</a>
        </form>
    </div>

    <!-- Records Table -->
    <div class="card" style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Check-in Time</th>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Status</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td data-label="Date"><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
                            <td data-label="Check-in Time"><?= date('h:i A', strtotime($r['check_in_time'])) ?></td>
                            <td data-label="User ID"><?= htmlspecialchars($r['unique_user_id']) ?></td>
                            <td data-label="Full Name"><?= htmlspecialchars($r['full_name']) ?></td>
                            <td data-label="Status">
                                <span style="background:<?= $r['status']=='Present' ? '#d1fae5' : ($r['status']=='Absent' ? '#fee2e2' : '#fef3c7') ?>; color:<?= $r['status']=='Present' ? '#065f46' : ($r['status']=='Absent' ? '#991b1b' : '#92400e') ?>; padding:5px 10px; border-radius:4px; font-size:0.85rem; font-weight:bold;">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </td>
                            <td data-label="IP Address"><?= htmlspecialchars($r['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
