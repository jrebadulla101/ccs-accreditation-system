<?php
require_once 'db_connect.php';
require_once 'functions.php';

// For non-login pages, require login
if (basename($_SERVER['PHP_SELF']) != 'login.php') {
    requireLogin();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo pageTitle(); ?></title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo $basePath; ?>../assets/images/earist-logo.png" type="image/x-icon">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>../assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php if (isLoggedIn()): ?>
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo $basePath; ?>../assets/images/earist-logo.png" alt="EARIST Logo" class="logo">
                <h2>CCS Accreditation</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="<?php echo $basePath; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    
                    <li>
                        <a href="#"><i class="fas fa-graduation-cap"></i> Programs</a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/programs/list.php">View All Programs</a></li>
                            <?php if (hasPermission('add_program')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/programs/add.php">Add New Program</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li>
                        <a href="#"><i class="fas fa-layer-group"></i> Area Levels</a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/areas/list.php">View All Areas</a></li>
                            <?php if (hasPermission('add_area')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/areas/add.php">Add New Area</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li>
                        <a href="#"><i class="fas fa-clipboard-list"></i> Parameters</a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/parameters/list.php">View All Parameters</a></li>
                            <?php if (hasPermission('add_parameter')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/parameters/add.php">Add New Parameter</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li>
                        <a href="#"><i class="fas fa-file-upload"></i> Evidence</a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/evidence/list.php">View All Evidence</a></li>
                            <?php if (hasPermission('add_evidence')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/evidence/add.php">Upload New Evidence</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <?php if (hasPermission('view_users') || hasPermission('add_user')): ?>
                    <li>
                        <a href="#"><i class="fas fa-users"></i> Users Management</a>
                        <ul class="submenu">
                            <?php if (hasPermission('view_users')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/users/list.php">View All Users</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('add_user')): ?>
                            <li><a href="<?php echo $basePath; ?>modules/users/add.php">Add New User</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('manage_permissions') || hasPermission('view_roles')): ?>
                    <li>
                        <a href="#"><i class="fas fa-user-tag"></i> Roles & Permissions</a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/roles/list.php">Manage Roles</a></li>
                            <li><a href="<?php echo $basePath; ?>modules/roles/permissions.php">Manage Permissions</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('manage_settings')): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link has-submenu">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                            <i class="submenu-arrow fas fa-chevron-right"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="<?php echo $basePath; ?>modules/settings/index.php">General Settings</a></li>
                            <li><a href="<?php echo $basePath; ?>modules/settings/index.php?category=appearance">Appearance</a></li>
                            <li><a href="<?php echo $basePath; ?>modules/settings/index.php?category=security">Security</a></li>
                            <li><a href="<?php echo $basePath; ?>modules/settings/system.php">System Management</a></li>
                            <li><a href="<?php echo $basePath; ?>modules/settings/index.php?category=backup">Backup & Maintenance</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>CCS Accreditation System<br>EARIST Manila</p>
            </div>
        </aside>
        <?php endif; ?>

        <main class="content">
            <?php if (isLoggedIn()): ?>
            <!-- Top Navigation Bar -->
            <header class="topbar">
                <button id="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['admin_name']; ?></span>
                    <div class="dropdown">
                        <button class="dropbtn"><i class="fas fa-user-circle"></i></button>
                        <div class="dropdown-content">
                            <a href="<?php echo $basePath; ?>modules/users/profile.php"><i class="fas fa-user-cog"></i> Profile</a>
                            <a href="<?php echo $basePath; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            <?php endif; ?>

            <!-- Flash Messages -->
            <?php $flash = getFlashMessage(); ?>
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
                <span class="close-btn">&times;</span>
            </div>
            <?php endif; ?>
            
            <div class="content-wrapper"> 