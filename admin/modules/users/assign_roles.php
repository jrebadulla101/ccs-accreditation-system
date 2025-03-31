<?php
// Adjust the relative path as needed
$basePath = '../../';
include $basePath . 'includes/header.php';

// Check if user has permission to assign roles
if (!hasPermission('assign_roles')) {
    setFlashMessage("danger", "You don't have permission to assign roles.");
    header("Location: " . $basePath . "dashboard.php");
    exit();
}

// Get user_id from URL
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    setFlashMessage("danger", "User ID is required.");
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

// Handle form submission to update roles
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First, delete all existing roles for this user
    $deleteQuery = "DELETE FROM user_roles WHERE user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();
    
    // Then, add the selected roles
    if (isset($_POST['roles']) && is_array($_POST['roles'])) {
        $insertQuery = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        
        foreach ($_POST['roles'] as $roleId) {
            $insertStmt->bind_param("ii", $userId, $roleId);
            $insertStmt->execute();
        }
    }
    
    // Update user's primary role in admin_users table
    if (isset($_POST['primary_role']) && $_POST['primary_role']) {
        $primaryRoleQuery = "SELECT name FROM roles WHERE id = ?";
        $primaryRoleStmt = $conn->prepare($primaryRoleQuery);
        $primaryRoleStmt->bind_param("i", $_POST['primary_role']);
        $primaryRoleStmt->execute();
        $primaryRoleResult = $primaryRoleStmt->get_result();
        
        if ($primaryRoleResult->num_rows > 0) {
            $primaryRole = $primaryRoleResult->fetch_assoc()['name'];
            
            $updateUserQuery = "UPDATE admin_users SET role = ? WHERE id = ?";
            $updateUserStmt = $conn->prepare($updateUserQuery);
            $updateUserStmt->bind_param("si", $primaryRole, $userId);
            $updateUserStmt->execute();
        }
    }
    
    // Log activity
    $adminId = $_SESSION['admin_id'];
    $activityType = "user_roles_update";
    $activityDescription = "Updated roles for user '{$user['full_name']}'";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("issss", $adminId, $activityType, $activityDescription, $ipAddress, $userAgent);
    $logStmt->execute();
    
    setFlashMessage("success", "Roles updated successfully for " . $user['full_name']);
    header("Location: view.php?id=" . $userId);
    exit();
}

// Get all available roles
$rolesQuery = "SELECT * FROM roles ORDER BY name ASC";
$rolesResult = $conn->query($rolesQuery);

// Get current roles for this user
$currentRolesQuery = "SELECT role_id FROM user_roles WHERE user_id = ?";
$currentRolesStmt = $conn->prepare($currentRolesQuery);
$currentRolesStmt->bind_param("i", $userId);
$currentRolesStmt->execute();
$currentRolesResult = $currentRolesStmt->get_result();

$currentRoles = [];
while ($row = $currentRolesResult->fetch_assoc()) {
    $currentRoles[] = $row['role_id'];
}
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1>Assign Roles to <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Users</a></li>
                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $userId; ?>"><?php echo htmlspecialchars($user['full_name']); ?></a></li>
                <li class="breadcrumb-item active">Assign Roles</li>
            </ol>
        </nav>
    </div>

    <div class="form-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?user_id=" . $userId); ?>" method="post">
            <div class="form-info mb-3">
                <p><strong>User:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Current Primary Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
            </div>
            
            <div class="roles-section">
                <h3>Select Roles</h3>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Assign roles to define what actions this user can perform in the system. Select a primary role to determine the user's main responsibilities.
                </div>
                
                <?php if ($rolesResult && $rolesResult->num_rows > 0): ?>
                    <div class="roles-list">
                        <?php while ($role = $rolesResult->fetch_assoc()): ?>
                            <div class="role-assignment-item">
                                <div class="role-checkbox">
                                    <input type="checkbox" id="role_<?php echo $role['id']; ?>" name="roles[]" value="<?php echo $role['id']; ?>" 
                                           <?php echo in_array($role['id'], $currentRoles) ? 'checked' : ''; ?>>
                                    <label for="role_<?php echo $role['id']; ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($role['name']))); ?></label>
                                </div>
                                
                                <div class="primary-role-radio">
                                    <input type="radio" id="primary_<?php echo $role['id']; ?>" name="primary_role" value="<?php echo $role['id']; ?>" 
                                           <?php echo ($user['role'] == $role['name']) ? 'checked' : ''; ?>>
                                    <label for="primary_<?php echo $role['id']; ?>">Primary Role</label>
                                </div>
                                
                                <div class="role-description">
                                    <?php echo htmlspecialchars($role['description']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i>
                        <p>No roles defined in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Roles</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // When a role checkbox is unchecked, also uncheck its primary role
    const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
    roleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const roleId = this.value;
            const primaryRadio = document.getElementById('primary_' + roleId);
            
            if (!this.checked && primaryRadio && primaryRadio.checked) {
                primaryRadio.checked = false;
            }
        });
    });
    
    // When a primary role is selected, ensure its role checkbox is checked
    const primaryRadios = document.querySelectorAll('input[name="primary_role"]');
    primaryRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const roleId = this.value;
                const roleCheckbox = document.getElementById('role_' + roleId);
                
                if (roleCheckbox && !roleCheckbox.checked) {
                    roleCheckbox.checked = true;
                }
            }
        });
    });
});
</script>

<?php include $basePath . 'includes/footer.php'; ?> 