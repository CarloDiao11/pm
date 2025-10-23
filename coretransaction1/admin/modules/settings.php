<?php
// settings.php - Fully Fixed Dynamic Settings Page
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Hardcoded user ID (replace with $_SESSION['user_id'] later)
$userId = 'e8b3e465-4117-4f53-8b58-d288644914f3';

// Fetch user data
$user = db_select('users', ['user_id' => $userId], ['limit' => 1]);
if (!$user) {
    die('User not found');
}
$user = $user[0];

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $name = trim($firstName . ' ' . $lastName);
        
        if (!$name || !$email) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Check if email is already taken by another user
            $existing = db_select('users', ['email' => $email]);
            if ($existing && $existing[0]['user_id'] !== $userId) {
                $error = 'Email is already in use.';
            } else {
                // Handle profile picture upload
                $profilePicture = $user['profile_picture'] ?? null;
                if (!empty($_FILES['profile_picture']['name'])) {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile_pictures/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $file = $_FILES['profile_picture'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && $file['error'] === UPLOAD_ERR_OK && $file['size'] < 5 * 1024 * 1024) {
                        $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
                        $path = $uploadDir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $path)) {
                            $profilePicture = 'uploads/profile_pictures/' . $filename;
                        }
                    }
                }
                
                // Prepare update data
                $updateData = [
                    'name' => $name,
                    'email' => $email,
                    'profile_picture' => $profilePicture
                ];
                if ($phone !== '') $updateData['phone'] = $phone;
                if ($address !== '') $updateData['address'] = $address;
                
                // Update user
                if (db_update('users', $updateData, ['user_id' => $userId])) {
                    $message = 'Profile updated successfully!';
                    // Refresh user data
                    $user = db_select('users', ['user_id' => $userId], ['limit' => 1])[0];
                    // Refresh name parts
                    $parts = explode(' ', $user['name'], 2);
                    $firstName = $parts[0] ?? $user['name'];
                    $lastName = $parts[1] ?? '';
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!isset($user['password_hash']) || !password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (db_update('users', ['password_hash' => $newHash], ['user_id' => $userId])) {
                $message = 'Password updated successfully!';
            } else {
                $error = 'Failed to update password.';
            }
        }
    }
}

// Safely split name into first and last
$parts = explode(' ', $user['name'], 2);
$firstName = $parts[0] ?? $user['name'];
$lastName = $parts[1] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="settings-header">
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle">Manage your account and system preferences</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Settings Section -->
        <div class="settings-section">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2 class="card-title">
                        <i class="bi bi-person-circle"></i>
                        Profile Settings
                    </h2>
                    <p class="card-description">Update your personal information and credentials</p>
                </div>

                <form class="settings-form" id="profileForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- Profile Picture -->
                    <div class="form-group text-center mb-4">
                        <div class="profile-picture-preview">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" id="profilePreview">
                            <?php else: ?>
                                <div class="profile-placeholder" id="profilePreview">
                                    <?= strtoupper(substr($firstName, 0, 1) . substr($lastName ?: $firstName, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label class="btn-upload mt-2">
                            <i class="bi bi-camera"></i>
                            Change Photo
                            <input type="file" name="profile_picture" accept="image/*" onchange="previewImage(event)" style="display:none;">
                        </label>
                        <small class="form-hint">JPG, PNG, or GIF (max 5MB)</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName" class="form-label">
                                <i class="bi bi-person"></i>
                                First Name
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="firstName" 
                                name="first_name" 
                                placeholder="Enter your first name"
                                value="<?= htmlspecialchars($firstName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="lastName" class="form-label">
                                <i class="bi bi-person"></i>
                                Last Name
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="lastName" 
                                name="last_name" 
                                placeholder="Enter your last name"
                                value="<?= htmlspecialchars($lastName) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i>
                            Email Address
                        </label>
                        <input 
                            type="email" 
                            class="form-input" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email address"
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" class="form-label">
                                <i class="bi bi-telephone"></i>
                                Phone Number
                            </label>
                            <input 
                                type="tel" 
                                class="form-input" 
                                id="phone" 
                                name="phone" 
                                placeholder="+63 912 345 6789"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="role" class="form-label">
                                <i class="bi bi-person-badge"></i>
                                Role
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                value="<?= ucfirst($user['role'] ?? 'admin') ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address" class="form-label">
                            <i class="bi bi-geo-alt"></i>
                            Address
                        </label>
                        <textarea 
                            class="form-input form-textarea" 
                            id="address" 
                            name="address" 
                            rows="3"
                            placeholder="Enter your address"
                        ><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-divider">
                        <span class="divider-text">Security Settings</span>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-circle"></i>
                            Save Profile Changes
                        </button>
                    </div>
                </form>

                <!-- Password Change Form -->
                <form class="settings-form mt-5" id="passwordForm" method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <h3 class="card-title">Change Password</h3>
                    <div class="form-group">
                        <label for="currentPassword" class="form-label">
                            <i class="bi bi-lock"></i>
                            Current Password
                        </label>
                        <div class="password-input-wrapper">
                            <input 
                                type="password" 
                                class="form-input" 
                                id="currentPassword" 
                                name="current_password" 
                                placeholder="Enter current password"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                                <i class="bi bi-eye" id="currentPassword-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newPassword" class="form-label">
                                <i class="bi bi-lock-fill"></i>
                                New Password
                            </label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    class="form-input" 
                                    id="newPassword" 
                                    name="new_password" 
                                    placeholder="Enter new password"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                    <i class="bi bi-eye" id="newPassword-icon"></i>
                                </button>
                            </div>
                            <small class="form-hint">Minimum 8 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">
                                <i class="bi bi-lock-fill"></i>
                                Confirm New Password
                            </label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    class="form-input" 
                                    id="confirmPassword" 
                                    name="confirm_password" 
                                    placeholder="Confirm new password"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                    <i class="bi bi-eye" id="confirmPassword-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-circle"></i>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- System Preferences Section -->
        <div class="settings-section">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2 class="card-title">
                        <i class="bi bi-gear"></i>
                        System Preferences
                    </h2>
                    <p class="card-description">Customize your notification and display settings</p>
                </div>

                <form class="settings-form" id="preferencesForm">
                    <div class="preferences-group">
                        <h3 class="group-heading">Notification Preferences</h3>
                        
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="emailNotifications" checked>
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">Email Notifications</span>
                                    <span class="checkbox-description">Receive email alerts for important updates</span>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="smsNotifications">
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">SMS Notifications</span>
                                    <span class="checkbox-description">Receive text messages for critical alerts</span>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="pushNotifications" checked>
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">Push Notifications</span>
                                    <span class="checkbox-description">Get browser notifications for real-time updates</span>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="maintenanceAlerts" checked>
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">Maintenance Alerts</span>
                                    <span class="checkbox-description">Notifications for upcoming vehicle maintenance</span>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="tripUpdates" checked>
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">Trip Updates</span>
                                    <span class="checkbox-description">Receive notifications about trip status changes</span>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="weeklyReports">
                                <span class="checkbox-custom"></span>
                                <div class="checkbox-content">
                                    <span class="checkbox-title">Weekly Reports</span>
                                    <span class="checkbox-description">Get weekly summary reports via email</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-circle"></i>
                            Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="overlay" id="overlay"></div>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        document.getElementById('overlay').addEventListener('click', toggleSidebar);

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;border-radius:50%;">';
                    }
                }
                reader.readAsDataURL(file);
            }
        }

        document.getElementById('preferencesForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Preferences saved successfully!');
        });
    </script>
</body>
</html>