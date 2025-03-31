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
    setFlashMessage("danger", "You don't have permission to delete sub-parameters.");
    header("Location: list.php");
    exit();
}

// Check if sub-parameter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No sub-parameter ID provided.");
    header("Location: list.php");
    exit();
}

$subParameterId = intval($_GET['id']);

// Get sub-parameter details
$query = "SELECT name, parameter_id FROM sub_parameters WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subParameterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Sub-parameter not found.");
    header("Location: list.php");
    exit();
}

$subParameter = $result->fetch_assoc();
$subParameterName = $subParameter['name'];
$parameterId = $subParameter['parameter_id'];

// Start transaction for safe deletion
$conn->begin_transaction();

try {
    // Check for evidence table and column
    $checkEvidenceTable = "SHOW TABLES LIKE 'evidence'";
    $evidenceTableExists = $conn->query($checkEvidenceTable)->num_rows > 0;

    if ($evidenceTableExists) {
        // Check if sub_parameter_id column exists in evidence table
        $evidenceColumnsQuery = "SHOW COLUMNS FROM evidence LIKE 'sub_parameter_id'";
        $evidenceColumnsResult = $conn->query($evidenceColumnsQuery);
        
        if ($evidenceColumnsResult->num_rows > 0) {
            // Delete associated evidence files (get file paths first)
            $evidenceQuery = "SELECT file_path FROM evidence WHERE sub_parameter_id = ?";
            $evidenceStmt = $conn->prepare($evidenceQuery);
            $evidenceStmt->bind_param("i", $subParameterId);
            $evidenceStmt->execute();
            $evidenceResult = $evidenceStmt->get_result();
            
            while ($evidenceFile = $evidenceResult->fetch_assoc()) {
                $filePath = $evidenceFile['file_path'];
                if (!empty($filePath) && file_exists($filePath)) {
                    @unlink($filePath); // Delete physical file (using @ to suppress warnings if file not found)
                }
            }
            
            // Delete evidence records
            $deleteEvidenceQuery = "DELETE FROM evidence WHERE sub_parameter_id = ?";
            $deleteEvidenceStmt = $conn->prepare($deleteEvidenceQuery);
            $deleteEvidenceStmt->bind_param("i", $subParameterId);
            $deleteEvidenceStmt->execute();
        }
    }
    
    // Delete the sub-parameter
    $deleteSubParamQuery = "DELETE FROM sub_parameters WHERE id = ?";
    $deleteSubParamStmt = $conn->prepare($deleteSubParamQuery);
    $deleteSubParamStmt->bind_param("i", $subParameterId);
    $deleteSubParamStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $userId = $_SESSION['admin_id'];
    $activityType = "sub_parameter_deleted";
    $activityDescription = "Deleted sub-parameter: $subParameterName";
    
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
    
    setFlashMessage("success", "Sub-parameter \"$subParameterName\" and all associated data deleted successfully.");
    header("Location: view.php?id=" . $parameterId);
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction if any error occurs
    $conn->rollback();
    
    setFlashMessage("danger", "Failed to delete sub-parameter: " . $e->getMessage());
    header("Location: edit_sub_parameter.php?id=" . $subParameterId);
    exit();
}
?>