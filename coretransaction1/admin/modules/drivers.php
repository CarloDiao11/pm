<?php
// drivers.php - Drivers Management with Modals
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Fetch all drivers
$driversRaw = db_select('drivers', [], ['order_by' => 'license_number ASC']);
$drivers = [];

foreach ($driversRaw as $driverRow) {
    if (empty($driverRow['user_id']) || empty($driverRow['drivers_id'])) continue;

    $userRows = db_select('users', ['user_id' => $driverRow['user_id']], ['limit' => 1]);
    if (empty($userRows)) continue;

    $user = $userRows[0];
    if (($user['role'] ?? '') !== 'driver') continue;

    $drivers[] = [
        'drivers_id'      => $driverRow['drivers_id'],
        'user_id'         => $user['user_id'],
        'name'            => $user['name'] ?? 'N/A',
        'email'           => $user['email'] ?? '',
        'username'        => $user['username'] ?? '',
        'license_number'  => $driverRow['license_number'] ?? '',
        'license_expiry'  => $driverRow['license_expiry'] ?? null,
        'contact_number'  => $driverRow['contact_number'] ?? '',
        'address'         => $driverRow['address'] ?? '',
        'rating'          => isset($driverRow['rating']) ? (float)$driverRow['rating'] : 0.0,
        'profile_picture' => $driverRow['profile_picture'] ?? null,
        'user_status'     => $user['status'] ?? 'active'
    ];
}

// Helpers
function getComplianceStatus($expiry) {
    if (empty($expiry)) return 'pending';
    $expiryDate = strtotime($expiry);
    $today = strtotime(date('Y-m-d'));
    $daysLeft = ($expiryDate - $today) / (60 * 60 * 24);
    if ($daysLeft < 0) return 'non-compliant';
    if ($daysLeft <= 30) return 'pending';
    return 'compliant';
}

function renderRating($rating) {
    $rating = max(0, min(5, (float)$rating));
    $fullStars = floor($rating);
    $hasHalf = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalf ? 1 : 0);

    $html = '<div class="stars">';
    for ($i = 0; $i < $fullStars; $i++) $html .= '<i class="bi bi-star-fill"></i>';
    if ($hasHalf) $html .= '<i class="bi bi-star-half"></i>';
    for ($i = 0; $i < $emptyStars; $i++) $html .= '<i class="bi bi-star"></i>';
    $html .= '</div>';
    return $html;
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
    <title>Drivers - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/drivers.css">
    <!-- FIXED: Removed trailing spaces -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .driver-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: #444; color: white; display: flex; align-items: center;
            justify-content: center; font-weight: bold; font-size: 14px;
            overflow: hidden; flex-shrink: 0;
        }
        .driver-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .compliance-status.compliant { color: #2e7d32; font-weight: bold; }
        .compliance-status.pending { color: #f57f17; }
        .compliance-status.non-compliant { color: #c62828; font-weight: bold; }
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
            <h1 class="page-title">Drivers</h1>
            <button class="btn-add-driver" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                <i class="bi bi-plus-lg"></i> Add Driver
            </button>
        </div>

        <div class="drivers-card">
            <table class="drivers-table">
                <thead>
                    <tr>
                        <th>Driver ID</th>
                        <th>Name</th>
                        <th>License No</th>
                        <th>Expiry</th>
                        <th>Contact</th>
                        <th>Compliance</th>
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:20px;">No drivers found</td></tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $d): ?>
                        <tr>
                            <td><span class="driver-id"><?= htmlspecialchars(substr($d['drivers_id'], 0, 8)) ?></span></td>
                            <td>
                                <div class="driver-info">
                                    <div class="driver-avatar">
                                        <?php if (!empty($d['profile_picture']) && file_exists('../../' . $d['profile_picture'])): ?>
                                            <img src="<?= htmlspecialchars('../../' . $d['profile_picture']) ?>" alt="Profile">
                                        <?php else:
                                            $nameParts = explode(' ', trim($d['name']));
                                            $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? $nameParts[0] ?? '', 0, 1));
                                            echo htmlspecialchars($initials);
                                        endif; ?>
                                    </div>
                                    <div class="driver-details">
                                        <span class="driver-name"><?= htmlspecialchars($d['name']) ?></span>
                                        <span class="driver-location"><?= htmlspecialchars($d['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($d['license_number'] ?: 'N/A') ?></td>
                            <td><?= $d['license_expiry'] ? date('Y-m-d', strtotime($d['license_expiry'])) : '—' ?></td>
                            <td><?= htmlspecialchars($d['contact_number'] ?: '—') ?></td>
                            <td>
                                <span class="compliance-status <?= htmlspecialchars(getComplianceStatus($d['license_expiry'])) ?>">
                                    <?= ucfirst(getComplianceStatus($d['license_expiry'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="rating">
                                    <?= renderRating($d['rating']) ?>
                                    <span class="rating-value"><?= number_format($d['rating'], 1) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-view" title="View Details"
                                        data-bs-toggle="modal" data-bs-target="#viewDriverModal"
                                        onclick="loadDriverView(
                                            '<?= addslashes($d['name']) ?>',
                                            '<?= addslashes($d['email']) ?>',
                                            '<?= addslashes($d['username']) ?>',
                                            '<?= addslashes($d['license_number']) ?>',
                                            '<?= formatDateForInput($d['license_expiry']) ?>',
                                            '<?= addslashes($d['contact_number']) ?>',
                                            '<?= addslashes($d['address']) ?>',
                                            '<?= (float)$d['rating'] ?>'
                                        )">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-action btn-edit" title="Edit Driver"
                                        data-bs-toggle="modal" data-bs-target="#editDriverModal"
                                        onclick="loadDriverEdit(
                                            '<?= addslashes($d['drivers_id']) ?>',
                                            '<?= addslashes($d['user_id']) ?>',
                                            '<?= addslashes($d['name']) ?>',
                                            '<?= addslashes($d['email']) ?>',
                                            '<?= addslashes($d['username']) ?>',
                                            '<?= addslashes($d['license_number']) ?>',
                                            '<?= formatDateForInput($d['license_expiry']) ?>',
                                            '<?= addslashes($d['contact_number']) ?>',
                                            '<?= addslashes($d['address']) ?>',
                                            '<?= (float)$d['rating'] ?>'
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

    <!-- VIEW DRIVER MODAL -->
    <div class="modal fade" id="viewDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Driver Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3"><div class="col-4"><strong>Name:</strong></div><div class="col-8" id="view-name"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>Email:</strong></div><div class="col-8" id="view-email"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>Username:</strong></div><div class="col-8" id="view-username"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>License No:</strong></div><div class="col-8" id="view-license"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>Expiry:</strong></div><div class="col-8" id="view-expiry"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>Contact:</strong></div><div class="col-8" id="view-contact"></div></div>
                    <div class="row mb-3"><div class="col-4"><strong>Address:</strong></div><div class="col-8" id="view-address"></div></div>
                    <div class="row mb-3">
                        <div class="col-4"><strong>Rating:</strong></div>
                        <div class="col-8">
                            <div id="view-rating"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT DRIVER MODAL -->
    <div class="modal fade" id="editDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../backend/update_driver.php">
                    <input type="hidden" name="drivers_id" id="edit-drivers-id">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" id="edit-name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit-email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit-username" pattern="[a-zA-Z0-9._-]+" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" id="edit-license" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Expiry</label>
                            <input type="date" class="form-control" name="license_expiry" id="edit-expiry" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="edit-contact" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit-address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating (0–5)</label>
                            <input type="number" class="form-control" name="rating" id="edit-rating" min="0" max="5" step="0.1" required>
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

    <!-- ADD DRIVER MODAL -->
    <div class="modal fade" id="addDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../backend/add_driver.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" pattern="[a-zA-Z0-9._-]+" 
                                   title="Only letters, numbers, dots, underscores, or hyphens" required>
                            <div class="form-text">Used for login. Must be unique.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" minlength="6" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License Expiry</label>
                            <input type="date" class="form-control" name="license_expiry" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initial Rating</label>
                            <input type="number" class="form-control" name="rating" min="0" max="5" step="0.1" value="3.0" required>
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

        function loadDriverView(name, email, username, license, expiry, contact, address, rating) {
            document.getElementById('view-name').textContent = name;
            document.getElementById('view-email').textContent = email;
            document.getElementById('view-username').textContent = username;
            document.getElementById('view-license').textContent = license;
            document.getElementById('view-expiry').textContent = expiry || '—';
            document.getElementById('view-contact').textContent = contact || '—';
            document.getElementById('view-address').textContent = address || '—';
            
            const ratingEl = document.getElementById('view-rating');
            ratingEl.innerHTML = renderStars(rating) + ' ' + parseFloat(rating).toFixed(1);
        }

        function loadDriverEdit(id, userId, name, email, username, license, expiry, contact, address, rating) {
            document.getElementById('edit-drivers-id').value = id;
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-license').value = license;
            document.getElementById('edit-expiry').value = expiry;
            document.getElementById('edit-contact').value = contact;
            document.getElementById('edit-address').value = address;
            document.getElementById('edit-rating').value = rating;
        }

        function renderStars(rating) {
            rating = Math.max(0, Math.min(5, parseFloat(rating) || 0));
            let html = '<div class="stars" style="color:#f39c12;">';
            const full = Math.floor(rating);
            const hasHalf = (rating % 1) >= 0.5;
            const empty = 5 - full - (hasHalf ? 1 : 0);
            for (let i = 0; i < full; i++) html += '<i class="bi bi-star-fill"></i>';
            if (hasHalf) html += '<i class="bi bi-star-half"></i>';
            for (let i = 0; i < empty; i++) html += '<i class="bi bi-star"></i>';
            html += '</div>';
            return html;
        }

        // Show success/error notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                let msg = success === 'driver_added' ? 'Driver added successfully!' : 
                          success === 'driver_updated' ? 'Driver updated successfully!' : '';
                if (msg) showNotification(msg, 'success');
            }
            if (error) {
                let msg = '';
                if (error === 'missing_fields') msg = 'Please fill all required fields.';
                else if (error === 'email_exists') msg = 'Email already exists.';
                else if (error === 'username_exists') msg = 'Username already taken.';
                else if (error === 'driver_failed') msg = 'Failed to save driver.';
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