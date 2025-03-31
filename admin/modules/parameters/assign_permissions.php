<?php
// Include configuration before starting session
require_once '../../includes/config.php';

// Start session after config is loaded
session_start();

// Include other necessary files
require_once '../../includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if user has permission to manage parameter permissions
if (!hasPermission('assign_parameters')) {
    setFlashMessage("danger", "You don't have permission to assign parameter permissions.");
    header("Location: ../../dashboard.php");
    exit();
}

// Get parameter_id from URL
$parameterId = isset($_GET['parameter_id']) ? intval($_GET['parameter_id']) : 0;

if ($parameterId <= 0) {
    setFlashMessage("danger", "Parameter ID is required.");
    header("Location: list.php");
    exit();
}

// Get parameter details
$paramQuery = "SELECT p.*, a.name as area_name, a.id as area_id, pr.name as program_name, pr.id as program_id 
               FROM parameters p 
               JOIN area_levels a ON p.area_level_id = a.id 
               JOIN programs pr ON a.program_id = pr.id 
               WHERE p.id = ?";
$paramStmt = $conn->prepare($paramQuery);
$paramStmt->bind_param("i", $parameterId);
$paramStmt->execute();
$paramResult = $paramStmt->get_result();

if ($paramResult->num_rows === 0) {
    setFlashMessage("danger", "Parameter not found.");
    header("Location: list.php");
    exit();
}

$parameter = $paramResult->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $users = isset($_POST['users']) ? $_POST['users'] : [];
    
    // First clear all existing permissions for this parameter
    $clearQuery = "DELETE FROM parameter_user_permissions WHERE parameter_id = ?";
    $clearStmt = $conn->prepare($clearQuery);
    $clearStmt->bind_param("i", $parameterId);
    $clearStmt->execute();
    
    // Insert new permissions
    if (!empty($users)) {
        $insertQuery = "INSERT INTO parameter_user_permissions 
                        (user_id, parameter_id, can_view, can_add, can_edit, can_delete, can_download, can_approve) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        
        foreach ($users as $userId => $permissions) {
            $canView = isset($permissions['view']) ? 1 : 0;
            $canAdd = isset($permissions['add']) ? 1 : 0;
            $canEdit = isset($permissions['edit']) ? 1 : 0;
            $canDelete = isset($permissions['delete']) ? 1 : 0;
            $canDownload = isset($permissions['download']) ? 1 : 0;
            $canApprove = isset($permissions['approve']) ? 1 : 0;
            
            $insertStmt->bind_param("iiiiiiii", $userId, $parameterId, $canView, $canAdd, $canEdit, $canDelete, $canDownload, $canApprove);
            $insertStmt->execute();
        }
    }
    
    // Log activity
    $adminId = $_SESSION['admin_id'];
    $activityType = "parameter_permissions_update";
    $activityDescription = "Updated user permissions for parameter '{$parameter['name']}'";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("issss", $adminId, $activityType, $activityDescription, $ipAddress, $userAgent);
    $logStmt->execute();
    
    setFlashMessage("success", "Parameter permissions updated successfully.");
    header("Location: view.php?id=" . $parameterId);
    exit();
}

// Get all active users
$usersQuery = "SELECT id, username, full_name, email, role FROM admin_users WHERE status = 'active' ORDER BY full_name ASC";
$usersResult = $conn->query($usersQuery);

// Get current parameter permissions
$currentPermissionsQuery = "SELECT * FROM parameter_user_permissions WHERE parameter_id = ?";
$currentPermissionsStmt = $conn->prepare($currentPermissionsQuery);
$currentPermissionsStmt->bind_param("i", $parameterId);
$currentPermissionsStmt->execute();
$currentPermissionsResult = $currentPermissionsStmt->get_result();

$currentPermissions = [];
while ($row = $currentPermissionsResult->fetch_assoc()) {
    $currentPermissions[$row['user_id']] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parameter Permissions - CCS Accreditation System</title>
    
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
    
    /* Page Header Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
    }
    
    .breadcrumb-container {
        display: flex;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 14px;
    }
    
    .breadcrumb-item {
        color: var(--text-muted);
    }
    
    .breadcrumb-item.active {
        color: var(--text-color);
    }
    
    .breadcrumb-item:not(.active):after {
        content: "/";
        margin-left: 0.5rem;
        color: var(--text-muted);
    }
    
    /* Table Styles */
    .table-container {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .table-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: var(--bg-light);
    }
    
    .table-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
    
    .table-actions {
        display: flex;
        gap: 10px;
    }
    
    .table-body {
        padding: 20px;
    }
    
    .permissions-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .permissions-table th, 
    .permissions-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    
    .permissions-table th {
        background-color: var(--bg-light);
        font-weight: 600;
        color: var(--text-color);
    }
    
    .permissions-table .text-center {
        text-align: center;
    }
    
    .user-info-cell {
        display: flex;
        flex-direction: column;
    }
    
    .user-info-cell strong {
        margin-bottom: 4px;
    }
    
    .user-info-cell small {
        color: var(--text-muted);
        font-size: 12px;
    }
    
    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 16px;
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
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
    }
    
    .btn-warning {
        background-color: var(--warning);
        color: #212529;
    }
    
    .btn-warning:hover {
        background-color: #d9a700;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
    
    .permission-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .alert {
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid transparent;
    }
    
    .alert-info {
        background-color: rgba(74, 144, 226, 0.1);
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    
    /* Responsive Styles */
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
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .table-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .table-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .permissions-table {
            min-width: 800px;
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
                    
                    <li class="menu-item">
                        <a href="#" class="menu-link">
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
                    
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
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
                                $roleQuery = "SELECT name FROM roles WHERE id = ?";
                                $stmt = $conn->prepare($roleQuery);
                                $stmt->bind_param("i", $_SESSION['admin_id']);
                                $stmt->execute();
                                $roleResult = $stmt->get_result();
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
                    <h1>Manage Parameter Permissions: <?php echo htmlspecialchars($parameter['name']); ?></h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                            <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo htmlspecialchars($parameter['program_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo htmlspecialchars($parameter['area_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="view.php?id=<?php echo $parameterId; ?>"><?php echo htmlspecialchars($parameter['name']); ?></a></li>
                            <li class="breadcrumb-item active">Manage Permissions</li>
                        </ol>
                    </nav>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="table-title">User Permissions for <?php echo htmlspecialchars($parameter['name']); ?></h2>
                        <div class="table-actions">
                            <a href="view.php?id=<?php echo $parameterId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Parameter</a>
                        </div>
                    </div>
                    
                    <div class="table-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Set specific permissions for each user for this parameter. These permissions override the area-level permissions.
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?parameter_id=" . $parameterId); ?>" method="post">
                            <div class="permission-actions">
                                <button type="button" id="select-all-view" class="btn btn-sm btn-secondary">Select All View</button>
                                <button type="button" id="select-all-add" class="btn btn-sm btn-secondary">Select All Add</button>
                                <button type="button" id="select-all-download" class="btn btn-sm btn-secondary">Select All Download</button>
                                <button type="button" id="clear-all" class="btn btn-sm btn-warning">Clear All</button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="permissions-table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th class="text-center">View</th>
                                            <th class="text-center">Add</th>
                                            <th class="text-center">Edit</th>
                                            <th class="text-center">Delete</th>
                                            <th class="text-center">Download</th>
                                            <th class="text-center">Approve</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                                            <?php while ($user = $usersResult->fetch_assoc()): ?>
                                                <?php 
                                                // Skip super_admin users as they have all permissions by default
                                                if ($user['role'] == 1) continue;
                                                
                                                $hasView = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_view'] == 1;
                                                $hasAdd = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_add'] == 1;
                                                $hasEdit = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_edit'] == 1;
                                                $hasDelete = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_delete'] == 1;
                                                $hasDownload = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_download'] == 1;
                                                $hasApprove = isset($currentPermissions[$user['id']]) && $currentPermissions[$user['id']]['can_approve'] == 1;
                                                
                                                // Fetch role name
                                                $roleName = "User";
                                                $roleQuery = "SELECT name FROM roles WHERE id = ?";
                                                $stmt = $conn->prepare($roleQuery);
                                                $stmt->bind_param("i", $user['role']);
                                                $stmt->execute();
                                                $roleResult = $stmt->get_result();
                                                if ($roleResult && $roleResult->num_rows > 0) {
                                                    $roleName = $roleResult->fetch_assoc()['name'];
                                                }
                                                ?>
                                                <tr>
                                                    <td class="user-info-cell">
                                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                        <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($roleName); ?></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][view]" value="1" class="view-checkbox" <?php echo $hasView ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][add]" value="1" class="add-checkbox" <?php echo $hasAdd ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][edit]" value="1" class="edit-checkbox" <?php echo $hasEdit ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][delete]" value="1" class="delete-checkbox" <?php echo $hasDelete ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][download]" value="1" class="download-checkbox" <?php echo $hasDownload ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="users[<?php echo $user['id']; ?>][approve]" value="1" class="approve-checkbox" <?php echo $hasApprove ? 'checked' : ''; ?>>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No users found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="form-actions">
                                <a href="view.php?id=<?php echo $parameterId; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Permissions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize Particles.js
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof particlesJS !== 'undefined' && document.getElementById('particles-js')) {
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
    
    // Permission checkbox management
    document.addEventListener('DOMContentLoaded', function() {
        // Select all view checkboxes
        const selectAllViewBtn = document.getElementById('select-all-view');
        if (selectAllViewBtn) {
            selectAllViewBtn.addEventListener('click', function() {
                const viewCheckboxes = document.querySelectorAll('.view-checkbox');
                viewCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
        }
        
        // Select all add checkboxes
        const selectAllAddBtn = document.getElementById('select-all-add');
        if (selectAllAddBtn) {
            selectAllAddBtn.addEventListener('click', function() {
                const addCheckboxes = document.querySelectorAll('.add-checkbox');
                addCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
        }
        
        // Select all download checkboxes
        const selectAllDownloadBtn = document.getElementById('select-all-download');
        if (selectAllDownloadBtn) {
            selectAllDownloadBtn.addEventListener('click', function() {
                const downloadCheckboxes = document.querySelectorAll('.download-checkbox');
                downloadCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
        }
        
        // Clear all checkboxes
        const clearAllBtn = document.getElementById('clear-all');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function() {
                const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
                allCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            });
        }
    });
    
    // User dropdown toggle
    document.addEventListener('DOMContentLoaded', function() {
        const avatar = document.querySelector('.avatar');
        if (avatar) {
            const userDropdown = document.createElement('div');
            userDropdown.className = 'user-dropdown';
            userDropdown.innerHTML = `
                <ul class="dropdown-menu">
                    <li><a href="../users/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="../users/change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
                    <li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            `;
            
            avatar.parentNode.style.position = 'relative';
            avatar.parentNode.appendChild(userDropdown);
            
            avatar.addEventListener('click', function(e) {
                userDropdown.classList.toggle('show');
                e.stopPropagation();
            });
            
            // Close dropdown when clicking elsewhere
            document.addEventListener('click', function() {
                userDropdown.classList.remove('show');
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
                        if (submenu.style.maxHeight === '1000px') {
                            submenu.style.maxHeight = '0';
                            item.classList.remove('active');
                        } else {
                            submenu.style.maxHeight = '1000px';
                            item.classList.add('active');
                        }
                    } else {
                        // Mobile behavior - slide toggle
                        if (submenu.style.maxHeight === '1000px') {
                            submenu.style.maxHeight = '0';
                            item.classList.remove('active');
                        } else {
                            submenu.style.maxHeight = '1000px';
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