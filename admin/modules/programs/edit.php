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

// Check if user has permission to edit programs
if (!hasPermission('edit_program')) {
    setFlashMessage("danger", "You don't have permission to edit programs.");
    header("Location: list.php");
    exit();
}

// Check if program ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No program ID provided.");
    header("Location: list.php");
    exit();
}

$programId = intval($_GET['id']);

// Get program details
$query = "SELECT * FROM programs WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $programId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Program not found.");
    header("Location: list.php");
    exit();
}

$program = $result->fetch_assoc();

// Get available columns in programs table
$tableColumnsQuery = "SHOW COLUMNS FROM programs";
$tableColumnsResult = $conn->query($tableColumnsQuery);
$availableColumns = [];

while ($column = $tableColumnsResult->fetch_assoc()) {
    $availableColumns[$column['Field']] = true;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $name = trim($_POST['name']);
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $description = trim($_POST['description']);
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    
    // Basic validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Program name is required.";
    }
    
    if (empty($errors)) {
        // Build the update query based on available columns
        $updateFields = [];
        $updateTypes = "";
        $updateParams = [];
        
        // Always include name
        $updateFields[] = "name = ?";
        $updateTypes .= "s";
        $updateParams[] = $name;
        
        // Include code if available
        if (isset($availableColumns['code'])) {
            $updateFields[] = "code = ?";
            $updateTypes .= "s";
            $updateParams[] = $code;
        }
        
        // Include description if available
        if (isset($availableColumns['description'])) {
            $updateFields[] = "description = ?";
            $updateTypes .= "s";
            $updateParams[] = $description;
        }
        
        // Include status if available
        if (isset($availableColumns['status'])) {
            $updateFields[] = "status = ?";
            $updateTypes .= "s";
            $updateParams[] = $status;
        }
        
        // Include updated_at if available
        if (isset($availableColumns['updated_at'])) {
            $updateFields[] = "updated_at = NOW()";
        }
        
        // Add the ID to the parameters
        $updateTypes .= "i";
        $updateParams[] = $programId;
        
        // Create the update query
        $updateQuery = "UPDATE programs SET " . implode(", ", $updateFields) . " WHERE id = ?";
        
        // Execute the update
        $updateStmt = $conn->prepare($updateQuery);
        
        // Create a reference array for bind_param
        $bindParams = array($updateTypes);
        foreach ($updateParams as $key => $value) {
            $bindParams[] = &$updateParams[$key];
        }
        
        // Call bind_param() using call_user_func_array
        call_user_func_array(array($updateStmt, 'bind_param'), $bindParams);
        
        if ($updateStmt->execute()) {
            // Log the activity
            $userId = $_SESSION['admin_id'];
            $activityType = "program_updated";
            $activityDescription = "Updated program: $name";
            
            // Check if activity_logs table exists
            $checkTableQuery = "SHOW TABLES LIKE 'activity_logs'";
            $tableExists = $conn->query($checkTableQuery)->num_rows > 0;
            
            if ($tableExists) {
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                            VALUES (?, ?, ?, ?)";
                $logStmt = $conn->prepare($logQuery);
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $logStmt->bind_param("isss", $userId, $activityType, $activityDescription, $ipAddress);
                $logStmt->execute();
            }
            
            setFlashMessage("success", "Program updated successfully.");
            header("Location: list.php");
            exit();
        } else {
            $errors[] = "Failed to update program. Error: " . $conn->error;
        }
    }
}

// Count associated areas
$areaCount = 0;
$checkAreasTable = "SHOW TABLES LIKE 'area_levels'";
$areasTableExists = $conn->query($checkAreasTable)->num_rows > 0;

if ($areasTableExists) {
    try {
        // Check if the program_id column exists in the area_levels table
        $areaColumnsQuery = "SHOW COLUMNS FROM area_levels LIKE 'program_id'";
        $areaColumnsResult = $conn->query($areaColumnsQuery);
        
        if ($areaColumnsResult->num_rows > 0) {
            $areaQuery = "SELECT COUNT(*) as count FROM area_levels WHERE program_id = ?";
            $areaStmt = $conn->prepare($areaQuery);
            $areaStmt->bind_param("i", $programId);
            $areaStmt->execute();
            $areaResult = $areaStmt->get_result();
            $areaCount = $areaResult->fetch_assoc()['count'];
        }
    } catch (Exception $e) {
        // If any error occurs, keep count at 0
    }
}

// Get current status (with fallback to a default)
$currentStatus = isset($program['status']) ? $program['status'] : 'active';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program - CCS Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    
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
    .menu-toggle {
        font-size: 20px;
        cursor: pointer;
        display: none;
    }
    
    @media (max-width: 992px) {
        .menu-toggle {
            display: block;
        }
    }
    
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
        position: relative;
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
        cursor: pointer;
    }
    
    .dropdown-menu {
        position: absolute;
        top: 45px;
        right: 0;
        min-width: 180px;
        background-color: white;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        color: var(--text-color);
        transition: background-color 0.2s ease;
    }
    
    .dropdown-item:hover {
        background-color: var(--bg-light);
    }
    
    .dropdown-item i {
        margin-right: 10px;
        color: var(--text-muted);
        width: 16px;
        text-align: center;
    }
    
    .dropdown-divider {
        height: 1px;
        background-color: var(--border-color);
        margin: 5px 0;
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
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 24px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--bg-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .card-title i {
        margin-right: 8px;
        color: var(--accent-color);
    }
    
    .card-body {
        padding: 20px;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        font-size: 14px;
        transition: border-color 0.2s ease;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }
    
    .form-text {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: var(--text-muted);
    }
    
    /* Stats */
    .stats-card {
        display: flex;
        align-items: center;
        padding: 15px;
        background-color: var(--bg-light);
        border-radius: 6px;
        margin-bottom: 15px;
    }
    
    .stats-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 18px;
    }
    
    .stats-details {
        flex: 1;
    }
    
    .stats-value {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 3px;
    }
    
    .stats-label {
        font-size: 13px;
        color: var(--text-muted);
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
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #e62a21;
    }
    
    /* Custom Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background-color: rgba(52, 199, 89, 0.1);
        color: #34c759;
        border: 1px solid rgba(52, 199, 89, 0.2);
    }
    
    .status-inactive {
        background-color: rgba(108, 117, 125, 0.1);
        color: #6c757d;
        border: 1px solid rgba(108, 117, 125, 0.2);
    }
    
    .status-pending {
        background-color: rgba(255, 159, 10, 0.1);
        color: #ff9f0a;
        border: 1px solid rgba(255, 159, 10, 0.2);
    }
    
    /* Alert Styles */
    .alert {
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid transparent;
    }
    
    .alert-danger {
        background-color: rgba(255, 59, 48, 0.1);
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .alert-success {
        background-color: rgba(52, 199, 89, 0.1);
        border-color: var(--success);
        color: var(--success);
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
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
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
                        <a href="#" class="menu-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Programs</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="list.php" class="submenu-link">View All Programs</a></li>
                            <?php if (hasPermission('add_program')): ?>
                            <li><a href="add.php" class="submenu-link">Add New Program</a></li>
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
                            <li><a href="../evidence/add.php" class="submenu-link">Add New Evidence</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <?php if (hasPermission('view_users')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
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
                            <span>Roles</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../roles/list.php" class="submenu-link">View All Roles</a></li>
                            <?php if (hasPermission('add_role')): ?>
                            <li><a href="../roles/add.php" class="submenu-link">Add New Role</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('manage_permissions')): ?>
                            <li><a href="../roles/permissions.php" class="submenu-link">Manage Permissions</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('view_logs')): ?>
                    <li class="menu-item">
                        <a href="../logs/list.php" class="menu-link">
                            <i class="fas fa-history"></i>
                            <span>Activity Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('manage_settings')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="../settings/index.php" class="submenu-link">General</a></li>
                            <li><a href="../settings/appearance.php" class="submenu-link">Appearance</a></li>
                            <li><a href="../settings/email.php" class="submenu-link">Email</a></li>
                            <li><a href="../settings/security.php" class="submenu-link">Security</a></li>
                            <li><a href="../settings/backup.php" class="submenu-link">Backup & Maintenance</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <p class="text-muted small">&copy; 2023 CCS Accreditation</p>
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
                            <?php echo isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin User'; ?>
                        </div>
                        <div class="user-role">
                            <?php
                            $adminId = $_SESSION['admin_id'];
                            // Check if the role_id column exists in the admin_users table
                            $checkRoleIdCol = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role_id'");
                            
                            if ($checkRoleIdCol->num_rows > 0) {
                                // Using role_id column to get role name from roles table
                                $roleQuery = "SELECT r.name 
                                             FROM admin_users au 
                                             JOIN roles r ON au.role_id = r.id 
                                             WHERE au.id = $adminId";
                                $roleResult = $conn->query($roleQuery);
                                if ($roleResult && $roleRow = $roleResult->fetch_assoc()) {
                                    echo $roleRow['name'];
                                } else {
                                    echo 'Administrator';
                                }
                            } else {
                                // Using role column directly from admin_users table
                                $roleQuery = "SELECT role FROM admin_users WHERE id = $adminId";
                                $roleResult = $conn->query($roleQuery);
                                if ($roleResult && $roleRow = $roleResult->fetch_assoc()) {
                                    echo $roleRow['role'];
                                } else {
                                    echo 'Administrator';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="avatar" id="userDropdown">
                        <?php
                        $userName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin User';
                        $nameParts = explode(' ', $userName);
                        if (count($nameParts) > 1) {
                            echo strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
                        } else {
                            echo strtoupper(substr($userName, 0, 2));
                        }
                        ?>
                    </div>
                    
                    <div class="dropdown-menu" id="userMenu">
                        <a href="../profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="../notifications.php" class="dropdown-item">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?>">
                        <?php echo $_SESSION['flash_message']; ?>
                    </div>
                    <?php
                    // Clear the flash message
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_message_type']);
                    ?>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1>Edit Program</h1>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="list.php">Programs</a></li>
                            <li class="breadcrumb-item active">Edit Program</li>
                        </ul>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-edit"></i> Edit Program</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">Program Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($program['name']); ?>" required>
                                    </div>
                                    
                                    <?php if (isset($availableColumns['code'])): ?>
                                    <div class="form-group">
                                        <label for="code">Program Code</label>
                                        <input type="text" class="form-control" id="code" name="code" value="<?php echo htmlspecialchars(isset($program['code']) ? $program['code'] : ''); ?>">
                                        <small class="form-text text-muted">A short code or abbreviation for the program (e.g., BSCS, BSIT).</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($availableColumns['description'])): ?>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars(isset($program['description']) ? $program['description'] : ''); ?></textarea>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($availableColumns['status'])): ?>
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo ($currentStatus == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($currentStatus == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="pending" <?php echo ($currentStatus == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4 d-flex justify-content-between">
                                        <a href="list.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to List
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Program
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-info-circle"></i> Program Info</h5>
                            </div>
                            <div class="card-body">
                                <div class="stats-card mb-3" style="background-color: rgba(52, 199, 89, 0.1);">
                                    <div class="stats-icon" style="background-color: rgba(52, 199, 89, 0.2); color: #34c759;">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="stats-details">
                                        <div class="stats-value"><?php echo $areaCount; ?></div>
                                        <div class="stats-label">Area Levels</div>
                                    </div>
                                </div>
                                
                                <?php if (isset($program['created_at'])): ?>
                                <div class="stats-card" style="background-color: rgba(74, 144, 226, 0.1);">
                                    <div class="stats-icon" style="background-color: rgba(74, 144, 226, 0.2); color: #4a90e2;">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stats-details">
                                        <div class="stats-value"><?php echo date('M d, Y', strtotime($program['created_at'])); ?></div>
                                        <div class="stats-label">Created Date</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($availableColumns['status'])): ?>
                                <div class="mt-3">
                                    <h6 class="font-weight-bold">Current Status:</h6>
                                    <div class="mt-2">
                                        <span class="status-badge status-<?php echo htmlspecialchars($currentStatus); ?>">
                                            <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                            <?php echo ucfirst(htmlspecialchars($currentStatus)); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6 class="font-weight-bold">Related Actions:</h6>
                                    <div class="d-flex flex-column mt-2">
                                        <a href="../areas/list.php?program_id=<?php echo $programId; ?>" class="btn btn-sm btn-outline-primary mb-2">
                                            <i class="fas fa-layer-group"></i> View Areas
                                        </a>
                                        
                                        <?php if (hasPermission('add_area')): ?>
                                        <a href="../areas/add.php?program_id=<?php echo $programId; ?>" class="btn btn-sm btn-outline-success mb-2">
                                            <i class="fas fa-plus"></i> Add Area
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_program')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#deleteModal">
                                            <i class="fas fa-trash-alt"></i> Delete Program
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <?php if (hasPermission('delete_program')): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this program? This action cannot be undone.</p>
                    
                    <?php if ($areaCount > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Warning:</strong> This program has <?php echo $areaCount; ?> associated area(s). Deleting this program will affect all associated areas and their data.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="delete.php?id=<?php echo $programId; ?>" class="btn btn-danger">Delete Program</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize particles.js
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: "#5C6BC0" },
                shape: { type: "circle" },
                opacity: { value: 0.1, random: false },
                size: { value: 3, random: true },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: "#5C6BC0",
                    opacity: 0.1,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 2,
                    direction: "none",
                    random: false,
                    straight: false,
                    out_mode: "out",
                    bounce: false
                }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: { enable: true, mode: "grab" },
                    onclick: { enable: true, mode: "push" },
                    resize: true
                },
                modes: {
                    grab: { distance: 140, line_linked: { opacity: 0.3 } },
                    push: { particles_nb: 4 }
                }
            },
            retina_detect: true
        });
        
        // Toggle dropdown menu
        $("#userDropdown").click(function(e) {
            e.stopPropagation();
            $("#userMenu").toggleClass("show");
        });
        
        // Close dropdown when clicking outside
        $(document).click(function() {
            $("#userMenu").removeClass("show");
        });
        
        // Toggle sidebar on mobile
        $(".menu-toggle").click(function() {
            $(".sidebar").toggleClass("active");
        });
        
        // Toggle submenu
        $(".menu-link").click(function(e) {
            if ($(this).next(".submenu").length) {
                e.preventDefault();
                $(this).parent().toggleClass("active");
            }
        });
    });
    </script>
</body>
</html> 