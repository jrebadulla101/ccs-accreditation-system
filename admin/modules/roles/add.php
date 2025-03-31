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

// Check if user has permission to add roles
if (!hasRole('super_admin')) {
    setFlashMessage("danger", "You don't have permission to access this page.");
    header("Location: ../../dashboard.php");
    exit();
}

// Initialize variables
$name = '';
$description = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate role name
    if (empty($_POST['name'])) {
        $errors['name'] = 'Role name is required';
    } else {
        $name = trim($_POST['name']);
        
        // Check if role name already exists
        $checkQuery = "SELECT COUNT(*) as count FROM roles WHERE name = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $name);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors['name'] = 'Role name already exists';
        }
    }
    
    // Get description
    $description = trim($_POST['description'] ?? '');
    
    // If no errors, insert the role
    if (empty($errors)) {
        $insertQuery = "INSERT INTO roles (name, description, created_at) VALUES (?, ?, NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ss", $name, $description);
        
        if ($insertStmt->execute()) {
            $roleId = $conn->insert_id;
            
            // Log activity
            $userId = $_SESSION['admin_id'];
            $activityType = "role_created";
            $activityDescription = "Created new role: {$name}";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bind_param("issss", $userId, $activityType, $activityDescription, $ipAddress, $userAgent);
            $logStmt->execute();
            
            setFlashMessage("success", "Role created successfully.");
            header("Location: permissions.php?role_id=" . $roleId);
            exit();
        } else {
            $errors['db'] = 'Database error: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Role - CCS Accreditation System</title>
    
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
    /* Base Variables and Reset to gain the more local data and reset the main css file color*/
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
    
    /* Form Styles */
    .form-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .form-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
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
        margin-bottom: 5px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        background-color: var(--bg-light);
        font-size: 14px;
        transition: border-color 0.2s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }
    
    .form-text {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 5px;
    }
    
    .form-control.is-invalid {
        border-color: var(--danger);
    }
    
    .invalid-feedback {
        color: var(--danger);
        font-size: 12px;
        margin-top: 5px;
    }
    
    .form-actions {
        padding: 15px 20px;
        border-top: 1px solid var(--border-color);
        background-color: var(--bg-light);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
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
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
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
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
                            <i class="fas fa-user-tag"></i>
                            <span>Roles & Permissions</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="list.php" class="submenu-link">View All Roles</a></li>
                            <?php if (hasPermission('add_role')): ?>
                            <li><a href="add.php" class="submenu-link">Add New Role</a></li>
                            <?php endif; ?>
                            <li><a href="permissions.php" class="submenu-link">Manage Permissions</a></li>
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
                <div class="menu-toggle" id="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </div>
                
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search..." disabled>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo $_SESSION['admin_name']; ?>
                        </div>
                        <div class="user-role">
                            <?php 
                            try {
                                $roleQuery = "SELECT role FROM admin_users WHERE id = ?";
                                $roleStmt = $conn->prepare($roleQuery);
                                $adminId = $_SESSION['admin_id'];
                                $roleStmt->bind_param("i", $adminId);
                                $roleStmt->execute();
                                $roleResult = $roleStmt->get_result();
                                
                                if ($roleResult && $roleResult->num_rows > 0) {
                                    $roleData = $roleResult->fetch_assoc();
                                    echo ucfirst(str_replace('_', ' ', $roleData['role']));
                                } else {
                                    echo "User";
                                }
                            } catch (Exception $e) {
                                echo "System User";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="avatar" id="user-dropdown-toggle">
                        <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?>
                    </div>
                    
                    <div class="dropdown-menu" id="user-dropdown-menu">
                        <a href="../profile/view.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="../profile/change_password.php" class="dropdown-item">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="page-header">
                    <h1>Add New Role</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="list.php">Roles</a></li>
                            <li class="breadcrumb-item active">Add Role</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (isset($errors['db'])): ?>
                <div class="alert alert-danger">
                    <?php echo $errors['db']; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">Role Information</h2>
                    </div>
                    
                    <form method="post" action="">
                        <div class="form-body">
                            <div class="form-group">
                                <label for="name" class="form-label">Role Name <span style="color: var(--danger);">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['name']; ?>
                                </div>
                                <?php endif; ?>
                                <small class="form-text">Role name should be unique and descriptive (e.g., admin, editor, viewer).</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                                <small class="form-text">Describe what this role is responsible for and what permissions it should have.</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> After creating the role, you will be redirected to assign permissions.
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="list.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Role
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Initialize particles.js
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS("particles-js", {
                    particles: {
                        number: { value: 80, density: { enable: true, value_area: 800 } },
                        color: { value: "#ffffff" },
                        shape: { type: "circle" },
                        opacity: { value: 0.3, random: false },
                        size: { value: 3, random: true },
                        line_linked: { enable: true, distance: 150, color: "#ffffff", opacity: 0.3, width: 1 },
                        move: { enable: true, speed: 2, direction: "none", random: false, straight: false, out_mode: "out", bounce: false }
                    },
                    interactivity: {
                        detect_on: "canvas",
                        events: { onhover: { enable: true, mode: "repulse" }, onclick: { enable: true, mode: "push" }, resize: true },
                        modes: { grab: { distance: 400, line_linked: { opacity: 1 } }, bubble: { distance: 400, size: 40, duration: 2, opacity: 8 }, repulse: { distance: 200, duration: 0.4 }, push: { particles_nb: 4 }, remove: { particles_nb: 2 } }
                    },
                    retina_detect: true
                });
            }
            
            // Toggle user dropdown
            const userDropdownToggle = document.getElementById('user-dropdown-toggle');
            const userDropdownMenu = document.getElementById('user-dropdown-menu');
            
            if (userDropdownToggle && userDropdownMenu) {
                userDropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    userDropdownMenu.classList.toggle('show');
                });
                
                // Close dropdown when clicking elsewhere
                document.addEventListener('click', function(event) {
                    if (!userDropdownToggle.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                        userDropdownMenu.classList.remove('show');
                    }
                });
            }
            
            // Mobile Sidebar Toggle
            const toggleSidebarBtn = document.getElementById('toggle-sidebar');
            const sidebar = document.querySelector('.sidebar');
            
            if (toggleSidebarBtn && sidebar) {
                toggleSidebarBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
                
                // Close sidebar when clicking outside
                document.addEventListener('click', function(event) {
                    if (!sidebar.contains(event.target) && event.target !== toggleSidebarBtn) {
                        sidebar.classList.remove('active');
                    }
                });
            }
            
            // Submenu Toggle
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(function(item) {
                const link = item.querySelector('.menu-link');
                const submenu = item.querySelector('.submenu');
                
                if (link && submenu) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        item.classList.toggle('active');
                        
                        if (window.innerWidth < 992) {
                            if (submenu.style.maxHeight) {
                                submenu.style.maxHeight = null;
                            } else {
                                submenu.style.maxHeight = submenu.scrollHeight + "px";
                            }
                        }
                    });
                }
            });
            
            // Initialize active menu items
            document.querySelectorAll('.menu-item.active').forEach(function(item) {
                const submenu = item.querySelector('.submenu');
                if (submenu && window.innerWidth < 992) {
                    submenu.style.maxHeight = submenu.scrollHeight + "px";
                }
            });
            
            // Form validation
            const form = document.querySelector('form');
            const nameInput = document.getElementById('name');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validate role name
                    if (!nameInput.value.trim()) {
                        document.querySelector('#name + .invalid-feedback').textContent = 'Role name is required';
                        nameInput.classList.add('is-invalid');
                        isValid = false;
                    } else if (!/^[a-zA-Z0-9_]+$/.test(nameInput.value.trim())) {
                        document.querySelector('#name + .invalid-feedback').textContent = 'Role name should contain only letters, numbers, and underscores';
                        nameInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        nameInput.classList.remove('is-invalid');
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
                
                // Live validation
                nameInput.addEventListener('input', function() {
                    if (!this.value.trim()) {
                        document.querySelector('#name + .invalid-feedback').textContent = 'Role name is required';
                        this.classList.add('is-invalid');
                    } else if (!/^[a-zA-Z0-9_]+$/.test(this.value.trim())) {
                        document.querySelector('#name + .invalid-feedback').textContent = 'Role name should contain only letters, numbers, and underscores';
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
</body>
</html> 