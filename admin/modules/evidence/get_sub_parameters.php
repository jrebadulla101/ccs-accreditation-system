<?php
// Include configuration and start session
require_once '../../includes/config.php';
session_start();

// Include functions
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check for parameter_id
if (!isset($_GET['parameter_id']) || empty($_GET['parameter_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$parameter_id = intval($_GET['parameter_id']);

// Fetch sub-parameters for the given parameter
$query = "SELECT id, name FROM sub_parameters WHERE parameter_id = ? ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parameter_id);
$stmt->execute();
$result = $stmt->get_result();

$subParameters = [];
while ($row = $result->fetch_assoc()) {
    $subParameters[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode($subParameters);
exit();
?> 