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

// Check if user has permission to edit parameters
if (!hasPermission('edit_parameter')) {
    setFlashMessage("danger", "You don't have permission to edit parameters.");
    header("Location: list.php");
    exit();
}

// Check if parameter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No parameter ID provided.");
    header("Location: list.php");
    exit();
}

$parameterId = intval($_GET['id']);

// Check database structure to determine the correct column names
// Check for area_id or area_level_id
$areaReferenceColumn = 'area_id'; // Default
$paramColumnsQuery = "SHOW COLUMNS FROM parameters";
$paramColumns = $conn->query($paramColumnsQuery);

if ($paramColumns) {
    while ($column = $paramColumns->fetch_assoc()) {
        if ($column['Field'] == 'area_level_id') {
            $areaReferenceColumn = 'area_level_id';
            break;
        }
    }
}

// Get parameter details
$query = "SELECT * FROM parameters WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parameterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Parameter not found.");
    header("Location: list.php");
    exit();
}

$parameter = $result->fetch_assoc();

// Get available columns in parameters table
$tableColumnsQuery = "SHOW COLUMNS FROM parameters";
$tableColumnsResult = $conn->query($tableColumnsQuery);
$availableColumns = [];

while ($column = $tableColumnsResult->fetch_assoc()) {
    $availableColumns[$column['Field']] = true;
}

// Set default values if not present
if (!isset($parameter['weight'])) {
    $parameter['weight'] = 0;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $areaId = isset($_POST['area_id']) ? intval($_POST['area_id']) : $parameter[$areaReferenceColumn];
    
    // Basic validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Parameter name is required.";
    }
    
    if (isset($availableColumns['weight']) && ($weight < 0 || $weight > 100)) {
        $errors[] = "Weight must be between 0 and 100.";
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
        
        // Include description if available
        if (isset($availableColumns['description'])) {
            $updateFields[] = "description = ?";
            $updateTypes .= "s";
            $updateParams[] = $description;
        }
        
        // Include weight if available
        if (isset($availableColumns['weight'])) {
            $updateFields[] = "weight = ?";
            $updateTypes .= "d";
            $updateParams[] = $weight;
        }
        
        // Include area_id if available
        if (isset($availableColumns[$areaReferenceColumn])) {
            $updateFields[] = "$areaReferenceColumn = ?";
            $updateTypes .= "i";
            $updateParams[] = $areaId;
        }
        
        // Include updated_at if available
        if (isset($availableColumns['updated_at'])) {
            $updateFields[] = "updated_at = NOW()";
        }
        
        // Add the ID to the parameters
        $updateTypes .= "i";
        $updateParams[] = $parameterId;
        
        // Create the update query
        $updateQuery = "UPDATE parameters SET " . implode(", ", $updateFields) . " WHERE id = ?";
        
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
            $activityType = "parameter_updated";
            $activityDescription = "Updated parameter: $name";
            
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
            
            setFlashMessage("success", "Parameter updated successfully.");
            header("Location: list.php");
            exit();
        } else {
            $errors[] = "Failed to update parameter. Error: " . $conn->error;
        }
    }
}

// Get all areas for the dropdown
$areasQuery = "SELECT id, name FROM area_levels ORDER BY name ASC";
$areasResult = $conn->query($areasQuery);
$areas = [];

if ($areasResult && $areasResult->num_rows > 0) {
    while ($row = $areasResult->fetch_assoc()) {
        $areas[] = $row;
    }
}

// Get evidence count for this parameter
$evidenceCount = 0;
$checkEvidenceTable = "SHOW TABLES LIKE 'evidence'";
$evidenceTableExists = $conn->query($checkEvidenceTable)->num_rows > 0;

if ($evidenceTableExists) {
    try {
        $evidenceQuery = "SELECT COUNT(*) as count FROM evidence WHERE parameter_id = ?";
        $evidenceStmt = $conn->prepare($evidenceQuery);
        $evidenceStmt->bind_param("i", $parameterId);
        $evidenceStmt->execute();
        $evidenceResult = $evidenceStmt->get_result();
        $evidenceCount = $evidenceResult->fetch_assoc()['count'];
    } catch (Exception $e) {
        // If any error occurs, keep count at 0
    }
}

// Check if parameter has sub-parameters
$subParamCount = 0;
$checkSubParamsTable = "SHOW TABLES LIKE 'sub_parameters'";
$subParamsTableExists = $conn->query($checkSubParamsTable)->num_rows > 0;

if ($subParamsTableExists) {
    try {
        $subParamQuery = "SELECT COUNT(*) as count FROM sub_parameters WHERE parameter_id = ?";
        $subParamStmt = $conn->prepare($subParamQuery);
        $subParamStmt->bind_param("i", $parameterId);
        $subParamStmt->execute();
        $subParamResult = $subParamStmt->get_result();
        $subParamCount = $subParamResult->fetch_assoc()['count'];
    } catch (Exception $e) {
        // If any error occurs, keep count at 0
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Parameter - CCS Accreditation System</title>
    
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
    
    .form-range {
        width: 100%;
    }
    
    .range-value {
        font-weight: 600;
        display: inline-block;
        min-width: 40px;
        text-align: center;
    }
    
    /* Weight Indicator */
    .weight-indicator {
        display: flex;
        align-items: center;
        margin-top: 8px;
    }
    
    .weight-bar {
        flex: 1;
        height: 6px;
        background-color: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
        margin-right: 10px;
    }
    
    .weight-progress {
        height: 100%;
        background: linear-gradient(90deg, #4a90e2 0%, #5C6BC0 100%);
        border-radius: 3px;
        transition: width 0.3s ease;
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
                        <a href="#" class="menu-link">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Parameters</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="list.php" class="submenu-link">View All Parameters</a></li>
                            <?php if (hasPermission('add_parameter')): ?>
                            <li><a href="add.php" class="submenu-link">Add New Parameter</a></li>
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
                            <i class="fas fa-file-alt"></i>
                            <span>Logs</span>
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
                        <h1>Edit Parameter</h1>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="list.php">Parameters</a></li>
                            <li class="breadcrumb-item active">Edit Parameter</li>
                        </ul>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-edit"></i> Edit Parameter</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">Parameter Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($parameter['name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="area_id">Area Level</label>
                                        <select class="form-control" id="area_id" name="area_id">
                                            <option value="">-- Select Area --</option>
                                            <?php foreach ($areas as $area): ?>
                                                <option value="<?php echo $area['id']; ?>" <?php echo ($parameter[$areaReferenceColumn] == $area['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($area['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <?php if (isset($availableColumns['weight'])): ?>
                                    <div class="form-group">
                                        <label for="weight">Weight <span class="text-danger">*</span></label>
                                        <input type="range" class="form-range" id="weight" name="weight" min="0" max="100" step="0.5" value="<?php echo $parameter['weight']; ?>">
                                        <div class="weight-indicator">
                                            <div class="weight-bar">
                                                <div class="weight-progress" style="width: <?php echo $parameter['weight']; ?>%;"></div>
                                            </div>
                                            <span class="range-value weight-value"><?php echo $parameter['weight']; ?></span>%
                                        </div>
                                        <small class="form-text text-muted">Weight represents the importance of this parameter relative to others. Total weights for all parameters should equal 100%.</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($availableColumns['description'])): ?>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars(isset($parameter['description']) ? $parameter['description'] : ''); ?></textarea>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4 d-flex justify-content-between">
                                        <a href="list.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to List
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Parameter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-info-circle"></i> Parameter Info</h5>
                            </div>
                            <div class="card-body">
                                <div class="stats-card mb-3" style="background-color: rgba(52, 199, 89, 0.1);">
                                    <div class="stats-icon" style="background-color: rgba(52, 199, 89, 0.2); color: #34c759;">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="stats-details">
                                        <div class="stats-value"><?php echo $evidenceCount; ?></div>
                                        <div class="stats-label">Evidence Files</div>
                                    </div>
                                </div>
                                
                                <div class="stats-card mb-3" style="background-color: rgba(90, 120, 255, 0.1);">
                                    <div class="stats-icon" style="background-color: rgba(90, 120, 255, 0.2); color: #5a78ff;">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <div class="stats-details">
                                        <div class="stats-value"><?php echo $subParamCount; ?></div>
                                        <div class="stats-label">Sub-Parameters</div>
                                    </div>
                                </div>
                                
                                <div class="stats-card" style="background-color: rgba(74, 144, 226, 0.1);">
                                    <div class="stats-icon" style="background-color: rgba(74, 144, 226, 0.2); color: #4a90e2;">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stats-details">
                                        <div class="stats-value"><?php echo isset($parameter['created_at']) ? date('M d, Y', strtotime($parameter['created_at'])) : 'N/A'; ?></div>
                                        <div class="stats-label">Created Date</div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6 class="font-weight-bold">Related Actions:</h6>
                                    <div class="d-flex flex-column mt-2">
                                        <a href="view.php?id=<?php echo $parameterId; ?>" class="btn btn-sm btn-outline-primary mb-2">
                                            <i class="fas fa-eye"></i> View Parameter
                                        </a>
                                        
                                        <?php if (hasPermission('add_evidence')): ?>
                                        <a href="../evidence/add.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-sm btn-outline-success mb-2">
                                            <i class="fas fa-file-upload"></i> Upload Evidence
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_parameter')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger mb-2" data-toggle="modal" data-target="#deleteModal">
                                            <i class="fas fa-trash-alt"></i> Delete Parameter
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
    <?php if (hasPermission('delete_parameter')): ?>
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
                    <p>Are you sure you want to delete this parameter? This action cannot be undone.</p>
                    
                    <?php if ($evidenceCount > 0 || $subParamCount > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Warning:</strong> This parameter has associated data:
                        <ul class="mb-0 mt-2">
                            <?php if ($evidenceCount > 0): ?>
                            <li><?php echo $evidenceCount; ?> evidence file(s) will be deleted</li>
                            <?php endif; ?>
                            
                            <?php if ($subParamCount > 0): ?>
                            <li><?php echo $subParamCount; ?> sub-parameter(s) will be deleted</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="delete.php?id=<?php echo $parameterId; ?>" class="btn btn-danger">Delete Parameter</a>
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
        
        // Update weight display on range input change
        $("#weight").on("input", function() {
            var value = $(this).val();
            $(".weight-value").text(value);
            $(".weight-progress").css("width", value + "%");
        });
    });
    </script>
</body>
</html> 