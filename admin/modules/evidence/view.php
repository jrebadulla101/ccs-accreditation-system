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

// Format File Size function
function formatFileSize($bytes) {
    if (!$bytes) return "N/A";
    if ($bytes < 1024) return $bytes . " B";
    else if ($bytes < 1048576) return round($bytes / 1024, 1) . " KB";
    else if ($bytes < 1073741824) return round($bytes / 1048576, 1) . " MB";
    else return round($bytes / 1073741824, 1) . " GB";
}

// Get evidence ID from URL
$evidenceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($evidenceId <= 0) {
    setFlashMessage("danger", "Invalid evidence ID.");
    header("Location: list.php");
    exit();
}

// Check permissions
$hasApprovePermission = hasPermission('approve_evidence');
$hasEditPermission = hasPermission('edit_evidence');
$hasDeletePermission = hasPermission('delete_evidence');

// Get evidence details
$sql = "SELECT e.*, p.name as parameter_name, p.id as parameter_id, 
               sp.name as sub_parameter_name, sp.id as sub_parameter_id,
               a.name as area_name, a.id as area_id, 
               pr.name as program_name, pr.id as program_id,
               u.full_name as uploader_name, u.email as uploader_email
        FROM parameter_evidence e
        LEFT JOIN parameters p ON e.parameter_id = p.id
        LEFT JOIN sub_parameters sp ON e.sub_parameter_id = sp.id
        LEFT JOIN area_levels a ON p.area_level_id = a.id
        LEFT JOIN programs pr ON a.program_id = pr.id
        LEFT JOIN admin_users u ON e.uploaded_by = u.id
        WHERE e.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evidenceId);
$stmt->execute();
$result = $stmt->get_result();

// Check if evidence exists
if ($result->num_rows === 0) {
    setFlashMessage("danger", "Evidence not found.");
    header("Location: list.php");
    exit();
}

$evidence = $result->fetch_assoc();
$isSubParameter = (!empty($evidence['sub_parameter_id']));

// Check if user has permission to view this evidence
$canView = false;

// Super admin or admin with view_evidence permission can view all evidence
if (hasPermission('view_evidence')) {
    $canView = true;
} else {
    // Check if this user has specific permission for this parameter
    if (isset($evidence['parameter_id'])) {
        $permCheckQuery = "SELECT can_view FROM parameter_user_permissions 
                          WHERE user_id = ? AND parameter_id = ?";
        $permCheckStmt = $conn->prepare($permCheckQuery);
        $permCheckStmt->bind_param("ii", $_SESSION['admin_id'], $evidence['parameter_id']);
        $permCheckStmt->execute();
        $permResult = $permCheckStmt->get_result();
        
        if ($permResult->num_rows > 0) {
            $permRow = $permResult->fetch_assoc();
            $canView = ($permRow['can_view'] == 1);
        }
    }
    
    // Check if user is the uploader
    if (!$canView && isset($evidence['uploaded_by']) && $evidence['uploaded_by'] == $_SESSION['admin_id']) {
        $canView = true;
    }
}

if (!$canView) {
    setFlashMessage("danger", "You don't have permission to view this evidence.");
    header("Location: list.php");
    exit();
}

// Process approval/rejection if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$hasApprovePermission) {
        setFlashMessage("danger", "You don't have permission to approve or reject evidence.");
        header("Location: view.php?id=" . $evidenceId);
        exit();
    }
    
    $action = $_POST['action'];
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Update evidence status
    $updateSql = "UPDATE parameter_evidence SET status = ?, status_comment = ?, status_updated_by = ?, status_updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ssis", $newStatus, $comment, $_SESSION['admin_id'], $evidenceId);
    
    if ($updateStmt->execute()) {
        // Log activity
        $activity = ($action === 'approve') ? "approved" : "rejected";
        $evidenceTitle = isset($evidence['title']) ? $evidence['title'] : "Evidence #" . $evidenceId;
        logActivity($_SESSION['admin_id'], "Evidence " . $activity, "Evidence '" . $evidenceTitle . "' was " . $activity . ".");
        
        setFlashMessage("success", "Evidence has been " . $activity . " successfully.");
    } else {
        setFlashMessage("danger", "Error updating evidence status: " . $conn->error);
    }
    
    header("Location: view.php?id=" . $evidenceId);
    exit();
}

// Define $statusUpdatedDate with a default empty value
$statusUpdatedDate = 'Not available';

// Then check if status_updated_at exists and has a value before using strtotime
if (isset($evidence['status_updated_at']) && !empty($evidence['status_updated_at'])) {
    $statusUpdatedDate = date('M d, Y', strtotime($evidence['status_updated_at']));
}

// Updated logActivity function with default for $conn parameter
/**
 * Log user activity to the activity_logs table
 * 
 * @param int $userId User ID performing the action
 * @param string $activityType Type of activity
 * @param string $description Description of the activity
 * @param mysqli $conn Database connection (optional, will use global $conn if not provided)
 * @return bool True if logging succeeded, false otherwise
 */
function logActivity($userId, $activityType, $description, $conn = null) {
    // Use the global connection if not provided
    if ($conn === null) {
        global $conn;
    }
    
    // If still no connection, return false
    if (!$conn) {
        return false;
    }
    
    // Check if the activity_logs table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($checkTable->num_rows === 0) {
        return false; // Table doesn't exist
    }
    
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    try {
        $query = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $userId, $activityType, $description, $ipAddress);
        return $stmt->execute();
    } catch (Exception $e) {
        // If anything goes wrong, fail silently
        return false;
    }
}

// Then update the approve/reject code to use our function or an alternative approach
if (isset($_POST['approve_evidence']) || isset($_POST['reject_evidence'])) {
    // Determine the new status based on which button was clicked
    $newStatus = isset($_POST['approve_evidence']) ? 'approved' : 'rejected';
    $actionType = isset($_POST['approve_evidence']) ? 'approve_evidence' : 'reject_evidence';
    $actionDescription = isset($_POST['approve_evidence']) ? "Approved" : "Rejected";
    
    // Check if the user has permission to approve/reject evidence
    if (!hasPermission('approve_evidence')) {
        setFlashMessage("danger", "You don't have permission to " . $actionDescription . " evidence.");
        header("Location: view.php?id=" . $evidenceId);
        exit();
    }
    
    // Get current user ID for status_updated_by
    $userId = $_SESSION['admin_id'];
    
    // We'll build the query and parameters dynamically based on what columns exist
    $updateFields = ["status = ?"];
    $updateParams = [$newStatus];
    $types = "s";
    
    // Check for status_updated_at column
    $checkColumn = $conn->query("SHOW COLUMNS FROM parameter_evidence LIKE 'status_updated_at'");
    if ($checkColumn->num_rows > 0) {
        $updateFields[] = "status_updated_at = NOW()";
    }
    
    // Check for status_comment column
    $checkColumn = $conn->query("SHOW COLUMNS FROM parameter_evidence LIKE 'status_comment'");
    if ($checkColumn->num_rows > 0 && isset($_POST['status_comment'])) {
        $updateFields[] = "status_comment = ?";
        $updateParams[] = trim($_POST['status_comment']);
        $types .= "s";
    }
    
    // Check for status_updated_by column
    $checkColumn = $conn->query("SHOW COLUMNS FROM parameter_evidence LIKE 'status_updated_by'");
    if ($checkColumn->num_rows > 0) {
        $updateFields[] = "status_updated_by = ?";
        $updateParams[] = $userId;
        $types .= "i";
    }
    
    // Add the evidence ID as the last parameter
    $updateParams[] = $evidenceId;
    $types .= "i";
    
    // Build the final query
    $query = "UPDATE parameter_evidence SET " . implode(", ", $updateFields) . " WHERE id = ?";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    
    // Bind parameters dynamically
    $bindParams = array($types);
    foreach ($updateParams as $key => $value) {
        $bindParams[] = &$updateParams[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    
    if ($stmt->execute()) {
        // Log the activity with the correct parameters
        $logDescription = "$actionDescription evidence ID: $evidenceId";
        logActivity($userId, $actionType, $logDescription);
        
        setFlashMessage("success", "Evidence has been $actionDescription successfully.");
        header("Location: view.php?id=" . $evidenceId);
        exit();
    } else {
        setFlashMessage("danger", "Failed to $actionDescription evidence. Error: " . $conn->error);
        header("Location: view.php?id=" . $evidenceId);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Evidence - EARIST Accreditation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
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
            cursor: pointer;
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
        
        .page-header-actions {
            display: flex;
            gap: 10px;
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
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Evidence Styles */
        .evidence-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .evidence-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meta-item i {
            color: var(--primary-color);
        }
        
        .evidence-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: var(--warning);
            color: #212529;
        }
        
        .status-approved {
            background-color: var(--success);
            color: white;
        }
        
        .status-rejected {
            background-color: var(--danger);
            color: white;
        }
        
        .evidence-description {
            background-color: var(--bg-light);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .evidence-info-group {
            margin-bottom: 10px;
        }
        
        .evidence-info-group label {
            display: block;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        
        .evidence-info-group span {
            display: block;
        }
        
        .evidence-file-preview {
            margin-top: 20px;
        }
        
        .file-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .file-preview-title {
            font-weight: 600;
            font-size: 16px;
        }
        
        .file-container {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }
        
        .file-iframe {
            width: 100%;
            height: 500px;
            border: none;
        }
        
        .file-image {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        
        .file-icon-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 20px;
            background-color: var(--bg-light);
            text-align: center;
        }
        
        .file-icon {
            font-size: 64px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .file-name {
            font-weight: 500;
            margin-bottom: 10px;
            word-break: break-all;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
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
            background-color: #2cb04c;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dd3325;
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }
        
        .btn-block {
            display: block;
            width: 100%;
            text-align: center;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* Alert Styles */
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid transparent;
        }
        
        .alert-info {
            background-color: rgba(74, 144, 226, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .alert-success {
            background-color: rgba(52, 199, 89, 0.1);
            border-color: var(--success);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(255, 59, 48, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .alert-warning {
            background-color: rgba(248, 194, 0, 0.1);
            border-color: var(--warning);
            color: var(--warning);
        }
        
        /* Layout Utilities */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        
        .col-8, .col-4 {
            padding: 10px;
            box-sizing: border-box;
        }
        
        .col-8 {
            width: 66.666667%;
        }
        
        .col-4 {
            width: 33.333333%;
        }
        
        .mt-3 {
            margin-top: 15px;
        }
        
        .mb-3 {
            margin-bottom: 15px;
        }
        
        .text-center {
            text-align: center;
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
            
            .col-8, .col-4 {
                width: 100%;
            }
            
            .evidence-meta {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>

    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../../assets/images/logo.png" alt="EARIST Logo">
                <h1>Accreditation System</h1>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li class="menu-item">
                        <a href="../../dashboard.php" class="menu-link">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-graduation-cap"></i> Programs
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
                            <i class="fas fa-layer-group"></i> Areas
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
                            <i class="fas fa-clipboard-list"></i> Parameters
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
                            <i class="fas fa-file-alt"></i> Evidence
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu" style="display: block;">
                            <li><a href="list.php" class="submenu-link">View All Evidence</a></li>
                            <?php if (hasPermission('add_evidence')): ?>
                            <li><a href="add.php" class="submenu-link">Upload New Evidence</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php if (hasPermission('view_users')): ?>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <i class="fas fa-users"></i> Users Management
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
                            <i class="fas fa-user-tag"></i> Roles & Permissions
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
                            <i class="fas fa-cog"></i> Settings
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
                    <i class="fas fa-sign-out-alt"></i> Logout
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
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
                        <div class="user-role">
                            <?php 
                            // Get user role name
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
                
                <div class="page-header">
                    <h1>Evidence Details</h1>
                    <div class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                            <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $evidence['program_id']; ?>"><?php echo htmlspecialchars($evidence['program_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $evidence['area_id']; ?>"><?php echo htmlspecialchars($evidence['area_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $evidence['parameter_id']; ?>"><?php echo htmlspecialchars($evidence['parameter_name']); ?></a></li>
                            
                            <?php if ($isSubParameter): ?>
                            <li class="breadcrumb-item"><a href="../parameters/view_sub_parameter.php?id=<?php echo $evidence['sub_parameter_id']; ?>"><?php echo htmlspecialchars($evidence['sub_parameter_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="list.php?sub_parameter_id=<?php echo $evidence['sub_parameter_id']; ?>">Evidence</a></li>
                            <?php else: ?>
                            <li class="breadcrumb-item"><a href="list.php?parameter_id=<?php echo $evidence['parameter_id']; ?>">Evidence</a></li>
                            <?php endif; ?>
                            
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($evidence['title']); ?></li>
                        </ol>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-8">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><?php echo htmlspecialchars($evidence['title']); ?></h2>
                                <div class="card-actions">
                                    <?php if (hasPermission('edit_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                    <a href="edit.php?id=<?php echo $evidenceId; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('delete_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                    <a href="delete.php?id=<?php echo $evidenceId; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this evidence?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="evidence-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Uploaded: <?php echo date('F j, Y', strtotime($evidence['created_at'])); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>By: <?php echo htmlspecialchars($evidence['uploader_name'] ?? 'Unknown'); ?></span>
                                    </div>
                                    
                                    <?php
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'clock';
                                    $statusText = 'Pending';
                                    
                                    if ($evidence['status'] == 'approved') {
                                        $statusClass = 'status-approved';
                                        $statusIcon = 'check-circle';
                                        $statusText = 'Approved';
                                    } elseif ($evidence['status'] == 'rejected') {
                                        $statusClass = 'status-rejected';
                                        $statusIcon = 'times-circle';
                                        $statusText = 'Rejected';
                                    }
                                    ?>
                                    
                                    <div class="evidence-status <?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        <span><?php echo $statusText; ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($evidence['description'])): ?>
                                <div class="evidence-description">
                                    <h3>Description</h3>
                                    <p><?php echo nl2br(htmlspecialchars($evidence['description'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($evidence['status_comment'])): ?>
                                <div class="evidence-description">
                                    <h3>Reviewer Comments</h3>
                                    <p><?php echo nl2br(htmlspecialchars($evidence['status_comment'])); ?></p>
                                    <div class="mt-3">
                                        <?php
                                        // Get reviewer name
                                        $reviewerName = "Unknown";
                                        if (!empty($evidence['status_updated_by'])) {
                                            $reviewerQuery = "SELECT full_name FROM admin_users WHERE id = ?";
                                            $reviewerStmt = $conn->prepare($reviewerQuery);
                                            $reviewerStmt->bind_param("i", $evidence['status_updated_by']);
                                            $reviewerStmt->execute();
                                            $reviewerResult = $reviewerStmt->get_result();
                                            if ($reviewerResult->num_rows > 0) {
                                                $reviewerName = $reviewerResult->fetch_assoc()['full_name'];
                                            }
                                        }
                                        ?>
                                        <small>Reviewed by <?php echo htmlspecialchars($reviewerName); ?> on <?php echo $statusUpdatedDate; ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- File Preview -->
                                <div class="evidence-file-preview">
                                    <div class="file-preview-header">
                                        <div class="file-preview-title">Evidence Content</div>
                                        <div>
                                            <?php if (!empty($evidence['file_path'])): ?>
                                            <a href="download.php?id=<?php echo $evidenceId; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($evidence['drive_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($evidence['drive_link']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fas fa-external-link-alt"></i> Open in New Tab
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="file-container">
                                        <?php if (!empty($evidence['file_path'])): ?>
                                            <?php
                                            $fileExtension = pathinfo($evidence['file_path'], PATHINFO_EXTENSION);
                                            $filePath = '../../uploads/' . $evidence['file_path'];
                                            $fileName = basename($evidence['file_path']);
                                            
                                            // Determine file type for display
                                            $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']);
                                            $isPdf = strtolower($fileExtension) == 'pdf';
                                            ?>
                                            
                                            <?php if ($isImage): ?>
                                                <img src="<?php echo $filePath; ?>" class="file-image" alt="<?php echo htmlspecialchars($fileName); ?>">
                                            <?php elseif ($isPdf): ?>
                                                <iframe src="<?php echo $filePath; ?>" class="file-iframe" title="<?php echo htmlspecialchars($fileName); ?>"></iframe>
                                            <?php else: ?>
                                                <div class="file-icon-container">
                                                    <?php
                                                    // Set appropriate icon based on file type
                                                    $fileIcon = 'fa-file';
                                                    
                                                    if (in_array(strtolower($fileExtension), ['doc', 'docx'])) {
                                                        $fileIcon = 'fa-file-word';
                                                    } elseif (in_array(strtolower($fileExtension), ['xls', 'xlsx'])) {
                                                        $fileIcon = 'fa-file-excel';
                                                    } elseif (in_array(strtolower($fileExtension), ['ppt', 'pptx'])) {
                                                        $fileIcon = 'fa-file-powerpoint';
                                                    } elseif (in_array(strtolower($fileExtension), ['zip', 'rar'])) {
                                                        $fileIcon = 'fa-file-archive';
                                                    } elseif (strtolower($fileExtension) == 'txt') {
                                                        $fileIcon = 'fa-file-alt';
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $fileIcon; ?> file-icon"></i>
                                                    <div class="file-name"><?php echo htmlspecialchars($fileName); ?></div>
                                                    <p>This file type cannot be previewed. Please download to view.</p>
                                                </div>
                                            <?php endif; ?>
                                            
                                        <?php elseif (!empty($evidence['drive_link'])): ?>
                                            <?php
                                            // Try to extract Google Drive file ID for embedding
                                            $driveId = '';
                                            $driveLink = $evidence['drive_link'];
                                            
                                            if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $driveLink, $matches)) {
                                                $driveId = $matches[1];
                                            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveLink, $matches)) {
                                                $driveId = $matches[1];
                                            }
                                            
                                            if (!empty($driveId)) {
                                                $embedLink = "https://drive.google.com/file/d/{$driveId}/preview";
                                            }
                                            ?>
                                            
                                            <?php if (!empty($driveId)): ?>
                                                <iframe src="<?php echo $embedLink; ?>" class="file-iframe" title="Google Drive Document"></iframe>
                                            <?php else: ?>
                                                <div class="file-icon-container">
                                                    <i class="fab fa-google-drive file-icon"></i>
                                                    <div class="file-name">Google Drive Link</div>
                                                    <p>Click the button below to open this document in Google Drive.</p>
                                                    <a href="<?php echo htmlspecialchars($driveLink); ?>" target="_blank" class="btn btn-primary mt-3">
                                                        <i class="fas fa-external-link-alt"></i> Open in Google Drive
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                        <?php else: ?>
                                            <div class="file-icon-container">
                                                <i class="fas fa-exclamation-circle file-icon"></i>
                                                <div class="file-name">No file or link available</div>
                                                <p>This evidence doesn't have any attached file or external link.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (hasPermission('approve_evidence') && $evidence['status'] == 'pending'): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h2 class="card-title">Review Evidence</h2>
                                    </div>
                                    <div class="card-body">
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $evidenceId); ?>" method="post">
                                            <div class="form-group">
                                                <label for="comment" class="form-label">Comments (Optional)</label>
                                                <textarea id="comment" name="comment" class="form-control" rows="3"></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Evidence Details</h2>
                            </div>
                            <div class="card-body">
                                <div class="evidence-info-group">
                                    <label>Parameter:</label>
                                    <span><?php echo htmlspecialchars($evidence['parameter_name']); ?></span>
                                </div>
                                
                                <?php if ($isSubParameter): ?>
                                <div class="evidence-info-group">
                                    <label>Sub-Parameter:</label>
                                    <span><?php echo htmlspecialchars($evidence['sub_parameter_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="evidence-info-group">
                                    <label>Area:</label>
                                    <span><?php echo htmlspecialchars($evidence['area_name']); ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Program:</label>
                                    <span><?php echo htmlspecialchars($evidence['program_name']); ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Uploaded By:</label>
                                    <span><?php echo htmlspecialchars($evidence['uploader_name'] ?? 'Unknown'); ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Date Uploaded:</label>
                                    <span><?php echo date('F j, Y', strtotime($evidence['created_at'])); ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Last Updated:</label>
                                    <span><?php echo $statusUpdatedDate; ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Evidence Type:</label>
                                    <span><?php echo !empty($evidence['file_path']) ? 'File Upload' : 'Google Drive Link'; ?></span>
                                </div>
                                
                                <div class="evidence-info-group">
                                    <label>Status:</label>
                                    <span class="evidence-status <?php echo $statusClass; ?>" style="display: inline-flex;">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        <span><?php echo $statusText; ?></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h2 class="card-title">Actions</h2>
                            </div>
                            <div class="card-body">
                                <a href="<?php echo $isSubParameter ? 'list.php?sub_parameter_id=' . $evidence['sub_parameter_id'] : 'list.php?parameter_id=' . $evidence['parameter_id']; ?>" class="btn btn-secondary btn-block mb-3">
                                    <i class="fas fa-arrow-left"></i> Back to Evidence List
                                </a>
                                
                                <?php if (hasPermission('edit_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                <a href="edit.php?id=<?php echo $evidenceId; ?>" class="btn btn-primary btn-block mb-3">
                                    <i class="fas fa-edit"></i> Edit Evidence
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($evidence['file_path'])): ?>
                                <a href="download.php?id=<?php echo $evidenceId; ?>" class="btn btn-success btn-block mb-3">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($evidence['drive_link'])): ?>
                                <a href="<?php echo htmlspecialchars($evidence['drive_link']); ?>" target="_blank" class="btn btn-success btn-block mb-3">
                                    <i class="fas fa-external-link-alt"></i> Open Google Drive Link
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('delete_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by']): ?>
                                <a href="delete.php?id=<?php echo $evidenceId; ?>" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this evidence? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i> Delete Evidence
                                </a>
                                <?php endif; ?>
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