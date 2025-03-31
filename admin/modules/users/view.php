<?php
// Adjust the relative path as needed
$basePath = '../../';
include $basePath . 'includes/header.php';

// Check if user has permission to view users
if (!hasPermission('view_users')) {
    setFlashMessage("danger", "You don't have permission to view users.");
    header("Location: " . $basePath . "dashboard.php");
    exit();
}

// Get user ID from URL
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($userId <= 0) {
    setFlashMessage("danger", "Invalid user ID.");
    header("Location: list.php");
    exit();
}

// Get user details
$userQuery = "SELECT * FROM admin_users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    setFlashMessage("danger", "User not found.");
    header("Location: list.php");
    exit();
}

$user = $userResult->fetch_assoc();

// Get user roles
$rolesQuery = "SELECT r.* FROM roles r
              JOIN user_roles ur ON r.id = ur.role_id
              WHERE ur.user_id = ?";
$rolesStmt = $conn->prepare($rolesQuery);
$rolesStmt->bind_param("i", $userId);
$rolesStmt->execute();
$rolesResult = $rolesStmt->get_result();

// Get assigned programs
$programsQuery = "SELECT p.* FROM programs p
                 JOIN program_users pu ON p.id = pu.program_id
                 WHERE pu.user_id = ? ORDER BY p.name ASC";
$programsStmt = $conn->prepare($programsQuery);
$programsStmt->bind_param("i", $userId);
$programsStmt->execute();
$programsResult = $programsStmt->get_result();
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Users</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['username']); ?></li>
            </ol>
        </nav>
    </div>

    <div class="user-details-container">
        <div class="row">
            <div class="col-lg-4">
                <div class="user-info card">
                    <div class="card-header">
                        <h2>User Details</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('edit_user')): ?>
                            <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        
                        <div class="info-group">
                            <label>Username:</label>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Full Name:</label>
                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Status:</label>
                            <span class="status-badge status-<?php echo strtolower($user['status']); ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        
                        <div class="info-group">
                            <label>Created:</label>
                            <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Last Updated:</label>
                            <span><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="user-roles card mb-4">
                    <div class="card-header">
                        <h2>Roles</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('assign_roles')): ?>
                            <a href="assign_roles.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-tag"></i> Assign Roles</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($rolesResult && $rolesResult->num_rows > 0): ?>
                            <div class="roles-list">
                                <?php while ($role = $rolesResult->fetch_assoc()): ?>
                                    <div class="role-item">
                                        <span class="role-name"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($role['name']))); ?></span>
                                        <span class="role-description"><?php echo htmlspecialchars($role['description']); ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i>
                                <p>No roles assigned to this user.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="user-programs card">
                    <div class="card-header">
                        <h2>Program Assignments</h2>
                        <div class="card-actions">
                            <?php if (hasPermission('assign_programs')): ?>
                            <a href="assign_programs.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-graduation-cap"></i> Assign Programs</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($programsResult && $programsResult->num_rows > 0): ?>
                            <div class="programs-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Program Name</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($program = $programsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($program['code']); ?></td>
                                                <td><?php echo htmlspecialchars($program['name']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($program['status']); ?>">
                                                        <?php echo ucfirst($program['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <a href="../programs/view.php?id=<?php echo $program['id']; ?>" class="btn btn-icon btn-info" title="View Program"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i>
                                <p>No programs assigned to this user.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $basePath . 'includes/footer.php'; ?> 