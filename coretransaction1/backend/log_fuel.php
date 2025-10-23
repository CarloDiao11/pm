/**
 * ============================================
 * FILE: backend/log_fuel.php
 * PURPOSE: Handle fuel logging from dashboard
 * ============================================
 */
<?php 
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $driver_id = $_POST['driver_id'] ?? '';
    $fuel_type = $_POST['fuel_type'] ?? 'diesel';
    $liters = floatval($_POST['liters'] ?? 0);
    $cost = floatval($_POST['cost'] ?? 0);
    $odometer_reading = floatval($_POST['odometer_reading'] ?? 0);
    $station_name = trim($_POST['station_name'] ?? '');
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    
    // Validate inputs
    if (empty($vehicle_id) || empty($driver_id) || $liters <= 0 || $cost <= 0) {
        header('Location: ../admin/modules/index.php?error=invalid_fuel_data');
        exit;
    }
    
    // Generate fuel log ID
    $fuel_id = bin2hex(random_bytes(16));
    
    // Insert fuel log
    $result = db_insert('fuel_logs', [
        'fuel_id' => $fuel_id,
        'vehicle_id' => $vehicle_id,
        'driver_id' => $driver_id,
        'fuel_type' => $fuel_type,
        'liters' => $liters,
        'cost' => $cost,
        'odometer_reading' => $odometer_reading,
        'station_name' => $station_name,
        'receipt_number' => $receipt_number,
        'date' => date('Y-m-d H:i:s')
    ]);
    
    if ($result) {
        // Update vehicle mileage
        db_update('vehicles', ['mileage' => $odometer_reading], ['vehicle_id' => $vehicle_id]);
        
        header('Location: ../admin/modules/index.php?success=fuel_logged');
    } else {
        header('Location: ../admin/modules/index.php?error=fuel_failed');
    }
    exit;
}
?>