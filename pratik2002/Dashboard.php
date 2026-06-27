<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: index.php"); exit; }
require_once 'config.php';
$pageTitle = 'Dashboard';

// 1. Total Members
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newMembersToday = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(registration_date) = CURRENT_DATE")->fetchColumn();

// 2. Total Tables & Available
$totalTables = $pdo->query("SELECT COUNT(*) FROM library_tables")->fetchColumn();
$availableTables = $pdo->query("SELECT COUNT(*) FROM library_tables WHERE status = 'Available'")->fetchColumn();
$bookedTables = $pdo->query("SELECT COUNT(*) FROM library_tables WHERE status = 'Booked'")->fetchColumn();
$maintenanceTables = $pdo->query("SELECT COUNT(*) FROM library_tables WHERE status = 'Maintenance'")->fetchColumn();

// 3. Total Revenue & This Month
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'")->fetchColumn();
$monthRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid' AND MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();

// 4. Active Bookings
$activeBookings = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE subscription_status = 'Active'")->fetchColumn();

// 5. Recent Bookings (Limit 5)
$recentBookings = $pdo->query("
    SELECT b.booking_reference, b.booking_status, b.start_date, u.full_name, t.table_number 
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN library_tables t ON b.table_id = t.table_id
    ORDER BY b.booking_date DESC LIMIT 5
")->fetchAll();

// 6. Recent Payments (Limit 5)
$recentPayments = $pdo->query("
    SELECT p.amount, p.payment_status, p.payment_date, u.full_name
    FROM payments p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.payment_date DESC LIMIT 5
")->fetchAll();

// 7. Chart Data: Monthly Revenue (Current Year)
$monthlyRevData = array_fill(1, 12, 0);
$monthlyQuery = $pdo->query("
    SELECT MONTH(payment_date) as m, SUM(amount) as total 
    FROM payments 
    WHERE payment_status = 'Paid' AND YEAR(payment_date) = YEAR(CURRENT_DATE)
    GROUP BY MONTH(payment_date)
");
while($row = $monthlyQuery->fetch()) {
    $monthlyRevData[(int)$row['m']] = (float)$row['total'];
}
$monthlyRevJson = json_encode(array_values($monthlyRevData));

// 8. Chart Data: Membership Types
$planDataQuery = $pdo->query("
    SELECT p.plan_name, COUNT(us.subscription_id) as count
    FROM user_subscriptions us
    JOIN subscription_plans p ON us.plan_id = p.plan_id
    WHERE us.subscription_status = 'Active'
    GROUP BY p.plan_name
");
$planLabels = [];
$planCounts = [];
while($row = $planDataQuery->fetch()) {
    $planLabels[] = $row['plan_name'];
    $planCounts[] = $row['count'];
}
$planLabelsJson = json_encode($planLabels);
$planCountsJson = json_encode($planCounts);

// 9. Fetch Notifications
$notifStmt = $pdo->query("SELECT * FROM system_notifications ORDER BY created_at DESC LIMIT 10");
$notifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADMIN DASHBOARD - Library</title>
    <link rel="stylesheet" href="Dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'header.php'; ?>

<!-- Top Stats Row -->
<div class="dash-grid" style="margin-top: 20px;">
    <!-- Dashboard Search removed per user request -->
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff; color:#4f46e5;">👥</div>
        <div class="stat-info">
            <h4>Total Members</h4>
            <h2><?= number_format($totalMembers) ?></h2>
            <div class="stat-trend trend-up">↑ <?= $newMembersToday ?> today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">🪑</div>
        <div class="stat-info">
            <h4>Total Tables</h4>
            <h2><?= number_format($totalTables) ?></h2>
            <div class="stat-trend trend-neutral"><?= $availableTables ?> available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5; color:#10b981;">₹</div>
        <div class="stat-info">
            <h4>Total Revenue</h4>
            <h2>₹<?= number_format($totalRevenue) ?></h2>
            <div class="stat-trend trend-up">₹<?= number_format($monthRevenue) ?> this month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ffedd5; color:#f97316;">📅</div>
        <div class="stat-info">
            <h4>Active Bookings</h4>
            <h2><?= number_format($activeBookings) ?></h2>
            <div class="stat-trend trend-neutral">Currently active</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Monthly Revenue (<?= date('Y') ?>)</h3>
        </div>
        <div class="chart-body">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <h3>Table Occupancy</h3>
        </div>
        <div class="chart-body">
            <canvas id="occupancyChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <h3>Membership Types</h3>
        </div>
        <div class="chart-body">
            <canvas id="membershipChart"></canvas>
        </div>
    </div>
</div>

<!-- Lists Row -->
<div class="lists-grid">
    <div class="list-card">
        <div class="list-header">
            <h3>Recent Bookings</h3>
            <a href="tables.php">View All</a>
        </div>
        <?php foreach($recentBookings as $b): ?>
        <div class="list-item">
            <div class="list-item-left">
                <div class="list-avatar">👤</div>
                <div class="list-info">
                    <h4><?= htmlspecialchars($b['full_name']) ?></h4>
                    <p>Table T-<?= $b['table_number'] ?></p>
                </div>
            </div>
            <div class="list-right">
                <?php 
                    $bClass = 'badge-primary';
                    if($b['booking_status'] == 'Pending') $bClass = 'badge-warning';
                    if($b['booking_status'] == 'Active') $bClass = 'badge-success';
                ?>
                <div class="badge <?= $bClass ?>"><?= $b['booking_status'] ?></div>
                <div class="date" style="margin-top:4px;"><?= date('d M Y', strtotime($b['start_date'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="list-card">
        <div class="list-header">
            <h3>Recent Payments</h3>
            <a href="payments.php">View All</a>
        </div>
        <?php foreach($recentPayments as $p): ?>
        <div class="list-item">
            <div class="list-item-left">
                <div class="list-avatar">💳</div>
                <div class="list-info">
                    <h4><?= htmlspecialchars($p['full_name']) ?></h4>
                    <p><?= date('d M Y, h:i A', strtotime($p['payment_date'])) ?></p>
                </div>
            </div>
            <div class="list-right">
                <div class="value">₹<?= number_format($p['amount']) ?></div>
                <?php 
                    $pClass = 'badge-primary';
                    if($p['payment_status'] == 'Pending') $pClass = 'badge-warning';
                    if($p['payment_status'] == 'Paid') $pClass = 'badge-success';
                ?>
                <div class="badge <?= $pClass ?>"><?= $p['payment_status'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="list-card">
        <div class="list-header">
            <h3>System Alerts</h3>
            <a href="#" onclick="openNavDrawer()">View All</a>
        </div>
        <?php if(count($notifications) > 0): ?>
            <?php foreach(array_slice($notifications, 0, 4) as $n): ?>
            <div class="list-item">
                <div class="list-item-left">
                    <div class="list-avatar" style="background:#fee2e2; color:#dc2626; font-size:1rem;">
                        <?= ($n['type'] === 'login') ? '👤' : (($n['type'] === 'payment') ? '💳' : '⚠️') ?>
                    </div>
                <div class="list-info">
                    <h4 style="font-size:0.8rem;"><?= htmlspecialchars($n['title']) ?></h4>
                    <p style="font-size:0.7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;"><?= htmlspecialchars($n['message']) ?></p>
                </div>
            </div>
                <div class="list-right">
                    <div class="date"><?= date('d M', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 20px;">No alerts to display.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="add_user.php" class="btn-action action-purple"><span class="icon">👤</span> Add Member</a>
    <a href="add_subscription.php" class="btn-action action-blue"><span class="icon">🪑</span> Assign Seat</a>
    <a href="payments.php" class="btn-action action-green"><span class="icon">💳</span> Add Payment</a>
    <a href="reports.php" class="btn-action action-orange"><span class="icon">📊</span> Generate Report</a>
    <a href="#" class="btn-action action-red" onclick="openNavDrawer()"><span class="icon">🔔</span> View Notices</a>
</div>

<div class="dashboard-footer">
    <div>&copy; <?= date('Y') ?> Saraswati Abhyasika Library Management System. All rights reserved.</div>
    <div class="server-status">
        <div class="status-dot"></div>
        Server Status: Online
    </div>
</div>

<script>
// Chart.js Default styling
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b';

// 1. Monthly Revenue Bar Chart
const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Revenue (₹)',
            data: <?= $monthlyRevJson ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// 2. Table Occupancy Doughnut Chart
const occCtx = document.getElementById('occupancyChart').getContext('2d');
new Chart(occCtx, {
    type: 'doughnut',
    data: {
        labels: ['Occupied', 'Available', 'Maintenance'],
        datasets: [{
            data: [<?= $bookedTables ?>, <?= $availableTables ?>, <?= $maintenanceTables ?>],
            backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } }
        }
    }
});

// 3. Membership Types Doughnut Chart
const memCtx = document.getElementById('membershipChart').getContext('2d');
new Chart(memCtx, {
    type: 'doughnut',
    data: {
        labels: <?= $planLabelsJson ?: '[]' ?>,
        datasets: [{
            data: <?= $planCountsJson ?: '[]' ?>,
            backgroundColor: ['#8b5cf6', '#3b82f6', '#10b981', '#f43f5e', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } }
        }
    }
});
</script>

<script>
// No dashboard specific search logic needed anymore
</script>

<?php include 'footer.php'; ?>