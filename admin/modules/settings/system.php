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
    setFlashMessage("danger", "You don't have permission to manage system settings.");
    header("Location: ../../dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // System maintenance operations
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_cache':
                // Clear cache logic
                $cacheDir = '../../cache/';
                if (is_dir($cacheDir)) {
                    $files = glob($cacheDir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                setFlashMessage("success", "Cache cleared successfully.");
                break;
                
            case 'run_diagnostics':
                // System diagnostics logic
                $diagnosticResults = runSystemDiagnostics();
                $_SESSION['diagnostic_results'] = $diagnosticResults;
                break;
                
            case 'update_system_info':
                // Update system info
                updateSetting('institution_name', cleanInput($_POST['institution_name']));
                updateSetting('institution_address', cleanInput($_POST['institution_address']));
                updateSetting('institution_contact', cleanInput($_POST['institution_contact']));
                updateSetting('accreditation_agency', cleanInput($_POST['accreditation_agency']));
                setFlashMessage("success", "System information updated successfully.");
                break;
                
            case 'maintenance_mode':
                // Toggle maintenance mode
                $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
                updateSetting('maintenance_mode', $maintenanceMode);
                if ($maintenanceMode === '1') {
                    updateSetting('maintenance_message', cleanInput($_POST['maintenance_message']));
                }
                setFlashMessage("success", "Maintenance mode settings updated.");
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: system.php");
    exit();
}

// Get current settings
function getSetting($key, $default = '') {
    global $conn;
    $key = $conn->real_escape_string($key);
    $query = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    
    return $default;
}

// Update a setting
function updateSetting($key, $value) {
    global $conn;
    $key = $conn->real_escape_string($key);
    $value = $conn->real_escape_string($value);
    
    $query = "SELECT id FROM settings WHERE setting_key = '$key'";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        // Update existing setting
        $query = "UPDATE settings SET setting_value = '$value', updated_at = NOW() WHERE setting_key = '$key'";
    } else {
        // Insert new setting
        $query = "INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES ('$key', '$value', NOW(), NOW())";
    }
    
    return $conn->query($query);
}

// Run system diagnostics
function runSystemDiagnostics() {
    global $conn;
    $results = [];
    
    // Check PHP version
    $results['php_version'] = [
        'name' => 'PHP Version',
        'value' => phpversion(),
        'status' => version_compare(phpversion(), '7.4.0', '>=') ? 'pass' : 'fail',
        'message' => version_compare(phpversion(), '7.4.0', '>=') ? 'PHP version is compatible.' : 'PHP version should be 7.4.0 or higher.'
    ];
    
    // Check necessary PHP extensions
    $requiredExtensions = ['mysqli', 'gd', 'zip', 'curl', 'mbstring'];
    $missingExtensions = [];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    $results['php_extensions'] = [
        'name' => 'PHP Extensions',
        'value' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing: ' . implode(', ', $missingExtensions),
        'status' => empty($missingExtensions) ? 'pass' : 'fail',
        'message' => empty($missingExtensions) ? 'All required PHP extensions are loaded.' : 'Some required PHP extensions are missing.'
    ];
    
    // Check database connection
    $results['database_connection'] = [
        'name' => 'Database Connection',
        'value' => $conn->ping() ? 'Connected' : 'Failed',
        'status' => $conn->ping() ? 'pass' : 'fail',
        'message' => $conn->ping() ? 'Database connection is working.' : 'Database connection failed: ' . $conn->error
    ];
    
    // Check file permissions
    $writableFolders = ['uploads/', 'cache/', 'backups/'];
    $nonWritableFolders = [];
    foreach ($writableFolders as $folder) {
        $path = '../../' . $folder;
        if (!is_dir($path)) {
            // Try to create the directory
            if (!mkdir($path, 0755, true)) {
                $nonWritableFolders[] = $folder . ' (folder does not exist and cannot be created)';
            }
        } elseif (!is_writable($path)) {
            $nonWritableFolders[] = $folder;
        }
    }
    $results['file_permissions'] = [
        'name' => 'File Permissions',
        'value' => empty($nonWritableFolders) ? 'All folders are writable' : 'Non-writable folders: ' . implode(', ', $nonWritableFolders),
        'status' => empty($nonWritableFolders) ? 'pass' : 'warning',
        'message' => empty($nonWritableFolders) ? 'All required folders have proper write permissions.' : 'Some folders do not have proper write permissions. This may cause issues with file uploads or system operations.'
    ];
    
    // Check for database tables
    $tablesQuery = "SHOW TABLES";
    $tablesResult = $conn->query($tablesQuery);
    $tableCount = $tablesResult ? $tablesResult->num_rows : 0;
    $results['database_tables'] = [
        'name' => 'Database Tables',
        'value' => $tableCount . ' tables found',
        'status' => $tableCount > 0 ? 'pass' : 'fail',
        'message' => $tableCount > 0 ? 'Database schema is set up.' : 'Database appears to be empty or not properly set up.'
    ];
    
    // Check for admin accounts
    $adminQuery = "SELECT COUNT(*) as count FROM admin_users";
    $adminResult = $conn->query($adminQuery);
    $adminCount = $adminResult ? $adminResult->fetch_assoc()['count'] : 0;
    $results['admin_accounts'] = [
        'name' => 'Admin Accounts',
        'value' => $adminCount . ' accounts found',
        'status' => $adminCount > 0 ? 'pass' : 'warning',
        'message' => $adminCount > 0 ? 'Administrator accounts exist in the system.' : 'No administrator accounts found. This may indicate an issue with user setup.'
    ];
    
    // Check for system settings
    $settingsQuery = "SELECT COUNT(*) as count FROM settings";
    $settingsResult = $conn->query($settingsQuery);
    $settingsCount = $settingsResult ? $settingsResult->fetch_assoc()['count'] : 0;
    $results['system_settings'] = [
        'name' => 'System Settings',
        'value' => $settingsCount . ' settings found',
        'status' => $settingsCount > 0 ? 'pass' : 'warning',
        'message' => $settingsCount > 0 ? 'System settings are configured.' : 'No system settings found. This may indicate an issue with the system configuration.'
    ];
    
    return $results;
}

// Get diagnostic results from session if they exist
$diagnosticResults = isset($_SESSION['diagnostic_results']) ? $_SESSION['diagnostic_results'] : null;
if ($diagnosticResults) {
    unset($_SESSION['diagnostic_results']); // Clear results from session after retrieving
}

// Get server information
$serverInfo = [
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server OS' => PHP_OS,
    'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'PHP Version' => phpversion(),
    'MySQL Version' => $conn->server_info,
    'Memory Limit' => ini_get('memory_limit'),
    'Upload Max Size' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
    'Max Execution Time' => ini_get('max_execution_time') . ' seconds'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Management - CCS Accreditation System</title>
    
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
    
    .btn-accent {
        background-color: var(--accent-color);
        color: white;
    }
    
    .btn-accent:hover {
        background-color: #4c5ba0;
    }
    
    /* Form Styles */
    .form-row {
        margin-bottom: 20px;
    }
    
    .form-row label {
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
    
    .form-actions {
        margin-top: 24px;
        display: flex;
        justify-content: flex-end;
    }
    
    .checkbox-row {
        display: flex;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    
    .checkbox-row label {
        margin-left: 10px;
        margin-bottom: 0;
        font-weight: 500;
    }
    
    .checkbox-row input[type="checkbox"] {
        margin-top: 5px;
    }
    
    /* Diagnostic Results Styles */
    .diagnostic-results {
        margin-bottom: 24px;
    }
    
    .diagnostic-table {
        margin-bottom: 0;
    }
    
    .diagnostic-table th {
        background-color: var(--bg-light);
        font-weight: 600;
    }
    
    .diagnostic-table td {
        vertical-align: middle;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .status-pass {
        background-color: rgba(52, 199, 89, 0.1);
        color: #34c759;
    }
    
    .status-warning {
        background-color: rgba(255, 204, 0, 0.1);
        color: #f8c200;
    }
    
    .status-fail {
        background-color: rgba(255, 59, 48, 0.1);
        color: #ff3b30;
    }
    
    .details-value {
        font-weight: 500;
        margin-bottom: 4px;
    }
    
    .details-message {
        font-size: 13px;
        color: var(--text-muted);
    }
    
    /* Maintenance Actions Styles */
    .maintenance-actions {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .action-card {
        background-color: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        border: 1px solid var(--border-color);
    }
    
    .action-info {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background-color: rgba(74, 144, 226, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--accent-color);
    }
    
    .action-details h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .action-details p {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 0;
    }
    
    /* Server Info Styles */
    .server-info-table th {
        width: 40%;
        font-weight: 600;
    }
    
    .server-info-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }
    
    .uptime-info {
        font-size: 13px;
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
        
        .server-info-footer {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
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
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="system.php" class="submenu-link">System Settings</a></li>
                            <li><a href="index.php" class="submenu-link">General Settings</a></li>
                            <li><a href="backup.php" class="submenu-link">Backup Database</a></li>
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
        
        <div class="main-content">
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
            
            <div class="content">
                <div class="page-header">
                    <h1>System Management</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Settings</a></li>
                            <li class="breadcrumb-item active">System Management</li>
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
                
                <?php if ($diagnosticResults): ?>
                <div class="diagnostic-results">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fas fa-stethoscope"></i> System Diagnostic Results</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> System diagnostics completed. Below are the results:
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table diagnostic-table">
                                    <thead>
                                        <tr>
                                            <th>Component</th>
                                            <th>Status</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($diagnosticResults as $item): ?>
                                        <tr class="status-<?php echo $item['status']; ?>">
                                            <td><?php echo $item['name']; ?></td>
                                            <td>
                                                <?php if ($item['status'] == 'pass'): ?>
                                                    <span class="status-badge status-pass"><i class="fas fa-check-circle"></i> Pass</span>
                                                <?php elseif ($item['status'] == 'warning'): ?>
                                                    <span class="status-badge status-warning"><i class="fas fa-exclamation-triangle"></i> Warning</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-fail"><i class="fas fa-times-circle"></i> Fail</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="details-value"><?php echo $item['value']; ?></div>
                                                <div class="details-message"><?php echo $item['message']; ?></div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Maintenance Mode -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-wrench"></i> Maintenance Mode</h2>
                            </div>
                            <div class="card-body">
                                <form action="system.php" method="post">
                                    <input type="hidden" name="action" value="maintenance_mode">
                                    
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode" <?php echo getSetting('maintenance_mode') === '1' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="maintenance_mode">Enable Maintenance Mode</label>
                                        </div>
                                        <small class="form-text text-muted">When enabled, only administrators can access the system. All other users will see a maintenance message.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="maintenance_message">Maintenance Message</label>
                                        <textarea id="maintenance_message" name="maintenance_message" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('maintenance_message', 'The system is currently under maintenance. Please check back later.')); ?></textarea>
                                        <small class="form-text text-muted">This message will be displayed to users when the system is in maintenance mode.</small>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-building"></i> Institution Information</h2>
                            </div>
                            <div class="card-body">
                                <form action="system.php" method="post">
                                    <input type="hidden" name="action" value="update_system_info">
                                    
                                    <div class="form-group">
                                        <label for="institution_name">Institution Name</label>
                                        <input type="text" id="institution_name" name="institution_name" class="form-control" value="<?php echo htmlspecialchars(getSetting('institution_name', 'EARIST')); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="institution_address">Institution Address</label>
                                        <textarea id="institution_address" name="institution_address" class="form-control" rows="2"><?php echo htmlspecialchars(getSetting('institution_address', '')); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="institution_contact">Contact Information</label>
                                        <input type="text" id="institution_contact" name="institution_contact" class="form-control" value="<?php echo htmlspecialchars(getSetting('institution_contact', '')); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="accreditation_agency">Accreditation Agency</label>
                                        <input type="text" id="accreditation_agency" name="accreditation_agency" class="form-control" value="<?php echo htmlspecialchars(getSetting('accreditation_agency', 'AACCUP')); ?>">
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Save Information</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- System Maintenance -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-tools"></i> System Maintenance</h2>
                            </div>
                            <div class="card-body">
                                <div class="maintenance-actions">
                                    <div class="action-card">
                                        <div class="action-info">
                                            <div class="action-icon">
                                                <i class="fas fa-trash-alt"></i>
                                            </div>
                                            <div class="action-details">
                                                <h3>Clear Cache</h3>
                                                <p>Remove temporary files to free up space and fix potential issues.</p>
                                            </div>
                                        </div>
                                        <form action="system.php" method="post">
                                            <input type="hidden" name="action" value="clear_cache">
                                            <button type="submit" class="btn btn-accent btn-block">Clear Cache</button>
                                        </form>
                                    </div>
                                    
                                    <div class="action-card">
                                        <div class="action-info">
                                            <div class="action-icon">
                                                <i class="fas fa-stethoscope"></i>
                                            </div>
                                            <div class="action-details">
                                                <h3>Run Diagnostics</h3>
                                                <p>Check system health, configurations, and potential issues.</p>
                                            </div>
                                        </div>
                                        <form action="system.php" method="post">
                                            <input type="hidden" name="action" value="run_diagnostics">
                                            <button type="submit" class="btn btn-accent btn-block">Run Diagnostics</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Server Information -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-server"></i> Server Information</h2>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table server-info-table">
                                        <tbody>
                                            <?php foreach ($serverInfo as $label => $value): ?>
                                            <tr>
                                                <th><?php echo $label; ?></th>
                                                <td><?php echo $value; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="server-info-footer">
                                    <div class="uptime-info">
                                        <?php
                                        // Try to get server uptime (works on Linux servers)
                                        $uptime = @shell_exec('uptime');
                                        if ($uptime): 
                                        ?>
                                            <strong>Server Uptime:</strong> <?php echo trim($uptime); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="php-info-link">
                                        <a href="phpinfo.php" target="_blank" class="btn btn-sm btn-secondary">
                                            <i class="fab fa-php"></i> View PHP Info
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize particles.js
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
                    "value": "#4A90E2"
                },
                "shape": {
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                    "polygon": {
                        "nb_sides": 5
                    }
                },
                "opacity": {
                    "value": 0.1,
                    "random": false,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#808080",
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
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
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
                        "enable": false,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 140,
                        "line_linked": {
                            "opacity": 0.3
                        }
                    }
                }
            },
            "retina_detect": true
        });
    });
    </script>
</body>
</html> 