<?php
// admin/modules/drivers_document.php - Driver Documents Management with Modal
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Fetch active drivers for dropdown
$activeDrivers = db_select_advanced("
    SELECT d.drivers_id, u.name 
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id 
    WHERE u.role = 'driver' AND u.status = 'active'
    ORDER BY u.name
");

// Fetch all drivers with documents (for display)
$drivers = db_select_advanced("
    SELECT 
        u.user_id,
        u.name AS driver_name,
        d.drivers_id,
        dd.doc_id,
        dd.doc_type,
        dd.file_url,
        dd.expiry_date
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id AND u.role = 'driver' AND u.status = 'active'
    LEFT JOIN driver_documents dd ON d.drivers_id = dd.driver_id
    ORDER BY u.name ASC, dd.expiry_date ASC
");

// Group documents by driver
$driverMap = [];
foreach ($drivers as $row) {
    $driverId = $row['drivers_id'];
    if (!isset($driverMap[$driverId])) {
        $driverMap[$driverId] = [
            'driver_id' => $row['drivers_id'],
            'driver_name' => $row['driver_name'],
            'user_id' => $row['user_id'],
            'documents' => []
        ];
    }
    if ($row['doc_id']) {
        // Ensure file_url starts with / for absolute path
        $fileUrl = $row['file_url'];
        if ($fileUrl && $fileUrl[0] !== '/') {
            $fileUrl = '/' . ltrim($fileUrl, '/');
        }
        $driverMap[$driverId]['documents'][] = [
            'doc_id' => $row['doc_id'],
            'doc_type' => $row['doc_type'],
            'file_url' => $fileUrl,
            'expiry_date' => $row['expiry_date']
        ];
    }
}
$driverList = array_values($driverMap);

// Helpers
function getDocIcon($type) {
    $type = strtolower($type);
    if (strpos($type, 'license') !== false) return 'bi-card-text';
    if (strpos($type, 'image') !== false || strpos($type, 'photo') !== false) return 'bi-file-earmark-image';
    return 'bi-file-earmark-pdf';
}

function getExpiryClass($expiry) {
    if (!$expiry) return '';
    $expiryDate = strtotime($expiry);
    $today = strtotime(date('Y-m-d'));
    if ($expiryDate < $today) return 'expired';
    if (($expiryDate - $today) / (60 * 60 * 24) <= 30) return 'expiring';
    return '';
}

function getComplianceStatus($docs) {
    if (empty($docs)) return ['text' => 'No Documents', 'class' => 'expired'];
    
    $expired = 0; $expiringSoon = 0; $today = strtotime(date('Y-m-d'));
    foreach ($docs as $doc) {
        if ($doc['expiry_date']) {
            $expiry = strtotime($doc['expiry_date']);
            if ($expiry < $today) $expired++;
            elseif (($expiry - $today) / (60 * 60 * 24) <= 30) $expiringSoon++;
        }
    }
    if ($expired > 0) return ['text' => "$expired Expired", 'class' => 'expired'];
    if ($expiringSoon > 0) return ['text' => "$expiringSoon Expiring", 'class' => 'warning'];
    return ['text' => 'All Valid', 'class' => 'compliant'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Documents - Fleet Management System</title>
    <!-- Fixed paths: now ../../css/... -->
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/drivers-documents.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .driver-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: #444; color: white; display: flex;
            align-items: center; justify-content: center;
            font-weight: bold; font-size: 16px;
            overflow: hidden; flex-shrink: 0;
        }
        .driver-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .compliance-status.expired { color: #c62828; font-weight: bold; }
        .compliance-status.warning { color: #f57f17; }
        .compliance-status.compliant { color: #2e7d32; font-weight: bold; }
        .doc-expiry.expired { color: #c62828; }
        .doc-expiry.expiring { color: #f57f17; }
        .modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .form-label { font-weight: 600; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Driver Documents</h1>
            <button class="btn-add-document" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                <i class="bi bi-plus-lg"></i> Add Document
            </button>
        </div>
        
        <div class="documents-grid">
            <?php if (empty($driverList)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                    No drivers found
                </div>
            <?php else: ?>
                <?php foreach ($driverList as $driver): ?>
                <?php
                $compliance = getComplianceStatus($driver['documents']);
                $docCount = count($driver['documents']);
                ?>
                <div class="driver-card">
                    <div class="driver-header">
                        <div class="driver-info">
                            <div class="driver-avatar">
                                <?php 
                                $user = db_select('users', ['user_id' => $driver['user_id']], ['limit' => 1]);
                                $profilePic = $user[0]['profile_picture'] ?? null;
                                if ($profilePic && file_exists('../../' . ltrim($profilePic, '/'))): ?>
                                    <img src="<?= htmlspecialchars('../../' . $profilePic) ?>" alt="Profile">
                                <?php else: 
                                    $initials = strtoupper(substr($driver['driver_name'], 0, 1) . (preg_match('/\s(\w)/', $driver['driver_name'], $m) ? $m[1] : 'X'));
                                    echo htmlspecialchars($initials);
                                endif; ?>
                            </div>
                            <div class="driver-details">
                                <span class="driver-name"><?= htmlspecialchars($driver['driver_name']) ?></span>
                                <span class="driver-id"><?= htmlspecialchars(substr($driver['driver_id'], 0, 8)) ?></span>
                            </div>
                        </div>
                        <div class="document-summary">
                            <span class="doc-count"><?= $docCount ?> Document<?= $docCount !== 1 ? 's' : '' ?></span>
                            <span class="compliance-status <?= $compliance['class'] ?>">
                                <?= htmlspecialchars($compliance['text']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="documents-list">
                        <?php if (empty($driver['documents'])): ?>
                            <div style="padding: 12px; color: #888; font-style: italic;">No documents uploaded</div>
                        <?php else: ?>
                            <?php foreach ($driver['documents'] as $doc): ?>
                            <div class="document-item">
                                <div class="doc-info">
                                    <i class="bi <?= getDocIcon($doc['doc_type']) ?> doc-icon"></i>
                                    <div class="doc-details">
                                        <span class="doc-name"><?= htmlspecialchars($doc['doc_type']) ?></span>
                                        <?php if ($doc['expiry_date']): ?>
                                            <span class="doc-expiry <?= getExpiryClass($doc['expiry_date']) ?>">
                                                <?= (getExpiryClass($doc['expiry_date']) === 'expired') ? 'Expired: ' : 'Expires: ' ?>
                                                <?= date('Y-m-d', strtotime($doc['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="doc-expiry">No expiry</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="doc-actions">
                                    <?php if (!empty($doc['file_url'])): ?>
                                        <button class="btn-doc-action" title="View"
                                            onclick="window.open('<?= htmlspecialchars($doc['file_url']) ?>', '_blank')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="<?= htmlspecialchars($doc['file_url']) ?>" download class="btn-doc-action" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-doc-action" disabled title="No file">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ADD DOCUMENT MODAL -->
    <div class="modal fade" id="addDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2"></i>Add Driver Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../backend/add_driver_document.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Driver *</label>
                            <select class="form-select" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($activeDrivers as $drv): ?>
                                <option value="<?= htmlspecialchars($drv['drivers_id']) ?>"><?= htmlspecialchars($drv['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Document Type *</label>
                            <input type="text" class="form-control" name="doc_type" placeholder="e.g. Driver's License, Medical Certificate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date">
                            <div class="form-text">Leave blank if no expiry</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload File *</label>
                            <input type="file" class="form-control" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">PDF, JPG, or PNG only</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Show notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success === 'document_added') {
                showNotification('Document uploaded successfully!', 'success');
            }
            if (error) {
                let msg = '';
                if (error === 'missing_fields') msg = 'Please fill all required fields.';
                else if (error === 'invalid_file') msg = 'Invalid file type. Only PDF, JPG, PNG allowed.';
                else if (error === 'upload_failed') msg = 'Failed to upload document.';
                else if (error === 'missing_file') msg = 'Please select a document file.';
                if (msg) showNotification(msg, 'error');
            }
        });

        function showNotification(message, type) {
            const notif = document.createElement('div');
            notif.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notif.style.cssText = 'top:80px;right:20px;z-index:9999;min-width:300px;';
            notif.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 5000);
        }
    </script>
</body>
</html>