-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2025 at 05:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ccs_accreditation`
--

-- --------------------------------------------------------

--
-- Table structure for table `accreditation_applications`
--

CREATE TABLE `accreditation_applications` (
  `id` int(11) NOT NULL,
  `institution_id` int(11) NOT NULL,
  `application_code` varchar(20) NOT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected') DEFAULT 'draft',
  `submission_date` datetime DEFAULT NULL,
  `review_deadline` datetime DEFAULT NULL,
  `decision_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','reviewer') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$q6CMAqvJuiufErJOmCa2m.tt63Ay6V/aSrpbHeb0orz5FheRbCVZ.', 'Administrator', 'admin@example.com', 'super_admin', 'active', '2025-03-20 02:51:08', '2025-03-20 02:51:08');

-- --------------------------------------------------------

--
-- Table structure for table `area_levels`
--

CREATE TABLE `area_levels` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `area_user_permissions`
--

CREATE TABLE `area_user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_download` tinyint(1) DEFAULT 0,
  `can_approve` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `criteria`
--

CREATE TABLE `criteria` (
  `id` int(11) NOT NULL,
  `standard_id` int(11) NOT NULL,
  `criteria_code` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `max_points` int(11) NOT NULL DEFAULT 5,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_scores`
--

CREATE TABLE `evaluation_scores` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `evaluation_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence_requirements`
--

CREATE TABLE `evidence_requirements` (
  `id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `required` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence_submissions`
--

CREATE TABLE `evidence_submissions` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewer_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

CREATE TABLE `institutions` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','under_review','accredited') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parameters`
--

CREATE TABLE `parameters` (
  `id` int(11) NOT NULL,
  `area_level_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 1.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parameter_evidence`
--

CREATE TABLE `parameter_evidence` (
  `id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `drive_link` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sub_parameter_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parameter_user_permissions`
--

CREATE TABLE `parameter_user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_download` tinyint(1) DEFAULT 0,
  `can_approve` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'view_all_programs', 'Can view all programs in the system', '2025-03-20 02:45:08'),
(2, 'view_assigned_programs', 'Can only view programs assigned to the user', '2025-03-20 02:45:08'),
(3, 'add_program', 'Can add new programs', '2025-03-20 02:45:08'),
(4, 'edit_program', 'Can edit program details', '2025-03-20 02:45:08'),
(5, 'delete_program', 'Can delete programs', '2025-03-20 02:45:08'),
(6, 'view_all_areas', 'Can view all areas in the system', '2025-03-20 02:45:08'),
(7, 'view_assigned_areas', 'Can only view areas within assigned programs', '2025-03-20 02:45:08'),
(8, 'add_area', 'Can add new area levels', '2025-03-20 02:45:08'),
(9, 'edit_area', 'Can edit area details', '2025-03-20 02:45:08'),
(10, 'delete_area', 'Can delete areas', '2025-03-20 02:45:08'),
(11, 'view_all_parameters', 'Can view all parameters in the system', '2025-03-20 02:45:08'),
(12, 'view_assigned_parameters', 'Can only view parameters within assigned areas', '2025-03-20 02:45:08'),
(13, 'add_parameter', 'Can add new parameters', '2025-03-20 02:45:08'),
(14, 'edit_parameter', 'Can edit parameter details', '2025-03-20 02:45:08'),
(15, 'delete_parameter', 'Can delete parameters', '2025-03-20 02:45:08'),
(16, 'view_all_evidence', 'Can view all evidence items in the system', '2025-03-20 02:45:08'),
(17, 'view_assigned_evidence', 'Can only view evidence for assigned parameters', '2025-03-20 02:45:08'),
(18, 'add_evidence', 'Can upload evidence files or links', '2025-03-20 02:45:08'),
(19, 'edit_evidence', 'Can edit evidence details', '2025-03-20 02:45:08'),
(20, 'delete_evidence', 'Can delete evidence', '2025-03-20 02:45:08'),
(21, 'download_evidence', 'Can download evidence files', '2025-03-20 02:45:08'),
(22, 'approve_evidence', 'Can approve or reject evidence submissions', '2025-03-20 02:45:08'),
(23, 'view_pending_evidence', 'Can view pending evidence submissions', '2025-03-20 02:45:08'),
(24, 'view_users', 'Can view system users', '2025-03-20 02:45:08'),
(25, 'add_user', 'Can add new users', '2025-03-20 02:45:08'),
(26, 'edit_user', 'Can edit user details', '2025-03-20 02:45:08'),
(27, 'delete_user', 'Can delete users', '2025-03-20 02:45:08'),
(28, 'assign_roles', 'Can assign roles to users', '2025-03-20 02:45:08'),
(29, 'assign_programs', 'Can assign programs to users', '2025-03-20 02:45:08'),
(30, 'assign_areas', 'Can assign areas to users', '2025-03-20 02:45:08'),
(31, 'assign_parameters', 'Can assign parameters to users', '2025-03-20 02:45:08'),
(32, 'view_roles', 'Can view system roles', '2025-03-20 02:45:08'),
(33, 'add_role', 'Can add new roles', '2025-03-20 02:45:08'),
(34, 'edit_role', 'Can edit role details', '2025-03-20 02:45:08'),
(35, 'delete_role', 'Can delete roles', '2025-03-20 02:45:08'),
(36, 'manage_permissions', 'Can manage role permissions', '2025-03-20 02:45:08'),
(37, 'view_reports', 'Can view accreditation reports', '2025-03-20 02:45:08'),
(38, 'generate_reports', 'Can generate new reports', '2025-03-20 02:45:08'),
(39, 'export_reports', 'Can export reports to different formats', '2025-03-20 02:45:08'),
(40, 'manage_settings', 'Can manage system settings', '2025-03-20 02:45:08'),
(41, 'view_logs', 'Can view system logs and activity', '2025-03-20 02:45:08'),
(42, 'backup_system', 'Can create and restore system backups', '2025-03-20 02:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_users`
--

CREATE TABLE `program_users` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_assignments`
--

CREATE TABLE `reviewer_assignments` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `assigned_date` datetime DEFAULT current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'Full access to all system features', '2025-03-20 02:45:08', '2025-03-20 02:45:08'),
(2, 'admin', 'Administrative access with some restrictions', '2025-03-20 02:45:08', '2025-03-20 02:45:08'),
(3, 'program_manager', 'Can manage specific programs and their evaluation', '2025-03-20 02:45:08', '2025-03-20 02:45:08'),
(4, 'evaluator', 'Can evaluate assigned programs and parameters', '2025-03-20 02:45:08', '2025-03-20 02:45:08'),
(5, 'viewer', 'Can only view information without making changes', '2025-03-20 02:45:08', '2025-03-20 02:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 8, '2025-03-20 02:45:08'),
(2, 1, 18, '2025-03-20 02:45:08'),
(3, 1, 13, '2025-03-20 02:45:08'),
(4, 1, 3, '2025-03-20 02:45:08'),
(5, 1, 33, '2025-03-20 02:45:08'),
(6, 1, 25, '2025-03-20 02:45:08'),
(7, 1, 22, '2025-03-20 02:45:08'),
(8, 1, 30, '2025-03-20 02:45:08'),
(9, 1, 31, '2025-03-20 02:45:08'),
(10, 1, 29, '2025-03-20 02:45:08'),
(11, 1, 28, '2025-03-20 02:45:08'),
(12, 1, 42, '2025-03-20 02:45:08'),
(13, 1, 10, '2025-03-20 02:45:08'),
(14, 1, 20, '2025-03-20 02:45:08'),
(15, 1, 15, '2025-03-20 02:45:08'),
(16, 1, 5, '2025-03-20 02:45:08'),
(17, 1, 35, '2025-03-20 02:45:08'),
(18, 1, 27, '2025-03-20 02:45:08'),
(19, 1, 21, '2025-03-20 02:45:08'),
(20, 1, 9, '2025-03-20 02:45:08'),
(21, 1, 19, '2025-03-20 02:45:08'),
(22, 1, 14, '2025-03-20 02:45:08'),
(23, 1, 4, '2025-03-20 02:45:08'),
(24, 1, 34, '2025-03-20 02:45:08'),
(25, 1, 26, '2025-03-20 02:45:08'),
(26, 1, 39, '2025-03-20 02:45:08'),
(27, 1, 38, '2025-03-20 02:45:08'),
(28, 1, 36, '2025-03-20 02:45:08'),
(29, 1, 40, '2025-03-20 02:45:08'),
(30, 1, 6, '2025-03-20 02:45:08'),
(31, 1, 16, '2025-03-20 02:45:08'),
(32, 1, 11, '2025-03-20 02:45:08'),
(33, 1, 1, '2025-03-20 02:45:08'),
(34, 1, 7, '2025-03-20 02:45:08'),
(35, 1, 17, '2025-03-20 02:45:08'),
(36, 1, 12, '2025-03-20 02:45:08'),
(37, 1, 2, '2025-03-20 02:45:08'),
(38, 1, 41, '2025-03-20 02:45:08'),
(39, 1, 23, '2025-03-20 02:45:08'),
(40, 1, 37, '2025-03-20 02:45:08'),
(41, 1, 32, '2025-03-20 02:45:08'),
(42, 1, 24, '2025-03-20 02:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'CCS Accreditation System', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(2, 'site_description', 'Manage accreditation for programs and institutions', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(3, 'admin_email', 'admin@example.com', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(4, 'date_format', 'F j, Y', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(5, 'time_format', 'g:i a', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(6, 'primary_color', '#4A90E2', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(7, 'accent_color', '#34C759', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(8, 'sidebar_style', 'default', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(9, 'enable_particles', '1', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(10, 'enable_email_notifications', '0', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(11, 'email_from', 'noreply@example.com', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(12, 'email_from_name', 'CCS Accreditation System', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(13, 'smtp_host', '', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(14, 'smtp_port', '587', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(15, 'smtp_encryption', 'tls', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(16, 'smtp_username', '', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(17, 'smtp_password', '', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(18, 'session_timeout', '30', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(19, 'max_login_attempts', '5', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(20, 'password_policy', 'medium', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(21, 'enable_2fa', '0', '2025-03-20 03:41:13', '2025-03-20 03:41:13'),
(22, 'require_password_change', '1', '2025-03-20 03:41:13', '2025-03-20 03:41:13');

-- --------------------------------------------------------

--
-- Table structure for table `standards`
--

CREATE TABLE `standards` (
  `id` int(11) NOT NULL,
  `standard_code` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `weight` int(11) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sub_parameters`
--

CREATE TABLE `sub_parameters` (
  `id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 1.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sub_parameter_user_permissions`
--

CREATE TABLE `sub_parameter_user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sub_parameter_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_download` tinyint(1) DEFAULT 0,
  `can_approve` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accreditation_applications`
--
ALTER TABLE `accreditation_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_code` (`application_code`),
  ADD KEY `institution_id` (`institution_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `area_levels`
--
ALTER TABLE `area_levels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `area_user_permissions`
--
ALTER TABLE `area_user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`area_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `standard_id` (`standard_id`);

--
-- Indexes for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `criteria_id` (`criteria_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `evidence_requirements`
--
ALTER TABLE `evidence_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criteria_id` (`criteria_id`);

--
-- Indexes for table `evidence_submissions`
--
ALTER TABLE `evidence_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `criteria_id` (`criteria_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parameters`
--
ALTER TABLE `parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_level_id` (`area_level_id`);

--
-- Indexes for table `parameter_evidence`
--
ALTER TABLE `parameter_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parameter_id` (`parameter_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `parameter_user_permissions`
--
ALTER TABLE `parameter_user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`parameter_id`),
  ADD KEY `parameter_id` (`parameter_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `program_users`
--
ALTER TABLE `program_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_id` (`program_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reviewer_assignments`
--
ALTER TABLE `reviewer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_id` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `standards`
--
ALTER TABLE `standards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sub_parameters`
--
ALTER TABLE `sub_parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parameter_id` (`parameter_id`);

--
-- Indexes for table `sub_parameter_user_permissions`
--
ALTER TABLE `sub_parameter_user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`sub_parameter_id`),
  ADD KEY `sub_parameter_id` (`sub_parameter_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accreditation_applications`
--
ALTER TABLE `accreditation_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `area_levels`
--
ALTER TABLE `area_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `area_user_permissions`
--
ALTER TABLE `area_user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence_requirements`
--
ALTER TABLE `evidence_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence_submissions`
--
ALTER TABLE `evidence_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parameters`
--
ALTER TABLE `parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parameter_evidence`
--
ALTER TABLE `parameter_evidence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parameter_user_permissions`
--
ALTER TABLE `parameter_user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_users`
--
ALTER TABLE `program_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviewer_assignments`
--
ALTER TABLE `reviewer_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `standards`
--
ALTER TABLE `standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sub_parameters`
--
ALTER TABLE `sub_parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sub_parameter_user_permissions`
--
ALTER TABLE `sub_parameter_user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accreditation_applications`
--
ALTER TABLE `accreditation_applications`
  ADD CONSTRAINT `accreditation_applications_ibfk_1` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`);

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `area_levels`
--
ALTER TABLE `area_levels`
  ADD CONSTRAINT `area_levels_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `area_user_permissions`
--
ALTER TABLE `area_user_permissions`
  ADD CONSTRAINT `area_user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `area_user_permissions_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `area_levels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `criteria_ibfk_1` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD CONSTRAINT `evaluation_scores_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `accreditation_applications` (`id`),
  ADD CONSTRAINT `evaluation_scores_ibfk_2` FOREIGN KEY (`criteria_id`) REFERENCES `criteria` (`id`),
  ADD CONSTRAINT `evaluation_scores_ibfk_3` FOREIGN KEY (`reviewer_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `evidence_requirements`
--
ALTER TABLE `evidence_requirements`
  ADD CONSTRAINT `evidence_requirements_ibfk_1` FOREIGN KEY (`criteria_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evidence_submissions`
--
ALTER TABLE `evidence_submissions`
  ADD CONSTRAINT `evidence_submissions_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `accreditation_applications` (`id`),
  ADD CONSTRAINT `evidence_submissions_ibfk_2` FOREIGN KEY (`criteria_id`) REFERENCES `criteria` (`id`),
  ADD CONSTRAINT `evidence_submissions_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `parameters`
--
ALTER TABLE `parameters`
  ADD CONSTRAINT `parameters_ibfk_1` FOREIGN KEY (`area_level_id`) REFERENCES `area_levels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parameter_evidence`
--
ALTER TABLE `parameter_evidence`
  ADD CONSTRAINT `parameter_evidence_ibfk_1` FOREIGN KEY (`parameter_id`) REFERENCES `parameters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parameter_evidence_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `parameter_user_permissions`
--
ALTER TABLE `parameter_user_permissions`
  ADD CONSTRAINT `parameter_user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parameter_user_permissions_ibfk_2` FOREIGN KEY (`parameter_id`) REFERENCES `parameters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_users`
--
ALTER TABLE `program_users`
  ADD CONSTRAINT `program_users_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviewer_assignments`
--
ALTER TABLE `reviewer_assignments`
  ADD CONSTRAINT `reviewer_assignments_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `accreditation_applications` (`id`),
  ADD CONSTRAINT `reviewer_assignments_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_parameters`
--
ALTER TABLE `sub_parameters`
  ADD CONSTRAINT `sub_parameters_ibfk_1` FOREIGN KEY (`parameter_id`) REFERENCES `parameters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_parameter_user_permissions`
--
ALTER TABLE `sub_parameter_user_permissions`
  ADD CONSTRAINT `sub_parameter_user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_parameter_user_permissions_ibfk_2` FOREIGN KEY (`sub_parameter_id`) REFERENCES `sub_parameters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
