<?php
// Create download_test.php
$path = isset($_GET['path']) ? $_GET['path'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($path) || !$id) {
    die('Missing parameters');
}

if (!file_exists($path)) {
    die('File does not exist: ' . htmlspecialchars($path));
}

$fileSize = filesize($path);
$fileType = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
$downloadFilename = basename($path);

// Clear any buffered output
if (ob_get_level()) ob_end_clean();

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Send file
readfile($path);
exit;