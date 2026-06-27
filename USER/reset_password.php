<?php
require_once 'config.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Security.php';

SessionManager::startSecureSession();

// Ensure the user went through forgot_password.php and verify_otp.php first
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';
$csrfToken = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCSRFToken($token)) {
        $alertMessage = 'Invalid security token. Please try again.';
        $alertType = 'alert-error';
    } elseif (empty($password) || empty($confirmPassword)) {
        $alertMessage = 'Please fill in both password fields.';
        $alertType = 'alert-error';
    } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[\W_]/", $password)) {
        $alertMessage = 'Password must be at least 8 characters long, contain an uppercase letter, a lowercase letter, a number, and a special character.';
        $alertType = 'alert-error';
    } elseif ($password !== $confirmPassword) {
        $alertMessage = 'Passwords do not match.';
        $alertType = 'alert-error';
    } else {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear OTP
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expires = NULL WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['reset_user_id']]);
        
        // Clear session variables
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['otp_verified']);
        
        // Set success message for login page
        $_SESSION['login_alert'] = "Password successfully reset! You can now log in.";
        
        header("Location: index.php?reset=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - User Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Saraswati Abhyasika Logo" class="auth-logo">
        <h1>सरस्वती अभ्यासिका</h1>
        <p>Set New Password</p>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>

    <form method="POST" action="reset_password.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required placeholder="Minimum 6 characters">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-type new password">
        </div>

        <button type="submit" class="btn-primary">Save New Password</button>
    </form>
</div>

</body>
</html>
