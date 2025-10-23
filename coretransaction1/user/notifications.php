<?php
define('APP_LOADED', true);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../backend/db1.php';
$user_id = $_SESSION['user_id'];

// Fetch driver_id (optional, but kept for consistency)
try {
    $stmt = $conn->prepare("SELECT drivers_id FROM drivers WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        // Not critical for notifications, so continue
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Driver fetch error: " . $e->getMessage());
}

// Fetch notifications
try {
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            message,
            type,
            status,
            created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $notifications_result = $stmt->get_result();
    $notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $notifications = [];
    error_log("Error fetching notifications: " . $e->getMessage());
}

$unread_count = 0;
foreach ($notifications as $notif) {
    if ($notif['status'] === 'unread') {
        $unread_count++;
    }
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_as_read' && isset($_POST['notification_id'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($_POST['action'] === 'mark_all_as_read') {
        $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'");
        $stmt->bind_param("s", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_notification' && isset($_POST['notification_id'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }
}

function getNotificationIcon($type) {
    $icons = [
        'trip' => 'fa-car',
        'wallet' => 'fa-wallet',
        'maintenance' => 'fa-wrench',
        'document' => 'fa-file-alt',
        'system' => 'fa-info-circle',
        'warning' => 'fa-exclamation-triangle',
        'success' => 'fa-check-circle',
        'error' => 'fa-times-circle'
    ];
    return $icons[$type] ?? 'fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'trip' => '#4CAF50',
        'wallet' => '#2196F3',
        'maintenance' => '#FF9800',
        'document' => '#9C27B0',
        'system' => '#607D8B',
        'warning' => '#FFC107',
        'success' => '#4CAF50',
        'error' => '#F44336'
    ];
    return $colors[$type] ?? '#757575';
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $time_ago = time() - $timestamp;
    if ($time_ago < 60) return 'Just now';
    elseif ($time_ago < 3600) {
        $m = floor($time_ago / 60);
        return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago';
    } elseif ($time_ago < 86400) {
        $h = floor($time_ago / 3600);
        return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago';
    } elseif ($time_ago < 604800) {
        $d = floor($time_ago / 86400);
        return $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #fff; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #0f0f0f; }
        .content-area { padding: 40px; width: 100%; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 20px; flex-wrap: wrap; }
        .page-title { font-size: 42px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 15px; }
        .btn-mark-all { padding: 16px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 12px; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .btn-mark-all:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .stat-card { background: linear-gradient(135deg, #1a1a1a 0%, #252525 100%); padding: 32px; border-radius: 20px; border: 1px solid #2a2a2a; transition: all 0.3s ease; }
        .stat-value { font-size: 48px; font-weight: 700; color: #fff; }
        .filter-tabs { display: flex; gap: 14px; margin-bottom: 30px; overflow-x: auto; padding-bottom: 10px; }
        .filter-tab { padding: 14px 28px; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 30px; color: #888; font-size: 16px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.3s ease; }
        .filter-tab.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
        .notifications-list { display: flex; flex-direction: column; gap: 20px; }
        .notification-card { background: linear-gradient(135deg, #1a1a1a 0%, #252525 100%); border-radius: 20px; padding: 28px; border-left: 5px solid; display: flex; align-items: flex-start; gap: 20px; transition: all 0.3s ease; border: 1px solid #2a2a2a; position: relative; }
        .notification-card.unread { background: linear-gradient(135deg, #252525 0%, #2f2f2f 100%); border-color: #667eea; }
        .notification-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex-shrink: 0; }
        .notification-message { color: #fff; font-size: 15px; line-height: 1.6; margin-bottom: 10px; }
        .notification-meta { display: flex; align-items: center; gap: 16px; font-size: 13px; color: #888; flex-wrap: wrap; }
        .notification-type { background: #2a2a2a; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .notification-actions { display: flex; gap: 8px; align-items: center; }
        .notification-btn { background: #2a2a2a; border: none; color: #888; cursor: pointer; padding: 10px; border-radius: 8px; transition: all 0.3s ease; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
        .notification-btn:hover { color: #fff; background: #3a3a3a; transform: scale(1.1); }
        .notification-btn.delete:hover { color: #f44336; background: rgba(244, 67, 54, 0.1); }
        .empty-state { text-align: center; padding: 80px 20px; color: #888; }
        .empty-state i { font-size: 80px; margin-bottom: 24px; opacity: 0.3; color: #667eea; }
        .empty-state h3 { font-size: 24px; margin-bottom: 12px; color: #fff; font-weight: 600; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .content-area { padding: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-mark-all { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .notification-card { padding: 16px; }
            .notification-icon { width: 40px; height: 40px; font-size: 18px; }
            .page-title { font-size: 24px; }
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'include/header.php'; ?>
        <div class="content-area">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-bell"></i> Notifications</h1>
                <button class="btn-mark-all" id="markAllRead">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Notifications</h3>
                    <div class="stat-value"><?php echo count($notifications); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Unread</h3>
                    <div class="stat-value"><?php echo $unread_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Read</h3>
                    <div class="stat-value"><?php echo count($notifications) - $unread_count; ?></div>
                </div>
            </div>

            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">All</button>
                <button class="filter-tab" data-filter="unread">Unread</button>
                <button class="filter-tab" data-filter="read">Read</button>
                <button class="filter-tab" data-filter="trip">Trips</button>
                <button class="filter-tab" data-filter="wallet">Wallet</button>
                <button class="filter-tab" data-filter="maintenance">Maintenance</button>
                <button class="filter-tab" data-filter="system">System</button>
            </div>

            <div class="notifications-list" id="notificationsList">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! No notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo $notification['status'] === 'unread' ? 'unread' : ''; ?>" 
                             data-notification-id="<?php echo htmlspecialchars($notification['notification_id']); ?>"
                             data-status="<?php echo htmlspecialchars($notification['status']); ?>"
                             data-type="<?php echo htmlspecialchars($notification['type']); ?>"
                             style="border-left-color: <?php echo getNotificationColor($notification['type']); ?>">
                            
                            <div class="notification-icon" style="background: <?php echo getNotificationColor($notification['type']); ?>">
                                <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                <div class="notification-meta">
                                    <span class="notification-type">
                                        <?php echo ucfirst(htmlspecialchars($notification['type'])); ?>
                                    </span>
                                    <span class="notification-time">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo timeAgo($notification['created_at']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if ($notification['status'] === 'unread'): ?>
                                    <button class="notification-btn mark-read" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="notification-btn delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php include 'include/footer.php'; ?>
    </main>
    <?php include 'include/logout_modal.php'; ?>

    <script>
        // Mark single as read
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const card = this.closest('.notification-card');
                const id = card.dataset.notificationId;
                
                fetch('./notifications.php', { // ✅ Correct filename with "s"
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=mark_as_read&notification_id=${id}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        card.classList.remove('unread');
                        card.dataset.status = 'read';
                        this.remove();
                        updateStats();
                    }
                })
                .catch(() => alert('Failed to update'));
            });
        });

        // Mark all as read
        document.getElementById('markAllRead').addEventListener('click', function() {
            if (!confirm('Mark all as read?')) return;
            
            fetch('./notifications.php', { // ✅ Correct filename
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_all_as_read'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.dataset.status = 'read';
                        const btn = card.querySelector('.mark-read');
                        if (btn) btn.remove();
                    });
                    updateStats();
                }
            })
            .catch(() => alert('Failed to update'));
        });

        // Delete
        document.querySelectorAll('.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!confirm('Delete this notification?')) return;
                
                const card = this.closest('.notification-card');
                const id = card.dataset.notificationId;
                
                fetch('./notifications.php', { // ✅ Correct filename
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_notification&notification_id=${id}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                        updateStats();
                    }
                })
                .catch(() => alert('Failed to delete'));
            });
        });

        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                document.querySelectorAll('.notification-card').forEach(card => {
                    if (filter === 'all') card.style.display = 'flex';
                    else if (filter === 'unread' || filter === 'read') 
                        card.style.display = card.dataset.status === filter ? 'flex' : 'none';
                    else 
                        card.style.display = card.dataset.type === filter ? 'flex' : 'none';
                });
            });
        });

        // Update stats without reload
        function updateStats() {
            const all = document.querySelectorAll('.notification-card').length;
            const unread = document.querySelectorAll('.notification-card.unread').length;
            document.querySelectorAll('.stat-value')[0].textContent = all;
            document.querySelectorAll('.stat-value')[1].textContent = unread;
            document.querySelectorAll('.stat-value')[2].textContent = all - unread;
        }
    </script>
</body>
</html>