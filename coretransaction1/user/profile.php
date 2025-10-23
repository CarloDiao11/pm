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

// Fetch user and driver data
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, d.drivers_id, d.license_number, d.license_expiry, 
          d.contact_number, d.address, d.rating, d.profile_picture as driver_profile_pic
          FROM users u
          LEFT JOIN drivers d ON u.user_id = d.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Determine which profile picture to use
$profile_pic = $user_data['profile_picture'] ?? $user_data['driver_profile_pic'] ?? null;

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = '../uploads/profile_pictures/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['profile_picture'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($file_ext, $allowed_exts) && $file['size'] <= 5242880) { // 5MB max
        $new_filename = $user_id . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Delete old profile picture if exists
            if ($profile_pic && file_exists('../' . $profile_pic)) {
                unlink('../' . $profile_pic);
            }
            
            // Update database
            $relative_path = 'uploads/profile_pictures/' . $new_filename;
            $update_query = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $relative_path, $user_id);
            $update_stmt->execute();
            
            // Also update drivers table if driver exists
            if ($user_data['drivers_id']) {
                $update_driver = "UPDATE drivers SET profile_picture = ? WHERE drivers_id = ?";
                $update_driver_stmt = $conn->prepare($update_driver);
                $update_driver_stmt->bind_param("ss", $relative_path, $user_data['drivers_id']);
                $update_driver_stmt->execute();
            }
            
            $_SESSION['success'] = "Profile picture updated successfully!";
            header('Location: profile.php');
            exit;
        } else {
            $_SESSION['error'] = "Failed to upload file.";
        }
    } else {
        $_SESSION['error'] = "Invalid file. Only JPG, JPEG, PNG, GIF allowed (max 5MB).";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    
    // Update users table
    $update_user = "UPDATE users SET name = ?, email = ? WHERE user_id = ?";
    $stmt_user = $conn->prepare($update_user);
    $stmt_user->bind_param("sss", $name, $email, $user_id);
    $stmt_user->execute();
    
    // Update drivers table if driver exists
    if ($user_data['drivers_id']) {
        $update_driver = "UPDATE drivers SET contact_number = ?, address = ? WHERE drivers_id = ?";
        $stmt_driver = $conn->prepare($update_driver);
        $stmt_driver->bind_param("sss", $contact_number, $address, $user_data['drivers_id']);
        $stmt_driver->execute();
    }
    
    $_SESSION['success'] = "Profile updated successfully!";
    header('Location: profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    <style>
        .content-area {
            padding: 30px;
        }
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-header-section {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #2a2a2a;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-picture-section {
            position: relative;
        }

        .profile-picture-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #4CAF50;
        }

        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #fff;
            border: 4px solid #4CAF50;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 45px;
            height: 45px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 3px solid #0a0a0a;
        }

        .upload-overlay:hover {
            background: #45a049;
            transform: scale(1.1);
        }

        .upload-overlay i {
            color: #fff;
            font-size: 18px;
        }

        .profile-info-header {
            flex: 1;
        }

        .profile-name-large {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .profile-role-badge {
            display: inline-block;
            background: #4CAF50;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #4CAF50;
        }

        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid #2a2a2a;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #4CAF50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #888;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            background: #2a2a2a;
            border: 1px solid #333;
            padding: 12px 15px;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #4CAF50;
            background: #333;
        }

        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #4CAF50;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }

        .alert-error {
            background: rgba(211, 47, 47, 0.1);
            border: 1px solid #d32f2f;
            color: #d32f2f;
        }

        #fileInput {
            display: none;
        }

        @media (max-width: 768px) {
            .profile-header-section {
                flex-direction: column;
                text-align: center;
            }
            .sidebar {
                transform: translateX(-260px);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .profile-content {
                grid-template-columns: 1fr;
            }

            .profile-stats {
                justify-content: center;
            }
            .header {
                padding: 15px 20px;
            }

            .content-area {
                padding: 20px;
            }

            .profile-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include 'include/header.php'; ?>
        
        <!-- Content Area -->
        <div class="content-area">
            <div class="profile-container">
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header-section">
                    <div class="profile-picture-section">
                        <?php if ($profile_pic && file_exists('../' . $profile_pic)): ?>
                            <img src="../<?php echo htmlspecialchars($profile_pic); ?>" 
                                 alt="Profile Picture" 
                                 class="profile-picture-large">
                        <?php else: ?>
                            <div class="profile-picture-placeholder">
                                <?php echo strtoupper(substr($user_data['name'], 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <label for="fileInput" class="upload-overlay">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" 
                                   id="fileInput" 
                                   name="profile_picture" 
                                   accept="image/*"
                                   onchange="document.getElementById('uploadForm').submit();">
                        </form>
                    </div>

                    <div class="profile-info-header">
                        <h1 class="profile-name-large"><?php echo htmlspecialchars($user_data['name']); ?></h1>
                        <span class="profile-role-badge">
                            <i class="fas fa-truck"></i> 
                            <?php echo ucfirst($user_data['role']); ?>
                        </span>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <span class="stat-value">
                                    <?php echo $user_data['rating'] ? number_format($user_data['rating'], 1) : 'N/A'; ?>
                                </span>
                                <span class="stat-label">Rating</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value">
                                    <?php echo ucfirst($user_data['status']); ?>
                                </span>
                                <span class="stat-label">Status</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value">
                                    <?php echo date('Y', strtotime($user_data['created_at'])); ?>
                                </span>
                                <span class="stat-label">Joined</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Content -->
                <div class="profile-content">
                    <!-- Personal Information -->
                    <div class="profile-card">
                        <h2 class="card-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h2>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" 
                                       name="name" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['name']); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Contact Number</label>
                                <input type="text" 
                                       name="contact_number" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" 
                                       name="address" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                            </div>

                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Driver Information -->
                    <div class="profile-card">
                        <h2 class="card-title">
                            <i class="fas fa-id-card"></i>
                            Driver Information
                        </h2>

                        <div class="form-group">
                            <label class="form-label">License Number</label>
                            <input type="text" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['license_number'] ?? 'N/A'); ?>" 
                                   disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label">License Expiry</label>
                            <input type="text" 
                                   class="form-input" 
                                   value="<?php echo $user_data['license_expiry'] ? date('F d, Y', strtotime($user_data['license_expiry'])) : 'N/A'; ?>" 
                                   disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['username'] ?? 'N/A'); ?>" 
                                   disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Member Since</label>
                            <input type="text" 
                                   class="form-input" 
                                   value="<?php echo date('F d, Y', strtotime($user_data['created_at'])); ?>" 
                                   disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'include/footer.php'; ?>
    </main>

    <?php include 'include/logout_modal.php'; ?>
    
    <script src="assets/js/sidebar.js"></script>
</body>
</html>