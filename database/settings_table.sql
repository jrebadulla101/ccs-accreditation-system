-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'CCS Accreditation System'),
('site_description', 'Manage accreditation for programs and institutions'),
('admin_email', 'admin@example.com'),
('date_format', 'F j, Y'),
('time_format', 'g:i a'),
('primary_color', '#4A90E2'),
('accent_color', '#34C759'),
('sidebar_style', 'default'),
('enable_particles', '1'),
('enable_email_notifications', '0'),
('email_from', 'noreply@example.com'),
('email_from_name', 'CCS Accreditation System'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('session_timeout', '30'),
('max_login_attempts', '5'),
('password_policy', 'medium'),
('enable_2fa', '0'),
('require_password_change', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value); 