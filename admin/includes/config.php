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

// Create program_assignments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS program_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (program_id, user_id)
)";

if (!$conn->query($sql)) {
    error_log("Error creating program_assignments table: " . $conn->error);
}

// Create programs table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    error_log("Error creating programs table: " . $conn->error);
}

// Create area_levels table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS area_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    error_log("Error creating area_levels table: " . $conn->error);
}

// Create parameters table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS parameters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_level_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    weight DECIMAL(5,2) DEFAULT 1.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_level_id) REFERENCES area_levels(id) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    error_log("Error creating parameters table: " . $conn->error);
}

// Create evidence table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    uploaded_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parameter_id) REFERENCES parameters(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id)
)";

if (!$conn->query($sql)) {
    error_log("Error creating evidence table: " . $conn->error);
}

// Set timezone
date_default_timezone_set('Asia/Manila'); 