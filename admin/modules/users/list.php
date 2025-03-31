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

// Check if user has permission to view users
if (!hasPermission('view_users')) {
    setFlashMessage("danger", "You don't have permission to view users.");
    header("Location: ../../dashboard.php");
    exit();
}

// Check if the role_id column exists in admin_users table
$hasRoleId = false;
$tableColumnsQuery = "SHOW COLUMNS FROM admin_users LIKE 'role_id'";
$tableColumnsResult = $conn->query($tableColumnsQuery);
if ($tableColumnsResult && $tableColumnsResult->num_rows > 0) {
    $hasRoleId = true;
}

// Check if the roles table exists
$rolesExist = false;
$tablesQuery = "SHOW TABLES LIKE 'roles'";
$tablesResult = $conn->query($tablesQuery);
if ($tablesResult && $tablesResult->num_rows > 0) {
    $rolesExist = true;
}

// Build the query based on the database structure
if ($hasRoleId && $rolesExist) {
    $query = "SELECT a.*, r.name as role_name
              FROM admin_users a
              LEFT JOIN roles r ON a.role_id = r.id
              ORDER BY a.id DESC";
} else {
    $query = "SELECT * FROM admin_users ORDER BY id DESC";
}

// Execute the query
$result = $conn->query($query);

// Count total users
$totalUsersQuery = "SELECT COUNT(*) as total FROM admin_users";
$totalUsersResult = $conn->query($totalUsersQuery);
$totalUsers = ($totalUsersResult && $totalUsersResult->num_rows > 0) ? $totalUsersResult->fetch_assoc()['total'] : 0;

// Count active users
$activeUsersQuery = "SELECT COUNT(*) as active FROM admin_users WHERE status = 'active'";
$activeUsersResult = $conn->query($activeUsersQuery);
$activeUsers = ($activeUsersResult && $activeUsersResult->num_rows > 0) ? $activeUsersResult->fetch_assoc()['active'] : 0;

// Count inactive users
$inactiveUsers = $totalUsers - $activeUsers;

// At the top of your file, add this helper function to safely display roles
function getUserRoleDisplay($user) {
    // If role is a string (direct field in users table)
    if (isset($user['role']) && !is_array($user['role'])) {
        return '<span class="badge badge-secondary">' . htmlspecialchars($user['role']) . '</span>';
    }
    
    // If role_name is available (possibly from a join)
    if (isset($user['role_name']) && !is_array($user['role_name'])) {
        return '<span class="badge badge-secondary">' . htmlspecialchars($user['role_name']) . '</span>';
    }
    
    // If there's a roles array (possibly from a separate query)
    if (isset($user['roles'])) {
        if (is_array($user['roles'])) {
            $roleDisplay = '';
            foreach ($user['roles'] as $key => $role) {
                if (is_array($role)) {
                    // If each role is an array (might have id and name)
                    if (isset($role['name'])) {
                        $roleDisplay .= '<span class="badge badge-secondary mr-1">' . htmlspecialchars($role['name']) . '</span>';
                    } else {
                        // Just show something if it's an array but we can't find a name
                        $roleDisplay .= '<span class="badge badge-secondary mr-1">Role ' . htmlspecialchars($key) . '</span>';
                    }
                } else {
                    // If each role is a string
                    $roleDisplay .= '<span class="badge badge-secondary mr-1">' . htmlspecialchars($role) . '</span>';
                }
            }
            return $roleDisplay ?: '<span class="text-muted">No role assigned</span>';
        } else {
            // If roles is there but not an array, display it safely
            return '<span class="badge badge-secondary">' . htmlspecialchars((string)$user['roles']) . '</span>';
        }
    }
    
    // Default case - no role information found
    return '<span class="text-muted">No role assigned</span>';
}

// Function to get user roles from junction table
function getUserRoles($conn, $userId) {
    $roles = [];
    
    // Check if user_roles table exists first
    $tableExistsQuery = "SHOW TABLES LIKE 'user_roles'";
    $tableExistsResult = $conn->query($tableExistsQuery);
    if (!$tableExistsResult || $tableExistsResult->num_rows == 0) {
        return [];
    }
    
    $query = "SELECT r.id, r.name 
              FROM roles r 
              JOIN user_roles ur ON r.id = ur.role_id 
              WHERE ur.user_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($role = $result->fetch_assoc()) {
        $roles[] = [
            'id' => $role['id'],
            'name' => $role['name']
        ];
    }
    
    $stmt->close();
    return $roles;
}

// This function should be added near the top of your file after database connection is established
function getUserRoleNames($conn, $userId) {
    // Query to get role names for a specific user
    $query = "SELECT r.name 
              FROM roles r 
              JOIN user_roles ur ON r.id = ur.role_id 
              WHERE ur.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roleNames = [];
    while ($role = $result->fetch_assoc()) {
        $roleNames[] = $role['name'];
    }
    
    return $roleNames;
}

function displayUserRoles($roles) {
    // If roles is already a string (old data format), just return it
    if (is_string($roles)) {
        return '<span class="badge badge-primary">' . htmlspecialchars($roles) . '</span>';
    }
    
    // If roles is an array, format each role as a badge
    if (is_array($roles)) {
        if (empty($roles)) {
            return '<span class="text-muted">No roles assigned</span>';
        }
        
        $output = '';
        foreach ($roles as $role) {
            // Handle if role is itself an array (containing id, name, etc.)
            if (is_array($role)) {
                if (isset($role['name'])) {
                    $output .= '<span class="badge badge-primary mr-1">' . htmlspecialchars($role['name']) . '</span> ';
                }
            } else {
                // Role is a simple string
                $output .= '<span class="badge badge-primary mr-1">' . htmlspecialchars($role) . '</span> ';
            }
        }
        return $output;
    }
    
    // If roles is null or another unexpected type
    return '<span class="text-muted">No roles assigned</span>';
}

// Then, modify your main user query section to fetch roles for each user:
// Find where you're fetching users, which might look something like:
$query = "SELECT * FROM admin_users ORDER BY id";
$result = $conn->query($query);

// After fetching users, add roles for each one:
$users = [];
if ($result) {
    while ($user = $result->fetch_assoc()) {
        // Get roles for this user
        $user['roles'] = getUserRoles($conn, $user['id']);
        $users[] = $user;
    }
}

// Now when you display user data in your table row, use this for the roles column:
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CCS Accreditation System</title>
    
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
        background-color: white;
        border-radius: 8px;
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
    
    /* Stats Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        padding: 20px;
        display: flex;
        align-items: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
        color: white;
    }
    
    .stat-icon.total {
        background-color: var(--primary-color);
    }
    
    .stat-icon.active {
        background-color: var(--success);
    }
    
    .stat-icon.inactive {
        background-color: var(--warning);
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }
    
    .stat-label {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
    }
    
    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    
    .data-table th {
        background-color: var(--bg-light);
        color: var(--text-color);
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    
    .data-table tr:hover {
        background-color: rgba(74, 144, 226, 0.05);
    }
    
    .data-table .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
    }
    
    .data-table .user-info {
        display: flex;
        align-items: center;
    }
    
    .data-table .user-details {
        margin-left: 12px;
    }
    
    .data-table .user-name {
        font-weight: 500;
        display: block;
    }
    
    .data-table .user-email {
        font-size: 13px;
        color: var(--text-muted);
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .status-active {
        background-color: rgba(52, 199, 89, 0.2);
        color: var(--success);
    }
    
    .status-inactive {
        background-color: rgba(248, 194, 0, 0.2);
        color: var(--warning);
    }
    
    /* Action Button Styles */
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
        padding: 4px 10px;
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
    
    .btn-success {
        background-color: var(--success);
        color: white;
    }
    
    .btn-success:hover {
        background-color: #2aa44e;
    }
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #e62a21;
    }
    
    .btn-icon {
        padding: 6px;
        width: 30px;
        height: 30px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .empty-state p {
        color: var(--text-muted);
        max-width: 500px;
        margin: 0 auto 20px;
    }
    
    /* Pagination */
    .pagination {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
    }
    
    .pagination .page-item {
        margin: 0 2px;
    }
    
    .pagination .page-link {
        display: block;
        padding: 6px 12px;
        border-radius: 4px;
        background-color: white;
        color: var(--text-color);
        border: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .pagination .page-link:hover {
        background-color: var(--bg-light);
    }
    
    .pagination .page-item.active .page-link {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .pagination .page-item.disabled .page-link {
        color: var(--text-muted);
        cursor: not-allowed;
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
        
        .menu-toggle {
            display: block;
        }
        
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 10px;
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
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
                            <i class="fas fa-users"></i>
                            <span>Users Management</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="list.php" class="submenu-link">View All Users</a></li>
                            <?php if (hasPermission('add_user')): ?>
                            <li><a href="add.php" class="submenu-link">Add New User</a></li>
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
                <div class="menu-toggle" id="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </div>
                
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search users...">
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
                                // Check if role_id column exists in admin_users table
                                $checkRoleIdColumn = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role_id'");
                                
                                if ($checkRoleIdColumn && $checkRoleIdColumn->num_rows > 0) {
                                    // If role_id column exists, join with roles table
                                    $roleQuery = "SELECT r.name FROM admin_users au 
                                               LEFT JOIN roles r ON au.role_id = r.id 
                                               WHERE au.id = " . $_SESSION['admin_id'];
                                } else {
                                    // Otherwise, just use the role field directly
                                    $roleQuery = "SELECT role FROM admin_users WHERE id = " . $_SESSION['admin_id'];
                                }
                                
                                $roleResult = $conn->query($roleQuery);
                                
                                if ($roleResult && $roleResult->num_rows > 0) {
                                    $roleData = $roleResult->fetch_assoc();
                                    echo ucfirst(isset($roleData['name']) ? $roleData['name'] : $roleData['role']);
                                } else {
                                    echo ucfirst($_SESSION['admin_role'] ?? "User");
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
                    <h1>User Management</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?>">
                    <?php 
                    echo $_SESSION['flash_message']; 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_message_type']);
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Add New User Button -->
                <?php if (hasPermission('add_user')): ?>
                <div class="action-header">
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- User Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h2 class="stat-value"><?php echo $totalUsers; ?></h2>
                            <p class="stat-label">Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h2 class="stat-value"><?php echo $activeUsers; ?></h2>
                            <p class="stat-label">Active Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon inactive">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="stat-info">
                            <h2 class="stat-value"><?php echo $inactiveUsers; ?></h2>
                            <p class="stat-label">Inactive Users</p>
                        </div>
                    </div>
                </div>
                
                <!-- Users List Table -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Users List</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Last Login</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        // Reset result pointer in case it was moved
                                        if ($result) { $result->data_seek(0); }
                                        
                                        while ($result && $user = $result->fetch_assoc()): 
                                            // Get user initials for avatar
                                            $initials = strtoupper(substr($user['full_name'], 0, 1));
                                            
                                            // Get user roles, only if the user_roles table exists
                                            $userRoles = getUserRoles($conn, $user['id']);
                                            
                                            // Format last login date
                                            $lastLogin = isset($user['last_login']) && $user['last_login'] !== null ? 
                                                date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';
                                                
                                            // Determine user status class
                                            $statusClass = $user['status'] === 'active' ? 'status-active' : 'status-inactive';
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                        <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                // First check if we have roles from the junction table
                                                if (!empty($userRoles)): ?>
                                                    <?php foreach ($userRoles as $role): ?>
                                                        <span class="badge badge-primary mr-1"><?php echo htmlspecialchars($role['name']); ?></span>
                                                    <?php endforeach; ?>
                                                <?php 
                                                // Next check if we have a role_name from join
                                                elseif (isset($user['role_name']) && !empty($user['role_name'])): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                                <?php 
                                                // Finally, fall back to the role column in admin_users
                                                elseif (isset($user['role']) && !empty($user['role'])): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($user['role']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No role assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $lastLogin; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if (hasPermission('view_user')): ?>
                                                    <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-icon btn-primary" title="View User">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('edit_user')): ?>
                                                    <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-icon btn-success" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('delete_user') && $user['id'] != $_SESSION['admin_id']): ?>
                                                    <a href="delete.php?id=<?php echo $user['id']; ?>" class="btn btn-icon btn-danger" title="Delete User" onclick="return confirm('Are you sure you want to delete this user?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No Users Found</h3>
                                <p>There are no users registered in the system yet.</p>
                                <?php if (hasPermission('add_user')): ?>
                                <a href="add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First User
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pagination Example (replace with actual pagination if needed) -->
                <?php if ($result && $result->num_rows > 10): ?>
                <div class="pagination">
                    <div class="page-item disabled">
                        <span class="page-link">Previous</span>
                    </div>
                    <div class="page-item active">
                        <span class="page-link">1</span>
                    </div>
                    <div class="page-item">
                        <a class="page-link" href="#">2</a>
                    </div>
                    <div class="page-item">
                        <a class="page-link" href="#">3</a>
                    </div>
                    <div class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Initialize particles.js
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

        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Search functionality
            const searchInput = document.querySelector('.search-input');
            const dataTable = document.querySelector('.data-table');
            
            if (searchInput && dataTable) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = dataTable.querySelectorAll('tbody tr');
                    
                    rows.forEach(function(row) {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Show empty state if no results
                    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                    const tableBody = dataTable.querySelector('tbody');
                    const emptyState = document.querySelector('.empty-search-state');
                    
                    if (visibleRows.length === 0 && searchTerm !== '') {
                        // If no empty state element exists, create one
                        if (!emptyState) {
                            const emptyStateDiv = document.createElement('div');
                            emptyStateDiv.className = 'empty-search-state';
                            emptyStateDiv.innerHTML = `
                                <i class="fas fa-search"></i>
                                <h3>No matching users found</h3>
                                <p>Try adjusting your search term.</p>
                            `;
                            tableBody.parentNode.appendChild(emptyStateDiv);
                        } else {
                            emptyState.style.display = 'block';
                        }
                        dataTable.style.display = 'none';
                    } else {
                        // Show table and hide empty state
                        dataTable.style.display = '';
                        if (emptyState) {
                            emptyState.style.display = 'none';
                        }
                    }
                });
            }
            
            // Status filter
            const statusFilters = document.querySelectorAll('.status-filter');
            
            if (statusFilters.length && dataTable) {
                statusFilters.forEach(function(filter) {
                    filter.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Remove active class from all filters
                        statusFilters.forEach(f => f.classList.remove('active'));
                        // Add active class to clicked filter
                        this.classList.add('active');
                        
                        const filterValue = this.dataset.filter;
                        const rows = dataTable.querySelectorAll('tbody tr');
                        
                        rows.forEach(function(row) {
                            const statusCell = row.querySelector('.status-badge');
                            
                            if (filterValue === 'all') {
                                row.style.display = '';
                            } else if (statusCell && statusCell.textContent.trim().toLowerCase() === filterValue) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                });
            }
            
            // Confirmation for delete actions
            const deleteButtons = document.querySelectorAll('.btn-danger[title="Delete User"]');
            
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Tooltip initialization (if Bootstrap is used)
            if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
                $('[title]').tooltip();
            }
            
            // Add animation to statistics cards
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 6px 12px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            // Add smooth scrolling to pagination links
            const paginationLinks = document.querySelectorAll('.pagination .page-link');
            
            paginationLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#') {
                        e.preventDefault();
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 