<?php
// Header: show user's profile picture (from uploads) with fallback.
// Place this in your admin/partials/header.php where you render the header/profile area.

// If your app uses a different session structure, update the checks below.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Try common session/db variables for the user's profile filename.
// Adjust these keys to match how your app stores the profile filename.
$profile_filename = null;
if (!empty($_SESSION['user']['profile_picture'])) {
    $profile_filename = $_SESSION['user']['profile_picture'];
} elseif (!empty($_SESSION['login_user']['profile_picture'])) {
    $profile_filename = $_SESSION['login_user']['profile_picture'];
} elseif (!empty($user['profile_picture'])) {
    $profile_filename = $user['profile_picture'];
}

// Base web path to the uploads folder (change if your app is in a different base path)
$uploads_web_prefix = '/recipe_ai_system/uploads/';
// Default image in admin assets
$default_profile_web = '/recipe_ai_system/admin/assets/img/default-avatar.png';

// Server filesystem docroot for file_exists checks
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');

// Resolve web path for profile image; fallback to default if missing
$profile_web_path = $default_profile_web;
if (!empty($profile_filename)) {
    // rawurlencode to safely encode filenames into URLs
    $candidate_web = $uploads_web_prefix . rawurlencode($profile_filename);
    $candidate_file = $docroot . $candidate_web;
    if (file_exists($candidate_file) && is_file($candidate_file)) {
        $profile_web_path = $candidate_web;
    }
}
?>
<!-- Header profile markup -->
<div class="header-profile" style="display:flex;align-items:center;gap:8px;">
    <img
        src="<?php echo htmlspecialchars($profile_web_path, ENT_QUOTES, 'UTF-8'); ?>"
        alt="Profile"
        class="header-profile-image"
        style="width:40px;height:40px;border-radius:50%;object-fit:cover;"
    />
    <?php if (!empty($_SESSION['user']['name']) || !empty($_SESSION['login_user']['name'])): ?>
        <span class="header-username" style="font-weight:600;">
            <?php
                $name = $_SESSION['user']['name'] ?? $_SESSION['login_user']['name'] ?? '';
                echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            ?>
        </span>
    <?php endif; ?>
</div>
