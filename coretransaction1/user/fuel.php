<?php
// IMPORTANT: Start session FIRST, before any output
define('APP_LOADED', true);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if user is a driver
if ($_SESSION['role'] !== 'driver' || !isset($_SESSION['driver_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../backend/db1.php';

$driver_id = $_SESSION['driver_id'];
$user_id = $_SESSION['user_id'];

// Fetch fuel logs from database
$fuel_query = "SELECT f.fuel_id, f.vehicle_id, f.driver_id, f.liters, f.cost, 
                f.date, f.station_name, f.odometer_reading, f.fuel_type, 
                f.receipt_number, f.created_at,
                v.plate_number, v.type as vehicle_type
                FROM fuel_logs f
                LEFT JOIN vehicles v ON f.vehicle_id = v.vehicle_id
                WHERE f.driver_id = ?
                ORDER BY f.date DESC";

$stmt = $conn->prepare($fuel_query);
$stmt->bind_param("s", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

$fuel_logs = [];
$total_spent = 0;
$total_liters = 0;

while ($row = $result->fetch_assoc()) {
    $fuel_logs[] = $row;
    $total_spent += $row['cost'];
    $total_liters += $row['liters'];
}
$stmt->close();

// Get vehicles for the driver
$vehicles_query = "SELECT v.vehicle_id, v.plate_number, v.type 
                   FROM vehicles v
                   INNER JOIN trips t ON v.vehicle_id = t.vehicle_id
                   WHERE t.driver_id = ?
                   GROUP BY v.vehicle_id";
$vehicles_stmt = $conn->prepare($vehicles_query);
$vehicles_stmt->bind_param("s", $driver_id);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();

$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}
$vehicles_stmt->close();

// Calculate statistics
$avg_cost_per_liter = $total_liters > 0 ? $total_spent / $total_liters : 0;
$total_records = count($fuel_logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    <style>
    /* === GENERAL === */
    .content-area {
        padding: 30px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        color: #fff;
    }

    .add-fuel-btn {
        background: #4CAF50;
        color: #fff;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .add-fuel-btn:hover {
        background: #45a049;
        transform: translateY(-2px);
    }

    /* === STATS === */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: #1a1a1a;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #2a2a2a;
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .stat-label {
        color: #888;
        font-size: 14px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-icon.red { background: rgba(244, 67, 54, 0.1); color: #f44336; }
    .stat-icon.blue { background: rgba(33, 150, 243, 0.1); color: #2196F3; }
    .stat-icon.green { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
    .stat-icon.orange { background: rgba(255, 152, 0, 0.1); color: #FF9800; }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #fff;
    }

    /* === FUEL TABLE === */
    .fuel-logs-section {
        background: #1a1a1a;
        border-radius: 12px;
        border: 1px solid #2a2a2a;
        overflow: hidden;
    }

    .section-header {
        padding: 20px 25px;
        border-bottom: 1px solid #2a2a2a;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-title {
        font-size: 20px;
        font-weight: 600;
    }

    .fuel-table {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        background: #222;
        color: #888;
        font-size: 12px;
        text-transform: uppercase;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }

    td {
        padding: 15px;
        border-bottom: 1px solid #2a2a2a;
        color: #fff;
    }

    tr:hover {
        background: #222;
    }

    .fuel-type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .fuel-type-badge.petrol { background: rgba(255, 193, 7, 0.2); color: #FFC107; }
    .fuel-type-badge.diesel { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
    .fuel-type-badge.gas { background: rgba(33, 150, 243, 0.2); color: #2196F3; }

    .action-btn {
        background: transparent;
        border: none;
        color: #888;
        cursor: pointer;
        padding: 8px;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .action-btn:hover {
        background: #2a2a2a;
        color: #fff;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    /* === MODAL - IMPROVED === */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    z-index: 1999;
}

.modal-overlay.active {
    display: block;
}

.modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 500px;
    background: linear-gradient(135deg, #1a1a1a 0%, #222222 100%);
    border-radius: 16px;
    border: 1px solid #333333;
    z-index: 2000;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.95), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -48%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

.modal.active {
    display: flex;
}

.modal-header {
    padding: 24px 24px 16px;
    border-bottom: 1px solid #2a2a2a;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: rgba(76, 175, 80, 0.08);
}

.modal-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.modal-title i {
    color: #4CAF50;
}

.close-modal {
    background: transparent;
    border: none;
    color: #888;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}

.close-modal:hover {
    background: rgba(244, 67, 54, 0.1);
    color: #f44336;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(85vh - 140px);
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.modal-footer {
    padding: 16px 24px 24px;
    border-top: 1px solid #2a2a2a;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    flex-shrink: 0;
    background: rgba(0, 0, 0, 0.3);
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #aaa;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-label::after {
    content: attr(data-required);
    color: #f44336;
}

.form-input,
.form-select {
    width: 100%;
    padding: 12px 14px;
    background: #262626;
    border: 1.5px solid #333333;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    box-sizing: border-box;
    transition: all 0.2s;
    font-family: inherit;
}

.form-input::placeholder {
    color: #666;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: #4CAF50;
    background: #2a2a2a;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.form-input:invalid:not(:placeholder-shown) {
    border-color: #f44336;
}

.form-input:valid:not(:placeholder-shown) {
    border-color: #4CAF50;
}

.form-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-cancel {
    background: #2a2a2a;
    color: #ccc;
    border: 1px solid #3a3a3a;
}

.btn-cancel:hover {
    background: #333;
    color: #fff;
    border-color: #444;
}

.btn-submit {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.btn-submit:hover {
    box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
    transform: translateY(-1px);
}

.btn-submit:active {
    transform: translateY(0);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* === MOBILE RESPONSIVE === */
@media (max-width: 768px) {
    .modal {
        width: 95%;
        max-width: 100%;
        max-height: 95vh;
    }

    .modal-header {
        padding: 20px 20px 14px;
    }

    .modal-title {
        font-size: 18px;
        gap: 10px;
    }

    .modal-body {
        padding: 20px;
        max-height: calc(95vh - 130px);
    }

    .modal-footer {
        padding: 14px 20px 20px;
        gap: 10px;
    }

    .form-input,
    .form-select {
        padding: 10px 12px;
        font-size: 14px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .btn {
        padding: 10px 16px;
        font-size: 13px;
        flex: 1;
    }

    .btn-cancel {
        flex: 1;
    }

    .btn-submit {
        flex: 1;
    }
}
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'include/header.php'; ?>

        <div class="content-area">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-gas-pump"></i> Fuel Logs</h1>
                <button class="add-fuel-btn" onclick="openAddFuelModal()">
                    <i class="fas fa-plus"></i> Add Fuel Log
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-icon red">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($total_spent, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Total Liters</div>
                        <div class="stat-icon blue">
                            <i class="fas fa-tint"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_liters, 2); ?> L</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Avg. Cost/Liter</div>
                        <div class="stat-icon green">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($avg_cost_per_liter, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Total Records</div>
                        <div class="stat-icon orange">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_records; ?></div>
                </div>
            </div>

            <!-- Fuel Logs Table -->
            <div class="fuel-logs-section">
                <div class="section-header">
                    <div class="section-title">Fuel History</div>
                </div>

                <div class="fuel-table">
                    <?php if (count($fuel_logs) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vehicle</th>
                                    <th>Fuel Type</th>
                                    <th>Liters</th>
                                    <th>Cost</th>
                                    <th>Station</th>
                                    <th>Odometer</th>
                                    <th>Receipt</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fuel_logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($log['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['plate_number'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="fuel-type-badge <?php echo strtolower(htmlspecialchars($log['fuel_type'])); ?>">
                                                <?php echo strtoupper(htmlspecialchars($log['fuel_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($log['liters'], 2); ?> L</td>
                                        <td>₱<?php echo number_format($log['cost'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($log['station_name']); ?></td>
                                        <td><?php echo number_format($log['odometer_reading'], 0); ?> km</td>
                                        <td><?php echo htmlspecialchars($log['receipt_number'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button class="action-btn" onclick="viewFuelLog('<?php echo (int)$log['fuel_id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn" onclick="deleteFuelLog('<?php echo (int)$log['fuel_id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-gas-pump"></i>
                            <h3>No Fuel Logs Yet</h3>
                            <p>Start tracking your fuel consumption by adding your first log</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include 'include/footer.php'; ?>
    </main>

    <?php include 'include/logout_modal.php'; ?>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeAddFuelModal()"></div>

    <!-- Add Fuel Modal -->
    <div class="modal" id="addFuelModal">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fas fa-gas-pump"></i> Add Fuel Log
            </h2>
            <button class="close-modal" onclick="closeAddFuelModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addFuelForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Vehicle *</label>
                    <select class="form-select" name="vehicle_id" required>
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo (int)$vehicle['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($vehicle['plate_number'] . ' (' . $vehicle['type'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-input" name="date" required max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fuel Type *</label>
                        <select class="form-select" name="fuel_type" required>
                            <option value="diesel">Diesel</option>
                            <option value="petrol">Petrol</option>
                            <option value="gas">Gas</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Liters *</label>
                        <input type="number" step="0.01" class="form-input" name="liters" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cost (₱) *</label>
                        <input type="number" step="0.01" class="form-input" name="cost" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Gas Station Name *</label>
                    <input type="text" class="form-input" name="station_name" placeholder="e.g., Petron, Shell, Caltex" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Odometer Reading (km) *</label>
                        <input type="number" step="0.01" class="form-input" name="odometer_reading" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Receipt Number</label>
                        <input type="text" class="form-input" name="receipt_number" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeAddFuelModal()">Cancel</button>
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save"></i> Save Log
                </button>
            </div>
        </form>
    </div>

    <script>
        function openAddFuelModal() {
            document.getElementById('modalOverlay').classList.add('active');
            document.getElementById('addFuelModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddFuelModal() {
            document.getElementById('modalOverlay').classList.remove('active');
            document.getElementById('addFuelModal').classList.remove('active');
            document.getElementById('addFuelForm').reset();
            document.body.style.overflow = 'auto';
        }

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddFuelModal();
            }
        });

        // Handle form submission
        document.getElementById('addFuelForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('../backend/add_fuel_log.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Fuel log added successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Log';
                }
            })
            .catch(error => {
                alert('An error occurred');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Log';
            });
        });

        function viewFuelLog(fuelId) {
            alert('View fuel log: ' + fuelId + '\n\nDetailed view coming soon!');
        }

        function deleteFuelLog(fuelId) {
            if (confirm('Are you sure you want to delete this fuel log?')) {
                fetch('../backend/delete_fuel_log.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'fuel_id=' + encodeURIComponent(fuelId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Fuel log deleted successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('An error occurred');
                });
            }
        }
    </script>
</body>
</html>