<?php
// Sidebar component - Pass $activePage variable to highlight active menu item
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <img src="images/logo.jpg" alt="Viros Logo" class="logo-img">
        <h2>Production MS</h2>
    </div>
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item <?php echo ($activePage == 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="line-management.php" class="nav-item <?php echo ($activePage == 'line-management') ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>Line Management</span>
        </a>
        <a href="part-management.php" class="nav-item <?php echo ($activePage == 'part-management') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Part Management</span>
        </a>
        <a href="stages-management.php" class="nav-item <?php echo ($activePage == 'stages-management') ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i>
            <span>Stages Management</span>
        </a>
        <a href="wing-scale-management.php" class="nav-item <?php echo ($activePage == 'wing-scale-management') ? 'active' : ''; ?>">
            <i class="fas fa-cart-shopping"></i>
            <span>Bin Management</span>
        </a>
        <a href="admin-material-in.php" class="nav-item <?php echo ($activePage == 'material-in') ? 'active' : ''; ?>">
            <i class="fas fa-inbox"></i>
            <span>Material Management</span>
        </a>
        <a href="production-report.php" class="nav-item <?php echo ($activePage == 'production-report') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Production Report</span>
        </a>
    </nav>
</div>
