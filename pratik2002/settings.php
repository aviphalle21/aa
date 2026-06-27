<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$alertMessage = '';
$alertType = '';
$admin_id = $_SESSION['admin_id'];

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    
    try {
        $stmt = $pdo->prepare("UPDATE admin SET name = ?, email = ?, username = ? WHERE admin_id = ?");
        if ($stmt->execute([$name, $email, $username, $admin_id])) {
            $alertMessage = "Profile updated successfully!";
            $alertType = "alert-success";
        }
    } catch (PDOException $e) {
        $alertMessage = "Error updating profile. Email or username might already be in use.";
        $alertType = "alert-error";
    }
}

// Handle Password Update
if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
        if ($stmt->execute([$hashed, $admin_id])) {
            $alertMessage = "Password updated successfully!";
            $alertType = "alert-success";
        }
    } else {
        $alertMessage = "Passwords do not match.";
        $alertType = "alert-error";
    }
}

// Fetch current admin details
$stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Library Management</title>
    <link rel="stylesheet" href="Dashboard.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .settings-card h3 {
            margin-top: 0;
            color: var(--navy-blue);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-gray);
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
        }
        .form-group input:focus {
            border-color: var(--sidebar-active);
        }
        .btn-submit {
            background: var(--sidebar-active);
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<?php 
$pageTitle = 'Settings';
$showBackButton = true;
?>
<body>
    <?php include 'header.php'; ?>

    <div style="max-width: 1000px; margin: 0 auto;">
        <?php if ($alertMessage): ?>
            <div class="alert <?= $alertType ?>"><?= htmlspecialchars($alertMessage) ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Theme Settings (Full Width) -->
            <div class="settings-card" style="grid-column: 1 / -1;">
                <h3>Theme Presets</h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="theme-btn" onclick="setTheme('default')" style="background: #0f172a; color: #fff; padding: 15px 25px; border-radius: 8px; cursor: pointer; text-align: center; border: 2px solid #2563eb;">
                        <div style="font-weight: 600;">Navy Default</div>
                        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 5px;">Blue Accents</div>
                    </div>
                    <div class="theme-btn" onclick="setTheme('emerald')" style="background: #064e3b; color: #fff; padding: 15px 25px; border-radius: 8px; cursor: pointer; text-align: center; border: 2px solid transparent;">
                        <div style="font-weight: 600;">Emerald</div>
                        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 5px;">Green Accents</div>
                    </div>
                    <div class="theme-btn" onclick="setTheme('sunset')" style="background: #431407; color: #fff; padding: 15px 25px; border-radius: 8px; cursor: pointer; text-align: center; border: 2px solid transparent;">
                        <div style="font-weight: 600;">Sunset</div>
                        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 5px;">Orange Accents</div>
                    </div>
                    <div class="theme-btn" onclick="setTheme('midnight')" style="background: #111827; color: #fff; padding: 15px 25px; border-radius: 8px; cursor: pointer; text-align: center; border: 2px solid transparent;">
                        <div style="font-weight: 600;">Midnight</div>
                        <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 5px;">Indigo Accents</div>
                    </div>
                </div>
            </div>

            <!-- Profile Settings -->
            <div class="settings-card">
                <h3>Admin Profile</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($admin['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn-submit">Save Profile</button>
                </form>
            </div>

            <!-- Password Settings -->
            <div class="settings-card">
                <h3>Change Password</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" name="update_password" class="btn-submit">Update Password</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    const themes = {
        default: { '--sidebar-bg': '#0f172a', '--sidebar-active': '#2563eb', '--sidebar-hover': '#1e293b' },
        emerald: { '--sidebar-bg': '#064e3b', '--sidebar-active': '#10b981', '--sidebar-hover': '#065f46' },
        sunset:  { '--sidebar-bg': '#431407', '--sidebar-active': '#ea580c', '--sidebar-hover': '#7c2d12' },
        midnight:{ '--sidebar-bg': '#111827', '--sidebar-active': '#6366f1', '--sidebar-hover': '#1f2937' }
    };

    function setTheme(themeName) {
        const theme = themes[themeName];
        if (theme) {
            localStorage.setItem('appTheme', JSON.stringify(theme));
            localStorage.setItem('appThemeName', themeName);
            for (const key in theme) {
                document.documentElement.style.setProperty(key, theme[key]);
            }
            updateActiveButton(themeName);
        }
    }

    function updateActiveButton(themeName) {
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.style.borderColor = 'transparent';
        });
        const activeBtn = document.querySelector(`.theme-btn[onclick="setTheme('${themeName}')"]`);
        if (activeBtn) {
            const activeColor = themes[themeName]['--sidebar-active'];
            activeBtn.style.borderColor = activeColor;
        }
    }

    // Initialize on page load
    const currentThemeName = localStorage.getItem('appThemeName') || 'default';
    updateActiveButton(currentThemeName);
    </script>
    
<?php include 'footer.php'; ?>
