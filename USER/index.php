<?php
// User/index.php
require_once 'config.php';
require_once '../includes/Security.php';
require_once '../includes/Logger.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$alertMessage = '';
$alertType = '';

if (isset($_SESSION['login_alert'])) {
    $alertMessage = $_SESSION['login_alert'];
    $alertType = 'alert-success';
    unset($_SESSION['login_alert']);
}

if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $alertMessage = "Your session has expired due to inactivity. Please sign in again.";
    $alertType = 'alert-error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $alertMessage = 'Invalid request (CSRF check failed). Please try again.';
        $alertType = 'alert-error';
    } else {
        $uniqueId = trim($_POST['unique_id']);
        $password = $_POST['password'];

        if (empty($uniqueId) || empty($password)) {
            $alertMessage = 'Please enter both Unique ID and Password.';
            $alertType = 'alert-error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, unique_user_id, full_name, password, account_status, failed_login_attempts, locked_until FROM users WHERE unique_user_id = ?");
                $stmt->execute([$uniqueId]);
                $user = $stmt->fetch();

                if ($user) {
                    // Check if account is locked
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $alertMessage = 'Account locked due to multiple failed attempts. Try again later.';
                        $alertType = 'alert-error';
                        Logger::logAudit($pdo, 'Login', 'Locked', $user['user_id'], null);
                    } else {
                        if (password_verify($password, $user['password'])) {
                            if ($user['account_status'] === 'Active') {
                                // Reset failed attempts
                                $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

                                // Insert log
                                $ipAddress = $_SERVER['REMOTE_ADDR'];
                                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                $deviceInfo = Security::parseUserAgent($userAgent);
                                
                                $logStmt = $pdo->prepare("INSERT INTO user_login_logs (user_id, ip_address, browser, os, device_type) VALUES (?, ?, ?, ?, ?)");
                                $logStmt->execute([$user['user_id'], $ipAddress, $deviceInfo['browser'], $deviceInfo['os'], $deviceInfo['device']]);

                                // Insert notification
                                $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('login', 'User Login', ?)");
                                $notifMsg = $user['full_name'] . " (" . $user['unique_user_id'] . ") logged in from " . $deviceInfo['browser'] . " on " . $deviceInfo['os'] . ".";
                                $notifStmt->execute([$notifMsg]);

                                Logger::logAudit($pdo, 'Login', 'Success', $user['user_id'], null);

                                // Prevent Session Fixation
                                session_regenerate_id(true);

                                // Success
                                $_SESSION['user_id'] = $user['user_id'];
                                $_SESSION['user_name'] = $user['full_name'];
                                $_SESSION['unique_id'] = $user['unique_user_id'];
                                header("Location: dashboard.php");
                                exit;
                            } else {
                                $alertMessage = 'Your account is currently ' . htmlspecialchars($user['account_status']) . '. Please contact support.';
                                $alertType = 'alert-error';
                                Logger::logAudit($pdo, 'Login', 'Failed (Status: '.$user['account_status'].')', $user['user_id'], null);
                            }
                        } else {
                            // Failed password
                            $fails = $user['failed_login_attempts'] + 1;
                            $lockedUntil = null;
                            if ($fails >= 5) {
                                $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                                $alertMessage = 'Account locked for 15 minutes due to multiple failed attempts.';
                                Logger::logAudit($pdo, 'Login', 'Account Locked', $user['user_id'], null);
                                
                                // Insert system notification for multiple failed attempts
                                $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('login', 'Security Alert: Account Locked', ?)");
                                $notifMsg = "Multiple failed login attempts for user " . $user['unique_user_id'] . ". Account temporarily locked.";
                                $notifStmt->execute([$notifMsg]);
                            } else {
                                $alertMessage = 'Invalid Unique ID or Password. Attempts left: ' . (5 - $fails);
                                Logger::logAudit($pdo, 'Login', 'Failed Password', $user['user_id'], null);
                            }
                            
                            $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?")->execute([$fails, $lockedUntil, $user['user_id']]);
                            $alertType = 'alert-error';
                        }
                    }
                } else {
                    $alertMessage = 'Invalid Unique ID or Password.';
                    $alertType = 'alert-error';
                    Logger::logAudit($pdo, 'Login', 'Failed User Not Found (ID: '.$uniqueId.')', null, null);
                }
            } catch (PDOException $e) {
                $alertMessage = 'A system error occurred. Please try again later.';
                $alertType = 'alert-error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - User Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Saraswati Abhyasika Logo" class="auth-logo">
        <h1>सरस्वती अभ्यासिका</h1>
        <p>Sign in to your library account</p>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken()) ?>">
        <div class="form-group">
            <label for="unique_id">Unique Login ID</label>
            <input type="text" id="unique_id" name="unique_id" required placeholder="UNIQUE ID">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="PASSWORD">
            <div style="text-align: right; margin-top: 5px;">
                <a href="forgot_password.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none;">Forgot Password?</a>
            </div>
        </div>

        <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <div class="auth-footer">
        <a href="register.php" class="btn-secondary">Create Account</a>
    </div>
</div>

</body>
</html>
