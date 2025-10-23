<?php
define('APP_LOADED', true);
session_start();

// Store user info for logging (optional)
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? null;

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Optional: Log the logout action in database
if ($user_id) {
    // You can add logging here if needed
    // Example: INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, 'logout', NOW())
}

// Redirect to login page
header('Location: ../index.php');
exit;
?>