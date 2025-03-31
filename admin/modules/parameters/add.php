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

// Check if user has permission to add parameters
if (!hasPermission('add_parameter')) {
    setFlashMessage("danger", "You don't have permission to add parameters.");
    header("Location: list.php");
    exit();
}

// Get area_id from URL if available
$areaId = isset($_GET['area_id']) ? intval($_GET['area_id']) : 0;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $areaLevelId = isset($_POST['area_level_id']) ? intval($_POST['area_level_id']) : 0;
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'inactive';
    
    // Validate required fields
    $errors = [];
    
    if ($areaLevelId <= 0) {
        $errors[] = "Area is required.";
    }
    
    if (empty($name)) {
        $errors[] = "Parameter name is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    
    if ($weight <= 0) {
        $errors[] = "Weight must be greater than 0.";
    }
    
    // If no errors, proceed with saving
    if (empty($errors)) {
        // Check if parameter name already exists for this area
        $checkQuery = "SELECT id FROM parameters WHERE name = ? AND area_level_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("si", $name, $areaLevelId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors[] = "A parameter with this name already exists for the selected area.";
        } else {
            // Insert new parameter
            $sql = "INSERT INTO parameters (area_level_id, name, description, weight, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issds", $areaLevelId, $name, $description, $weight, $status);
            
            if ($stmt->execute()) {
                $parameterId = $conn->insert_id;
                
                // Log activity
                $adminId = $_SESSION['admin_id'];
                $activityType = "parameter_created";
                $activityDescription = "Created new parameter '{$name}' for area {$areaLevelId}";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->bind_param("issss", $adminId, $activityType, $activityDescription, $ipAddress, $userAgent);
                $logStmt->execute();
                
                setFlashMessage("success", "Parameter created successfully.");
                
                // Redirect to view the new parameter
                header("Location: view.php?id=" . $parameterId);
                exit();
            } else {
                $errors[] = "Error creating parameter: " . $conn->error;
            }
        }
    }
}

// Get list of areas for dropdown
if ($areaId > 0) {
    // If area_id is provided, only show that area
    $areasQuery = "SELECT a.id, a.name, p.name as program_name 
                 FROM area_levels a 
                 JOIN programs p ON a.program_id = p.id 
                 WHERE a.id = ? AND a.status = 'active'";
    $areasStmt = $conn->prepare($areasQuery);
    $areasStmt->bind_param("i", $areaId);
} else {
    // Otherwise show all active areas
    $areasQuery = "SELECT a.id, a.name, p.name as program_name 
                 FROM area_levels a 
                 JOIN programs p ON a.program_id = p.id 
                 WHERE a.status = 'active' 
                 ORDER BY p.name ASC, a.name ASC";
    $areasStmt = $conn->prepare($areasQuery);
}

$areasStmt->execute();
$areasResult = $areasStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Parameter - CCS Accreditation System</title>
    
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
    
    /* Form Styles */
    .form-container {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .form-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: var(--bg-light);
    }
    
    .form-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
    
    .form-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
    }
    
    .form-control {
        display: block;
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background-color: white;
        transition: border-color 0.2s ease;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
    }
    
    .form-col {
        flex: 1;
        padding: 0 10px;
        min-width: 200px;
    }
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
    
    .form-text {
        margin-top: 5px;
        font-size: 14px;
        color: var(--text-muted);
    }
    
    .info-box {
        background-color: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .info-box-title {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 10px;
        color: var(--primary-color);
    }
    
    .info-box-content {
        color: var(--text-muted);
        font-size: 14px;
    }
    
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
        
        .form-row {
            flex-direction: column;
        }
        
        .form-col {
            flex: 0 0 100%;
            margin-bottom: 10px;
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
                    <h1>Add New Parameter</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="list.php">Parameters</a></li>
                            <li class="breadcrumb-item active">Add New Parameter</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">Parameter Information</h2>
                    </div>
                    
                    <div class="form-body">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . ($areaId > 0 ? "?area_id=" . $areaId : "")); ?>">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="area_level_id" class="form-label">Area <span class="text-danger">*</span></label>
                                        <select name="area_level_id" id="area_level_id" class="form-control" required>
                                            <option value="">-- Select Area --</option>
                                            <?php if ($areasResult && $areasResult->num_rows > 0): ?>
                                                <?php while ($area = $areasResult->fetch_assoc()): ?>
                                                    <option value="<?php echo $area['id']; ?>" <?php echo ($areaId == $area['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($area['program_name'] . " - " . $area['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="form-text">Select the area level this parameter belongs to.</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="name" class="form-label">Parameter Name <span class="text-danger">*</span></label>
                                        <input type="text" id="name" name="name" class="form-control" required placeholder="Enter parameter name/number">
                                        <small class="form-text text-muted">Example: Parameter 1, 1.1, A.1, etc.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description">Description *</label>
                                        <textarea id="description" name="description" class="form-control" rows="4" required placeholder="Enter parameter description"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="weight">Weight</label>
                                        <input type="number" id="weight" name="weight" class="form-control" value="<?php echo $weight; ?>" min="0">
                                        <small class="form-text text-muted">Optional weight for this parameter.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="<?php echo $areaId > 0 ? "../areas/view.php?id=" . $areaId : "list.php"; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Parameter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
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
        
        // User dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdownToggle = document.querySelector('.user-dropdown-toggle');
            const userDropdownMenu = document.querySelector('.user-dropdown-menu');
            
            if (userDropdownToggle && userDropdownMenu) {
                userDropdownToggle.addEventListener('click', function(e) {
                    userDropdownMenu.classList.toggle('active');
                    e.stopPropagation();
                });
                
                // Close dropdown when clicking elsewhere
                document.addEventListener('click', function() {
                    userDropdownMenu.classList.remove('active');
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