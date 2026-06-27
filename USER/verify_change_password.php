<?php
require_once 'config.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Security.php';
require_once '../includes/Logger.php';

SessionManager::startSecureSession();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['change_pwd_otp_sent'])) {
    header("Location: dashboard.php");
    exit;
}

$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $alertMessage = 'Invalid request (CSRF check failed). Please try again.';
        $alertType = 'alert-error';
    } else {
        $otp_entered = trim($_POST['otp']);
        
        if (empty($otp_entered)) {
            $alertMessage = 'Please enter the 6-digit OTP.';
            $alertType = 'alert-error';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, unique_user_id, full_name, reset_otp FROM users WHERE user_id = ? AND reset_otp_expires > NOW()");
            $stmt->execute([$_SESSION['user_id']]);
            $validUser = $stmt->fetch();
            
            if ($validUser && password_verify($otp_entered, $validUser['reset_otp'])) {
                // Apply the new password
                $hashedPassword = $_SESSION['pending_new_password_hash'];
                $updStmt = $pdo->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expires = NULL WHERE user_id = ?");
                $updStmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                Logger::logAudit($pdo, 'Change Password', 'Success', $_SESSION['user_id'], null);
                
                // Insert Notification
                $notifStmt = $pdo->prepare("INSERT INTO system_notifications (type, title, message) VALUES ('General', 'Security Update', ?)");
                $notifMsg = "User " . $validUser['full_name'] . " (" . $validUser['unique_user_id'] . ") changed their password successfully.";
                $notifStmt->execute([$notifMsg]);
                
                unset($_SESSION['change_pwd_otp_sent']);
                unset($_SESSION['pending_new_password_hash']);
                
                header("Location: dashboard.php");
                exit;
            } else {
                $alertMessage = 'Invalid or expired OTP. Please request a new one.';
                $alertType = 'alert-error';
                Logger::logAudit($pdo, 'Change Password', 'Failed (Invalid OTP)', $_SESSION['user_id'], null);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Password Change - Saraswati Abhyasika</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .otp-input {
            letter-spacing: 5px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Logo" class="auth-logo">
        <h1>Verify OTP</h1>
        <p>Confirm Password Change</p>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>
    
    <p style="text-align:center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
        Please enter the 6-digit OTP sent to your registered email to confirm the password change.
    </p>

    <form method="POST" action="verify_change_password.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken()) ?>">
        <div class="form-group">
            <input type="text" id="otp" name="otp" class="otp-input" required maxlength="6" pattern="[0-9]{6}" placeholder="------">
        </div>

        <button type="submit" class="btn-primary">Confirm & Change Password</button>
    </form>

    <div class="auth-footer">
        <a href="change_password.php" class="btn-secondary">Cancel</a>
    </div>
</div>

</body>
</html>
