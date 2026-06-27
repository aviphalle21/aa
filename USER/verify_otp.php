<?php
require_once 'config.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Security.php';

SessionManager::startSecureSession();

// Ensure the user went through forgot_password.php first
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$alertMessage = '';
$alertType = '';
$csrfToken = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_entered = trim($_POST['otp']);
    $token = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCSRFToken($token)) {
        $alertMessage = 'Invalid security token. Please try again.';
        $alertType = 'alert-error';
    } elseif (empty($otp_entered)) {
        $alertMessage = 'Please enter the 6-digit OTP.';
        $alertType = 'alert-error';
    } else {
        $stmt = $pdo->prepare("SELECT user_id, reset_otp FROM users WHERE user_id = ? AND reset_otp_expires > NOW()");
        $stmt->execute([$_SESSION['reset_user_id']]);
        $validUser = $stmt->fetch();
        
        if ($validUser && password_verify($otp_entered, $validUser['reset_otp'])) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit;
        } else {
            $alertMessage = 'Invalid or expired OTP. Please try again or request a new one.';
            $alertType = 'alert-error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - User Portal</title>
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
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Saraswati Abhyasika Logo" class="auth-logo">
        <h1>सरस्वती अभ्यासिका</h1>
        <p>Enter Verification Code</p>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>
    
    <p style="text-align:center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
        We sent a 6-digit code to <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>. It will expire in 5 minutes.
    </p>

    <form method="POST" action="verify_otp.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="form-group">
            <input type="text" id="otp" name="otp" class="otp-input" required maxlength="6" pattern="[0-9]{6}" placeholder="------">
        </div>

        <button type="submit" class="btn-primary">Verify OTP</button>
    </form>

    <div class="auth-footer">
        <a href="forgot_password.php" class="btn-secondary">Didn't receive it? Request again</a>
    </div>
</div>

</body>
</html>
