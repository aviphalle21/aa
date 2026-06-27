<?php
// USER/attendance.php
require_once 'config.php';
require_once '../includes/Security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Check user details
$stmt = $pdo->prepare("SELECT full_name, unique_user_id, account_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['account_status'] !== 'Active') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$firstLetter = strtoupper(substr($user['full_name'], 0, 1));

// Handle Mark Attendance
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid request (CSRF).';
        $messageType = 'alert-error';
    } else {
        $today = date('Y-m-d');
        // Check if already marked
        $chk = $pdo->prepare("SELECT attendance_id FROM attendance WHERE user_id = ? AND attendance_date = ?");
        $chk->execute([$user_id, $today]);
        if ($chk->rowCount() > 0) {
            $message = 'Attendance for today is already marked.';
            $messageType = 'alert-warning';
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $timeNow = date('H:i:s');
            // ensure the table exists! It will be added in full schema. 
            // In a real environment, wait for full schema before deploying, but we create it here via PHP if it doesn't exist for robustness (optional, but let's just assume schema runs)
            try {
                $ins = $pdo->prepare("INSERT INTO attendance (user_id, attendance_date, check_in_time, status, ip_address) VALUES (?, ?, ?, 'Present', ?)");
                if ($ins->execute([$user_id, $today, $timeNow, $ipAddress])) {
                    $message = 'Attendance marked successfully!';
                    $messageType = 'alert-success';
                } else {
                    $message = 'Failed to mark attendance.';
                    $messageType = 'alert-error';
                }
            } catch (PDOException $e) {
                $message = 'Database error. Did you run the latest schema update? ' . $e->getMessage();
                $messageType = 'alert-error';
            }
        }
    }
}

// Fetch stats
$today = date('Y-m-d');
// Check today
$todayRecord = null;
$totalPresent = 0;
$monthlyPresent = 0;
$lastAttendance = null;
$attendanceList = [];

try {
    $chk = $pdo->prepare("SELECT check_in_time FROM attendance WHERE user_id = ? AND attendance_date = ?");
    $chk->execute([$user_id, $today]);
    $todayRecord = $chk->fetch();

    // Total present
    $tot = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND status = 'Present'");
    $tot->execute([$user_id]);
    $totalPresent = $tot->fetchColumn();

    // Monthly present
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $mon = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND status = 'Present' AND attendance_date BETWEEN ? AND ?");
    $mon->execute([$user_id, $monthStart, $monthEnd]);
    $monthlyPresent = $mon->fetchColumn();

    // Last attendance
    $last = $pdo->prepare("SELECT attendance_date, check_in_time FROM attendance WHERE user_id = ? ORDER BY attendance_date DESC, check_in_time DESC LIMIT 1");
    $last->execute([$user_id]);
    $lastAttendance = $last->fetch();

    // History
    $history = $pdo->prepare("SELECT attendance_date, check_in_time, status FROM attendance WHERE user_id = ? ORDER BY attendance_date DESC LIMIT 30");
    $history->execute([$user_id]);
    $attendanceList = $history->fetchAll();
} catch (PDOException $e) {
    // Suppress errors if table doesn't exist yet, it will be added in full schema.
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Saraswati Abhyasika</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: rgba(59,130,246,0.1); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid rgba(59,130,246,0.2); }
        .stat-val { font-size: 1.8rem; font-weight: bold; color: var(--text-main); margin-bottom: 5px; }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .history-table th { background: rgba(0,0,0,0.02); font-weight: 600; }
        .badge-present { background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <a href="dashboard.php" style="text-decoration: none; color: inherit; display:flex; align-items:center; gap:15px;">
            <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Logo">
            <h1>सरस्वती अभ्यासिका</h1>
        </a>
    </div>
    <div class="nav-right">
        <button class="theme-toggle" id="theme-toggle">🌙 Dark Mode</button>
        <div class="profile-avatar"><?= htmlspecialchars($firstLetter) ?></div>
        <a href="?logout=1" class="btn-logout">Sign Out</a>
    </div>
</nav>

<div class="dashboard-container single-col-center">
    
    <div style="width: 100%; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2>My Attendance</h2>
        <a href="dashboard.php" class="btn-primary" style="background:#64748b; text-decoration:none; padding:10px 20px; border-radius:8px; color:#fff;">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType ?>" style="width:100%; margin-bottom: 20px; padding: 15px; border-radius: 8px;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="text-align: center; margin-bottom: 30px;">
            <h3>Today's Attendance</h3>
            <p style="color:var(--text-muted); margin-bottom: 20px;"><?= date('l, d F Y') ?></p>
            <?php if ($todayRecord): ?>
                <div style="display:inline-block; padding: 15px 30px; background:#d1fae5; color:#065f46; border-radius:12px; font-weight:bold; font-size:1.1rem; border:1px solid #34d399;">
                    ✓ Marked Present at <?= date('h:i A', strtotime($todayRecord['check_in_time'])) ?>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken()) ?>">
                    <button type="submit" name="mark_attendance" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; border-radius:30px; background:var(--header-bg); color:#fff; border:none; cursor:pointer;">
                        Mark Present
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <h3 style="margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom:10px;">Overview</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val"><?= $monthlyPresent ?></div>
                <div class="stat-label">Days Present (This Month)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?= $totalPresent ?></div>
                <div class="stat-label">Total Days Present</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="font-size:1.1rem; line-height:2.5;">
                    <?= $lastAttendance ? date('d M Y', strtotime($lastAttendance['attendance_date'])) : 'Never' ?>
                </div>
                <div class="stat-label">Last Attendance</div>
            </div>
        </div>

        <h3 style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom:10px;">Recent History</h3>
        <?php if(count($attendanceList) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check-in Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($attendanceList as $row): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($row['attendance_date'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                                <td><span class="badge-present"><?= htmlspecialchars($row['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:var(--text-muted); padding: 20px;">No attendance history found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    const themeToggle = document.getElementById('theme-toggle');
    const htmlEl = document.documentElement;
    if (localStorage.getItem('userTheme') === 'dark') {
        htmlEl.setAttribute('data-theme', 'dark');
        themeToggle.textContent = '☀️ Light Mode';
    }
    themeToggle.addEventListener('click', () => {
        if (htmlEl.getAttribute('data-theme') === 'dark') {
            htmlEl.setAttribute('data-theme', 'light');
            themeToggle.textContent = '🌙 Dark Mode';
            localStorage.setItem('userTheme', 'light');
        } else {
            htmlEl.setAttribute('data-theme', 'dark');
            themeToggle.textContent = '☀️ Light Mode';
            localStorage.setItem('userTheme', 'dark');
        }
    });
</script>
</body>
</html>
