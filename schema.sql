-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 24, 2026 at 09:18 AM
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
-- Database: `fsuu_dental_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `notes` text DEFAULT NULL,
  `consent_agreed` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `service_id`, `appointment_date`, `appointment_time`, `notes`, `consent_agreed`, `status`, `cancellation_reason`, `cancelled_at`, `created_at`) VALUES
(4, 5, 1, '2026-04-02', '13:00:00', '', 1, '', 'Conflict schedule with other students who book first', NULL, '2026-04-01 21:51:43'),
(5, 5, 2, '2026-04-02', '13:00:00', '', 1, 'completed', '', NULL, '2026-04-02 06:55:12'),
(6, 11, 4, '2026-04-23', '14:30:00', '', 1, 'completed', '', NULL, '2026-04-06 14:46:55'),
(7, 6, 1, '2026-04-08', '13:00:00', '', 1, 'approved', '', NULL, '2026-04-06 21:38:22'),
(8, 6, 3, '2026-04-10', '13:00:00', '', 1, 'approved', '', NULL, '2026-04-06 21:44:56'),
(9, 6, 4, '2026-04-11', '09:00:00', '', 1, 'approved', '', NULL, '2026-04-09 07:34:59'),
(10, 6, 3, '2026-04-13', '15:00:00', '', 1, 'approved', '', NULL, '2026-04-09 07:37:22'),
(11, 10, 3, '2026-04-10', '15:00:00', '', 1, '', 'dentist off duty', NULL, '2026-04-09 11:02:21'),
(12, 10, 1, '2026-04-13', '13:00:00', '', 1, 'approved', NULL, NULL, '2026-04-09 11:45:48'),
(13, 6, 1, '2026-04-15', '09:00:00', '', 1, 'completed', NULL, NULL, '2026-04-14 21:22:11'),
(14, 6, 1, '2026-04-16', '09:00:00', '', 1, 'completed', NULL, NULL, '2026-04-14 22:26:46'),
(15, 6, 2, '2026-04-17', '09:00:00', '', 1, 'completed', NULL, NULL, '2026-04-14 23:08:17'),
(16, 6, 1, '2026-04-18', '09:00:00', '', 1, 'completed', NULL, NULL, '2026-04-15 15:41:35'),
(17, 10, 4, '2026-04-17', '09:00:00', '', 1, 'completed', NULL, NULL, '2026-04-15 18:59:32'),
(18, 10, 3, '2026-04-20', '09:00:00', '', 1, 'approved', NULL, NULL, '2026-04-15 20:51:20'),
(19, 6, 3, '2026-04-20', '09:30:00', '', 1, 'approved', NULL, NULL, '2026-04-15 20:52:51'),
(20, 10, 1, '2026-04-17', '09:00:00', '', 1, 'approved', NULL, NULL, '2026-04-16 07:28:52'),
(21, 6, 4, '2026-04-17', '09:30:00', '', 1, 'approved', NULL, NULL, '2026-04-16 07:34:11'),
(22, 5, 1, '2026-04-17', '15:00:00', '', 1, 'completed', NULL, NULL, '2026-04-17 13:37:25'),
(23, 6, 2, '2026-04-18', '08:00:00', '', 1, 'completed', NULL, NULL, '2026-04-17 13:52:42'),
(24, 11, 1, '2026-04-21', '13:30:00', '', 1, 'approved', NULL, NULL, '2026-04-20 15:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reminders`
--

CREATE TABLE `appointment_reminders` (
  `reminder_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `reminder_hours` int(11) NOT NULL,
  `delivery_status` varchar(20) NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_reminders`
--

INSERT INTO `appointment_reminders` (`reminder_id`, `appointment_id`, `reminder_hours`, `delivery_status`, `error_message`, `sent_at`) VALUES
(1, 20, 24, 'sent', NULL, '2026-04-16 09:00:12'),
(2, 21, 24, 'sent', NULL, '2026-04-16 09:31:13'),
(3, 22, 24, 'sent', NULL, '2026-04-17 13:39:17'),
(4, 23, 24, 'sent', NULL, '2026-04-17 13:54:41'),
(5, 24, 24, 'sent', NULL, '2026-04-20 15:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, NULL, 'book_appointment', 'Booked appointment #1 for 2026-03-31 at 13:00:00', '::1', '2026-03-30 18:55:16'),
(2, NULL, 'book_appointment', 'Booked appointment #2 for 2026-04-01 at 13:00:00', '::1', '2026-03-31 20:16:18'),
(3, NULL, 'book_appointment', 'Booked appointment #3 for 2026-04-02 at 13:00:00', '::1', '2026-04-01 21:48:45'),
(4, 5, 'book_appointment', 'Booked appointment #4 for 2026-04-02 at 13:00:00', '::1', '2026-04-01 21:51:43'),
(5, 5, 'book_appointment', 'Booked appointment #5 for 2026-04-02 at 13:00:00', '::1', '2026-04-02 06:55:12'),
(6, 11, 'book_appointment', 'Booked appointment #6 for 2026-04-23 at 14:30:00', '::1', '2026-04-06 14:46:55'),
(7, 6, 'book_appointment', 'Booked appointment #7 for 2026-04-08 at 13:00:00', '::1', '2026-04-06 21:38:22'),
(8, 6, 'book_appointment', 'Booked appointment #8 for 2026-04-10 at 13:00:00', '::1', '2026-04-06 21:44:56'),
(9, 6, 'book_appointment', 'Booked appointment #9 for 2026-04-11 at 09:00:00', '::1', '2026-04-09 07:34:59'),
(10, 6, 'book_appointment', 'Booked appointment #10 for 2026-04-13 at 15:00:00', '::1', '2026-04-09 07:37:22'),
(11, 10, 'book_appointment', 'Booked appointment #11 for 2026-04-10 at 15:00:00', '::1', '2026-04-09 11:02:21'),
(12, 10, 'book_appointment', 'Booked appointment #12 for 2026-04-13 at 13:00:00', '::1', '2026-04-09 11:45:48'),
(13, 6, 'book_appointment', 'Booked appointment #13 for 2026-04-15 at 09:00:00', '::1', '2026-04-14 21:22:11'),
(14, 6, 'book_appointment', 'Booked appointment #14 for 2026-04-16 at 09:00:00', '::1', '2026-04-14 22:26:46'),
(15, 6, 'book_appointment', 'Booked appointment #15 for 2026-04-17 at 09:00:00', '::1', '2026-04-14 23:08:17'),
(16, 6, 'book_appointment', 'Booked appointment #16 for 2026-04-18 at 09:00:00', '::1', '2026-04-15 15:41:35'),
(17, 10, 'book_appointment', 'Booked appointment #17 for 2026-04-17 at 09:00:00', '::1', '2026-04-15 18:59:32'),
(18, 10, 'book_appointment', 'Booked appointment #18 for 2026-04-20 at 09:00:00', '::1', '2026-04-15 20:51:20'),
(19, 6, 'book_appointment', 'Booked appointment #19 for 2026-04-20 at 09:30:00', '::1', '2026-04-15 20:52:51'),
(20, 10, 'book_appointment', 'Booked appointment #20 for 2026-04-17 at 09:00:00', '::1', '2026-04-16 07:28:52'),
(21, 6, 'book_appointment', 'Booked appointment #21 for 2026-04-17 at 09:30:00', '::1', '2026-04-16 07:34:11'),
(22, 5, 'book_appointment', 'Booked appointment #22 for 2026-04-17 at 15:00:00', '::1', '2026-04-17 13:37:25'),
(23, 6, 'book_appointment', 'Booked appointment #23 for 2026-04-18 at 08:00:00', '::1', '2026-04-17 13:52:42'),
(24, 11, 'book_appointment', 'Booked appointment #24 for 2026-04-21 at 13:30:00', '::1', '2026-04-20 15:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_schedules`
--

CREATE TABLE `blocked_schedules` (
  `block_id` int(11) NOT NULL,
  `block_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_full_day` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocked_schedules`
--

INSERT INTO `blocked_schedules` (`block_id`, `block_date`, `start_time`, `end_time`, `reason`, `is_full_day`, `created_by`) VALUES
(1, '2026-04-09', NULL, NULL, 'Regular Holiday: Araw ng Kagitingan', 1, 8),
(4, '2026-05-01', NULL, NULL, 'Holiday : Labor Day', 1, 8),
(5, '2026-05-02', NULL, NULL, 'Seminar', 1, 13);

-- --------------------------------------------------------

--
-- Table structure for table `dentist_appointment_assignments`
--

CREATE TABLE `dentist_appointment_assignments` (
  `assignment_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_appointment_assignments`
--

INSERT INTO `dentist_appointment_assignments` (`assignment_id`, `appointment_id`, `dentist_id`, `checked_in_at`, `completed_at`, `created_at`) VALUES
(1, 13, 12, '2026-04-14 21:24:54', '2026-04-14 22:37:14', '2026-04-14 21:22:11'),
(2, 14, 12, '2026-04-14 22:37:12', '2026-04-14 22:37:15', '2026-04-14 22:26:46'),
(3, 15, 12, '2026-04-14 23:19:54', '2026-04-14 23:22:04', '2026-04-14 23:08:17'),
(4, 16, 12, '2026-04-15 15:55:25', '2026-04-15 16:05:36', '2026-04-15 15:41:35'),
(5, 17, 12, '2026-04-15 19:12:19', '2026-04-15 19:12:34', '2026-04-15 18:59:32'),
(6, 18, 12, NULL, NULL, '2026-04-15 20:51:20'),
(7, 19, 12, NULL, NULL, '2026-04-15 20:52:51'),
(8, 20, 12, NULL, NULL, '2026-04-16 07:28:52'),
(9, 21, 12, NULL, NULL, '2026-04-16 07:34:11'),
(10, 22, 13, '2026-04-17 13:51:35', '2026-04-19 08:05:43', '2026-04-17 13:37:25'),
(11, 23, 13, '2026-04-17 13:53:33', '2026-04-19 08:05:41', '2026-04-17 13:52:42'),
(12, 24, 13, NULL, NULL, '2026-04-20 15:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `dentist_patient_records`
--

CREATE TABLE `dentist_patient_records` (
  `record_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `treatment_notes` text NOT NULL,
  `prescription` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dentist_profiles`
--

CREATE TABLE `dentist_profiles` (
  `dentist_id` int(11) NOT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `digital_signature_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_profiles`
--

INSERT INTO `dentist_profiles` (`dentist_id`, `specialization`, `digital_signature_path`, `created_at`, `updated_at`) VALUES
(12, 'Orthodontics', NULL, '2026-04-14 20:47:50', '2026-04-14 20:47:50'),
(13, 'Orthodontics', NULL, '2026-04-17 13:44:54', '2026-04-17 13:44:54');

-- --------------------------------------------------------

--
-- Table structure for table `medical_info`
--

CREATE TABLE `medical_info` (
  `user_id` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_info`
--

INSERT INTO `medical_info` (`user_id`, `allergies`, `medical_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_number`, `last_update`) VALUES
(4, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-31 20:31:02'),
(5, '', '', '', 'Ereneo Emmanuel', 'Father', '09671324710', '2026-04-17 13:37:25'),
(6, '', '', '', 'Marianne L. Nala', 'Sibling', '09123456789', '2026-04-17 13:52:42'),
(9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-02 20:04:41'),
(10, '', '', '', 'Juan Dela Cruz', 'Father', '09234567891', '2026-04-16 07:28:52'),
(11, 'dairy products', 'N/A', 'N/A', 'Remelyn Tobias', NULL, '09187179287', '2026-04-20 15:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `subject`, `message_text`, `is_read`, `created_at`) VALUES
(9, 8, 6, 'Schedule', 'Good day Urian', 1, '2026-04-02 17:13:38'),
(10, 8, 6, 'Appointment Schedule: Consultation', 'Good day Urian!\r\n\r\nThis is a reminder for your appointment schedule this coming Monday at 1:00 PM.\r\n\r\nThank you', 1, '2026-04-02 17:25:52'),
(11, 6, 8, 'Appointment Schedule: Consultation', 'Good afternoon, Thank you po', 1, '2026-04-02 17:44:16'),
(12, 6, 8, 'Appointment Schedule: Consultation', 'Good afternoon yes po, thank you', 1, '2026-04-02 17:45:34'),
(13, 6, 8, 'Appointment Schedule: Consultation', 'Good afternoon', 1, '2026-04-02 17:53:25'),
(14, 8, 6, 'Appointment Schedule: Consultation', 'Good afternoon', 1, '2026-04-02 17:53:56'),
(15, 8, 10, 'Appointment Schedule', 'Good day Urian!', 1, '2026-04-03 14:30:55'),
(16, 5, 8, 'Appointment Schedule', 'Good day ma\'am/sir, I would like to ask about my appointment schedule if it is okay to move my appointment to this coming saturday due to I have a fever today. Thank you po', 1, '2026-04-03 15:48:35'),
(17, 8, 5, 'Appointment Schedule', 'Good afternoon, yes you can reschedule it', 1, '2026-04-03 17:00:06'),
(18, 8, 10, 'Appointment Schedule', 'Good day urian!', 1, '2026-04-03 17:10:23'),
(19, 8, 10, 'Appointment Schedule', 'Good day', 1, '2026-04-03 18:35:07'),
(20, 8, 5, 'Appointment Schedule', 'ting', 1, '2026-04-03 18:36:45'),
(21, 8, 5, 'Appointment Schedule', 'Ting', 1, '2026-04-03 18:39:50'),
(22, 5, 8, 'Appointment Schedule', 'Hello', 1, '2026-04-03 18:43:59'),
(23, 8, 5, 'Appointment Schedule', 'Ting', 1, '2026-04-03 18:50:44'),
(24, 8, 4, 'Appointment Schedule', 'Ting', 0, '2026-04-03 19:16:23'),
(25, 8, 4, 'Appointment Schedule', 'Good day Urian!', 0, '2026-04-03 19:21:03'),
(26, 8, 5, 'Appointment Schedule', 'Good day Urian', 1, '2026-04-03 19:30:59'),
(27, 10, 8, 'Appointment Schedule: Extraction', 'Good day po', 1, '2026-04-04 23:20:37'),
(28, 6, 8, 'Appointment Schedule: Consultation', 'Good evening', 1, '2026-04-04 23:29:11'),
(29, 8, 10, 'Appointment Schedule: Extraction', 'Good evening Urian', 1, '2026-04-04 23:31:33'),
(30, 6, 8, 'Schedule', 'Good afternoon', 1, '2026-04-05 15:22:38'),
(31, 8, 6, 'Schedule', 'Good afternoon, Urian!', 1, '2026-04-06 14:37:42'),
(32, 11, 8, 'REQUESTING', 'Hi! Good afternoon, I would like to request for reschedule my appointment, this coming friday. Thank you.', 1, '2026-04-06 14:51:56'),
(33, 8, 11, 'REQUESTING', 'Yes, you can what would you prefer time', 1, '2026-04-06 14:53:02'),
(34, 8, 11, 'REQUESTING', 'Good evening Urian!', 1, '2026-04-06 22:10:57'),
(35, 8, 10, 'Appointment Schedule - Consultation', 'Good day', 1, '2026-04-06 22:24:50'),
(36, 6, 8, 'Appointment Schedule: Consultation', 'Good morning, ma\'am', 1, '2026-04-09 07:35:56'),
(37, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:32'),
(38, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:37'),
(39, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:41'),
(40, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:46'),
(41, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:50'),
(42, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:55'),
(43, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:58:59'),
(44, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:59:03'),
(45, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:59:08'),
(46, 12, 10, 'Appointment Schedule: Consultation', 'Good day Urian', 1, '2026-04-15 19:59:11'),
(47, 12, 8, 'Schedule', 'Good day Admin', 1, '2026-04-15 20:01:44'),
(48, 8, 12, 'Schedule', 'Good day Dr. Win, how\'s your day?', 1, '2026-04-15 20:33:15'),
(49, 12, 8, 'Schedule', 'Good afternoon doc', 1, '2026-04-24 11:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `subject`, `message`, `status`, `is_read`, `created_at`, `sent_at`) VALUES
(10, 5, 'email', 'Appointment Request Submitted', 'Your appointment request for April 2, 2026 at 1:00 PM has been submitted and is pending approval.', 'pending', 1, '2026-04-02 06:55:12', NULL),
(11, 5, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-02 08:37:00', NULL),
(13, 11, 'email', 'Appointment Request Submitted', 'Your appointment request for April 23, 2026 at 2:30 PM has been submitted and is pending approval.', 'pending', 1, '2026-04-06 14:46:55', NULL),
(14, 11, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-06 14:47:12', NULL),
(15, 11, 'email', 'Appointment Completed', 'Your appointment has been completed', 'pending', 1, '2026-04-06 14:54:52', NULL),
(16, 5, 'email', 'Appointment Completed', 'Your appointment has been completed', 'pending', 1, '2026-04-06 14:54:55', NULL),
(18, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 10, 2026 at 1:00 PM has been submitted and is pending approval.', 'pending', 1, '2026-04-06 21:44:56', NULL),
(19, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 11, 2026 at 9:00 AM has been submitted and is pending approval.', 'pending', 1, '2026-04-09 07:34:59', NULL),
(20, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 13, 2026 at 3:00 PM has been submitted and is pending approval.', 'pending', 1, '2026-04-09 07:37:22', NULL),
(21, 6, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-09 07:38:00', NULL),
(22, 6, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-09 07:38:03', NULL),
(23, 6, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-09 07:38:07', NULL),
(24, 6, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-09 07:38:11', NULL),
(27, 10, 'email', 'Appointment Request Submitted', 'Your appointment request for April 13, 2026 at 1:00 PM has been submitted and is pending approval.', 'pending', 1, '2026-04-09 11:45:48', NULL),
(28, 10, 'email', 'Appointment Approved', 'Your appointment has been approved', 'pending', 1, '2026-04-09 12:26:21', NULL),
(29, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 15, 2026 at 9:00 AM has been submitted and is pending approval.', 'pending', 1, '2026-04-14 21:22:11', NULL),
(30, 6, 'email', 'Appointment Approved', 'Your appointment on April 15, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-14 22:04:43', NULL),
(31, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 16, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-14 22:26:46', NULL),
(32, 6, 'email', 'Appointment Approved', 'Your appointment on April 16, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-14 22:37:09', NULL),
(33, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 17, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-14 23:08:17', NULL),
(34, 6, 'email', 'Appointment Approved', 'Your appointment on April 17, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-14 23:19:37', NULL),
(35, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 18, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-15 15:41:35', NULL),
(36, 6, 'email', 'Appointment Approved', 'Your appointment on April 18, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-15 15:55:17', NULL),
(37, 10, 'email', 'Appointment Request Submitted', 'Your appointment request for April 17, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-15 18:59:32', NULL),
(38, 12, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 17, 2026 at 9:00 AM. Patient: Janice Dela Cruz.', 'pending', 1, '2026-04-15 18:59:32', NULL),
(39, 10, 'email', 'Appointment Approved', 'Your appointment on April 17, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-15 19:07:42', NULL),
(40, 10, 'email', 'Appointment Request Submitted', 'Your appointment request for April 20, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 0, '2026-04-15 20:51:20', NULL),
(41, 12, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 20, 2026 at 9:00 AM. Patient: Janice Dela Cruz.', 'pending', 1, '2026-04-15 20:51:20', NULL),
(42, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 20, 2026 at 9:30 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-15 20:52:51', NULL),
(43, 12, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 20, 2026 at 9:30 AM. Patient: Marenel Nala.', 'pending', 1, '2026-04-15 20:52:51', NULL),
(44, 6, 'email', 'Appointment Approved', 'Your appointment on April 20, 2026 at 9:30 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-15 21:20:17', NULL),
(45, 10, 'email', 'Appointment Approved', 'Your appointment on April 20, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 0, '2026-04-15 21:20:18', NULL),
(46, 10, 'email', 'Appointment Request Submitted', 'Your appointment request for April 17, 2026 at 9:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 0, '2026-04-16 07:28:52', NULL),
(47, 12, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 17, 2026 at 9:00 AM. Patient: Janice Dela Cruz.', 'pending', 1, '2026-04-16 07:28:52', NULL),
(48, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 17, 2026 at 9:30 AM has been submitted and is pending approval. Assigned dentist: Dr. Win Bonbon.', 'pending', 1, '2026-04-16 07:34:11', NULL),
(49, 12, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 17, 2026 at 9:30 AM. Patient: Marenel Nala.', 'pending', 1, '2026-04-16 07:34:11', NULL),
(50, 10, 'email', 'Appointment Reminder', 'This is a reminder that your dental appointment for Consultation is scheduled on April 17, 2026 at 9:00 AM.', 'sent', 0, '2026-04-16 09:00:12', '2026-04-16 09:00:12'),
(51, 6, 'email', 'Appointment Approved', 'Your appointment on April 17, 2026 at 9:30 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-16 09:28:41', NULL),
(52, 10, 'email', 'Appointment Approved', 'Your appointment on April 17, 2026 at 9:00 AM has been approved by your assigned dentist.', 'pending', 1, '2026-04-16 09:28:42', NULL),
(53, 6, 'email', 'Appointment Reminder', 'This is a reminder that your dental appointment for Permanent Tooth Filling is scheduled on April 17, 2026 at 9:30 AM.', 'sent', 1, '2026-04-16 09:31:13', '2026-04-16 09:31:13'),
(54, 5, 'email', 'Appointment Request Submitted', 'Your appointment request for April 17, 2026 at 3:00 PM has been submitted and is pending approval. Assigned dentist: Dr. Jeo Natividad.', 'pending', 1, '2026-04-17 13:37:25', NULL),
(55, 13, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 17, 2026 at 3:00 PM. Patient: Cris Bonbon.', 'pending', 1, '2026-04-17 13:37:25', NULL),
(56, 5, 'email', 'Appointment Reminder', 'This is a reminder that your dental appointment for Consultation is scheduled on April 17, 2026 at 3:00 PM.', 'sent', 1, '2026-04-17 13:39:17', '2026-04-17 13:39:17'),
(57, 5, 'email', 'Appointment Approved', 'Your appointment on April 17, 2026 at 3:00 PM has been approved by your assigned dentist.', 'pending', 1, '2026-04-17 13:42:44', NULL),
(58, 6, 'email', 'Appointment Request Submitted', 'Your appointment request for April 18, 2026 at 8:00 AM has been submitted and is pending approval. Assigned dentist: Dr. Jeo Natividad.', 'pending', 0, '2026-04-17 13:52:42', NULL),
(59, 13, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 18, 2026 at 8:00 AM. Patient: Marenel Nala.', 'pending', 1, '2026-04-17 13:52:42', NULL),
(60, 6, 'email', 'Appointment Approved', 'Your appointment on April 18, 2026 at 8:00 AM has been approved by your assigned dentist.', 'pending', 0, '2026-04-17 13:53:18', NULL),
(61, 6, 'email', 'Appointment Reminder', 'This is a reminder that your dental appointment for Tooth Extraction is scheduled on April 18, 2026 at 8:00 AM.', 'sent', 1, '2026-04-17 13:54:41', '2026-04-17 13:54:41'),
(62, 11, 'email', 'Appointment Request Submitted', 'Your appointment request for April 21, 2026 at 1:30 PM has been submitted and is pending approval. Assigned dentist: Dr. Jeo Natividad.', 'pending', 1, '2026-04-20 15:38:11', NULL),
(63, 13, 'email', 'New Appointment Assigned', 'A new appointment has been assigned to you for April 21, 2026 at 1:30 PM. Patient: Rochelle Cabusao.', 'pending', 1, '2026-04-20 15:38:11', NULL),
(64, 11, 'email', 'Appointment Approved', 'Your appointment on April 21, 2026 at 1:30 PM has been approved by your assigned dentist.', 'pending', 1, '2026-04-20 15:38:49', NULL),
(65, 11, 'email', 'Appointment Reminder', 'This is a reminder that your dental appointment for Consultation is scheduled on April 21, 2026 at 1:30 PM.', 'sent', 1, '2026-04-20 15:42:08', '2026-04-20 15:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `duration_minutes`, `is_active`) VALUES
(1, 'Consultation', 'Professional dental check-ups and preventive care to keep your smile healthy.', 30, 1),
(2, 'Tooth Extraction', 'Removing a tooth that is damaged, decayed, or impacted.', 30, 1),
(3, 'Oral Prophylaxis', 'Professional cleaning of teeth to remove plaque, tartar, and stains.', 30, 1),
(4, 'Permanent Tooth Filling', 'Restoring damaged teeth with durable filling materials.', 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('dentist_12_saturday_end', '17:00'),
('dentist_12_saturday_start', '13:00'),
('dentist_12_weekday_end', '12:00'),
('dentist_12_weekday_start', '08:00'),
('dentist_13_saturday_end', '12:00'),
('dentist_13_saturday_start', '08:00'),
('dentist_13_weekday_end', '17:00'),
('dentist_13_weekday_start', '13:00'),
('max_bookings_per_day', '1'),
('reminder_hours', '24'),
('reminder_last_run_at', '2026-04-24 15:17:17'),
('saturday_end', '16:00'),
('saturday_start', '08:00'),
('wednesday_end', '17:00'),
('wednesday_start', '08:00'),
('weekday_end', '21:00'),
('weekday_start', '08:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fsuu_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `program` varchar(20) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `role` enum('student','staff','dentist','admin') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fsuu_id`, `first_name`, `last_name`, `email`, `password`, `contact_number`, `program`, `verification_code`, `is_verified`, `role`, `is_active`, `profile_picture`, `created_at`) VALUES
(4, '22100001064', 'Cris', 'Bonbon', 'bonboncris6@gmail.com', '$2y$10$/./yU4NirTAd.7hP1W5w/OBRx0f6D.4iviiSPjCamMgg3D7fmuQFq', '09126758394', 'TEP', NULL, 1, 'student', 1, 'img/uploads/profiles/user_4.jpg', '2026-03-31 20:31:01'),
(5, '2310000444', 'Cris', 'Bonbon', 'cris.bonbon@urios.edu.ph', '$2y$10$SD3SVME6BIv8J5iSd41wCOJ.yztgbIdgzdiEgB99egcQc6zpsShAm', '09388820638', 'TEP', NULL, 1, 'student', 1, 'img/uploads/profiles/user_5.jpg', '2026-04-01 21:50:06'),
(6, '23100000446', 'Marenel', 'Nala', 'marenel.nala@urios.edu.ph', '$2y$10$BqDKB6KEXI.H9BpzGSxDauq3LyS2FhiTqOyxVqaZbH/ziH23mCp8u', '09494752601', 'CSP', NULL, 1, 'student', 1, NULL, '2026-04-02 15:59:18'),
(8, '23100001064', 'FSUU Dental', 'Clinic', 'winnylynbonbon@gmail.com', '$2y$10$3Gz7PgM9qoFUk9yEiP5u.OIBjjt3h7YoqSPjPvhYCNo2OQFwh4ZQi', '09671324710', NULL, NULL, 1, 'admin', 1, 'img/uploads/profiles/user_8_1775121674.jpg', '2026-04-02 16:47:44'),
(9, '23100000747', 'yvan', 'morales', 'yvan.morales@urios.edu.ph', '$2y$10$gOsRQOXnHEbkydx.TU/lsOpbiv5bO8VxE66QrO0OBNILGAWYkgOoi', '09098114820', 'ASP', '967563', 0, 'student', 1, NULL, '2026-04-02 20:04:41'),
(10, '22100001607', 'Janice', 'Dela Cruz', 'janice.delacruz@urios.edu.ph', '$2y$10$NMNl/stpuZsfWbUAHVsD6OsWdNSiqny8bh7ljtlWkXJm4LZj8bOtS', '09459772084', 'CSP', NULL, 1, 'student', 1, NULL, '2026-04-03 11:31:59'),
(11, '23100001909', 'Rochelle', 'Cabusao', 'rochelle.cabusao@urios.edu.ph', '$2y$10$lsKsBJUqst.thBGgeWoA9.vyKrZPfc5JiKMZhSKx9K9wR1EEoSzKO', '09494752601', 'CSP', NULL, 1, 'student', 1, 'img/uploads/profiles/user_11_1775458152.jpg', '2026-04-06 14:44:20'),
(12, '23100001065', 'Win', 'Bonbon', 'winnylyn.bonbon@urios.edu.ph', '$2y$10$gPKE4xrL1SMtgjLRr6Z4HO/liPGVq8PUm.xbF5DEy4kCqDUb3Y0jm', '09383888206', NULL, NULL, 1, 'dentist', 1, 'img/uploads/profiles/user_12_1776179220.jpg', '2026-04-14 19:16:27'),
(13, '23100001066', 'Jeo', 'Natividad', 'jeonatividad4@gmail.com', '$2y$10$kMPYXlQOPvpGYumF7m7AMeMM6XuLpCFlf4.DuP1gEvsd9S3iU.Gom', '09671324710', NULL, NULL, 1, 'dentist', 1, 'img/uploads/profiles/user_13_1776404637.jpg', '2026-04-16 08:30:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD UNIQUE KEY `uq_appointment_reminder` (`appointment_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blocked_schedules`
--
ALTER TABLE `blocked_schedules`
  ADD PRIMARY KEY (`block_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `dentist_appointment_assignments`
--
ALTER TABLE `dentist_appointment_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_appointment` (`appointment_id`),
  ADD KEY `idx_dentist` (`dentist_id`);

--
-- Indexes for table `dentist_patient_records`
--
ALTER TABLE `dentist_patient_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `idx_dentist_patient` (`dentist_id`,`patient_id`),
  ADD KEY `idx_appointment` (`appointment_id`),
  ADD KEY `fk_dpr_patient` (`patient_id`);

--
-- Indexes for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  ADD PRIMARY KEY (`dentist_id`);

--
-- Indexes for table `medical_info`
--
ALTER TABLE `medical_info`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `blocked_schedules`
--
ALTER TABLE `blocked_schedules`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dentist_appointment_assignments`
--
ALTER TABLE `dentist_appointment_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `dentist_patient_records`
--
ALTER TABLE `dentist_patient_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`),
  ADD CONSTRAINT `fk_appt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  ADD CONSTRAINT `fk_reminder_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `blocked_schedules`
--
ALTER TABLE `blocked_schedules`
  ADD CONSTRAINT `fk_block_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `dentist_appointment_assignments`
--
ALTER TABLE `dentist_appointment_assignments`
  ADD CONSTRAINT `fk_daa_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_daa_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `dentist_patient_records`
--
ALTER TABLE `dentist_patient_records`
  ADD CONSTRAINT `fk_dpr_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dpr_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dpr_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  ADD CONSTRAINT `fk_dentist_profile_user` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_info`
--
ALTER TABLE `medical_info`
  ADD CONSTRAINT `fk_medical_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
