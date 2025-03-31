<?php
// Include configuration before starting session
include 'includes/config.php';

// Start session after config is loaded
session_start();

// Include other necessary files
include 'includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get user ID for permission checks
$userId = $_SESSION['admin_id'];

// Initialize metrics
$metrics = [
    'programs' => 0,
    'areas' => 0,
    'parameters' => 0,
    'evidence' => 0
];

// Get counts with permission checks
if (hasPermission('view_all_programs')) {
    $query = "SELECT COUNT(*) as count FROM programs";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $metrics['programs'] = $result->fetch_assoc()['count'];
    }
} else {
    $query = "SELECT COUNT(*) as count FROM program_users WHERE user_id = $userId";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $metrics['programs'] = $result->fetch_assoc()['count'];
    }
}

// Get areas count
$query = "SELECT COUNT(*) as count FROM area_levels";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $metrics['areas'] = $result->fetch_assoc()['count'];
}

// Get parameters count
$query = "SELECT COUNT(*) as count FROM parameters";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $metrics['parameters'] = $result->fetch_assoc()['count'];
}

// Get evidence count
$query = "SELECT COUNT(*) as count FROM parameter_evidence";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $metrics['evidence'] = $result->fetch_assoc()['count'];
}

// Get evidence by status
$evidenceStatus = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$statusQuery = "SELECT status, COUNT(*) as count FROM parameter_evidence GROUP BY status";
$result = $conn->query($statusQuery);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
        if (isset($evidenceStatus[$status])) {
            $evidenceStatus[$status] = $row['count'];
        }
    }
}

// Get recent activity
$activityQuery = "SELECT al.*, au.full_name 
                 FROM activity_logs al
                 LEFT JOIN admin_users au ON al.user_id = au.id
                 ORDER BY al.created_at DESC LIMIT 5";
$recentActivity = $conn->query($activityQuery);

// Get recent evidence
$evidenceQuery = "SELECT pe.*, p.name as parameter_name, au.full_name as uploaded_by_name 
                 FROM parameter_evidence pe
                 JOIN parameters p ON pe.parameter_id = p.id
                 LEFT JOIN admin_users au ON pe.uploaded_by = au.id
                 ORDER BY pe.created_at DESC LIMIT 5";
$recentEvidence = $conn->query($evidenceQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CCS Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
    
    /* Dashboard Content Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 600;
    }
    
    .date-display {
        font-size: 14px;
        color: var(--text-muted);
        padding: 6px 12px;
        background-color: var(--bg-light);
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .date-display i {
        color: var(--accent-color);
    }
    
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: -12px;
    }
    
    .col-lg-6 {
        width: 50%;
        padding: 12px;
    }
    
    @media (max-width: 992px) {
        .col-lg-6 {
            width: 100%;
        }
    }
    
    .card {
        background-color: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 24px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    
    .card-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--bg-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-title {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-title i {
        color: var(--accent-color);
    }
    
    .card-body {
        padding: 20px;
    }
    
    .welcome-card {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        color: white;
    }
    
    .welcome-card .card-body {
        padding: 30px;
    }
    
    .welcome-card h2 {
        font-size: 22px;
        margin-bottom: 10px;
    }
    
    .welcome-card p {
        opacity: 0.9;
        margin-bottom: 20px;
    }
    
    .welcome-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 6px;
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
        transform: translateY(-2px);
    }
    
    .btn-accent {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
    }
    
    .btn-accent:hover {
        background-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    .header-link {
        font-size: 13px;
        color: var(--accent-color);
    }
    
    .header-link:hover {
        text-decoration: underline;
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stats-card {
        background-color: var(--card-bg);
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: white;
    }
    
    .stats-icon.programs {
        background: linear-gradient(135deg, #4a90e2 0%, #3742fa 100%);
    }
    
    .stats-icon.areas {
        background: linear-gradient(135deg, #2ed573 0%, #7bed9f 100%);
    }
    
    .stats-icon.parameters {
        background: linear-gradient(135deg, #ff6b6b 0%, #ff9ff3 100%);
    }
    
    .stats-icon.evidence {
        background: linear-gradient(135deg, #ffa502 0%, #feca57 100%);
    }
    
    .stats-icon i {
        font-size: 20px;
    }
    
    .stats-info {
        flex: 1;
    }
    
    .stats-info h3 {
        font-size: 14px;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    
    .stats-count {
        font-size: 24px;
        font-weight: 600;
    }
    
    /* Evidence Status */
    .status-summary {
        display: flex;
        justify-content: space-around;
        margin-bottom: 20px;
    }
    
    .status-item {
        text-align: center;
    }
    
    .status-count {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .status-label {
        font-size: 14px;
        padding: 3px 10px;
        border-radius: 20px;
    }
    
    .status-label.pending {
        background-color: rgba(248, 194, 0, 0.1);
        color: var(--warning);
    }
    
    .status-label.approved {
        background-color: rgba(52, 199, 89, 0.1);
        color: var(--success);
    }
    
    .status-label.rejected {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }
    
    .chart-container {
        height: 200px;
        position: relative;
    }
    
    /* Activity List */
    .activity-list {
        display: flex;
        flex-direction: column;
    }
    
    .activity-item {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--bg-light);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .activity-icon i {
        font-size: 14px;
        color: var(--accent-color);
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-text {
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .activity-meta {
        display: flex;
        font-size: 12px;
        color: var(--text-muted);
        gap: 15px;
    }
    
    .activity-meta .user {
        font-weight: 500;
    }
    
    /* Evidence List */
    .evidence-list {
        display: flex;
        flex-direction: column;
    }
    
    .evidence-item {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .evidence-item:last-child {
        border-bottom: none;
    }
    
    .evidence-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background-color: var(--bg-light);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .evidence-icon i {
        font-size: 16px;
        color: var(--accent-color);
    }
    
    .evidence-content {
        flex: 1;
    }
    
    .evidence-title {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-color);
        display: block;
        margin-bottom: 5px;
    }
    
    .evidence-title:hover {
        color: var(--accent-color);
    }
    
    .evidence-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 12px;
    }
    
    .evidence-meta .parameter {
        background-color: var(--bg-light);
        padding: 2px 8px;
        border-radius: 4px;
    }
    
    .evidence-meta .status {
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 500;
    }
    
    .evidence-meta .status.pending {
        background-color: rgba(248, 194, 0, 0.1);
        color: var(--warning);
    }
    
    .evidence-meta .status.approved {
        background-color: rgba(52, 199, 89, 0.1);
        color: var(--success);
    }
    
    .evidence-meta .status.rejected {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }
    
    /* Quick Links */
    .quick-links {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    
    .quick-link {
        background-color: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: var(--text-color);
        transition: all 0.2s ease;
    }
    
    .quick-link:hover {
        transform: translateY(-5px);
        background-color: var(--primary-color);
        color: white;
    }
    
    .quick-link i {
        font-size: 24px;
        margin-bottom: 10px;
        color: var(--accent-color);
        transition: color 0.2s ease;
    }
    
    .quick-link:hover i {
        color: white;
    }
    
    .quick-link span {
        font-size: 13px;
        font-weight: 500;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 30px 0;
    }
    
    .empty-state i {
        font-size: 40px;
        color: #cfd8dc;
        margin-bottom: 15px;
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .quick-links {
            grid-template-columns: repeat(3, 1fr);
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
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .welcome-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
        
        .row {
            flex-direction: column;
        }
        
        .col-lg-6 {
            width: 100%;
        }
        
        .quick-links {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .status-summary {
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
    }
    
    @media (max-width: 576px) {
        .search-bar {
            display: none;
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
                <img src="assets/images/logo.png" alt="EARIST Logo">
                <h1>CCS Accreditation</h1>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li class="menu-item active">
                        <a href="dashboard.php" class="menu-link active">
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
                            <li><a href="modules/programs/list.php" class="submenu-link">View All Programs</a></li>
                            <?php if (hasPermission('add_program')): ?>
                            <li><a href="modules/programs/add.php" class="submenu-link">Add New Program</a></li>
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
                            <li><a href="modules/areas/list.php" class="submenu-link">View All Areas</a></li>
                            <?php if (hasPermission('add_area')): ?>
                            <li><a href="modules/areas/add.php" class="submenu-link">Add New Area</a></li>
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
                            <li><a href="modules/parameters/list.php" class="submenu-link">View All Parameters</a></li>
                            <?php if (hasPermission('add_parameter')): ?>
                            <li><a href="modules/parameters/add.php" class="submenu-link">Add New Parameter</a></li>
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
                            <li><a href="modules/evidence/list.php" class="submenu-link">View All Evidence</a></li>
                            <?php if (hasPermission('add_evidence')): ?>
                            <li><a href="modules/evidence/add.php" class="submenu-link">Upload New Evidence</a></li>
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
                            <li><a href="modules/users/list.php" class="submenu-link">View All Users</a></li>
                            <?php if (hasPermission('add_user')): ?>
                            <li><a href="modules/users/add.php" class="submenu-link">Add New User</a></li>
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
                            <li><a href="modules/roles/list.php" class="submenu-link">View All Roles</a></li>
                            <?php if (hasPermission('add_role')): ?>
                            <li><a href="modules/roles/add.php" class="submenu-link">Add New Role</a></li>
                            <?php endif; ?>
                            <li><a href="modules/roles/permissions.php" class="submenu-link">Manage Permissions</a></li>
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
                            <li><a href="modules/settings/system.php" class="submenu-link">System Settings</a></li>
                            <li><a href="modules/settings/backup.php" class="submenu-link">Backup Database</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="menu-link">
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
                            // Fix the role query by checking your actual database structure
                            // This assumes your admin_users table might have a different role column name
                            $roleQuery = "SELECT r.name FROM admin_users au 
                                          JOIN roles r ON au.role = r.id 
                                          WHERE au.id = " . $_SESSION['admin_id'];
                            
                            // Alternative approach if the above doesn't work
                            try {
                                $roleResult = $conn->query($roleQuery);
                                if ($roleResult && $roleResult->num_rows > 0) {
                                    echo $roleResult->fetch_assoc()['name'];
                                } else {
                                    // Fallback if query returns no results
                                    echo "User";
                                }
                            } catch (Exception $e) {
                                // If there's an error with the query, just display a default role
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
                    <h1>Dashboard</h1>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date("F d, Y"); ?></span>
                    </div>
                </div>
                
                <!-- Welcome Banner -->
                <div class="card welcome-card">
                    <div class="card-body">
                        <h2>Welcome to the CCS Accreditation System, <?php echo explode(' ', $_SESSION['admin_name'])[0]; ?>!</h2>
                        <p>Monitor accreditation status, manage programs, upload evidence, and collaborate with your team - all in one place.</p>
                        <div class="welcome-actions">
                            <a href="modules/programs/list.php" class="btn btn-accent">
                                <i class="fas fa-graduation-cap"></i>
                                View Programs
                            </a>
                            <?php if (hasPermission('add_evidence')): ?>
                            <a href="modules/evidence/add.php" class="btn btn-accent">
                                <i class="fas fa-file-upload"></i>
                                Upload Evidence
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-icon programs">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Programs</h3>
                            <div class="stats-count"><?php echo $metrics['programs']; ?></div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon areas">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Areas</h3>
                            <div class="stats-count"><?php echo $metrics['areas']; ?></div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon parameters">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Parameters</h3>
                            <div class="stats-count"><?php echo $metrics['parameters']; ?></div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon evidence">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Evidence Files</h3>
                            <div class="stats-count"><?php echo $metrics['evidence']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Evidence Status -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-pie"></i>
                                    Evidence Status
                                </h3>
                                <a href="modules/evidence/list.php" class="header-link">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="status-summary">
                                    <div class="status-item">
                                        <div class="status-count"><?php echo $evidenceStatus['pending']; ?></div>
                                        <div class="status-label pending">Pending</div>
                                    </div>
                                    
                                    <div class="status-item">
                                        <div class="status-count"><?php echo $evidenceStatus['approved']; ?></div>
                                        <div class="status-label approved">Approved</div>
                                    </div>
                                    
                                    <div class="status-item">
                                        <div class="status-count"><?php echo $evidenceStatus['rejected']; ?></div>
                                        <div class="status-label rejected">Rejected</div>
                                    </div>
                                </div>
                                
                                <div class="chart-container">
                                    <canvas id="evidenceChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-link"></i>
                                    Quick Links
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="quick-links">
                                    <a href="modules/programs/list.php" class="quick-link">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span>Programs</span>
                                    </a>
                                    
                                    <a href="modules/areas/list.php" class="quick-link">
                                        <i class="fas fa-layer-group"></i>
                                        <span>Areas</span>
                                    </a>
                                    
                                    <a href="modules/parameters/list.php" class="quick-link">
                                        <i class="fas fa-clipboard-list"></i>
                                        <span>Parameters</span>
                                    </a>
                                    
                                    <a href="modules/evidence/list.php" class="quick-link">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Evidence</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-history"></i>
                                    Recent Activity
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="activity-list">
                                    <?php if ($recentActivity && $recentActivity->num_rows > 0): ?>
                                        <?php while ($activity = $recentActivity->fetch_assoc()): ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <i class="fas fa-<?php
                                                        // Safe version that handles null values
                                                        $type = strtolower((string)($activity['action'] ?? ''));
                                                        if (strpos($type, 'add') !== false) echo 'plus';
                                                        elseif (strpos($type, 'edit') !== false) echo 'edit';
                                                        elseif (strpos($type, 'delete') !== false) echo 'trash-alt';
                                                        elseif (strpos($type, 'upload') !== false) echo 'upload';
                                                        elseif (strpos($type, 'login') !== false) echo 'sign-in-alt';
                                                        else echo 'clock';
                                                    ?>"></i>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="activity-text">
                                                        <?php echo htmlspecialchars($activity['description']); ?>
                                                    </div>
                                                    <div class="activity-meta">
                                                        <span class="user"><?php echo htmlspecialchars($activity['full_name']); ?></span>
                                                        <span class="time"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="far fa-clock"></i>
                                            <p>No recent activity found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Evidence -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-file-alt"></i>
                                    Recent Evidence
                                </h3>
                                <a href="modules/evidence/list.php" class="header-link">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="evidence-list">
                                    <?php if ($recentEvidence && $recentEvidence->num_rows > 0): ?>
                                        <?php while ($evidence = $recentEvidence->fetch_assoc()): ?>
                                            <div class="evidence-item">
                                                <div class="evidence-icon">
                                                    <i class="fas fa-<?php
                                                        // Safe version that handles null values
                                                        $fileType = strtolower((string)pathinfo($evidence['file_path'], PATHINFO_EXTENSION) ?? '');
                                                        if ($fileType == 'pdf') echo 'file-pdf';
                                                        elseif (in_array($fileType, ['doc', 'docx'])) echo 'file-word';
                                                        elseif (in_array($fileType, ['xls', 'xlsx'])) echo 'file-excel';
                                                        elseif (in_array($fileType, ['ppt', 'pptx'])) echo 'file-powerpoint';
                                                        elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) echo 'file-image';
                                                        elseif ($evidence['drive_link']) echo 'google-drive';
                                                        else echo 'file-alt';
                                                    ?>"></i>
                                                </div>
                                                <div class="evidence-content">
                                                    <a href="modules/evidence/view.php?id=<?php echo $evidence['id']; ?>" class="evidence-title">
                                                        <?php echo htmlspecialchars($evidence['title']); ?>
                                                    </a>
                                                    <div class="evidence-meta">
                                                        <span class="parameter"><?php echo htmlspecialchars($evidence['parameter_name']); ?></span>
                                                        <span class="status <?php echo strtolower($evidence['status']); ?>">
                                                            <?php echo $evidence['status']; ?>
                                                        </span>
                                                        <span>By: <?php echo htmlspecialchars($evidence['uploaded_by_name']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="far fa-file-alt"></i>
                                            <p>No evidence files found.</p>
                                            <?php if (hasPermission('add_evidence')): ?>
                                                <a href="modules/evidence/add.php" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-upload"></i> Upload Evidence
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
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
    
    // Initialize Chart.js for evidence status
    document.addEventListener('DOMContentLoaded', function() {
        var evidenceCanvas = document.getElementById('evidenceChart');
        if (evidenceCanvas) {
            var ctx = evidenceCanvas.getContext('2d');
            var evidenceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Rejected'],
                    datasets: [{
                        data: [
                            <?php echo $evidenceStatus['pending']; ?>, 
                            <?php echo $evidenceStatus['approved']; ?>, 
                            <?php echo $evidenceStatus['rejected']; ?>
                        ],
                        backgroundColor: [
                            '#f8c200',
                            '#34c759',
                            '#ff3b30'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
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
