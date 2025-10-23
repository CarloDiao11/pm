<?php
// reports.php - Dynamic Reports Page
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Helper: Format as Peso
function formatPeso($amount) {
    return '₱' . number_format($amount, 0);
}

// Helper: Get report category
function getReportCategory($type) {
    $type = strtolower($type);
    if (strpos($type, 'fuel') !== false || strpos($type, 'expense') !== false || strpos($type, 'budget') !== false) {
        return 'financial';
    } elseif (strpos($type, 'compliance') !== false || strpos($type, 'incident') !== false || strpos($type, 'safety') !== false) {
        return 'compliance';
    }
    return 'usage';
}

// Helper: Get severity badge
function getSeverityBadge($description) {
    $desc = strtolower($description);
    if (strpos($desc, 'expired') !== false || strpos($desc, 'overdue') !== false) {
        return ['class' => 'high', 'text' => 'High'];
    } elseif (strpos($desc, 'renewal') !== false || strpos($desc, 'inspection') !== false) {
        return ['class' => 'medium', 'text' => 'Medium'];
    }
    return ['class' => 'low', 'text' => 'Low'];
}

// --- Pagination & Filtering ---
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$reportType = $_GET['report_type'] ?? '';
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch all reports with related data
$reports = db_select_advanced("
    SELECT 
        r.report_id,
        r.report_type,
        r.generated_at,
        r.file_url,
        r.generated_by,
        u.name AS generated_by_name,
        -- Try to extract vehicle/driver from description (or join if you have report_details table)
        r.description
    FROM reports r
    JOIN users u ON r.generated_by = u.user_id
    WHERE 1=1
        " . ($search ? "AND (r.report_type LIKE ? OR r.description LIKE ? OR u.name LIKE ?)" : "") . "
        " . ($reportType ? "AND r.report_type = ?" : "") . "
    ORDER BY r.generated_at DESC
    LIMIT $limit OFFSET $offset
", array_merge(
    $search ? ["%$search%", "%$search%", "%$search%"] : [],
    $reportType ? [$reportType] : []
));

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total 
    FROM reports r
    JOIN users u ON r.generated_by = u.user_id
    WHERE 1=1
        " . ($search ? "AND (r.report_type LIKE ? OR r.description LIKE ? OR u.name LIKE ?)" : "") . "
        " . ($reportType ? "AND r.report_type = ?" : "");
$total = db_select_advanced($countSql, array_merge(
    $search ? ["%$search%", "%$search%", "%$search%"] : [],
    $reportType ? [$reportType] : []
))[0]['total'] ?? 0;

// Group reports by category
$usageReports = [];
$financialReports = [];
$complianceReports = [];

foreach ($reports as $report) {
    $category = getReportCategory($report['report_type']);
    $report['severity'] = getSeverityBadge($report['description']);
    
    // Mock vehicle/driver data (since your schema doesn't link reports to vehicles/drivers directly)
    // In real app, you'd have a report_details table or parse description
    $report['vehicle'] = [
        'name' => strpos($report['description'], 'All Vehicles') !== false ? 'All Vehicles' : 'Toyota Hilux',
        'plate' => strpos($report['description'], 'All Vehicles') !== false ? '-' : 'ABC-1234'
    ];
    $report['driver'] = strpos($report['description'], 'Driver') !== false ? 'John Doe' : '-';
    
    // Extract amount from description if financial
    $report['amount'] = null;
    if ($category === 'financial') {
        preg_match('/[\d,]+/', $report['description'], $matches);
        if (!empty($matches)) {
            $report['amount'] = (float)str_replace(',', '', $matches[0]);
        }
    }

    if ($category === 'usage') {
        $usageReports[] = $report;
    } elseif ($category === 'financial') {
        $financialReports[] = $report;
    } else {
        $complianceReports[] = $report;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/reports.css">
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
        <div class="reports-header">
            <div class="header-content">
                <h1 class="page-title">Reports</h1>
                <p class="page-subtitle">Generate and view system reports and analytics</p>
            </div>
            <div class="header-actions">
                <button class="btn-report btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                    <i class="bi bi-file-earmark-text"></i>
                    Generate Report
                </button>
                <button class="btn-report btn-secondary">
                    <i class="bi bi-download"></i>
                    Export All
                </button>
            </div>
        </div>

        <!-- Filter Section (Global) -->
        <div class="filter-section <?= ($search || $reportType) ? 'active' : '' ?>" id="filterSection">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Reports</label>
                    <input type="text" class="form-control" placeholder="Search by type, description, or user..." 
                           value="<?= htmlspecialchars($search) ?>" id="searchInput">
                </div>
                <div class="filter-group">
                    <label>Report Type</label>
                    <select class="form-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="Usage" <?= $reportType === 'Usage' ? 'selected' : '' ?>>Usage</option>
                        <option value="Fuel Expense" <?= $reportType === 'Fuel Expense' ? 'selected' : '' ?>>Fuel Expense</option>
                        <option value="Maintenance" <?= $reportType === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        <option value="Budget Summary" <?= $reportType === 'Budget Summary' ? 'selected' : '' ?>>Budget Summary</option>
                        <option value="Compliance" <?= $reportType === 'Compliance' ? 'selected' : '' ?>>Compliance</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions mt-3">
                <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                <button class="btn btn-outline-secondary" onclick="clearFilters()">Clear</button>
            </div>
        </div>

        <!-- System Usage Reports Section -->
        <?php if (!empty($usageReports)): ?>
        <div class="report-section">
            <div class="report-card">
                <div class="report-card-header">
                    <div class="header-left">
                        <h2 class="card-heading">
                            <i class="bi bi-graph-up"></i>
                            System Usage Reports
                        </h2>
                        <p class="card-description">Track system activity, user engagement, and operational metrics</p>
                    </div>
                    <div class="header-right">
                        <button class="btn-card-action" onclick="toggleFilter()">
                            <i class="bi bi-funnel"></i>
                            Filter
                        </button>
                        <button class="btn-card-action">
                            <i class="bi bi-download"></i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="report-table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Report Type</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Description</th>
                                <th>Date Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usageReports as $r): ?>
                            <tr>
                                <td><span class="report-id">#<?= htmlspecialchars(substr($r['report_id'], 0, 6)) ?></span></td>
                                <td><span class="report-type"><?= htmlspecialchars($r['report_type']) ?></span></td>
                                <td>
                                    <div class="vehicle-cell">
                                        <span class="vehicle-name"><?= htmlspecialchars($r['vehicle']['name']) ?></span>
                                        <span class="vehicle-plate"><?= htmlspecialchars($r['vehicle']['plate']) ?></span>
                                    </div>
                                </td>
                                <td><span class="driver-name"><?= htmlspecialchars($r['driver']) ?></span></td>
                                <td><span class="description-text"><?= htmlspecialchars($r['description']) ?></span></td>
                                <td>
                                    <div class="date-cell">
                                        <span class="date-value"><?= date('M j, Y', strtotime($r['generated_at'])) ?></span>
                                        <span class="time-value"><?= date('g:i A', strtotime($r['generated_at'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($r['file_url']): ?>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" target="_blank" class="btn-icon" title="View Report">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" download class="btn-icon" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-icon" disabled title="No file available">
                                                <i class="bi bi-file-earmark"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Financial Reports Section -->
        <?php if (!empty($financialReports)): ?>
        <div class="report-section">
            <div class="report-card">
                <div class="report-card-header">
                    <div class="header-left">
                        <h2 class="card-heading">
                            <i class="bi bi-cash-stack"></i>
                            Financial Reports
                        </h2>
                        <p class="card-description">Monitor expenses, fuel costs, and budget utilization</p>
                    </div>
                    <div class="header-right">
                        <button class="btn-card-action" onclick="toggleFilter()">
                            <i class="bi bi-funnel"></i>
                            Filter
                        </button>
                        <button class="btn-card-action">
                            <i class="bi bi-download"></i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="report-table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Report Type</th>
                                <th>Vehicle</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Date Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financialReports as $r): ?>
                            <tr>
                                <td><span class="report-id">#<?= htmlspecialchars(substr($r['report_id'], 0, 6)) ?></span></td>
                                <td><span class="report-type"><?= htmlspecialchars($r['report_type']) ?></span></td>
                                <td>
                                    <div class="vehicle-cell">
                                        <span class="vehicle-name"><?= htmlspecialchars($r['vehicle']['name']) ?></span>
                                        <span class="vehicle-plate"><?= htmlspecialchars($r['vehicle']['plate']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['amount'] !== null): ?>
                                        <span class="amount-value"><?= formatPeso($r['amount']) ?></span>
                                    <?php else: ?>
                                        <span class="amount-value">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="description-text"><?= htmlspecialchars($r['description']) ?></span></td>
                                <td>
                                    <div class="date-cell">
                                        <span class="date-value"><?= date('M j, Y', strtotime($r['generated_at'])) ?></span>
                                        <span class="time-value"><?= date('g:i A', strtotime($r['generated_at'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($r['file_url']): ?>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" target="_blank" class="btn-icon" title="View Report">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" download class="btn-icon" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-icon" disabled title="No file available">
                                                <i class="bi bi-file-earmark"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Compliance Reports Section -->
        <?php if (!empty($complianceReports)): ?>
        <div class="report-section">
            <div class="report-card">
                <div class="report-card-header">
                    <div class="header-left">
                        <h2 class="card-heading">
                            <i class="bi bi-shield-check"></i>
                            Compliance Reports
                        </h2>
                        <p class="card-description">Review regulatory compliance, safety, and incident reports</p>
                    </div>
                    <div class="header-right">
                        <button class="btn-card-action" onclick="toggleFilter()">
                            <i class="bi bi-funnel"></i>
                            Filter
                        </button>
                        <button class="btn-card-action">
                            <i class="bi bi-download"></i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="report-table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Description</th>
                                <th>Severity</th>
                                <th>Date Reported</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complianceReports as $r): ?>
                            <tr>
                                <td><span class="report-id">#<?= htmlspecialchars(substr($r['report_id'], 0, 6)) ?></span></td>
                                <td>
                                    <div class="vehicle-cell">
                                        <span class="vehicle-name"><?= htmlspecialchars($r['vehicle']['name']) ?></span>
                                        <span class="vehicle-plate"><?= htmlspecialchars($r['vehicle']['plate']) ?></span>
                                    </div>
                                </td>
                                <td><span class="driver-name"><?= htmlspecialchars($r['driver']) ?></span></td>
                                <td><span class="description-text"><?= htmlspecialchars($r['description']) ?></span></td>
                                <td><span class="severity-badge severity-<?= $r['severity']['class'] ?>"><?= $r['severity']['text'] ?></span></td>
                                <td>
                                    <div class="date-cell">
                                        <span class="date-value"><?= date('M j, Y', strtotime($r['generated_at'])) ?></span>
                                        <span class="time-value"><?= date('g:i A', strtotime($r['generated_at'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($r['file_url']): ?>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" target="_blank" class="btn-icon" title="View Report">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($r['file_url']) ?>" download class="btn-icon" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-icon" disabled title="No file available">
                                                <i class="bi bi-file-earmark"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($reports)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                No reports found. 
            </div>
        <?php endif; ?>

        <!-- Pagination (Global) -->
        <?php if ($total > $limit): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&report_type=<?= urlencode($reportType) ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min(ceil($total / $limit), $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&report_type=<?= urlencode($reportType) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < ceil($total / $limit)): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&report_type=<?= urlencode($reportType) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="overlay" id="overlay"></div>
    <?php include '../includes/footer.php'; ?>

    <!-- Generate Report Modal (Placeholder) -->
    <div class="modal fade" id="generateReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate New Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="reportForm">
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Type</option>
                                <option value="Usage">Usage Report</option>
                                <option value="Fuel Expense">Fuel Expense</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Budget Summary">Budget Summary</option>
                                <option value="Compliance">Compliance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const type = document.getElementById('typeFilter').value;
            let url = '?page=1';
            if (search) url += '&search=' + encodeURIComponent(search);
            if (type) url += '&report_type=' + encodeURIComponent(type);
            window.location.href = url;
        }

        function clearFilters() {
            window.location.href = '?page=1';
        }

        // Handle report generation
        document.getElementById('reportForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In real app: POST to generate_report.php
            alert('Report generation would be handled by backend.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('generateReportModal'));
            modal.hide();
        });
    </script>
</body>
</html>