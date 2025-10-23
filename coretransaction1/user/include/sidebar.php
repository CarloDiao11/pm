<!-- Sidebar Overlay (Mobile only) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="app-logo">
            <i class="fas fa-truck"></i>
            <span class="app-title">Driver App</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="trip.php" class="nav-item">
            <i class="fas fa-route"></i>
            <span class="nav-label">Trips</span>
        </a>
        <a href="wallet.php" class="nav-item">
            <i class="fas fa-wallet"></i>
            <span class="nav-label">Wallet</span>
        </a>
        <a href="fuel.php" class="nav-item">
            <i class="fa-solid fa-gas-pump"></i>
            <span class="nav-label">Fuel</span>
        </a>
        <a href="storage.php" class="nav-item">
            <i class="fa-solid fa-warehouse"></i>
            <span class="nav-label">Storage</span>
        </a>
        <a href="document.php" class="nav-item">
            <i class="fa-solid fa-file"></i>
            <span class="nav-label">Document</span>
        </a>
        <a href="notifications.php" class="nav-item">
            <i class="fas fa-bell"></i>
            <span class="nav-label">Notifications</span>
        </a>
    
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span class="nav-label">Profile</span>
        </a>
        
    </nav>
</aside>

<script>
    // Utility: Check if mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (isMobile()) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    }

    // ✅ Auto-set active sidebar item based on current page
    function setActiveNavItem() {
        const currentPage = window.location.pathname.split('/').pop(); // e.g., "trip.php"
        const navItems = document.querySelectorAll('.nav-item');

        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && href === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', () => {
        setActiveNavItem();
    });

    // Optional: Close sidebar when resizing to desktop
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
    });
</script>