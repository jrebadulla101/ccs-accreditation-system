<?php
// Adjust the relative path as needed
$basePath = '../../';
include $basePath . 'includes/header.php';

// Get parameter_id from URL
$parameterId = isset($_GET['parameter_id']) ? intval($_GET['parameter_id']) : 0;

if ($parameterId <= 0) {
    setFlashMessage("danger", "Parameter ID is required.");
    header("Location: ../parameters/list.php");
    exit();
}

// Check if user has permission to add evidence to this parameter
if (!hasParameterPermission($parameterId, 'add')) {
    setFlashMessage("danger", "You don't have permission to add evidence to this parameter.");
    header("Location: list.php?parameter_id=" . $parameterId);
    exit();
}

// Get parameter details with area and program info
$paramQuery = "SELECT p.*, a.name as area_name, a.id as area_id, pr.name as program_name, pr.id as program_id 
               FROM parameters p 
               JOIN area_levels a ON p.area_level_id = a.id 
               JOIN programs pr ON a.program_id = pr.id 
               WHERE p.id = ?";
$paramStmt = $conn->prepare($paramQuery);
$paramStmt->bind_param("i", $parameterId);
$paramStmt->execute();
$paramResult = $paramStmt->get_result();

if ($paramResult->num_rows === 0) {
    setFlashMessage("danger", "Parameter not found.");
    header("Location: ../parameters/list.php");
    exit();
}

$parameter = $paramResult->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description']);
    $driveLink = cleanInput($_POST['drive_link']);
    $evidenceType = cleanInput($_POST['evidence_type']);
    $filePath = '';
    
    // Validate required fields
    if (empty($title)) {
        setFlashMessage("danger", "Title is required.");
    } else {
        // Handle file upload if type is 'file'
        if ($evidenceType == 'file') {
            if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] == 0) {
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'text/plain', 'application/zip', 'application/x-rar-compressed'];
                
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($fileInfo, $_FILES['evidence_file']['tmp_name']);
                finfo_close($fileInfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    setFlashMessage("danger", "Invalid file type. Allowed types: PDF, Images, Office documents, Text, ZIP, RAR.");
                } else {
                    $uploadDir = $basePath . '../uploads/evidence/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'evidence_' . time() . '_' . uniqid() . '_' . $_FILES['evidence_file']['name'];
                    $filePath = 'evidence/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $uploadDir . $filename)) {
                        // File uploaded successfully
                    } else {
                        setFlashMessage("danger", "Error uploading file. Please try again.");
                        $filePath = '';
                    }
                }
            } else {
                setFlashMessage("danger", "Please select a file to upload.");
                header("Location: add_enhanced.php?parameter_id=" . $parameterId);
                exit();
            }
        } elseif ($evidenceType == 'drive' && empty($driveLink)) {
            setFlashMessage("danger", "Google Drive link is required.");
            header("Location: add_enhanced.php?parameter_id=" . $parameterId);
            exit();
        }
        
        // Only proceed if no errors
        if (empty($_SESSION['flash_message']) || $_SESSION['flash_message']['type'] != 'danger') {
            // Insert new evidence
            $userId = $_SESSION['admin_id'];
            
            if ($evidenceType == 'file') {
                $insertQuery = "INSERT INTO parameter_evidence (parameter_id, title, description, file_path, uploaded_by, status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("isssi", $parameterId, $title, $description, $filePath, $userId);
            } else { // drive link
                $insertQuery = "INSERT INTO parameter_evidence (parameter_id, title, description, drive_link, uploaded_by, status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("isssi", $parameterId, $title, $description, $driveLink, $userId);
            }
            
            if ($stmt->execute()) {
                // Log activity
                $activityType = "evidence_upload";
                $activityDescription = "Uploaded evidence '$title' for parameter '{$parameter['name']}'";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->bind_param("issss", $userId, $activityType, $activityDescription, $ipAddress, $userAgent);
                $logStmt->execute();
                
                setFlashMessage("success", "Evidence added successfully.");
                header("Location: list.php?parameter_id=" . $parameterId);
                exit();
            } else {
                setFlashMessage("danger", "Error adding evidence: " . $conn->error);
            }
        }
    }
}
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1>Upload Evidence</h1>
        <nav class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../programs/list.php">Programs</a></li>
                <li class="breadcrumb-item"><a href="../programs/view.php?id=<?php echo $parameter['program_id']; ?>"><?php echo htmlspecialchars($parameter['program_name']); ?></a></li>
                <li class="breadcrumb-item"><a href="../areas/view.php?id=<?php echo $parameter['area_id']; ?>"><?php echo htmlspecialchars($parameter['area_name']); ?></a></li>
                <li class="breadcrumb-item"><a href="../parameters/view.php?id=<?php echo $parameterId; ?>"><?php echo htmlspecialchars($parameter['name']); ?></a></li>
                <li class="breadcrumb-item"><a href="list.php?parameter_id=<?php echo $parameterId; ?>">Evidence</a></li>
                <li class="breadcrumb-item active">Upload Evidence</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upload Evidence for <?php echo htmlspecialchars($parameter['name']); ?></h2>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?parameter_id=" . $parameterId); ?>" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <label for="title">Evidence Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required placeholder="Enter a descriptive title for this evidence">
                        </div>
                        
                        <div class="form-row">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" placeholder="Describe the evidence and its relevance to this parameter"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <label>Evidence Type *</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="type_file" name="evidence_type" value="file" checked>
                                    <label for="type_file">Upload File</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" id="type_drive" name="evidence_type" value="drive">
                                    <label for="type_drive">Google Drive Link</label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="file_upload_section">
                            <div class="file-upload-container">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">
                                    <h3>Drag and drop file here</h3>
                                    <p>or</p>
                                </div>
                                <label for="evidence_file" class="file-upload-btn">Choose File</label>
                                <input type="file" id="evidence_file" name="evidence_file" class="file-input">
                                
                                <div class="file-preview">
                                    <div class="file-preview-item">
                                        <div class="file-preview-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="file-preview-info">
                                            <div class="file-preview-name">filename.pdf</div>
                                            <div class="file-preview-size">2.5 MB</div>
                                        </div>
                                        <div class="file-preview-remove">
                                            <i class="fas fa-times"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> Allowed file types: PDF, Images, Office documents, Text files, ZIP, RAR (Max size: 10MB)
                            </div>
                        </div>
                        
                        <div id="drive_link_section" style="display: none;">
                            <div class="drive-link-container">
                                <div class="drive-link-header">
                                    <h3><i class="fab fa-google-drive drive-icon"></i> Google Drive Link</h3>
                                </div>
                                <div class="drive-link-body">
                                    <input type="url" id="drive_link" name="drive_link" class="drive-link-input" placeholder="https://drive.google.com/file/d/...">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i> Make sure the link is publicly accessible or shared with appropriate permissions
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="list.php?parameter_id=<?php echo $parameterId; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Upload Evidence</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Parameter Information</h2>
                </div>
                <div class="card-body">
                    <div class="info-group">
                        <label>Parameter:</label>
                        <span><?php echo htmlspecialchars($parameter['name']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Area:</label>
                        <span><?php echo htmlspecialchars($parameter['area_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Program:</label>
                        <span><?php echo htmlspecialchars($parameter['program_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Weight:</label>
                        <span><?php echo $parameter['weight']; ?></span>
                    </div>
                    <div class="info-group">
                        <label>Description:</label>
                        <p><?php echo nl2br(htmlspecialchars($parameter['description'])); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2 class="card-title">Upload Guidelines</h2>
                </div>
                <div class="card-body">
                    <div class="guidelines">
                        <div class="guideline-item">
                            <i class="fas fa-file-pdf"></i>
                            <div class="guideline-text">
                                <h4>Preferred Formats</h4>
                                <p>Upload PDFs when possible for best compatibility.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <i class="fas fa-file-image"></i>
                            <div class="guideline-text">
                                <h4>Images & Scans</h4>
                                <p>Ensure images are clear and legible.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <i class="fas fa-link"></i>
                            <div class="guideline-text">
                                <h4>Drive Links</h4>
                                <p>Ensure links are set to "Anyone with the link can view".</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <i class="fas fa-check-circle"></i>
                            <div class="guideline-text">
                                <h4>Approval Process</h4>
                                <p>Uploads require approval from administrators.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload drag and drop functionality
    const uploadContainer = document.querySelector('.file-upload-container');
    const fileInput = document.getElementById('evidence_file');
    
    if (uploadContainer && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadContainer.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadContainer.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadContainer.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadContainer.classList.add('highlight');
        }
        
        function unhighlight() {
            uploadContainer.classList.remove('highlight');
        }
        
        uploadContainer.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                fileInput.files = files;
                updateFilePreview(files[0]);
            }
        }
        
        // Handle manual file selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                updateFilePreview(this.files[0]);
            }
        });
        
        function updateFilePreview(file) {
            const filePreview = document.querySelector('.file-preview');
            const filePreviewName = document.querySelector('.file-preview-name');
            const filePreviewSize = document.querySelector('.file-preview-size');
            const filePreviewIcon = document.querySelector('.file-preview-icon i');
            
            filePreview.style.display = 'block';
            filePreviewName.textContent = file.name;
            
            // Format file size
            let fileSize = (file.size / 1024).toFixed(2) + ' KB';
            if (file.size > 1024 * 1024) {
                fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            filePreviewSize.textContent = fileSize;
            
            // Set icon based on file type
            if (file.type.includes('image')) {
                filePreviewIcon.className = 'fas fa-file-image';
            } else if (file.type.includes('pdf')) {
                filePreviewIcon.className = 'fas fa-file-pdf';
            } else if (file.type.includes('word')) {
                filePreviewIcon.className = 'fas fa-file-word';
            } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
                filePreviewIcon.className = 'fas fa-file-excel';
            } else if (file.type.includes('powerpoint') || file.type.includes('presentation')) {
                filePreviewIcon.className = 'fas fa-file-powerpoint';
            } else if (file.type.includes('zip') || file.type.includes('rar') || file.type.includes('archive')) {
                filePreviewIcon.className = 'fas fa-file-archive';
            } else {
                filePreviewIcon.className = 'fas fa-file-alt';
            }
        }
        
        // Remove file button
        const removeFileBtn = document.querySelector('.file-preview-remove');
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function() {
                fileInput.value = '';
                document.querySelector('.file-preview').style.display = 'none';
            });
        }
    }
    
    // Toggle evidence type
    const typeFileRadio = document.getElementById('type_file');
    const typeDriveRadio = document.getElementById('type_drive');
    const fileUploadSection = document.getElementById('file_upload_section');
    const driveLinkSection = document.getElementById('drive_link_section');
    
    if (typeFileRadio && typeDriveRadio && fileUploadSection && driveLinkSection) {
        typeFileRadio.addEventListener('change', function() {
            if (this.checked) {
                fileUploadSection.style.display = 'block';
                driveLinkSection.style.display = 'none';
            }
        });
        
        typeDriveRadio.addEventListener('change', function() {
            if (this.checked) {
                fileUploadSection.style.display = 'none';
                driveLinkSection.style.display = 'block';
            }
        });
    }
});
</script>

<style>
.row {
    display: flex;
    flex-wrap: wrap;
    margin: -10px;
}

.col-lg-8, .col-lg-4 {
    padding: 10px;
    box-sizing: border-box;
}

.col-lg-8 {
    width: 66.66667%;
}

.col-lg-4 {
    width: 33.33333%;
}

@media (max-width: 992px) {
    .col-lg-8, .col-lg-4 {
        width: 100%;
    }
}

.file-upload-container {
    border: 2px dashed var(--border-color);
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 20px;
    background-color: #f9fafb;
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-container.highlight {
    border-color: var(--accent-color);
    background-color: rgba(74, 144, 226, 0.05);
}

.guideline-item {
    display: flex;
    margin-bottom: 15px;
    align-items: flex-start;
}

.guideline-item i {
    color: var(--accent-color);
    font-size: 24px;
    margin-right: 15px;
    min-width: 24px;
}

.guideline-text h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: var(--primary-color);
}

.guideline-text p {
    margin: 0;
    font-size: 13px;
    color: var(--dark-gray);
}

.form-hint {
    font-size: 12px;
    color: var(--dark-gray);
    margin-top: 5px;
    margin-bottom: 20px;
}

.mt-4 {
    margin-top: 20px;
}
</style>

<?php include $basePath . 'includes/footer.php'; ?> 