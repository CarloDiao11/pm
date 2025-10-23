<?php
// drivers.php - Dynamic Drivers Management Page
define('APP_LOADED', true);
require_once '../../backend/db.php';

// Fetch all drivers
$driversRaw = db_select('drivers', [], ['order_by' => 'license_number ASC']);
$driverList = [];

foreach ($driversRaw as $driverRow) {
    if (empty($driverRow['user_id']) || empty($driverRow['drivers_id'])) {
        continue;
    }

    $userRows = db_select('users', ['user_id' => $driverRow['user_id']], ['limit' => 1]);
    if (empty($userRows)) continue;

    $user = $userRows[0];
    if (($user['role'] ?? '') !== 'driver') {
        continue;
    }

    $driverList[] = [
        'drivers_id'      => $driverRow['drivers_id'],
        'name'            => $user['name'] ?? 'N/A',
        'email'           => $user['email'] ?? '',
        'username'        => $user['username'] ?? '', // ✅ include username
        'license_number'  => $driverRow['license_number'] ?? '',
        'license_expiry'  => $driverRow['license_expiry'] ?? null,
        'contact_number'  => $driverRow['contact_number'] ?? '',
        'rating'          => isset($driverRow['rating']) ? (float)$driverRow['rating'] : 0.0,
        'profile_picture' => $driverRow['profile_picture'] ?? null,
        'user_status'     => $user['status'] ?? 'active'
    ];
}

$drivers = $driverList;

// Helper: Compliance status
function getComplianceStatus($expiry) {
    if (empty($expiry)) return 'pending';
    $expiryDate = new DateTime($expiry);
    $today = new DateTime();
    $daysLeft = (int)$today->diff($expiryDate)->format('%r%a');
    if ($daysLeft < 0) return 'non-compliant';
    if ($daysLeft <= 30) return 'pending';
    return 'compliant';
}

// Helper: Render star rating
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers - Fleet Management System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/drivers.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .driver-avatar {
            width: 40px;
            height: 40px;   
            border-radius: 50%;
            background: #444;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .driver-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
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
                        <th>Username</th> <!-- ✅ Added column -->
                        <th>License No</th>
                        <th>License Expiry</th>
                        <th>Contact Number</th>
                        <th>Compliance Status</th>
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">No drivers found</td>
                        </tr>
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
                                            $initial1 = $nameParts[0] ?? '';
                                            $initial2 = $nameParts[1] ?? $initial1;
                                            $initials = strtoupper(substr($initial1, 0, 1) . substr($initial2, 0, 1));
                                            echo htmlspecialchars($initials);
                                        endif; ?>
                                    </div>
                                    <div class="driver-details">
                                        <span class="driver-name"><?= htmlspecialchars($d['name']) ?></span>
                                        <span class="driver-location"><?= htmlspecialchars($d['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($d['username'] ?: '—') ?></td> <!-- ✅ Show username -->
                            <td><?= htmlspecialchars($d['license_number'] ?: 'N/A') ?></td>
                            <td><?= $d['license_expiry'] ? htmlspecialchars(date('Y-m-d', strtotime($d['license_expiry']))) : '—' ?></td>
                            <td><?= htmlspecialchars($d['contact_number'] ?: '—') ?></td>
                            <td>
                                <span class="compliance-status <?= htmlspecialchars(getComplianceStatus($d['license_expiry'])) ?>">
                                    <?= htmlspecialchars(ucfirst(getComplianceStatus($d['license_expiry']))) ?>
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
                                        onclick="window.location='driver-view.php?id=<?= urlencode($d['drivers_id']) ?>'">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-action btn-edit" title="Edit Driver"
                                        onclick="window.location='driver-edit.php?id=<?= urlencode($d['drivers_id']) ?>'">
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

    <!-- ADD DRIVER MODAL -->
    <div class="modal fade" id="addDriverModal" tabindex="-1" aria-labelledby="addDriverModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDriverModalLabel">Add New Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDriverForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="driverName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="driverName" required>
                        </div>
                        <div class="mb-3">
                            <label for="driverEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="driverEmail" required>
                        </div>
                        <!-- ✅ USERNAME FIELD ADDED HERE -->
                        <div class="mb-3">
                            <label for="driverUsername" class="form-label">Username</label>

                            title="Only letters, numbers, dots, underscores, and hyphens"
                            required>
                            <div class="form-text">Used for login. Must be unique.</div>
                        </div>
                        <div class="mb-3">
                            <label for="driverPassword" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="driverPassword" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="driverPasswordConfirm" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="driverPasswordConfirm" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label for="licenseNo" class="form-label">License Number</label>
                            <input type="text" class="form-control" id="licenseNo" required>
                        </div>
                        <div class="mb-3">
                            <label for="licenseExpiry" class="form-label">License Expiry Date</label>
                            <input type="date" class="form-control" id="licenseExpiry" required>
                        </div>
                        <div class="mb-3">
                            <label for="contactNumber" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contactNumber" placeholder="e.g. 09123456789" required>
                        </div>
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating (1–5)</label>
                            <input type="number" class="form-control" id="rating" min="0" max="5" step="0.1" value="3.0" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveDriverBtn">Save Driver</button>
                </div>
            </div>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passInput = document.getElementById('driverPassword');
            const icon = this.querySelector('i');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });

        // Save driver
        document.getElementById('saveDriverBtn').addEventListener('click', function () {
            const form = document.getElementById('addDriverForm');
            const pass = document.getElementById('driverPassword').value;
            const passConfirm = document.getElementById('driverPasswordConfirm').value;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (pass !== passConfirm) {
                alert('Passwords do not match!');
                return;
            }

            const data = {
                name: document.getElementById('driverName').value.trim(),
                email: document.getElementById('driverEmail').value.trim(),
                username: document.getElementById('driverUsername').value.trim(), // ✅ added
                password: pass,
                license_number: document.getElementById('licenseNo').value.trim(),
                license_expiry: document.getElementById('licenseExpiry').value,
                contact_number: document.getElementById('contactNumber').value.trim(),
                rating: parseFloat(document.getElementById('rating').value) || 0
            };

            fetch('add-driver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Driver added successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to add driver'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('An unexpected error occurred.');
            });
        });

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        document.getElementById('overlay').addEventListener('click', toggleSidebar);
    </script>
</body>
</html>