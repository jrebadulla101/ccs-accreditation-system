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

// Define settings categories
$categories = [
    'general' => 'General Settings',
    'appearance' => 'Appearance & Theme',
    'email' => 'Email Configuration',
    'security' => 'Security & Access Control',
    'backup' => 'Backup & Maintenance'
];

// Get active category
$activeCategory = isset($_GET['category']) && array_key_exists($_GET['category'], $categories) ? $_GET['category'] : 'general';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form data based on category
    switch ($activeCategory) {
        case 'general':
            // Update general settings
            $siteName = cleanInput($_POST['site_name']);
            $siteDescription = cleanInput($_POST['site_description']);
            $adminEmail = cleanInput($_POST['admin_email']);
            $dateFormat = cleanInput($_POST['date_format']);
            $timeFormat = cleanInput($_POST['time_format']);
            
            // Update settings in database
            updateSetting('site_name', $siteName);
            updateSetting('site_description', $siteDescription);
            updateSetting('admin_email', $adminEmail);
            updateSetting('date_format', $dateFormat);
            updateSetting('time_format', $timeFormat);
            
            setFlashMessage("success", "General settings updated successfully.");
            break;
            
        case 'appearance':
            // Update appearance settings
            $primaryColor = cleanInput($_POST['primary_color']);
            $accentColor = cleanInput($_POST['accent_color']);
            $logoPath = ''; // Initialize
            
            // Handle logo upload if provided
            if (isset($_FILES['site_logo']) && $_FILES['site_logo']['size'] > 0) {
                // Logo upload handling logic
                $uploadDir = '../../uploads/settings/';
                $fileName = 'logo_' . time() . '_' . basename($_FILES['site_logo']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Check file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
                if (in_array($_FILES['site_logo']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadFile)) {
                        $logoPath = 'uploads/settings/' . $fileName;
                        updateSetting('site_logo', $logoPath);
                    } else {
                        setFlashMessage("danger", "Failed to upload logo file.");
                    }
                } else {
                    setFlashMessage("danger", "Invalid file type. Please upload a JPG, PNG, GIF or SVG file.");
                }
            }
            
            // Update other appearance settings
            updateSetting('primary_color', $primaryColor);
            updateSetting('accent_color', $accentColor);
            updateSetting('enable_particles', isset($_POST['enable_particles']) ? '1' : '0');
            updateSetting('sidebar_style', cleanInput($_POST['sidebar_style']));
            
            setFlashMessage("success", "Appearance settings updated successfully.");
            break;
            
        case 'email':
            // Update email settings
            $smtpHost = cleanInput($_POST['smtp_host']);
            $smtpPort = intval($_POST['smtp_port']);
            $smtpUser = cleanInput($_POST['smtp_username']);
            $smtpPass = $_POST['smtp_password']; // Don't clean password to preserve special chars
            $smtpEncryption = cleanInput($_POST['smtp_encryption']);
            $emailFrom = cleanInput($_POST['email_from']);
            $emailFromName = cleanInput($_POST['email_from_name']);
            
            // Update settings in database
            updateSetting('smtp_host', $smtpHost);
            updateSetting('smtp_port', $smtpPort);
            updateSetting('smtp_username', $smtpUser);
            
            // Only update password if provided
            if (!empty($smtpPass)) {
                updateSetting('smtp_password', $smtpPass);
            }
            
            updateSetting('smtp_encryption', $smtpEncryption);
            updateSetting('email_from', $emailFrom);
            updateSetting('email_from_name', $emailFromName);
            updateSetting('enable_email_notifications', isset($_POST['enable_email_notifications']) ? '1' : '0');
            
            setFlashMessage("success", "Email settings updated successfully.");
            break;
            
        case 'security':
            // Update security settings
            $sessionTimeout = intval($_POST['session_timeout']);
            $maxLoginAttempts = intval($_POST['max_login_attempts']);
            $passwordPolicy = cleanInput($_POST['password_policy']);
            
            // Update settings in database
            updateSetting('session_timeout', $sessionTimeout);
            updateSetting('max_login_attempts', $maxLoginAttempts);
            updateSetting('password_policy', $passwordPolicy);
            updateSetting('enable_2fa', isset($_POST['enable_2fa']) ? '1' : '0');
            updateSetting('require_password_change', isset($_POST['require_password_change']) ? '1' : '0');
            
            setFlashMessage("success", "Security settings updated successfully.");
            break;
            
        case 'backup':
            // Handle backup and maintenance actions
            if (isset($_POST['action']) && $_POST['action'] == 'backup') {
                // Backup database logic
                if (createDatabaseBackup()) {
                    setFlashMessage("success", "Database backup created successfully.");
                } else {
                    setFlashMessage("danger", "Failed to create database backup.");
                }
            } elseif (isset($_POST['action']) && $_POST['action'] == 'clear_logs') {
                // Clear old logs
                $daysToKeep = intval($_POST['days_to_keep']);
                if ($daysToKeep > 0) {
                    $cleared = clearOldLogs($daysToKeep);
                    setFlashMessage("success", "$cleared old log entries removed successfully.");
                } else {
                    setFlashMessage("danger", "Invalid number of days provided.");
                }
            } elseif (isset($_POST['action']) && $_POST['action'] == 'optimize') {
                // Optimize database
                if (optimizeDatabase()) {
                    setFlashMessage("success", "Database optimized successfully.");
                } else {
                    setFlashMessage("danger", "Failed to optimize database.");
                }
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header("Location: index.php?category=$activeCategory");
    exit();
}

// Get existing settings
$settings = getAllSettings();

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Helper function to update a setting
function updateSetting($key, $value) {
    global $conn;
    $key = $conn->real_escape_string($key);
    $value = $conn->real_escape_string($value);
    
    // Check if setting exists
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

// Helper function to get all settings
function getAllSettings() {
    global $conn;
    $settings = [];
    
    $query = "SELECT setting_key, setting_value FROM settings";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings;
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
                $row[$j] = addslashes($row[$j]);
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
    
    $query = "DELETE FROM activity_logs WHERE created_at < '$date'";
    $conn->query($query);
    
    return $conn->affected_rows;
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

// Helper function for category icons
function getCategoryIcon($category) {
    $icons = [
        'general' => 'cog',
        'appearance' => 'palette',
        'email' => 'envelope',
        'security' => 'shield-alt',
        'backup' => 'database'
    ];
    
    return $icons[$category] ?? 'cog';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - CCS Accreditation System</title>
    
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
    
    /* Settings Container Styles */
    .settings-container {
        display: flex;
        gap: 24px;
    }
    
    .settings-sidebar {
        flex: 0 0 250px;
    }
    
    .settings-content {
        flex: 1;
    }
    
    .settings-nav {
        background-color: var(--card-bg);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    
    .settings-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 20px;
        color: var(--text-color);
        text-decoration: none;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }
    
    .settings-nav-item:last-child {
        border-bottom: none;
    }
    
    .settings-nav-item:hover {
        background-color: var(--bg-light);
        color: var(--accent-color);
    }
    
    .settings-nav-item.active {
        background-color: var(--bg-light);
        color: var(--accent-color);
        border-left: 3px solid var(--accent-color);
        font-weight: 500;
    }
    
    .settings-nav-item i {
        font-size: 18px;
        width: 24px;
        text-align: center;
    }
    
    .settings-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .settings-header h2 {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 22px;
        color: var(--text-color);
    }
    
    .settings-header h2 i {
        color: var(--accent-color);
    }
    
    .settings-body {
        background-color: var(--card-bg);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        padding: 24px;
    }
    
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .form-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .form-section h3 {
        font-size: 18px;
        margin-bottom: 15px;
        color: var(--text-color);
    }
    
    .form-row {
        margin-bottom: 20px;
    }
    
    .form-row label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .checkbox-row {
        display: flex;
        flex-direction: column;
    }
    
    .custom-control {
        padding-left: 2.5rem;
    }
    
    .custom-control-label {
        font-weight: 500;
    }
    
    .custom-control-label::before,
    .custom-control-label::after {
        top: 0.25rem;
    }
    
    .form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
        text-align: right;
    }
    
    /* Logo Upload Styles */
    .logo-upload-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .current-logo {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px;
        background-color: var(--bg-light);
        border-radius: 6px;
        border: 1px solid var(--border-color);
    }
    
    .current-logo img {
        max-height: 60px;
        max-width: 100%;
        margin-bottom: 10px;
    }
    
    .current-logo span {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    /* Color Picker Styles */
    .color-picker-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-control-color {
        width: 50px;
        height: 38px;
        padding: 3px;
    }
    
    .color-text {
        width: 100px;
    }
    
    /* Theme Preview Styles */
    .theme-preview {
        background-color: var(--bg-light);
        border-radius: 6px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .preview-header {
        background-color: var(--primary-color);
        color: white;
        padding: 10px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .preview-logo {
        font-weight: bold;
        letter-spacing: 1px;
    }
    
    .preview-actions {
        display: flex;
        gap: 10px;
    }
    
    .preview-action {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.3);
    }
    
    .preview-content {
        display: flex;
        height: 200px;
    }
    
    .preview-sidebar {
        width: 70px;
        background-color: #f0f2f5;
        padding: 15px 10px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .preview-sidebar.dark {
        background-color: #333;
    }
    
    .preview-sidebar.gradient {
        background: linear-gradient(135deg, var(--primary-color) 0%, #333 100%);
    }
    
    .preview-menu-item {
        height: 20px;
        border-radius: 4px;
        background-color: rgba(0, 0, 0, 0.1);
    }
    
    .preview-menu-item.active {
        background-color: var(--primary-color);
    }
    
    .preview-sidebar.dark .preview-menu-item {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .preview-sidebar.gradient .preview-menu-item {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .preview-main {
        flex: 1;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .preview-card {
        background-color: white;
        height: 100px;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .preview-button {
        width: 80px;
        height: 30px;
        border-radius: 4px;
        background-color: var(--accent-color);
    }
    
    /* Existing Backups Table */
    .existing-backups {
        margin-top: 20px;
    }
    
    .existing-backups h4 {
        margin-bottom: 15px;
        font-size: 16px;
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
        
        .settings-container {
            flex-direction: column;
        }
        
        .settings-sidebar {
            flex: 0 0 auto;
        }
        
        .settings-nav {
            margin-bottom: 20px;
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
                            <li><a href="index.php" class="submenu-link">General Settings</a></li>
                            <li><a href="system.php" class="submenu-link">System Settings</a></li>
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
                    <h1>System Settings</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">System Settings</li>
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
                
                <div class="settings-container">
                    <div class="settings-sidebar">
                        <div class="settings-nav">
                            <?php foreach ($categories as $slug => $name): ?>
                            <a href="?category=<?php echo $slug; ?>" class="settings-nav-item <?php echo $activeCategory === $slug ? 'active' : ''; ?>">
                                <i class="fas fa-<?php echo getCategoryIcon($slug); ?>"></i>
                                <span><?php echo $name; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="settings-content">
                        <div class="settings-header">
                            <h2>
                                <i class="fas fa-<?php echo getCategoryIcon($activeCategory); ?>"></i>
                                <?php echo $categories[$activeCategory]; ?>
                            </h2>
                        </div>
                        
                        <div class="settings-body">
                            <form action="index.php?category=<?php echo $activeCategory; ?>" method="post" enctype="multipart/form-data">
                                <?php if ($activeCategory === 'general'): ?>
                                    <!-- General Settings -->
                                    <div class="form-section">
                                        <div class="form-group">
                                            <label for="site_name">Site Name</label>
                                            <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars(getSetting('site_name', 'CCS Accreditation System')); ?>">
                                            <small class="form-text text-muted">The name of your accreditation system.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="site_description">Site Description</label>
                                            <textarea id="site_description" name="site_description" class="form-control" rows="2"><?php echo htmlspecialchars(getSetting('site_description', 'Manage accreditation for programs and institutions')); ?></textarea>
                                            <small class="form-text text-muted">A brief description of the system.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="admin_email">Administrator Email</label>
                                            <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_email', 'admin@example.com')); ?>">
                                            <small class="form-text text-muted">The main administrator email address.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="date_format">Date Format</label>
                                            <select id="date_format" name="date_format" class="form-control">
                                                <option value="F j, Y" <?php echo getSetting('date_format') === 'F j, Y' ? 'selected' : ''; ?>>January 1, 2023</option>
                                                <option value="Y-m-d" <?php echo getSetting('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>2023-01-01</option>
                                                <option value="m/d/Y" <?php echo getSetting('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>01/01/2023</option>
                                                <option value="d/m/Y" <?php echo getSetting('date_format') === 'd/m/Y' ? 'selected' : ''; ?>>01/01/2023</option>
                                                <option value="d.m.Y" <?php echo getSetting('date_format') === 'd.m.Y' ? 'selected' : ''; ?>>01.01.2023</option>
                                            </select>
                                            <small class="form-text text-muted">The format for displaying dates throughout the system.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="time_format">Time Format</label>
                                            <select id="time_format" name="time_format" class="form-control">
                                                <option value="g:i a" <?php echo getSetting('time_format') === 'g:i a' ? 'selected' : ''; ?>>1:30 pm</option>
                                                <option value="g:i A" <?php echo getSetting('time_format') === 'g:i A' ? 'selected' : ''; ?>>1:30 PM</option>
                                                <option value="H:i" <?php echo getSetting('time_format') === 'H:i' ? 'selected' : ''; ?>>13:30</option>
                                            </select>
                                            <small class="form-text text-muted">The format for displaying times throughout the system.</small>
                                        </div>
                                    </div>
                                
                                <?php elseif ($activeCategory === 'appearance'): ?>
                                    <!-- Appearance & Theme Settings -->
                                    <div class="form-section">
                                        <div class="form-group">
                                            <label for="site_logo">Site Logo</label>
                                            <div class="logo-upload-container">
                                                <?php $logoPath = getSetting('site_logo'); ?>
                                                <?php if (!empty($logoPath)): ?>
                                                    <div class="current-logo">
                                                        <img src="<?php echo "../../" . $logoPath; ?>" alt="Current Logo">
                                                        <span>Current Logo</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="logo-upload">
                                                    <input type="file" id="site_logo" name="site_logo" class="form-control-file">
                                                    <small class="form-text text-muted">Recommended size: 200x60px. Supported formats: JPG, PNG, GIF, SVG.</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="primary_color">Primary Color</label>
                                            <div class="color-picker-container">
                                                <input type="color" id="primary_color" name="primary_color" class="form-control form-control-color" value="<?php echo getSetting('primary_color', '#4A90E2'); ?>">
                                                <input type="text" id="primary_color_text" class="form-control color-text" value="<?php echo getSetting('primary_color', '#4A90E2'); ?>">
                                            </div>
                                            <small class="form-text text-muted">The main color used throughout the system.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="accent_color">Accent Color</label>
                                            <div class="color-picker-container">
                                                <input type="color" id="accent_color" name="accent_color" class="form-control form-control-color" value="<?php echo getSetting('accent_color', '#34C759'); ?>">
                                                <input type="text" id="accent_color_text" class="form-control color-text" value="<?php echo getSetting('accent_color', '#34C759'); ?>">
                                            </div>
                                            <small class="form-text text-muted">Used for highlights and call-to-action elements.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="sidebar_style">Sidebar Style</label>
                                            <select id="sidebar_style" name="sidebar_style" class="form-control">
                                                <option value="default" <?php echo getSetting('sidebar_style') === 'default' ? 'selected' : ''; ?>>Default (Light)</option>
                                                <option value="dark" <?php echo getSetting('sidebar_style') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                                <option value="gradient" <?php echo getSetting('sidebar_style') === 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                            </select>
                                            <small class="form-text text-muted">The style of the sidebar navigation.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="enable_particles" name="enable_particles" <?php echo getSetting('enable_particles', '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="enable_particles">Enable Particles Background</label>
                                            </div>
                                            <small class="form-text text-muted">Show the animated particles background effect throughout the system.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3>Preview</h3>
                                        <div class="theme-preview">
                                            <div class="preview-header" style="background-color: var(--primary-color);">
                                                <div class="preview-logo">LOGO</div>
                                                <div class="preview-actions">
                                                    <div class="preview-action"></div>
                                                    <div class="preview-action"></div>
                                                </div>
                                            </div>
                                            <div class="preview-content">
                                                <div class="preview-sidebar" id="preview-sidebar">
                                                    <div class="preview-menu-item active"></div>
                                                    <div class="preview-menu-item"></div>
                                                    <div class="preview-menu-item"></div>
                                                    <div class="preview-menu-item"></div>
                                                </div>
                                                <div class="preview-main">
                                                    <div class="preview-card"></div>
                                                    <div class="preview-button" style="background-color: var(--accent-color);"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                
                                <?php elseif ($activeCategory === 'email'): ?>
                                    <!-- Email Configuration -->
                                    <div class="form-section">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="enable_email_notifications" name="enable_email_notifications" <?php echo getSetting('enable_email_notifications', '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="enable_email_notifications">Enable Email Notifications</label>
                                            </div>
                                            <small class="form-text text-muted">Send email notifications for important events like new user registrations, evidence uploads, etc.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email_from">From Email Address</label>
                                            <input type="email" id="email_from" name="email_from" class="form-control" value="<?php echo htmlspecialchars(getSetting('email_from', 'noreply@example.com')); ?>">
                                            <small class="form-text text-muted">The email address that all system emails will be sent from.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email_from_name">From Name</label>
                                            <input type="text" id="email_from_name" name="email_from_name" class="form-control" value="<?php echo htmlspecialchars(getSetting('email_from_name', 'CCS Accreditation System')); ?>">
                                            <small class="form-text text-muted">The name that will appear as the sender of system emails.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3>SMTP Configuration</h3>
                                        <div class="form-group">
                                            <label for="smtp_host">SMTP Host</label>
                                            <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.example.com')); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="smtp_port">SMTP Port</label>
                                            <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="smtp_encryption">Encryption</label>
                                            <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                                <option value="tls" <?php echo getSetting('smtp_encryption') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo getSetting('smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="" <?php echo getSetting('smtp_encryption') === '' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="smtp_username">SMTP Username</label>
                                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="smtp_password">SMTP Password</label>
                                            <input type="password" id="smtp_password" name="smtp_password" class="form-control" placeholder="••••••••">
                                            <small class="form-text text-muted">Leave blank to keep the current password.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <button type="button" id="test_email_btn" class="btn btn-secondary">
                                            <i class="fas fa-paper-plane"></i> Send Test Email
                                        </button>
                                        <div id="test_email_result" class="mt-3"></div>
                                    </div>
                                
                                <?php elseif ($activeCategory === 'security'): ?>
                                    <!-- Security & Access Control -->
                                    <div class="form-section">
                                        <div class="form-group">
                                            <label for="session_timeout">Session Timeout (minutes)</label>
                                            <input type="number" id="session_timeout" name="session_timeout" class="form-control" value="<?php echo intval(getSetting('session_timeout', '30')); ?>" min="5" max="1440">
                                            <small class="form-text text-muted">How long a user session remains active after inactivity (5-1440 minutes).</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="max_login_attempts">Max Login Attempts</label>
                                            <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" value="<?php echo intval(getSetting('max_login_attempts', '5')); ?>" min="3" max="10">
                                            <small class="form-text text-muted">Maximum number of failed login attempts before temporary lockout (3-10 attempts).</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="password_policy">Password Policy</label>
                                            <select id="password_policy" name="password_policy" class="form-control">
                                                <option value="basic" <?php echo getSetting('password_policy') === 'basic' ? 'selected' : ''; ?>>Basic (min 8 characters)</option>
                                                <option value="medium" <?php echo getSetting('password_policy') === 'medium' ? 'selected' : ''; ?>>Medium (min 8 chars, requires numbers)</option>
                                                <option value="strong" <?php echo getSetting('password_policy') === 'strong' ? 'selected' : ''; ?>>Strong (min 8 chars, upper/lowercase, numbers)</option>
                                                <option value="very_strong" <?php echo getSetting('password_policy') === 'very_strong' ? 'selected' : ''; ?>>Very Strong (min 10 chars, upper/lowercase, numbers, special chars)</option>
                                            </select>
                                            <small class="form-text text-muted">Password strength requirements for all users.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="enable_2fa" name="enable_2fa" <?php echo getSetting('enable_2fa', '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="enable_2fa">Enable Two-Factor Authentication</label>
                                            </div>
                                            <small class="form-text text-muted">Allow users to enable two-factor authentication for their accounts.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="require_password_change" name="require_password_change" <?php echo getSetting('require_password_change', '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="require_password_change">Require Password Change on First Login</label>
                                            </div>
                                            <small class="form-text text-muted">Force new users to change their password when they first log in.</small>
                                        </div>
                                    </div>
                                
                                <?php elseif ($activeCategory === 'backup'): ?>
                                    <!-- Backup & Maintenance -->
                                    <div class="form-section">
                                        <h3>Database Backup</h3>
                                        <p>Create a backup of your database to prevent data loss. Backups are stored in the /backups directory.</p>
                                        
                                        <input type="hidden" name="action" value="backup">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-download"></i> Create Database Backup
                                        </button>
                                        
                                        <!-- List existing backups -->
                                        <?php
                                        $backupDir = '../../backups/';
                                        $backupFiles = glob($backupDir . '*.sql');
                                        if (!empty($backupFiles)):
                                        ?>
                                        <div class="existing-backups mt-4">
                                            <h4>Existing Backups</h4>
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>File Name</th>
                                                        <th>Size</th>
                                                        <th>Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($backupFiles as $file): ?>
                                                        <tr>
                                                            <td><?php echo basename($file); ?></td>
                                                            <td><?php echo formatFileSize(filesize($file)); ?></td>
                                                            <td><?php echo date('F j, Y g:i a', filemtime($file)); ?></td>
                                                            <td>
                                                                <a href="download_backup.php?file=<?php echo urlencode(basename($file)); ?>" class="btn btn-sm btn-secondary">
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3>Log Management</h3>
                                        <p>Remove old log entries to keep your database lean.</p>
                                        
                                        <form action="index.php?category=backup" method="post" class="mt-3">
                                            <input type="hidden" name="action" value="clear_logs">
                                            <div class="form-group">
                                                <label for="days_to_keep">Remove Logs Older Than</label>
                                                <div class="input-group">
                                                    <input type="number" id="days_to_keep" name="days_to_keep" class="form-control" value="30" min="7" max="365">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">days</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-warning mt-3">
                                                <i class="fas fa-trash-alt"></i> Clear Old Logs
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h3>Database Optimization</h3>
                                        <p>Optimize database tables to improve performance.</p>
                                        
                                        <form action="index.php?category=backup" method="post">
                                            <input type="hidden" name="action" value="optimize">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-database"></i> Optimize Database
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($activeCategory !== 'backup'): ?>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
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
        
        // Color Picker Sync
        const primaryColorInput = document.getElementById('primary_color');
        const primaryColorText = document.getElementById('primary_color_text');
        const accentColorInput = document.getElementById('accent_color');
        const accentColorText = document.getElementById('accent_color_text');
        
        if (primaryColorInput && primaryColorText) {
            primaryColorInput.addEventListener('input', function() {
                primaryColorText.value = this.value;
                document.documentElement.style.setProperty('--primary-color', this.value);
            });
            
            primaryColorText.addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    primaryColorInput.value = this.value;
                    document.documentElement.style.setProperty('--primary-color', this.value);
                }
            });
        }
        
        if (accentColorInput && accentColorText) {
            accentColorInput.addEventListener('input', function() {
                accentColorText.value = this.value;
                document.documentElement.style.setProperty('--accent-color', this.value);
            });
            
            accentColorText.addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    accentColorInput.value = this.value;
                    document.documentElement.style.setProperty('--accent-color', this.value);
                }
            });
        }
        
        // Sidebar Style Preview
        const sidebarStyleSelect = document.getElementById('sidebar_style');
        const previewSidebar = document.getElementById('preview-sidebar');
        
        if (sidebarStyleSelect && previewSidebar) {
            sidebarStyleSelect.addEventListener('change', function() {
                // Remove all classes
                previewSidebar.className = 'preview-sidebar';
                
                // Add selected style class
                if (this.value !== 'default') {
                    previewSidebar.classList.add(this.value);
                }
            });
            
            // Set initial state
            if (sidebarStyleSelect.value !== 'default') {
                previewSidebar.classList.add(sidebarStyleSelect.value);
            }
        }
        
        // Test Email Button
        const testEmailBtn = document.getElementById('test_email_btn');
        if (testEmailBtn) {
            testEmailBtn.addEventListener('click', function() {
                const resultDiv = document.getElementById('test_email_result');
                resultDiv.innerHTML = '<div class="alert alert-info">Sending test email...</div>';
                
                // Collect form data
                const formData = new FormData();
                formData.append('action', 'test_email');
                formData.append('smtp_host', document.getElementById('smtp_host').value);
                formData.append('smtp_port', document.getElementById('smtp_port').value);
                formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
                formData.append('smtp_username', document.getElementById('smtp_username').value);
                formData.append('smtp_password', document.getElementById('smtp_password').value);
                formData.append('email_from', document.getElementById('email_from').value);
                formData.append('email_from_name', document.getElementById('email_from_name').value);
                
                // Send AJAX request
                fetch('test_email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success">Test email sent successfully!</div>';
                    } else {
                        resultDiv.innerHTML = `<div class="alert alert-danger">Failed to send test email: ${data.error}</div>`;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                });
            });
        }
    });
    </script>
</body>
</html>
 