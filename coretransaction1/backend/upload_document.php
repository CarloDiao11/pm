<?php
// backend/upload_document.php
define('APP_LOADED', true);
require_once 'db1.php'; // This gives you $conn (MySQLi)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$docType = $_POST['type'] ?? '';

if (empty($docType)) {
    echo json_encode(['success' => false, 'message' => 'Document type is required']);
    exit;
}

// Map to internal type
$docTypeMap = [
    "Driver's License" => 'drivers_license',
    "Vehicle Registration (OR/CR)" => 'vehicle_registration',
    "Vehicle Insurance" => 'vehicle_insurance',
    "Police Clearance" => 'police_clearance',
    "Barangay Clearance" => 'barangay_clearance',
    "Valid ID" => 'valid_id'
];
$internalType = $docTypeMap[$docType] ?? preg_replace('/[^a-z0-9_]/i', '_', strtolower($docType));

// Handle file upload
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file selected']);
    exit;
}

$file = $_FILES['document'];
$allowedMime = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

$mimeType = mime_content_type($file['tmp_name']);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($mimeType, $allowedMime) || !in_array($ext, $allowedExts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file. Only PDF, JPG, PNG allowed.']);
    exit;
}

$uploadDir = '../uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'doc_' . substr(str_replace('-', '', $user_id), 0, 8) . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$fullPath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    echo json_encode(['success' => false, 'message' => 'File save failed']);
    exit;
}

// Use MySQLi ($conn) to insert into DB
try {
    // Get driver_id from user_id
    $stmt = $conn->prepare("SELECT drivers_id FROM drivers WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();

    if (!$driver) {
        unlink($fullPath);
        echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
        exit;
    }

    $driver_id = $driver['drivers_id'];
    $fileUrl = '/uploads/documents/' . $fileName;
    $doc_id = substr(chunk_split(bin2hex(random_bytes(16)), 8, '-'), 0, 36);

    // Insert into driver_documents
    $insert = $conn->prepare("
        INSERT INTO driver_documents (doc_id, driver_id, doc_type, file_url, expiry_date)
        VALUES (?, ?, ?, ?, NULL)
    ");
    $insert->bind_param("ssss", $doc_id, $driver_id, $internalType, $fileUrl);

    if ($insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
    } else {
        unlink($fullPath);
        echo json_encode(['success' => false, 'message' => 'Database insert failed']);
    }

    $stmt->close();
    $insert->close();

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    unlink($fullPath);
    echo json_encode(['success' => false, 'message' => 'System error']);
}

$conn->close();
?>