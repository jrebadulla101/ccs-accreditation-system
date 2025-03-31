<?php
// Include configuration file and start session
require_once '../../includes/config.php';
session_start();

// Include functions
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if user has permission to delete roles
if (!hasPermission('delete_role')) {
    setFlashMessage("danger", "You don't have permission to delete roles.");
    header("Location: list.php");
    exit();
}

// Check if role ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No role ID provided.");
    header("Location: list.php");
    exit();
}

$roleId = intval($_GET['id']);

// Get role details
$query = "SELECT * FROM roles WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $roleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Role not found.");
    header("Location: list.php");
    exit();
}

$role = $result->fetch_assoc();

// Check if it's a system role (cannot be deleted)
$isSystemRole = in_array($role['name'], ['super_admin', 'admin']);
if ($isSystemRole) {
    setFlashMessage("danger", "System roles cannot be deleted.");
    header("Location: list.php");
    exit();
}

// Check if the role is assigned to any users
$checkQuery = "SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("i", $roleId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$userCount = $checkResult->fetch_assoc()['count'];

if ($userCount > 0) {
    setFlashMessage("danger", "Cannot delete role. It is currently assigned to {$userCount} user(s). Please reassign these users to different roles first.");
    header("Location: view.php?id=" . $roleId);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete role permissions
    $deletePermissionsQuery = "DELETE FROM role_permissions WHERE role_id = ?";
    $deletePermissionsStmt = $conn->prepare($deletePermissionsQuery);
    $deletePermissionsStmt->bind_param("i", $roleId);
    $deletePermissionsStmt->execute();
    
    // Delete role
    $deleteRoleQuery = "DELETE FROM roles WHERE id = ?";
    $deleteRoleStmt = $conn->prepare($deleteRoleQuery);
    $deleteRoleStmt->bind_param("i", $roleId);
    $deleteRoleStmt->execute();
    
    // Log activity
    $userId = $_SESSION['admin_id'];
    $activityType = "role_deleted";
    $activityDescription = "Deleted role: {$role['name']} (ID: $roleId)";
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                VALUES (?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $logStmt->bind_param("isss", $userId, $activityType, $activityDescription, $ipAddress);
    $logStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    setFlashMessage("success", "Role deleted successfully.");
    header("Location: list.php");
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    setFlashMessage("danger", "Failed to delete role. Error: " . $e->getMessage());
    header("Location: view.php?id=" . $roleId);
    exit();
}
?> 