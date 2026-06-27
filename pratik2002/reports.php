<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// ---------------------------------------------------------
// Date Filtering Logic
// ---------------------------------------------------------
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// To calculate trends, we need the previous period of the same length
$dateDiff = strtotime($endDate) - strtotime($startDate);
$daysDiff = round($dateDiff / (60 * 60 * 24));
$prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
$prevStartDate = date('Y-m-d', strtotime($prevEndDate . " -$daysDiff days"));

// Helper function to calculate percentage change
function getTrendHtml($current, $previous) {
    if ($previous == 0) {
        if ($current > 0) return "<span class='trend-up'>▲ 100%</span>";
        return "<span class='trend-neutral'>- 0%</span>";
    }
    $change = (($current - $previous) / $previous) * 100;
    if ($change > 0) {
        return "<span class='trend-up'>▲ " . number_format($change, 1) . "%</span>";
    } elseif ($change < 0) {
        return "<span class='trend-down'>▼ " . number_format(abs($change), 1) . "%</span>";
    } else {
        return "<span class='trend-neutral'>- 0%</span>";
    }
}

// ---------------------------------------------------------
// 1. KEY METRICS QUERIES
// ---------------------------------------------------------

// Tables currently booked (real-time metric)
$bookedTablesStmt = $pdo->query("SELECT COUNT(*) FROM library_tables WHERE status = 'Booked'");
$bookedTables = $bookedTablesStmt->fetchColumn();

// Users in Current Period
$usersPeriodStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(registration_date) BETWEEN ? AND ?");
$usersPeriodStmt->execute([$startDate, $endDate]);
$usersPeriod = $usersPeriodStmt->fetchColumn();

// Users in Previous Period
$usersPrevPeriodStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(registration_date) BETWEEN ? AND ?");
$usersPrevPeriodStmt->execute([$prevStartDate, $prevEndDate]);
$usersPrevPeriod = $usersPrevPeriodStmt->fetchColumn();
$usersTrend = getTrendHtml($usersPeriod, $usersPrevPeriod);

// Revenue Current Period
$revPeriodStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM user_subscriptions WHERE DATE(start_date) BETWEEN ? AND ?");
$revPeriodStmt->execute([$startDate, $endDate]);
$revenuePeriod = $revPeriodStmt->fetchColumn();

// Revenue Previous Period
$revPrevPeriodStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM user_subscriptions WHERE DATE(start_date) BETWEEN ? AND ?");
$revPrevPeriodStmt->execute([$prevStartDate, $prevEndDate]);
$revenuePrevPeriod = $revPrevPeriodStmt->fetchColumn();
$revenueTrend = getTrendHtml($revenuePeriod, $revenuePrevPeriod);

// ---------------------------------------------------------
// 2. CHART DATA QUERIES
// ---------------------------------------------------------

// Line Chart: Yearly Revenue by Month
$yearlyDataStmt = $pdo->query("
    SELECT MONTH(start_date) as month_num, SUM(amount_paid) as total 
    FROM user_subscriptions 
    WHERE YEAR(start_date) = YEAR(CURRENT_DATE()) 
    GROUP BY MONTH(start_date)
");
$yearlyRaw = $yearlyDataStmt->fetchAll();
$monthlyRevenueArray = array_fill(1, 12, 0);
foreach($yearlyRaw as $row) {
    $monthlyRevenueArray[$row['month_num']] = $row['total'];
}
$monthlyRevenueJson = json_encode(array_values($monthlyRevenueArray));

// Donut 1: Subscription Plans Distribution (Filtered)
$planDistStmt = $pdo->prepare("
    SELECT sp.plan_name as label, COUNT(us.subscription_id) as value 
    FROM user_subscriptions us 
    JOIN subscription_plans sp ON us.plan_id = sp.plan_id 
    WHERE DATE(us.start_date) BETWEEN ? AND ?
    GROUP BY sp.plan_id
");
$planDistStmt->execute([$startDate, $endDate]);
$planDist = $planDistStmt->fetchAll();
$planLabels = json_encode(array_column($planDist, 'label'));
$planValues = json_encode(array_column($planDist, 'value'));

// Donut 2: Table Status Distribution (Current snapshot, no date filter)
$tableDistStmt = $pdo->query("SELECT status as label, COUNT(*) as value FROM library_tables GROUP BY status");
$tableDist = $tableDistStmt->fetchAll();
$tableLabels = json_encode(array_column($tableDist, 'label'));
$tableValues = json_encode(array_column($tableDist, 'value'));

// Donut 3: Payment Status Distribution (Filtered)
$paymentDistStmt = $pdo->prepare("SELECT payment_status as label, COUNT(*) as value FROM user_subscriptions WHERE DATE(start_date) BETWEEN ? AND ? GROUP BY payment_status");
$paymentDistStmt->execute([$startDate, $endDate]);
$paymentDist = $paymentDistStmt->fetchAll();
$paymentLabels = json_encode(array_column($paymentDist, 'label'));
$paymentValues = json_encode(array_column($paymentDist, 'value'));

// Donut 4: User Account Status Distribution (Current snapshot)
$userDistStmt = $pdo->query("SELECT account_status as label, COUNT(*) as value FROM users GROUP BY account_status");
$userDist = $userDistStmt->fetchAll();
$userLabels = json_encode(array_column($userDist, 'label'));
$userValues = json_encode(array_column($userDist, 'value'));

// ---------------------------------------------------------
// 3. ACTIONABLE TABLES
// ---------------------------------------------------------

// Expiring Subscriptions (Next 7 days)
$expiringStmt = $pdo->query("
    SELECT u.full_name, u.phone, t.table_number, us.expiry_date 
    FROM user_subscriptions us
    JOIN users u ON us.user_id = u.user_id
    JOIN library_tables t ON us.table_id = t.table_id
    WHERE us.expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    AND us.subscription_status = 'Active'
    ORDER BY us.expiry_date ASC
    LIMIT 10
");
$expiringSubs = $expiringStmt->fetchAll();

// Recent Transactions
$recentTransStmt = $pdo->query("
    SELECT u.full_name, p.amount, p.payment_method, p.payment_date, p.payment_status, p.payment_reference
    FROM payments p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.payment_date DESC
    LIMIT 10
");
$recentTrans = $recentTransStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<?php 
$pageTitle = 'Reports & Analytics';
$showBackButton = true;
?>
<body>
    <?php include 'header.php'; ?>

    <div class="report-container">
        
        <!-- Date Filter Form -->
        <div class="filter-section">
            <form method="GET" action="reports.php" class="date-filter-form">
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <button type="submit" class="btn-filter">Filter</button>
                <a href="reports.php" class="btn-clear">Clear</a>
            </form>
        </div>

        <!-- BOX 2: KEY METRICS -->
        <div class="report-box">
            <h2>Key Metrics & Important Details</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">🪑</div>
                    <h4>Tables Booked</h4>
                    <div class="metric-value"><?= number_format($bookedTables) ?></div>
                    <div class="metric-trend"><span class="trend-neutral">Real-time</span></div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">👥</div>
                    <h4>Users Registered</h4>
                    <div class="metric-value"><?= number_format($usersPeriod) ?></div>
                    <div class="metric-trend"><?= $usersTrend ?> vs prev period</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">₹</div>
                    <h4>Revenue</h4>
                    <div class="metric-value" style="color:var(--brand-crimson);">₹<?= number_format($revenuePeriod, 2) ?></div>
                    <div class="metric-trend"><?= $revenueTrend ?> vs prev period</div>
                </div>
            </div>
        </div>

        <!-- BOX 1: GRAPHICAL ANALYSIS -->
        <div class="report-box">
            <h2>Graphical Analysis</h2>
            
            <div class="charts-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));">
                <!-- Bar Chart: Period Comparison -->
                <div class="chart-wrapper">
                    <h4>Revenue Comparison (Selected vs Previous Period)</h4>
                    <canvas id="periodCompareChart" height="200"></canvas>
                </div>
                
                <!-- Line Chart: Yearly Trend -->
                <div class="chart-wrapper">
                    <h4>Revenue Trend (Current Year)</h4>
                    <canvas id="yearlyTrendChart" height="200"></canvas>
                </div>
            </div>

            <h3 style="text-align: center; margin: 30px 0 20px 0; color: var(--navy-blue);">Distribution Breakdowns (Selected Period)</h3>
            
            <!-- 4 Donut Charts -->
            <div class="charts-grid">
                <div class="chart-wrapper">
                    <h4>Subscription Plans</h4>
                    <canvas id="donutPlan"></canvas>
                </div>
                <div class="chart-wrapper">
                    <h4>Table Status (Real-time)</h4>
                    <canvas id="donutTable"></canvas>
                </div>
                <div class="chart-wrapper">
                    <h4>Payment Status</h4>
                    <canvas id="donutPayment"></canvas>
                </div>
                <div class="chart-wrapper">
                    <h4>User Account Status (Real-time)</h4>
                    <canvas id="donutUser"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ACTIONABLE TABLES -->
        <div class="tables-grid">
            <div class="report-box actionable-table-container">
                <div class="table-header-flex">
                    <h2>Expiring Subscriptions (Next 7 Days)</h2>
                </div>
                <div class="table-responsive">
                    <table class="action-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Table</th>
                                <th>Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expiringSubs) > 0): ?>
                                <?php foreach($expiringSubs as $sub): ?>
                                <tr>
                                    <td data-label="User"><?= htmlspecialchars($sub['full_name']) ?></td>
                                    <td data-label="Phone"><?= htmlspecialchars($sub['phone']) ?></td>
                                    <td data-label="Table">T-<?= htmlspecialchars($sub['table_number']) ?></td>
                                    <td data-label="Expiry Date" class="expiring-soon"><?= date('d M Y', strtotime($sub['expiry_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">No subscriptions expiring in the next 7 days.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-box actionable-table-container">
                <div class="table-header-flex">
                    <h2>Recent Transactions</h2>
                </div>
                <div class="table-responsive">
                    <table class="action-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentTrans) > 0): ?>
                                <?php foreach($recentTrans as $trans): ?>
                                <tr>
                                    <td data-label="User"><?= htmlspecialchars($trans['full_name']) ?></td>
                                    <td data-label="Amount">₹<?= number_format($trans['amount'], 2) ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?= strtolower($trans['payment_status']) ?>">
                                            <?= htmlspecialchars($trans['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Date"><?= date('d M Y, h:i A', strtotime($trans['payment_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">No recent transactions.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Shared Chart Configuration Variables
        const crimson = '#991b1b';
        const crimsonLight = '#f87171';
        const navy = '#0f172a';
        const navyLight = '#3b82f6';
        const gray = '#9ca3af';
        const green = '#10b981';
        const orange = '#f59e0b';
        
        const donutColors = [navy, crimson, gray, green, orange, navyLight];

        // 1. Period Comparison Bar Chart
        new Chart(document.getElementById('periodCompareChart'), {
            type: 'bar',
            data: {
                labels: ['Previous Period', 'Selected Period'],
                datasets: [{
                    label: 'Revenue (₹)',
                    data: [<?= $revenuePrevPeriod ?>, <?= $revenuePeriod ?>],
                    backgroundColor: [gray, crimson],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 2. Yearly Trend Line Chart
        new Chart(document.getElementById('yearlyTrendChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= $monthlyRevenueJson ?>,
                    borderColor: navy,
                    backgroundColor: 'rgba(15, 23, 42, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 3. Donut Charts Function
        function createDonut(ctxId, labels, dataVals) {
            if(dataVals.length === 0) {
                // Handle empty data
                labels = ['No Data'];
                dataVals = [1];
            }
            new Chart(document.getElementById(ctxId), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataVals,
                        backgroundColor: donutColors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: { 
                    responsive: true,
                    cutout: '65%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Initialize 4 Donuts using PHP JSON Data
        createDonut('donutPlan', <?= $planLabels ?>, <?= $planValues ?>);
        createDonut('donutTable', <?= $tableLabels ?>, <?= $tableValues ?>);
        createDonut('donutPayment', <?= $paymentLabels ?>, <?= $paymentValues ?>);
        createDonut('donutUser', <?= $userLabels ?>, <?= $userValues ?>);
    </script>
<?php include 'footer.php'; ?>
