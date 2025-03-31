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

// Check if user has permission to delete programs
if (!hasPermission('delete_program')) {
    setFlashMessage("danger", "You don't have permission to delete programs.");
    header("Location: list.php");
    exit();
}

// Check if program ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage("danger", "No program ID provided.");
    header("Location: list.php");
    exit();
}

$programId = intval($_GET['id']);

// Get program details
$query = "SELECT name FROM programs WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $programId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Program not found.");
    header("Location: list.php");
    exit();
}

$program = $result->fetch_assoc();
$programName = $program['name'];

// Start transaction for safe deletion
$conn->begin_transaction();

try {
    // Check for areas associated with this program
    $checkAreaTable = "SHOW TABLES LIKE 'area_levels'";
    $areaTableExists = $conn->query($checkAreaTable)->num_rows > 0;
    
    if ($areaTableExists) {
        // Check if the program_id column exists in the area_levels table
        $areaColumnsQuery = "SHOW COLUMNS FROM area_levels LIKE 'program_id'";
        $areaColumnsResult = $conn->query($areaColumnsQuery);
        
        if ($areaColumnsResult->num_rows > 0) {
            // Get all area IDs associated with this program
            $areaQuery = "SELECT id FROM area_levels WHERE program_id = ?";
            $areaStmt = $conn->prepare($areaQuery);
            $areaStmt->bind_param("i", $programId);
            $areaStmt->execute();
            $areaResult = $areaStmt->get_result();
            
            $areaIds = [];
            while ($areaRow = $areaResult->fetch_assoc()) {
                $areaIds[] = $areaRow['id'];
            }
            
            // Check for parameters table
            $checkParamTable = "SHOW TABLES LIKE 'parameters'";
            $paramTableExists = $conn->query($checkParamTable)->num_rows > 0;
            
            if ($paramTableExists && !empty($areaIds)) {
                // Check which column name is used in parameters table
                $paramColumnQuery = "SHOW COLUMNS FROM parameters LIKE 'area_level_id'";
                $paramColumnResult = $conn->query($paramColumnQuery);
                $areaReferenceColumn = ($paramColumnResult->num_rows > 0) ? 'area_level_id' : 'area_id';
                
                // Get all parameter IDs associated with these areas
                $paramQuery = "SELECT id FROM parameters WHERE $areaReferenceColumn IN (" . implode(',', $areaIds) . ")";
                $paramResult = $conn->query($paramQuery);
                
                $paramIds = [];
                if ($paramResult) {
                    while ($paramRow = $paramResult->fetch_assoc()) {
                        $paramIds[] = $paramRow['id'];
                    }
                }
                
                // Check for sub_parameters table
                $checkSubParamTable = "SHOW TABLES LIKE 'sub_parameters'";
                $subParamTableExists = $conn->query($checkSubParamTable)->num_rows > 0;
                
                if ($subParamTableExists && !empty($paramIds)) {
                    // Delete sub parameters
                    $deleteSubParamsQuery = "DELETE FROM sub_parameters WHERE parameter_id IN (" . implode(',', $paramIds) . ")";
                    $conn->query($deleteSubParamsQuery);
                }
                
                // Check for evidence table
                $checkEvidenceTable = "SHOW TABLES LIKE 'evidence'";
                $evidenceTableExists = $conn->query($checkEvidenceTable)->num_rows > 0;
                
                if ($evidenceTableExists && !empty($paramIds)) {
                    // Get file paths for evidence files
                    $evidenceQuery = "SELECT file_path FROM evidence WHERE parameter_id IN (" . implode(',', $paramIds) . ")";
                    $evidenceResult = $conn->query($evidenceQuery);
                    
                    if ($evidenceResult) {
                        while ($evidenceRow = $evidenceResult->fetch_assoc()) {
                            if (!empty($evidenceRow['file_path']) && file_exists($evidenceRow['file_path'])) {
                                @unlink($evidenceRow['file_path']); // Delete physical file
                            }
                        }
                    }
                    
                    // Delete evidence records
                    $deleteEvidenceQuery = "DELETE FROM evidence WHERE parameter_id IN (" . implode(',', $paramIds) . ")";
                    $conn->query($deleteEvidenceQuery);
                }
                
                // Delete parameters
                $deleteParamsQuery = "DELETE FROM parameters WHERE $areaReferenceColumn IN (" . implode(',', $areaIds) . ")";
                $conn->query($deleteParamsQuery);
            }
            
            // Delete areas
            $deleteAreasQuery = "DELETE FROM area_levels WHERE program_id = ?";
            $deleteAreasStmt = $conn->prepare($deleteAreasQuery);
            $deleteAreasStmt->bind_param("i", $programId);
            $deleteAreasStmt->execute();
        }
    }
    
    // Delete the program
    $deleteProgramQuery = "DELETE FROM programs WHERE id = ?";
    $deleteProgramStmt = $conn->prepare($deleteProgramQuery);
    $deleteProgramStmt->bind_param("i", $programId);
    $deleteProgramStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $userId = $_SESSION['admin_id'];
    $activityType = "program_deleted";
    $activityDescription = "Deleted program: $programName";
    
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
    
    setFlashMessage("success", "Program \"$programName\" and all associated data deleted successfully.");
    header("Location: list.php");
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction if any error occurs
    $conn->rollback();
    
    setFlashMessage("danger", "Failed to delete program: " . $e->getMessage());
    header("Location: edit.php?id=" . $programId);
    exit();
}
?> 