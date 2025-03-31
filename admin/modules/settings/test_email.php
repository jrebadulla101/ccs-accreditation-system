<?php
// Initialize session and include required files
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'test_email') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

// Adjust path as needed
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Get admin user's email for testing
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$adminId = $_SESSION['admin_id'];
$query = "SELECT email FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Admin user not found']);
    exit();
}

$adminEmail = $result->fetch_assoc()['email'];

// Collect SMTP details from the form
$smtpHost = $_POST['smtp_host'] ?? '';
$smtpPort = intval($_POST['smtp_port'] ?? 0);
$smtpEncryption = $_POST['smtp_encryption'] ?? '';
$smtpUsername = $_POST['smtp_username'] ?? '';
$smtpPassword = $_POST['smtp_password'] ?? '';
$emailFrom = $_POST['email_from'] ?? '';
$emailFromName = $_POST['email_from_name'] ?? '';

// If SMTP password is blank, try to get the saved one from the database
if (empty($smtpPassword)) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'smtp_password'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $smtpPassword = $result->fetch_assoc()['setting_value'];
    }
}

// Use PHPMailer for sending test email
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->Port = $smtpPort;
    
    if (!empty($smtpEncryption)) {
        $mail->SMTPSecure = $smtpEncryption;
    }
    
    if (!empty($smtpUsername) && !empty($smtpPassword)) {
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
    }

    // Recipients
    $mail->setFrom($emailFrom, $emailFromName);
    $mail->addAddress($adminEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from CCS Accreditation System';
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">
            <h1 style="color: #4A90E2; margin-top: 0;">Test Email</h1>
            <p>This is a test email from the CCS Accreditation System to confirm your email settings are working correctly.</p>
            <p>If you received this email, your email configuration is properly set up!</p>
            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="color: #777; font-size: 12px;">This is an automated message. Please do not reply to this email.</p>
        </div>
    ';
    $mail->AltBody = 'This is a test email from the CCS Accreditation System to confirm your email settings are working correctly.';

    $mail->send();
    
    // Log this activity
    $activityType = "email_test";
    $description = "Sent a test email to " . $adminEmail;
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("issss", $adminId, $activityType, $description, $ipAddress, $userAgent);
    $logStmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}

$conn->close();
?> 