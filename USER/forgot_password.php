<?php
require_once 'config.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Security.php';

SessionManager::startSecureSession();

$alertMessage = '';
$alertType = '';
$csrfToken = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $token = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCSRFToken($token)) {
        $alertMessage = 'Invalid security token. Please try again.';
        $alertType = 'alert-error';
    } elseif (empty($email)) {
        $alertMessage = 'Please enter your registered email address.';
        $alertType = 'alert-error';
    } else {
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, reset_otp_requested_at, otp_resend_count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check Rate Limiting (max 3 times per 15 minutes)
            $canSend = true;
            if ($user['reset_otp_requested_at']) {
                $lastRequested = new DateTime($user['reset_otp_requested_at']);
                $now = new DateTime();
                $diff = $now->getTimestamp() - $lastRequested->getTimestamp();
                if ($diff < 900) { // 15 mins
                    if ($user['otp_resend_count'] >= 3) {
                        $canSend = false;
                        $alertMessage = 'Too many requests. Please try again after 15 minutes.';
                        $alertType = 'alert-error';
                    }
                } else {
                    // Reset count if 15 mins passed
                    $pdo->prepare("UPDATE users SET otp_resend_count = 0 WHERE user_id = ?")->execute([$user['user_id']]);
                    $user['otp_resend_count'] = 0;
                }
            }

            if ($canSend) {
                // Generate 6-digit OTP
                $otp = sprintf("%06d", mt_rand(1, 999999));
                $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
                
                // Store hashed OTP in database with 5 mins expiry
                $updStmt = $pdo->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires = DATE_ADD(NOW(), INTERVAL 5 MINUTE), reset_otp_requested_at = NOW(), otp_resend_count = otp_resend_count + 1 WHERE user_id = ?");
                $updStmt->execute([$hashedOtp, $user['user_id']]);
                
                // Send Email
                $to = $user['email'];
                $subject = "Password Reset OTP - Saraswati Abhyasika";
                $message = "Hello " . $user['full_name'] . ",\n\nYour OTP to reset your password is: " . $otp . "\n\nThis OTP is valid for 5 minutes. If you did not request a password reset, please ignore this email.";
                $headers = "From: noreply@saraswatiabhyasika.com\r\n" .
                           "Reply-To: noreply@saraswatiabhyasika.com\r\n" .
                           "X-Mailer: PHP/" . phpversion();
                
                @mail($to, $subject, $message, $headers);
                
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email'] = $user['email'];
                
                header("Location: verify_otp.php");
                exit;
            }
        } else {
            // Do not reveal if email exists or not
            $alertMessage = 'If that email is registered, an OTP has been sent.';
            $alertType = 'alert-success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - User Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Saraswati Abhyasika Logo" class="auth-logo">
        <h1>सरस्वती अभ्यासिका</h1>
        <p>Reset Your Password</p>
    </div>

    <?php if ($alertMessage): ?>
        <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
    <?php endif; ?>
    
    <?php if ($alertMessage !== 'If that email is registered, an OTP has been sent.'): ?>
        <p style="text-align:center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
            Enter your registered email address and we will send you a 6-digit OTP to reset your password.
        </p>
        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="john@example.com">
            </div>

            <button type="submit" class="btn-primary">Send OTP</button>
        </form>
    <?php else: ?>
        <p style="text-align:center; color: var(--success); font-size: 0.9rem; margin-bottom: 20px;">
            <a href="verify_otp.php" style="text-decoration:underline;">Click here to continue to verification</a>
        </p>
    <?php endif; ?>

    <div class="auth-footer">
        <a href="index.php" class="btn-secondary">Back to Login</a>
    </div>
</div>

</body>
</html>
