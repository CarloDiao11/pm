<?php
// fuel.php - Dynamic Fuel & Consumables with Search and Filters
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Helper: Format as Peso
function formatPeso($amount) {
    return '₱' . number_format($amount, 0);
}

// Helper: Format liters
function formatLiters($liters) {
    return number_format($liters, 1) . ' L';
}

// --- Summary Stats ---
$totalFuelLogs = db_count('fuel_logs');
$totalFuelCost = (float) db_select_advanced("
    SELECT COALESCE(SUM(cost), 0) as total FROM fuel_logs
")[0]['total'] ?? 0;
$totalConsumables = db_count('consumables');
$lowStockCount = db_count('consumables', ['stock_qty <= min_threshold']);

// --- Current Tab & Filters ---
$tab = $_GET['tab'] ?? 'fuel-logs';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$limit = 8;
$offset = ($page - 1) * $limit;

// Fuel Logs Filters
$fuelDateFrom = $_GET['fuel_date_from'] ?? '';
$fuelDateTo = $_GET['fuel_date_to'] ?? '';
$fuelVehicle = $_GET['fuel_vehicle'] ?? '';
$fuelDriver = $_GET['fuel_driver'] ?? '';

// Consumable Logs Filters
$consDateFrom = $_GET['cons_date_from'] ?? '';
$consDateTo = $_GET['cons_date_to'] ?? '';
$consType = $_GET['cons_type'] ?? '';
$consRequester = $_GET['cons_requester'] ?? '';

// Inventory Filters
$invCategory = $_GET['inv_category'] ?? '';
$invStatus = $_GET['inv_status'] ?? '';

// --- Get filter options for dropdowns ---
$vehicles = db_select('vehicles', [], ['columns' => 'vehicle_id, plate_number, type']);
$drivers = db_select_advanced("
    SELECT u.user_id, u.name 
    FROM users u 
    JOIN drivers d ON u.user_id = d.user_id 
    WHERE u.role = 'driver' AND u.status = 'active'
");
$categories = db_select_advanced("SELECT DISTINCT category FROM consumables ORDER BY category");
$usageTypes = ['maintenance', 'repair', 'replacement', 'emergency', 'routine'];

// --- Fuel Logs Query ---
$fuelLogs = [];
$fuelTotal = 0;
if ($tab === 'fuel-logs') {
    $params = [];
    $where = [];
    
    if ($search) {
        $where[] = "(v.plate_number LIKE ? OR v.type LIKE ? OR u.name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    if ($fuelDateFrom) {
        $where[] = "DATE(f.date) >= ?";
        $params[] = $fuelDateFrom;
    }
    if ($fuelDateTo) {
        $where[] = "DATE(f.date) <= ?";
        $params[] = $fuelDateTo;
    }
    if ($fuelVehicle) {
        $where[] = "f.vehicle_id = ?";
        $params[] = $fuelVehicle;
    }
    if ($fuelDriver) {
        $where[] = "f.driver_id = ?";
        $params[] = $fuelDriver;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $fuelLogs = db_select_advanced("
        SELECT 
            f.fuel_id,
            f.liters,
            f.cost,
            f.date,
            f.station_name,
            v.plate_number,
            v.type AS vehicle_type,
            u.name AS driver_name,
            f.driver_id,
            f.vehicle_id
        FROM fuel_logs f
        JOIN vehicles v ON f.vehicle_id = v.vehicle_id
        JOIN drivers d ON f.driver_id = d.drivers_id
        JOIN users u ON d.user_id = u.user_id
        $whereClause
        ORDER BY f.date DESC
        LIMIT $limit OFFSET $offset
    ", $params);
    
    $fuelTotal = db_select_advanced("
        SELECT COUNT(*) as total FROM fuel_logs f
        JOIN vehicles v ON f.vehicle_id = v.vehicle_id
        JOIN drivers d ON f.driver_id = d.drivers_id
        JOIN users u ON d.user_id = u.user_id
        $whereClause
    ", $params)[0]['total'] ?? 0;
}

// --- Consumable Logs Query ---
$consumableLogs = [];
$consumableTotal = 0;
if ($tab === 'consumable-logs') {
    $params = [];
    $where = [];
    
    if ($search) {
        $where[] = "(c.item_name LIKE ? OR u.name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }
    if ($consDateFrom) {
        $where[] = "DATE(cl.date) >= ?";
        $params[] = $consDateFrom;
    }
    if ($consDateTo) {
        $where[] = "DATE(cl.date) <= ?";
        $params[] = $consDateTo;
    }
    if ($consType) {
        $where[] = "cl.usage_type = ?";
        $params[] = $consType;
    }
    if ($consRequester) {
        $where[] = "cl.requested_by = ?";
        $params[] = $consRequester;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $consumableLogs = db_select_advanced("
        SELECT 
            cl.log_id,
            cl.quantity,
            cl.date,
            cl.usage_type,
            cl.purpose,
            c.item_name,
            u.name AS requester_name,
            v.plate_number,
            v.type AS vehicle_type,
            cl.requested_by
        FROM consumable_logs cl
        JOIN consumables c ON cl.consumable_id = c.consumable_id
        JOIN users u ON cl.requested_by = u.user_id
        LEFT JOIN vehicles v ON cl.vehicle_id = v.vehicle_id
        $whereClause
        ORDER BY cl.date DESC
        LIMIT $limit OFFSET $offset
    ", $params);
    
    $consumableTotal = db_select_advanced("
        SELECT COUNT(*) as total FROM consumable_logs cl
        JOIN consumables c ON cl.consumable_id = c.consumable_id
        JOIN users u ON cl.requested_by = u.user_id
        LEFT JOIN vehicles v ON cl.vehicle_id = v.vehicle_id
        $whereClause
    ", $params)[0]['total'] ?? 0;
}

// --- Inventory Query ---
$inventoryItems = [];
$inventoryTotal = 0;
if ($tab === 'inventory') {
    $params = [];
    $where = [];
    
    if ($search) {
        $where[] = "(item_name LIKE ? OR category LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }
    if ($invCategory) {
        $where[] = "category = ?";
        $params[] = $invCategory;
    }
    if ($invStatus === 'low') {
        $where[] = "stock_qty <= min_threshold";
    } elseif ($invStatus === 'ok') {
        $where[] = "stock_qty > min_threshold";
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $inventoryItems = db_select_advanced("
        SELECT 
            consumable_id,
            item_name,
            category,
            stock_qty,
            min_threshold,
            unit_price
        FROM consumables
        $whereClause
        ORDER BY 
            CASE WHEN stock_qty <= min_threshold THEN 0 ELSE 1 END,
            stock_qty ASC
        LIMIT $limit OFFSET $offset
    ", $params);
    
    $inventoryTotal = db_select_advanced("
        SELECT COUNT(*) as total FROM consumables $whereClause
    ", $params)[0]['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel & Consumables - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/fuel.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .filter-section {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        .filter-section.active {
            display: block;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="page-title-wrapper">
                <h1 class="page-title">Fuel & Consumables</h1>
                <p class="page-subtitle">Manage fuel logs, consumable usage, and track inventory levels</p>
            </div>
            <div class="page-actions">
                <button class="btn-action btn-black" data-bs-toggle="modal" data-bs-target="#addFuelModal">
                    <i class="bi bi-fuel-pump"></i>
                    <span>Add Fuel Log</span>
                </button>
                <button class="btn-action btn-black" data-bs-toggle="modal" data-bs-target="#addConsumableModal">
                    <i class="bi bi-box-seam"></i>
                    <span>Add Consumable</span>
                </button>
                <button class="btn-action btn-black" data-bs-toggle="modal" data-bs-target="#addConsumableLogModal">
                    <i class="bi bi-journal-plus"></i>
                    <span>Log Usage</span>
                </button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <div class="summary-icon fuel">
                        <i class="bi bi-fuel-pump"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label">Total Fuel Logs</div>
                        <div class="summary-value"><?= $totalFuelLogs ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <div class="summary-icon cost">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label">Total Fuel Cost</div>
                        <div class="summary-value"><?= formatPeso($totalFuelCost) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <div class="summary-icon items">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label">Consumable Items</div>
                        <div class="summary-value"><?= $totalConsumables ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card alert-card">
                    <div class="summary-icon alert">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label">Low Stock Alerts</div>
                        <div class="summary-value"><?= $lowStockCount ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="content-tabs">
            <ul class="nav nav-pills custom-tabs" id="contentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab === 'fuel-logs' ? 'active' : '' ?>" 
                            data-bs-toggle="pill" data-bs-target="#fuel-logs" type="button" role="tab"
                            onclick="setTab('fuel-logs')">
                        <i class="bi bi-fuel-pump-fill"></i> Fuel Logs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab === 'consumable-logs' ? 'active' : '' ?>" 
                            data-bs-toggle="pill" data-bs-target="#consumable-logs" type="button" role="tab"
                            onclick="setTab('consumable-logs')">
                        <i class="bi bi-journal-text"></i> Usage Logs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab === 'inventory' ? 'active' : '' ?>" 
                            data-bs-toggle="pill" data-bs-target="#inventory" type="button" role="tab"
                            onclick="setTab('inventory')">
                        <i class="bi bi-box-seam-fill"></i> Inventory
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="contentTabsContent">
            
            <!-- Fuel Logs Tab -->
            <div class="tab-pane fade <?= $tab === 'fuel-logs' ? 'show active' : '' ?>" id="fuel-logs" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-wrapper">
                            <h2 class="section-title"><i class="bi bi-fuel-pump-fill"></i> Fuel Consumption Logs</h2>
                            <span class="section-subtitle">Track all fuel consumption and costs across your fleet</span>
                        </div>
                        <div class="section-actions">
                            <form method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="tab" value="fuel-logs">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" name="search" placeholder="Search fuel logs..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <button type="button" class="btn-filter" onclick="toggleFilter('fuel')">
                                    <i class="bi bi-funnel"></i>
                                    <span>Filter</span>
                                </button>
                                <button class="btn-export">
                                    <i class="bi bi-download"></i>
                                    <span>Export</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Fuel Filter Section -->
                    <div class="filter-section <?= ($fuelDateFrom || $fuelDateTo || $fuelVehicle || $fuelDriver) ? 'active' : '' ?>" id="fuelFilterSection">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>From Date</label>
                                <input type="date" name="fuel_date_from" class="form-control" 
                                       value="<?= htmlspecialchars($fuelDateFrom) ?>">
                            </div>
                            <div class="filter-group">
                                <label>To Date</label>
                                <input type="date" name="fuel_date_to" class="form-control" 
                                       value="<?= htmlspecialchars($fuelDateTo) ?>">
                            </div>
                            <div class="filter-group">
                                <label>Vehicle</label>
                                <select name="fuel_vehicle" class="form-select">
                                    <option value="">All Vehicles</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['vehicle_id'] ?>" <?= $fuelVehicle === $v['vehicle_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v['type'] . ' (' . $v['plate_number'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Driver</label>
                                <select name="fuel_driver" class="form-select">
                                    <option value="">All Drivers</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?= $d['user_id'] ?>" <?= $fuelDriver === $d['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary" form="fuelFilterForm">Apply Filters</button>
                            <a href="?tab=fuel-logs" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </div>
                    <form id="fuelFilterForm" method="GET" style="display:none;">
                        <input type="hidden" name="tab" value="fuel-logs">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="fuel_date_from" id="fuel_date_from_hidden">
                        <input type="hidden" name="fuel_date_to" id="fuel_date_to_hidden">
                        <input type="hidden" name="fuel_vehicle" id="fuel_vehicle_hidden">
                        <input type="hidden" name="fuel_driver" id="fuel_driver_hidden">
                    </form>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>Vehicle</th>
                                    <th>Driver</th>
                                    <th>Liters</th>
                                    <th>Cost</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($fuelLogs)): ?>
                                    <tr><td colspan="7" style="text-align:center;padding:20px;">No fuel logs found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fuelLogs as $log): ?>
                                    <tr>
                                        <td><span class="log-id">#<?= htmlspecialchars(substr($log['fuel_id'], 0, 6)) ?></span></td>
                                        <td>
                                            <div class="vehicle-info">
                                                <div class="vehicle-icon"><i class="bi bi-truck"></i></div>
                                                <div class="vehicle-details">
                                                    <span class="vehicle-name"><?= htmlspecialchars($log['vehicle_type']) ?></span>
                                                    <span class="vehicle-plate"><?= htmlspecialchars($log['plate_number']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="driver-name"><?= htmlspecialchars($log['driver_name']) ?></span></td>
                                        <td><span class="fuel-amount"><?= formatLiters($log['liters']) ?></span></td>
                                        <td><span class="cost-value"><?= formatPeso($log['cost']) ?></span></td>
                                        <td>
                                            <div class="date-time">
                                                <span class="date-text"><?= date('M j, Y', strtotime($log['date'])) ?></span>
                                                <span class="time-text"><?= date('g:i A', strtotime($log['date'])) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action-icon" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn-action-icon" title="Edit">
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
                    
                    <div class="table-pagination">
                        <div class="pagination-info">
                            Showing <strong><?= $offset + 1 ?>-<?= min($offset + $limit, $fuelTotal) ?></strong> of <strong><?= $fuelTotal ?></strong> fuel logs
                        </div>
                        <div class="pagination-controls">
                            <?php for ($i = 1; $i <= ceil($fuelTotal / $limit); $i++): ?>
                                <a href="?tab=fuel-logs&page=<?= $i ?>&search=<?= urlencode($search) ?>&fuel_date_from=<?= urlencode($fuelDateFrom) ?>&fuel_date_to=<?= urlencode($fuelDateTo) ?>&fuel_vehicle=<?= urlencode($fuelVehicle) ?>&fuel_driver=<?= urlencode($fuelDriver) ?>" 
                                   class="btn-page <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consumable Usage Logs Tab -->
            <div class="tab-pane fade <?= $tab === 'consumable-logs' ? 'show active' : '' ?>" id="consumable-logs" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-wrapper">
                            <h2 class="section-title"><i class="bi bi-journal-text"></i> Consumable Usage Logs</h2>
                            <span class="section-subtitle">Track when consumables are used and by whom</span>
                        </div>
                        <div class="section-actions">
                            <form method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="tab" value="consumable-logs">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" name="search" placeholder="Search usage logs..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <button type="button" class="btn-filter" onclick="toggleFilter('cons')">
                                    <i class="bi bi-funnel"></i>
                                    <span>Filter</span>
                                </button>
                                <button class="btn-export">
                                    <i class="bi bi-download"></i>
                                    <span>Export</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Consumable Filter Section -->
                    <div class="filter-section <?= ($consDateFrom || $consDateTo || $consType || $consRequester) ? 'active' : '' ?>" id="consFilterSection">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>From Date</label>
                                <input type="date" name="cons_date_from" class="form-control" 
                                       value="<?= htmlspecialchars($consDateFrom) ?>">
                            </div>
                            <div class="filter-group">
                                <label>To Date</label>
                                <input type="date" name="cons_date_to" class="form-control" 
                                       value="<?= htmlspecialchars($consDateTo) ?>">
                            </div>
                            <div class="filter-group">
                                <label>Usage Type</label>
                                <select name="cons_type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach ($usageTypes as $type): ?>
                                        <option value="<?= $type ?>" <?= $consType === $type ? 'selected' : '' ?>>
                                            <?= ucfirst($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Requested By</label>
                                <select name="cons_requester" class="form-select">
                                    <option value="">All Users</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?= $d['user_id'] ?>" <?= $consRequester === $d['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary" form="consFilterForm">Apply Filters</button>
                            <a href="?tab=consumable-logs" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </div>
                    <form id="consFilterForm" method="GET" style="display:none;">
                        <input type="hidden" name="tab" value="consumable-logs">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="cons_date_from" id="cons_date_from_hidden">
                        <input type="hidden" name="cons_date_to" id="cons_date_to_hidden">
                        <input type="hidden" name="cons_type" id="cons_type_hidden">
                        <input type="hidden" name="cons_requester" id="cons_requester_hidden">
                    </form>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Requested By</th>
                                    <th>Vehicle/Purpose</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consumableLogs)): ?>
                                    <tr><td colspan="8" style="text-align:center;padding:20px;">No usage logs found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($consumableLogs as $log): ?>
                                    <tr>
                                        <td><span class="log-id">#<?= htmlspecialchars(substr($log['log_id'], 0, 6)) ?></span></td>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-icon usage"><i class="bi bi-box"></i></div>
                                                <span class="item-name"><?= htmlspecialchars($log['item_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="usage-type <?= $log['usage_type'] ?>"><?= ucfirst($log['usage_type']) ?></span></td>
                                        <td><span class="quantity-used"><?= (int)$log['quantity'] ?> units</span></td>
                                        <td><span class="requester-name"><?= htmlspecialchars($log['requester_name']) ?></span></td>
                                        <td>
                                            <div class="purpose-info">
                                                <span class="vehicle-ref"><?= $log['plate_number'] ? $log['vehicle_type'] . ' (' . $log['plate_number'] . ')' : 'N/A' ?></span>
                                                <span class="purpose-desc"><?= htmlspecialchars($log['purpose']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-time">
                                                <span class="date-text"><?= date('M j, Y', strtotime($log['date'])) ?></span>
                                                <span class="time-text"><?= date('g:i A', strtotime($log['date'])) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action-icon" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn-action-icon" title="Edit">
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
                    
                    <div class="table-pagination">
                        <div class="pagination-info">
                            Showing <strong><?= $offset + 1 ?>-<?= min($offset + $limit, $consumableTotal) ?></strong> of <strong><?= $consumableTotal ?></strong> usage logs
                        </div>
                        <div class="pagination-controls">
                            <?php for ($i = 1; $i <= ceil($consumableTotal / $limit); $i++): ?>
                                <a href="?tab=consumable-logs&page=<?= $i ?>&search=<?= urlencode($search) ?>&cons_date_from=<?= urlencode($consDateFrom) ?>&cons_date_to=<?= urlencode($consDateTo) ?>&cons_type=<?= urlencode($consType) ?>&cons_requester=<?= urlencode($consRequester) ?>" 
                                   class="btn-page <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div class="tab-pane fade <?= $tab === 'inventory' ? 'show active' : '' ?>" id="inventory" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-wrapper">
                            <h2 class="section-title"><i class="bi bi-box-seam-fill"></i> Consumables Inventory</h2>
                            <span class="section-subtitle">Monitor stock levels and manage inventory</span>
                        </div>
                        <div class="section-actions">
                            <form method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="tab" value="inventory">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" name="search" placeholder="Search items..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <button type="button" class="btn-filter" onclick="toggleFilter('inv')">
                                    <i class="bi bi-funnel"></i>
                                    <span>Filter</span>
                                </button>
                                <button class="btn-export">
                                    <i class="bi bi-download"></i>
                                    <span>Export</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Inventory Filter Section -->
                    <div class="filter-section <?= ($invCategory || $invStatus) ? 'active' : '' ?>" id="invFilterSection">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Category</label>
                                <select name="inv_category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $invCategory === $cat['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="inv_status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="low" <?= $invStatus === 'low' ? 'selected' : '' ?>>Low Stock</option>
                                    <option value="ok" <?= $invStatus === 'ok' ? 'selected' : '' ?>>In Stock</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary" form="invFilterForm">Apply Filters</button>
                            <a href="?tab=inventory" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </div>
                    <form id="invFilterForm" method="GET" style="display:none;">
                        <input type="hidden" name="tab" value="inventory">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="inv_category" id="inv_category_hidden">
                        <input type="hidden" name="inv_status" id="inv_status_hidden">
                    </form>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Stock Qty</th>
                                    <th>Min Threshold</th>
                                    <th>Unit Price</th>
                                    <th>Total Value</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventoryItems)): ?>
                                    <tr><td colspan="8" style="text-align:center;padding:20px;">No items found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($inventoryItems as $item): ?>
                                    <?php
                                    $isLow = $item['stock_qty'] <= $item['min_threshold'];
                                    $totalValue = $item['stock_qty'] * $item['unit_price'];
                                    ?>
                                    <tr <?= $isLow ? 'class="low-stock-row"' : '' ?>>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-icon <?= $isLow ? 'low-stock' : '' ?>">
                                                    <i class="bi <?= $isLow ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?>"></i>
                                                </div>
                                                <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="category-badge"><?= htmlspecialchars($item['category']) ?></span></td>
                                        <td><span class="stock-qty <?= $isLow ? 'low' : '' ?>"><?= (int)$item['stock_qty'] ?></span></td>
                                        <td><span class="threshold-qty"><?= (int)$item['min_threshold'] ?></span></td>
                                        <td><span class="price-value"><?= formatPeso($item['unit_price']) ?></span></td>
                                        <td><span class="total-value"><?= formatPeso($totalValue) ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?= $isLow ? 'low' : 'ok' ?>">
                                                <span class="status-dot"></span>
                                                <?= $isLow ? 'Low Stock' : 'In Stock' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($isLow): ?>
                                                    <button class="btn-action-icon restock" title="Restock">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-action-icon" title="Edit">
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
                    
                    <div class="table-pagination">
                        <div class="pagination-info">
                            Showing <strong><?= $offset + 1 ?>-<?= min($offset + $limit, $inventoryTotal) ?></strong> of <strong><?= $inventoryTotal ?></strong> items
                        </div>
                        <div class="pagination-controls">
                            <?php for ($i = 1; $i <= ceil($inventoryTotal / $limit); $i++): ?>
                                <a href="?tab=inventory&page=<?= $i ?>&search=<?= urlencode($search) ?>&inv_category=<?= urlencode($invCategory) ?>&inv_status=<?= urlencode($invStatus) ?>" 
                                   class="btn-page <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="overlay" id="overlay"></div>
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
        document.getElementById('overlay').addEventListener('click', toggleSidebar);

        function setTab(tab) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            url.searchParams.delete('page');
            window.history.pushState({}, '', url);
        }

        function toggleFilter(type) {
            const section = document.getElementById(type + 'FilterSection');
            section.classList.toggle('active');
        }

        // Handle filter form submission
        document.querySelectorAll('.filter-actions .btn-primary').forEach((btn, index) => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const type = ['fuel', 'cons', 'inv'][index];
                const form = document.getElementById(type + 'FilterForm');
                
                // Copy values from visible inputs to hidden inputs
                const inputs = document.querySelectorAll(`[name^="${type}_"]`);
                inputs.forEach(input => {
                    const hidden = document.getElementById(input.name + '_hidden');
                    if (hidden) {
                        hidden.value = input.value;
                    }
                });
                
                form.submit();
            });
        });
    </script>
</body>
</html>