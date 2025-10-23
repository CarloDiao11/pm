<?php
define('APP_LOADED', true);
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once 'db1.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel_id = $_POST['fuel_id'] ?? '';
    $driver_id = $_SESSION['driver_id'];
    
    if (empty($fuel_id)) {
        echo json_encode(['success' => false, 'error' => 'Fuel ID is required']);
        exit;
    }
    
    try {
        // Verify that the fuel log belongs to this driver
        $verify_stmt = $conn->prepare("SELECT fuel_id FROM fuel_logs WHERE fuel_id = ? AND driver_id = ?");
        $verify_stmt->bind_param("ss", $fuel_id, $driver_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Fuel log not found or unauthorized']);
            exit;
        }
        $verify_stmt->close();
        
        // Delete the fuel log
        $delete_stmt = $conn->prepare("DELETE FROM fuel_logs WHERE fuel_id = ? AND driver_id = ?");
        $delete_stmt->bind_param("ss", $fuel_id, $driver_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Fuel log deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete fuel log']);
        }
        
        $delete_stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>