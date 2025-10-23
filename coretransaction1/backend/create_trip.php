<?php
/**
 * ============================================
 * FILE: backend/create_trip.php
 * PURPOSE: Handle trip creation from dashboard
 * ============================================
 */
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = $_POST['driver_id'] ?? '';
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $trip_type = $_POST['trip_type'] ?? '';
    $origin = $_POST['origin'] ?? '';
    $destination = $_POST['destination'] ?? '';
    
    // Validate inputs
    if (empty($driver_id) || empty($vehicle_id) || empty($trip_type) || empty($origin) || empty($destination)) {
        header('Location: ../admin/modules/index.php?error=missing_fields');
        exit;
    }
    
    // Generate unique trip ID
    $trip_id = bin2hex(random_bytes(16));
    
    // Insert trip into database
    $result = db_insert('trips', [
        'trip_id' => $trip_id,
        'driver_id' => $driver_id,
        'vehicle_id' => $vehicle_id,
        'trip_type' => $trip_type,
        'origin' => $origin,
        'destination' => $destination,
        'status' => 'pending',
        'start_time' => date('Y-m-d H:i:s')
    ]);
    
    if ($result) {
        // Update vehicle status to in_use
        db_update('vehicles', ['status' => 'in_use'], ['vehicle_id' => $vehicle_id]);
        
        header('Location:  ../admin/modules/index.php?success=trip_created');
    } else {
        header('Location: ../admin/modules/index.php?error=trip_failed');
    }
    exit;
}





