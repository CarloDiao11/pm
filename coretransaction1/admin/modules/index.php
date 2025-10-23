<?php
// index.php - Fully Dynamic Dashboard with Quick Action Modals
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Helper functions
function formatPeso($amount) {
    return '₱' . number_format($amount, 0);
}

function percentChange($current, $previous) {
    if ($previous == 0 && $current == 0) return 0;
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function time_ago($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return date('M j', $timestamp);
}

$todayDate = date('Y-m-d');

// --- FETCH CURRENT KPIs ---
$activeTrips = db_count('trips', ['status' => 'ongoing']);
$pendingPayouts = (float) (@db_select('transactions', ['status' => 'pending', 'type' => 'debit'], ['columns' => 'COALESCE(SUM(amount), 0) as total'])[0]['total'] ?? 0);

// Low Stock
$allConsumables = db_select('consumables', [], [
    'columns' => 'consumable_id, item_name, stock_qty, min_threshold, last_low_stock_alert'
]);
$lowStockAlerts = 0;
$newLowStockCount = 0;
foreach ($allConsumables as $item) {
    if ($item['stock_qty'] <= $item['min_threshold']) {
        $lowStockAlerts++;
        if (empty($item['last_low_stock_alert']) || $item['last_low_stock_alert'] !== $todayDate) {
            db_update('consumables', [
                'last_low_stock_alert' => $todayDate
            ], [
                'consumable_id' => $item['consumable_id']
            ]);
            $newLowStockCount++;
        }
    }
}
$lowStockChangeText = $newLowStockCount . ' new alert' . ($newLowStockCount !== 1 ? 's' : '') . ' today';

$totalVehicles = db_count('vehicles');
$operationalVehicles = db_count('vehicles', ['status' => 'available']);

// --- SAVE TODAY'S SNAPSHOT ---
$existingSnapshot = db_select('kpi_snapshots', ['snapshot_date' => $todayDate]);
if (empty($existingSnapshot)) {
    db_insert('kpi_snapshots', [
        'snapshot_id' => bin2hex(random_bytes(16)),
        'snapshot_date' => $todayDate,
        'active_trips' => $activeTrips,
        'pending_payouts' => $pendingPayouts,
        'low_stock_count' => $lowStockAlerts
    ]);
}

// --- COMPARE WITH LAST WEEK ---
$lastWeekDate = date('Y-m-d', strtotime('-7 days'));
$lastWeekData = db_select('kpi_snapshots', ['snapshot_date' => $lastWeekDate]);
$lastWeekActive = $lastWeekData ? (int)$lastWeekData[0]['active_trips'] : 0;
$lastWeekPayouts = $lastWeekData ? (float)$lastWeekData[0]['pending_payouts'] : 0;
$lastWeekLowStock = $lastWeekData ? (int)$lastWeekData[0]['low_stock_count'] : 0;

// Calculate changes
$tripChange = percentChange($activeTrips, $lastWeekActive);
$payoutChange = percentChange($pendingPayouts, $lastWeekPayouts);
$lowStockChange = percentChange($lowStockAlerts, $lastWeekLowStock);

// Trend classes
$tripTrendClass = $tripChange > 0 ? 'trend-up' : ($tripChange < 0 ? 'trend-down' : 'trend-neutral');
$payoutTrendClass = $payoutChange > 0 ? 'trend-up' : ($payoutChange < 0 ? 'trend-down' : 'trend-neutral');
$lowStockTrendClass = $lowStockChange > 0 ? 'trend-up' : ($lowStockChange < 0 ? 'trend-down' : 'trend-neutral');

$tripChangeText = ($tripChange >= 0 ? '+' : '') . $tripChange . '% from last week';
$payoutChangeText = ($payoutChange >= 0 ? '+' : '') . $payoutChange . '% from last week';

// --- Fetch data for modals ---
$availableDrivers = db_select_advanced("
    SELECT d.drivers_id, u.name, d.license_number 
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id
    WHERE u.status = 'active' AND d.license_expiry > CURDATE()
    ORDER BY u.name
");

$availableVehicles = db_select('vehicles', ['status' => 'available'], [
    'columns' => 'vehicle_id, plate_number, type',
    'order_by' => 'plate_number'
]);

// --- Recent Activities ---
$activities = [];
$recentTrips = db_select('trips', [], [
    'columns' => 'trip_id, driver_id, vehicle_id, status, start_time, end_time',
    'order_by' => 'COALESCE(end_time, start_time) DESC',
    'limit' => 5
]);
foreach ($recentTrips as $trip) {
    $driver = db_select('users', ['user_id' => $trip['driver_id']], ['limit' => 1]);
    $driverName = $driver ? $driver[0]['name'] : 'Unknown';
    if ($trip['status'] === 'ongoing') {
        $activities[] = [
            'icon' => 'trip-start',
            'text' => "<strong>Trip #{$trip['trip_id']}</strong> started by {$driverName}",
            'time' => time_ago(strtotime($trip['start_time']))
        ];
    } else {
        $activities[] = [
            'icon' => 'trip-complete',
            'text' => "<strong>Trip #{$trip['trip_id']}</strong> completed successfully",
            'time' => time_ago(strtotime($trip['end_time']))
        ];
    }
}

// --- Upcoming Maintenance ---
$maintenanceItems = [];
$maintSchedules = db_select('maintenance_schedules', [], [
    'columns' => 'vehicle_id, schedule_date, type',
    'order_by' => 'schedule_date ASC',
    'limit' => 3
]);
foreach ($maintSchedules as $sched) {
    $vehicle = db_select('vehicles', ['vehicle_id' => $sched['vehicle_id']], ['limit' => 1]);
    $plate = $vehicle ? $vehicle[0]['plate_number'] : 'Unknown';
    $type = $vehicle ? $vehicle[0]['type'] : 'Vehicle';
    $dueDate = date('M j, Y', strtotime($sched['schedule_date']));
    $isOverdue = strtotime($sched['schedule_date']) < time();
    $maintenanceItems[] = [
        'plate' => $plate,
        'name' => $type,
        'type' => $sched['type'],
        'date' => $dueDate,
        'overdue' => $isOverdue
    ];
}

// --- Top Drivers ---
$topDrivers = db_select_advanced("
    SELECT 
        u.name,
        d.rating,
        COUNT(t.trip_id) AS trip_count
    FROM drivers d
    JOIN users u ON d.user_id = u.user_id AND u.role = 'driver' AND u.status = 'active'
    LEFT JOIN trips t ON d.drivers_id = t.driver_id AND t.status = 'completed'
    GROUP BY d.drivers_id, u.name, d.rating
    ORDER BY trip_count DESC, d.rating DESC
    LIMIT 3
");

// --- Critical Alerts ---
$alerts = [];
$expiredDrivers = db_select('drivers', ['license_expiry <' => $todayDate]);
foreach ($expiredDrivers as $d) {
    $user = db_select('users', ['user_id' => $d['user_id']], ['limit' => 1]);
    if ($user) {
        $alerts[] = [
            'type' => 'urgent',
            'text' => 'License expired for ' . $user[0]['name'],
            'time' => 'Today'
        ];
    }
}
$lowStockItems = db_select('consumables', ['stock_qty <= min_threshold'], ['limit' => 1]);
if (!empty($lowStockItems)) {
    $alerts[] = [
        'type' => 'info',
        'text' => 'Low stock: ' . $lowStockItems[0]['item_name'],
        'time' => 'Yesterday'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/dashboard.css">
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
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-primary {
            background: #3498db;
            border: none;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="dashboard-header">
            <div>
                <h1 class="page-title">Dashboard Overview</h1>
                <p class="dashboard-subtitle">Welcome back, Admin! Here's what's happening today.</p>
            </div>
            <div class="header-actions">
                <button class="btn-dashboard-action">
                    <i class="bi bi-download"></i>
                    Export Report
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon trips"><i class="bi bi-truck"></i></div>
                    <div class="kpi-value"><?= $activeTrips ?></div>
                    <div class="kpi-label">Active Trips</div>
                    <div class="kpi-trend <?= $tripTrendClass ?>">
                        <i class="bi bi-arrow-<?= $tripChange > 0 ? 'up' : ($tripChange < 0 ? 'down' : 'right') ?>"></i>
                        <span><?= htmlspecialchars($tripChangeText) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon payouts"><i class="bi bi-currency-dollar"></i></div>
                    <div class="kpi-value"><?= formatPeso($pendingPayouts) ?></div>
                    <div class="kpi-label">Pending Payouts</div>
                    <div class="kpi-trend <?= $payoutTrendClass ?>">
                        <i class="bi bi-arrow-<?= $payoutChange > 0 ? 'up' : ($payoutChange < 0 ? 'down' : 'right') ?>"></i>
                        <span><?= htmlspecialchars($payoutChangeText) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon alerts"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="kpi-value"><?= $lowStockAlerts ?></div>
                    <div class="kpi-label">Low Stock Alerts</div>
                    <div class="kpi-trend <?= $lowStockTrendClass ?>">
                        <i class="bi bi-arrow-<?= $lowStockChange > 0 ? 'up' : ($lowStockChange < 0 ? 'down' : 'right') ?>"></i>
                        <span><?= htmlspecialchars($lowStockChangeText) ?></span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon vehicles"><i class="bi bi-truck-flatbed"></i></div>
                    <div class="kpi-value"><?= $totalVehicles ?></div>
                    <div class="kpi-label">Total Vehicles</div>
                    <div class="kpi-trend trend-neutral">
                        <i class="bi bi-check-circle"></i>
                        <span><?= $operationalVehicles ?> operational</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Recent Activities -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-clock-history"></i> Recent Activities</h3>
                        <a href="#" class="view-all-link">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php foreach ($activities as $act): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $act['icon'] ?>">
                                    <i class="bi bi-<?= $act['icon'] === 'trip-start' ? 'play-circle' : 'check-circle' ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text"><?= $act['text'] ?></p>
                                    <span class="activity-time"><?= $act['time'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Maintenance -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-wrench"></i> Upcoming Maintenance</h3>
                        <a href="#" class="view-all-link">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="maintenance-list">
                            <?php foreach ($maintenanceItems as $item): ?>
                            <div class="maintenance-item">
                                <div class="maintenance-vehicle">
                                    <span class="vehicle-plate"><?= htmlspecialchars($item['plate']) ?></span>
                                    <span class="vehicle-name"><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                                <div class="maintenance-details">
                                    <span class="maintenance-type"><?= htmlspecialchars($item['type']) ?></span>
                                    <span class="maintenance-date <?= $item['overdue'] ? 'overdue' : '' ?>">
                                        <?= $item['overdue'] ? 'Overdue: ' : 'Due: ' ?><?= $item['date'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions with Modals -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-lightning"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#createTripModal">
                                <i class="bi bi-plus-circle"></i> <span>Create New Trip</span>
                            </button>
                            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                                <i class="bi bi-person-plus"></i> <span>Add Driver</span>
                            </button>
                            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#logFuelModal">
                                <i class="bi bi-fuel-pump"></i> <span>Log Fuel Entry</span>
                            </button>
                            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                                <i class="bi bi-file-earmark-text"></i> <span>Generate Report</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Top Drivers -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-trophy"></i> Top Drivers</h3>
                    </div>
                    <div class="card-body">
                        <div class="driver-leaderboard">
                            <?php foreach ($topDrivers as $idx => $driver): ?>
                            <?php $rank = $idx + 1; ?>
                            <div class="leaderboard-item">
                                <div class="rank-badge <?= $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : 'bronze') ?>"><?= $rank ?></div>
                                <div class="driver-info-small">
                                    <span class="driver-name-small"><?= htmlspecialchars($driver['name']) ?></span>
                                    <span class="driver-trips"><?= (int)$driver['trip_count'] ?> trips</span>
                                </div>
                                <div class="driver-rating">
                                    <i class="bi bi-star-fill"></i> <span><?= number_format($driver['rating'], 1) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Critical Alerts -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-bell"></i> Critical Alerts</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert-list">
                            <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item alert-<?= $alert['type'] ?>">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div class="alert-content">
                                    <p class="alert-text"><?= htmlspecialchars($alert['text']) ?></p>
                                    <span class="alert-time"><?= $alert['time'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Trip Modal -->
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
                                <option value="<?= $driver['drivers_id'] ?>"><?= htmlspecialchars($driver['name']) ?> (<?= $driver['license_number'] ?>)</option>
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

    <!-- Add Driver Modal -->
    <div class="modal fade" id="addDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../backend/add_driver.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="driver@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" placeholder="ABC-12-345678" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Expiry</label>
                            <input type="date" class="form-control" name="license_expiry" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" placeholder="+63 912 345 6789" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Complete address" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Add Driver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Log Fuel Modal -->
    <div class="modal fade" id="logFuelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-fuel-pump me-2"></i>Log Fuel Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../backend/log_fuel.php" method="POST">
                    <div class="modal-body">
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
                            <label class="form-label">Driver</label>
                            <select class="form-select" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($availableDrivers as $driver): ?>
                                <option value="<?= $driver['drivers_id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fuel Type</label>
                                <select class="form-select" name="fuel_type" required>
                                    <option value="diesel">Diesel</option>
                                    <option value="petrol">Petrol</option>
                                    <option value="gas">Gas</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Liters</label>
                                <input type="number" class="form-control" name="liters" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost (₱)</label>
                                <input type="number" class="form-control" name="cost" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Odometer Reading</label>
                                <input type="number" class="form-control" name="odometer_reading" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Station Name</label>
                            <input type="text" class="form-control" name="station_name" placeholder="Gas station name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" name="receipt_number" placeholder="Receipt #">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Log Fuel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Generate Report Modal -->
    <div class="modal fade" id="generateReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Generate Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../backend/generate_report.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="trips">Trip Summary</option>
                                <option value="fuel">Fuel Consumption</option>
                                <option value="maintenance">Maintenance History</option>
                                <option value="driver_performance">Driver Performance</option>
                                <option value="financial">Financial Overview</option>
                                <option value="vehicle_utilization">Vehicle Utilization</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-select" name="format" required>
                                <option value="pdf">PDF Document</option>
                                <option value="excel">Excel Spreadsheet</option>
                                <option value="csv">CSV File</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_charts" id="includeCharts">
                                <label class="form-check-label" for="includeCharts">
                                    Include charts and visualizations
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Generate Report</button>
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
            const body = document.body;
            const isActive = sidebar.classList.toggle('active');
            overlay.classList.toggle('active', isActive);
            body.classList.toggle('sidebar-open', isActive);
        }
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const body = document.body;
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            body.classList.remove('sidebar-open');
        }
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });

            // Show success/error messages
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                let message = '';
                switch(success) {
                    case 'trip_created':
                        message = 'Trip created successfully!';
                        break;
                    case 'driver_added':
                        message = 'Driver added successfully!';
                        break;
                    case 'fuel_logged':
                        message = 'Fuel entry logged successfully!';
                        break;
                    case 'report_generated':
                        message = 'Report generated successfully!';
                        break;
                }
                if (message) {
                    showNotification(message, 'success');
                }
            }
            
            if (error) {
                let message = '';
                switch(error) {
                    case 'missing_fields':
                        message = 'Please fill in all required fields.';
                        break;
                    case 'trip_failed':
                        message = 'Failed to create trip. Please try again.';
                        break;
                    case 'email_exists':
                        message = 'Email already exists. Please use a different email.';
                        break;
                    case 'driver_failed':
                        message = 'Failed to add driver. Please try again.';
                        break;
                    case 'invalid_fuel_data':
                        message = 'Invalid fuel data. Please check your inputs.';
                        break;
                    case 'fuel_failed':
                        message = 'Failed to log fuel entry. Please try again.';
                        break;
                    case 'invalid_report_params':
                        message = 'Invalid report parameters. Please check your inputs.';
                        break;
                    case 'report_failed':
                        message = 'Failed to generate report. Please try again.';
                        break;
                }
                if (message) {
                    showNotification(message, 'error');
                }
            }
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>
</body>
</html>