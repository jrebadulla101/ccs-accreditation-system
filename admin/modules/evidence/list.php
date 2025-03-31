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

// Get parameter_id or sub_parameter_id from URL
$parameterId = isset($_GET['parameter_id']) ? intval($_GET['parameter_id']) : 0;
$subParameterId = isset($_GET['sub_parameter_id']) ? intval($_GET['sub_parameter_id']) : 0;
$isSubParameter = ($subParameterId > 0);

// If no parameter_id or sub_parameter_id, show parameter selection interface
if ($parameterId <= 0 && $subParameterId <= 0) {
    // Show parameter selection interface
    $showParameterSelection = true;
    
    // Get all parameters
    $paramQuery = "SELECT p.id, p.name, a.name as area_name, pr.name as program_name 
                  FROM parameters p 
                  JOIN area_levels a ON p.area_level_id = a.id 
                  JOIN programs pr ON a.program_id = pr.id 
                  ORDER BY pr.name, a.name, p.name";
    $paramResult = $conn->query($paramQuery);
    
    // Get all sub-parameters
    $subParamQuery = "SELECT sp.id, sp.name, p.name as parameter_name, p.id as parameter_id,
                     a.name as area_name, pr.name as program_name
                     FROM sub_parameters sp
                     JOIN parameters p ON sp.parameter_id = p.id
                     JOIN area_levels a ON p.area_level_id = a.id
                     JOIN programs pr ON a.program_id = pr.id
                     ORDER BY pr.name, a.name, p.name, sp.name";
    $subParamResult = $conn->query($subParamQuery);
} else {
    // Get data for specific parameter or sub-parameter
    $showParameterSelection = false;
    
    if ($isSubParameter) {
        // Get sub-parameter details
        $subParamQuery = "SELECT sp.*, p.name as parameter_name, p.id as parameter_id,
                         a.name as area_name, a.id as area_id, 
                         pr.name as program_name, pr.id as program_id
                         FROM sub_parameters sp
                         JOIN parameters p ON sp.parameter_id = p.id
                         JOIN area_levels a ON p.area_level_id = a.id
                         JOIN programs pr ON a.program_id = pr.id
                         WHERE sp.id = ?";
        $subParamStmt = $conn->prepare($subParamQuery);
        $subParamStmt->bind_param("i", $subParameterId);
        $subParamStmt->execute();
        $subParamResult = $subParamStmt->get_result();
        
        if ($subParamResult->num_rows === 0) {
            setFlashMessage("danger", "Sub-Parameter not found.");
            header("Location: ../parameters/list.php");
            exit();
        }
        
        $subParameter = $subParamResult->fetch_assoc();
        $parameterId = $subParameter['parameter_id']; // Set parameter ID for breadcrumbs
        
        // Get evidence for this sub-parameter
        $evidenceQuery = "SELECT e.*, u.full_name as uploaded_by_name, p.name as parameter_name 
                         FROM parameter_evidence e 
                         LEFT JOIN admin_users u ON e.uploaded_by = u.id 
                         JOIN parameters p ON e.parameter_id = p.id 
                         JOIN sub_parameters sp ON p.id = sp.parameter_id  
                         WHERE sp.id = ?";
        $evidenceStmt = $conn->prepare($evidenceQuery);
        $evidenceStmt->bind_param("i", $subParameterId);
        $evidenceStmt->execute();
        $evidenceResult = $evidenceStmt->get_result();
        
        // Get parameter info for breadcrumbs
        $paramQuery = "SELECT p.*, a.name as area_name, a.id as area_id, pr.name as program_name, pr.id as program_id 
                      FROM parameters p 
                      JOIN area_levels a ON p.area_level_id = a.id 
                      JOIN programs pr ON a.program_id = pr.id 
                      WHERE p.id = ?";
        $paramStmt = $conn->prepare($paramQuery);
        $paramStmt->bind_param("i", $parameterId);
        $paramStmt->execute();
        $paramResult = $paramStmt->get_result();
        
        if ($paramResult->num_rows > 0) {
            $parameter = $paramResult->fetch_assoc();
        }
    } else {
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
            header("Location: ../parameters/list.php");
            exit();
        }
        
        $parameter = $paramResult->fetch_assoc();
        
        // Get evidence for this parameter (excluding sub-parameters)
        $evidenceQuery = "SELECT e.*, u.full_name as uploaded_by_name, p.name as parameter_name 
                         FROM parameter_evidence e 
                         LEFT JOIN admin_users u ON e.uploaded_by = u.id 
                         JOIN parameters p ON e.parameter_id = p.id 
                         WHERE e.parameter_id = ?";
        $evidenceStmt = $conn->prepare($evidenceQuery);
        $evidenceStmt->bind_param("i", $parameterId);
        $evidenceStmt->execute();
        $evidenceResult = $evidenceStmt->get_result();
    }
}

// Check if user has permission to view evidence
if (!hasPermission('view_all_evidence')) {
    setFlashMessage("danger", "You don't have permission to view evidence.");
    header("Location: ../../dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence List - CCS Accreditation System</title>
    
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
    
    /* Card Styles */
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
        background-color: var(--bg-light);
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
    
    .card-body {
        padding: 20px;
    }
    
    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .table th,
    .table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    
    .table th {
        font-weight: 600;
        background-color: var(--bg-light);
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: var(--bg-light);
    }
    
    /* Badge Styles */
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        color: white;
    }
    
    .badge-info {
        background-color: var(--primary-color);
    }
    
    .badge-warning {
        background-color: var(--warning);
        color: #212529;
    }
    
    .badge-success {
        background-color: var(--success);
    }
    
    .badge-danger {
        background-color: var(--danger);
    }
    
    .badge-secondary {
        background-color: var(--text-muted);
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
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
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
    
    .btn-info {
        background-color: #17a2b8;
        color: white;
    }
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-success {
        background-color: var(--success);
        color: white;
    }
    
    .btn-warning {
        background-color: var(--warning);
        color: #212529;
    }
    
    .btn-group {
        display: flex;
        gap: 5px;
    }
    
    /* Alert Styles */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    
    .alert-info {
        color: #31708f;
        background-color: #d9edf7;
        border-color: #bce8f1;
    }
    
    .alert-success {
        color: #3c763d;
        background-color: #dff0d8;
        border-color: #d6e9c6;
    }
    
    .alert-danger {
        color: #a94442;
        background-color: #f2dede;
        border-color: #ebccd1;
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
    }
    
    /* Utility Classes */
    .mt-4 {
        margin-top: 1.5rem;
    }
    
    .d-flex {
        display: flex;
    }
    
    .justify-content-between {
        justify-content: space-between;
    }
    
    .align-items-center {
        align-items: center;
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
                    
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
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
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?>">
                        <?php echo $_SESSION['flash_message']['message']; ?>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <?php if ($showParameterSelection): ?>
                <div class="page-header">
                    <h1>Select Parameter to View Evidence</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Evidence</li>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Available Parameters</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($paramResult && $paramResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Program</th>
                                            <th>Area</th>
                                            <th>Parameter</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($param = $paramResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($param['program_name']); ?></td>
                                                <td><?php echo htmlspecialchars($param['area_name']); ?></td>
                                                <td><?php echo htmlspecialchars($param['name']); ?></td>
                                                <td>
                                                    <a href="list.php?parameter_id=<?php echo $param['id']; ?>" class="btn btn-sm btn-primary" title="View Evidence"><i class="fas fa-file-alt"></i> View Evidence</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <p>No parameters found in the system. Please add parameters first.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($subParamResult && $subParamResult->num_rows > 0): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h2 class="card-title">Available Sub-Parameters</h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Area</th>
                                        <th>Parameter</th>
                                        <th>Sub-Parameter</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subParam = $subParamResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subParam['program_name']); ?></td>
                                            <td><?php echo htmlspecialchars($subParam['area_name']); ?></td>
                                            <td><?php echo htmlspecialchars($subParam['parameter_name']); ?></td>
                                            <td><?php echo htmlspecialchars($subParam['name']); ?></td>
                                            <td>
                                                <a href="list.php?sub_parameter_id=<?php echo $subParam['id']; ?>" class="btn btn-sm btn-primary" title="View Evidence"><i class="fas fa-file-alt"></i> View Evidence</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="page-header">
                    <h1>
                        <?php if ($isSubParameter): ?>
                            Evidence for Sub-Parameter: <?php echo htmlspecialchars($subParameter['name']); ?>
                        <?php else: ?>
                            Evidence for Parameter: <?php echo htmlspecialchars($parameter['name']); ?>
                        <?php endif; ?>
                    </h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                            
                            <?php if ($isSubParameter): ?>
                            <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo htmlspecialchars($parameter['program_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo htmlspecialchars($parameter['area_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $parameter['id']; ?>"><?php echo htmlspecialchars($parameter['name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../parameters/view_sub_parameter.php?id=<?php echo $subParameterId; ?>"><?php echo htmlspecialchars($subParameter['name']); ?></a></li>
                            <?php else: ?>
                            <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo htmlspecialchars($parameter['program_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo htmlspecialchars($parameter['area_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $parameterId; ?>"><?php echo htmlspecialchars($parameter['name']); ?></a></li>
                            <?php endif; ?>
                            
                            <li class="breadcrumb-item active">Evidence</li>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title">Evidence Items</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('add_evidence')): ?>
                                <?php if ($isSubParameter): ?>
                                <a href="add.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add Evidence</a>
                                <?php else: ?>
                                <a href="add.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add Evidence</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($isSubParameter): ?>
                            <a href="../parameters/view_sub_parameter.php?id=<?php echo $subParameterId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Sub-Parameter</a>
                            <?php else: ?>
                            <a href="../parameters/view.php?id=<?php echo $parameterId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Parameter</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($evidenceResult && $evidenceResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Uploaded By</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($evidence = $evidenceResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($evidence['title']); ?></td>
                                                <td>
                                                    <?php if (!empty($evidence['file_path'])): ?>
                                                        <span class="badge badge-info"><i class="fas fa-file"></i> File</span>
                                                    <?php elseif (!empty($evidence['drive_link'])): ?>
                                                        <span class="badge badge-warning"><i class="fab fa-google-drive"></i> Drive Link</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><i class="fas fa-question"></i> Unknown</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($evidence['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($evidence['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo ($evidence['status'] == 'approved') ? 'success' : 
                                                            (($evidence['status'] == 'rejected') ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($evidence['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view.php?id=<?php echo $evidence['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                                        
                                                        <?php if (hasPermission('edit_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                                        <a href="edit.php?id=<?php echo $evidence['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (hasPermission('delete_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                                        <a href="delete.php?id=<?php echo $evidence['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this evidence?');"><i class="fas fa-trash-alt"></i></a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($evidence['file_path']) && hasPermission('download_evidence')): ?>
                                                        <a href="download.php?id=<?php echo $evidence['id']; ?>" class="btn btn-sm btn-success" title="Download"><i class="fas fa-download"></i></a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($evidence['drive_link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($evidence['drive_link']); ?>" class="btn btn-sm btn-warning" title="Open Drive Link" target="_blank"><i class="fas fa-external-link-alt"></i></a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <p>No evidence items found. 
                                <?php if (hasPermission('add_evidence')): ?>
                                    <?php if ($isSubParameter): ?>
                                        <a href="add.php?sub_parameter_id=<?php echo $subParameterId; ?>">Get started by adding evidence</a>.
                                    <?php else: ?>
                                        <a href="add.php?parameter_id=<?php echo $parameterId; ?>">Get started by adding evidence</a>.
                                    <?php endif; ?>
                                <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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