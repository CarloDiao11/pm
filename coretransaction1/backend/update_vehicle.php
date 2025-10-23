<?php
// backend/update_vehicle.php
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$vehicle_id = $_POST['vehicle_id'] ?? '';
$plate = trim($_POST['plate_number'] ?? '');
$type = trim($_POST['type'] ?? '');
$status = $_POST['status'] ?? 'available';
$mileage = !empty($_POST['mileage']) ? (float)$_POST['mileage'] : null;
$insurance = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;

if (empty($vehicle_id) || empty($plate) || empty($type)) {
    header('Location: ../public/fleet.php?error=missing_fields');
    exit;
}

// Optional: prevent duplicate plate (excluding self)
$existing = db_select('vehicles', [
    'plate_number' => $plate,
    'vehicle_id !=' => $vehicle_id
]);
if (!empty($existing)) {
    header('Location: ../public/fleet.php?error=plate_exists');
    exit;
}

try {
    db_update('vehicles', [
        'plate_number' => $plate,
        'type' => $type,
        'status' => $status,
        'mileage' => $mileage,
        'insurance_expiry' => $insurance
    ], [
        'vehicle_id' => $vehicle_id
    ]);
    header('Location: ../public/fleet.php?success=vehicle_updated');
} catch (Exception $e) {
    error_log("Update Vehicle Error: " . $e->getMessage());
    header('Location: ../public/fleet.php?error=vehicle_failed');
}
exit;
?>