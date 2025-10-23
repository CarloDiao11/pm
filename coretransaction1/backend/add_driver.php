/**
 * ============================================
 * FILE: backend/add_driver.php
 * PURPOSE: Handle driver registration from dashboard
 * ============================================
 */
<?php
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expiry = $_POST['license_expiry'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($license_number) || empty($license_expiry) || empty($contact_number)) {
        header('Location: ../admin/modules/index.php?error=missing_fields');
        exit;
    }
    
    // Check if email already exists
    $existing = db_select('users', ['email' => $email]);
    if (!empty($existing)) {
        header('Location: ../admin/modules/index.php?error=email_exists');
        exit;
    }
    
    // Generate unique IDs
    $user_id = bin2hex(random_bytes(16));
    $driver_id = bin2hex(random_bytes(16));
    $wallet_id = bin2hex(random_bytes(16));
    
    // Create default password (driver should change on first login)
    $default_password = 'Driver@' . date('Y');
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Insert user account
    $userResult = db_insert('users', [
        'user_id' => $user_id,
        'name' => $name,
        'email' => $email,
        'password_hash' => $password_hash,
        'role' => 'driver',
        'status' => 'active'
    ]);
    
    if ($userResult) {
        // Insert driver details
        db_insert('drivers', [
            'drivers_id' => $driver_id,
            'user_id' => $user_id,
            'license_number' => $license_number,
            'license_expiry' => $license_expiry,
            'contact_number' => $contact_number,
            'address' => $address,
            'rating' => 5.00
        ]);
        
        // Create driver wallet
        db_insert('driver_wallets', [
            'wallet_id' => $wallet_id,
            'driver_id' => $driver_id,
            'current_balance' => 0.00
        ]);
        
        header('Location: ../admin/modules/index.php?success=driver_added');
    } else {
        header('Location: ../admin/modules/index.php?error=driver_failed');
    }
    exit;
}
?>