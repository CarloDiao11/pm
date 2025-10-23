<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Include database connection
require_once '../backend/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'user';
$user_email = $_SESSION['email'] ?? '';

// Fetch user profile picture from database
$profile_pic = null;
$pic_query = "SELECT u.profile_picture, d.profile_picture as driver_profile_pic
              FROM users u
              LEFT JOIN drivers d ON u.user_id = d.user_id
              WHERE u.user_id = ?";
$pic_stmt = $conn->prepare($pic_query);
$pic_stmt->bind_param("s", $user_id);
$pic_stmt->execute();
$pic_result = $pic_stmt->get_result();
if ($pic_row = $pic_result->fetch_assoc()) {
    $profile_pic = $pic_row['profile_picture'] ?? $pic_row['driver_profile_pic'] ?? null;
}
$pic_stmt->close();

// Get initials for avatar fallback
$name_parts = explode(' ', $user_name);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user_name, 0, 2));
}

// Fetch unread notifications count and recent notifications
$notif_query = "SELECT notification_id, message, type, created_at, status 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("s", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();

$notifications = [];
$unread_count = 0;

while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
    if ($row['status'] === 'unread') {
        $unread_count++;
    }
}
$notif_stmt->close();

// Function to get time ago
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    if ($time_difference < 60) {
        return 'Just now';
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time_ago);
    }
}

// Get notification icon based on type
function get_notification_icon($type) {
    $icons = [
        'trip' => 'fa-route',
        'payment' => 'fa-money-bill-wave',
        'wallet' => 'fa-wallet',
        'profile' => 'fa-user',
        'system' => 'fa-info-circle',
        'warning' => 'fa-exclamation-triangle',
        'success' => 'fa-check-circle',
    ];
    return $icons[$type] ?? 'fa-bell';
}
?>

<style>
/* Profile Avatar Styles */
.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid #4CAF50;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
</style>

<header class="header">
    <div class="header-left">
        <button class="hamburger-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="header-right">
        <!-- Notification Dropdown Toggle -->
        <div class="profile-section" id="notificationSection">
            <button class="notification-icon" onclick="toggleNotificationDropdown()">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge" id="notificationBadge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <div class="notification-title">Notifications</div>
                    <?php if ($unread_count > 0): ?>
                        <button class="mark-read-btn" onclick="markAllAsRead()">Mark all as read</button>
                    <?php endif; ?>
                </div>
                <div class="notification-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>" 
                                 data-id="<?php echo $notif['notification_id']; ?>"
                                 onclick="markAsRead('<?php echo $notif['notification_id']; ?>')">
                                <div class="notification-item-header">
                                    <div class="notification-item-title">
                                        <i class="fas <?php echo get_notification_icon($notif['type']); ?>"></i>
                                        <?php echo ucfirst($notif['type']); ?> Notification
                                    </div>
                                    <div class="notification-time"><?php echo time_ago($notif['created_at']); ?></div>
                                </div>
                                <div class="notification-item-body">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Profile Section -->
        <div class="profile-section" id="profileSection">
            <div class="profile-header" onclick="toggleProfileDropdown()">
                <div class="profile-avatar">
                    <?php if ($profile_pic && file_exists('../' . $profile_pic)): ?>
                        <img src="../<?php echo htmlspecialchars($profile_pic); ?>" 
                             alt="Profile Picture" 
                             class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="profile-role"><?php echo ucfirst($user_role); ?></div>
                </div>
                <i class="fas fa-chevron-down profile-toggle"></i>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="#" class="dropdown-item" onclick="event.preventDefault(); showLogoutModal();">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Logout Confirmation Modal -->
<div class="modal" id="logoutModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="modal-title">Confirm Logout</div>
        </div>
        <div class="modal-body">
            Are you sure you want to logout? You will be redirected to the login page.
        </div>
        <div class="modal-actions">
            <button class="btn btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <button class="btn btn-confirm" onclick="confirmLogout()">Logout</button>
        </div>
    </div>
</div>

<!-- Dropdown menu and mobile responsive javascript -->
<script>
// Toggle Profile Dropdown
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    const header = document.querySelector('.profile-header');
    const notifDropdown = document.getElementById('notificationDropdown');
    
    // Close notification dropdown
    notifDropdown.classList.remove('active');
    
    dropdown.classList.toggle('active');
    header.classList.toggle('active');
}

// Toggle Notification Dropdown
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileHeader = document.querySelector('.profile-header');
    
    // Close profile dropdown
    profileDropdown.classList.remove('active');
    profileHeader.classList.remove('active');
    
    dropdown.classList.toggle('active');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    // Close profile dropdown
    const profileSection = document.getElementById('profileSection');
    if (profileSection && !profileSection.contains(event.target)) {
        document.getElementById('profileDropdown').classList.remove('active');
        const profileHeader = document.querySelector('.profile-header');
        if (profileHeader) {
            profileHeader.classList.remove('active');
        }
    }
    
    // Close notification dropdown
    const notifSection = document.getElementById('notificationSection');
    if (notifSection && !notifSection.contains(event.target)) {
        document.getElementById('notificationDropdown').classList.remove('active');
    }
});

// Mark single notification as read
function markAsRead(notificationId) {
    fetch('../backend/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notifItem = document.querySelector(`[data-id="${notificationId}"]`);
            if (notifItem) {
                notifItem.classList.remove('unread');
            }
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Mark all notifications as read
function markAllAsRead() {
    fetch('../backend/mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Hide badge
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            
            // Hide "Mark all as read" button
            const markReadBtn = document.querySelector('.mark-read-btn');
            if (markReadBtn) {
                markReadBtn.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Update notification badge count
function updateNotificationBadge() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const badge = document.getElementById('notificationBadge');
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Show Logout Modal
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.add('active');
    
    // Close dropdowns
    document.getElementById('profileDropdown').classList.remove('active');
    document.querySelector('.profile-header').classList.remove('active');
}

// Close Logout Modal
function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.remove('active');
}

// Confirm Logout
function confirmLogout() {
    window.location.href = 'logout.php';
}

// Close modal when clicking outside
document.getElementById('logoutModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeLogoutModal();
    }
});

// Toggle Sidebar (if you have sidebar functionality)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('open');
    }
    
    if (overlay) {
        overlay.classList.toggle('active');
    }
}
</script>