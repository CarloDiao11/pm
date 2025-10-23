<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><i class="bi bi-speedometer2"></i> Fleet Manager</h3>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="trips.php" class="<?php echo ($current_page == 'trips.php') ? 'active' : ''; ?>">
                <i class="bi bi-send"></i>
                <span>Dispatch & Trips</span>
            </a>
        </li>
        <li>
            <a href="fleet.php" class="<?php echo ($current_page == 'fleet.php') ? 'active' : ''; ?>">
                <i class="bi bi-truck"></i>
                <span>Fleet Management</span>
            </a>
        </li>
        <li>
            <a href="drivers.php" class="<?php echo ($current_page == 'drivers.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i>
                <span>Drivers</span>
            </a>
        </li>
        <li>
            <a href="drivers-documents.php" class="<?php echo ($current_page == 'drivers-documents.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span>Drivers Documents</span>
            </a>
        </li>
        <li>
            <a href="wallet.php" class="<?php echo ($current_page == 'wallet.php') ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i>
                <span>Wallet & Earnings</span>
            </a>
        </li>
        <li>
            <a href="fuel.php" class="<?php echo ($current_page == 'fuel.php') ? 'active' : ''; ?>">
                <i class="bi bi-fuel-pump"></i>
                <span>Fuel & Consumables</span>
            </a>
        </li>
        <li>
            <a href="store.php" class="<?php echo ($current_page == 'store.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i>
                <span>Store Room & Supplies</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</div>