<?php
define('APP_LOADED', true);
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db1.php';

$action = $_POST['action'] ?? '';
$trip_id = $_POST['trip_id'] ?? '';
$driver_id = $_SESSION['driver_id'];

if (empty($action) || empty($trip_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Verify trip belongs to driver
$verify_query = "SELECT trip_id FROM trips WHERE trip_id = ? AND driver_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ss", $trip_id, $driver_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Trip not found or unauthorized']);
    exit;
}
$verify_stmt->close();

// Handle actions
if ($action === 'start') {
    // Start trip - update status to ongoing and set start_time
    $update_query = "UPDATE trips SET status = 'ongoing', start_time = NOW() WHERE trip_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("s", $trip_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Trip started successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to start trip']);
    }
    $update_stmt->close();
    
} elseif ($action === 'complete') {
    // Get trip distance and fare from POST
    $distance = floatval($_POST['distance'] ?? 0);
    $fare = floatval($_POST['fare'] ?? 0);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update trip status to completed and set end_time
        $update_trip_query = "UPDATE trips SET status = 'completed', end_time = NOW() WHERE trip_id = ?";
        $update_trip_stmt = $conn->prepare($update_trip_query);
        $update_trip_stmt->bind_param("s", $trip_id);
        $update_trip_stmt->execute();
        $update_trip_stmt->close();
        
        // 2. Get or create driver wallet
        $wallet_query = "SELECT wallet_id, current_balance FROM driver_wallets WHERE driver_id = ?";
        $wallet_stmt = $conn->prepare($wallet_query);
        $wallet_stmt->bind_param("s", $driver_id);
        $wallet_stmt->execute();
        $wallet_result = $wallet_stmt->get_result();
        
        if ($wallet_result->num_rows > 0) {
            // Wallet exists, get wallet_id
            $wallet_row = $wallet_result->fetch_assoc();
            $wallet_id = $wallet_row['wallet_id'];
            $current_balance = floatval($wallet_row['current_balance']);
        } else {
            // Create new wallet
            $wallet_id = generateUUID();
            $current_balance = 0;
            $create_wallet_query = "INSERT INTO driver_wallets (wallet_id, driver_id, current_balance) VALUES (?, ?, 0)";
            $create_wallet_stmt = $conn->prepare($create_wallet_query);
            $create_wallet_stmt->bind_param("ss", $wallet_id, $driver_id);
            $create_wallet_stmt->execute();
            $create_wallet_stmt->close();
        }
        $wallet_stmt->close();
        
        // 3. Update wallet balance (add fare)
        $new_balance = $current_balance + $fare;
        $update_wallet_query = "UPDATE driver_wallets SET current_balance = ? WHERE wallet_id = ?";
        $update_wallet_stmt = $conn->prepare($update_wallet_query);
        $update_wallet_stmt->bind_param("ds", $new_balance, $wallet_id);
        $update_wallet_stmt->execute();
        $update_wallet_stmt->close();
        
        // 4. Create transaction record
        $transaction_id = generateUUID();
        $transaction_query = "INSERT INTO transactions (transaction_id, wallet_id, trip_id, amount, type, status, created_at) 
                             VALUES (?, ?, ?, ?, 'credit', 'completed', NOW())";
        $transaction_stmt = $conn->prepare($transaction_query);
        $transaction_stmt->bind_param("sssd", $transaction_id, $wallet_id, $trip_id, $fare);
        $transaction_stmt->execute();
        $transaction_stmt->close();
        
        // 5. Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Trip completed and payment added to wallet',
            'distance' => $distance,
            'fare' => $fare,
            'new_balance' => $new_balance
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();

// UUID Generator Function
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>