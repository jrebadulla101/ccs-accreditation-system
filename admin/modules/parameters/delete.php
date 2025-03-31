<?php
// Include configuration before starting session
require_once '../../includes/config.php';

// Start session after config is loaded
session_start();

// Include other necessary files
require_once '../../includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if user has permission to delete parameters
if (!hasPermission('delete_parameter')) {
    setFlashMessage("danger", "You don't have permission to delete parameters.");
    header("Location: list.php");
    exit();
}

// Check if parameter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No parameter ID provided.");
    header("Location: list.php");
    exit();
}

$parameterId = intval($_GET['id']);

// Get parameter details
$query = "SELECT name FROM parameters WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parameterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Parameter not found.");
    header("Location: list.php");
    exit();
}

$parameter = $result->fetch_assoc();
$parameterName = $parameter['name'];

// Start transaction for safe deletion
$conn->begin_transaction();

try {
    // Check for evidence table
    $checkEvidenceTable = "SHOW TABLES LIKE 'evidence'";
    $evidenceTableExists = $conn->query($checkEvidenceTable)->num_rows > 0;

    if ($evidenceTableExists) {
        // Delete associated evidence files (get file paths first)
        $evidenceQuery = "SELECT file_path FROM evidence WHERE parameter_id = ?";
        $evidenceStmt = $conn->prepare($evidenceQuery);
        $evidenceStmt->bind_param("i", $parameterId);
        $evidenceStmt->execute();
        $evidenceResult = $evidenceStmt->get_result();
        
        while ($evidenceFile = $evidenceResult->fetch_assoc()) {
            $filePath = $evidenceFile['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath); // Delete physical file (using @ to suppress warnings if file not found)
            }
        }
        
        // Delete evidence records
        $deleteEvidenceQuery = "DELETE FROM evidence WHERE parameter_id = ?";
        $deleteEvidenceStmt = $conn->prepare($deleteEvidenceQuery);
        $deleteEvidenceStmt->bind_param("i", $parameterId);
        $deleteEvidenceStmt->execute();
    }
    
    // Check for sub_parameters table
    $checkSubParamsTable = "SHOW TABLES LIKE 'sub_parameters'";
    $subParamsTableExists = $conn->query($checkSubParamsTable)->num_rows > 0;
    
    if ($subParamsTableExists) {
        // Delete sub-parameters
        $deleteSubParamsQuery = "DELETE FROM sub_parameters WHERE parameter_id = ?";
        $deleteSubParamsStmt = $conn->prepare($deleteSubParamsQuery);
        $deleteSubParamsStmt->bind_param("i", $parameterId);
        $deleteSubParamsStmt->execute();
    }
    
    // Delete the parameter
    $deleteParamQuery = "DELETE FROM parameters WHERE id = ?";
    $deleteParamStmt = $conn->prepare($deleteParamQuery);
    $deleteParamStmt->bind_param("i", $parameterId);
    $deleteParamStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $userId = $_SESSION['admin_id'];
    $activityType = "parameter_deleted";
    $activityDescription = "Deleted parameter: $parameterName";
    
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
    
    setFlashMessage("success", "Parameter \"$parameterName\" and all associated data deleted successfully.");
    header("Location: list.php");
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction if any error occurs
    $conn->rollback();
    
    setFlashMessage("danger", "Failed to delete parameter: " . $e->getMessage());
    header("Location: edit.php?id=" . $parameterId);
    exit();
}
?>