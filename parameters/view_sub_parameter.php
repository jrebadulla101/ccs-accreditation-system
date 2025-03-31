<?php if (hasPermission('add_evidence')): ?>
<!-- Always show the upload button regardless of existing files -->
<div class="action-button mb-3">
    <a href="../evidence/add.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-success">
        <i class="fas fa-upload"></i> Upload Evidence
    </a>
</div>
<?php endif; ?>

<!-- Evidence Files Section -->
<div class="card evidence-card mt-4">
    <div class="card-header">
        <h5><i class="fas fa-file-alt"></i> Evidence Files</h5>
    </div>
    <div class="card-body">
        <!-- Remove any conditional statements that might hide the upload button -->
        <!-- Make sure the upload section is not conditionally hidden based on file count -->
        
        <?php if (count($evidenceFiles) > 0): ?>
        <div class="evidence-files-grid">
            <?php foreach ($evidenceFiles as $file): ?>
                <!-- Display existing files here -->
                <div class="evidence-file-card">
                    <!-- File content -->
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-file-upload"></i>
            </div>
            <h4>No Evidence Files Yet</h4>
            <p>Upload evidence files to support this sub-parameter.</p>
            
            <?php if (hasPermission('add_evidence')): ?>
            <a href="../evidence/add.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-primary mt-3">
                <i class="fas fa-upload"></i> Upload Evidence
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add an additional persistent upload button at the bottom for convenience -->
<?php if (hasPermission('add_evidence') && count($evidenceFiles) > 0): ?>
<div class="mt-4 text-center">
    <a href="../evidence/add.php?sub_parameter_id=<?php echo $subParameterId; ?>" class="btn btn-success">
        <i class="fas fa-plus"></i> Add More Evidence Files
    </a>
</div>
<?php endif; ?> 