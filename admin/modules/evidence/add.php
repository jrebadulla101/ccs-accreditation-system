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

// Get parameter_id or sub_parameter_id from URL
$parameterId = isset($_GET['parameter_id']) ? intval($_GET['parameter_id']) : 0;
$subParameterId = isset($_GET['sub_parameter_id']) ? intval($_GET['sub_parameter_id']) : 0;

// Instead of immediately showing an error, check if the user came from navigation
// We should show a selection screen if neither parameter is provided
$isDirectNavigation = ($parameterId == 0 && $subParameterId == 0);

// Only show error if it's not direct navigation and parameters aren't provided
if (!$isDirectNavigation && $parameterId == 0 && $subParameterId == 0) {
    setFlashMessage("danger", "Parameter ID or Sub-Parameter ID is required.");
    header("Location: list.php");
    exit();
}

// Initialize variables
$parameter = null;
$subParameter = null;
$isSubParameter = ($subParameterId > 0);

// If we have a sub-parameter, fetch its data including parent parameter
if ($isSubParameter) {
    $subParamQuery = "SELECT sp.*, p.name as parameter_name, p.id as parameter_id,
                      a.name as area_name, a.id as area_id, 
                      pr.name as program_name, pr.id as program_id
                      FROM sub_parameters sp
                      JOIN parameters p ON sp.parameter_id = p.id
                      JOIN area_levels a ON p.area_level_id = a.id
                      JOIN programs pr ON a.program_id = pr.id
                      WHERE sp.id = ?";
    $subParamStmt = $conn->prepare($subParamQuery);
    $subParamStmt->bind_param("i", $subParameterId);
    $subParamStmt->execute();
    $subParamResult = $subParamStmt->get_result();

    if ($subParamResult && $subParamResult->num_rows > 0) {
        $subParameter = $subParamResult->fetch_assoc();
        $parameterId = $subParameter['parameter_id']; // Get parent parameter ID
    } else {
        setFlashMessage("danger", "Sub-Parameter not found.");
        header("Location: ../parameters/list.php");
        exit();
    }
}

// Get parameter details
if ($parameterId > 0) {
    $paramQuery = "SELECT p.*, a.name as area_name, a.id as area_id, pr.name as program_name, pr.id as program_id 
                  FROM parameters p 
                  JOIN area_levels a ON p.area_level_id = a.id 
                  JOIN programs pr ON a.program_id = pr.id 
                  WHERE p.id = ?";
    $paramStmt = $conn->prepare($paramQuery);
    $paramStmt->bind_param("i", $parameterId);
    $paramStmt->execute();
    $paramResult = $paramStmt->get_result();

    if ($paramResult && $paramResult->num_rows > 0) {
        $parameter = $paramResult->fetch_assoc();
    } else if (!$isSubParameter) {
        setFlashMessage("danger", "Parameter not found.");
        header("Location: ../parameters/list.php");
        exit();
    }
}

// Helper function for safely displaying values
function safeParam($param, $key, $default = '') {
    if (isset($param) && is_array($param) && isset($param[$key])) {
        return htmlspecialchars($param[$key]);
    }
    return $default;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description']);
    $driveLink = cleanInput($_POST['drive_link']);
    $evidenceType = cleanInput($_POST['evidence_type']);
    $filePath = '';
    
    // Validate required fields
    if (empty($title)) {
        setFlashMessage("danger", "Title is required.");
    } else {
        // Handle file upload if type is 'file'
        if ($evidenceType == 'file') {
            if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] == 0) {
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'text/plain', 'application/zip', 'application/x-rar-compressed'];
                
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($fileInfo, $_FILES['evidence_file']['tmp_name']);
                finfo_close($fileInfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    setFlashMessage("danger", "Invalid file type. Allowed types: PDF, Images, Office documents, Text, ZIP, RAR.");
                } else {
                    $uploadDir = '../../uploads/evidence/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'evidence_' . time() . '_' . uniqid() . '_' . $_FILES['evidence_file']['name'];
                    $filePath = 'evidence/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $uploadDir . $filename)) {
                        // File uploaded successfully
                    } else {
                        setFlashMessage("danger", "Error uploading file. Please try again.");
                        $filePath = '';
                    }
                }
            } else {
                setFlashMessage("danger", "Please select a file to upload.");
                header("Location: add.php?" . ($isSubParameter ? "sub_parameter_id=".$subParameterId : "parameter_id=".$parameterId));
                exit();
            }
        } elseif ($evidenceType == 'drive' && empty($driveLink)) {
            setFlashMessage("danger", "Google Drive link is required.");
            header("Location: add.php?" . ($isSubParameter ? "sub_parameter_id=".$subParameterId : "parameter_id=".$parameterId));
            exit();
        }
        
        // Only proceed if no errors
        if (empty($_SESSION['flash_message']) || $_SESSION['flash_message']['type'] != 'danger') {
            // Insert new evidence
            $userId = $_SESSION['admin_id'];
            
            if ($isSubParameter) {
                // Insert evidence for sub-parameter
                if ($evidenceType == 'file') {
                    $insertQuery = "INSERT INTO parameter_evidence (parameter_id, sub_parameter_id, title, description, file_path, uploaded_by, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("iisssi", $parameterId, $subParameterId, $title, $description, $filePath, $userId);
                } else { // drive link
                    $insertQuery = "INSERT INTO parameter_evidence (parameter_id, sub_parameter_id, title, description, drive_link, uploaded_by, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("iisssi", $parameterId, $subParameterId, $title, $description, $driveLink, $userId);
                }
            } else {
                // Insert evidence for parameter
                if ($evidenceType == 'file') {
                    $insertQuery = "INSERT INTO parameter_evidence (parameter_id, title, description, file_path, uploaded_by, status) 
                                  VALUES (?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("isssi", $parameterId, $title, $description, $filePath, $userId);
                } else { // drive link
                    $insertQuery = "INSERT INTO parameter_evidence (parameter_id, title, description, drive_link, uploaded_by, status) 
                                  VALUES (?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("isssi", $parameterId, $title, $description, $driveLink, $userId);
                }
            }
            
            if ($stmt->execute()) {
                // Log activity
                $activityType = "evidence_upload";
                if ($isSubParameter) {
                    $activityDescription = "Uploaded evidence '$title' for sub-parameter '{$subParameter['name']}'";
                } else {
                    $activityDescription = "Uploaded evidence '$title' for parameter '{$parameter['name']}'";
                }
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->bind_param("issss", $userId, $activityType, $activityDescription, $ipAddress, $userAgent);
                $logStmt->execute();
                
                setFlashMessage("success", "Evidence added successfully.");
                if ($isSubParameter) {
                    header("Location: list.php?sub_parameter_id=" . $subParameterId);
                } else {
                    header("Location: list.php?parameter_id=" . $parameterId);
                }
                exit();
            } else {
                setFlashMessage("danger", "Error adding evidence: " . $conn->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Evidence - CCS Accreditation System</title>
    
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
    
    /* Card and Form Styles */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: -10px;
    }
    
    .col-lg-8, .col-lg-4 {
        padding: 10px;
        box-sizing: border-box;
    }
    
    .col-lg-8 {
        width: 66.66667%;
    }
    
    .col-lg-4 {
        width: 33.33333%;
    }
    
    @media (max-width: 992px) {
        .col-lg-8, .col-lg-4 {
            width: 100%;
        }
    }
    
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
    
    .card-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background-color: #fff;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
    }
    
    .form-text {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 5px;
    }
    
    .radio-group {
        display: flex;
        gap: 20px;
    }
    
    .radio-item {
        display: flex;
        align-items: center;
    }
    
    .radio-item input {
        margin-right: 5px;
    }
    
    /* File Upload Styles */
    .file-upload-container {
        border: 2px dashed var(--border-color);
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 20px;
        background-color: #f9fafb;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .file-upload-container.highlight {
        border-color: var(--accent-color);
        background-color: rgba(74, 144, 226, 0.05);
    }
    
    .file-upload-icon {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    .file-upload-text h3 {
        font-size: 18px;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .file-upload-btn {
        display: inline-block;
        padding: 8px 20px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        margin-top: 10px;
    }
    
    .file-input {
        display: none;
    }
    
    .file-preview {
        display: none;
        margin-top: 20px;
    }
    
    .file-preview-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
    }
    
    .file-preview-icon {
        font-size: 24px;
        margin-right: 15px;
        color: var(--primary-color);
    }
    
    .file-preview-info {
        flex: 1;
    }
    
    .file-preview-name {
        font-weight: 500;
    }
    
    .file-preview-size {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .file-preview-remove {
        cursor: pointer;
        color: var(--danger);
        padding: 5px;
    }
    
    /* Drive Link Styles */
    .drive-link-container {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .drive-link-header {
        background-color: #f9fafb;
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .drive-link-header h3 {
        font-size: 16px;
        font-weight: 500;
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .drive-icon {
        color: #1da462;
        margin-right: 10px;
    }
    
    .drive-link-body {
        padding: 15px;
    }
    
    .drive-link-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background-color: #fff;
        font-size: 14px;
    }
    
    /* Info Group Styles */
    .info-group {
        margin-bottom: 15px;
    }
    
    .info-group label {
        display: block;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    
    .info-group span,
    .info-group p {
        color: var(--text-color);
    }
    
    /* Guidelines Styles */
    .guideline-item {
        display: flex;
        margin-bottom: 15px;
        align-items: flex-start;
    }
    
    .guideline-item i {
        color: var(--accent-color);
        font-size: 24px;
        margin-right: 15px;
        min-width: 24px;
    }
    
    .guideline-text h4 {
        margin: 0 0 5px 0;
        font-size: 16px;
        color: var(--primary-color);
    }
    
    .guideline-text p {
        margin: 0;
        font-size: 13px;
        color: var(--text-muted);
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
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
    
    /* Alert Styles */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    
    .alert-info {
        color: #31708f;
        background-color: #d9edf7;
        border-color: #bce8f1;
    }
    
    .alert-success {
        color: #3c763d;
        background-color: #dff0d8;
        border-color: #d6e9c6;
    }
    
    .alert-danger {
        color: #a94442;
        background-color: #f2dede;
        border-color: #ebccd1;
    }
    
    /* Utility Classes */
    .mt-4 {
        margin-top: 1.5rem;
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
                    
                    <li class="menu-item active">
                        <a href="#" class="menu-link active">
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
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?>">
                        <?php echo $_SESSION['flash_message']['message']; ?>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <div class="page-header">
                    <h1>Upload Evidence</h1>
                    <nav class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                            
                            <?php if ($isSubParameter): ?>
                                <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $subParameter['program_id']; ?>"><?php echo safeParam($subParameter, 'program_name'); ?></a></li>
                                <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $subParameter['area_id']; ?>"><?php echo safeParam($subParameter, 'area_name'); ?></a></li>
                                <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $subParameter['parameter_id']; ?>"><?php echo safeParam($subParameter, 'parameter_name'); ?></a></li>
                                <li class="breadcrumb-item"><a href="../parameters/view_sub_parameter.php?id=<?php echo $subParameterId; ?>"><?php echo safeParam($subParameter, 'name'); ?></a></li>
                            <?php else: ?>
                                <?php if (isset($parameter) && isset($parameter['program_id'])): ?>
                                <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo safeParam($parameter, 'program_name'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if (isset($parameter) && isset($parameter['area_id'])): ?>
                                <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo safeParam($parameter, 'area_name'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if (isset($parameter) && isset($parameter['id'])): ?>
                                <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $parameter['id']; ?>"><?php echo safeParam($parameter, 'name'); ?></a></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <li class="breadcrumb-item active">Add Evidence</li>
                        </ol>
                    </nav>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <?php if ($isSubParameter): ?>
                                        Upload Evidence for Sub-Parameter: <?php echo safeParam($subParameter, 'name'); ?>
                                    <?php else: ?>
                                        Upload Evidence for Parameter: <?php echo safeParam($parameter, 'name'); ?>
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . ($isSubParameter ? "?sub_parameter_id=".$subParameterId : "?parameter_id=".$parameterId)); ?>" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="title" class="form-label">Evidence Title <span class="text-danger">*</span></label>
                                        <input type="text" id="title" name="title" class="form-control" required placeholder="Enter a descriptive title for this evidence">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Describe the evidence and its relevance to this parameter"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Evidence Type <span class="text-danger">*</span></label>
                                        <div class="radio-group">
                                            <div class="radio-item">
                                                <input type="radio" id="type_file" name="evidence_type" value="file" checked>
                                                <label for="type_file">Upload File</label>
                                            </div>
                                            <div class="radio-item">
                                                <input type="radio" id="type_drive" name="evidence_type" value="drive">
                                                <label for="type_drive">Google Drive Link</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="file_upload_section">
                                        <div class="file-upload-container">
                                            <div class="file-upload-icon">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                            </div>
                                            <div class="file-upload-text">
                                                <h3>Drag and drop file here</h3>
                                                <p>or</p>
                                            </div>
                                            <label for="evidence_file" class="file-upload-btn">Choose File</label>
                                            <input type="file" id="evidence_file" name="evidence_file" class="file-input">
                                            
                                            <div class="file-preview">
                                                <div class="file-preview-item">
                                                    <div class="file-preview-icon">
                                                        <i class="fas fa-file-alt"></i>
                                                    </div>
                                                    <div class="file-preview-info">
                                                        <div class="file-preview-name">filename.pdf</div>
                                                        <div class="file-preview-size">2.5 MB</div>
                                                    </div>
                                                    <div class="file-preview-remove">
                                                        <i class="fas fa-times"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Allowed file types: PDF, Images, Office documents, Text files, ZIP, RAR (Max size: 10MB)
                                        </div>
                                    </div>
                                    
                                    <div id="drive_link_section" style="display: none;">
                                        <div class="drive-link-container">
                                            <div class="drive-link-header">
                                                <h3><i class="fab fa-google-drive drive-icon"></i> Google Drive Link</h3>
                                            </div>
                                            <div class="drive-link-body">
                                                <input type="url" id="drive_link" name="drive_link" class="drive-link-input" placeholder="https://drive.google.com/file/d/...">
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle"></i> Make sure the link is publicly accessible or shared with appropriate permissions
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <?php if ($isSubParameter): ?>
                                            <a href="list.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-secondary">Cancel</a>
                                        <?php else: ?>
                                            <a href="list.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-primary">Upload Evidence</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <?php if ($isSubParameter): ?>
                                    <h2 class="card-title">Sub-Parameter Information</h2>
                                <?php else: ?>
                                    <h2 class="card-title">Parameter Information</h2>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($isSubParameter && isset($subParameter) && !empty($subParameter)): ?>
                                    <div class="info-group">
                                        <label>Sub-Parameter:</label>
                                        <span><?php echo safeParam($subParameter, 'name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Parent Parameter:</label>
                                        <span><?php echo safeParam($subParameter, 'parameter_name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Area:</label>
                                        <span><?php echo safeParam($subParameter, 'area_name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Program:</label>
                                        <span><?php echo safeParam($subParameter, 'program_name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Weight:</label>
                                        <span><?php echo isset($subParameter['weight']) ? $subParameter['weight'] : '0'; ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Description:</label>
                                        <p><?php echo nl2br(safeParam($subParameter, 'description')); ?></p>
                                    </div>
                                <?php elseif (isset($parameter) && !empty($parameter)): ?>
                                    <div class="info-group">
                                        <label>Parameter:</label>
                                        <span><?php echo safeParam($parameter, 'name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Area:</label>
                                        <span><?php echo safeParam($parameter, 'area_name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Program:</label>
                                        <span><?php echo safeParam($parameter, 'program_name'); ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Weight:</label>
                                        <span><?php echo isset($parameter['weight']) ? $parameter['weight'] : '0'; ?></span>
                                    </div>
                                    <div class="info-group">
                                        <label>Description:</label>
                                        <p><?php echo nl2br(safeParam($parameter, 'description')); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Information not available. Please select a valid parameter or sub-parameter.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h2 class="card-title">Upload Guidelines</h2>
                            </div>
                            <div class="card-body">
                                <div class="guidelines">
                                    <div class="guideline-item">
                                        <i class="fas fa-file-pdf"></i>
                                        <div class="guideline-text">
                                            <h4>Preferred Formats</h4>
                                            <p>Upload PDFs when possible for best compatibility.</p>
                                        </div>
                                    </div>
                                    <div class="guideline-item">
                                        <i class="fas fa-file-image"></i>
                                        <div class="guideline-text">
                                            <h4>Images & Scans</h4>
                                            <p>Ensure images are clear and legible.</p>
                                        </div>
                                    </div>
                                    <div class="guideline-item">
                                        <i class="fas fa-link"></i>
                                        <div class="guideline-text">
                                            <h4>Drive Links</h4>
                                            <p>Ensure links are set to "Anyone with the link can view".</p>
                                        </div>
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
    
    // File upload drag and drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        // File upload drag and drop functionality
        const uploadContainer = document.querySelector('.file-upload-container');
        const fileInput = document.getElementById('evidence_file');
        
        if (uploadContainer && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadContainer.classList.add('highlight');
            }
            
            function unhighlight() {
                uploadContainer.classList.remove('highlight');
            }
            
            uploadContainer.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    updateFilePreview(files[0]);
                }
            }
            
            // Handle manual file selection
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    updateFilePreview(this.files[0]);
                }
            });
            
            function updateFilePreview(file) {
                const filePreview = document.querySelector('.file-preview');
                const filePreviewName = document.querySelector('.file-preview-name');
                const filePreviewSize = document.querySelector('.file-preview-size');
                const filePreviewIcon = document.querySelector('.file-preview-icon i');
                
                filePreview.style.display = 'block';
                filePreviewName.textContent = file.name;
                
                // Format file size
                let fileSize = (file.size / 1024).toFixed(2) + ' KB';
                if (file.size > 1024 * 1024) {
                    fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                }
                filePreviewSize.textContent = fileSize;
                
                // Set icon based on file type
                if (file.type.includes('image')) {
                    filePreviewIcon.className = 'fas fa-file-image';
                } else if (file.type.includes('pdf')) {
                    filePreviewIcon.className = 'fas fa-file-pdf';
                } else if (file.type.includes('word')) {
                    filePreviewIcon.className = 'fas fa-file-word';
                } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
                    filePreviewIcon.className = 'fas fa-file-excel';
                } else if (file.type.includes('powerpoint') || file.type.includes('presentation')) {
                    filePreviewIcon.className = 'fas fa-file-powerpoint';
                } else if (file.type.includes('zip') || file.type.includes('rar') || file.type.includes('archive')) {
                    filePreviewIcon.className = 'fas fa-file-archive';
                } else {
                    filePreviewIcon.className = 'fas fa-file-alt';
                }
            }
            
            // Remove file button
            const removeFileBtn = document.querySelector('.file-preview-remove');
            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    document.querySelector('.file-preview').style.display = 'none';
                });
            }
        }
        
        // Toggle evidence type
        const typeFileRadio = document.getElementById('type_file');
        const typeDriveRadio = document.getElementById('type_drive');
        const fileUploadSection = document.getElementById('file_upload_section');
        const driveLinkSection = document.getElementById('drive_link_section');
        
        if (typeFileRadio && typeDriveRadio && fileUploadSection && driveLinkSection) {
            typeFileRadio.addEventListener('change', function() {
                if (this.checked) {
                    fileUploadSection.style.display = 'block';
                    driveLinkSection.style.display = 'none';
                }
            });
            
            typeDriveRadio.addEventListener('change', function() {
                if (this.checked) {
                    fileUploadSection.style.display = 'none';
                    driveLinkSection.style.display = 'block';
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