<?php
// Start session and include necessary files
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if user has permission to view programs
if (!hasPermission('view_all_programs') && !hasPermission('view_assigned_programs')) {
    header("Location: ../../dashboard.php");
    exit();
}

// Get all programs based on permission
if (hasPermission('view_all_programs')) {
    $query = "SELECT * FROM programs ORDER BY name ASC";
    $result = $conn->query($query);
} else {
    // Get only assigned programs
    $userId = $_SESSION['admin_id'];
    $query = "SELECT p.* FROM programs p
              JOIN program_users pu ON p.id = pu.program_id
              WHERE pu.user_id = ? ORDER BY p.name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs - CCS Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
    /* Base Variables and Reset */
    :root {
        --primary-color: #4a90e2;
        --accent-color: #5C6BC0;
        --text-color: #333333;
        --text-muted: #6c757d;
        --bg-color: #f5f7fa;
        --bg-light: #eef2f7;
        --card-bg: #ffffff;
        --border-color: #e0e6ed;
        --sidebar-width: 250px;
        --header-height: 60px;
        --danger: #ff3b30;
        --success: #34c759;
        --warning: #f8c200;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--bg-color);
        color: var(--text-color);
        line-height: 1.6;
        overflow-x: hidden;
    }
    
    a {
        text-decoration: none;
        color: var(--primary-color);
    }
    
    ul {
        list-style: none;
    }
    
    /* Layout Structure */
    .admin-container {
        display: flex;
        min-height: 100vh;
    }
    
    .sidebar {
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
        color: white;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    .header {
        height: var(--header-height);
        background-color: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        z-index: 999;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .content {
        padding: 80px 20px 20px;
        flex: 1;
    }
    
    #particles-js {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: -1;
    }
    
    /* Sidebar Styles */
    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
    }
    
    .sidebar-header img {
        max-width: 40px;
        margin-right: 10px;
    }
    
    .sidebar-header h1 {
        font-size: 18px;
        font-weight: 600;
        color: white;
    }
    
    .sidebar-menu {
        padding: 20px 0;
    }
    
    .menu-item {
        position: relative;
    }
    
    .menu-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: rgba(255,255,255,0.7);
        transition: all 0.3s ease;
    }
    
    .menu-link:hover {
        background-color: rgba(255,255,255,0.1);
        color: white;
    }
    
    .menu-link.active {
        background-color: var(--primary-color);
        color: white;
    }
    
    .menu-link i {
        width: 20px;
        text-align: center;
        margin-right: 10px;
    }
    
    .submenu {
        background-color: rgba(0,0,0,0.2);
        display: none;
    }
    
    .submenu-link {
        display: block;
        padding: 10px 20px 10px 50px;
        color: rgba(255,255,255,0.6);
        transition: all 0.3s ease;
    }
    
    .submenu-link:hover {
        color: white;
        background-color: rgba(255,255,255,0.05);
    }
    
    .menu-item.active .submenu {
        display: block;
    }
    
    .submenu-arrow {
        margin-left: auto;
        transition: transform 0.3s ease;
    }
    
    .menu-item.active .submenu-arrow {
        transform: rotate(90deg);
    }
    
    .sidebar-footer {
        padding: 15px 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
        position: absolute;
        bottom: 0;
        width: 100%;
        background: rgba(0,0,0,0.2);
    }
    
    /* Header Styles */
    .search-bar {
        flex: 1;
        max-width: 400px;
        margin-right: 20px;
    }
    
    .search-input {
        width: 100%;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        background-color: var(--bg-light);
    }
    
    .user-menu {
        display: flex;
        align-items: center;
    }
    
    .user-info {
        margin-right: 15px;
        text-align: right;
    }
    
    .user-name {
        font-weight: 500;
        font-size: 14px;
    }
    
    .user-role {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--primary-color);
        color: white;
        font-weight: 500;
    }
    
    /* Program List Specific Styles */
    .program-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: white;
        font-size: 20px;
    }
    
    .stat-details h3 {
        font-size: 14px;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    
    .stat-count {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-color);
    }
    
    .card {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
    
    .card-actions {
        display: flex;
        gap: 10px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .program-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    .program-card {
        background-color: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    
    .program-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .program-card-header {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .program-card-title {
        display: flex;
        flex-direction: column;
    }
    
    .program-card-title h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 5px 0;
    }
    
    .program-card-code {
        font-size: 12px;
        color: var(--text-muted);
        background-color: var(--bg-light);
        padding: 2px 8px;
        border-radius: 20px;
        align-self: flex-start;
    }
    
    .program-card-body {
        padding: 15px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .program-card-description {
        margin-bottom: 15px;
        font-size: 14px;
        color: var(--text-muted);
        flex: 1;
    }
    
    .program-card-stats {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }
    
    .program-card-stat {
        text-align: center;
    }
    
    .program-card-stat-value {
        font-size: 18px;
        font-weight: 600;
        color: var(--accent-color);
    }
    
    .program-card-stat-label {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .status-badge-container {
        display: flex;
        justify-content: flex-end;
    }
    
    .status-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background-color: rgba(52, 199, 89, 0.1);
        color: var(--success);
    }
    
    .status-inactive {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }
    
    .program-card-footer {
        padding: 15px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .program-card-date {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .program-card-actions {
        display: flex;
        gap: 5px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #3a80d1;
    }
    
    .btn-info {
        background-color: #17a2b8;
        color: white;
    }
    
    .btn-info:hover {
        background-color: #138496;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-state-icon {
        font-size: 48px;
        color: #c9d2e0;
        margin-bottom: 15px;
    }
    
    .empty-state-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .empty-state-description {
        font-size: 14px;
        color: var(--text-muted);
        max-width: 500px;
        margin: 0 auto 20px;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
    }
    
    /* Responsive Styles */
    @media (max-width: 1200px) {
        .program-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .program-stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .header {
            left: 0;
        }
        
        .menu-toggle {
            display: block;
        }
    }
    
    @media (max-width: 768px) {
        .program-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    <!-- Particles.js background -->
    <div id="particles-js"></div>
    
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../../assets/images/logo.png" alt="EARIST Logo">
                <h1>CCS Accreditation</h1>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li class="menu-item">
                        <a href="../../dashboard.php" class="menu-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Programs</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../programs/list.php" class="submenu-link">View All Programs</a></li>
                            <?php if (hasPermission('add_program')): ?>
                            <li><a href="../programs/add.php" class="submenu-link">Add New Program</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-layer-group"></i>
                            <span>Area Levels</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../areas/list.php" class="submenu-link">View All Areas</a></li>
                            <?php if (hasPermission('add_area')): ?>
                            <li><a href="../areas/add.php" class="submenu-link">Add New Area</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Parameters</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../parameters/list.php" class="submenu-link">View All Parameters</a></li>
                            <?php if (hasPermission('add_parameter')): ?>
                            <li><a href="../parameters/add.php" class="submenu-link">Add New Parameter</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Evidence</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../evidence/list.php" class="submenu-link">View All Evidence</a></li>
                            <?php if (hasPermission('add_evidence')): ?>
                            <li><a href="../evidence/add.php" class="submenu-link">Upload New Evidence</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <?php if (hasPermission('view_users')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-users"></i>
                            <span>Users Management</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../users/list.php" class="submenu-link">View All Users</a></li>
                            <?php if (hasPermission('add_user')): ?>
                            <li><a href="../users/add.php" class="submenu-link">Add New User</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('view_roles')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-user-tag"></i>
                            <span>Roles & Permissions</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../roles/list.php" class="submenu-link">View All Roles</a></li>
                            <?php if (hasPermission('add_role')): ?>
                            <li><a href="../roles/add.php" class="submenu-link">Add New Role</a></li>
                            <?php endif; ?>
                            <li><a href="../roles/permissions.php" class="submenu-link">Manage Permissions</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('view_settings')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../settings/system.php" class="submenu-link">System Settings</a></li>
                            <li><a href="../settings/backup.php" class="submenu-link">Backup Database</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <a href="../../logout.php" class="menu-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search...">
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo $_SESSION['admin_name']; ?>
                        </div>
                        <div class="user-role">
                            <?php 
                            // Use try-catch to handle any database errors gracefully
                            try {
                                $roleQuery = "SELECT r.name FROM admin_users au 
                                            JOIN roles r ON au.role = r.id 
                                            WHERE au.id = " . $_SESSION['admin_id'];
                                $roleResult = $conn->query($roleQuery);
                                if ($roleResult && $roleResult->num_rows > 0) {
                                    echo $roleResult->fetch_assoc()['name'];
                                } else {
                                    echo "User";
                                }
                            } catch (Exception $e) {
                                echo "System User";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="page-header">
                    <h1>Programs / Courses</h1>
                </div>

                <div class="program-stats-row">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Total Programs</h3>
                            <p class="stat-count"><?php echo $result ? $result->num_rows : 0; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Areas</h3>
                            <p class="stat-count">
                                <?php
                                $areaQuery = "SELECT COUNT(*) as count FROM area_levels";
                                $areaResult = $conn->query($areaQuery);
                                echo $areaResult->fetch_assoc()['count'];
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Parameters</h3>
                            <p class="stat-count">
                                <?php
                                $paramQuery = "SELECT COUNT(*) as count FROM parameters";
                                $paramResult = $conn->query($paramQuery);
                                echo $paramResult->fetch_assoc()['count'];
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Evidence Files</h3>
                            <p class="stat-count">
                                <?php
                                $evidenceQuery = "SELECT COUNT(*) as count FROM parameter_evidence";
                                $evidenceResult = $conn->query($evidenceQuery);
                                echo $evidenceResult->fetch_assoc()['count'];
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">All Programs</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('add_program')): ?>
                            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Program</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="program-grid">
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Get area count for this program
                                    $areaCountQuery = "SELECT COUNT(*) as count FROM area_levels WHERE program_id = " . $row['id'];
                                    $areaCountResult = $conn->query($areaCountQuery);
                                    $areaCount = $areaCountResult->fetch_assoc()['count'];
                                    
                                    // Get parameter count for this program
                                    $paramCountQuery = "SELECT COUNT(*) as count FROM parameters p 
                                                      JOIN area_levels a ON p.area_level_id = a.id 
                                                      WHERE a.program_id = " . $row['id'];
                                    $paramCountResult = $conn->query($paramCountQuery);
                                    $paramCount = $paramCountResult->fetch_assoc()['count'];
                                    
                                    // Get evidence count for this program
                                    $evidenceCountQuery = "SELECT COUNT(*) as count FROM parameter_evidence pe 
                                                         JOIN parameters p ON pe.parameter_id = p.id 
                                                         JOIN area_levels a ON p.area_level_id = a.id 
                                                         WHERE a.program_id = " . $row['id'];
                                    $evidenceCountResult = $conn->query($evidenceCountQuery);
                                    $evidenceCount = $evidenceCountResult->fetch_assoc()['count'];
                                ?>
                                    <div class="program-card">
                                        <div class="program-card-header">
                                            <div class="program-card-title">
                                                <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                                                <span class="program-card-code"><?php echo htmlspecialchars($row['code']); ?></span>
                                            </div>
                                        </div>
                                        <div class="program-card-body">
                                            <div class="program-card-description">
                                                <?php echo htmlspecialchars(substr($row['description'], 0, 200)) . (strlen($row['description']) > 200 ? '...' : ''); ?>
                                            </div>
                                            
                                            <div class="program-card-stats">
                                                <div class="program-card-stat">
                                                    <div class="program-card-stat-value"><?php echo $areaCount; ?></div>
                                                    <div class="program-card-stat-label">Areas</div>
                                                </div>
                                                <div class="program-card-stat">
                                                    <div class="program-card-stat-value"><?php echo $paramCount; ?></div>
                                                    <div class="program-card-stat-label">Parameters</div>
                                                </div>
                                                <div class="program-card-stat">
                                                    <div class="program-card-stat-value"><?php echo $evidenceCount; ?></div>
                                                    <div class="program-card-stat-label">Evidence</div>
                                                </div>
                                            </div>
                                            
                                            <div class="status-badge-container">
                                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="program-card-footer">
                                            <div class="program-card-date">
                                                <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                            </div>
                                            <div class="program-card-actions">
                                                <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i> View</a>
                                                
                                                <?php if (hasPermission('edit_program')): ?>
                                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                                <?php endif; ?>
                                                
                                                <a href="../areas/list.php?program_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-secondary" title="View Areas"><i class="fas fa-layer-group"></i> Areas</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h3 class="empty-state-title">No Programs Found</h3>
                                <p class="empty-state-description">There are no programs available in the system yet.</p>
                                
                                <?php if (hasPermission('add_program')): ?>
                                <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Program</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Initialize Particles.js
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('particles-js')) {
            particlesJS('particles-js', {
                "particles": {
                    "number": {
                        "value": 80,
                        "density": {
                            "enable": true,
                            "value_area": 800
                        }
                    },
                    "color": {
                        "value": "#4a90e2"
                    },
                    "shape": {
                        "type": "circle",
                        "stroke": {
                            "width": 0,
                            "color": "#000000"
                        },
                    },
                    "opacity": {
                        "value": 0.1,
                        "random": false,
                    },
                    "size": {
                        "value": 3,
                        "random": true,
                    },
                    "line_linked": {
                        "enable": true,
                        "distance": 150,
                        "color": "#4a90e2",
                        "opacity": 0.1,
                        "width": 1
                    },
                    "move": {
                        "enable": true,
                        "speed": 2,
                        "direction": "none",
                        "random": false,
                        "straight": false,
                        "out_mode": "out",
                        "bounce": false,
                    }
                },
                "interactivity": {
                    "detect_on": "canvas",
                    "events": {
                        "onhover": {
                            "enable": true,
                            "mode": "grab"
                        },
                        "onclick": {
                            "enable": true,
                            "mode": "push"
                        },
                        "resize": true
                    },
                    "modes": {
                        "grab": {
                            "distance": 140,
                            "line_linked": {
                                "opacity": 1
                            }
                        },
                        "push": {
                            "particles_nb": 4
                        }
                    }
                },
                "retina_detect": true
            });
        }
    });
    
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    if (window.innerWidth < 992 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        }
    });
    
    // Toggle submenu in sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(function(item) {
            const link = item.querySelector('.menu-link');
            const submenu = item.querySelector('.submenu');
            
            if (link && submenu) {
                link.addEventListener('click', function(e) {
                    if (window.innerWidth > 992) {
                        if (submenu.style.display === 'block') {
                            submenu.style.display = 'none';
                            item.classList.remove('active');
                        } else {
                            submenu.style.display = 'block';
                            item.classList.add('active');
                        }
                    } else {
                        // Mobile behavior - slide toggle
                        if (submenu.style.height === 'auto' || submenu.style.height === submenu.scrollHeight + 'px') {
                            submenu.style.height = '0';
                            item.classList.remove('active');
                        } else {
                            submenu.style.height = submenu.scrollHeight + 'px';
                            item.classList.add('active');
                        }
                    }
                    e.preventDefault();
                });
            }
        });
    });
    </script>
</body>
</html> 