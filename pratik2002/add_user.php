<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $plainPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email) || empty($phone) || empty($plainPassword)) {
        $alertMessage = 'Please fill name, email, phone, and password.';
        $alertType = 'alert-error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alertMessage = 'Please enter a valid email address.';
        $alertType = 'alert-error';
    } elseif ($plainPassword !== $confirmPassword) {
        $alertMessage = 'Password and confirm password do not match.';
        $alertType = 'alert-error';
    } elseif (strlen($plainPassword) < 8) {
        $alertMessage = 'Password must be at least 8 characters long.';
        $alertType = 'alert-error';
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    // Generate unique ID
    $uniqueStmt = $pdo->query("SELECT MAX(user_id) as max_id FROM users");
    $maxRow = $uniqueStmt->fetch();
    $nextId = ($maxRow['max_id'] ?? 0) + 1;
    $uniqueUserId = 'USR-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    try {
        $insertStmt = $pdo->prepare("INSERT INTO users (unique_user_id, full_name, email, phone, address, password) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$uniqueUserId, $fullName, $email, $phone, $address, $password]);
        $alertMessage = 'User added successfully! The member can now login with email and the password you set.';
        $alertType = 'alert-success';
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            if (strpos($e->getMessage(), 'email') !== false) {
                $alertMessage = 'This email is already registered. Please use a different email or ask the user to login.';
            } elseif (strpos($e->getMessage(), 'phone') !== false) {
                $alertMessage = 'This phone number is already registered. Please use a different phone number.';
            } else {
                $alertMessage = 'This user already exists. Please check email and phone number.';
            }
        } else {
            $alertMessage = 'Could not add user right now. Please try again later.';
        }
        $alertType = 'alert-error';
    }
    }
}
$pageTitle = 'Add New User';
$showBackButton = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--navy-blue);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: inherit;
        }
        .btn-submit {
            background: var(--brand-crimson);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        .btn-submit:hover {
            background: var(--brand-crimson-dark);
            color: #fff;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: 600;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="form-container">
        <h2>Register New Member</h2>
        <?php if ($alertMessage): ?>
            <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn-submit">Add User</button>
        </form>
    </div>
    </div>
<?php if ($alertMessage): ?>
<script>
    alert(<?= json_encode($alertMessage) ?>);
</script>
<?php endif; ?>
<?php include 'footer.php'; ?>
