<?php
// Initialize session and include required files
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Adjust path as needed
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Initialize response array
$response = [
    'stats' => [],
    'evidenceStatus' => [],
    'recentActivity' => [],
    'recentUploads' => []
];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get user permissions
$userId = $_SESSION['admin_id'];
$userPermissions = getUserPermissions($userId, $conn);

// Get total programs
$programQuery = "SELECT COUNT(*) as total FROM programs";
if (!hasPermission('view_all_programs', $userPermissions)) {
    $programQuery = "SELECT COUNT(*) as total FROM program_assignments WHERE user_id = $userId";
}
$result = $conn->query($programQuery);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['stats']['programs'] = (int)$row['total'];
}

// Get total areas
$areaQuery = "SELECT COUNT(*) as total FROM area_levels";
if (!hasPermission('view_all_areas', $userPermissions)) {
    $areaQuery = "SELECT COUNT(al.id) as total 
                 FROM area_levels al
                 JOIN programs p ON al.program_id = p.id
                 JOIN program_assignments pa ON p.id = pa.program_id
                 WHERE pa.user_id = $userId";
}
$result = $conn->query($areaQuery);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['stats']['areas'] = (int)$row['total'];
}

// Get total parameters
$parameterQuery = "SELECT COUNT(*) as total FROM parameters";
if (!hasPermission('view_all_parameters', $userPermissions)) {
    $parameterQuery = "SELECT COUNT(p.id) as total 
                      FROM parameters p
                      JOIN area_levels al ON p.area_id = al.id
                      JOIN programs pr ON al.program_id = pr.id
                      JOIN program_assignments pa ON pr.id = pa.program_id
                      WHERE pa.user_id = $userId";
}
$result = $conn->query($parameterQuery);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['stats']['parameters'] = (int)$row['total'];
}

// Get total evidence
$evidenceQuery = "SELECT COUNT(*) as total FROM parameter_evidence";
if (!hasPermission('view_all_evidence', $userPermissions)) {
    $evidenceQuery = "SELECT COUNT(pe.id) as total 
                     FROM parameter_evidence pe
                     JOIN parameters p ON pe.parameter_id = p.id
                     JOIN area_levels al ON p.area_id = al.id
                     JOIN programs pr ON al.program_id = pr.id
                     JOIN program_assignments pa ON pr.id = pa.program_id
                     WHERE pa.user_id = $userId";
}
$result = $conn->query($evidenceQuery);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['stats']['evidence'] = (int)$row['total'];
}

// Get total users
if (hasPermission('view_users', $userPermissions)) {
    $userQuery = "SELECT COUNT(*) as total FROM admin_users";
    $result = $conn->query($userQuery);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['stats']['users'] = (int)$row['total'];
    }
}

// Get evidence by status
$evidenceByStatusQuery = "SELECT status, COUNT(*) as count FROM parameter_evidence";
if (!hasPermission('view_all_evidence', $userPermissions)) {
    $evidenceByStatusQuery .= " WHERE uploaded_by = $userId";
}
$evidenceByStatusQuery .= " GROUP BY status";

$result = $conn->query($evidenceByStatusQuery);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response['evidenceStatus'][strtolower($row['status'])] = (int)$row['count'];
    }
}

// If no data, set default values
if (empty($response['evidenceStatus'])) {
    $response['evidenceStatus'] = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
}

// Get recent activity
$recentActivityQuery = "SELECT al.id, al.user_id, al.activity_type, al.description, al.created_at, au.full_name 
                        FROM activity_logs al
                        LEFT JOIN admin_users au ON al.user_id = au.id
                        ORDER BY al.created_at DESC
                        LIMIT 10";
$result = $conn->query($recentActivityQuery);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response['recentActivity'][] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'activity_type' => $row['activity_type'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'full_name' => $row['full_name']
        ];
    }
}

// Get recent evidence uploads
$recentEvidenceQuery = "SELECT pe.id, pe.parameter_id, pe.title, pe.file_path, pe.drive_link, 
                         pe.status, pe.uploaded_by, pe.created_at, p.name as parameter_name,
                         au.full_name as uploaded_by_name 
                        FROM parameter_evidence pe
                        JOIN parameters p ON pe.parameter_id = p.id
                        LEFT JOIN admin_users au ON pe.uploaded_by = au.id";

if (!hasPermission('view_all_evidence', $userPermissions)) {
    $recentEvidenceQuery .= " WHERE pe.uploaded_by = $userId";
}

$recentEvidenceQuery .= " ORDER BY pe.created_at DESC LIMIT 5";
$result = $conn->query($recentEvidenceQuery);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response['recentUploads'][] = [
            'id' => (int)$row['id'],
            'parameter_id' => (int)$row['parameter_id'],
            'title' => $row['title'],
            'file_path' => $row['file_path'],
            'drive_link' => $row['drive_link'],
            'status' => $row['status'],
            'uploaded_by' => (int)$row['uploaded_by'],
            'created_at' => $row['created_at'],
            'parameter_name' => $row['parameter_name'],
            'uploaded_by_name' => $row['uploaded_by_name']
        ];
    }
}

// Close database connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?> 