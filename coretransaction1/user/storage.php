<?php
// IMPORTANT: Start session FIRST, before any output
define('APP_LOADED', true);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
// Include database connection
require_once '../backend/db1.php';

// Handle supply request submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $item_name = trim($_POST['itemSelect'] ?? '');
    $quantity = (int)($_POST['quantityInput'] ?? 0);
    $purpose = trim($_POST['purposeSelect'] ?? '');
    $notes = trim($_POST['notesInput'] ?? '');

    if (empty($item_name) || $quantity <= 0 || empty($purpose)) {
        $error_message = "Please fill all required fields.";
    } else {
        // Get consumable_id and current stock
        $stmt = $conn->prepare("SELECT consumable_id, stock_qty FROM consumables WHERE item_name = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $item_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "Selected item is not available for request.";
            $stmt->close();
        } else {
            $row = $result->fetch_assoc();
            $consumable_id = $row['consumable_id'];
            $available_stock = (int)$row['stock_qty'];
            $stmt->close();

            // 🔒 ADVANCED VALIDATION: Prevent over-requesting
            if ($quantity > $available_stock) {
                $error_message = "You cannot request more than the available stock ({$available_stock} units).";
            } else {
                // Insert request
                $log_id = bin2hex(random_bytes(16)); // 32-char ID
                $user_id = $_SESSION['user_id'];
                $status = 'pending';

                $stmt2 = $conn->prepare("
                    INSERT INTO consumable_logs (
                        log_id, consumable_id, usage_type, quantity, 
                        requested_by, purpose, notes, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt2->bind_param(
                    "sssissss",
                    $log_id,
                    $consumable_id,
                    $purpose,
                    $quantity,
                    $user_id,
                    $purpose,
                    $notes,
                    $status
                );

                if ($stmt2->execute()) {
                    $success_message = "Your request has been submitted and is pending approval.";
                } else {
                    $error_message = "Failed to submit request. Please try again.";
                }
                $stmt2->close();
            }
        }
    }
}

// Fetch active consumables for dropdown AND table
$items = [];
$consumables = [];
$items_result = $conn->query("SELECT item_name FROM consumables WHERE status = 'active' ORDER BY item_name");
$consumables_result = $conn->query("
    SELECT 
        consumable_id,
        item_name,
        category,
        stock_qty,
        unit_price,
        location,
        min_threshold,
        status
    FROM consumables 
    WHERE status IN ('active', 'out_of_stock')
    ORDER BY item_name
");

if ($items_result) {
    while ($row = $items_result->fetch_assoc()) {
        $items[] = htmlspecialchars($row['item_name']);
    }
}

if ($consumables_result) {
    while ($row = $consumables_result->fetch_assoc()) {
        $stock_qty = (int)$row['stock_qty'];
        if ($row['status'] === 'out_of_stock' || $stock_qty <= 0) {
            $display_status = 'Out of Stock';
        } else if ($stock_qty <= $row['min_threshold']) {
            $display_status = 'Low Stock';
        } else {
            $display_status = 'In Stock';
        }

        $consumables[] = [
            'id' => $row['consumable_id'],
            'item_name' => htmlspecialchars($row['item_name']),
            'category' => htmlspecialchars($row['category'] ?? 'Uncategorized'),
            'stock_qty' => $stock_qty,
            'unit_price' => (float)$row['unit_price'],
            'location' => htmlspecialchars($row['location'] ?? 'Main Warehouse'),
            'status' => $display_status,
            'is_available' => ($display_status !== 'Out of Stock')
        ];
    }
}

// Fetch user's recent requests
$my_requests = [];
$req_sql = "
    SELECT 
        cl.log_id,
        c.item_name,
        cl.quantity,
        cl.usage_type AS purpose,
        DATE(cl.created_at) AS request_date,
        cl.status,
        cl.notes
    FROM consumable_logs cl
    JOIN consumables c ON cl.consumable_id = c.consumable_id
    WHERE cl.requested_by = ?
    ORDER BY cl.created_at DESC
    LIMIT 10
";
$req_stmt = $conn->prepare($req_sql);
$req_stmt->bind_param("s", $_SESSION['user_id']);
$req_stmt->execute();
$req_result = $req_stmt->get_result();
while ($row = $req_result->fetch_assoc()) {
    $my_requests[] = $row;
}
$req_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage & Supplies - Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/driver-storage.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
</head>
<body>
    <?php include 'include/sidebar.php'?>
    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <?php include 'include/header.php';?>
        <div class="content-area">
            <div class="page-header">
                <h1 class="page-title">Storage & Supplies</h1>
                <button class="request-btn" id="openRequestModal">
                    <i class="fas fa-plus-circle"></i>
                    Request Item
                </button>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-group">
                    <div class="filter-item">
                        <label for="searchInput"><i class="fas fa-search"></i> Search Items</label>
                        <input type="text" id="searchInput" placeholder="Search by item name...">
                    </div>
                    <div class="filter-item">
                        <label for="categoryFilter"><i class="fas fa-filter"></i> Category</label>
                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php
                            $categories = [];
                            foreach ($consumables as $c) {
                                if (!in_array($c['category'], $categories)) {
                                    $categories[] = $c['category'];
                                }
                            }
                            sort($categories);
                            foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="statusFilter"><i class="fas fa-info-circle"></i> Stock Status</label>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="In Stock">In Stock</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Out of Stock">Out of Stock</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="table-container">
                <table class="inventory-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Available Stock</th>
                            <th>Unit Price</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php if (empty($consumables)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:20px;">No items available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($consumables as $item): 
                                $iconClass = strtolower(str_replace(' ', '', $item['category']));
                                $iconMap = [
                                    'maintenance' => 'fa-oil-can',
                                    'cleaning' => 'fa-spray-can',
                                    'safetyequipment' => 'fa-hard-hat',
                                    'fueladditives' => 'fa-gas-pump',
                                    'tools' => 'fa-wrench',
                                ];
                                $icon = $iconMap[strtolower(str_replace(' ', '', $item['category']))] ?? 'fa-box';
                                $stockClass = $item['status'] === 'Out of Stock' ? 'out' : ($item['status'] === 'Low Stock' ? 'low' : 'high');
                                $statusBadgeClass = $item['status'] === 'Out of Stock' ? 'out-stock' : ($item['status'] === 'Low Stock' ? 'low-stock' : 'in-stock');
                            ?>
                            <tr data-category="<?= htmlspecialchars($item['category']) ?>" data-status="<?= htmlspecialchars($item['status']) ?>">
                                <td>
                                    <div class="item-cell">
                                        <div class="item-icon-small <?= htmlspecialchars($iconClass) ?>">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <span class="item-name-text"><?= $item['item_name'] ?></span>
                                    </div>
                                </td>
                                <td><?= $item['category'] ?></td>
                                <td><span class="stock-value <?= $stockClass ?>"><?= $item['stock_qty'] ?> units</span></td>
                                <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                <td><?= $item['location'] ?></td>
                                <td><span class="status-badge <?= $statusBadgeClass ?>"><?= $item['status'] ?></span></td>
                                <td>
                                    <?php if ($item['is_available']): ?>
                                        <button class="btn-request" onclick="openRequestModal('<?= addslashes($item['item_name']) ?>', <?= $item['stock_qty'] ?>)">
                                            <i class="fas fa-hand-paper"></i> Request
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-request" disabled>
                                            <i class="fas fa-ban"></i> Unavailable
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="empty-state" id="emptyState" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No items found</h3>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            </div>

            <!-- My Requests Section -->
            <div class="my-requests-section">
                <h2 class="section-title"><i class="fas fa-clipboard-list"></i> My Recent Requests</h2>
                <div class="table-container">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="requestsTableBody">
                            <?php if (empty($my_requests)): ?>
                                <tr><td colspan="7" style="text-align:center;padding:15px;">No requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($my_requests as $req): 
                                    $status_class = match($req['status']) {
                                        'approved' => 'approved',
                                        'rejected' => 'rejected',
                                        default => 'pending'
                                    };
                                ?>
                                <tr>
                                    <td>#REQ-<?= substr($req['log_id'], 0, 6) ?></td>
                                    <td><?= htmlspecialchars($req['item_name']) ?></td>
                                    <td><?= (int)$req['quantity'] ?> units</td>
                                    <td><?= htmlspecialchars(ucfirst($req['purpose'])) ?></td>
                                    <td><?= htmlspecialchars($req['request_date']) ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= ucfirst($req['status']) ?></span></td>
                                    <td>
                                        <button class="action-btn view-btn" onclick="viewRequestDetails('<?= addslashes($req['log_id']) ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'include/footer.php';?>
    </main>

    <?php include 'include/logout_modal.php';?>

    <!-- Request Modal -->
    <div class="modal" id="requestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Request Item</h2>
                <button class="close-btn" id="closeModal">&times;</button>
            </div>
            <form id="requestForm" method="POST">
                <div class="form-group">
                    <label for="itemSelect">Select Item *</label>
                    <select id="itemSelect" name="itemSelect" required>
                        <option value="">Choose an item...</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item ?>"><?= $item ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantityInput">Quantity *</label>
                    <input type="number" id="quantityInput" name="quantityInput" min="1" placeholder="Enter quantity" required>
                    <small id="stockLimitNote" style="display:none; color:#e74c3c; margin-top:4px;">Max: <span id="maxQty"></span> units available</small>
                </div>
                <div class="form-group">
                    <label for="purposeSelect">Purpose *</label>
                    <select id="purposeSelect" name="purposeSelect" required>
                        <option value="">Select purpose...</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="repair">Repair</option>
                        <option value="replacement">Replacement</option>
                        <option value="emergency">Emergency</option>
                        <option value="routine">Routine</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notesInput">Additional Notes</label>
                    <textarea id="notesInput" name="notesInput" rows="4" placeholder="Provide additional details..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" name="submit_request" class="btn-primary" id="submitRequestBtn">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Request Details Modal -->
    <div class="modal" id="viewRequestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-alt"></i> Request Details</h2>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewRequestBody">
                <div class="detail-section">
                    <h3>Request Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-hashtag"></i> Request ID:</span>
                            <span class="detail-value" id="viewReqId"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-box"></i> Item Name:</span>
                            <span class="detail-value" id="viewItemName"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-sort-numeric-up"></i> Quantity:</span>
                            <span class="detail-value" id="viewQuantity"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-tasks"></i> Purpose:</span>
                            <span class="detail-value" id="viewPurpose"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Request Date:</span>
                            <span class="detail-value" id="viewDate"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-info-circle"></i> Status:</span>
                            <span class="detail-value" id="viewStatus"></span>
                        </div>
                    </div>
                </div>
                <div class="detail-section" id="notesSection">
                    <h3>Additional Notes</h3>
                    <p id="viewNotes"></p>
                </div>
                <div class="detail-section" id="approvalSection" style="display: none;">
                    <h3>Approval Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-user-check"></i> Approved By:</span>
                            <span class="detail-value" id="viewApprovedBy"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="fas fa-calendar-check"></i> Approval Date:</span>
                            <span class="detail-value" id="viewApprovalDate"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content success-modal-content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Request Submitted Successfully!</h2>
            <p>Your supply request has been submitted and is pending approval from the admin.</p>
            <div class="form-actions">
                <button type="button" class="btn-primary" id="closeSuccessBtn">Got it!</button>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const overlay = document.getElementById('overlay');
    const requestModal = document.getElementById('requestModal');
    const viewRequestModal = document.getElementById('viewRequestModal');
    const successModal = document.getElementById('successModal');
    const openRequestModalBtn = document.getElementById('openRequestModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const closeSuccessBtn = document.getElementById('closeSuccessBtn');
    const requestForm = document.getElementById('requestForm');
    const itemSelect = document.getElementById('itemSelect');
    const quantityInput = document.getElementById('quantityInput');
    const submitBtn = document.getElementById('submitRequestBtn');
    const stockLimitNote = document.getElementById('stockLimitNote');
    const maxQtySpan = document.getElementById('maxQty');

    // Store stock limits by item name
    const stockLimits = {};
    <?php foreach ($consumables as $c): ?>
        stockLimits["<?= addslashes($c['item_name']) ?>"] = <?= $c['stock_qty'] ?>;
    <?php endforeach; ?>

    // Sample data for view modal (optional: replace with real fetch later)
    const requestsData = {};
    <?php foreach ($my_requests as $req): ?>
        requestsData["<?= addslashes($req['log_id']) ?>"] = {
            id: '#REQ-<?= substr($req['log_id'], 0, 6) ?>',
            itemName: "<?= addslashes($req['item_name']) ?>",
            quantity: "<?= (int)$req['quantity'] ?> units",
            purpose: "<?= addslashes($req['purpose']) ?>",
            date: "<?= addslashes($req['request_date']) ?>",
            status: "<?= addslashes($req['status']) ?>",
            notes: "<?= addslashes($req['notes'] ?? 'No notes provided.') ?>",
            approvedBy: "Admin",
            approvalDate: "<?= addslashes($req['request_date']) ?>"
        };
    <?php endforeach; ?>

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function openModal() {
        requestModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        requestModal.classList.remove('active');
        document.body.style.overflow = '';
        requestForm.reset();
        stockLimitNote.style.display = 'none';
    }

    // Updated: accept maxQty
    function openRequestModal(itemName = '', maxQty = null) {
        openModal();
        if (itemName) {
            itemSelect.value = itemName;
            if (maxQty !== null) {
                quantityInput.max = maxQty;
                maxQtySpan.textContent = maxQty;
                stockLimitNote.style.display = 'block';
            } else if (stockLimits[itemName]) {
                quantityInput.max = stockLimits[itemName];
                maxQtySpan.textContent = stockLimits[itemName];
                stockLimitNote.style.display = 'block';
            }
            setTimeout(() => quantityInput.focus(), 100);
        }
    }

    function closeViewModal() {
        viewRequestModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function viewRequestDetails(requestId) {
        const request = requestsData[requestId];
        if (!request) return;
        document.getElementById('viewReqId').textContent = request.id;
        document.getElementById('viewItemName').textContent = request.itemName;
        document.getElementById('viewQuantity').textContent = request.quantity;
        document.getElementById('viewPurpose').textContent = capitalize(request.purpose);
        document.getElementById('viewDate').textContent = request.date;
        const statusEl = document.getElementById('viewStatus');
        statusEl.innerHTML = `<span class="status-badge ${request.status}">${capitalize(request.status)}</span>`;
        document.getElementById('viewNotes').textContent = request.notes || 'No additional notes provided.';
        const approvalSection = document.getElementById('approvalSection');
        if (request.status === 'approved' || request.status === 'rejected') {
            document.getElementById('viewApprovedBy').textContent = request.approvedBy;
            document.getElementById('viewApprovalDate').textContent = request.approvalDate;
            approvalSection.style.display = 'block';
        } else {
            approvalSection.style.display = 'none';
        }
        viewRequestModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function showSuccessModal() {
        successModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSuccessModal() {
        successModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Validation on quantity change
    quantityInput?.addEventListener('input', () => {
        const itemName = itemSelect.value;
        const qty = parseInt(quantityInput.value) || 0;
        const max = parseInt(quantityInput.max) || Infinity;
        submitBtn.disabled = (qty <= 0 || qty > max);
    });

    itemSelect?.addEventListener('change', () => {
        const itemName = itemSelect.value;
        if (itemName && stockLimits[itemName]) {
            quantityInput.max = stockLimits[itemName];
            maxQtySpan.textContent = stockLimits[itemName];
            stockLimitNote.style.display = 'block';
        } else {
            quantityInput.removeAttribute('max');
            stockLimitNote.style.display = 'none';
        }
        quantityInput.value = '';
        submitBtn.disabled = true;
    });

    // Event Listeners
    menuToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    });

    openRequestModalBtn?.addEventListener('click', () => openRequestModal());
    closeModalBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    closeSuccessBtn?.addEventListener('click', closeSuccessModal);

    requestModal?.addEventListener('click', (e) => { if (e.target === requestModal) closeModal(); });
    viewRequestModal?.addEventListener('click', (e) => { if (e.target === viewRequestModal) closeViewModal(); });
    successModal?.addEventListener('click', (e) => { if (e.target === successModal) closeSuccessModal(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (requestModal.classList.contains('active')) closeModal();
            if (viewRequestModal.classList.contains('active')) closeViewModal();
            if (successModal.classList.contains('active')) closeSuccessModal();
        }
    });

    // Filters
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const inventoryTableBody = document.getElementById('inventoryTableBody');
    const emptyState = document.getElementById('emptyState');

    function filterInventory() {
        const searchTerm = searchInput.value.toLowerCase();
        const category = categoryFilter.value;
        const status = statusFilter.value;
        const rows = inventoryTableBody.querySelectorAll('tr');
        let visibleCount = 0;
        rows.forEach(row => {
            const itemName = row.querySelector('.item-name-text')?.textContent.toLowerCase() || '';
            const itemCategory = row.getAttribute('data-category') || '';
            const itemStatus = row.getAttribute('data-status') || '';
            const matchesSearch = itemName.includes(searchTerm);
            const matchesCategory = !category || itemCategory === category;
            const matchesStatus = !status || itemStatus === status;
            if (matchesSearch && matchesCategory && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        if (visibleCount === 0) {
            inventoryTableBody.parentElement.style.display = 'none';
            emptyState.style.display = 'flex';
        } else {
            inventoryTableBody.parentElement.style.display = 'table';
            emptyState.style.display = 'none';
        }
    }

    searchInput?.addEventListener('input', filterInventory);
    categoryFilter?.addEventListener('change', filterInventory);
    statusFilter?.addEventListener('change', filterInventory);

    // Make globally accessible
    window.openRequestModal = openRequestModal;
    window.viewRequestDetails = viewRequestDetails;
    window.closeViewModal = closeViewModal;
    window.closeSuccessModal = closeSuccessModal;

    // Auto-show modals
    <?php if (!empty($success_message)): ?>
        showSuccessModal();
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        alert("<?= addslashes($error_message) ?>");
    <?php endif; ?>
});
    </script>
</body>
</html>