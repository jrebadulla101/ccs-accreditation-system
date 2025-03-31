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

// Check if user has permission to edit roles
if (!hasPermission('edit_role')) {
    setFlashMessage("danger", "You don't have permission to edit roles.");
    header("Location: list.php");
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

// Check if it's a system role (cannot be edited)
$isSystemRole = in_array($role['name'], ['super_admin']);
if ($isSystemRole) {
    setFlashMessage("danger", "System roles cannot be edited.");
    header("Location: view.php?id=" . $roleId);
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Role name is required.";
    } elseif ($name !== $role['name']) {
        // Check if role name already exists (only if name is changed)
        $checkQuery = "SELECT id FROM roles WHERE name = ? AND id != ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("si", $name, $roleId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors[] = "Role with this name already exists.";
        }
    }
    
    if (empty($errors)) {
        // Update role
        $updateQuery = "UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ssi", $name, $description, $roleId);
        
        if ($updateStmt->execute()) {
            // Log activity
            $userId = $_SESSION['admin_id'];
            $activityType = "role_updated";
            $activityDescription = "Updated role: $name (ID: $roleId)";
            
            $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                        VALUES (?, ?, ?, ?)";
            $logStmt = $conn->prepare($logQuery);
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $logStmt->bind_param("isss", $userId, $activityType, $activityDescription, $ipAddress);
            $logStmt->execute();
            
            setFlashMessage("success", "Role updated successfully.");
            header("Location: view.php?id=" . $roleId);
            exit();
        } else {
            $errors[] = "Failed to update role. Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role - CCS Accreditation System</title>
    
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
        
        /* Form card styles */
        .form-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .form-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
        }
        
        .form-card .card-header h5 {
            margin: 0;
            font-weight: 500;
        }
        
        .form-card .card-body {
            padding: 20px;
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
                <span class="ml-2 d-none d-md-inline">Edit Role</span>
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
                    <li class="breadcrumb-item"><a href="view.php?id=<?php echo $roleId; ?>"><?php echo htmlspecialchars($role['name']); ?></a></li>
                    <li class="breadcrumb-item active">Edit</li>
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
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Edit Form -->
            <div class="form-card">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Edit Role</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($role['name']); ?>" required>
                            <small class="form-text text-muted">Role name should be unique and descriptive.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($role['description']); ?></textarea>
                            <small class="form-text text-muted">Provide a brief description of this role and its responsibilities.</small>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="view.php?id=<?php echo $roleId; ?>" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                To manage permissions for this role, go to <a href="permissions.php?role_id=<?php echo $roleId; ?>" class="alert-link">Manage Permissions</a> page.
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
        });
    </script>
</body>
</html> 