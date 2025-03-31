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

// Format File Size - Add this function to fix the error
function formatFileSize($bytes) {
    if (!$bytes) return "N/A";
    if ($bytes < 1024) return $bytes . " B";
    else if ($bytes < 1048576) return round($bytes / 1024, 1) . " KB";
    else if ($bytes < 1073741824) return round($bytes / 1048576, 1) . " MB";
    else return round($bytes / 1073741824, 1) . " GB";
}

// Get parameter ID from URL
$parameterId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($parameterId <= 0) {
    setFlashMessage("danger", "Invalid parameter ID.");
    header("Location: list.php");
    exit();
}

// Check if user has permission to view this parameter
$hasAssignPermission = hasPermission('assign_parameters');
$hasApproveEvidence = hasPermission('approve_evidence');
$hasEditPermission = hasPermission('edit_parameter');
$hasDeletePermission = hasPermission('delete_parameter');

// Get parameter details with area and program info
$sql = "SELECT p.*, a.name as area_name, a.id as area_id, pr.name as program_name, pr.id as program_id 
        FROM parameters p 
        JOIN area_levels a ON p.area_level_id = a.id 
        JOIN programs pr ON a.program_id = pr.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parameterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Parameter not found.");
    header("Location: list.php");
    exit();
}

$parameter = $result->fetch_assoc();

// Check if the user has permission to view this parameter
$canView = false;

// Super admin or admin with view_parameters permission can view all parameters
if (hasPermission('view_parameters')) {
    $canView = true;
} else {
    // Check if this user has specific permission for this parameter
    $permCheckQuery = "SELECT can_view FROM parameter_user_permissions WHERE user_id = ? AND parameter_id = ?";
    $permCheckStmt = $conn->prepare($permCheckQuery);
    $permCheckStmt->bind_param("ii", $_SESSION['admin_id'], $parameterId);
    $permCheckStmt->execute();
    $permResult = $permCheckStmt->get_result();
    
    if ($permResult->num_rows > 0) {
        $permRow = $permResult->fetch_assoc();
        $canView = ($permRow['can_view'] == 1);
    }
    
    // Also check area-level permissions if not found at parameter level
    if (!$canView) {
        $areaPermCheckQuery = "SELECT can_view FROM area_user_permissions WHERE user_id = ? AND area_id = ?";
        $areaPermCheckStmt = $conn->prepare($areaPermCheckQuery);
        $areaPermCheckStmt->bind_param("ii", $_SESSION['admin_id'], $parameter['area_id']);
        $areaPermCheckStmt->execute();
        $areaPermResult = $areaPermCheckStmt->get_result();
        
        if ($areaPermResult->num_rows > 0) {
            $areaPermRow = $areaPermResult->fetch_assoc();
            $canView = ($areaPermRow['can_view'] == 1);
        }
    }
}

if (!$canView) {
    setFlashMessage("danger", "You don't have permission to view this parameter.");
    header("Location: list.php");
    exit();
}

// Get the list of users with permissions for this parameter
$userPermissionsQuery = "SELECT pu.*, au.username, au.full_name, au.email
                        FROM parameter_user_permissions pu
                        JOIN admin_users au ON pu.user_id = au.id
                        WHERE pu.parameter_id = ?
                        ORDER BY au.full_name ASC";
$userPermissionsStmt = $conn->prepare($userPermissionsQuery);
$userPermissionsStmt->bind_param("i", $parameterId);
$userPermissionsStmt->execute();
$userPermissionsResult = $userPermissionsStmt->get_result();

// Get evidence files for this parameter
$evidenceQuery = "SELECT e.*, au.full_name as uploaded_by, au.id as uploaded_by_id
                 FROM parameter_evidence e
                 LEFT JOIN admin_users au ON e.uploaded_by = au.id
                 WHERE e.parameter_id = ?
                 ORDER BY e.created_at DESC";
$evidenceStmt = $conn->prepare($evidenceQuery);
$evidenceStmt->bind_param("i", $parameterId);
$evidenceStmt->execute();
$evidenceResult = $evidenceStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($parameter['name']); ?> - Parameter Details - CCS Accreditation System</title>
    
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
    
    /* Admin Container */
    .admin-container {
        display: flex;
        min-height: 100vh;
    }
    
    /* Sidebar */
    .sidebar {
        width: 260px;
        background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
        color: #fff;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        transition: all 0.3s ease;
    }

    .sidebar-brand {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-brand h2 {
        color: #fff;
        font-size: 20px;
        margin: 0;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .sidebar-menu li {
        margin-bottom: 5px;
    }

    .sidebar-menu a {
        display: block;
        color: rgba(255, 255, 255, 0.7);
        padding: 12px 20px;
        transition: all 0.3s ease;
    }

    .sidebar-menu a:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    .sidebar-menu a.active {
        background-color: var(--primary-color);
        color: #fff;
    }

    .sidebar-menu a i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    .sidebar-menu .has-submenu .submenu {
        display: none;
        background-color: rgba(0, 0, 0, 0.2);
    }

    .sidebar-menu .has-submenu.active .submenu {
        display: block;
    }

    .sidebar-menu .submenu a {
        padding-left: 50px;
    }

    .sidebar-toggle {
        background-color: transparent;
        border: none;
        color: #fff;
        font-size: 24px;
        position: absolute;
        top: 10px;
        right: 10px;
        cursor: pointer;
        display: none;
    }
    
    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: 260px;
        transition: all 0.3s ease;
    }
    
    /* Header */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 99;
    }

    .menu-toggle {
        background: none;
        border: none;
        font-size: 24px;
        color: var(--text-color);
        cursor: pointer;
        display: none;
    }

    .search-box {
        flex: 1;
        margin: 0 20px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        max-width: 400px;
        padding: 10px 15px;
        padding-left: 40px;
        border: 1px solid var(--border-color);
        border-radius: 30px;
        background-color: var(--bg-light);
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .user-dropdown {
        position: relative;
    }

    .user-info {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-right: 10px;
    }

    .user-name {
        font-weight: 500;
    }

    .user-role {
        font-size: 12px;
        color: var(--text-muted);
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background-color: #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border-radius: 4px;
        width: 200px;
        display: none;
        z-index: 1000;
    }

    .dropdown-menu.active {
        display: block;
    }

    .dropdown-menu a {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        color: var(--text-color);
        transition: all 0.2s ease;
    }

    .dropdown-menu a:hover {
        background-color: var(--bg-light);
    }

    .dropdown-menu a i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    
    /* Content */
    .content {
        padding: 20px;
    }
    
    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .page-title h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
    }

    .breadcrumb {
        display: flex;
        list-style: none;
        align-items: center;
        margin: 0;
        padding: 0;
        font-size: 14px;
    }

    .breadcrumb-item {
        color: var(--text-muted);
    }

    .breadcrumb-item a {
        color: var(--text-muted);
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: var(--text-color);
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "/";
        margin: 0 8px;
        color: var(--text-muted);
    }

    .page-actions {
        display: flex;
        gap: 10px;
    }

    /* Cards */
    .card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: rgba(245, 247, 250, 0.5);
    }

    .card-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        color: var(--text-color);
    }

    .card-body {
        padding: 20px;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 4px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 13px;
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #3a80d1;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-success {
        background-color: var(--success);
        color: #fff;
    }

    .btn-success:hover {
        background-color: #2db94d;
    }

    .btn-danger {
        background-color: var(--danger);
        color: #fff;
    }

    .btn-danger:hover {
        background-color: #e52b20;
    }

    .btn-warning {
        background-color: var(--warning);
        color: #212529;
    }

    .btn-warning:hover {
        background-color: #e0ae00;
    }

    .btn i {
        margin-right: 5px;
    }

    /* Parameter info */
    .parameter-info-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .info-group {
        margin-bottom: 15px;
    }

    .info-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-muted);
        margin-bottom: 5px;
    }

    .info-group span, .info-group p {
        font-weight: 500;
        color: var(--text-color);
    }

    /* Status badge */
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-active {
        background-color: rgba(52, 199, 89, 0.1);
        color: var(--success);
    }

    .status-inactive {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }

    .status-pending {
        background-color: rgba(248, 194, 0, 0.1);
        color: var(--warning);
    }

    /* Tables */
    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table th, table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    table th {
        font-weight: 600;
        color: var(--text-color);
        background-color: rgba(245, 247, 250, 0.5);
    }

    table td {
        vertical-align: middle;
    }

    table tr:last-child td {
        border-bottom: none;
    }

    /* User info cell */
    .user-info-cell {
        display: flex;
        flex-direction: column;
    }

    .user-info-cell strong {
        font-weight: 500;
    }

    .user-info-cell small {
        font-size: 12px;
        color: var(--text-muted);
    }

    /* Stats */
    .stat-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        padding: 15px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        background-color: rgba(74, 144, 226, 0.1);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-right: 15px;
    }

    .stat-content {
        flex: 1;
    }

    .stat-value {
        font-size: 22px;
        font-weight: 600;
        color: var(--text-color);
        line-height: 1;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-muted);
    }

    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .btn-icon.btn-info {
        background-color: rgba(74, 144, 226, 0.1);
        color: var(--primary-color);
    }

    .btn-icon.btn-info:hover {
        background-color: var(--primary-color);
        color: #fff;
    }

    .btn-icon.btn-primary {
        background-color: rgba(74, 144, 226, 0.1);
        color: var(--primary-color);
    }

    .btn-icon.btn-primary:hover {
        background-color: var(--primary-color);
        color: #fff;
    }

    .btn-icon.btn-danger {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }

    .btn-icon.btn-danger:hover {
        background-color: var(--danger);
        color: #fff;
    }

    .btn-icon.btn-warning {
        background-color: rgba(248, 194, 0, 0.1);
        color: var(--warning);
    }

    .btn-icon.btn-warning:hover {
        background-color: var(--warning);
        color: #212529;
    }

    /* Badge */
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .badge-success {
        background-color: rgba(52, 199, 89, 0.1);
        color: var(--success);
    }

    .badge-danger {
        background-color: rgba(255, 59, 48, 0.1);
        color: var(--danger);
    }

    /* No data message */
    .no-data-message {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
    }

    .no-data-message i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .no-data-message p {
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    /* Particles.js */
    #particles-js {
        position: fixed;
        width: 100%;
        height: 100%;
        z-index: -1;
    }

    /* Responsive styles */
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

        .menu-toggle {
            display: block;
        }

        .parameter-info-container {
            grid-template-columns: 1fr;
        }

        .sidebar-toggle {
            display: block;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .page-title {
            margin-bottom: 10px;
        }

        .stat-container {
            grid-template-columns: 1fr;
        }

        .search-box {
            display: none;
        }
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
    }
    
    .col-md-6 {
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        margin-bottom: 20px;
    }
    
    @media (min-width: 768px) {
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
    
    .parameter-info-card {
        display: flex;
        background-color: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        height: 100%;
        transition: all 0.3s ease;
    }
    
    .parameter-info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .parameter-info-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        font-size: 20px;
        margin-right: 15px;
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    }
    
    .parameter-info-content {
        flex: 1;
    }
    
    .parameter-info-content label {
        display: block;
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 2px;
    }
    
    .parameter-info-content h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
        line-height: 1.3;
    }
    
    .description-section, .benchmark-section, .system-input-section {
        margin-top: 25px;
        padding: 20px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .description-section h3, .benchmark-section h3, .system-input-section h3 {
        display: flex;
        align-items: center;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--primary-color);
    }
    
    .description-section h3 i, .benchmark-section h3 i, .system-input-section h3 i {
        margin-right: 8px;
    }
    
    .description-content, .benchmark-content {
        padding: 10px;
        background-color: var(--bg-light);
        border-radius: 6px;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .score-display {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 80px;
    }
    
    .score-value {
        font-size: 40px;
        font-weight: 700;
        color: var(--primary-color);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    
    .text-muted.small {
        font-size: 12px;
    }

    /* Stats Card Styles */
    .stats-card {
        margin-bottom: 30px;
    }
    
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .animated-stat {
        position: relative;
        background-color: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: all 0.3s ease;
        z-index: 1;
    }
    
    .animated-stat:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background-color: var(--bg-light);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 15px;
        position: relative;
        z-index: 2;
    }
    
    .stat-content {
        position: relative;
        z-index: 2;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-color);
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        color: var(--text-muted);
    }
    
    .stat-wave {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 100px;
        z-index: 0;
    }
    
    /* Permissions Section Styles */
    .permissions-section {
        margin-top: 30px;
    }
    
    .section-title {
        display: flex;
        align-items: center;
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--primary-color);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
    }
    
    .section-title i {
        margin-right: 10px;
    }
    
    .custom-table {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    
    .custom-table table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .custom-table th {
        background-color: var(--bg-light);
        color: var(--text-color);
        font-weight: 600;
        text-align: left;
        padding: 12px 15px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .custom-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.2s ease;
    }
    
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    
    .custom-table tr:hover td {
        background-color: rgba(74, 144, 226, 0.03);
    }
    
    .permission-col {
        width: 80px;
    }
    
    .user-info-cell {
        display: flex;
        align-items: center;
    }
    
    .user-avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 10px;
        font-size: 14px;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-details strong {
        font-weight: 500;
        color: var(--text-color);
    }
    
    .user-details small {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .text-success, .text-danger {
        font-size: 16px;
    }
    
    .text-success {
        color: var(--success);
    }
    
    .text-danger {
        color: var(--danger);
    }
    
    /* Empty State Styles */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
        background-color: var(--bg-light);
        border-radius: 10px;
    }
    
    .empty-state-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--text-muted);
        margin-bottom: 15px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    .empty-state h4 {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    /* Sub-Parameters Styles */
    .sub-parameters-card {
        margin-bottom: 30px;
    }
    
    .sub-parameters-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .sub-parameter-card {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .sub-parameter-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .sub-parameter-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--bg-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .sub-parameter-title {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: var(--text-color);
    }
    
    .sub-parameter-badge {
        display: flex;
    }
    
    .sub-parameter-body {
        padding: 15px 20px;
        flex: 1;
    }
    
    .sub-parameter-description {
        font-size: 14px;
        color: var(--text-color);
        margin-bottom: 15px;
        line-height: 1.5;
        max-height: 80px;
        overflow: hidden;
    }
    
    .sub-parameter-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-top: 10px;
    }
    
    .sub-parameter-meta .meta-item {
        display: flex;
        align-items: center;
        font-size: 13px;
        color: var(--text-muted);
    }
    
    .sub-parameter-meta .meta-item i {
        margin-right: 5px;
        color: var(--primary-color);
    }
    
    .sub-parameter-actions {
        display: flex;
        align-items: center;
        justify-content: space-around;
        padding: 10px 20px;
        background-color: rgba(245, 247, 250, 0.5);
        border-top: 1px solid var(--border-color);
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--text-color);
        background-color: white;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .btn-icon:hover {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: scale(1.1);
    }
    
    /* Evidence Summary Styles */
    .evidence-summary-card {
        margin-bottom: 30px;
    }
    
    .evidence-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    @media (max-width: 992px) {
        .evidence-summary-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .summary-chart-container {
        background-color: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 20px;
        text-align: center;
    }
    
    .status-chart {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .chart-legend {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        font-size: 13px;
        color: var(--text-color);
    }
    
    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 3px;
        margin-right: 5px;
    }
    
    .progress-chart {
        height: 24px;
        border-radius: 12px;
        background-color: #f5f5f5;
        display: flex;
        overflow: hidden;
    }
    
    .progress-segment {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: width 0.3s ease;
    }
    
    .progress-segment.approved {
        background-color: var(--success);
    }
    
    .progress-segment.pending {
        background-color: var(--warning);
    }
    
    .progress-segment.rejected {
        background-color: var(--danger);
    }
    
    .empty-chart {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 150px;
        color: var(--text-muted);
    }
    
    .empty-chart-icon {
        font-size: 48px;
        margin-bottom: 10px;
        opacity: 0.3;
    }
    
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .summary-stat-item {
        background-color: white;
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }
    
    .summary-stat-item:hover {
        transform: translateY(-3px);
    }
    
    .summary-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-right: 15px;
    }
    
    .summary-stat-content {
        flex: 1;
    }
    
    .summary-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-color);
    }
    
    .summary-stat-label {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .evidence-notice {
        display: flex;
        align-items: center;
        padding: 15px;
        background-color: rgba(74, 144, 226, 0.05);
        border-left: 4px solid var(--primary-color);
        border-radius: 4px;
        font-size: 14px;
        color: var(--text-color);
    }
    
    .evidence-notice i {
        color: var(--primary-color);
        font-size: 18px;
        margin-right: 10px;
    }
    
    .mt-3 {
        margin-top: 15px;
    }
    
    .mt-4 {
        margin-top: 20px;
    }
    
    .mb-3 {
        margin-bottom: 15px;
    }
    
    .small {
        font-size: 12px;
    }
    
    .text-muted {
        color: var(--text-muted);
    }
    </style>
</head>
<body>
    <!-- Particles.js background -->
    <div id="particles-js"></div>

    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2>CCS Accreditation</h2>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-times"></i>
            </button>
            <ul class="sidebar-menu">
                <li>
                    <a href="../../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-graduation-cap"></i> Programs
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../programs/list.php">View All Programs</a></li>
                        <?php if (hasPermission('add_program')): ?>
                        <li><a href="../programs/add.php">Add New Program</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-layer-group"></i> Areas
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../areas/list.php">View All Areas</a></li>
                        <?php if (hasPermission('add_area')): ?>
                        <li><a href="../areas/add.php">Add New Area</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="has-submenu active">
                    <a href="#" class="submenu-toggle active">
                        <i class="fas fa-clipboard-list"></i> Parameters
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu" style="display: block;">
                        <li><a href="list.php">View All Parameters</a></li>
                        <?php if (hasPermission('add_parameter')): ?>
                        <li><a href="add.php">Add New Parameter</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-file-alt"></i> Evidence
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../evidence/list.php">View All Evidence</a></li>
                        <?php if (hasPermission('add_evidence')): ?>
                        <li><a href="../evidence/add.php">Add New Evidence</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php if (hasPermission('view_users')): ?>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-users"></i> Users Management
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../users/list.php">View All Users</a></li>
                        <?php if (hasPermission('add_user')): ?>
                        <li><a href="../users/add.php">Add New User</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('view_roles') || hasPermission('assign_permissions')): ?>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-user-shield"></i> Roles & Permissions
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <?php if (hasPermission('view_roles')): ?>
                        <li><a href="../roles/list.php">View All Roles</a></li>
                        <?php endif; ?>
                        <?php if (hasPermission('assign_permissions')): ?>
                        <li><a href="../permissions/assign.php">Assign Permissions</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('view_settings')): ?>
                <li class="has-submenu">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-cogs"></i> Settings
                        <i class="fas fa-chevron-right submenu-indicator"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../settings/system.php">System Settings</a></li>
                        <li><a href="../settings/maintenance.php">Maintenance</a></li>
                        <li><a href="../settings/logs.php">Activity Logs</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="user-dropdown">
                    <div class="user-info" id="userDropdownToggle">
                        <div class="user-avatar">
                            <?php 
                            $initials = '';
                            $nameParts = explode(' ', $_SESSION['admin_name']);
                            foreach($nameParts as $part) {
                                $initials .= substr($part, 0, 1);
                            }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator'); ?></div>
                        </div>
                    </div>
                    <div class="dropdown-menu" id="userDropdownMenu">
                        <a href="../../profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="../../change-password.php"><i class="fas fa-key"></i> Change Password</a>
                        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1><?php echo htmlspecialchars($parameter['name']); ?></h1>
                <nav class="breadcrumb-container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                        <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo htmlspecialchars($parameter['program_name']); ?></a></li>
                        <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo htmlspecialchars($parameter['area_name']); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($parameter['name']); ?></li>
                    </ol>
                </nav>
            </div>

            <!-- Parameter Details -->
            <div class="parameter-container">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Parameter Details</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('edit_parameter')): ?>
                            <a href="edit.php?id=<?php echo $parameterId; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('delete_parameter')): ?>
                            <a href="delete.php?id=<?php echo $parameterId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this parameter? This will also delete all associated sub-parameters and evidence.');">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Parameter Name</label>
                                        <h3><?php echo htmlspecialchars($parameter['name']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Area Level</label>
                                        <h3><?php echo htmlspecialchars($parameter['area_name']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Program</label>
                                        <h3><?php echo htmlspecialchars($parameter['program_name']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Weight</label>
                                        <h3><?php echo htmlspecialchars($parameter['weight']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (isset($parameter['sort_order'])): ?>
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-sort-numeric-down"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Sort Order</label>
                                        <h3><?php echo htmlspecialchars($parameter['sort_order']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Status</label>
                                        <h3>
                                            <span class="status-badge status-<?php echo strtolower($parameter['status']); ?>">
                                                <?php echo ucfirst($parameter['status']); ?>
                                            </span>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-calendar-plus"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Created</label>
                                        <h3><?php echo date('F j, Y', strtotime($parameter['created_at'])); ?></h3>
                                        <span class="text-muted small"><?php echo date('g:i A', strtotime($parameter['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="parameter-info-card">
                                    <div class="parameter-info-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="parameter-info-content">
                                        <label>Last Updated</label>
                                        <h3><?php echo date('F j, Y', strtotime($parameter['updated_at'])); ?></h3>
                                        <span class="text-muted small"><?php echo date('g:i A', strtotime($parameter['updated_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($parameter['description'])): ?>
                        <div class="description-section">
                            <h3><i class="fas fa-align-left"></i> Description</h3>
                            <div class="description-content">
                                <?php echo nl2br(htmlspecialchars($parameter['description'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($parameter['benchmark']) && !empty($parameter['benchmark'])): ?>
                        <div class="benchmark-section">
                            <h3><i class="fas fa-chart-line"></i> Benchmark</h3>
                            <div class="benchmark-content">
                                <?php echo nl2br(htmlspecialchars($parameter['benchmark'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($parameter['system_input'])): ?>
                        <div class="system-input-section">
                            <h3><i class="fas fa-sliders-h"></i> System Input Score</h3>
                            <div class="system-input-content">
                                <div class="score-display">
                                    <span class="score-value"><?php echo htmlspecialchars($parameter['system_input']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="parameter-stats card">
                    <div class="card-header">
                        <h2 class="card-title">Parameter Statistics</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('assign_parameters')): ?>
                            <a href="assign_permissions.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-lock"></i> Manage Permissions</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="stat-container">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-file-upload"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $evidenceResult ? $evidenceResult->num_rows : 0; ?></div>
                                    <div class="stat-label">Evidence Files</div>
                                </div>
                            </div>
                            
                            <?php
                            // Count users with permissions for this parameter
                            $userCountQuery = "SELECT COUNT(*) as total_users FROM parameter_user_permissions WHERE parameter_id = ?";
                            $userCountStmt = $conn->prepare($userCountQuery);
                            $userCountStmt->bind_param("i", $parameterId);
                            $userCountStmt->execute();
                            $userCountResult = $userCountStmt->get_result();
                            $userCount = $userCountResult->fetch_assoc()['total_users'];
                            ?>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $userCount; ?></div>
                                    <div class="stat-label">Users with Access</div>
                                </div>
                            </div>
                            
                            <?php
                            // Get average score if available
                            $avgScore = "N/A"; // Default value since we can't calculate it
                            ?>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $avgScore; ?></div>
                                    <div class="stat-label">Average Score</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Permissions -->
                        <div class="permissions-section mt-4">
                            <h3>User Permissions</h3>
                            
                            <?php
                            // Get users with specific permissions for this parameter
                            $permissionsQuery = "SELECT pup.*, u.full_name, u.email, u.role 
                                               FROM parameter_user_permissions pup
                                               JOIN admin_users u ON pup.user_id = u.id
                                               WHERE pup.parameter_id = ?
                                               ORDER BY u.full_name ASC";
                            $permissionsStmt = $conn->prepare($permissionsQuery);
                            $permissionsStmt->bind_param("i", $parameterId);
                            $permissionsStmt->execute();
                            $permissionsResult = $permissionsStmt->get_result();
                            ?>
                            
                            <?php if ($permissionsResult && $permissionsResult->num_rows > 0): ?>
                                <div class="table-responsive mt-3">
                                    <table class="evidence-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th class="text-center">View</th>
                                                <th class="text-center">Add</th>
                                                <th class="text-center">Edit</th>
                                                <th class="text-center">Delete</th>
                                                <th class="text-center">Download</th>
                                                <th class="text-center">Approve</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($perm = $permissionsResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="user-info-cell">
                                                            <strong><?php echo htmlspecialchars($perm['full_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($perm['email']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $perm['role'])); ?></td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_view']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_add']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_edit']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_delete']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_download']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($perm['can_approve']): ?>
                                                            <i class="fas fa-check text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mt-3">No specific user permissions set for this parameter.</p>
                                <?php if (hasPermission('assign_parameters')): ?>
                                    <a href="assign_permissions.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-user-lock"></i> Assign Permissions
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parameter Statistics Section -->
            <div class="card stats-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-chart-bar"></i> Parameter Statistics
                    </h2>
                    <div class="card-actions">
                        <?php if (hasPermission('assign_parameters')): ?>
                        <a href="assign_permissions.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-lock"></i> Manage Permissions
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="stat-grid">
                        <div class="stat-item animated-stat">
                            <div class="stat-icon">
                                <i class="fas fa-file-upload"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $evidenceResult ? $evidenceResult->num_rows : 0; ?></div>
                                <div class="stat-label">Evidence Files</div>
                            </div>
                            <svg class="stat-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                                <path fill="rgba(74, 144, 226, 0.1)" d="M0,192L48,176C96,160,192,128,288,112C384,96,480,96,576,112C672,128,768,160,864,176C960,192,1056,192,1152,170.7C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                            </svg>
                        </div>
                        
                        <div class="stat-item animated-stat">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $userCount; ?></div>
                                <div class="stat-label">Users with Access</div>
                            </div>
                            <svg class="stat-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                                <path fill="rgba(52, 199, 89, 0.1)" d="M0,160L48,170.7C96,181,192,203,288,202.7C384,203,480,181,576,165.3C672,149,768,139,864,144C960,149,1056,171,1152,176C1248,181,1344,171,1392,165.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                            </svg>
                        </div>
                        
                        <div class="stat-item animated-stat">
                            <div class="stat-icon">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <div class="stat-content">
                                <?php
                                // Count sub-parameters
                                $subParamCountQuery = "SELECT COUNT(*) as total FROM sub_parameters WHERE parameter_id = ?";
                                $subParamCountStmt = $conn->prepare($subParamCountQuery);
                                $subParamCountStmt->bind_param("i", $parameterId);
                                $subParamCountStmt->execute();
                                $subParamCount = $subParamCountStmt->get_result()->fetch_assoc()['total'];
                                ?>
                                <div class="stat-value"><?php echo $subParamCount; ?></div>
                                <div class="stat-label">Sub-Parameters</div>
                            </div>
                            <svg class="stat-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                                <path fill="rgba(255, 192, 0, 0.1)" d="M0,32L48,48C96,64,192,96,288,117.3C384,139,480,149,576,133.3C672,117,768,75,864,80C960,85,1056,139,1152,144C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                            </svg>
                        </div>
                        
                        <div class="stat-item animated-stat">
                            <div class="stat-icon">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo isset($parameter['weight']) ? $parameter['weight'] : 'N/A'; ?></div>
                                <div class="stat-label">Parameter Weight</div>
                            </div>
                            <svg class="stat-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                                <path fill="rgba(92, 107, 192, 0.1)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,224C672,245,768,267,864,261.3C960,256,1056,224,1152,229.3L1248,235C1344,245,1392,267,1440,277.3L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- User Permissions -->
                    <div class="permissions-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-shield"></i> User Permissions
                        </h3>
                        
                        <?php if ($userPermissionsResult && $userPermissionsResult->num_rows > 0): ?>
                            <div class="table-responsive custom-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th class="text-center permission-col">View</th>
                                            <th class="text-center permission-col">Add</th>
                                            <th class="text-center permission-col">Edit</th>
                                            <th class="text-center permission-col">Delete</th>
                                            <th class="text-center permission-col">Download</th>
                                            <th class="text-center permission-col">Approve</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($perm = $userPermissionsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info-cell">
                                                        <div class="user-avatar-sm">
                                                            <?php echo strtoupper(substr($perm['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="user-details">
                                                            <strong><?php echo htmlspecialchars($perm['full_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($perm['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $perm['role'])); ?></td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_view']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_add']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_edit']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_delete']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_download']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($perm['can_approve']): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                                <h4>No User Permissions Set</h4>
                                <p>No specific user permissions have been assigned to this parameter yet.</p>
                                <?php if (hasPermission('assign_parameters')): ?>
                                    <a href="assign_permissions.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-primary mt-3">
                                        <i class="fas fa-user-lock"></i> Assign Permissions
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sub-Parameters Section -->
            <div class="card sub-parameters-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-sitemap"></i> Sub-Parameters
                    </h2>
                    <?php if (hasPermission('add_parameter')): ?>
                    <a href="add_sub_parameter.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Sub-Parameter
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Get sub-parameters
                    $subParamQuery = "SELECT * FROM sub_parameters WHERE parameter_id = ? ORDER BY name";
                    $subParamStmt = $conn->prepare($subParamQuery);
                    $subParamStmt->bind_param("i", $parameterId);
                    $subParamStmt->execute();
                    $subParamResult = $subParamStmt->get_result();
                    
                    if ($subParamResult->num_rows > 0):
                    ?>
                        <div class="sub-parameters-container">
                            <?php while ($subParam = $subParamResult->fetch_assoc()): 
                                // Count evidence files for this sub-parameter
                                $evidenceCountQuery = "SELECT COUNT(*) as total FROM parameter_evidence WHERE sub_parameter_id = ?";
                                $evidenceCountStmt = $conn->prepare($evidenceCountQuery);
                                $evidenceCountStmt->bind_param("i", $subParam['id']);
                                $evidenceCountStmt->execute();
                                $evidenceCount = $evidenceCountStmt->get_result()->fetch_assoc()['total'];
                            ?>
                                <div class="sub-parameter-card">
                                    <div class="sub-parameter-header">
                                        <h3 class="sub-parameter-title"><?php echo htmlspecialchars($subParam['name']); ?></h3>
                                        <div class="sub-parameter-badge">
                                            <span class="status-badge status-<?php echo isset($subParam['status']) && $subParam['status'] == 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo isset($subParam['status']) ? ucfirst($subParam['status']) : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="sub-parameter-body">
                                        <?php if (isset($subParam['description']) && !empty($subParam['description'])): ?>
                                            <div class="sub-parameter-description">
                                                <?php 
                                                $description = htmlspecialchars($subParam['description']); 
                                                echo (strlen($description) > 100) 
                                                    ? nl2br(substr($description, 0, 100) . '...') 
                                                    : nl2br($description);
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="sub-parameter-description text-muted">
                                                No description available
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="sub-parameter-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-weight"></i>
                                                <span>Weight: <?php echo isset($subParam['weight']) ? $subParam['weight'] : '0'; ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-file-alt"></i>
                                                <span><?php echo $evidenceCount; ?> <?php echo $evidenceCount == 1 ? 'file' : 'files'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="sub-parameter-actions">
                                        <a href="view_sub_parameter.php?id=<?php echo $subParam['id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('add_evidence')): ?>
                                        <a href="../evidence/add.php?sub_parameter_id=<?php echo $subParam['id']; ?>" class="btn-icon" title="Upload Evidence">
                                            <i class="fas fa-file-upload"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('edit_parameter')): ?>
                                        <a href="edit_sub_parameter.php?id=<?php echo $subParam['id']; ?>" class="btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_parameter')): ?>
                                        <a href="delete_sub_parameter.php?id=<?php echo $subParam['id']; ?>" class="btn-icon" title="Delete" onclick="return confirm('Are you sure you want to delete this sub-parameter? All associated evidence will also be deleted.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h4>No Sub-Parameters Found</h4>
                            <p>This parameter doesn't have any sub-parameters yet.</p>
                            <?php if (hasPermission('add_parameter')): ?>
                                <a href="add_sub_parameter.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus"></i> Add Sub-Parameter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Evidence Summary Section -->
            <div class="card evidence-summary-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-file-alt"></i> Evidence Summary
                    </h2>
                    <div class="card-actions">
                        <?php if (hasPermission('add_evidence')): ?>
                        <span class="text-muted small">Upload evidence through sub-parameters</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    // Get summary of evidence files for all sub-parameters of this parameter
                    $evidenceSummaryQuery = "SELECT 
                                             e.status,
                                             COUNT(*) as count 
                                         FROM parameter_evidence e
                                         JOIN sub_parameters sp ON e.sub_parameter_id = sp.id
                                         WHERE sp.parameter_id = ?
                                         GROUP BY e.status";
                    $evidenceSummaryStmt = $conn->prepare($evidenceSummaryQuery);
                    $evidenceSummaryStmt->bind_param("i", $parameterId);
                    $evidenceSummaryStmt->execute();
                    $evidenceSummaryResult = $evidenceSummaryStmt->get_result();
                    
                    $approved = 0;
                    $pending = 0;
                    $rejected = 0;
                    $total = 0;
                    
                    while ($row = $evidenceSummaryResult->fetch_assoc()) {
                        if ($row['status'] == 'approved') {
                            $approved = $row['count'];
                        } elseif ($row['status'] == 'rejected') {
                            $rejected = $row['count'];
                        } else {
                            $pending = $row['count'];
                        }
                        $total += $row['count'];
                    }
                    
                    // Calculate percentages for status chart
                    $approvedPercent = $total > 0 ? round(($approved / $total) * 100) : 0;
                    $pendingPercent = $total > 0 ? round(($pending / $total) * 100) : 0;
                    $rejectedPercent = $total > 0 ? round(($rejected / $total) * 100) : 0;
                    ?>
                    
                    <div class="evidence-summary-grid">
                        <div class="summary-chart-container">
                            <div class="chart-title">Evidence Status Distribution</div>
                            
                            <?php if ($total > 0): ?>
                            <div class="status-chart">
                                <div class="chart-legend">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background-color: var(--success);"></span>
                                        <span>Approved (<?php echo $approvedPercent; ?>%)</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background-color: var(--warning);"></span>
                                        <span>Pending (<?php echo $pendingPercent; ?>%)</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background-color: var(--danger);"></span>
                                        <span>Rejected (<?php echo $rejectedPercent; ?>%)</span>
                                    </div>
                                </div>
                                
                                <div class="progress-chart">
                                    <?php if ($approvedPercent > 0): ?>
                                    <div class="progress-segment approved" style="width: <?php echo $approvedPercent; ?>%;" title="Approved: <?php echo $approved; ?> files (<?php echo $approvedPercent; ?>%)"></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($pendingPercent > 0): ?>
                                    <div class="progress-segment pending" style="width: <?php echo $pendingPercent; ?>%;" title="Pending: <?php echo $pending; ?> files (<?php echo $pendingPercent; ?>%)"></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rejectedPercent > 0): ?>
                                    <div class="progress-segment rejected" style="width: <?php echo $rejectedPercent; ?>%;" title="Rejected: <?php echo $rejected; ?> files (<?php echo $rejectedPercent; ?>%)"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="empty-chart">
                                <div class="empty-chart-icon">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <p>No evidence data available</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="summary-stats">
                            <div class="summary-stat-item">
                                <div class="summary-stat-icon" style="background-color: rgba(52, 199, 89, 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="summary-stat-content">
                                    <div class="summary-stat-value"><?php echo $approved; ?></div>
                                    <div class="summary-stat-label">Approved</div>
                                </div>
                            </div>
                            
                            <div class="summary-stat-item">
                                <div class="summary-stat-icon" style="background-color: rgba(248, 194, 0, 0.1); color: var(--warning);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-stat-content">
                                    <div class="summary-stat-value"><?php echo $pending; ?></div>
                                    <div class="summary-stat-label">Pending</div>
                                </div>
                            </div>
                            
                            <div class="summary-stat-item">
                                <div class="summary-stat-icon" style="background-color: rgba(255, 59, 48, 0.1); color: var(--danger);">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="summary-stat-content">
                                    <div class="summary-stat-value"><?php echo $rejected; ?></div>
                                    <div class="summary-stat-label">Rejected</div>
                                </div>
                            </div>
                            
                            <div class="summary-stat-item">
                                <div class="summary-stat-icon" style="background-color: rgba(74, 144, 226, 0.1); color: var(--primary-color);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="summary-stat-content">
                                    <div class="summary-stat-value"><?php echo $total; ?></div>
                                    <div class="summary-stat-label">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="evidence-notice mt-4">
                        <i class="fas fa-info-circle"></i>
                        <span>Evidence files can only be uploaded to specific sub-parameters. Please click on "View" or "Upload Evidence" for a sub-parameter to manage its evidence files.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script>
        // Helper function for formatting file sizes
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + " B";
            else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
            else if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + " MB";
            else return (bytes / 1073741824).toFixed(1) + " GB";
        }
        
        // Initialize Particles.js
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined' && document.getElementById('particles-js')) {
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
            const userDropdownToggle = document.getElementById('userDropdownToggle');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userDropdownToggle && userDropdownMenu) {
                userDropdownToggle.addEventListener('click', function() {
                    userDropdownMenu.classList.toggle('active');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userDropdownToggle.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                        userDropdownMenu.classList.remove('active');
                    }
                });
            }
        });
        
        // Sidebar toggle for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            
            // Menu toggle button (mobile)
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.add('active');
                });
            }
            
            // Close sidebar button
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                });
            }
            
            // Close sidebar when clicking outside (for mobile)
            document.addEventListener('click', function(event) {
                if (sidebar && sidebar.classList.contains('active') && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
        });
        
        // Submenu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const parent = this.parentElement;
                    const submenu = parent.querySelector('.submenu');
                    const indicator = this.querySelector('.submenu-indicator');
                    
                    // Close other open submenus
                    if (!parent.classList.contains('active')) {
                        const activeSubmenu = document.querySelector('.has-submenu.active');
                        if (activeSubmenu && activeSubmenu !== parent) {
                            activeSubmenu.classList.remove('active');
                            const activeSubmenuContent = activeSubmenu.querySelector('.submenu');
                            if (activeSubmenuContent) {
                                activeSubmenuContent.style.display = 'none';
                            }
                            const activeIndicator = activeSubmenu.querySelector('.submenu-indicator');
                            if (activeIndicator) {
                                activeIndicator.style.transform = 'rotate(0deg)';
                            }
                        }
                    }
                    
                    // Toggle current submenu
                    parent.classList.toggle('active');
                    if (parent.classList.contains('active')) {
                        submenu.style.display = 'block';
                        if (indicator) {
                            indicator.style.transform = 'rotate(90deg)';
                        }
                    } else {
                        submenu.style.display = 'none';
                        if (indicator) {
                            indicator.style.transform = 'rotate(0deg)';
                        }
                    }
                });
            });
        });
        
        // Add text-center class to appropriate table cells
        document.addEventListener('DOMContentLoaded', function() {
            const tables = document.querySelectorAll('table');
            tables.forEach(function(table) {
                const headerCells = table.querySelectorAll('th');
                headerCells.forEach(function(cell, index) {
                    if (cell.textContent.trim() === 'View' || 
                        cell.textContent.trim() === 'Add' || 
                        cell.textContent.trim() === 'Edit' || 
                        cell.textContent.trim() === 'Delete' || 
                        cell.textContent.trim() === 'Download' || 
                        cell.textContent.trim() === 'Approve') {
                        // Add text-center class to header
                        cell.classList.add('text-center');
                        
                        // Add text-center class to all cells in this column
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(function(row) {
                            const dataCell = row.cells[index];
                            if (dataCell) {
                                dataCell.classList.add('text-center');
                            }
                        });
                    }
                });
            });
        });
        
        // Function to add classes to text-success and text-danger elements
        document.addEventListener('DOMContentLoaded', function() {
            const textSuccessElements = document.querySelectorAll('.text-success');
            const textDangerElements = document.querySelectorAll('.text-danger');
            
            textSuccessElements.forEach(function(element) {
                element.style.color = 'var(--success)';
            });
            
            textDangerElements.forEach(function(element) {
                element.style.color = 'var(--danger)';
            });
        });
    </script>
</body>
</html> 