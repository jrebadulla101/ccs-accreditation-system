<?php
// Start session and include required files
require_once '../../includes/config.php';
session_start();

// Include other necessary files
require_once '../../includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get area ID from URL
$areaId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate area ID
if ($areaId <= 0) {
    setFlashMessage("danger", "Invalid area ID.");
    header("Location: list.php");
    exit();
}

// Check if user has permission to view areas
if (!hasPermission('view_all_areas') && !hasAreaPermission($areaId, 'view')) {
    setFlashMessage("danger", "You don't have permission to view this area.");
    header("Location: list.php");
    exit();
}

// Get area details
$query = "SELECT a.*, p.name as program_name 
          FROM area_levels a 
          JOIN programs p ON a.program_id = p.id 
          WHERE a.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $areaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage("danger", "Area not found.");
    header("Location: list.php");
    exit();
}

$area = $result->fetch_assoc();

// Set page title
$page_title = "View Area: " . htmlspecialchars($area['name']);

// Include header
include_once '../../includes/header.php';

// Get parameters for this area
$paramQuery = "SELECT * FROM parameters WHERE area_level_id = ? ORDER BY name ASC";
$paramStmt = $conn->prepare($paramQuery);
$paramStmt->bind_param("i", $areaId);
$paramStmt->execute();
$paramResult = $paramStmt->get_result();

// Count evidences for this area
$evidenceQuery = "SELECT COUNT(*) as total_evidence FROM parameter_evidence pe
                 JOIN parameters p ON pe.parameter_id = p.id
                 WHERE p.area_level_id = ?";
$evidenceStmt = $conn->prepare($evidenceQuery);
$evidenceStmt->bind_param("i", $areaId);
$evidenceStmt->execute();
$evidenceResult = $evidenceStmt->get_result();
$totalEvidence = $evidenceResult->fetch_assoc()['total_evidence'];

// Get user permissions for this area
$permissionsQuery = "SELECT COUNT(*) as total_users FROM area_user_permissions WHERE area_id = ?";
$permissionsStmt = $conn->prepare($permissionsQuery);
$permissionsStmt->bind_param("i", $areaId);
$permissionsStmt->execute();
$permissionsResult = $permissionsStmt->get_result();
$totalUsersWithPermissions = $permissionsResult->fetch_assoc()['total_users'];
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1><?php echo htmlspecialchars($area['name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $area['program_id']; ?>"><?php echo htmlspecialchars($area['program_name']); ?></a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($area['name']); ?></li>
            </ol>
        </nav>
    </div>

    <div class="area-details-container">
        <div class="row">
            <div class="col-lg-4">
                <div class="area-info card">
            <div class="card-header">
                        <h2>Area Details</h2>
                <div class="card-actions">
                            <?php if (hasPermission('edit_area')): ?>
                            <a href="edit.php?id=<?php echo $areaId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="info-group">
                            <label>Area Name:</label>
                    <span><?php echo htmlspecialchars($area['name']); ?></span>
                </div>
                <div class="info-group">
                    <label>Program:</label>
                    <span><?php echo htmlspecialchars($area['program_name']); ?></span>
                </div>
                        <div class="info-group">
                            <label>Status:</label>
                            <span class="status-badge status-<?php echo strtolower($area['status']); ?>">
                                <?php echo ucfirst($area['status']); ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Description:</label>
                            <p><?php echo nl2br(htmlspecialchars($area['description'])); ?></p>
                        </div>
                        <div class="info-group">
                            <label>Created:</label>
                            <span><?php echo date('F j, Y', strtotime($area['created_at'])); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Last Updated:</label>
                            <span><?php echo date('F j, Y', strtotime($area['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="area-stats card mt-4">
                    <div class="card-header">
                        <h2>Area Statistics</h2>
                    </div>
                    <div class="card-body">
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $paramResult ? $paramResult->num_rows : 0; ?></div>
                                <div class="stat-label">Parameters</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-file-upload"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalEvidence; ?></div>
                                <div class="stat-label">Evidence Files</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalUsersWithPermissions; ?></div>
                                <div class="stat-label">Users with Permissions</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (hasPermission('assign_areas')): ?>
                <div class="area-actions card mt-4">
                    <div class="card-header">
                        <h2>Area Management</h2>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <a href="assign_permissions.php?area_id=<?php echo $areaId; ?>" class="btn btn-primary btn-block mb-2">
                                <i class="fas fa-user-lock"></i> Manage User Permissions
                            </a>
                            <?php if (hasPermission('delete_area')): ?>
                            <a href="delete.php?id=<?php echo $areaId; ?>" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this area? This will delete all associated parameters and evidence.');">
                                <i class="fas fa-trash-alt"></i> Delete Area
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-8">
                <div class="parameters-container card">
                    <div class="card-header">
                        <h2>Parameters</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('add_parameter')): ?>
                            <a href="../parameters/add.php?area_id=<?php echo $areaId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Parameter</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($paramResult && $paramResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Weight</th>
                                            <th>Evidence</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($param = $paramResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($param['name']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($param['description'], 0, 100)) . (strlen($param['description']) > 100 ? '...' : ''); ?></td>
                                                <td><?php echo $param['weight']; ?></td>
                                                <td>
                                                    <?php 
                                                    // Count evidences for this parameter
                                                    $evidenceCountQuery = "SELECT COUNT(*) as count FROM parameter_evidence WHERE parameter_id = ?";
                                                    $evidenceCountStmt = $conn->prepare($evidenceCountQuery);
                                                    $evidenceCountStmt->bind_param("i", $param['id']);
                                                    $evidenceCountStmt->execute();
                                                    $evidenceCountResult = $evidenceCountStmt->get_result();
                                                    $evidenceCount = $evidenceCountResult->fetch_assoc()['count'];
                                                    echo $evidenceCount;
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($param['status']); ?>">
                                                        <?php echo ucfirst($param['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <a href="../parameters/view.php?id=<?php echo $param['id']; ?>" class="btn btn-icon btn-info" title="View"><i class="fas fa-eye"></i></a>
                                                    
                                                    <?php if (hasPermission('edit_parameter')): ?>
                                                    <a href="../parameters/edit.php?id=<?php echo $param['id']; ?>" class="btn btn-icon btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('delete_parameter')): ?>
                                                    <a href="../parameters/delete.php?id=<?php echo $param['id']; ?>" class="btn btn-icon btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this parameter?');"><i class="fas fa-trash-alt"></i></a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="../evidence/list.php?parameter_id=<?php echo $param['id']; ?>" class="btn btn-icon btn-secondary" title="View Evidence"><i class="fas fa-file-upload"></i></a>
                                                    
                                                    <?php if (hasPermission('assign_parameters')): ?>
                                                    <a href="../parameters/assign_permissions.php?parameter_id=<?php echo $param['id']; ?>" class="btn btn-icon btn-warning" title="Manage Permissions"><i class="fas fa-user-lock"></i></a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i>
                                <p>No parameters defined for this area yet. <?php echo hasPermission('add_parameter') ? 'Click "Add Parameter" to create a new parameter.' : ''; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="user-permissions-container card mt-4">
                    <div class="card-header">
                        <h2>User Permissions</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('assign_areas')): ?>
                            <a href="assign_permissions.php?area_id=<?php echo $areaId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-lock"></i> Manage Permissions</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get users with permissions for this area
                        $userPermissionsQuery = "SELECT aup.*, u.full_name, u.email, u.role 
                                               FROM area_user_permissions aup
                                               JOIN admin_users u ON aup.user_id = u.id
                                               WHERE aup.area_id = ?
                                               ORDER BY u.full_name ASC";
                        $userPermissionsStmt = $conn->prepare($userPermissionsQuery);
                        $userPermissionsStmt->bind_param("i", $areaId);
                        $userPermissionsStmt->execute();
                        $userPermissionsResult = $userPermissionsStmt->get_result();
                        ?>
                        
                        <?php if ($userPermissionsResult && $userPermissionsResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
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
                                        <?php while ($userPerm = $userPermissionsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info-cell">
                                                        <strong><?php echo htmlspecialchars($userPerm['full_name']); ?></strong>
                                                        <small><?php echo htmlspecialchars($userPerm['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $userPerm['role'])); ?></td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_view']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_add']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_edit']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_delete']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_download']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($userPerm['can_approve']): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i>
                                <p>No specific user permissions set for this area. <?php echo hasPermission('assign_areas') ? 'Click "Manage Permissions" to assign user permissions.' : ''; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $basePath . 'includes/footer.php'; ?> 