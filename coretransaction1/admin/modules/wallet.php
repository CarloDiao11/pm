<?php
// wallet.php - Dynamic Wallet with Search, Filter, and Live Stats
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Helper: Format as Peso
function formatPeso($amount) {
    return '₱' . number_format(abs($amount), 0);
}

// Helper: Get avatar initials
function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    } else {
        $initials .= strtoupper(substr($name, 1, 1));
    }
    return $initials;
}

// --- KPIs ---
$firstDayThisMonth = date('Y-m-01');

$totalEarnings = (float) db_select_advanced("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN driver_wallets w ON t.wallet_id = w.wallet_id
    WHERE t.type = 'credit' 
      AND t.status = 'completed'
      AND DATE(t.created_at) >= ?
", [$firstDayThisMonth])[0]['total'] ?? 0;

$pendingPayouts = (float) db_select_advanced("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE type = 'debit' AND status = 'pending'
")[0]['total'] ?? 0;

$completedPayouts = (float) db_select_advanced("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    WHERE t.type = 'debit' 
      AND t.status = 'completed'
      AND DATE(t.created_at) >= ?
", [$firstDayThisMonth])[0]['total'] ?? 0;

// Count of completed payout transactions this month
$completedCount = (int) db_select_advanced("
    SELECT COUNT(*) as count 
    FROM transactions t
    WHERE t.type = 'debit' 
      AND t.status = 'completed'
      AND DATE(t.created_at) >= ?
", [$firstDayThisMonth])[0]['count'] ?? 0;

// Save snapshot
$existingSnapshot = db_select('wallet_snapshots', ['snapshot_month' => $firstDayThisMonth]);
if (empty($existingSnapshot)) {
    db_insert('wallet_snapshots', [
        'snapshot_id' => bin2hex(random_bytes(16)),
        'snapshot_month' => $firstDayThisMonth,
        'total_earnings' => $totalEarnings,
        'pending_payouts' => $pendingPayouts,
        'completed_payouts' => $completedPayouts
    ]);
}

// % Change
$firstDayLastMonth = date('Y-m-01', strtotime('-1 month'));
$lastMonthData = db_select('wallet_snapshots', ['snapshot_month' => $firstDayLastMonth]);
$lastMonthEarnings = $lastMonthData ? (float)$lastMonthData[0]['total_earnings'] : 0;

if ($lastMonthEarnings == 0) {
    $percentChange = $totalEarnings > 0 ? 100 : 0;
} else {
    $percentChange = round((($totalEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100, 1);
}
$earningsTrend = ($percentChange >= 0 ? '+' : '') . $percentChange . '% from last month';
$trendClass = $percentChange > 0 ? 'trend-up' : ($percentChange < 0 ? 'trend-down' : 'trend-neutral');

// --- Search & Filter ---
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

// Build dynamic query
$params = [];
$conditions = [];

if ($search) {
    $conditions[] = "(u.name LIKE ? OR t.transaction_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($typeFilter) {
    $conditions[] = "t.type = ?";
    $params[] = $typeFilter;
}
if ($statusFilter) {
    $conditions[] = "t.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total 
    FROM transactions t
    JOIN driver_wallets w ON t.wallet_id = w.wallet_id
    JOIN drivers d ON w.driver_id = d.drivers_id
    JOIN users u ON d.user_id = u.user_id AND u.role = 'driver'
    $whereClause
";
$totalRecords = (int) db_select_advanced($countSql, $params)[0]['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch transactions
$transactions = db_select_advanced("
    SELECT 
        t.transaction_id,
        t.amount,
        t.type,
        t.status,
        t.created_at,
        u.name AS driver_name,
        u.profile_picture,
        d.drivers_id
    FROM transactions t
    JOIN driver_wallets w ON t.wallet_id = w.wallet_id
    JOIN drivers d ON w.driver_id = d.drivers_id
    JOIN users u ON d.user_id = u.user_id AND u.role = 'driver'
    $whereClause
    ORDER BY t.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet & Earnings - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/wallet.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .filter-dropdown {
            position: relative;
            display: inline-block;
        }
        .filter-menu {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 180px;
            padding: 12px;
            margin-top: 4px;
        }
        .filter-menu.active {
            display: block;
        }
        .filter-item {
            padding: 6px 0;
            cursor: pointer;
            border-radius: 4px;
        }
        .filter-item:hover {
            background-color: #f8f9fa;
        }
        .filter-item.active {
            color: #0d6efd;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Wallet & Earnings</h1>
            <div class="page-actions">
                <button class="btn-action btn-secondary">
                    <i class="bi bi-download"></i>
                    <span>Export Report</span>
                </button>
                <button class="btn-action btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    <span>Request Payout</span>
                </button>
            </div>
        </div>
        
        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="wallet-kpi-card earnings-card">
                    <div class="kpi-icon-wrapper">
                        <div class="wallet-kpi-icon total-earnings">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <div class="wallet-kpi-content">
                        <div class="wallet-kpi-label">Total Earnings</div>
                        <div class="wallet-kpi-value"><?= formatPeso($totalEarnings) ?></div>
                        <div class="wallet-kpi-trend <?= $trendClass ?>">
                            <i class="bi bi-arrow-<?= $percentChange > 0 ? 'up' : ($percentChange < 0 ? 'down' : 'right') ?>-circle-fill"></i>
                            <span><?= htmlspecialchars($earningsTrend) ?></span>
                        </div>
                    </div>
                    <div class="card-corner-accent"></div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="wallet-kpi-card pending-card">
                    <div class="kpi-icon-wrapper">
                        <div class="wallet-kpi-icon pending-payouts">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="wallet-kpi-content">
                        <div class="wallet-kpi-label">Pending Payouts</div>
                        <div class="wallet-kpi-value"><?= formatPeso($pendingPayouts) ?></div>
                        <div class="wallet-kpi-trend trend-warning">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <span><?= count(array_filter($transactions, fn($t) => $t['status'] === 'pending')) ?> transactions awaiting</span>
                        </div>
                    </div>
                    <div class="card-corner-accent"></div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="wallet-kpi-card completed-card">
                    <div class="kpi-icon-wrapper">
                        <div class="wallet-kpi-icon completed-payouts">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="wallet-kpi-content">
                        <div class="wallet-kpi-label">Completed Payouts</div>
                        <div class="wallet-kpi-value"><?= formatPeso($completedPayouts) ?></div>
                        <div class="wallet-kpi-trend trend-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <span><?= $completedCount ?> completed this month</span>
                        </div>
                    </div>
                    <div class="card-corner-accent"></div>
                </div>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <div class="transactions-section">
            <div class="section-header">
                <div class="section-title-wrapper">
                    <h2 class="section-title">Recent Transactions</h2>
                    <span class="section-subtitle">View and manage all wallet transactions</span>
                </div>
                <div class="section-actions">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <form method="GET" style="display:inline;">
                            <input type="text" name="search" placeholder="Search transactions..." 
                                   value="<?= htmlspecialchars($search) ?>"
                                   style="border: none; outline: none; width: 200px; background: transparent;">
                        </form>
                    </div>
                    
                    <!-- Type Filter -->
                    <div class="filter-dropdown" id="typeFilter">
                        <button class="btn-filter">
                            <i class="bi bi-funnel"></i>
                            <span>Type: <?= $typeFilter ?: 'All' ?></span>
                        </button>
                        <div class="filter-menu" id="typeMenu">
                            <div class="filter-item <?= !$typeFilter ? 'active' : '' ?>" data-value="">All Types</div>
                            <div class="filter-item <?= $typeFilter === 'credit' ? 'active' : '' ?>" data-value="credit">Earning</div>
                            <div class="filter-item <?= $typeFilter === 'debit' ? 'active' : '' ?>" data-value="debit">Payout</div>
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="filter-dropdown" id="statusFilter">
                        <button class="btn-filter">
                            <i class="bi bi-funnel"></i>
                            <span>Status: <?= $statusFilter ?: 'All' ?></span>
                        </button>
                        <div class="filter-menu" id="statusMenu">
                            <div class="filter-item <?= !$statusFilter ? 'active' : '' ?>" data-value="">All Status</div>
                            <div class="filter-item <?= $statusFilter === 'completed' ? 'active' : '' ?>" data-value="completed">Completed</div>
                            <div class="filter-item <?= $statusFilter === 'pending' ? 'active' : '' ?>" data-value="pending">Pending</div>
                            <div class="filter-item <?= $statusFilter === 'failed' ? 'active' : '' ?>" data-value="failed">Failed</div>
                            <div class="filter-item <?= $statusFilter === 'processing' ? 'active' : '' ?>" data-value="processing">Processing</div>
                        </div>
                    </div>
                    
                    <button class="btn-export">
                        <i class="bi bi-download"></i>
                        <span>Export</span>
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Driver</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <?php
                            $isCredit = $t['type'] === 'credit';
                            $amountFormatted = ($isCredit ? '+' : '-') . formatPeso($t['amount']);
                            $typeClass = $isCredit ? 'earning' : 'payout';
                            $typeIcon = $isCredit ? 'arrow-down-circle' : 'arrow-up-circle';
                            $statusClass = match($t['status']) {
                                'completed' => 'completed',
                                'pending' => 'pending',
                                'failed' => 'failed',
                                'processing' => 'processing',
                                default => 'pending'
                            };
                            $initials = getInitials($t['driver_name']);
                            $profilePic = $t['profile_picture'] ?? null;
                            $dateTime = new DateTime($t['created_at']);
                            ?>
                            <tr>
                                <td><span class="transaction-id">#<?= htmlspecialchars(substr($t['transaction_id'], 0, 6)) ?></span></td>
                                <td>
                                    <div class="driver-info">
                                        <div class="driver-avatar" style="
                                            width: 36px;
                                            height: 36px;
                                            border-radius: 50%;
                                            <?php if ($profilePic): ?>
                                                background-image: url('<?= htmlspecialchars($profilePic) ?>');
                                                background-size: cover;
                                                background-position: center;
                                            <?php else: ?>
                                                background-color: #4a6cf7;
                                                color: white;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                font-weight: bold;
                                            <?php endif; ?>
                                        ">
                                            <?php if (!$profilePic): ?>
                                                <?= htmlspecialchars($initials) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="driver-details">
                                            <span class="driver-name"><?= htmlspecialchars($t['driver_name']) ?></span>
                                            <span class="driver-id">ID: <?= htmlspecialchars(substr($t['drivers_id'], 0, 6)) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="amount <?= $isCredit ? 'positive' : 'negative' ?>"><?= $amountFormatted ?></span></td>
                                <td>
                                    <span class="type-badge type-<?= $typeClass ?>">
                                        <i class="bi bi-<?= $typeIcon ?>"></i>
                                        <?= ucfirst($t['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="date-text"><?= $dateTime->format('M j, Y') ?></span>
                                    <span class="time-text"><?= $dateTime->format('g:i A') ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $statusClass ?>">
                                        <span class="status-dot"></span>
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-action-icon" title="View Details"
                                        onclick="window.location='transaction-view.php?id=<?= urlencode($t['transaction_id']) ?>'">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing <strong><?= $offset + 1 ?>-<?= min($offset + $limit, $totalRecords) ?></strong> of <strong><?= $totalRecords ?></strong> transactions
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-page">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn-page" disabled><i class="bi bi-chevron-left"></i></button>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="btn-page <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-page">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn-page" disabled><i class="bi bi-chevron-right"></i></button>
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

        // Filter dropdowns
        document.querySelectorAll('.filter-dropdown').forEach(dropdown => {
            const button = dropdown.querySelector('button');
            const menu = dropdown.querySelector('.filter-menu');
            
            button.addEventListener('click', () => {
                document.querySelectorAll('.filter-menu').forEach(m => {
                    if (m !== menu) m.classList.remove('active');
                });
                menu.classList.toggle('active');
            });
            
            dropdown.querySelectorAll('.filter-item').forEach(item => {
                item.addEventListener('click', () => {
                    const filterType = dropdown.id === 'typeFilter' ? 'type' : 'status';
                    const value = item.dataset.value;
                    
                    const url = new URL(window.location);
                    if (value) {
                        url.searchParams.set(filterType, value);
                    } else {
                        url.searchParams.delete(filterType);
                    }
                    url.searchParams.delete('page'); // reset to page 1
                    window.location.href = url.toString();
                });
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.filter-dropdown')) {
                document.querySelectorAll('.filter-menu').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>