<?php
/**
 * Database Configuration
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Default XAMPP username
define('DB_PASS', '');             // Default XAMPP password (empty)
define('DB_NAME', 'ccs_accreditation'); // Your database name

/**
 * Application Configuration
 */
define('APP_NAME', 'CCS Accreditation System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/admin/');
define('UPLOAD_PATH', '../uploads/');

/**
 * Error Reporting
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Session Configuration - Only apply if session hasn't started yet
 */
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
}

/**
 * Database Connection
 */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4"); 