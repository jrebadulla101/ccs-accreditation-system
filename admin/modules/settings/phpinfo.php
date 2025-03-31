<?php
// Initialize session
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

// Display PHP info
phpinfo();
?> 