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
    $driver_id = $_SESSION['driver_id'];
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $fuel_type = $_POST['fuel_type'] ?? '';
    $liters = $_POST['liters'] ?? 0;
    $cost = $_POST['cost'] ?? 0;
    $station_name = trim($_POST['station_name'] ?? '');
    $odometer_reading = $_POST['odometer_reading'] ?? 0;
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($vehicle_id)) $errors[] = "Vehicle is required";
    if (empty($date)) $errors[] = "Date is required";
    if (empty($fuel_type)) $errors[] = "Fuel type is required";
    if ($liters <= 0) $errors[] = "Liters must be greater than 0";
    if ($cost <= 0) $errors[] = "Cost must be greater than 0";
    if (empty($station_name)) $errors[] = "Station name is required";
    if ($odometer_reading <= 0) $errors[] = "Odometer reading must be greater than 0";
    
    if (empty($errors)) {
        try {
            // Generate UUID for fuel log
            $fuel_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // Insert fuel log
            $stmt = $conn->prepare("INSERT INTO fuel_logs (fuel_id, vehicle_id, driver_id, liters, cost, date, station_name, odometer_reading, fuel_type, receipt_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddssdss", $fuel_id, $vehicle_id, $driver_id, $liters, $cost, $date, $station_name, $odometer_reading, $fuel_type, $receipt_number);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Fuel log added successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to add fuel log']);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>