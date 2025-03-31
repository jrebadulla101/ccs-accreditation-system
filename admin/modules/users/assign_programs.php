<?php
// Adjust the relative path as needed
$basePath = '../../';
include $basePath . 'includes/header.php';

// Check if user has permission to assign programs
if (!hasPermission('assign_programs')) {
    setFlashMessage("danger", "You don't have permission to assign programs.");
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

// Handle form submission to update program assignments
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First, delete all existing program assignments for this user
    $deleteQuery = "DELETE FROM program_users WHERE user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();
    
    // Then, add the selected programs
    if (isset($_POST['programs']) && is_array($_POST['programs'])) {
        $insertQuery = "INSERT INTO program_users (program_id, user_id) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        
        foreach ($_POST['programs'] as $programId) {
            $insertStmt->bind_param("ii", $programId, $userId);
            $insertStmt->execute();
        }
    }
    
    // Log activity
    $adminId = $_SESSION['admin_id'];
    $activityType = "program_assignment_update";
    $activityDescription = "Updated program assignments for user '{$user['full_name']}'";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("issss", $adminId, $activityType, $activityDescription, $ipAddress, $userAgent);
    $logStmt->execute();
    
    setFlashMessage("success", "Program assignments updated successfully for " . $user['full_name']);
    header("Location: assign_programs.php?user_id=" . $userId);
    exit();
}

// Get all available programs
$programsQuery = "SELECT * FROM programs ORDER BY name ASC";
$programsResult = $conn->query($programsQuery);

// Get current program assignments for this user
$currentProgramsQuery = "SELECT program_id FROM program_users WHERE user_id = ?";
$currentProgramsStmt = $conn->prepare($currentProgramsQuery);
$currentProgramsStmt->bind_param("i", $userId);
$currentProgramsStmt->execute();
$currentProgramsResult = $currentProgramsStmt->get_result();

$currentPrograms = [];
while ($row = $currentProgramsResult->fetch_assoc()) {
    $currentPrograms[] = $row['program_id'];
}
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1>Assign Programs to <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Users</a></li>
                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $userId; ?>"><?php echo htmlspecialchars($user['full_name']); ?></a></li>
                <li class="breadcrumb-item active">Assign Programs</li>
            </ol>
        </nav>
    </div>

    <div class="form-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?user_id=" . $userId); ?>" method="post">
            <div class="form-info mb-3">
                <p><strong>User:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
            </div>
            
            <div class="programs-section">
                <h3>Select Programs to Assign</h3>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Assigning programs gives this user access to view, manage, and evaluate accreditation for these programs based on their role permissions.
                </div>
                
                <?php if ($programsResult && $programsResult->num_rows > 0): ?>
                    <div class="programs-grid">
                        <?php while ($program = $programsResult->fetch_assoc()): ?>
                            <div class="program-item">
                                <input type="checkbox" id="prog_<?php echo $program['id']; ?>" name="programs[]" value="<?php echo $program['id']; ?>" 
                                       <?php echo in_array($program['id'], $currentPrograms) ? 'checked' : ''; ?>>
                                <label for="prog_<?php echo $program['id']; ?>">
                                    <strong><?php echo htmlspecialchars($program['code'] . ' - ' . $program['name']); ?></strong>
                                    <?php if ($program['status'] == 'inactive'): ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="assignment-actions">
                        <button type="button" id="select-all" class="btn btn-sm btn-secondary">Select All</button>
                        <button type="button" id="deselect-all" class="btn btn-sm btn-secondary">Deselect All</button>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i>
                        <p>No programs defined in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Assignments</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="programs[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="programs[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
});
</script>

<?php include $basePath . 'includes/footer.php'; ?> 