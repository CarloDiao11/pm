<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user data
$user_query = "SELECT id, username, email, name, profile_picture, initials, avatar_color, role 
               FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

// Generate initials if not set
if (empty($user['initials'])) {
    $name_parts = explode(' ', $user['name']);
    $user['initials'] = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
}

// Generate avatar color if not set
if (empty($user['avatar_color'])) {
    $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
    $user['avatar_color'] = $colors[array_rand($colors)];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Statistics
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users = $conn->query($total_users_query)->fetch_assoc()['total'];

$active_users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'online' OR status = 'away'";
$active_users = $conn->query($active_users_query)->fetch_assoc()['total'];

$inactive_users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'offline'";
$inactive_users = $conn->query($inactive_users_query)->fetch_assoc()['total'];

$new_users_query = "SELECT COUNT(*) as total FROM users 
                    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                    AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$new_users = $conn->query($new_users_query)->fetch_assoc()['total'];

// Fetch users with search and pagination
$users_query = "SELECT u.id, u.username, u.email, u.name, u.profile_picture, u.initials, 
                u.avatar_color, u.role, u.status, u.created_at,
                COUNT(DISTINCT r.id) as recipe_count
                FROM users u
                LEFT JOIN recipes r ON u.id = r.created_by
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";

$search_param = "%$search%";
$stmt = $conn->prepare($users_query);
$stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
$stmt->execute();
$users_result = $stmt->get_result();
$stmt->close();

// Count total search results
$count_query = "SELECT COUNT(*) as total FROM users 
                WHERE name LIKE ? OR email LIKE ? OR username LIKE ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("sss", $search_param, $search_param, $search_param);
$stmt->execute();
$total_filtered = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_filtered / $limit);

// Fetch notifications
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$notifications_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flavor Forge - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/header.css">

    <style>
        /* Content Area */
        .content {
            padding: 40px;
            background: var(--bg-secondary);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.users { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.active { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.inactive { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.new { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        .stat-info h3 {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-info .stat-value {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 700;
        }

        /* User Table Section */
        .user-table-section {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px var(--shadow);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            color: var(--text-primary);
            font-size: 22px;
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .btn-primary {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Table Styles */
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table thead {
            background: var(--bg-secondary);
        }

        .user-table th {
            padding: 15px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .user-table tbody tr:hover {
            background: var(--bg-secondary);
        }

        .user-table td {
            padding: 15px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-initials {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-details .user-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .user-details .user-email {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.away {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.offline {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .role-badge.admin {
            background: #cce5ff;
            color: #004085;
        }

        .role-badge.user {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit:hover {
            background: #1976d2;
            color: white;
        }

        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }

        .btn-delete:hover {
            background: #c62828;
            color: white;
        }

        .btn-view {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .btn-view:hover {
            background: #7b1fa2;
            color: white;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
        }

        .pagination button, .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .pagination button:hover:not(:disabled), .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination button.active, .pagination a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn-secondary {
            padding: 10px 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
        }

        .btn-danger {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* View Modal Specific */
        .user-detail-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .user-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .user-detail-item:last-child {
            border-bottom: none;
        }

        .user-detail-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .user-detail-value {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive Design */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .profile-info {
                display: none;
            }

            .content {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .user-table-section {
                overflow-x: auto;
            }

            .user-table {
                min-width: 800px;
            }
        }

        /* Overlay for mobile */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>
    
    <div class="container">
        <?php include '../partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>User Management</h1>
                </div>
                
                <div class="header-right">
                    <!-- Dark Mode Toggle -->
                    <div class="dark-mode-toggle" id="darkModeToggle">
                        <i class="fas fa-sun" id="themeIcon"></i>
                    </div>
                    
                    <!-- Notification -->
                    <div class="notification-container">
                        <div class="notification" id="notificationButton">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="#" class="mark-all-read">Mark all as read</a>
                            </div>
                            
                            <div class="notification-list">
                                <?php while ($notif = $notifications->fetch_assoc()): ?>
                                <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-icon <?php echo $notif['type']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $notif['type'] == 'recipe' ? 'utensils' : 
                                                ($notif['type'] == 'comment' ? 'comment' : 
                                                ($notif['type'] == 'like' ? 'heart' : 
                                                ($notif['type'] == 'follower' ? 'user-plus' : 'envelope'))); 
                                        ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notification-text"><?php echo htmlspecialchars($notif['message']); ?></div>
                                        <div class="notification-time"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="notification-footer">
                                <a href="#" class="view-all">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Dropdown -->
                    <div class="profile-container">
                        <div class="profile-button" id="profileButton">
                            <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="profile-pic" style="border-radius: 50%; width: 40px; height: 40px; object-fit: cover;">
                            <?php else: ?>
                                <div class="profile-pic" style="background-color: <?php echo $user['avatar_color']; ?>">
                                    <?php echo htmlspecialchars($user['initials']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="profile-role"><?php echo ucfirst($user['role']); ?></div>
                            </div>
                            <svg class="dropdown-icon" id="dropdownIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        
                        <div class="dropdown-menu" id="dropdownMenu">
                            <a href="profile.php" class="dropdown-item">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Profile
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Settings
                            </a>
                            <a href="../logout.php" class="dropdown-item">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Alert Messages -->
                <div id="alertMessage" class="alert"></div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Active Users</h3>
                            <div class="stat-value"><?php echo number_format($active_users); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon inactive">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Inactive Users</h3>
                            <div class="stat-value"><?php echo number_format($inactive_users); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon new">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3>New This Month</h3>
                            <div class="stat-value"><?php echo number_format($new_users); ?></div>
                        </div>
                    </div>
                </div>

                <!-- User Table -->
                <div class="user-table-section">
                    <div class="table-header">
                        <h2>User Management</h2>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <form method="GET" action="user.php" id="searchForm">
                                    <input type="text" name="search" id="searchInput" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                </form>
                            </div>
                            <button class="btn-primary" id="addUserBtn">
                                <i class="fas fa-plus"></i>
                                Add New User
                            </button>
                        </div>
                    </div>

                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Recipes</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <?php while ($usr = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <?php if (!empty($usr['profile_picture']) && file_exists('../' . $usr['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($usr['profile_picture']); ?>" alt="User" class="user-avatar">
                                        <?php else: ?>
                                            <div class="user-initials" style="background-color: <?php echo $usr['avatar_color'] ?? '#667eea'; ?>">
                                                <?php echo htmlspecialchars($usr['initials'] ?? strtoupper(substr($usr['name'], 0, 2))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($usr['name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($usr['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="role-badge <?php echo $usr['role']; ?>"><?php echo ucfirst($usr['role']); ?></span></td>
                                <td><span class="status-badge <?php echo $usr['status']; ?>"><?php echo ucfirst($usr['status']); ?></span></td>
                                <td><?php echo $usr['recipe_count']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($usr['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon btn-view" data-id="<?php echo $usr['id']; ?>" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-icon btn-edit" data-id="<?php echo $usr['id']; ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon btn-delete" data-id="<?php echo $usr['id']; ?>" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <button disabled><i class="fas fa-chevron-left"></i></button>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <button disabled><i class="fas fa-chevron-right"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close" id="closeAddModal">&times;</button>
            </div>
            <form id="addUserForm">
                <div class="form-group">
                    <label for="addName">Full Name *</label>
                    <input type="text" id="addName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="addUsername">Username *</label>
                    <input type="text" id="addUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="addEmail">Email *</label>
                    <input type="email" id="addEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="addPassword">Password *</label>
                    <input type="password" id="addPassword" name="password" required>
                </div>
                <div class="form-group">
                    <label for="addRole">Role *</label>
                    <select id="addRole" name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addStatus">Status *</label>
                    <select id="addStatus" name="status" required>
                        <option value="online">Online</option>
                        <option value="away">Away</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="cancelAdd">Cancel</button>
                    <button type="submit" class="btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="id">
                <div class="form-group">
                    <label for="editName">Full Name *</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editUsername">Username *</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="editEmail">Email *</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editPassword">Password (leave blank to keep current)</label>
                    <input type="password" id="editPassword" name="password">
                </div>
                <div class="form-group">
                    <label for="editRole">Role *</label>
                    <select id="editRole" name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editStatus">Status *</label>
                    <select id="editStatus" name="status" required>
                        <option value="online">Online</option>
                        <option value="away">Away</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="cancelEdit">Cancel</button>
                    <button type="submit" class="btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal" id="viewUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <button class="modal-close" id="closeViewModal">&times;</button>
            </div>
            <div class="user-detail-card">
                <div class="user-detail-item">
                    <span class="user-detail-label">Full Name:</span>
                    <span class="user-detail-value" id="viewName"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Username:</span>
                    <span class="user-detail-value" id="viewUsername"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Email:</span>
                    <span class="user-detail-value" id="viewEmail"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Role:</span>
                    <span class="user-detail-value" id="viewRole"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Status:</span>
                    <span class="user-detail-value" id="viewStatus"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Total Recipes:</span>
                    <span class="user-detail-value" id="viewRecipes"></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Joined Date:</span>
                    <span class="user-detail-value" id="viewJoined"></span>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" id="closeViewBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <p style="color: var(--text-primary); margin-bottom: 20px;">
                Are you sure you want to delete user <strong id="deleteUserName"></strong>? This action cannot be undone.
            </p>
            <form id="deleteUserForm">
                <input type="hidden" id="deleteUserId" name="id">
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="cancelDelete">Cancel</button>
                    <button type="submit" class="btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Dark Mode Toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const html = document.documentElement;
            
            const currentTheme = localStorage.getItem('theme') || 'light';
            html.setAttribute('data-theme', currentTheme);
            if (currentTheme === 'dark') {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
            
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isDark = html.getAttribute('data-theme') === 'dark';
                    const newTheme = isDark ? 'light' : 'dark';
                    
                    html.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    if (newTheme === 'dark') {
                        themeIcon.classList.remove('fa-sun');
                        themeIcon.classList.add('fa-moon');
                    } else {
                        themeIcon.classList.remove('fa-moon');
                        themeIcon.classList.add('fa-sun');
                    }
                });
            }

            // Mobile Menu Toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            if (menuToggle && sidebar && overlay) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }

            // Notification & Profile Dropdowns
            const notificationButton = document.getElementById('notificationButton');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const profileButton = document.getElementById('profileButton');
            const dropdownIcon = document.getElementById('dropdownIcon');
            
            if (notificationButton && notificationDropdown) {
                notificationButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    
                    if (dropdownMenu) {
                        dropdownMenu.classList.remove('show');
                        if (dropdownIcon) dropdownIcon.classList.remove('open');
                    }
                });
            }

            if (profileButton && dropdownMenu && dropdownIcon) {
                profileButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                    dropdownIcon.classList.toggle('open');
                    
                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.profile-container')) {
                    if (dropdownMenu) dropdownMenu.classList.remove('show');
                    if (dropdownIcon) dropdownIcon.classList.remove('open');
                }
                
                if (!e.target.closest('.notification-container')) {
                    if (notificationDropdown) notificationDropdown.classList.remove('show');
                }
            });

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        document.getElementById('searchForm').submit();
                    }, 500);
                });
            }

            // Modal Elements
            const addUserModal = document.getElementById('addUserModal');
            const editUserModal = document.getElementById('editUserModal');
            const viewUserModal = document.getElementById('viewUserModal');
            const deleteUserModal = document.getElementById('deleteUserModal');

            // Add User Modal
            const addUserBtn = document.getElementById('addUserBtn');
            const closeAddModal = document.getElementById('closeAddModal');
            const cancelAdd = document.getElementById('cancelAdd');

            if (addUserBtn) {
                addUserBtn.addEventListener('click', function() {
                    addUserModal.classList.add('active');
                });
            }

            if (closeAddModal) {
                closeAddModal.addEventListener('click', function() {
                    addUserModal.classList.remove('active');
                });
            }

            if (cancelAdd) {
                cancelAdd.addEventListener('click', function() {
                    addUserModal.classList.remove('active');
                });
            }

            // Add User Form Submit
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(addUserForm);
                    
                    fetch('../api/add_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('User added successfully!', 'success');
                            addUserModal.classList.remove('active');
                            addUserForm.reset();
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert(data.message || 'Error adding user', 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error adding user', 'error');
                        console.error('Error:', error);
                    });
                });
            }

            // View User
            const viewButtons = document.querySelectorAll('.btn-view');
            const closeViewModal = document.getElementById('closeViewModal');
            const closeViewBtn = document.getElementById('closeViewBtn');

            viewButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    
                    fetch('../api/get_user.php?id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('viewName').textContent = data.user.name;
                            document.getElementById('viewUsername').textContent = data.user.username;
                            document.getElementById('viewEmail').textContent = data.user.email;
                            document.getElementById('viewRole').textContent = data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1);
                            document.getElementById('viewStatus').textContent = data.user.status.charAt(0).toUpperCase() + data.user.status.slice(1);
                            document.getElementById('viewRecipes').textContent = data.user.recipe_count || 0;
                            document.getElementById('viewJoined').textContent = new Date(data.user.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'});
                            
                            viewUserModal.classList.add('active');
                        } else {
                            showAlert('Error loading user details', 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error loading user details', 'error');
                        console.error('Error:', error);
                    });
                });
            });

            if (closeViewModal) {
                closeViewModal.addEventListener('click', function() {
                    viewUserModal.classList.remove('active');
                });
            }

            if (closeViewBtn) {
                closeViewBtn.addEventListener('click', function() {
                    viewUserModal.classList.remove('active');
                });
            }

            // Edit User
            const editButtons = document.querySelectorAll('.btn-edit');
            const closeEditModal = document.getElementById('closeEditModal');
            const cancelEdit = document.getElementById('cancelEdit');

            editButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    
                    fetch('../api/get_user.php?id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('editUserId').value = data.user.id;
                            document.getElementById('editName').value = data.user.name;
                            document.getElementById('editUsername').value = data.user.username;
                            document.getElementById('editEmail').value = data.user.email;
                            document.getElementById('editRole').value = data.user.role;
                            document.getElementById('editStatus').value = data.user.status;
                            
                            editUserModal.classList.add('active');
                        } else {
                            showAlert('Error loading user details', 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error loading user details', 'error');
                        console.error('Error:', error);
                    });
                });
            });

            if (closeEditModal) {
                closeEditModal.addEventListener('click', function() {
                    editUserModal.classList.remove('active');
                });
            }

            if (cancelEdit) {
                cancelEdit.addEventListener('click', function() {
                    editUserModal.classList.remove('active');
                });
            }

            // Edit User Form Submit
            const editUserForm = document.getElementById('editUserForm');
            if (editUserForm) {
                editUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(editUserForm);
                    
                    fetch('../api/edit_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('User updated successfully!', 'success');
                            editUserModal.classList.remove('active');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert(data.message || 'Error updating user', 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error updating user', 'error');
                        console.error('Error:', error);
                    });
                });
            }

            // Delete User
            const deleteButtons = document.querySelectorAll('.btn-delete');
            const closeDeleteModal = document.getElementById('closeDeleteModal');
            const cancelDelete = document.getElementById('cancelDelete');

            deleteButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const row = this.closest('tr');
                    const userName = row.querySelector('.user-name').textContent;
                    
                    document.getElementById('deleteUserId').value = userId;
                    document.getElementById('deleteUserName').textContent = userName;
                    
                    deleteUserModal.classList.add('active');
                });
            });

            if (closeDeleteModal) {
                closeDeleteModal.addEventListener('click', function() {
                    deleteUserModal.classList.remove('active');
                });
            }

            if (cancelDelete) {
                cancelDelete.addEventListener('click', function() {
                    deleteUserModal.classList.remove('active');
                });
            }

            // Delete User Form Submit
            const deleteUserForm = document.getElementById('deleteUserForm');
            if (deleteUserForm) {
                deleteUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(deleteUserForm);
                    
                    fetch('../api/delete_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('User deleted successfully!', 'success');
                            deleteUserModal.classList.remove('active');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert(data.message || 'Error deleting user', 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error deleting user', 'error');
                        console.error('Error:', error);
                    });
                });
            }

            // Close modals on outside click
            window.addEventListener('click', function(e) {
                if (e.target === addUserModal) {
                    addUserModal.classList.remove('active');
                }
                if (e.target === editUserModal) {
                    editUserModal.classList.remove('active');
                }
                if (e.target === viewUserModal) {
                    viewUserModal.classList.remove('active');
                }
                if (e.target === deleteUserModal) {
                    deleteUserModal.classList.remove('active');
                }
            });

            // Alert function
            function showAlert(message, type) {
                const alert = document.getElementById('alertMessage');
                alert.textContent = message;
                alert.className = 'alert alert-' + type + ' show';
                
                setTimeout(function() {
                    alert.classList.remove('show');
                }, 5000);
            }
        });
    </script>
</body>
</html>