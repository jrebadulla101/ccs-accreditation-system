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

// Check if user has permission to view roles
if (!hasPermission('view_roles')) {
    setFlashMessage("danger", "You don't have permission to view roles.");
    header("Location: ../../dashboard.php");
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

// Get role permissions
$permissionsQuery = "SELECT p.id, p.name, p.description, CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as has_permission
                    FROM permissions p
                    LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
                    ORDER BY p.name";

$permStmt = $conn->prepare($permissionsQuery);
$permStmt->bind_param("i", $roleId);
$permStmt->execute();
$permissionsResult = $permStmt->get_result();
$permissions = $permissionsResult->fetch_all(MYSQLI_ASSOC);

// Group permissions by category
$permissionGroups = [];
foreach ($permissions as $permission) {
    // Extract category from permission name (e.g. view_users -> users)
    $parts = explode('_', $permission['name']);
    $category = end($parts);
    
    // Some special cases and pluralization handling
    switch ($category) {
        case 'user':
        case 'users':
            $category = 'Users';
            break;
        case 'role':
        case 'roles':
            $category = 'Roles';
            break;
        case 'permission':
        case 'permissions':
            $category = 'Permissions';
            break;
        case 'program':
        case 'programs':
            $category = 'Programs';
            break;
        case 'area':
        case 'areas':
            $category = 'Areas';
            break;
        case 'parameter':
        case 'parameters':
            $category = 'Parameters';
            break;
        case 'evidence':
            $category = 'Evidence';
            break;
        case 'report':
        case 'reports':
            $category = 'Reports';
            break;
        case 'system':
        case 'settings':
            $category = 'System';
            break;
        case 'logs':
        case 'log':
            $category = 'Logs';
            break;
        default:
            $category = ucfirst($category);
    }
    
    if (!isset($permissionGroups[$category])) {
        $permissionGroups[$category] = [];
    }
    
    $permissionGroups[$category][] = $permission;
}

// Get user count with this role
$userCountQuery = "SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?";
$userCountStmt = $conn->prepare($userCountQuery);
$userCountStmt->bind_param("i", $roleId);
$userCountStmt->execute();
$userCountResult = $userCountStmt->get_result();
$userCount = $userCountResult->fetch_assoc()['count'];

// Check if the role is a system role (cannot be deleted)
$isSystemRole = in_array($role['name'], ['super_admin', 'admin']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Role - CCS Accreditation System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background-color: #343a40;
            padding-top: 20px;
            color: #fff;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .sidebar-header p {
            margin: 0;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .sidebar ul.components {
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar ul li {
            padding: 0;
        }
        
        .sidebar ul li a {
            padding: 10px 15px;
            display: block;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar ul li.active > a {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-weight: 500;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Header styles */
        .header {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Role card styles */
        .role-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .role-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
        }
        
        .role-card .card-header h5 {
            margin: 0;
            font-weight: 500;
        }
        
        .role-card .card-body {
            padding: 20px;
        }
        
        /* Role info styles */
        .role-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .role-info h6 {
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .role-stats {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
        }
        
        .stat-card .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #007bff;
        }
        
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Permission list styles */
        .permission-group {
            margin-bottom: 20px;
        }
        
        .permission-group-title {
            font-weight: 500;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 10px;
        }
        
        .permission-item {
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .permission-item:hover {
            background-color: #f8f9fa;
        }
        
        .permission-name {
            font-weight: 500;
            flex: 1;
        }
        
        .permission-desc {
            color: #6c757d;
            font-size: 14px;
            margin-top: 3px;
        }
        
        .permission-status {
            margin-left: 10px;
        }
        
        .status-yes {
            color: #28a745;
        }
        
        .status-no {
            color: #dc3545;
            opacity: 0.5;
        }
        
        /* Action buttons */
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>CCS Accreditation</h3>
            <p>Role Management</p>
        </div>
        
        <ul class="list-unstyled components">
            <li>
                <a href="../../dashboard.php">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </li>
            
            <?php if (hasPermission('view_roles') || hasPermission('add_role') || hasPermission('manage_permissions')): ?>
            <li class="active">
                <a href="#rolesSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-user-tag mr-2"></i> Roles
                </a>
                <ul class="collapse list-unstyled" id="rolesSubmenu">
                    <li>
                        <a href="list.php">View Roles</a>
                    </li>
                    <?php if (hasPermission('add_role')): ?>
                    <li>
                        <a href="add.php">Add Role</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('manage_permissions')): ?>
                    <li>
                        <a href="permissions.php">Manage Permissions</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('view_users') || hasPermission('add_user')): ?>
            <li>
                <a href="#usersSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-users mr-2"></i> Users
                </a>
                <ul class="collapse list-unstyled" id="usersSubmenu">
                    <li>
                        <a href="../users/list.php">View Users</a>
                    </li>
                    <?php if (hasPermission('add_user')): ?>
                    <li>
                        <a href="../users/add.php">Add User</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- Add other menu items as needed -->
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <button type="button" id="sidebarCollapse" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="ml-2 d-none d-md-inline">View Role</span>
            </div>
            
            <div class="user-dropdown">
                <div class="user-info">
                    <div class="user-name">
                        <?php echo $_SESSION['admin_name']; ?>
                    </div>
                    <div class="user-role">
                        <?php 
                        try {
                            $roleQuery = "SELECT role FROM admin_users WHERE id = ?";
                            $roleStmt = $conn->prepare($roleQuery);
                            $adminId = $_SESSION['admin_id'];
                            $roleStmt->bind_param("i", $adminId);
                            $roleStmt->execute();
                            $roleResult = $roleStmt->get_result();
                            
                            if ($roleResult && $roleResult->num_rows > 0) {
                                $roleData = $roleResult->fetch_assoc();
                                echo ucfirst(str_replace('_', ' ', $roleData['role']));
                            } else {
                                echo "User";
                            }
                        } catch (Exception $e) {
                            echo "System User";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Roles</a></li>
                    <li class="breadcrumb-item active">View Role</li>
                </ol>
            </nav>
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['flash_message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php
                // Clear the flash message
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_message_type']);
                ?>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <!-- Role Info Card -->
                    <div class="role-card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Role Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="role-info">
                                <h6>Role Name</h6>
                                <p class="mb-3"><?php echo htmlspecialchars($role['name']); ?></p>
                                
                                <h6>Description</h6>
                                <p class="mb-3"><?php echo htmlspecialchars($role['description']); ?></p>
                                
                                <h6>Created At</h6>
                                <p><?php echo date('F j, Y', strtotime($role['created_at'])); ?></p>
                                
                                <?php if (isset($role['updated_at']) && $role['updated_at'] !== $role['created_at']): ?>
                                <h6>Last Updated</h6>
                                <p><?php echo date('F j, Y', strtotime($role['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="role-stats">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $userCount; ?></div>
                                    <div class="stat-label">Users with this role</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="stat-value">
                                        <?php
                                        $grantedCount = 0;
                                        foreach ($permissions as $permission) {
                                            if ($permission['has_permission']) {
                                                $grantedCount++;
                                            }
                                        }
                                        echo $grantedCount;
                                        ?>
                                    </div>
                                    <div class="stat-label">Permissions Granted</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="list.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                                
                                <?php if (hasPermission('edit_role')): ?>
                                <a href="edit.php?id=<?php echo $roleId; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Role
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('delete_role') && !$isSystemRole && $userCount == 0): ?>
                                <a href="delete.php?id=<?php echo $roleId; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this role?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('manage_permissions')): ?>
                                <a href="permissions.php?role_id=<?php echo $roleId; ?>" class="btn btn-info">
                                    <i class="fas fa-key"></i> Manage Permissions
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Permissions Card -->
                    <div class="role-card">
                        <div class="card-header">
                            <h5><i class="fas fa-key"></i> Role Permissions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($permissionGroups)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No permissions have been defined in the system.
                                </div>
                            <?php else: ?>
                                <?php foreach ($permissionGroups as $category => $categoryPermissions): ?>
                                <div class="permission-group">
                                    <h6 class="permission-group-title"><?php echo $category; ?></h6>
                                    
                                    <?php foreach ($categoryPermissions as $permission): ?>
                                    <div class="permission-item">
                                        <div>
                                            <div class="permission-name"><?php echo str_replace('_', ' ', ucfirst($permission['name'])); ?></div>
                                            <?php if (!empty($permission['description'])): ?>
                                            <div class="permission-desc"><?php echo htmlspecialchars($permission['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="permission-status">
                                            <?php if ($permission['has_permission']): ?>
                                            <i class="fas fa-check-circle status-yes"></i>
                                            <?php else: ?>
                                            <i class="fas fa-times-circle status-no"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle sidebar
            $('#sidebarCollapse').on('click', function() {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('active');
            });
            
            // Initialize other JS functionality as needed
        });
    </script>
</body>
</html> 