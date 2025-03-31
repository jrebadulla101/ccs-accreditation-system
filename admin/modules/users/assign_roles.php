<?php
// Include configuration and start session before any output
require_once '../../includes/config.php';
session_start();

// Include functions
require_once '../../includes/functions.php';

// Set base path
$basePath = '../../';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . $basePath . "login.php");
    exit();
}

// Check if user has permission to assign roles
if (!hasPermission('assign_roles')) {
    setFlashMessage("danger", "You don't have permission to assign roles.");
    header("Location: list.php");
    exit();
}   

// Check if user ID is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    setFlashMessage("danger", "No user ID provided.");
    header("Location: list.php");
    exit();
}

$userId = intval($_GET['user_id']);

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

// Get all available roles
$rolesQuery = "SELECT id, name, description FROM roles ORDER BY name";
$rolesResult = $conn->query($rolesQuery);
$roles = $rolesResult->fetch_all(MYSQLI_ASSOC);

// Get user's current roles
$userRolesQuery = "SELECT role_id FROM user_roles WHERE user_id = ?";
$userRolesStmt = $conn->prepare($userRolesQuery);
$userRolesStmt->bind_param("i", $userId);
$userRolesStmt->execute();
$userRolesResult = $userRolesStmt->get_result();

$userRoles = [];
while ($role = $userRolesResult->fetch_assoc()) {
    $userRoles[] = $role['role_id'];
}

// Handle form submission - process before any HTML output
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selectedRoles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete all current role assignments
        $deleteQuery = "DELETE FROM user_roles WHERE user_id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $userId);
        $deleteStmt->execute();
        
        // Insert new role assignments
        if (!empty($selectedRoles)) {
            $insertQuery = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            
            foreach ($selectedRoles as $roleId) {
                $insertStmt->bind_param("ii", $userId, $roleId);
                $insertStmt->execute();
            }
        }
        
        // Log activity
        $adminId = $_SESSION['admin_id'];
        $activityType = "roles_assigned";
        $activityDescription = "Assigned roles to user: {$user['full_name']} (ID: $userId)";
        
        $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
                    VALUES (?, ?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $logStmt->bind_param("isss", $adminId, $activityType, $activityDescription, $ipAddress);
        $logStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        setFlashMessage("success", "Roles assigned successfully.");
        header("Location: view.php?user_id=" . $userId);
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Store error message in a variable instead of immediately outputting it
        $errorMessage = "Failed to assign roles. Error: " . $e->getMessage();
    }
}

// Include header after all potential redirects
include_once $basePath . 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1>Assign Roles to <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Users</a></li>
                <li class="breadcrumb-item"><a href="view.php?user_id=<?php echo $userId; ?>"><?php echo htmlspecialchars($user['full_name']); ?></a></li>
                <li class="breadcrumb-item active">Assign Roles</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
    <?php endif; ?>

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

    <div class="form-container">
        <form method="POST" action="">
            <div class="form-info mb-3">
                <p><strong>User:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
            </div>
            
            <div class="roles-section">
                <h3>Select Roles to Assign</h3>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Assigning roles determines the user's permissions and access levels within the system.
                </div>
                
                <?php if (!empty($roles)): ?>
                    <div class="roles-grid">
                        <?php foreach ($roles as $role): ?>
                            <div class="role-item">
                                <input type="checkbox" id="role_<?php echo $role['id']; ?>" name="roles[]" value="<?php echo $role['id']; ?>" 
                                       <?php echo in_array($role['id'], $userRoles) ? 'checked' : ''; ?>>
                                <label for="role_<?php echo $role['id']; ?>">
                                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                    <?php if (!empty($role['description'])): ?>
                                        <p class="text-muted small"><?php echo htmlspecialchars($role['description']); ?></p>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="assignment-actions">
                        <button type="button" id="select-all" class="btn btn-sm btn-secondary">Select All</button>
                        <button type="button" id="deselect-all" class="btn btn-sm btn-secondary">Deselect All</button>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i>
                        <p>No roles defined in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="view.php?user_id=<?php echo $userId; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Role Assignments</button>
            </div>
        </form>
    </div>
</div>

<style>
.content-wrapper {
    padding: 20px;
    background: #f8f9fc;
    min-height: calc(100vh - 60px);
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    color: #4e73df;
    font-size: 24px;
    margin-bottom: 10px;
}

.breadcrumb-container {
    background: #fff;
    padding: 10px 15px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.breadcrumb {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.breadcrumb-item {
    color: #858796;
    font-size: 14px;
}

.breadcrumb-item a {
    color: #4e73df;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #4e73df;
    font-weight: 500;
}

.form-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 25px;
}

.form-info {
    background: #f8f9fc;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.form-info p {
    margin: 5px 0;
    color: #5a5c69;
}

.roles-section {
    margin-top: 20px;
}

.roles-section h3 {
    color: #4e73df;
    font-size: 18px;
    margin-bottom: 15px;
}

.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.role-item {
    background: #f8f9fc;
    border: 1px solid #e3e6f0;
    border-radius: 5px;
    padding: 15px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.role-item input[type="checkbox"] {
    margin-top: 3px;
}

.role-item label {
    flex: 1;
    margin: 0;
    cursor: pointer;
}

.role-item label strong {
    color: #4e73df;
    display: block;
    margin-bottom: 5px;
}

.role-item .text-muted {
    color: #858796 !important;
    font-size: 13px;
    margin: 0;
}

.assignment-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary {
    background: #4e73df;
    border-color: #4e73df;
}

.btn-primary:hover {
    background: #2e59d9;
    border-color: #2653d4;
}

.btn-secondary {
    background: #858796;
    border-color: #858796;
}

.btn-secondary:hover {
    background: #717384;
    border-color: #6b6d7d;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-info {
    background: #cce5ff;
    border-color: #b8daff;
    color: #004085;
}

.alert-danger {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.no-data-message {
    text-align: center;
    padding: 30px;
    background: #f8f9fc;
    border-radius: 5px;
    color: #858796;
}

.no-data-message i {
    font-size: 24px;
    margin-bottom: 10px;
    color: #4e73df;
}

@media (max-width: 768px) {
    .roles-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="roles[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="roles[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
});
</script>

<?php include $basePath . 'includes/footer.php'; ?> 