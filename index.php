<?php
// Root index.php - Main Landing Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Saraswati Abhyasika</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="landing-container">
    <div class="logo">
        <img src="IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Saraswati Abhyasika" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM0ZjQ2ZTUiIHN0cm9rZS13aWR0aD0iMiI+PHBhdGggZD0iTTEyIDJMMiA3bDEwIDUgMTAtNS0xMC01ek0yIDE3bDEwIDUgMTAtNU0yIDEybDEwIDUgMTAtNSIvPjwvc3ZnPg=='; this.style.width='50px'; this.style.height='50px';">
    </div>
    
    <h1>सरस्वती अभ्यासिका</h1>
    <p class="subtitle">Welcome to the Library Management System. Please select your portal to continue.</p>

    <div class="button-group">
        <a href="USER/index.php" class="btn btn-user">🎓 Student Portal</a>
        <a href="pratik2002/index.php" class="btn btn-admin">🛡️ Admin Portal</a>
    </div>

    <div class="footer">
        &copy; <?php echo date('Y'); ?> Saraswati Abhyasika. All rights reserved.
    </div>
</div>

</body>
</html>
