<?php
// trips.php - Trips Management with View & Edit Modals
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Fetch all trips with driver & vehicle info
$trips = db_select_advanced("
    SELECT 
        t.trip_id,
        t.trip_type,
        t.origin,
        t.destination,
        t.status,
        t.start_time,
        t.end_time,
        u.name AS driver_name,
        d.drivers_id,
        v.plate_number,
        v.vehicle_id,
        v.type AS vehicle_type
    FROM trips t
    LEFT JOIN drivers d ON t.driver_id = d.drivers_id
    LEFT JOIN users u ON d.user_id = u.user_id AND u.role = 'driver'
    LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
    ORDER BY 
        CASE t.status 
            WHEN 'ongoing' THEN 1
            WHEN 'pending' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        t.start_time DESC
");

// Fetch available drivers & vehicles for edit modal
$availableDrivers = db_select_advanced("
    SELECT d.drivers_id, u.name 
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id
    WHERE u.status = 'active'
    ORDER BY u.name
");
$availableVehicles = db_select('vehicles', [], [
    'columns' => 'vehicle_id, plate_number, type',
    'order_by' => 'plate_number'
]);

// Helpers
function getStatusClass($status) {
    return match($status) {
        'ongoing' => 'status-active',
        'completed' => 'status-completed',
        'pending' => 'status-pending',
        'cancelled' => 'status-cancelled',
        default => 'status-pending'
    };
}

function formatDateTime($dt) {
    return $dt ? date('Y-m-d H:i:s', strtotime($dt)) : '-';
}

function formatDateForInput($dt) {
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch & Trips - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/trips.css">
    <!-- Fixed CDN URLs (no trailing spaces!) -->
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
        .status-badge {
            padding: 0.25em 0.6em;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-completed { background: #e3f2fd; color: #1565c0; }
        .status-pending { background: #fff8e1; color: #f57f17; }
        .status-cancelled { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dispatch & Trips</h1>
            <button class="btn-create-trip" data-bs-toggle="modal" data-bs-target="#createTripModal">
                <i class="bi bi-plus-lg"></i>
                Create New Trip
            </button>
        </div>
        
        <div class="trips-table-container">
            <table class="trips-table">
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Trip Type</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trips)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 20px;">No trips found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><span class="trip-id"><?= htmlspecialchars(substr($trip['trip_id'], 0, 8)) ?></span></td>
                            <td>
                                <div class="driver-info">
                                    <div class="driver-avatar">
                                        <?php
                                        $name = $trip['driver_name'] ?? 'Unassigned';
                                        $initials = strtoupper(substr($name, 0, 1) . (preg_match('/\s(\w)/', $name, $m) ? $m[1] : substr($name, 1, 1)));
                                        echo htmlspecialchars($initials);
                                        ?>
                                    </div>
                                    <span><?= htmlspecialchars($name) ?></span>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($trip['vehicle_type'] ?? 'Unknown') ?> - 
                                <?= htmlspecialchars($trip['plate_number'] ?? 'N/A') ?>
                            </td>
                            <td><span class="trip-type"><?= htmlspecialchars(ucfirst($trip['trip_type'] ?? 'N/A')) ?></span></td>
                            <td><?= htmlspecialchars($trip['origin'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($trip['destination'] ?? '—') ?></td>
                            <td><?= formatDateTime($trip['start_time']) ?></td>
                            <td><?= formatDateTime($trip['end_time']) ?></td>
                            <td>
                                <span class="status-badge <?= getStatusClass($trip['status']) ?>">
                                    <?= ucfirst($trip['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- View Modal Trigger -->
                                    <button class="btn-action btn-view" title="View Details"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewTripModal"
                                        onclick="loadTripData(
                                            '<?= addslashes($trip['trip_id']) ?>',
                                            '<?= addslashes($trip['driver_name'] ?? 'Unassigned') ?>',
                                            '<?= addslashes($trip['plate_number'] ?? 'N/A') ?>',
                                            '<?= addslashes(ucfirst($trip['trip_type'] ?? '')) ?>',
                                            '<?= addslashes($trip['origin'] ?? '') ?>',
                                            '<?= addslashes($trip['destination'] ?? '') ?>',
                                            '<?= formatDateTime($trip['start_time']) ?>',
                                            '<?= formatDateTime($trip['end_time']) ?>',
                                            '<?= ucfirst($trip['status']) ?>'
                                        )">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <!-- Edit Modal Trigger -->
                                    <button class="btn-action btn-edit" title="Edit Trip"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editTripModal"
                                        onclick="loadEditTripData(
                                            '<?= addslashes($trip['trip_id']) ?>',
                                            '<?= addslashes($trip['drivers_id'] ?? '') ?>',
                                            '<?= addslashes($trip['vehicle_id'] ?? '') ?>',
                                            '<?= addslashes($trip['trip_type'] ?? '') ?>',
                                            '<?= addslashes($trip['origin'] ?? '') ?>',
                                            '<?= addslashes($trip['destination'] ?? '') ?>',
                                            '<?= addslashes($trip['status'] ?? '') ?>',
                                            '<?= formatDateForInput($trip['start_time']) ?>',
                                            '<?= formatDateForInput($trip['end_time']) ?>'
                                        )">
                                        <i class="bi bi-pencil"></i>
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

    <!-- VIEW TRIP MODAL -->
    <div class="modal fade" id="viewTripModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Trip Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4"><strong>Trip ID:</strong></div>
                        <div class="col-8" id="view-trip-id"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Driver:</strong></div>
                        <div class="col-8" id="view-driver"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Vehicle:</strong></div>
                        <div class="col-8" id="view-vehicle"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Trip Type:</strong></div>
                        <div class="col-8" id="view-type"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Origin:</strong></div>
                        <div class="col-8" id="view-origin"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Destination:</strong></div>
                        <div class="col-8" id="view-destination"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Start Time:</strong></div>
                        <div class="col-8" id="view-start"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>End Time:</strong></div>
                        <div class="col-8" id="view-end"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Status:</strong></div>
                        <div class="col-8">
                            <span class="status-badge" id="view-status-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT TRIP MODAL -->
    <div class="modal fade" id="editTripModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Trip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editTripForm" method="POST" action="../../backend/update_trip.php">
                    <input type="hidden" id="edit-trip-id" name="trip_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Driver</label>
                            <select class="form-select" id="edit-driver-id" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($availableDrivers as $driver): ?>
                                <option value="<?= $driver['drivers_id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vehicle</label>
                            <select class="form-select" id="edit-vehicle-id" name="vehicle_id" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($availableVehicles as $vehicle): ?>
                                <option value="<?= $vehicle['vehicle_id'] ?>"><?= htmlspecialchars($vehicle['plate_number']) ?> - <?= $vehicle['type'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trip Type</label>
                            <select class="form-select" id="edit-trip-type" name="trip_type" required>
                                <option value="delivery">Delivery</option>
                                <option value="pickup">Pickup</option>
                                <option value="transport">Transport</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Origin</label>
                            <input type="text" class="form-control" id="edit-origin" name="origin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destination</label>
                            <input type="text" class="form-control" id="edit-destination" name="destination" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="edit-status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="datetime-local" class="form-control" id="edit-start-time" name="start_time">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="datetime-local" class="form-control" id="edit-end-time" name="end_time">
                            </div>
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

    <!-- CREATE TRIP MODAL (same as index.php) -->
    <div class="modal fade" id="createTripModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Create New Trip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../backend/create_trip.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Driver</label>
                            <select class="form-select" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($availableDrivers as $driver): ?>
                                <option value="<?= $driver['drivers_id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vehicle</label>
                            <select class="form-select" name="vehicle_id" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($availableVehicles as $vehicle): ?>
                                <option value="<?= $vehicle['vehicle_id'] ?>"><?= htmlspecialchars($vehicle['plate_number']) ?> - <?= $vehicle['type'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trip Type</label>
                            <select class="form-select" name="trip_type" required>
                                <option value="">Select Type</option>
                                <option value="delivery">Delivery</option>
                                <option value="pickup">Pickup</option>
                                <option value="transport">Transport</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Origin</label>
                            <input type="text" class="form-control" name="origin" placeholder="Starting location" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destination</label>
                            <input type="text" class="form-control" name="destination" placeholder="Destination location" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Create Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Fixed CDN URLs -->
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

        // Load data into View Modal
        function loadTripData(id, driver, vehicle, type, origin, dest, start, end, status) {
            document.getElementById('view-trip-id').textContent = id;
            document.getElementById('view-driver').textContent = driver;
            document.getElementById('view-vehicle').textContent = vehicle;
            document.getElementById('view-type').textContent = type;
            document.getElementById('view-origin').textContent = origin || '—';
            document.getElementById('view-destination').textContent = dest || '—';
            document.getElementById('view-start').textContent = start;
            document.getElementById('view-end').textContent = end;
            
            const badge = document.getElementById('view-status-badge');
            badge.textContent = status;
            badge.className = 'status-badge';
            if (status.toLowerCase() === 'ongoing') badge.classList.add('status-active');
            else if (status.toLowerCase() === 'completed') badge.classList.add('status-completed');
            else if (status.toLowerCase() === 'pending') badge.classList.add('status-pending');
            else if (status.toLowerCase() === 'cancelled') badge.classList.add('status-cancelled');
        }

        // Load data into Edit Modal
        function loadEditTripData(id, driverId, vehicleId, type, origin, dest, status, start, end) {
            document.getElementById('edit-trip-id').value = id;
            document.getElementById('edit-driver-id').value = driverId || '';
            document.getElementById('edit-vehicle-id').value = vehicleId || '';
            document.getElementById('edit-trip-type').value = type;
            document.getElementById('edit-origin').value = origin;
            document.getElementById('edit-destination').value = dest;
            document.getElementById('edit-status').value = status;
            document.getElementById('edit-start-time').value = start;
            document.getElementById('edit-end-time').value = end;
        }
    </script>
</body>
</html>