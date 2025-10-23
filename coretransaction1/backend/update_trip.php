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
    $action = $_POST['action'] ?? '';
    $trip_id = $_POST['trip_id'] ?? '';
    $driver_id = $_SESSION['driver_id'];
    
    if (empty($action) || empty($trip_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    try {
        // Verify that the trip belongs to this driver
        $verify_stmt = $conn->prepare("SELECT trip_id, status FROM trips WHERE trip_id = ? AND driver_id = ?");
        $verify_stmt->bind_param("ss", $trip_id, $driver_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Trip not found or unauthorized']);
            exit;
        }
        
        $trip = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        if ($action === 'start') {
            // Can only start pending trips
            if ($trip['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Trip cannot be started. Current status: ' . $trip['status']]);
                exit;
            }
            
            // Update trip to ongoing and set start_time
            $update_stmt = $conn->prepare("UPDATE trips SET status = 'ongoing', start_time = NOW() WHERE trip_id = ? AND driver_id = ?");
            $update_stmt->bind_param("ss", $trip_id, $driver_id);
            
            if ($update_stmt->execute()) {
                // Create notification for trip start
                $notif_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $user_id = $_SESSION['user_id'];
                $message = "Trip #{$trip_id} has been started.";
                $type = "trip";
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (notification_id, user_id, message, type, status) VALUES (?, ?, ?, ?, 'unread')");
                $notif_stmt->bind_param("ssss", $notif_id, $user_id, $message, $type);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Trip started successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update trip status']);
            }
            
            $update_stmt->close();
            
        } elseif ($action === 'complete') {
            // Can only complete ongoing trips
            if ($trip['status'] !== 'ongoing') {
                echo json_encode(['success' => false, 'error' => 'Trip cannot be completed. Current status: ' . $trip['status']]);
                exit;
            }
            
            // Update trip to completed and set end_time
            $update_stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW() WHERE trip_id = ? AND driver_id = ?");
            $update_stmt->bind_param("ss", $trip_id, $driver_id);
            
            if ($update_stmt->execute()) {
                // Create notification for trip completion
                $notif_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $user_id = $_SESSION['user_id'];
                $message = "Trip #{$trip_id} has been completed successfully.";
                $type = "success";
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (notification_id, user_id, message, type, status) VALUES (?, ?, ?, ?, 'unread')");
                $notif_stmt->bind_param("ssss", $notif_id, $user_id, $message, $type);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Trip completed successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update trip status']);
            }
            
            $update_stmt->close();
            
        } elseif ($action === 'cancel') {
            // Can only cancel pending trips
            if ($trip['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Trip cannot be cancelled. Current status: ' . $trip['status']]);
                exit;
            }
            
            $reason = $_POST['reason'] ?? 'No reason provided';
            
            // Update trip to cancelled
            $update_stmt = $conn->prepare("UPDATE trips SET status = 'cancelled' WHERE trip_id = ? AND driver_id = ?");
            $update_stmt->bind_param("ss", $trip_id, $driver_id);
            
            if ($update_stmt->execute()) {
                // Create notification for trip cancellation
                $notif_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $user_id = $_SESSION['user_id'];
                $message = "Trip #{$trip_id} has been cancelled. Reason: {$reason}";
                $type = "warning";
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (notification_id, user_id, message, type, status) VALUES (?, ?, ?, ?, 'unread')");
                $notif_stmt->bind_param("ssss", $notif_id, $user_id, $message, $type);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Trip cancelled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update trip status']);
            }
            
            $update_stmt->close();
            
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>