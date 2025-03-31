<?php
// Adjust the relative path as needed
$basePath = '../../';
include $basePath . 'includes/header.php';

// Check if user has permission to manage area permissions
if (!hasPermission('assign_areas')) {
    setFlashMessage("danger", "You don't have permission to assign area permissions.");
    header("Location: " . $basePath . "dashboard.php");
    exit();
}

// Get area_id from URL
$areaId = isset($_GET['area_id']) ? intval($_GET['area_id']) : 0;

if ($areaId <= 0) {
    setFlashMessage("danger", "Area ID is required.");
    header("Location: list.php");
    exit();
}

// Get area details
$areaQuery = "SELECT a.*, p.name as program_name, p.id as program_id 
              FROM area_levels a 
              JOIN programs p ON a.program_id = p.id 
              WHERE a.id = ?";
$areaStmt = $conn->prepare($areaQuery);
$areaStmt->bind_param("i", $areaId);
$areaStmt->execute();
$areaResult = $areaStmt->get_result();

if ($areaResult->num_rows === 0) {
    setFlashMessage("danger", "Area not found.");
    header("Location: list.php");
    exit();
}

$area = $areaResult->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $users = isset($_POST['users']) ? $_POST['users'] : [];
    
    // First clear all existing permissions for this area
    $clearQuery = "DELETE FROM area_user_permissions WHERE area_id = ?";
    $clearStmt = $conn->prepare($clearQuery);
    $clearStmt->bind_param("i", $areaId);
    $clearStmt->execute();
    
    // Insert new permissions
    if (!empty($users)) {
        $insertQuery = "INSERT INTO area_user_permissions 
                        (user_id, area_id, can_view, can_add, can_edit, can_delete, can_download, can_approve) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        
        foreach ($users as $userId => $permissions) {
            $canView = isset($permissions['view']) ? 1 : 0;
            $canAdd = isset($permissions['add']) ? 1 : 0;
            $canEdit = isset($permissions['edit']) ? 1 : 0;
            $canDelete = isset($permissions['delete']) ? 1 : 0;
            $canDownload = isset($permissions['download']) ? 1 : 0;
            $canApprove = isset($permissions['approve']) ? 1 : 0;
            
            $insertStmt->bind_param("iiiiiii", $userId, $areaId, $canView, $canAdd, $canEdit, $canDelete, $canDownload, $canApprove);
            $insertStmt->execute();
        }
    }
    
    // Log activity
    $adminId = $_SESSION['admin_id'];
    $activityType = "area_permissions_update";
    $activityDescription = "Updated user permissions for area '{$area['name']}'";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("issss", $adminId, $activityType, $activityDescription, $ipAddress, $userAgent);
    $logStmt->execute();
    
    setFlashMessage("success", "Area permissions updated successfully.");
    header("Location: view.php?id=" . $areaId);
    exit();
}

// Get all active users
$usersQuery = "SELECT id, username, full_name, email, role FROM admin_users WHERE status = 'active' ORDER BY full_name ASC";
$usersResult = $conn->query($usersQuery);

// Get current area permissions
$currentPermissionsQuery = "SELECT * FROM area_user_permissions WHERE area_id = ?";
$currentPermissionsStmt = $conn->prepare($currentPermissionsQuery);
$currentPermissionsStmt->bind_param("i", $areaId);
$currentPermissionsStmt->execute();
$currentPermissionsResult = $currentPermissionsStmt->get_result();

$currentPermissions = [];
while ($row = $currentPermissionsResult->fetch_assoc()) {
    $currentPermissions[$row['user_id']] = $row;
}
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1>Manage Area Permissions: <?php echo htmlspecialchars($area['name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $area['program_id']; ?>"><?php echo htmlspecialchars($area['program_name']); ?></a></li>
                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $areaId; ?>"><?php echo htmlspecialchars($area['name']); ?></a></li>
                <li class="breadcrumb-item active">Manage Permissions</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">User Permissions for <?php echo htmlspecialchars($area['name']); ?></h2>
            <div class="card-actions">
                <a href="view.php?id=<?php echo $areaId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Area</a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Set specific permissions for each user for this area. Users can have different abilities like view, add, edit, delete, download, or approve evidence for this area.
            </div>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?area_id=" . $areaId); ?>" method="post">
                <div class="table-responsive">
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th class="text-center">View</th>
                                <th class="text-center">Add</th>
                                <th class="text-center">Edit</th>
                                <th class="text-center">Delete</th>
                                <th class="text-center">Download</th>
                                <th class="text-center">Approve</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                                <?php while ($user = $usersResult->fetch_assoc()): ?>
                                    <?php 
                                    // Skip super_admin users as they have all permissions by default
                                    if ($user['role'] == 'super_admin') continue;
                                    
                                    $hasPermissions = isset($currentPermissions[$user['id']]);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-info-cell">
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][view]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_view']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][add]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_add']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][edit]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_edit']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][delete]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_delete']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][download]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_download']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="users[<?php echo $user['id']; ?>][approve]" value="1" 
                                                   <?php echo ($hasPermissions && $currentPermissions[$user['id']]['can_approve']) ? 'checked' : ''; ?>>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $basePath . 'includes/footer.php'; ?> 