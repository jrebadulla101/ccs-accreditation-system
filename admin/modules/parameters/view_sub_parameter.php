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

// Get sub-parameter ID from URL
$subParameterId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subParameterId <= 0) {
    setFlashMessage("danger", "Invalid sub-parameter ID.");
    header("Location: list.php");
    exit();
}

// Get sub-parameter details with parameter, area and program info
$sql = "SELECT sp.*, p.name as parameter_name, p.id as parameter_id,
        a.name as area_name, a.id as area_id,
        pr.name as program_name, pr.id as program_id 
        FROM sub_parameters sp 
        JOIN parameters p ON sp.parameter_id = p.id 
        JOIN area_levels a ON p.area_level_id = a.id 
        JOIN programs pr ON a.program_id = pr.id 
        WHERE sp.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subParameterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Sub-parameter not found.");
    header("Location: list.php");
    exit();
}

$subParameter = $result->fetch_assoc();

// Check if user has permission to view this parameter/sub-parameter
$canView = false;

// Super admin or admin with view_parameters permission can view all parameters
if (hasPermission('view_parameters')) {
    $canView = true;
} else {
    // Check if this user has specific permission for this parameter
    $permCheckQuery = "SELECT can_view FROM parameter_user_permissions WHERE user_id = ? AND parameter_id = ?";
    $permCheckStmt = $conn->prepare($permCheckQuery);
    $permCheckStmt->bind_param("ii", $_SESSION['admin_id'], $subParameter['parameter_id']);
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
        $areaPermCheckStmt->bind_param("ii", $_SESSION['admin_id'], $subParameter['area_id']);
        $areaPermCheckStmt->execute();
        $areaPermResult = $areaPermCheckStmt->get_result();
        
        if ($areaPermResult->num_rows > 0) {
            $areaPermRow = $areaPermResult->fetch_assoc();
            $canView = ($areaPermRow['can_view'] == 1);
        }
    }
}

if (!$canView) {
    setFlashMessage("danger", "You don't have permission to view this sub-parameter.");
    header("Location: list.php");
    exit();
}

// Get evidence files for this sub-parameter
$evidenceQuery = "SELECT e.*, au.full_name as uploaded_by_name, au.id as uploaded_by_id
                 FROM parameter_evidence e
                 LEFT JOIN admin_users au ON e.uploaded_by = au.id
                 WHERE e.sub_parameter_id = ?
                 ORDER BY e.created_at DESC";
$evidenceStmt = $conn->prepare($evidenceQuery);
$evidenceStmt->bind_param("i", $subParameterId);
$evidenceStmt->execute();
$evidenceResult = $evidenceStmt->get_result();

// Check for user permissions
$hasEditPermission = hasPermission('edit_parameter');
$hasDeletePermission = hasPermission('delete_parameter');
$hasAddEvidencePermission = hasPermission('add_evidence');
$hasApproveEvidencePermission = hasPermission('approve_evidence');
$hasDownloadEvidencePermission = hasPermission('download_evidence');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subParameter['name']); ?> - Sub-Parameter Details - Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Particles.js -->
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

    .submenu-arrow {
        margin-left: auto;
        transition: transform 0.3s ease;
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
        display: flex;
        align-items: center;
    }

    .card-title i {
        margin-right: 10px;
        color: var(--primary-color);
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
    
    /* Info Groups */
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
    
    /* Status Badge */
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
    
    /* Sub-Parameter Detail Styles */
    .sub-parameter-info-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-card {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        padding: 20px;
        transition: all 0.3s ease;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }

    .info-card-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .info-card-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        margin-right: 15px;
    }

    .info-card-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
    }

    .info-card-content {
        font-size: 15px;
        color: var(--text-color);
    }
    
    /* Description Section */
    .description-section {
        background-color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.05);
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--primary-color);
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 10px;
    }

    .description-content {
        background-color: var(--bg-light);
        padding: 15px;
        border-radius: 6px;
        font-size: 15px;
        line-height: 1.6;
    }
    
    /* Evidence Files Section */
    .evidence-item {
        display: flex;
        background-color: white;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        margin-bottom: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .evidence-item:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transform: translateY(-3px);
    }

    .evidence-icon {
        width: 80px;
        min-width: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--bg-light);
        color: var(--primary-color);
        font-size: 28px;
    }

    .evidence-details {
        flex: 1;
        padding: 15px;
        border-right: 1px solid var(--border-color);
    }

    .evidence-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--primary-color);
    }

    .evidence-description {
        font-size: 14px;
        color: var(--text-color);
        margin-bottom: 10px;
    }

    .evidence-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .evidence-meta-item {
        display: flex;
        align-items: center;
    }

    .evidence-meta-item i {
        margin-right: 5px;
        color: var(--primary-color);
    }

    .evidence-actions {
        width: 100px;
        min-width: 100px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background-color: rgba(245, 247, 250, 0.5);
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .action-btn-view {
        background-color: var(--primary-color);
    }

    .action-btn-download {
        background-color: var(--success);
    }

    .action-btn-edit {
        background-color: var(--warning);
    }

    .action-btn-delete {
        background-color: var(--danger);
    }
    
    /* Empty state */
    .no-evidence {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
        background-color: var(--bg-light);
        border-radius: 10px;
    }

    .no-evidence i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .no-evidence p {
        color: var(--text-muted);
        margin-bottom: 20px;
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

        .sidebar-toggle {
            display: block;
        }
        
        .sub-parameter-info-container {
            grid-template-columns: 1fr;
        }
        
        .evidence-item {
            flex-direction: column;
        }
        
        .evidence-icon {
            width: 100%;
            height: 60px;
        }
        
        .evidence-actions {
            width: 100%;
            flex-direction: row;
            justify-content: space-around;
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

        .search-box {
            display: none;
        }
    }

    /* New styles for the evidence section */
    .view-toggle {
        margin-top: 15px;
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .view-btn {
        background-color: #f8f9fa;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 8px 16px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .view-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    /* List View Styles */
    .evidence-files-container {
        margin-top: 15px;
    }
    
    .evidence-item {
        display: flex;
        background-color: white;
        border-radius: 8px;
        margin-bottom: 15px;
        padding: 15px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .evidence-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .evidence-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--primary-color);
        margin-right: 15px;
    }
    
    .evidence-details {
        flex: 1;
    }
    
    .evidence-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
    }
    
    .evidence-description {
        font-size: 14px;
        color: var(--text-muted);
        margin-bottom: 10px;
    }
    
    .evidence-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .evidence-meta-item {
        display: flex;
        align-items: center;
    }
    
    .evidence-meta-item i {
        margin-right: 5px;
        width: 14px;
        text-align: center;
    }
    
    .evidence-actions {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: #f8f9fa;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .action-btn:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .action-btn-view:hover {
        background-color: var(--primary-color);
    }
    
    .action-btn-download:hover {
        background-color: var(--success);
    }
    
    .action-btn-edit:hover {
        background-color: var(--warning);
    }
    
    .action-btn-delete:hover {
        background-color: var(--danger);
    }
    
    .action-btn-preview:hover {
        background-color: #6c757d;
    }
    
    /* Grid View Styles */
    .evidence-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    .evidence-card {
        background-color: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
    }
    
    .evidence-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .evidence-card-content {
        flex: 1;
    }
    
    .evidence-card-thumbnail {
        height: 160px;
        position: relative;
        background-color: #f8f9fa;
        overflow: hidden;
    }
    
    .evidence-card-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .icon-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: var(--primary-color);
    }
    
    .status-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    .status-indicator.approved {
        background-color: var(--success);
    }
    
    .status-indicator.pending {
        background-color: var(--warning);
    }
    
    .status-indicator.rejected {
        background-color: var(--danger);
    }
    
    .evidence-card-details {
        padding: 15px;
    }
    
    .evidence-card-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .evidence-card-meta {
        display: flex;
        flex-direction: column;
        gap: 5px;
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .evidence-card-actions {
        display: flex;
        justify-content: space-around;
        padding: 10px 15px;
        border-top: 1px solid #efefef;
    }
    
    .action-btn-sm {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
    
    /* Empty State Styles */
    .no-evidence {
        text-align: center;
        padding: 40px 20px;
        background-color: white;
        border-radius: 8px;
        margin-top: 15px;
    }
    
    .no-evidence i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    .no-evidence p {
        font-size: 16px;
        color: var(--text-muted);
        margin-bottom: 20px;
    }
    
    /* Image Preview Modal */
    .image-preview-modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.8);
    }
    
    .modal-content {
        margin: 5% auto;
        display: block;
        max-width: 90%;
        max-height: 90%;
    }
    
    .modal-content img {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 85vh;
        margin: 0 auto;
    }
    
    .close-modal {
        position: absolute;
        top: 15px;
        right: 25px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        transition: 0.3s;
        cursor: pointer;
    }
    
    .close-modal:hover {
        color: #bbb;
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
                <h2>Accreditation System</h2>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
                        <i class="fas fa-chevron-right submenu-arrow"></i>
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
        <main class="main-content">
            <header class="header">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="search-box">
                    <input type="text" placeholder="Search...">
                    <i class="fas fa-search"></i>
                </div>
                <div class="user-dropdown">
                    <button class="user-info">
                        <span class="user-avatar">
                            <?php 
                            $initials = '';
                            $nameParts = explode(' ', $_SESSION['admin_name']);
                            foreach($nameParts as $part) {
                                $initials .= substr($part, 0, 1);
                            }
                            echo substr($initials, 0, 2);
                            ?>
                        </span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator'); ?></span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="../../profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="../../change-password.php"><i class="fas fa-key"></i> Change Password</a>
                        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </header>

            <div class="content">
                <div class="page-header">
                    <h1><?php echo htmlspecialchars($subParameter['name']); ?> - Sub-Parameter Details</h1>
                    <div class="page-actions">
                        <?php if ($hasEditPermission): ?>
                        <a href="edit.php?id=<?php echo $subParameter['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php endif; ?>
                        <?php if ($hasDeletePermission): ?>
                        <a href="delete.php?id=<?php echo $subParameter['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this sub-parameter?');">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sub-parameter-info-container">
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-info-circle info-card-icon"></i>
                            <h3 class="info-card-title">Parameter Details</h3>
                        </div>
                        <div class="info-card-content">
                            <p><strong>Parameter:</strong> <?php echo htmlspecialchars($subParameter['parameter_name']); ?></p>
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($subParameter['area_name']); ?></p>
                            <p><strong>Program:</strong> <?php echo htmlspecialchars($subParameter['program_name']); ?></p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-description info-card-icon"></i>
                            <h3 class="info-card-title">Description</h3>
                        </div>
                        <div class="info-card-content">
                            <?php echo htmlspecialchars($subParameter['description']); ?>
                        </div>
                    </div>
                </div>

                <div class="description-section">
                    <h2 class="section-title">
                        <i class="fas fa-file-alt"></i> Evidence Files
                    </h2>
                    <div class="card-body">
                        <?php if ($hasAddEvidencePermission): ?>
                        <!-- Quick Upload Form -->
                        <div class="quick-upload-section">
                            <button type="button" id="showUploadFormBtn" class="btn btn-primary">
                                <i class="fas fa-file-upload"></i> Quick Upload Evidence
                            </button>
                            
                            <div id="quickUploadForm" class="quick-upload-form" style="display: none;">
                                <form action="../evidence/process_upload.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="sub_parameter_id" value="<?php echo $subParameterId; ?>">
                                    <input type="hidden" name="parameter_id" value="<?php echo $subParameter['parameter_id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="evidence_title">Title <span class="text-danger">*</span></label>
                                            <input type="text" id="evidence_title" name="title" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="evidence_file">File Upload <span class="text-danger">*</span></label>
                                            <div class="custom-file-upload">
                                                <input type="file" id="evidence_file" name="evidence_file" class="form-control-file">
                                                <label for="evidence_file">
                                                    <i class="fas fa-cloud-upload-alt"></i> Choose File
                                                </label>
                                                <span id="selected-file">No file selected</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="evidence_description">Description</label>
                                        <textarea id="evidence_description" name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="button" id="cancelUploadBtn" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Upload Evidence
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Evidence Statistics -->
                        <div class="evidence-stats">
                            <?php
                            // Get evidence statistics
                            $statsQuery = "SELECT 
                                            status, 
                                            COUNT(*) as count 
                                          FROM parameter_evidence 
                                          WHERE sub_parameter_id = ? 
                                          GROUP BY status";
                            $statsStmt = $conn->prepare($statsQuery);
                            $statsStmt->bind_param("i", $subParameterId);
                            $statsStmt->execute();
                            $statsResult = $statsStmt->get_result();
                            
                            $totalEvidence = 0;
                            $approvedCount = 0;
                            $pendingCount = 0;
                            $rejectedCount = 0;
                            
                            while ($stat = $statsResult->fetch_assoc()) {
                                if ($stat['status'] == 'approved') {
                                    $approvedCount = $stat['count'];
                                } else if ($stat['status'] == 'rejected') {
                                    $rejectedCount = $stat['count'];
                                } else {
                                    $pendingCount = $stat['count'];
                                }
                                $totalEvidence += $stat['count'];
                            }
                            ?>
                            
                            <div class="stat-tiles">
                                <div class="stat-tile">
                                    <div class="stat-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $totalEvidence; ?></div>
                                        <div class="stat-label">Total Files</div>
                                    </div>
                                </div>
                                
                                <div class="stat-tile">
                                    <div class="stat-icon approve-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $approvedCount; ?></div>
                                        <div class="stat-label">Approved</div>
                                    </div>
                                </div>
                                
                                <div class="stat-tile">
                                    <div class="stat-icon pending-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $pendingCount; ?></div>
                                        <div class="stat-label">Pending</div>
                                    </div>
                                </div>
                                
                                <div class="stat-tile">
                                    <div class="stat-icon reject-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $rejectedCount; ?></div>
                                        <div class="stat-label">Rejected</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Evidence Filter Controls -->
                        <div class="evidence-filters">
                            <div class="filter-header">
                                <h3><i class="fas fa-filter"></i> Filter Evidence</h3>
                                <div class="filter-actions">
                                    <button type="button" id="toggleFiltersBtn" class="btn btn-sm btn-light">
                                        <i class="fas fa-sliders-h"></i> Toggle Filters
                                    </button>
                                </div>
                            </div>
                            
                            <div id="filterOptions" class="filter-options" style="display: none;">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label>Status:</label>
                                        <div class="filter-checks">
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="status" data-value="all" checked>
                                                <span>All</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="status" data-value="approved">
                                                <span>Approved</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="status" data-value="pending">
                                                <span>Pending</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="status" data-value="rejected">
                                                <span>Rejected</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label>File Type:</label>
                                        <div class="filter-checks">
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="type" data-value="all" checked>
                                                <span>All</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="type" data-value="document">
                                                <span>Documents</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="type" data-value="image">
                                                <span>Images</span>
                                            </label>
                                            <label class="filter-check">
                                                <input type="checkbox" class="evidence-filter" data-filter="type" data-value="drive">
                                                <span>Google Drive</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label>Search:</label>
                                        <input type="text" id="evidenceSearch" class="form-control" placeholder="Search by title...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Evidence Gallery/List View Toggle -->
                        <div class="view-toggle">
                            <button type="button" class="btn btn-sm btn-light view-btn active" data-view="list">
                                <i class="fas fa-list"></i> List View
                            </button>
                            <button type="button" class="btn btn-sm btn-light view-btn" data-view="grid">
                                <i class="fas fa-th-large"></i> Grid View
                            </button>
                        </div>

                        <?php if ($evidenceResult->num_rows > 0): 
                            // Reset the pointer to the beginning
                            $evidenceResult->data_seek(0);
                        ?>
                            <!-- List View (Default) -->
                            <div id="listView" class="evidence-view">
                                <div class="evidence-files-container">
                                    <?php while ($evidence = $evidenceResult->fetch_assoc()): 
                                        // Get file icon based on file type
                                        $fileIcon = 'fas fa-file';
                                        $fileTypeClass = 'document';
                                        
                                        if (isset($evidence['file_type'])) {
                                            if (strpos($evidence['file_type'], 'pdf') !== false) {
                                                $fileIcon = 'fas fa-file-pdf';
                                            } elseif (strpos($evidence['file_type'], 'word') !== false || strpos($evidence['file_type'], 'doc') !== false) {
                                                $fileIcon = 'fas fa-file-word';
                                            } elseif (strpos($evidence['file_type'], 'excel') !== false || strpos($evidence['file_type'], 'xls') !== false) {
                                                $fileIcon = 'fas fa-file-excel';
                                            } elseif (strpos($evidence['file_type'], 'image') !== false) {
                                                $fileIcon = 'fas fa-file-image';
                                                $fileTypeClass = 'image';
                                            } elseif (strpos($evidence['file_type'], 'powerpoint') !== false || strpos($evidence['file_type'], 'ppt') !== false) {
                                                $fileIcon = 'fas fa-file-powerpoint';
                                            } elseif (strpos($evidence['file_type'], 'zip') !== false || strpos($evidence['file_type'], 'rar') !== false) {
                                                $fileIcon = 'fas fa-file-archive';
                                            }
                                        } elseif (!empty($evidence['drive_link'])) {
                                            $fileIcon = 'fab fa-google-drive';
                                            $fileTypeClass = 'drive';
                                        }
                                        
                                        $statusClass = 'status-pending';
                                        $statusText = 'Pending';
                                        $statusValue = 'pending';
                                        
                                        if (isset($evidence['status'])) {
                                            if ($evidence['status'] == 'approved') {
                                                $statusClass = 'status-active';
                                                $statusText = 'Approved';
                                                $statusValue = 'approved';
                                            } elseif ($evidence['status'] == 'rejected') {
                                                $statusClass = 'status-inactive';
                                                $statusText = 'Rejected';
                                                $statusValue = 'rejected';
                                            }
                                        }
                                    ?>
                                    <div class="evidence-item" 
                                         data-status="<?php echo $statusValue; ?>" 
                                         data-type="<?php echo $fileTypeClass; ?>" 
                                         data-title="<?php echo htmlspecialchars($evidence['title']); ?>">
                                        <div class="evidence-icon">
                                            <i class="<?php echo $fileIcon; ?>"></i>
                                        </div>
                                        <div class="evidence-details">
                                            <div class="evidence-title">
                                                <?php echo htmlspecialchars($evidence['title']); ?>
                                                <span class="status-badge <?php echo $statusClass; ?>" style="margin-left: 10px; font-size: 11px;">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($evidence['description'])): ?>
                                            <div class="evidence-description">
                                                <?php 
                                                $desc = htmlspecialchars($evidence['description']);
                                                echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc; 
                                                ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="evidence-meta">
                                                <div class="evidence-meta-item">
                                                    <i class="fas fa-user"></i>
                                                    <span><?php echo htmlspecialchars($evidence['uploaded_by_name'] ?? 'Unknown'); ?></span>
                                                </div>
                                                <div class="evidence-meta-item">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span><?php echo isset($evidence['created_at']) ? date('M d, Y', strtotime($evidence['created_at'])) : 'N/A'; ?></span>
                                                </div>
                                                <?php if (isset($evidence['file_size'])): ?>
                                                <div class="evidence-meta-item">
                                                    <i class="fas fa-hdd"></i>
                                                    <span><?php echo formatFileSize($evidence['file_size']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($evidence['drive_link'])): ?>
                                                <div class="evidence-meta-item">
                                                    <i class="fab fa-google-drive"></i>
                                                    <span>Google Drive Link</span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="evidence-actions">
                                            <a href="../evidence/view.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($hasDownloadEvidencePermission && !empty($evidence['file_path'])): ?>
                                            <a href="../evidence/download.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-download" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('edit_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by_id']): ?>
                                            <a href="../evidence/edit.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('delete_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by_id']): ?>
                                            <a href="../evidence/delete.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this evidence file?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <!-- Quick Preview Button -->
                                            <?php if (!empty($evidence['file_path']) && $fileTypeClass == 'image'): ?>
                                            <button type="button" class="action-btn action-btn-preview" title="Quick Preview" 
                                                    data-toggle="preview" data-file="../../uploads/<?php echo $evidence['file_path']; ?>">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            
                            <!-- Grid View -->
                            <div id="gridView" class="evidence-view" style="display: none;">
                                <div class="evidence-grid">
                                    <?php 
                                    // Reset the pointer to the beginning
                                    $evidenceResult->data_seek(0);
                                    
                                    while ($evidence = $evidenceResult->fetch_assoc()): 
                                        // Get file icon based on file type
                                        $fileIcon = 'fas fa-file';
                                        $fileTypeClass = 'document';
                                        $thumbnailUrl = '';
                                        
                                        if (isset($evidence['file_type'])) {
                                            if (strpos($evidence['file_type'], 'pdf') !== false) {
                                                $fileIcon = 'fas fa-file-pdf';
                                            } elseif (strpos($evidence['file_type'], 'word') !== false || strpos($evidence['file_type'], 'doc') !== false) {
                                                $fileIcon = 'fas fa-file-word';
                                            } elseif (strpos($evidence['file_type'], 'excel') !== false || strpos($evidence['file_type'], 'xls') !== false) {
                                                $fileIcon = 'fas fa-file-excel';
                                            } elseif (strpos($evidence['file_type'], 'image') !== false) {
                                                $fileIcon = 'fas fa-file-image';
                                                $fileTypeClass = 'image';
                                                // If it's an image, use the actual image as thumbnail
                                                if (!empty($evidence['file_path'])) {
                                                    $thumbnailUrl = '../../uploads/' . $evidence['file_path'];
                                                }
                                            } elseif (strpos($evidence['file_type'], 'powerpoint') !== false || strpos($evidence['file_type'], 'ppt') !== false) {
                                                $fileIcon = 'fas fa-file-powerpoint';
                                            } elseif (strpos($evidence['file_type'], 'zip') !== false || strpos($evidence['file_type'], 'rar') !== false) {
                                                $fileIcon = 'fas fa-file-archive';
                                            }
                                        } elseif (!empty($evidence['drive_link'])) {
                                            $fileIcon = 'fab fa-google-drive';
                                            $fileTypeClass = 'drive';
                                        }
                                        
                                        $statusClass = 'status-pending';
                                        $statusText = 'Pending';
                                        $statusValue = 'pending';
                                        
                                        if (isset($evidence['status'])) {
                                            if ($evidence['status'] == 'approved') {
                                                $statusClass = 'status-active';
                                                $statusText = 'Approved';
                                                $statusValue = 'approved';
                                            } elseif ($evidence['status'] == 'rejected') {
                                                $statusClass = 'status-inactive';
                                                $statusText = 'Rejected';
                                                $statusValue = 'rejected';
                                            }
                                        }
                                    ?>
                                    <div class="evidence-card" 
                                         data-status="<?php echo $statusValue; ?>" 
                                         data-type="<?php echo $fileTypeClass; ?>" 
                                         data-title="<?php echo htmlspecialchars($evidence['title']); ?>">
                                        <div class="evidence-card-content">
                                            <div class="evidence-card-thumbnail">
                                                <?php if (!empty($thumbnailUrl)): ?>
                                                    <img src="<?php echo $thumbnailUrl; ?>" alt="<?php echo htmlspecialchars($evidence['title']); ?>">
                                                <?php else: ?>
                                                    <div class="icon-placeholder">
                                                        <i class="<?php echo $fileIcon; ?>"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="status-indicator <?php echo $statusValue; ?>"></div>
                                            </div>
                                            <div class="evidence-card-details">
                                                <h4 class="evidence-card-title" title="<?php echo htmlspecialchars($evidence['title']); ?>">
                                                    <?php echo htmlspecialchars(strlen($evidence['title']) > 25 ? substr($evidence['title'], 0, 25) . '...' : $evidence['title']); ?>
                                                </h4>
                                                <div class="evidence-card-meta">
                                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($evidence['uploaded_by_name'] ?? 'Unknown'); ?></span>
                                                    <span><i class="fas fa-calendar-alt"></i> <?php echo isset($evidence['created_at']) ? date('M d, Y', strtotime($evidence['created_at'])) : 'N/A'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="evidence-card-actions">
                                            <a href="../evidence/view.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($hasDownloadEvidencePermission && !empty($evidence['file_path'])): ?>
                                            <a href="../evidence/download.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-sm" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('edit_evidence') || $_SESSION['admin_id'] == $evidence['uploaded_by_id']): ?>
                                            <a href="../evidence/edit.php?id=<?php echo $evidence['id']; ?>" class="action-btn action-btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($fileTypeClass == 'image'): ?>
                                            <button type="button" class="action-btn action-btn-sm" title="Quick Preview" 
                                                    data-toggle="preview" data-file="<?php echo $thumbnailUrl; ?>">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-evidence">
                                <i class="fas fa-file-upload"></i>
                                <p>No evidence files have been uploaded for this sub-parameter yet.</p>
                                <?php if ($hasAddEvidencePermission): ?>
                                <a href="../evidence/add.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-primary">
                                    <i class="fas fa-file-upload"></i> Upload Evidence
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Image Preview Modal -->
                        <div id="previewModal" class="image-preview-modal">
                            <div class="modal-content">
                                <span class="close-modal">&times;</span>
                                <img id="previewImage" src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.main-content').classList.toggle('active');
    });

    document.getElementById('menuToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.main-content').classList.toggle('active');
    });

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

    // Toggle user dropdown
    document.addEventListener('DOMContentLoaded', function() {
        // Quick Upload Form Toggle
        const showUploadFormBtn = document.getElementById('showUploadFormBtn');
        const quickUploadForm = document.getElementById('quickUploadForm');
        const cancelUploadBtn = document.getElementById('cancelUploadBtn');
        
        if (showUploadFormBtn) {
            showUploadFormBtn.addEventListener('click', function() {
                quickUploadForm.style.display = 'block';
                showUploadFormBtn.style.display = 'none';
            });
        }
        
        if (cancelUploadBtn) {
            cancelUploadBtn.addEventListener('click', function() {
                quickUploadForm.style.display = 'none';
                showUploadFormBtn.style.display = 'block';
            });
        }
        
        // File input display selected filename
        const evidenceFile = document.getElementById('evidence_file');
        const selectedFile = document.getElementById('selected-file');
        
        if (evidenceFile && selectedFile) {
            evidenceFile.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    selectedFile.textContent = this.files[0].name;
                } else {
                    selectedFile.textContent = 'No file selected';
                }
            });
        }
        
        // Toggle Filters
        const toggleFiltersBtn = document.getElementById('toggleFiltersBtn');
        const filterOptions = document.getElementById('filterOptions');
        
        if (toggleFiltersBtn && filterOptions) {
            toggleFiltersBtn.addEventListener('click', function() {
                if (filterOptions.style.display === 'none') {
                    filterOptions.style.display = 'block';
                    toggleFiltersBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Filters';
                } else {
                    filterOptions.style.display = 'none';
                    toggleFiltersBtn.innerHTML = '<i class="fas fa-sliders-h"></i> Toggle Filters';
                }
            });
        }
        
        // View Toggle (List/Grid)
        const viewButtons = document.querySelectorAll('.view-btn');
        const listView = document.getElementById('listView');
        const gridView = document.getElementById('gridView');
        
        if (viewButtons.length > 0 && listView && gridView) {
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const viewType = this.getAttribute('data-view');
                    
                    // Remove active class from all buttons
                    viewButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Show/hide appropriate view
                    if (viewType === 'list') {
                        listView.style.display = 'block';
                        gridView.style.display = 'none';
                    } else {
                        listView.style.display = 'none';
                        gridView.style.display = 'block';
                    }
                });
            });
        }
        
        // Evidence Filtering
        const evidenceItems = document.querySelectorAll('.evidence-item, .evidence-card');
        const searchInput = document.getElementById('evidenceSearch');
        const filterCheckboxes = document.querySelectorAll('.evidence-filter');
        
        // Function to filter evidence items
        function filterEvidenceItems() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilters = [];
            const typeFilters = [];
            
            // Get selected filters
            filterCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const filterType = checkbox.getAttribute('data-filter');
                    const filterValue = checkbox.getAttribute('data-value');
                    
                    if (filterValue === 'all') return;
                    
                    if (filterType === 'status') {
                        statusFilters.push(filterValue);
                    } else if (filterType === 'type') {
                        typeFilters.push(filterValue);
                    }
                }
            });
            
            // Check if "All" is selected for status
            const allStatusSelected = Array.from(filterCheckboxes).find(checkbox => 
                checkbox.getAttribute('data-filter') === 'status' && 
                checkbox.getAttribute('data-value') === 'all'
            ).checked;
            
            // Check if "All" is selected for type
            const allTypeSelected = Array.from(filterCheckboxes).find(checkbox => 
                checkbox.getAttribute('data-filter') === 'type' && 
                checkbox.getAttribute('data-value') === 'all'
            ).checked;
            
            // Apply filters
            evidenceItems.forEach(item => {
                const itemTitle = item.getAttribute('data-title').toLowerCase();
                const itemStatus = item.getAttribute('data-status');
                const itemType = item.getAttribute('data-type');
                
                const matchesSearch = itemTitle.includes(searchTerm);
                const matchesStatus = allStatusSelected || statusFilters.includes(itemStatus);
                const matchesType = allTypeSelected || typeFilters.includes(itemType);
                
                if (matchesSearch && matchesStatus && matchesType) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Add event listeners for filters
        if (searchInput) {
            searchInput.addEventListener('input', filterEvidenceItems);
        }
        
        if (filterCheckboxes.length > 0) {
            filterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // If "All" is checked, uncheck other options in the same filter group
                    if (this.getAttribute('data-value') === 'all' && this.checked) {
                        const filterType = this.getAttribute('data-filter');
                        filterCheckboxes.forEach(cb => {
                            if (cb !== this && cb.getAttribute('data-filter') === filterType) {
                                cb.checked = false;
                            }
                        });
                    } 
                    // If a specific option is checked, uncheck "All"
                    else if (this.getAttribute('data-value') !== 'all' && this.checked) {
                        const filterType = this.getAttribute('data-filter');
                        const allCheckbox = Array.from(filterCheckboxes).find(cb => 
                            cb.getAttribute('data-filter') === filterType && 
                            cb.getAttribute('data-value') === 'all'
                        );
                        
                        if (allCheckbox) {
                            allCheckbox.checked = false;
                        }
                    }
                    
                    // If no specific options are checked, check "All"
                    const filterType = this.getAttribute('data-filter');
                    const anySpecificChecked = Array.from(filterCheckboxes).some(cb => 
                        cb.getAttribute('data-filter') === filterType && 
                        cb.getAttribute('data-value') !== 'all' && 
                        cb.checked
                    );
                    
                    if (!anySpecificChecked) {
                        const allCheckbox = Array.from(filterCheckboxes).find(cb => 
                            cb.getAttribute('data-filter') === filterType && 
                            cb.getAttribute('data-value') === 'all'
                        );
                        
                        if (allCheckbox) {
                            allCheckbox.checked = true;
                        }
                    }
                    
                    filterEvidenceItems();
                });
            });
        }
        
        // Image Preview Modal
        const previewModal = document.getElementById('previewModal');
        const previewImage = document.getElementById('previewImage');
        const closeModal = document.querySelector('.close-modal');
        const previewButtons = document.querySelectorAll('[data-toggle="preview"]');
        
        if (previewButtons.length > 0) {
            previewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const fileUrl = this.getAttribute('data-file');
                    if (fileUrl) {
                        previewImage.src = fileUrl;
                        previewModal.style.display = 'block';
                    }
                });
            });
        }
        
        if (closeModal) {
            closeModal.addEventListener('click', function() {
                previewModal.style.display = 'none';
            });
        }
        
        if (previewModal) {
            window.addEventListener('click', function(event) {
                if (event.target === previewModal) {
                    previewModal.style.display = 'none';
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
        const submenuToggles = document.querySelectorAll('.submenu-toggle');
        
        if (submenuToggles.length > 0) {
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const submenu = this.nextElementSibling;
                    
                    if (submenu.style.maxHeight) {
                        submenu.style.maxHeight = null;
                        this.querySelector('.submenu-indicator').classList.remove('active');
                    } else {
                        submenu.style.maxHeight = submenu.scrollHeight + "px";
                        this.querySelector('.submenu-indicator').classList.add('active');
                    }
                });
            });
        }
        
        // User Dropdown Toggle
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
    });
    </script>
</body>
</html> 