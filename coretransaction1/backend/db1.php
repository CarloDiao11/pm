<?php
if (!defined('APP_LOADED') && php_sapi_name() !== 'cli') {
    die('Direct access not allowed.');
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "corepm";
$port = 3306;

// Create MySQLi connection
$conn = new mysqli($host, $user, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Optional: set charset
$conn->set_charset("utf8mb4");
?>
