-- Create database
CREATE DATABASE IF NOT EXISTS ccs_accreditation;
USE ccs_accreditation;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('super_admin', 'admin', 'reviewer') NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$qeS2aVXZYiXLPFKs2ClZQ.gFhzXR.V5hPFKzwlECxlG5dqKQdgKrO', 'CCS Administrator', 'admin@earist.edu.ph', 'super_admin');

-- Institutions table
CREATE TABLE IF NOT EXISTS institutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    address TEXT,
    contact_person VARCHAR(100),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    status ENUM('active', 'inactive', 'under_review', 'accredited') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Accreditation standards table
CREATE TABLE IF NOT EXISTS standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    standard_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    weight INT DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Criteria table (sub-standards)
CREATE TABLE IF NOT EXISTS criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    standard_id INT NOT NULL,
    criteria_code VARCHAR(20) NOT NULL,
    description TEXT NOT NULL,
    max_points INT NOT NULL DEFAULT 5,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE CASCADE
);

-- Evidence requirements table
CREATE TABLE IF NOT EXISTS evidence_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criteria_id INT NOT NULL,
    description TEXT NOT NULL,
    required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE
);

-- Accreditation applications table
CREATE TABLE IF NOT EXISTS accreditation_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT NOT NULL,
    application_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected') DEFAULT 'draft',
    submission_date DATETIME,
    review_deadline DATETIME,
    decision_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id)
);

-- Reviewer assignments table
CREATE TABLE IF NOT EXISTS reviewer_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES accreditation_applications(id),
    FOREIGN KEY (reviewer_id) REFERENCES admin_users(id)
);

-- Evidence submissions table
CREATE TABLE IF NOT EXISTS evidence_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    criteria_id INT NOT NULL,
    file_path VARCHAR(255),
    description TEXT,
    submitted_by INT,
    submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewer_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES accreditation_applications(id),
    FOREIGN KEY (criteria_id) REFERENCES criteria(id),
    FOREIGN KEY (submitted_by) REFERENCES admin_users(id)
);

-- Evaluation scores table
CREATE TABLE IF NOT EXISTS evaluation_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    criteria_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    score DECIMAL(5,2),
    comments TEXT,
    evaluation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES accreditation_applications(id),
    FOREIGN KEY (criteria_id) REFERENCES criteria(id),
    FOREIGN KEY (reviewer_id) REFERENCES admin_users(id)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id)
);

-- Programs/Courses table
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Area Levels table
CREATE TABLE IF NOT EXISTS area_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

-- Parameters table
CREATE TABLE IF NOT EXISTS parameters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_level_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    weight DECIMAL(5,2) DEFAULT 1.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_level_id) REFERENCES area_levels(id) ON DELETE CASCADE
);

-- Parameter Evidence table for file uploads and links
CREATE TABLE IF NOT EXISTS parameter_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    drive_link VARCHAR(255),
    uploaded_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parameter_id) REFERENCES parameters(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id)
);

-- User roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES 
('super_admin', 'Full access to all system features'),
('admin', 'Administrative access with some restrictions'),
('program_manager', 'Can manage specific programs and their evaluation'),
('evaluator', 'Can evaluate assigned programs and parameters'),
('viewer', 'Can only view information without making changes');

-- User-role assignments
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id)
);

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert comprehensive permission set
INSERT INTO permissions (name, description) VALUES
-- Program permissions
('view_all_programs', 'Can view all programs in the system'),
('view_assigned_programs', 'Can only view programs assigned to the user'),
('add_program', 'Can add new programs'),
('edit_program', 'Can edit program details'),
('delete_program', 'Can delete programs'),

-- Area permissions
('view_all_areas', 'Can view all areas in the system'),
('view_assigned_areas', 'Can only view areas within assigned programs'),
('add_area', 'Can add new area levels'),
('edit_area', 'Can edit area details'),
('delete_area', 'Can delete areas'),

-- Parameter permissions
('view_all_parameters', 'Can view all parameters in the system'),
('view_assigned_parameters', 'Can only view parameters within assigned areas'),
('add_parameter', 'Can add new parameters'),
('edit_parameter', 'Can edit parameter details'),
('delete_parameter', 'Can delete parameters'),

-- Evidence permissions
('view_all_evidence', 'Can view all evidence items in the system'),
('view_assigned_evidence', 'Can only view evidence for assigned parameters'),
('add_evidence', 'Can upload evidence files or links'),
('edit_evidence', 'Can edit evidence details'),
('delete_evidence', 'Can delete evidence'),
('download_evidence', 'Can download evidence files'),

-- Approval permissions
('approve_evidence', 'Can approve or reject evidence submissions'),
('view_pending_evidence', 'Can view pending evidence submissions'),

-- User management permissions
('view_users', 'Can view system users'),
('add_user', 'Can add new users'),
('edit_user', 'Can edit user details'),
('delete_user', 'Can delete users'),
('assign_roles', 'Can assign roles to users'),

-- Program assignment permissions
('assign_programs', 'Can assign programs to users'),
('assign_areas', 'Can assign areas to users'),
('assign_parameters', 'Can assign parameters to users'),

-- Role management permissions
('view_roles', 'Can view system roles'),
('add_role', 'Can add new roles'),
('edit_role', 'Can edit role details'),
('delete_role', 'Can delete roles'),
('manage_permissions', 'Can manage role permissions'),

-- Report permissions
('view_reports', 'Can view accreditation reports'),
('generate_reports', 'Can generate new reports'),
('export_reports', 'Can export reports to different formats'),

-- System permissions
('manage_settings', 'Can manage system settings'),
('view_logs', 'Can view system logs and activity'),
('backup_system', 'Can create and restore system backups');

-- Role-permission assignments
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE(role_id, permission_id)
);

-- Insert default role-permission assignments
INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'super_admin';

-- Program user assignments (for program managers)
CREATE TABLE IF NOT EXISTS program_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    UNIQUE(program_id, user_id)
);

-- Create a table for area-specific permissions
CREATE TABLE IF NOT EXISTS area_user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    area_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_add BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_download BOOLEAN DEFAULT FALSE,
    can_approve BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES area_levels(id) ON DELETE CASCADE,
    UNIQUE(user_id, area_id)
);

-- Create a table for parameter-specific permissions for even more granularity
CREATE TABLE IF NOT EXISTS parameter_user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    parameter_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_add BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_download BOOLEAN DEFAULT FALSE,
    can_approve BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (parameter_id) REFERENCES parameters(id) ON DELETE CASCADE,
    UNIQUE(user_id, parameter_id)
);

-- Sub-parameters table
CREATE TABLE IF NOT EXISTS sub_parameters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    weight DECIMAL(5,2) DEFAULT 1.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parameter_id) REFERENCES parameters(id) ON DELETE CASCADE
);

-- Sub-parameter permissions table
CREATE TABLE IF NOT EXISTS sub_parameter_user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sub_parameter_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_add BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_download BOOLEAN DEFAULT FALSE,
    can_approve BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_parameter_id) REFERENCES sub_parameters(id) ON DELETE CASCADE,
    UNIQUE(user_id, sub_parameter_id)
); 