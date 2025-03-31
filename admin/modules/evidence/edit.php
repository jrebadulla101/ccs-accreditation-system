<?php
// Fix the include path - use require_once instead of include
require_once '../../includes/config.php';  // Changed from ../../config.php to ../../includes/config.php

// Start session
session_start();

// Include functions
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page
    header("Location: ../../login.php");
    exit();
}

// Check if evidence ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No evidence ID provided.");
    header("Location: list.php");
    exit();
}

// Check if user has permission to edit evidence
if (!hasPermission('edit_evidence')) {
    setFlashMessage("danger", "You don't have permission to edit evidence.");
    header("Location: list.php");
    exit();
}

$evidenceId = intval($_GET['id']);

// Get evidence details
$query = "SELECT pe.*, sp.name as sub_parameter_name, p.name as parameter_name 
          FROM parameter_evidence pe 
          LEFT JOIN sub_parameters sp ON pe.sub_parameter_id = sp.id
          LEFT JOIN parameters p ON pe.parameter_id = p.id
          WHERE pe.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $evidenceId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Evidence file not found.");
    header("Location: list.php");
    exit();
}

$evidence = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $drive_link = isset($_POST['drive_link']) ? trim($_POST['drive_link']) : '';
    
    // Basic validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    
    // If we have a new file upload
    $file_path = $evidence['file_path']; // Default to existing file path
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $errors[] = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $errors[] = "Unknown error occurred during file upload.";
            }
        } else {
            // Define allowed file types and max file size (5MB)
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                           'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                           'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                           'image/jpeg', 'image/png', 'application/zip', 'text/plain', 'text/csv'];
            $maxFileSize = 5 * 1024 * 1024; // 5 MB
            
            // Check file type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $fileType = $finfo->file($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Invalid file type. Allowed types: PDF, Word, Excel, PowerPoint, Images, ZIP, Text, CSV.";
            }
            
            // Check file size
            if ($file['size'] > $maxFileSize) {
                $errors[] = "File size exceeds the limit (5MB).";
            }
            
            if (empty($errors)) {
                // Create uploads directory if it doesn't exist
                $uploadsDir = '../../uploads/evidence/';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                // Generate unique filename
                $fileName = time() . '_' . $file['name'];
                $filePath = $uploadsDir . $fileName;
                
                // Move the file
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // Delete old file if it exists
                    if (!empty($evidence['file_path'])) {
                        $oldFilePath = $uploadsDir . $evidence['file_path'];
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }
                    
                    $file_path = $fileName; // Update file path for database
                } else {
                    $errors[] = "Failed to move uploaded file.";
                }
            }
        }
    }
    
    if (empty($errors)) {
        // Build the update query based on what's being updated
        $updateFields = [];
        $updateTypes = "";
        $updateParams = [];
        
        // Always update title and description
        $updateFields[] = "title = ?";
        $updateFields[] = "description = ?";
        $updateTypes .= "ss";
        $updateParams[] = $title;
        $updateParams[] = $description;
        
        // Update file_path if we have a new file
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $updateFields[] = "file_path = ?";
            $updateTypes .= "s";
            $updateParams[] = $file_path;
        }
        
        // Update drive_link if provided
        if (isset($_POST['drive_link'])) {
            $updateFields[] = "drive_link = ?";
            $updateTypes .= "s";
            $updateParams[] = $drive_link;
        }
        
        // Add updated_at timestamp
        $updateFields[] = "updated_at = NOW()";
        
        // Add the ID parameter
        $updateTypes .= "i";
        $updateParams[] = $evidenceId;
        
        // Create the final query
        $updateQuery = "UPDATE parameter_evidence SET " . implode(", ", $updateFields) . " WHERE id = ?";
        
        // Execute the update
        $updateStmt = $conn->prepare($updateQuery);
        
        // Dynamically bind parameters
        $bindParams = array($updateTypes);
        foreach ($updateParams as $key => $value) {
            $bindParams[] = &$updateParams[$key];
        }
        call_user_func_array(array($updateStmt, 'bind_param'), $bindParams);
        
        if ($updateStmt->execute()) {
            // Log the activity
            $userId = $_SESSION['admin_id'];
            $activityType = "evidence_updated";
            $activityDescription = "Updated evidence: $title (ID: $evidenceId)";
            
            // Check if activity_logs table exists
            $checkTableQuery = "SHOW TABLES LIKE 'activity_logs'";
            $tableExists = $conn->query($checkTableQuery)->num_rows > 0;
            
            if ($tableExists) {
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                            VALUES (?, ?, ?, ?)";
                $logStmt = $conn->prepare($logQuery);
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $logStmt->bind_param("isss", $userId, $activityType, $activityDescription, $ipAddress);
                $logStmt->execute();
            }
            
            setFlashMessage("success", "Evidence updated successfully.");
            header("Location: view.php?id=" . $evidenceId);
            exit();
        } else {
            $errors[] = "Failed to update evidence. Database error: " . $conn->error;
        }
    }
}

// Function to get file icon based on file type
function getFileIcon($filePath) {
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    switch (strtolower($extension)) {
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fas fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fas fa-file-image';
        case 'zip':
        case 'rar':
            return 'fas fa-file-archive';
        case 'txt':
        case 'csv':
            return 'fas fa-file-alt';
        default:
            return 'fas fa-file';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Evidence - CCS Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    
    <!-- Custom CSS -->
    <style>
        /* Add your custom styles here */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background-color: #343a40;
            padding-top: 20px;
            color: #fff;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .sidebar-header p {
            margin: 0;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .sidebar ul.components {
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar ul li {
            padding: 0;
        }
        
        .sidebar ul li a {
            padding: 10px 15px;
            display: block;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar ul li.active > a {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-weight: 500;
        }
        
        /* Submenu styles */
        .sidebar ul ul {
            padding-left: 20px;
        }
        
        .sidebar a[data-toggle="collapse"] {
            position: relative;
        }
        
        .sidebar a[aria-expanded="false"]::before, 
        .sidebar a[aria-expanded="true"]::before {
            content: '\f105';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 10px;
            transition: all 0.3s;
        }
        
        .sidebar a[aria-expanded="true"]::before {
            transform: rotate(90deg);
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Header styles */
        .header {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-dropdown-btn {
            background-color: transparent;
            border: none;
            color: #343a40;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .user-dropdown-btn:hover {
            color: #007bff;
        }
        
        .user-dropdown-btn i {
            margin-left: 10px;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #007bff;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            margin-right: 10px;
        }
        
        .user-dropdown-menu {
            position: absolute;
            right: 0;
            top: 50px;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            width: 200px;
            display: none;
            z-index: 1000;
        }
        
        .user-dropdown-menu.show {
            display: block;
        }
        
        .user-dropdown-menu a {
            display: block;
            padding: 10px 15px;
            color: #343a40;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .user-dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        .user-dropdown-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Form styles */
        .form-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-card .card-header {
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-card .card-header h5 {
            margin: 0;
            font-weight: 500;
        }
        
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f8f9fa;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .file-upload-wrapper:hover {
            background-color: #e9ecef;
        }
        
        .file-upload-message {
            text-align: center;
        }
        
        .file-upload-message i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }
        
        .current-file {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .current-file i {
            font-size: 2rem;
            margin-right: 15px;
            color: #6c757d;
        }
        
        .current-file-info {
            flex: 1;
        }
        
        .current-file-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .current-file-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Breadcrumb styles */
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 15px;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
        }
        
        .breadcrumb-item.active {
            color: #343a40;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }
        
        /* Error message styles */
        .error-messages {
            margin-bottom: 15px;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>CCS Accreditation</h3>
            <p>Evidence Management</p>
        </div>
        
        <ul class="list-unstyled components">
            <li>
                <a href="../../dashboard.php">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="#programsSubmenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-graduation-cap mr-2"></i> Programs
                </a>
                <ul class="collapse list-unstyled" id="programsSubmenu">
                    <li>
                        <a href="../programs/list.php">View Programs</a>
                    </li>
                    <?php if (hasPermission('add_program')): ?>
                    <li>
                        <a href="../programs/add.php">Add Program</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <li>
                <a href="#areasSubmenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-layer-group mr-2"></i> Area Levels
                </a>
                <ul class="collapse list-unstyled" id="areasSubmenu">
                    <li>
                        <a href="../areas/list.php">View Areas</a>
                    </li>
                    <?php if (hasPermission('add_area')): ?>
                    <li>
                        <a href="../areas/add.php">Add Area</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <li>
                <a href="#parametersSubmenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-cubes mr-2"></i> Parameters
                </a>
                <ul class="collapse list-unstyled" id="parametersSubmenu">
                    <li>
                        <a href="../parameters/list.php">View Parameters</a>
                    </li>
                    <?php if (hasPermission('add_parameter')): ?>
                    <li>
                        <a href="../parameters/add.php">Add Parameter</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <li class="active">
                <a href="#evidenceSubmenu" data-toggle="collapse" aria-expanded="true">
                    <i class="fas fa-file-alt mr-2"></i> Evidence
                </a>
                <ul class="collapse list-unstyled show" id="evidenceSubmenu">
                    <li>
                        <a href="list.php">View Evidence</a>
                    </li>
                    <?php if (hasPermission('add_evidence')): ?>
                    <li>
                        <a href="add.php">Upload Evidence</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('approve_evidence')): ?>
                    <li>
                        <a href="pending.php">Pending Approvals</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (hasPermission('view_users') || hasPermission('add_user')): ?>
            <li>
                <a href="#usersSubmenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-users mr-2"></i> Users
                </a>
                <ul class="collapse list-unstyled" id="usersSubmenu">
                    <li>
                        <a href="../users/list.php">View Users</a>
                    </li>
                    <?php if (hasPermission('add_user')): ?>
                    <li>
                        <a href="../users/add.php">Add User</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('view_roles') || hasPermission('manage_permissions')): ?>
            <li>
                <a href="#rolesSubmenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-user-tag mr-2"></i> Roles
                </a>
                <ul class="collapse list-unstyled" id="rolesSubmenu">
                    <li>
                        <a href="../roles/list.php">View Roles</a>
                    </li>
                    <?php if (hasPermission('add_role')): ?>
                    <li>
                        <a href="../roles/add.php">Add Role</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('manage_permissions')): ?>
                    <li>
                        <a href="../roles/permissions.php">Manage Permissions</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('manage_settings')): ?>
            <li>
                <a href="../settings/index.php">
                    <i class="fas fa-cog mr-2"></i> Settings
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('view_logs')): ?>
            <li>
                <a href="../logs/activity.php">
                    <i class="fas fa-history mr-2"></i> Activity Logs
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <button type="button" id="sidebarCollapse" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="ml-2 d-none d-md-inline">Edit Evidence</span>
            </div>
            
            <div class="user-dropdown">
                <button type="button" class="user-dropdown-btn" id="userDropdown">
                    <div class="user-avatar">
                        <?php
                        $userName = $_SESSION['admin_fullname'] ?? 'Admin User';
                        $initials = '';
                        $nameParts = explode(' ', $userName);
                        foreach ($nameParts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($userName); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <a href="../profile/view.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="../profile/change-password.php">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <a href="../../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Evidence</a></li>
                    <li class="breadcrumb-item active">Edit Evidence</li>
                </ol>
            </nav>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="error-messages alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Edit Form -->
            <div class="form-card">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Edit Evidence</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($evidence['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($evidence['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="parameter">Parameter</label>
                            <input type="text" class="form-control" id="parameter" value="<?php echo htmlspecialchars($evidence['parameter_name']); ?>" readonly>
                        </div>
                        
                        <?php if (!empty($evidence['sub_parameter_name'])): ?>
                        <div class="form-group">
                            <label for="subParameter">Sub-Parameter</label>
                            <input type="text" class="form-control" id="subParameter" value="<?php echo htmlspecialchars($evidence['sub_parameter_name']); ?>" readonly>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($evidence['file_path'])): ?>
                        <div class="form-group">
                            <label>Current File</label>
                            <div class="current-file">
                                <i class="<?php echo getFileIcon($evidence['file_path']); ?>"></i>
                                <div class="current-file-info">
                                    <div class="current-file-name"><?php echo htmlspecialchars(basename($evidence['file_path'])); ?></div>
                                    <div class="current-file-meta">
                                        <?php
                                        $filePath = '../../uploads/evidence/' . $evidence['file_path'];
                                        if (file_exists($filePath)) {
                                            echo 'Size: ' . round(filesize($filePath) / 1024, 2) . ' KB | ';
                                            echo 'Uploaded: ' . date('M d, Y', filemtime($filePath));
                                        } else {
                                            echo 'File not found on server';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <a href="download.php?id=<?php echo $evidenceId; ?>" class="btn btn-sm btn-outline-primary ml-2" title="Download File">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="file">Upload New File (Optional)</label>
                            <div class="file-upload-wrapper">
                                <input type="file" class="file-upload-input" id="file" name="file">
                                <div class="file-upload-message">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop a file here or click to browse</p>
                                    <small class="text-muted">Max file size: 5MB</small>
                                </div>
                            </div>
                            <small class="form-text text-muted">Allowed file types: PDF, Word, Excel, PowerPoint, Images, ZIP, Text, CSV</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="drive_link">Google Drive Link (Optional)</label>
                            <input type="url" class="form-control" id="drive_link" name="drive_link" value="<?php echo htmlspecialchars($evidence['drive_link'] ?? ''); ?>">
                            <small class="form-text text-muted">If you prefer to share a Google Drive link instead of uploading a file</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($evidence['status'] ?? 'pending')); ?>" readonly>
                            <small class="form-text text-muted">
                                <?php if (($evidence['status'] ?? 'pending') == 'pending'): ?>
                                    This evidence is pending approval by an administrator.
                                <?php elseif (($evidence['status'] ?? '') == 'approved'): ?>
                                    This evidence has been approved.
                                <?php elseif (($evidence['status'] ?? '') == 'rejected'): ?>
                                    This evidence has been rejected.
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Evidence
                            </button>
                            <a href="view.php?id=<?php echo $evidenceId; ?>" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle sidebar
            $('#sidebarCollapse').on('click', function() {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('active');
            });
            
            // User dropdown
            $('#userDropdown').on('click', function(e) {
                e.preventDefault();
                $('#userDropdownMenu').toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-dropdown').length) {
                    $('#userDropdownMenu').removeClass('show');
                }
            });
            
            // File upload preview
            $('.file-upload-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $('.file-upload-message p').text(fileName);
                    $('.file-upload-message i').removeClass('fa-cloud-upload-alt').addClass('fa-file');
                    $('.file-upload-wrapper').css('background-color', '#e8f4f8');
                } else {
                    $('.file-upload-message p').text('Drag & drop a file here or click to browse');
                    $('.file-upload-message i').removeClass('fa-file').addClass('fa-cloud-upload-alt');
                    $('.file-upload-wrapper').css('background-color', '#f8f9fa');
                }
            });
            
            // Submenu toggle
            $('.sidebar a[data-toggle="collapse"]').on('click', function() {
                var target = $(this).attr('href');
                if (!$(target).hasClass('show')) {
                    $('.sidebar .collapse.show').collapse('hide');
                }
            });
        });
    </script>
</body>
</html> 