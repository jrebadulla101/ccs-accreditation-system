<?php
// Initialize session and include required files
session_start();
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// Check if user has permission to manage settings
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!hasPermission('manage_settings')) {
    die('Permission denied');
}

// Check if file parameter is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('File parameter is required');
}

$filename = basename($_GET['file']);

// Validate filename to prevent directory traversal
if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename) !== 1) {
    die('Invalid backup file name');
}

$filepath = '../../backups/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    die('Backup file not found');
}

// Log this activity
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$userId = $_SESSION['admin_id'];
$activityType = "backup_download";
$description = "Downloaded database backup: $filename";
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

$logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
$logStmt = $conn->prepare($logQuery);
$logStmt->bind_param("issss", $userId, $activityType, $description, $ipAddress, $userAgent);
$logStmt->execute();
$conn->close();

// Force download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
flush();
readfile($filepath);
exit;
?> 