<?php
require_once 'config.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Security.php';
require_once '../includes/Logger.php';

SessionManager::startSecureSession();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $alertMessage = 'Invalid request (CSRF check failed). Please try again.';
        $alertType = 'alert-error';
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        
        if (empty($currentPassword) || empty($newPassword)) {
            $alertMessage = 'Please fill in both fields.';
            $alertType = 'alert-error';
        } elseif (strlen($newPassword) < 8 || !preg_match("/[A-Z]/", $newPassword) || !preg_match("/[a-z]/", $newPassword) || !preg_match("/[0-9]/", $newPassword) || !preg_match("/[\W_]/", $newPassword)) {
            $alertMessage = 'New password must be at least 8 characters long, contain an uppercase letter, a lowercase letter, a number, and a special character.';
            $alertType = 'alert-error';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, email, password, full_name FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (password_verify($currentPassword, $user['password'])) {
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(1, 999999));
                $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
                
                // Store OTP in database with 5 mins expiry
                $updStmt = $pdo->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE user_id = ?");
                $updStmt->execute([$hashedOtp, $user['user_id']]);
                
                // Send Email
                $to = $user['email'];
                $subject = "Change Password OTP - Saraswati Abhyasika";
                $message = "Hello " . $user['full_name'] . ",\n\nYour OTP to confirm your password change is: " . $otp . "\n\nThis OTP is valid for 5 minutes. If you did not request this, please contact support immediately.";
                $headers = "From: noreply@saraswatiabhyasika.com\r\nReply-To: noreply@saraswatiabhyasika.com\r\n";
                @mail($to, $subject, $message, $headers);
                
                // Store pending new password in session temporarily
                $_SESSION['pending_new_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $_SESSION['change_pwd_otp_sent'] = true;
                
                header("Location: verify_change_password.php");
                exit;
            } else {
                $alertMessage = 'Current password is incorrect.';
                $alertType = 'alert-error';
                Logger::logAudit($pdo, 'Change Password Request', 'Failed (Wrong Current Password)', $_SESSION['user_id'], null);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - Saraswati Abhyasika</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Logo" class="auth-logo">
        <h1>Change Password</h1>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>

    <form method="POST" action="change_password.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken()) ?>">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>
        <button type="submit" class="btn-primary">Request Change (Send OTP)</button>
    </form>

    <div class="auth-footer">
        <a href="dashboard.php" class="btn-secondary">Cancel & Go Back</a>
    </div>
</div>

</body>
</html>
