<?php
// Include configuration before starting session
require_once '../../includes/config.php';

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

$evidenceId = intval($_GET['id']);

// Get evidence details from parameter_evidence table
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
    setFlashMessage("danger", "Evidence file not found in database.");
    header("Location: list.php");
    exit();
}

$evidence = $result->fetch_assoc();

// Check if user has permission to download evidence
if (!hasPermission('download_evidence')) {
    // Log unauthorized access attempt
    $userId = $_SESSION['admin_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $description = "Unauthorized attempt to download evidence file (ID: $evidenceId)";
    
    // Check if activity_logs table exists
    $checkTableQuery = "SHOW TABLES LIKE 'activity_logs'";
    $tableExists = $conn->query($checkTableQuery)->num_rows > 0;
    
    if ($tableExists) {
        $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                    VALUES (?, 'unauthorized_access', ?, ?)";
        
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("iss", $userId, $description, $ipAddress);
        $logStmt->execute();
    }
    
    setFlashMessage("danger", "You don't have permission to download evidence files.");
    header("Location: view.php?id=" . $evidenceId);
    exit();
}

// Check if file path exists in the database
if (empty($evidence['file_path'])) {
    // Check if drive link exists instead
    if (!empty($evidence['drive_link'])) {
        setFlashMessage("info", "This evidence is a drive link. Redirecting...");
        header("Location: " . $evidence['drive_link']);
        exit();
    } else {
        setFlashMessage("danger", "No file or drive link associated with this evidence.");
        header("Location: view.php?id=" . $evidenceId);
        exit();
    }
}

// Define the possible file paths to check
                    $possiblePaths = [
    // 1. Standard path with uploads/evidence directory
    '../../uploads/evidence/' . $evidence['file_path'],
    
    // 2. If file_path already includes the directory structure
    '../../' . $evidence['file_path'],
    
    // 3. Just the filename in uploads/evidence
    '../../uploads/evidence/' . basename($evidence['file_path']),
    
    // 4. Absolute path using __DIR__
    __DIR__ . '/../../uploads/evidence/' . $evidence['file_path'],
    
    // 5. Path with any leading slashes or dots removed
    '../../uploads/evidence/' . ltrim($evidence['file_path'], './'),
    
    // 6. Direct path (if it's an absolute path)
    $evidence['file_path']
];

// Check which path exists
$filePath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $filePath = $path;
        break;
    }
}

// If none of the paths work, create the uploads directory if it doesn't exist
if ($filePath === null) {
                    $uploadsDir = '../../uploads/evidence/';
    if (!is_dir($uploadsDir)) {
        // Create the directory structure
        if (mkdir($uploadsDir, 0755, true)) {
            // Directory created, but file still doesn't exist
            setFlashMessage("danger", "File not found. The uploads directory has been created for future uploads.");
                                                } else {
            setFlashMessage("danger", "File not found and could not create uploads directory. Please contact the administrator.");
                                                }
                                            } else {
        setFlashMessage("danger", "File not found on the server. Please check if it was properly uploaded.");
    }
    header("Location: view.php?id=" . $evidenceId);
    exit();
}

// File exists, proceed with download

// Log the download activity
$userId = $_SESSION['admin_id'];
$activityType = "evidence_downloaded";
$fileName = !empty($evidence['title']) ? $evidence['title'] : basename($evidence['file_path']);
$activityDescription = "Downloaded evidence file: $fileName (ID: $evidenceId)";
$ipAddress = $_SERVER['REMOTE_ADDR'];

// Check if activity_logs table exists
$checkTableQuery = "SHOW TABLES LIKE 'activity_logs'";
$tableExists = $conn->query($checkTableQuery)->num_rows > 0;

if ($tableExists) {
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                VALUES (?, ?, ?, ?)";
    
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("isss", $userId, $activityType, $activityDescription, $ipAddress);
    $logStmt->execute();
}

// Increment the download count if the column exists
try {
    // Check if downloads column exists in parameter_evidence table
    $checkColumnQuery = "SHOW COLUMNS FROM parameter_evidence LIKE 'downloads'";
    $columnExistsResult = $conn->query($checkColumnQuery);
    
    if ($columnExistsResult->num_rows > 0) {
        // Update the download count
        $updateQuery = "UPDATE parameter_evidence SET downloads = downloads + 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $evidenceId);
        $updateStmt->execute();
    }
    
    // Check if last_downloaded_at column exists
    $checkColumnQuery = "SHOW COLUMNS FROM parameter_evidence LIKE 'last_downloaded_at'";
    $columnExistsResult = $conn->query($checkColumnQuery);
    
    if ($columnExistsResult->num_rows > 0) {
        // Update the last downloaded date
        $updateQuery = "UPDATE parameter_evidence SET last_downloaded_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $evidenceId);
        $updateStmt->execute();
    }
} catch (Exception $e) {
    // Silent catch - if update fails, continue with download
}

// Get file information
$fileSize = filesize($filePath);
if ($fileSize === false) {
    setFlashMessage("danger", "Error reading file size. Please contact the administrator.");
    header("Location: view.php?id=" . $evidenceId);
    exit();
}

// Determine file type
if (function_exists('mime_content_type')) {
    $fileType = mime_content_type($filePath);
} else {
    // Fallback to a basic method based on file extension
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'csv' => 'text/csv',
        'html' => 'text/html',
        'htm' => 'text/html',
        // Add more as needed
    ];
    $fileType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
}

// Determine a proper filename for download
$downloadFilename = !empty($evidence['title']) ? $evidence['title'] : basename($evidence['file_path']);

// Ensure the filename has an extension
$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
if (!empty($fileExtension)) {
    // Check if the downloadFilename already has this extension
    $downloadExtension = pathinfo($downloadFilename, PATHINFO_EXTENSION);
    if (empty($downloadExtension) || strtolower($downloadExtension) !== strtolower($fileExtension)) {
        $downloadFilename .= ".$fileExtension";
    }
}

// Clean the filename to remove problematic characters
$downloadFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $downloadFilename);

// Close any open database connections to free resources
$conn->close();

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set appropriate headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Stream the file
$handle = fopen($filePath, 'rb');
if ($handle) {
    // Read and output in chunks to handle large files efficiently
    while (!feof($handle) && (connection_status() === CONNECTION_NORMAL)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    // Fallback to readfile if fopen fails
    readfile($filePath);
}

exit();
?> 