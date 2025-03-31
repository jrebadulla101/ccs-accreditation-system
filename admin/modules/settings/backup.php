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

// Check if user has permission to manage settings
if (!hasPermission('manage_settings')) {
    setFlashMessage("danger", "You don't have permission to manage backup settings.");
    header("Location: ../../dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                // Create database backup
                if (createDatabaseBackup()) {
                    setFlashMessage("success", "Database backup created successfully.");
                } else {
                    setFlashMessage("danger", "Failed to create database backup.");
                }
                break;
                
            case 'delete_backup':
                // Delete a backup file
                if (isset($_POST['file']) && !empty($_POST['file'])) {
                    $filename = basename($_POST['file']);
                    
                    // Validate filename to prevent directory traversal
                    if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename) === 1) {
                        $filepath = '../../backups/' . $filename;
                        
                        if (file_exists($filepath) && unlink($filepath)) {
                            setFlashMessage("success", "Backup file deleted successfully.");
                        } else {
                            setFlashMessage("danger", "Failed to delete backup file.");
                        }
                    } else {
                        setFlashMessage("danger", "Invalid backup file name.");
                    }
                }
                break;
                
            case 'clear_logs':
                // Clear old logs
                $daysToKeep = intval($_POST['days_to_keep']);
                if ($daysToKeep > 0) {
                    $cleared = clearOldLogs($daysToKeep);
                    setFlashMessage("success", "$cleared old log entries removed successfully.");
                } else {
                    setFlashMessage("danger", "Invalid number of days provided.");
                }
                break;
                
            case 'optimize':
                // Optimize database
                if (optimizeDatabase()) {
                    setFlashMessage("success", "Database optimized successfully.");
                } else {
                    setFlashMessage("danger", "Failed to optimize database.");
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: backup.php");
    exit();
}

// Helper function to create database backup
function createDatabaseBackup() {
    global $conn;
    
    $backupDir = '../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $backup = "";
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        $numFields = $result->field_count;
        
        $backup .= "DROP TABLE IF EXISTS $table;";
        
        $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
        $backup .= "\n\n" . $row2[1] . ";\n\n";
        
        while($row = $result->fetch_row()) {
            $backup .= "INSERT INTO $table VALUES(";
            for($j=0; $j<$numFields; $j++) {
                $row[$j] = addslashes($row[$j] ?? '');
                $row[$j] = str_replace("\n","\\n",$row[$j]);
                if(isset($row[$j])) {
                    $backup .= '"'.$row[$j].'"' ;
                } else {
                    $backup .= '""';
                }
                if($j<($numFields-1)) {
                    $backup .= ',';
                }
            }
            $backup .= ");\n";
        }
        $backup .= "\n\n\n";
    }
    
    $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filePath = $backupDir . $fileName;
    
    if(file_put_contents($filePath, $backup)) {
        // Log backup activity
        $userId = $_SESSION['admin_id'];
        $activityType = "backup_created";
        $description = "Created database backup: $fileName";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("issss", $userId, $activityType, $description, $ipAddress, $userAgent);
        $logStmt->execute();
        
        return true;
    }
    
    return false;
}

// Helper function to clear old logs
function clearOldLogs($daysToKeep) {
    global $conn;
    
    $date = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));
    
    $query = "DELETE FROM activity_logs WHERE created_at < ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    
    return $stmt->affected_rows;
}

// Helper function to optimize database
function optimizeDatabase() {
    global $conn;
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $success = true;
    foreach ($tables as $table) {
        if (!$conn->query("OPTIMIZE TABLE $table")) {
            $success = false;
        }
    }
    
    return $success;
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get backup files
$backupDir = '../../backups/';
$backupFiles = file_exists($backupDir) ? glob($backupDir . '*.sql') : [];
$backupFiles = is_array($backupFiles) ? $backupFiles : [];
usort($backupFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Get system statistics
$dbSizeQuery = "SELECT
                sum(round(((data_length + index_length) / 1024 / 1024), 2)) AS size
                FROM information_schema.TABLES
                WHERE table_schema = '" . DB_NAME . "'";
$dbSizeResult = $conn->query($dbSizeQuery);
$dbSize = $dbSizeResult ? $dbSizeResult->fetch_assoc()['size'] : 0;

$tableCountQuery = "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'";
$tableCountResult = $conn->query($tableCountQuery);
$tableCount = $tableCountResult ? $tableCountResult->fetch_assoc()['count'] : 0;

$logCountQuery = "SELECT COUNT(*) as count FROM activity_logs";
$logCountResult = $conn->query($logCountQuery);
$logCount = $logCountResult ? $logCountResult->fetch_assoc()['count'] : 0;

$userCountQuery = "SELECT COUNT(*) as count FROM admin_users";
$userCountResult = $conn->query($userCountQuery);
$userCount = $userCountResult ? $userCountResult->fetch_assoc()['count'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Maintenance - CCS Accreditation System</title>
    
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
        --info: #1a7fe4;
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
    
    /* Stats Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        padding: 20px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background-color: rgba(74, 144, 226, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--primary-color);
        margin-right: 15px;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-color);
        margin-bottom: 5px;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 14px;
        color: var(--text-muted);
    }
    
    /* Backup Table */
    .backup-table {
        width: 100%;
    }
    
    .backup-table th {
        background-color: var(--bg-light);
        font-weight: 600;
    }
    
    .backup-actions {
        display: flex;
        gap: 5px;
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
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #e62a21;
    }
    
    .btn-warning {
        background-color: var(--warning);
        color: white;
    }
    
    .btn-warning:hover {
        background-color: #e0af00;
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
    
    .alert-info {
        background-color: rgba(26, 127, 228, 0.1);
        border-color: var(--info);
        color: var(--info);
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
    
    .empty-state p {
        color: var(--text-muted);
        font-size: 16px;
        margin-bottom: 20px;
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
    }
    
    .input-group {
        display: flex;
    }
    
    .input-group-append {
        display: flex;
    }
    
    .input-group-text {
        padding: 10px 12px;
        background-color: var(--bg-light);
        border: 1px solid var(--border-color);
        border-left: none;
        border-radius: 0 4px 4px 0;
    }
    
    /* Maintenance Section */
    .maintenance-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .maintenance-option {
        background-color: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        border: 1px solid var(--border-color);
    }
    
    .maintenance-header {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .maintenance-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background-color: rgba(74, 144, 226, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--primary-color);
    }
    
    .maintenance-title {
        flex: 1;
    }
    
    .maintenance-title h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .maintenance-title p {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 0;
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
        
        .stats-container {
            grid-template-columns: 1fr;
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
                    <li class="menu-item active">
                        <a href="#" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="index.php" class="submenu-link">General</a></li>
                            <li><a href="appearance.php" class="submenu-link">Appearance</a></li>
                            <li><a href="email.php" class="submenu-link">Email</a></li>
                            <li><a href="security.php" class="submenu-link">Security</a></li>
                            <li><a href="backup.php" class="submenu-link active">Backup & Maintenance</a></li>
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
                
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                    <h1>Backup & Maintenance</h1>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Settings</a></li>
                            <li class="breadcrumb-item active">Backup & Maintenance</li>
                        </ul>
                    </div>
                </div>
                
                <!-- System Stats -->
                        <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="stat-info">
                            <div class="stat-value"><?php echo number_format($dbSize, 2); ?> MB</div>
                            <div class="stat-label">Database Size</div>
                                </div>
                            </div>
                    
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-table"></i>
                                </div>
                                <div class="stat-info">
                            <div class="stat-value"><?php echo $tableCount; ?></div>
                            <div class="stat-label">Database Tables</div>
                                </div>
                            </div>
                    
                            <div class="stat-card">
                                <div class="stat-icon">
                            <i class="fas fa-history"></i>
                                </div>
                                <div class="stat-info">
                            <div class="stat-value"><?php echo $logCount; ?></div>
                            <div class="stat-label">Activity Logs</div>
                                </div>
                            </div>
                    
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-info">
                            <div class="stat-value"><?php echo $userCount; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Backup Section -->
                    <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-database"></i> Database Backups</h5>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Create Backup
                                    </button>
                                </form>
                    </div>
                    <div class="card-body">
                                <?php if (!empty($backupFiles)): ?>
                                <div class="table-responsive">
                                    <table class="table backup-table">
                            <thead>
                                <tr>
                                                <th>Backup Name</th>
                                    <th>Size</th>
                                                <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupFiles as $file): ?>
                                <tr>
                                    <td><?php echo basename($file); ?></td>
                                    <td><?php echo formatFileSize(filesize($file)); ?></td>
                                                <td><?php echo date("Y-m-d H:i:s", filemtime($file)); ?></td>
                                    <td class="backup-actions">
                                                    <a href="download_backup.php?file=<?php echo urlencode(basename($file)); ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                                        <input type="hidden" name="action" value="delete_backup">
                                                        <input type="hidden" name="file" value="<?php echo basename($file); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-database"></i>
                                    <h3>No backups found</h3>
                                    <p>You haven't created any database backups yet. Regular backups are recommended to prevent data loss.</p>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="create_backup">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Create Your First Backup
                                        </button>
                                    </form>
                </div>
                                <?php endif; ?>
            </div>
        </div>
    </div>
    
                    <!-- Maintenance Section -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-tools"></i> Maintenance</h5>
                            </div>
                            <div class="card-body">
                                <div class="maintenance-options">
                                    <div class="maintenance-option">
                                        <div class="maintenance-header">
                                            <div class="maintenance-icon">
                                                <i class="fas fa-broom"></i>
                                            </div>
                                            <div class="maintenance-title">
                                                <h3>Clear Old Logs</h3>
                                                <p>Remove old system logs to free up space</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="clear_logs">
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="days_to_keep" min="1" value="30" placeholder="Days to keep">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">days</span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">Logs older than this will be removed</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Are you sure you want to delete old logs?');">
                                                Clear Old Logs
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="maintenance-option">
                                        <div class="maintenance-header">
                                            <div class="maintenance-icon">
                                                <i class="fas fa-rocket"></i>
                                            </div>
                                            <div class="maintenance-title">
                                                <h3>Optimize Database</h3>
                                                <p>Improve performance and reclaim space</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="optimize">
                                            <p class="text-muted small mb-3">This will optimize all database tables to improve performance and reduce size.</p>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Optimize Database
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="maintenance-option">
                                        <div class="maintenance-header">
                                            <div class="maintenance-icon">
                                                <i class="fas fa-file-upload"></i>
                                            </div>
                                            <div class="maintenance-title">
                                                <h3>Restore Backup</h3>
                                                <p>Restore from a previous backup file</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="restore_backup.php" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="backup_file" name="backup_file" accept=".sql">
                                                    <label class="custom-file-label" for="backup_file">Choose backup file</label>
                                                </div>
                                                <small class="form-text text-muted">Upload a .sql backup file</small>
                                            </div>
                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Warning! This will overwrite your current database. Are you sure?');">
                                                Restore Backup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Information Card -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-info-circle"></i> Backup Information</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Regular backups are essential for data protection. We recommend:</p>
                                <ul class="mb-3">
                                    <li>Create weekly backups for normal use</li>
                                    <li>Create backups before major updates</li>
                                    <li>Store backups in multiple locations</li>
                                    <li>Test your backups regularly</li>
                                </ul>
                                <div class="alert alert-info">
                                    <i class="fas fa-lightbulb mr-2"></i>
                                    Backups include database content only. Files and uploads should be backed up separately.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
        
        // File input visual update
        $(".custom-file-input").on("change", function() {
            var fileName = $(this).val().split("\\").pop();
            $(this).next(".custom-file-label").html(fileName);
        });
    });
    </script>
</body>
</html> 