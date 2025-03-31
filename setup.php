<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ccs_accreditation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Hash the password
$admin_username = "admin";
$admin_password = password_hash("admin", PASSWORD_BCRYPT); // Securely hash the password
$full_name = "Administrator";
$email = "admin@example.com";
$role = "super_admin";
$status = "active";

// Insert admin user
$sql = "INSERT INTO admin_users (username, password, full_name, email, role, status) 
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $admin_username, $admin_password, $full_name, $email, $role, $status);

if ($stmt->execute()) {
    echo "Admin account created successfully.";
} else {
    echo "Error: " . $stmt->error;
}

// Close connection
$stmt->close();
$conn->close();
?>
