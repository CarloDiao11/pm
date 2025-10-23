<?php
// header.php - Dynamic Top Navigation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../backend/db.php';

$userName = 'User';
$userRole = 'user';
$profilePicture = null;

if (!empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user = db_select('users', ['user_id' => $user_id], ['limit' => 1]);

    if ($user && !empty($user[0]['user_id'])) {
        // Use name if available, otherwise fall back to username or "User"
        $name = trim($user[0]['name'] ?? '');
        $username = trim($user[0]['username'] ?? '');
        $userName = $name ?: ($username ?: 'User');
        $userName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

        $userRole = htmlspecialchars($user[0]['role'] ?? 'user', ENT_QUOTES, 'UTF-8');

        // Handle profile picture
        $pic = trim($user[0]['profile_picture'] ?? '');
        if ($pic !== '') {
            // If it's not a full URL, treat as relative to web root
            if (!preg_match('~^https?://~i', $pic)) {
                // Normalize to web-accessible path (assuming uploads/ is in project root)
                $pic = '/' . ltrim($pic, '/');
            }
            $profilePicture = $pic;
        }
    }
}

// Notifications count
$notificationCount = 0;
if (!empty($_SESSION['user_id'])) {
    $notificationCount = db_count('notifications', [
        'user_id' => $_SESSION['user_id'],
        'status' => 'unread'
    ]);
}
?>

<nav class="top-navbar">
    <div class="navbar-content">
        <div class="navbar-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h4 class="mb-0 ms-3 d-none d-md-block">Welcome back, <?= $userName ?>!</h4>
        </div>
        
        <div class="navbar-right">
            <!-- Notifications -->
            <div class="dropdown">
                <div class="notification-icon" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell" style="font-size: 1.3rem;"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?= (int)$notificationCount ?></span>
                    <?php endif; ?>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                    <li class="dropdown-header"><strong>Notifications</strong></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if ($notificationCount > 0):
                        $notifications = db_select('notifications', [
                            'user_id' => $_SESSION['user_id'],
                            'status' => 'unread'
                        ], ['order_by' => 'created_at DESC', 'limit' => 3]);
                        foreach ($notifications as $notif):
                            $message = htmlspecialchars($notif['message'] ?? 'No message', ENT_QUOTES, 'UTF-8');
                            $date = $notif['created_at'] ? date('M j, Y g:i A', strtotime($notif['created_at'])) : '';
                    ?>
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-info-circle text-info"></i>
                            <span><?= $message ?></span>
                            <small class="d-block text-muted"><?= $date ?></small>
                        </a></li>
                    <?php endforeach;
                    else: ?>
                        <li><a class="dropdown-item text-center text-muted">No new notifications</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center" href="notifications.php"><small>View all notifications</small></a></li>
                </ul>
            </div>
            
            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($profilePicture): ?>
                        <img src="<?= htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8') ?>"
                             alt="Profile"
                             class="user-avatar-img"
                             onerror="this.style.display='none'; document.querySelector('.user-avatar-fallback').style.display='flex';">
                        <div class="user-avatar user-avatar-fallback" style="display: none;">
                            <?= strtoupper(substr($userName, 0, 1) . (preg_match('/\s(\w)/', $userName, $m) ? $m[1] : '')) ?>
                        </div>
                    <?php else: ?>
                        <div class="user-avatar">
                            <?= strtoupper(substr($userName, 0, 1) . (preg_match('/\s(\w)/', $userName, $m) ? $m[1] : '')) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-none d-md-block ms-2">
                        <div style="line-height: 1.2;">
                            <strong style="font-size: 0.9rem;"><?= $userName ?></strong>
                            <small class="d-block text-muted"><?= ucfirst($userRole) ?></small>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down ms-2"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<style>
.user-avatar-img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 10px;
}
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    margin-right: 10px;
}
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4d4d;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.notification-icon {
    position: relative;
    cursor: pointer;
    padding: 8px;
}
</style>