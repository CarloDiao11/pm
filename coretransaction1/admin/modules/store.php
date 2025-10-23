<?php
// store.php - Dynamic Store Room & Supplies Page
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Helper: Format as Peso
function formatPeso($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper: Get stock status
function getStockStatus($qty, $threshold) {
    if ($qty <= 0) return ['class' => 'critical', 'text' => 'Out of Stock'];
    if ($qty <= $threshold * 0.3) return ['class' => 'critical', 'text' => 'Critical'];
    if ($qty <= $threshold) return ['class' => 'low', 'text' => 'Low Stock'];
    return ['class' => 'good', 'text' => 'In Stock'];
}

// Helper: Time ago
function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60 * 60 * 24) return 'Today';
    if ($diff < 60 * 60 * 24 * 7) return floor($diff / (60 * 60 * 24)) . ' days ago';
    if ($diff < 60 * 60 * 24 * 30) return floor($diff / (60 * 60 * 24 * 7)) . ' weeks ago';
    return floor($diff / (60 * 60 * 24 * 30)) . ' months ago';
}

// --- Pagination & Filtering ---
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$limit = 8;
$offset = ($page - 1) * $limit;

// Get categories for filter dropdown
$categories = db_select_advanced("SELECT DISTINCT category FROM consumables ORDER BY category");

// Build query
$params = [];
$where = [];

if ($search) {
    $where[] = "(item_name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}
if ($status === 'low') {
    $where[] = "stock_qty <= min_threshold AND stock_qty > 0";
} elseif ($status === 'critical') {
    $where[] = "stock_qty <= min_threshold * 0.3 OR stock_qty <= 0";
} elseif ($status === 'good') {
    $where[] = "stock_qty > min_threshold";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch items
$items = db_select_advanced("
    SELECT 
        consumable_id,
        item_name,
        category,
        stock_qty,
        min_threshold,
        unit_price,
        created_at,
        updated_at
    FROM consumables
    $whereClause
    ORDER BY 
        CASE WHEN stock_qty <= min_threshold THEN 0 ELSE 1 END,
        stock_qty ASC
    LIMIT $limit OFFSET $offset
", $params);

// Get total count for pagination
$total = db_select_advanced("
    SELECT COUNT(*) as total FROM consumables $whereClause
", $params)[0]['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Room & Supplies - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/store.css">
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
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="store-header">
            <div class="store-title-section">
                <h1 class="page-title">Store Room & Supplies</h1>
                <p class="page-subtitle">Manage inventory and track stock levels</p>
            </div>
            <div class="store-actions">
                <button class="btn-store btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle"></i>
                    Add Item
                </button>
                <button class="btn-store btn-success" data-bs-toggle="modal" data-bs-target="#stockInModal">
                    <i class="bi bi-arrow-down-circle"></i>
                    Stock In
                </button>
                <button class="btn-store btn-warning" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                    <i class="bi bi-arrow-up-circle"></i>
                    Stock Out
                </button>
            </div>
        </div>

        <div class="store-card">
            <div class="store-card-header">
                <div class="card-title-section">
                    <h2 class="card-title">
                        <i class="bi bi-boxes"></i>
                        Inventory Items
                    </h2>
                    <span class="card-subtitle">Current stock levels and item details</span>
                </div>
                <div class="card-actions">
                    <form method="GET" class="d-flex align-items-center">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" placeholder="Search items..." 
                                   class="search-input" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="button" class="btn-card-action" onclick="toggleFilter()">
                            <i class="bi bi-funnel"></i>
                            Filter
                        </button>
                        <button class="btn-card-action">
                            <i class="bi bi-download"></i>
                            Export
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section <?= ($category || $status) ? 'active' : '' ?>" id="filterSection">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category" class="form-select" id="filterCategory">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="good" <?= $status === 'good' ? 'selected' : '' ?>>In Stock</option>
                            <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="critical" <?= $status === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions mt-3">
                    <button type="submit" class="btn btn-primary" form="filterForm">Apply Filters</button>
                    <a href="?page=1" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
            <form id="filterForm" method="GET" style="display:none;">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="category" id="hiddenCategory">
                <input type="hidden" name="status" id="hiddenStatus">
            </form>

            <div class="table-wrapper">
                <table class="store-table">
                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Min Threshold</th>
                            <th>Last Restock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">No items found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                            <?php
                            $statusInfo = getStockStatus($item['stock_qty'], $item['min_threshold']);
                            $lastRestock = $item['updated_at'] ?? $item['created_at'];
                            ?>
                            <tr class="<?= $statusInfo['class'] === 'low' ? 'low-stock' : ($statusInfo['class'] === 'critical' ? 'critical-stock' : '') ?>">
                                <td><span class="item-id">#<?= htmlspecialchars(substr($item['consumable_id'], 0, 6)) ?></span></td>
                                <td>
                                    <div class="item-details">
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-category"><?= htmlspecialchars($item['category']) ?></span>
                                    </div>
                                </td>
                                <td><span class="category-tag"><?= htmlspecialchars($item['category']) ?></span></td>
                                <td><span class="quantity-value"><?= (int)$item['stock_qty'] ?></span></td>
                                <td><span class="threshold-value"><?= (int)$item['min_threshold'] ?></span></td>
                                <td>
                                    <div class="date-info">
                                        <span class="restock-date"><?= date('M j, Y', strtotime($lastRestock)) ?></span>
                                        <span class="restock-time"><?= timeAgo($lastRestock) ?></span>
                                    </div>
                                </td>
                                <td><span class="status-badge status-<?= $statusInfo['class'] ?>"><?= $statusInfo['text'] ?></span></td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn-action" title="View Details"
                                            onclick="window.location='item-view.php?id=<?= urlencode($item['consumable_id']) ?>'">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn-action" title="Edit Item"
                                            onclick="window.location='item-edit.php?id=<?= urlencode($item['consumable_id']) ?>'">
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

            <div class="table-footer">
                <div class="footer-info">
                    Showing <strong><?= $offset + 1 ?>-<?= min($offset + $limit, $total) ?></strong> of <strong><?= $total ?></strong> items
                </div>
                <div class="pagination-wrapper">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="page-btn">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <button class="page-btn" disabled><i class="bi bi-chevron-left"></i></button>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min(ceil($total / $limit), $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" 
                           class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < ceil($total / $limit)): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="page-btn">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="page-btn" disabled><i class="bi bi-chevron-right"></i></button>
                    <?php endif; ?>
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

        function toggleFilter() {
            document.getElementById('filterSection').classList.toggle('active');
        }

        // Handle filter form submission
        document.querySelector('.filter-actions .btn-primary').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('hiddenCategory').value = document.getElementById('filterCategory').value;
            document.getElementById('hiddenStatus').value = document.getElementById('filterStatus').value;
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>