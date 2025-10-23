<?php
// backend/add_vehicle.php
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$plate = trim($_POST['plate_number'] ?? '');
$type = trim($_POST['type'] ?? '');
$mileage = !empty($_POST['mileage']) ? (float)$_POST['mileage'] : 0;
$insurance = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;

// Validate
if (empty($plate) || empty($type)) {
    header('Location: ../public/fleet.php?error=missing_fields');
    exit;
}

// Check if plate already exists (optional but recommended)
$existing = db_select('vehicles', ['plate_number' => $plate]);
if (!empty($existing)) {
    header('Location: ../public/fleet.php?error=plate_exists');
    exit;
}

try {
    db_insert('vehicles', [
        'vehicle_id' => bin2hex(random_bytes(16)),
        'plate_number' => $plate,
        'type' => $type,
        'status' => 'available',
        'mileage' => $mileage,
        'insurance_expiry' => $insurance
    ]);
    header('Location: ../public/fleet.php?success=vehicle_added');
} catch (Exception $e) {
    error_log("Add Vehicle Error: " . $e->getMessage());
    header('Location: ../public/fleet.php?error=vehicle_failed');
}
exit;
?>