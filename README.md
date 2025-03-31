# CCS Accreditation System

A comprehensive web-based system for managing college accreditation processes, specifically designed for the College of Computer Studies (CCS).

## Features

- **Role-Based Access Control**
  - Super Admin, Admin, and User roles
  - Granular permission management
  - Custom role creation and management

- **Program Management**
  - View and manage academic programs
  - Track program accreditation status
  - Manage program-specific documentation

- **Area Level Management**
  - Organize accreditation areas
  - Track progress by area
  - Manage area-specific requirements

- **Parameter Management**
  - Define and manage accreditation parameters
  - Track parameter compliance
  - Link parameters to evidence

- **Evidence Management**
  - Upload and organize supporting documents
  - Track evidence status
  - Link evidence to specific parameters

- **User Management**
  - Manage system users
  - Assign roles and permissions
  - Track user activities

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP (recommended for local development)

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone [repository-url]
   ```

2. Import the database:
   - Create a new MySQL database
   - Import the `database.sql` file from the project root

3. Configure the database connection:
   - Navigate to `includes/config.php`
   - Update the database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'your_database');
     ```

4. Set up the web server:
   - For XAMPP: Copy the project to `htdocs` directory
   - For Apache: Configure the virtual host
   - Ensure proper permissions on upload directories

## Directory Structure

```
admin/
├── modules/
│   ├── areas/         # Area level management
│   ├── evidence/      # Evidence management
│   ├── parameters/    # Parameter management
│   ├── programs/      # Program management
│   ├── roles/         # Role & permission management
│   ├── settings/      # System settings
│   └── users/         # User management
├── includes/
│   ├── config.php     # Database & system configuration
│   ├── functions.php  # Helper functions
│   ├── header.php     # Common header template
│   └── footer.php     # Common footer template
├── assets/
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript files
│   └── images/       # System images
└── uploads/          # File upload directory
```

## Security Features

- Password hashing using modern algorithms
- Session management and protection
- CSRF protection
- Input validation and sanitization
- Role-based access control
- Activity logging

## Usage

1. Access the system through your web browser
2. Log in with your credentials
3. Navigate through the modules using the sidebar
4. Manage permissions through the role management interface
5. Upload and organize evidence as needed
6. Track accreditation progress through the dashboard

## Default Credentials

```
Super Admin:
Username: admin
Password: admin123

Note: Change these credentials immediately after first login
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and queries, please contact:
- Email: [support-email]
- Phone: [support-phone]

## Acknowledgments

- EARIST College of Computer Studies
- Accreditation Committee Members
- Development Team

## Version History

- v1.0.0 - Initial Release
  - Basic functionality
  - Role management
  - Evidence management

## Roadmap

- Enhanced reporting features
- Mobile application
- API integration
- Automated backups
- Enhanced analytics 