<?php
require_once 'config.php';
require_once '../includes/Security.php';
require_once '../includes/Logger.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request (CSRF check failed). Please try again.";
    } else {
        $email = $_POST['email'];
        $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin) {
        if (password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['admin_id'];
            header("Location: Dashboard.php");
            exit;
        } else if ($admin['password'] === $password) {
            // Auto-upgrade plaintext password from SQL import
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
            $update->execute([$newHash, $admin['admin_id']]);

            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['admin_id'];
            header("Location: Dashboard.php");
            exit;
            } else {
                $error = "Invalid Email or Password";
                Logger::logAudit($pdo, 'Admin Login', 'Failed (Wrong Password)', null, $admin['admin_id']);
            }
        } else {
            $error = "Invalid Email or Password";
            Logger::logAudit($pdo, 'Admin Login', 'Failed (User Not Found)', null, null);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Library Management</title>
    <link rel="stylesheet" href="index.css">
</head>

<body>

<div class="login-container">

    <!-- Logo -->
    <div class="logo-container">
        <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Library Logo">
    </div>
    
    <div class="site-title-badge">
        सरस्वती अभ्यासिका
    </div>

    <h2>Admin <span>Login</span></h2>

    <?php if(isset($error)) { echo "<p class='error'>$error</p>"; } ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken()) ?>">

        <div class="form-group">
            <label>Email Address</label>
            <input
                type="email"
                name="email"
                placeholder="youremailexample@gmail.com"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="••••••••"
                required
            >
        </div>

        <button type="submit" class="btn-login">
            Login
        </button>

    </form>

</div>

</body>
</html>