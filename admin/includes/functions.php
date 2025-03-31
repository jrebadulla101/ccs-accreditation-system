<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == $role;
}

// Clean input data
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Generate page title
function pageTitle($title = '') {
    $base = 'CCS Accreditation System - EARIST Manila';
    return $title ? "$title | $base" : $base;
}

// Check if user has a specific permission
function hasPermission($permission) {
    global $conn;
    
    // Super admins have all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check if session exists
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $userId = $_SESSION['admin_id'];
    
    // Get permissions for the user based on their role
    $query = "SELECT p.name FROM permissions p
              JOIN role_permissions rp ON p.id = rp.permission_id
              JOIN user_roles ur ON rp.role_id = ur.role_id
              WHERE ur.user_id = ? AND p.name = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $userId, $permission);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Check if user has access to a specific program
function hasAccessToProgram($programId) {
    global $conn;
    
    // Super admins have access to all programs
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check if user has permission to view all programs
    if (hasPermission('view_all_programs')) {
        return true;
    }
    
    // Check if user is assigned to the program
    if (isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        
        $query = "SELECT id FROM program_users WHERE user_id = ? AND program_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    return false;
}

// Check if user has access to a specific area
function hasAccessToArea($areaId) {
    global $conn;
    
    // Super admins have access to all areas
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check if user has permission to view all areas
    if (hasPermission('view_all_areas')) {
        return true;
    }
    
    // Check if area belongs to a program the user has access to
    if (isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        
        $query = "SELECT pu.id FROM program_users pu
                 JOIN area_levels a ON pu.program_id = a.program_id
                 WHERE pu.user_id = ? AND a.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $areaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    return false;
}

// Check if user has access to a specific parameter
function hasAccessToParameter($parameterId) {
    global $conn;
    
    // Super admins have access to all parameters
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check if user has permission to view all parameters
    if (hasPermission('view_all_parameters')) {
        return true;
    }
    
    // Check if parameter belongs to an area in a program the user has access to
    if (isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        
        $query = "SELECT pu.id FROM program_users pu
                 JOIN area_levels a ON pu.program_id = a.program_id
                 JOIN parameters p ON a.id = p.area_level_id
                 WHERE pu.user_id = ? AND p.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $parameterId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    return false;
}

// Check if user has access to a specific evidence
function hasAccessToEvidence($evidenceId) {
    global $conn;
    
    // Super admins have access to all evidence
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check if user has permission to view all evidence
    if (hasPermission('view_all_evidence')) {
        return true;
    }
    
    // Check if user is the uploader of the evidence
    if (isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        
        $query = "SELECT id FROM parameter_evidence WHERE id = ? AND uploaded_by = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $evidenceId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return true;
        }
        
        // Check if evidence belongs to a parameter in an area in a program the user has access to
        $query = "SELECT pu.id FROM program_users pu
                 JOIN area_levels a ON pu.program_id = a.program_id
                 JOIN parameters p ON a.id = p.area_level_id
                 JOIN parameter_evidence e ON p.id = e.parameter_id
                 WHERE pu.user_id = ? AND e.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $evidenceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    return false;
}

// Get all programs assigned to a user
function getUserPrograms($userId) {
    global $conn;
    
    // If the user has permission to view all programs, return all programs
    if (hasPermission('view_all_programs')) {
        $query = "SELECT * FROM programs ORDER BY name ASC";
        $result = $conn->query($query);
        return $result;
    }
    
    // Otherwise return only the assigned programs
    $query = "SELECT p.* FROM programs p
             JOIN program_users pu ON p.id = pu.program_id
             WHERE pu.user_id = ? ORDER BY p.name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result();
}

// Check if user has specific permission for an area
function hasAreaPermission($areaId, $permission) {
    global $conn;
    
    // Super admins have all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    // If not logged in, no permissions
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $userId = $_SESSION['admin_id'];
    
    // First check global permissions
    $globalPermission = hasPermission('view_all_areas');
    if ($permission == 'view' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('add_evidence');
    if ($permission == 'add' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('edit_evidence');
    if ($permission == 'edit' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('delete_evidence');
    if ($permission == 'delete' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('download_evidence');
    if ($permission == 'download' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('approve_evidence');
    if ($permission == 'approve' && $globalPermission) {
        return true;
    }
    
    // Then check specific area permissions
    $fieldName = 'can_' . $permission;
    $query = "SELECT $fieldName FROM area_user_permissions WHERE user_id = ? AND area_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $areaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row[$fieldName];
    }
    
    // If no specific permission is set, check if user has access to the program
    if ($permission == 'view') {
        $programQuery = "SELECT pu.id FROM program_users pu
                        JOIN area_levels a ON pu.program_id = a.program_id
                        WHERE pu.user_id = ? AND a.id = ?";
        $programStmt = $conn->prepare($programQuery);
        $programStmt->bind_param("ii", $userId, $areaId);
        $programStmt->execute();
        $programResult = $programStmt->get_result();
        
        return $programResult->num_rows > 0;
    }
    
    return false;
}

// Check if user has specific permission for a parameter
function hasParameterPermission($parameterId, $permission) {
    global $conn;
    
    // Super admins have all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    // If not logged in, no permissions
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $userId = $_SESSION['admin_id'];
    
    // First check global permissions
    $globalPermission = hasPermission('view_all_parameters');
    if ($permission == 'view' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('add_evidence');
    if ($permission == 'add' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('edit_evidence');
    if ($permission == 'edit' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('delete_evidence');
    if ($permission == 'delete' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('download_evidence');
    if ($permission == 'download' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('approve_evidence');
    if ($permission == 'approve' && $globalPermission) {
        return true;
    }
    
    // Then check specific parameter permissions
    $fieldName = 'can_' . $permission;
    $query = "SELECT $fieldName FROM parameter_user_permissions WHERE user_id = ? AND parameter_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $parameterId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row[$fieldName];
    }
    
    // If no parameter-specific permission, check area permissions
    $areaQuery = "SELECT area_level_id FROM parameters WHERE id = ?";
    $areaStmt = $conn->prepare($areaQuery);
    $areaStmt->bind_param("i", $parameterId);
    $areaStmt->execute();
    $areaResult = $areaStmt->get_result();
    
    if ($areaResult->num_rows > 0) {
        $areaId = $areaResult->fetch_assoc()['area_level_id'];
        return hasAreaPermission($areaId, $permission);
    }
    
    return false;
}

// Check if user has specific permission for an evidence
function hasEvidencePermission($evidenceId, $permission) {
    global $conn;
    
    // Super admins have all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    // If not logged in, no permissions
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $userId = $_SESSION['admin_id'];
    
    // First check if user is the uploader (always has permission to view, edit, delete their own uploads)
    $uploaderQuery = "SELECT id FROM parameter_evidence WHERE id = ? AND uploaded_by = ?";
    $uploaderStmt = $conn->prepare($uploaderQuery);
    $uploaderStmt->bind_param("ii", $evidenceId, $userId);
    $uploaderStmt->execute();
    $uploaderResult = $uploaderStmt->get_result();
    
    if ($uploaderResult->num_rows > 0) {
        // If user is the uploader and asking for view, edit, or delete permission
        if ($permission == 'view' || $permission == 'edit' || $permission == 'delete' || $permission == 'download') {
            return true;
        }
    }
    
    // Then check global permissions
    $globalPermission = hasPermission('view_all_evidence');
    if ($permission == 'view' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('download_evidence');
    if ($permission == 'download' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('edit_evidence');
    if ($permission == 'edit' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('delete_evidence');
    if ($permission == 'delete' && $globalPermission) {
        return true;
    }
    
    $globalPermission = hasPermission('approve_evidence');
    if ($permission == 'approve' && $globalPermission) {
        return true;
    }
    
    // If not checking at the evidence level, check parameter permissions
    $parameterQuery = "SELECT parameter_id FROM parameter_evidence WHERE id = ?";
    $parameterStmt = $conn->prepare($parameterQuery);
    $parameterStmt->bind_param("i", $evidenceId);
    $parameterStmt->execute();
    $parameterResult = $parameterStmt->get_result();
    
    if ($parameterResult->num_rows > 0) {
        $parameterId = $parameterResult->fetch_assoc()['parameter_id'];
        return hasParameterPermission($parameterId, $permission);
    }
    
    return false;
}

/**
 * Check if user has permission for a specific sub-parameter
 */
function hasSubParameterPermission($subParameterId, $action) {
    global $conn;
    
    // Super admins have all permissions
    if ($_SESSION['admin_role'] == 'super_admin') {
        return true;
    }
    
    $userId = $_SESSION['admin_id'];
    
    // Check specific permission based on action
    $column = 'can_view'; // Default
    
    switch ($action) {
        case 'view':
            $column = 'can_view';
            break;
        case 'add':
            $column = 'can_add';
            break;
        case 'edit':
            $column = 'can_edit';
            break;
        case 'delete':
            $column = 'can_delete';
            break;
        case 'download':
            $column = 'can_download';
            break;
        case 'approve':
            $column = 'can_approve';
            break;
    }
    
    // Check if user has specific permission for this sub-parameter
    $query = "SELECT $column FROM sub_parameter_user_permissions 
              WHERE user_id = ? AND sub_parameter_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $subParameterId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row[$column] == 1;
    }
    
    // Check if user has general permission for this action
    return hasPermission($action . '_parameter');
}
?> 