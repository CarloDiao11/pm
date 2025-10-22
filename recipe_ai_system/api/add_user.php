<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$status = $_POST['status'] ?? 'offline';

// Validation
if (empty($name) || empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Check if username already exists
$check_username = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($check_username);
$stmt->bind_param("s", $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    exit();
}
$stmt->close();

// Check if email already exists
$check_email = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($check_email);
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit();
}
$stmt->close();

// Generate initials
$name_parts = explode(' ', $name);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Generate avatar color
$colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
$avatar_color = $colors[array_rand($colors)];

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$insert_query = "INSERT INTO users (name, username, email, password, role, status, initials, avatar_color) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("ssssssss", $name, $username, $email, $hashed_password, $role, $status, $initials, $avatar_color);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'User added successfully']);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Error adding user: ' . $conn->error]);
}

$conn->close();
?>