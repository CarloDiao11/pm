<?php
// fleet.php - Fleet Management with Modals
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Fetch all vehicles
$vehicles = db_select('vehicles', [], [
    'order_by' => 'plate_number ASC'
]);

// Fetch vehicles for maintenance scheduling (only those not inactive)
$maintenanceVehicles = db_select('vehicles', ['status !=' => 'inactive'], [
    'columns' => 'vehicle_id, plate_number, type',
    'order_by' => 'plate_number'
]);

// Helpers
function getVehicleStatusClass($status) {
    return match($status) {
        'available' => 'status-active',
        'in_use' => 'status-active',
        'maintenance' => 'status-maintenance',
        'inactive' => 'status-inactive',
        default => 'status-inactive'
    };
}

function formatMileage($km) {
    return $km !== null ? number_format($km, 0, '.', ',') . ' km' : '—';
}

function getInsuranceClass($expiry) {
    if (!$expiry) return '';
    $expiryDate = strtotime($expiry);
    $today = strtotime(date('Y-m-d'));
    if ($expiryDate < $today) return 'expired';
    if (($expiryDate - $today) / (60 * 60 * 24) <= 30) return 'near-expiry';
    return '';
}

function formatDateForInput($date) {
    return $date ? date('Y-m-d', strtotime($date)) : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Management - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/fleet.css">
    <!-- FIXED: Removed trailing spaces in CDN URLs -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-maintenance { background: #fff8e1; color: #f57f17; }
        .status-inactive { background: #ffebee; color: #c62828; }
        .insurance-expiry.expired { color: #c62828; font-weight: bold; }
        .insurance-expiry.near-expiry { color: #f57f17; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Fleet Management</h1>
            <div class="header-buttons">
                <button class="btn-fleet btn-schedule" data-bs-toggle="modal" data-bs-target="#scheduleMaintenanceModal">
                    <i class="bi bi-calendar-check"></i>
                    Schedule Maintenance
                </button>
                <button class="btn-fleet btn-add" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                    <i class="bi bi-plus-lg"></i>
                    Add Vehicle
                </button>
            </div>
        </div>
        
        <div class="fleet-table-container">
            <table class="fleet-table">
                <thead>
                    <tr>
                        <th>Vehicle ID</th>
                        <th>Plate Number</th>
                        <th>Type (Model)</th>
                        <th>Status</th>
                        <th>Mileage</th>
                        <th>Insurance Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">No vehicles found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td><span class="vehicle-id"><?= htmlspecialchars(substr($v['vehicle_id'], 0, 8)) ?></span></td>
                            <td><span class="plate-number"><?= htmlspecialchars($v['plate_number'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($v['type'] ?? 'Unknown') ?></td>
                            <td>
                                <span class="status <?= getVehicleStatusClass($v['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $v['status'])) ?>
                                </span>
                            </td>
                            <td><?= formatMileage($v['mileage']) ?></td>
                            <td>
                                <span class="insurance-expiry <?= getInsuranceClass($v['insurance_expiry']) ?>">
                                    <?= $v['insurance_expiry'] ? date('Y-m-d', strtotime($v['insurance_expiry'])) : '—' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-view" title="View Details"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewVehicleModal"
                                        onclick="loadVehicleView(
                                            '<?= addslashes($v['vehicle_id']) ?>',
                                            '<?= addslashes($v['plate_number'] ?? 'N/A') ?>',
                                            '<?= addslashes($v['type'] ?? 'Unknown') ?>',
                                            '<?= addslashes($v['status']) ?>',
                                            '<?= $v['mileage'] ?>',
                                            '<?= addslashes($v['insurance_expiry'] ?? '') ?>'
                                        )">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-action btn-edit" title="Edit Vehicle"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editVehicleModal"
                                        onclick="loadVehicleEdit(
                                            '<?= addslashes($v['vehicle_id']) ?>',
                                            '<?= addslashes($v['plate_number'] ?? '') ?>',
                                            '<?= addslashes($v['type'] ?? '') ?>',
                                            '<?= addslashes($v['status']) ?>',
                                            '<?= $v['mileage'] ?>',
                                            '<?= addslashes($v['insurance_expiry'] ?? '') ?>'
                                        )">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-action btn-maintenance" title="Schedule Maintenance"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#scheduleMaintenanceModal"
                                        onclick="document.getElementById('maint-vehicle-id').value = '<?= addslashes($v['vehicle_id']) ?>'">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- VIEW VEHICLE MODAL -->
    <div class="modal fade" id="viewVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Vehicle Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4"><strong>Plate Number:</strong></div>
                        <div class="col-8" id="view-plate"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Type:</strong></div>
                        <div class="col-8" id="view-type"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Status:</strong></div>
                        <div class="col-8">
                            <span class="status" id="view-status-badge"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Mileage:</strong></div>
                        <div class="col-8" id="view-mileage"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Insurance Expiry:</strong></div>
                        <div class="col-8" id="view-insurance"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT VEHICLE MODAL -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editVehicleForm" method="POST" action="../../backend/update_vehicle.php">
                    <input type="hidden" id="edit-vehicle-id" name="vehicle_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" id="edit-plate" name="plate_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vehicle Type / Model</label>
                            <input type="text" class="form-control" id="edit-type" name="type" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="edit-status" name="status" required>
                                <option value="available">Available</option>
                                <option value="in_use">In Use</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Mileage (km)</label>
                            <input type="number" class="form-control" id="edit-mileage" name="mileage" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Insurance Expiry</label>
                            <input type="date" class="form-control" id="edit-insurance" name="insurance_expiry">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD VEHICLE MODAL -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Add New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../backend/add_vehicle.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" name="plate_number" placeholder="ABC-123" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vehicle Type / Model</label>
                            <input type="text" class="form-control" name="type" placeholder="e.g., Toyota Hiace, Isuzu Elf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initial Mileage (km)</label>
                            <input type="number" class="form-control" name="mileage" step="0.01" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Insurance Expiry</label>
                            <input type="date" class="form-control" name="insurance_expiry" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCHEDULE MAINTENANCE MODAL -->
    <div class="modal fade" id="scheduleMaintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-wrench me-2"></i>Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../backend/schedule_maintenance.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Vehicle</label>
                            <select class="form-select" id="maint-vehicle-id" name="vehicle_id" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($maintenanceVehicles as $veh): ?>
                                <option value="<?= $veh['vehicle_id'] ?>"><?= htmlspecialchars($veh['plate_number']) ?> - <?= $veh['type'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maintenance Type</label>
                            <input type="text" class="form-control" name="type" placeholder="e.g., Oil Change, Tire Rotation" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check me-1"></i>Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- FIXED: Removed trailing spaces -->
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
        document.getElementById('overlay').addEventListener('click', closeSidebar);

        // Load View Modal
        function loadVehicleView(id, plate, type, status, mileage, insurance) {
            document.getElementById('view-plate').textContent = plate;
            document.getElementById('view-type').textContent = type;
            
            const statusBadge = document.getElementById('view-status-badge');
            statusBadge.textContent = ucfirst(status.replace('_', ' '));
            statusBadge.className = 'status ' + getVehicleStatusClass(status);
            
            document.getElementById('view-mileage').textContent = mileage ? parseFloat(mileage).toLocaleString() + ' km' : '—';
            document.getElementById('view-insurance').textContent = insurance ? insurance : '—';
        }

        // Load Edit Modal
        function loadVehicleEdit(id, plate, type, status, mileage, insurance) {
            document.getElementById('edit-vehicle-id').value = id;
            document.getElementById('edit-plate').value = plate;
            document.getElementById('edit-type').value = type;
            document.getElementById('edit-status').value = status;
            document.getElementById('edit-mileage').value = mileage || '';
            document.getElementById('edit-insurance').value = insurance || '';
        }

        // Helper: status class mapping (for JS)
        function getVehicleStatusClass(status) {
            if (status === 'available' || status === 'in_use') return 'status-active';
            if (status === 'maintenance') return 'status-maintenance';
            return 'status-inactive';
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>