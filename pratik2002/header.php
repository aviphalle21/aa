<?php
// Expiry Check Logic
$expiringCheckStmt = $pdo->query("
    SELECT us.subscription_id, u.full_name, t.table_number, us.expiry_date
    FROM user_subscriptions us
    JOIN users u ON us.user_id = u.user_id
    JOIN library_tables t ON us.table_id = t.table_id
    WHERE us.subscription_status = 'Active' 
    AND us.expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY)
    AND us.subscription_id NOT IN (
        SELECT related_id FROM system_notifications WHERE type = 'expiry'
    )
");

$newExpiries = $expiringCheckStmt->fetchAll();
foreach ($newExpiries as $expiry) {
    $insertNotif = $pdo->prepare("INSERT INTO system_notifications (title, message, type, related_id) VALUES (?, ?, 'expiry', ?)");
    $title = "Subscription Expiring Soon";
    $message = "User {$expiry['full_name']} at Table T-{$expiry['table_number']} has a subscription expiring on {$expiry['expiry_date']}.";
    $insertNotif->execute([$title, $message, $expiry['subscription_id']]);
}

// Fetch Notifications (Both unread and recent read for context)
$notifStmt = $pdo->query("SELECT * FROM system_notifications ORDER BY is_read ASC, created_at DESC LIMIT 15");
$notifications = $notifStmt->fetchAll();

// Count unread
$unreadCountStmt = $pdo->query("SELECT COUNT(*) FROM system_notifications WHERE is_read = 0");
$unreadCount = $unreadCountStmt->fetchColumn();
?>

<script>
    (function() {
        const theme = JSON.parse(localStorage.getItem('appTheme'));
        if (theme) {
            for (const key in theme) {
                document.documentElement.style.setProperty(key, theme[key]);
            }
        }
    })();
</script>

<style>
/* --- NOTIFICATIONS DRAWER --- */
.notification-wrapper:hover .bell-icon { transform: scale(1.1); }
.bell-icon { transition: transform 0.2s ease; cursor: pointer; font-size: 1.5rem; position: relative; }
.notification-badge {
    position: absolute; top: -5px; right: -10px; font-size: 0.65rem;
    padding: 2px 6px; border-radius: 50%; background: #dc2626;
    color: white; border: 1px solid white;
}
.nav-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.4); z-index: 999; opacity: 0; visibility: hidden; transition: all 0.3s ease;
}
.nav-overlay.open { opacity: 1; visibility: visible; }
.notification-drawer {
    position: fixed; top: 0; right: -420px; width: 400px; height: 100%;
    background: #fff; z-index: 1000; box-shadow: -4px 0 15px rgba(0,0,0,0.1);
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column;
}
.notification-drawer.open { right: 0; }
.drawer-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px; background: #0f172a; color: #fff;
}
.drawer-header h3 { margin: 0; font-size: 1.2rem; font-family: 'Merriweather', serif; }
.mute-btn { background: none; border: none; color: #fff; cursor: pointer; font-size: 1.2rem; opacity: 0.8; transition: opacity 0.2s; }
.mute-btn:hover { opacity: 1; }
.close-drawer { background: none; border: none; color: #fff; font-size: 1.2rem; cursor: pointer; }
.drawer-actions {
    padding: 12px 20px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center; background: #f8fafc;
}
.btn-text { background: none; border: none; color: #3b82f6; font-weight: 600; cursor: pointer; }
.btn-text:hover { text-decoration: underline; }
.drawer-content { flex: 1; overflow-y: auto; padding: 10px 0; }
.notification-item {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 16px 20px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; position: relative;
}
.notification-item:hover { background: #f8fafc; }
.notification-item.unread { background: #eff6ff; }
.notification-item.unread:hover { background: #dbeafe; }
.notif-body { flex: 1; padding-right: 15px; }
.notif-title { font-weight: 700; color: #0f172a; margin-bottom: 5px; font-size: 0.95rem; }
.notif-msg { font-size: 0.85rem; color: #64748b; line-height: 1.4; margin-bottom: 8px; }
.notif-time { font-size: 0.75rem; color: #9ca3af; font-weight: 500; }
.btn-delete { background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: 1rem; transition: color 0.2s; }
.btn-delete:hover { color: #dc2626; }
.empty-state { padding: 40px 20px; text-align: center; color: #94a3b8; font-weight: 500; }
</style>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="../IMAGES/SHREE SARASWATI ABHYASIKA LOGO.png" alt="Logo" class="sidebar-logo">
            <div class="brand-text">
                <div class="title">सरस्वती अभ्यासिका</div>
                <div class="subtitle">Library Management</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">MAIN</div>
            <a href="Dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'Dashboard.php' ? 'active' : '' ?>">
                <span class="icon">🏠</span> Dashboard
            </a>
            <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <span class="icon">👤</span> Users
            </a>
            <a href="tables.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'tables.php' ? 'active' : '' ?>">
                <span class="icon">🪑</span> Tables
            </a>
            <a href="payments.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                <span class="icon">💳</span> Payments
            </a>
            <a href="attendance.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>">
                <span class="icon">📋</span> Attendance
            </a>
            <a href="#" class="nav-item" onclick="openNavDrawer()">
                <span class="icon">🔔</span> Notifications
            </a>

            <div class="nav-section">SETTINGS</div>
            <a href="reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                <span class="icon">📊</span> Reports
            </a>
            <a href="settings.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                <span class="icon">⚙️</span> Settings
            </a>
        </nav>

        <div class="sidebar-profile">
            <img src="https://ui-avatars.com/api/?name=Suhas+Vibhute&background=111827&color=fff" alt="Suhas Vibhute">
            <div class="profile-info">
                <div class="name">Suhas Vibhute</div>
                <div class="role">ADMIN</div>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="mobile-menu-toggle" onclick="toggleMobileNav()" style="font-size:1.5rem; cursor:pointer; display:none; padding-right:15px;">☰</div>
            <div class="topbar-search">
                <form id="globalSearchForm" onsubmit="handleGlobalSearch(event, this.search.value)" style="display: flex; align-items: center; width: 100%;">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" placeholder="Search tables, members, payments..." style="border: none; outline: none; background: transparent; width: 100%;">
                </form>
            </div>
            
            <div class="topbar-actions">
                <div class="date-widget">
                    <span class="calendar-icon" onclick="document.getElementById('hiddenDatePicker').showPicker()">📅</span>
                    <input type="date" id="hiddenDatePicker" style="visibility:hidden; position:absolute; right: 200px; top: 10px;">
                    <div class="datetime">
                        <div id="liveDate" class="date-text"></div>
                        <div id="liveTime" class="time-text"></div>
                    </div>
                </div>
                
                <div class="notification-wrapper" onclick="openNavDrawer()">
                    <div class="bell-icon">
                        🔔
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge notification-badge" id="badge-count"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-dropdown">
                    <img src="https://ui-avatars.com/api/?name=Suhas+Vibhute&background=111827&color=fff" alt="Suhas Vibhute" class="topbar-avatar">
                    <div class="admin-info">
                        <div class="name">Suhas Vibhute</div>
                        <div class="role">ADMIN</div>
                    </div>
                    <a href="logout.php" class="logout-icon" title="Logout">LOGOUT<br><center>👋</center></a>
                </div>
            </div>
        </header>
        <main class="page-content">

<!-- Notification Overlay -->
<div id="navOverlay" class="nav-overlay" onclick="closeNavDrawer()"></div>

<!-- Notification Drawer Panel -->
<div id="notificationDrawer" class="notification-drawer">
    <div class="drawer-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3>Notifications</h3>
            <button id="muteBtn" onclick="toggleMute()" class="mute-btn" title="Toggle Sound">🔊</button>
        </div>
        <button class="close-drawer" onclick="closeNavDrawer()">✕</button>
    </div>
    
    <div class="drawer-actions">
        <span style="font-size:0.85rem; color:#6b7280; font-weight:600;"><span id="unreadCountText"><?= $unreadCount ?></span> Unread</span>
        <?php if (count($notifications) > 0): ?>
            <button onclick="markAllRead()" class="btn-text">Mark all read</button>
        <?php endif; ?>
    </div>

    <div class="drawer-content">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" id="notif-<?= $notif['notification_id'] ?>">
                    <div class="notif-body">
                        <div class="notif-title">
                            <?php
                                $typeLower = strtolower($notif['type']);
                                if($typeLower == 'payment') echo '💰 ';
                                elseif($typeLower == 'booking') echo '🪑 ';
                                elseif($typeLower == 'expiry') echo '⚠️ ';
                                elseif($typeLower == 'registration') echo '🎉 ';
                                elseif($typeLower == 'login') echo '🔑 ';
                                else echo 'ℹ️ ';
                            ?>
                            <?= htmlspecialchars($notif['title']) ?>
                        </div>
                        <div class="notif-msg"><?= htmlspecialchars($notif['message']) ?></div>
                        <div class="notif-time"><?= date('d M, h:i A', strtotime($notif['created_at'])) ?></div>
                    </div>
                    <button class="btn-delete" onclick="deleteNotification(<?= $notif['notification_id'] ?>)" title="Remove">✕</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No notifications here!</div>
        <?php endif; ?>
    </div>
</div>

<!-- Audio for notification -->
<audio id="notifSound" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<script>
// Smart Global Search Routing
function handleGlobalSearch(e, query) {
    e.preventDefault();
    query = query.toLowerCase().trim();
    if (!query) return;

    if (query.includes('table') || query.startsWith('t1') || query.startsWith('t2') || query.startsWith('t-')) {
        window.location.href = 'tables.php';
    } else if (query.includes('payment') || query.includes('revenue') || query.includes('money')) {
        window.location.href = 'payments.php';
    } else if (query.includes('report') || query.includes('analytic')) {
        window.location.href = 'reports.php';
    } else if (query.includes('setting')) {
        window.location.href = 'settings.php';
    } else {
        window.location.href = 'Dashboard.php?search=' + encodeURIComponent(query);
    }
}

// UI Interactions
function openNavDrawer() {
    document.getElementById("notificationDrawer").classList.add('open');
    document.getElementById("navOverlay").classList.add('open');
}

function closeNavDrawer() {
    document.getElementById("notificationDrawer").classList.remove('open');
    document.getElementById("navOverlay").classList.remove('open');
}

function toggleMobileNav() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar.classList.contains('nav-open')) {
        sidebar.classList.remove('nav-open');
    } else {
        sidebar.classList.add('nav-open');
    }
}

// Live Date and Time
function updateDateTime() {
    const now = new Date();
    
    const dateOpts = { day: '2-digit', month: 'short', year: 'numeric' };
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-GB', dateOpts);
    
    const timeOpts = { hour: '2-digit', minute: '2-digit', hour12: true };
    document.getElementById('liveTime').innerText = now.toLocaleTimeString('en-US', timeOpts);
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Sound Management
let isMuted = localStorage.getItem('notifMuted') === 'true';
updateMuteIcon();

function toggleMute() {
    isMuted = !isMuted;
    localStorage.setItem('notifMuted', isMuted);
    updateMuteIcon();
}

function updateMuteIcon() {
    document.getElementById('muteBtn').innerText = isMuted ? '🔇' : '🔊';
}

// Check if we have new notifications compared to last page load
let currentUnread = <?= (int)$unreadCount ?>;
let lastUnread = sessionStorage.getItem('lastUnreadCount') || 0;

if (currentUnread > lastUnread && !isMuted) {
    let sound = document.getElementById('notifSound');
    sound.volume = 0.5;
    let playPromise = sound.play();
    if (playPromise !== undefined) {
        playPromise.catch(error => { console.log("Autoplay prevented: " + error); });
    }
}
sessionStorage.setItem('lastUnreadCount', currentUnread);

function markAllRead() {
    fetch('mark_notifications_read.php', { method: 'POST' })
    .then(response => response.text())
    .then(data => { location.reload(); });
}

function deleteNotification(id) {
    if(!confirm("Remove this notification?")) return;
    let formData = new FormData();
    formData.append('id', id);
    fetch('delete_notification.php', { method: 'POST', body: formData })
    .then(response => response.text())
    .then(data => {
        if(data.trim() === 'OK') {
            document.getElementById('notif-' + id).remove();
        }
    });
}
</script>
