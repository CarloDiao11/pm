<?php
// backend/add_driver_document.php
define('APP_LOADED', true);
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$driver_id = $_POST['driver_id'] ?? '';
$doc_type = trim($_POST['doc_type'] ?? '');
$expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

// Validate required fields
if (empty($driver_id) || empty($doc_type)) {
    header('Location: ../admin/modules/drivers_document.php?error=missing_fields');
    exit;
}

// Handle file upload
$uploadDir = '../uploads/documents/'; // Relative to backend/ → project-root/uploads/documents/
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filePath = null;
if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['document_file'];
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

    $fileType = mime_content_type($file['tmp_name']); // More secure than $_FILES['type']
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileType, $allowedTypes) || !in_array($ext, $allowedExts)) {
        header('Location: ../admin/modules/drivers_document.php?error=invalid_file');
        exit;
    }

    $fileName = 'doc_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $fullPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        // ✅ Store ABSOLUTE PATH from web root (starts with /)
        $filePath = '/uploads/documents/' . $fileName;
    } else {
        header('Location: ../admin/modules/drivers_document.php?error=upload_failed');
        exit;
    }
} else {
    header('Location: ../admin/modules/drivers_document.php?error=missing_file');
    exit;
}

// Insert into database
try {
    // Adjust field names to match your DB schema
    // Assuming your table has: doc_id (PK), driver_id, doc_type, file_url, expiry_date
    db_insert('driver_documents', [
        'doc_id' => bin2hex(random_bytes(16)), // or omit if auto-increment
        'driver_id' => $driver_id,
        'doc_type' => $doc_type,
        'file_url' => $filePath,
        'expiry_date' => $expiry_date
    ]);

    // ✅ Redirect to correct admin page
    header('Location: ../admin/modules/drivers_document.php?success=document_added');
    exit;

} catch (Exception $e) {
    error_log("Database insert error in add_driver_document.php: " . $e->getMessage());
    header('Location: ../admin/modules/drivers_document.php?error=db_error');
    exit;
}
?>