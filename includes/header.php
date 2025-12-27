<?php
// Header component - Pass $pageTitle and $current_user variables
?>
<!-- Header -->
<header class="header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></h1>
    </div>
    <div class="header-right">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search..." id="searchInput">
        </div>
        <div class="notifications">
            <i class="fas fa-bell"></i>
            <span class="badge">3</span>
        </div>
        <div class="user-profile" id="userProfile">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user); ?>&background=667eea&color=fff" alt="User">
            <div class="dropdown">
                <span><?php echo htmlspecialchars($current_user); ?></span>
                <i class="fas fa-chevron-down"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>
