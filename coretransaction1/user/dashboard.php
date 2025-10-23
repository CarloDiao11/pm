<?php
// IMPORTANT: Start session FIRST, before any output
define('APP_LOADED', true);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
// Include database connection
require_once '../backend/db1.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                padding: 15px 20px;
            }

            .page-title {
                font-size: 20px;
            }

            .profile-info {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-area {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'?>
    <!-- Main Content -->
    <main class="main-content">
    <!--header dashboard-->
        <?php include 'include/header.php';?>

        <div class="content-area">
            <p class="welcome-text">Welcome back, John Doe</p>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-truck"></i></div>
                    <div class="stat-details">
                        <div class="stat-label">ACTIVE TRIPS</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details">
                        <div class="stat-label">WALLET BALANCE</div>
                        <div class="stat-value">₱0.00</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-bell"></i></div>
                    <div class="stat-details">
                        <div class="stat-label">UNREAD NOTIFICATIONS</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
            </div>
        </div>
        <!--footer include-->
        <?php include 'include/footer.php';?>
    </main>
        <!--logout modal-->
        <?php include 'include/logout_modal.php';?>
</body>
</html>