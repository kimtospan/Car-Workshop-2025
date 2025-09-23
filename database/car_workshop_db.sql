-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 31, 2025 at 01:54 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_workshop_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `mechanic_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` enum('σέρβις','επιδιόρθωση','έλεγχος ΚΤΕΟ','άλλο') NOT NULL,
  `problem_description` text DEFAULT NULL,
  `status` enum('ΔΗΜΙΟΥΡΓΗΜΕΝΟ','ΣΕ ΕΞΕΛΙΞΗ','ΠΕΡΑΙΩΜΕΝΟ','ΑΚΥΡΩΜΕΝΟ') NOT NULL DEFAULT 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ',
  `total_cost` decimal(10,2) DEFAULT NULL COMMENT 'Total cost (calculated from tasks)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Completion timestamp',
  `cancelled_at` timestamp NULL DEFAULT NULL COMMENT 'Cancellation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `customer_id`, `vehicle_id`, `mechanic_id`, `appointment_date`, `appointment_time`, `reason`, `problem_description`, `status`, `total_cost`, `created_at`, `updated_at`, `completed_at`, `cancelled_at`) VALUES
(1, 2, 2, 2, '2025-07-25', '10:00:00', 'σέρβις', NULL, 'ΑΚΥΡΩΜΕΝΟ', 0.00, '2025-08-12 10:30:57', '2025-08-23 18:14:14', NULL, '2025-08-23 18:14:14'),
(2, 1, 1, 1, '2025-07-22', '14:30:00', 'επιδιόρθωση', 'Παράξενος θόρυβος από τον κινητήρα κατά την εκκίνηση', 'ΣΕ ΕΞΕΛΙΞΗ', 80.00, '2025-08-12 10:30:57', '2025-08-20 18:20:36', NULL, NULL),
(5, 2, 2, NULL, '2025-08-17', '08:00:00', 'σέρβις', '', 'ΑΚΥΡΩΜΕΝΟ', 0.00, '2025-08-16 15:30:26', '2025-08-23 16:59:32', NULL, '2025-08-23 16:59:32'),
(6, 2, NULL, 2, '2025-08-22', '14:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', 0.00, '2025-08-16 15:31:16', '2025-08-23 18:13:26', '2025-08-22 16:36:12', NULL),
(11, 2, 2, 1, '2025-08-19', '08:00:00', 'σέρβις', '', 'ΑΚΥΡΩΜΕΝΟ', 0.00, '2025-08-19 15:52:10', '2025-08-20 18:20:36', NULL, '2025-08-19 16:26:55'),
(13, 2, 2, 1, '2025-08-22', '14:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', NULL, '2025-08-22 12:27:08', '2025-08-22 16:00:44', '2025-08-22 15:00:44', NULL),
(15, 2, 2, 2, '2025-08-23', '08:00:00', 'σέρβις', '', 'ΑΚΥΡΩΜΕΝΟ', NULL, '2025-08-23 16:58:52', '2025-08-23 17:04:17', NULL, '2025-08-23 17:04:17'),
(16, 2, 6, 1, '2025-08-23', '08:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-23 19:50:15', '2025-08-23 19:50:15', NULL, NULL),
(17, 2, 6, 2, '2025-08-23', '10:00:00', 'σέρβις', '', 'ΑΚΥΡΩΜΕΝΟ', NULL, '2025-08-23 19:50:58', '2025-08-25 12:26:21', NULL, '2025-08-25 12:26:21'),
(18, 2, 6, 1, '2025-08-24', '08:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-24 16:55:00', '2025-08-24 16:55:00', NULL, NULL),
(19, 2, 6, 1, '2025-08-25', '10:00:00', 'σέρβις', '', 'ΣΕ ΕΞΕΛΙΞΗ', 0.00, '2025-08-25 13:45:22', '2025-08-29 19:34:55', NULL, NULL),
(20, 2, 6, 1, '2025-08-27', '08:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-27 12:42:24', '2025-08-27 12:42:24', NULL, NULL),
(22, 1, 1, 2, '2025-08-27', '10:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-27 12:50:08', '2025-08-27 12:50:08', NULL, NULL),
(23, 2, NULL, 1, '2025-08-27', '12:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', 54.68, '2025-08-27 17:41:46', '2025-08-29 21:03:14', '2025-08-29 18:03:27', NULL),
(25, 1, 1, 1, '2025-08-29', '12:00:00', 'σέρβις', '', 'ΣΕ ΕΞΕΛΙΞΗ', 0.00, '2025-08-29 19:41:37', '2025-08-29 20:19:56', NULL, NULL),
(29, 2, 7, 4, '2025-08-29', '14:00:00', 'σέρβις', '', 'ΑΚΥΡΩΜΕΝΟ', NULL, '2025-08-29 20:38:04', '2025-08-29 20:38:50', NULL, '2025-08-29 20:38:50'),
(35, 2, 7, 1, '2025-08-30', '10:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', 65.13, '2025-08-29 21:04:57', '2025-08-29 21:18:52', '2025-08-29 20:18:52', NULL),
(36, 2, 2, 2, '2025-08-30', '12:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', 62.77, '2025-08-29 21:06:19', '2025-08-29 21:15:04', '2025-08-29 20:15:04', NULL),
(37, 2, 7, 2, '2025-08-30', '08:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', NULL, '2025-08-29 21:13:13', '2025-08-30 20:52:22', '2025-08-30 19:52:22', NULL),
(38, 2, 7, 2, '2025-08-29', '12:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-29 21:17:15', '2025-08-29 21:17:15', NULL, NULL),
(39, 2, 6, 4, '2025-08-31', '08:00:00', 'σέρβις', '', 'ΠΕΡΑΙΩΜΕΝΟ', 53.53, '2025-08-30 18:46:06', '2025-08-30 20:49:16', '2025-08-30 19:49:16', NULL),
(40, 1, 1, 4, '2025-08-30', '12:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-30 18:46:27', '2025-08-30 18:46:27', NULL, NULL),
(41, 2, 6, 2, '2025-08-30', '10:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-30 18:46:39', '2025-08-30 18:46:39', NULL, NULL),
(42, 2, 7, 4, '2025-08-30', '08:00:00', 'σέρβις', '', 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ', NULL, '2025-08-30 20:47:33', '2025-08-30 20:47:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `afm` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `afm`, `address`, `created_at`, `updated_at`) VALUES
(1, 5, 'ΑΦΜ123456789', 'Λεωφόρος Κηφισίας 123, Αθήνα 11521', '2025-08-12 10:30:57', '2025-08-12 10:30:57'),
(2, 6, '234567890', 'Οδός Πατησίων 45, Αθήνα 10432', '2025-08-12 10:30:57', '2025-08-23 14:00:38'),
(25, 34, '262376631', 'Hello', '2025-08-29 20:59:58', '2025-08-30 18:47:40'),
(30, 41, '553412412', 'Dieyuynsh', '2025-08-30 20:46:26', '2025-08-30 20:46:26'),
(31, 42, '111222333', '10 Acropolis St', '2025-08-30 20:50:53', '2025-08-30 20:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `failed_login_attempts`
--

CREATE TABLE `failed_login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mechanics`
--

CREATE TABLE `mechanics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialty` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mechanics`
--

INSERT INTO `mechanics` (`id`, `user_id`, `specialty`, `created_at`, `updated_at`) VALUES
(1, 3, 'Μηχανικός κινητήρων', '2025-08-12 10:30:57', '2025-08-25 13:52:58'),
(2, 4, 'Αρχιμηχανικός', '2025-08-12 10:30:57', '2025-08-24 19:55:35'),
(4, 23, 'Engine specialist', '2025-08-27 20:18:04', '2025-08-27 20:18:04'),
(9, 43, 'Transmission specialist', '2025-08-30 20:50:53', '2025-08-30 20:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `materials` text NOT NULL,
  `completion_time` int(11) NOT NULL COMMENT 'Completion time in minutes',
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `appointment_id`, `mechanic_id`, `description`, `materials`, `completion_time`, `cost`, `created_at`, `updated_at`) VALUES
(3, 2, 1, 'Διάγνωση προβλήματος κινητήρα', 'Διαγνωστικός έλεγχος', 20000, 80.00, '2025-08-12 10:30:57', '2025-08-12 10:30:57'),
(14, 23, 1, 'fefaw', 'fwafaf', 30, 54.68, '2025-08-29 19:03:19', '2025-08-29 19:03:19'),
(17, 36, 2, 'φασφ', 'φασφα', 30, 62.77, '2025-08-29 21:14:53', '2025-08-29 21:14:53'),
(18, 35, 1, 'φσφα', 'φασφα', 30, 65.13, '2025-08-29 21:18:44', '2025-08-29 21:18:44'),
(20, 39, 4, 'Oil', 'Oil', 30, 53.53, '2025-08-30 20:49:11', '2025-08-30 20:49:11');

--
-- Triggers `tasks`
--
DELIMITER $$
CREATE TRIGGER `update_appointment_cost` AFTER INSERT ON `tasks` FOR EACH ROW BEGIN
    UPDATE
        appointments
    SET
        total_cost =(
        SELECT
            COALESCE(SUM(cost),
            0)
        FROM
            tasks
        WHERE
            appointment_id = NEW.appointment_id
    )
WHERE
    id = NEW.appointment_id ; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_appointment_cost_on_delete` AFTER DELETE ON `tasks` FOR EACH ROW BEGIN
    UPDATE
        appointments
    SET
        total_cost =(
        SELECT
            COALESCE(SUM(cost),
            0)
        FROM
            tasks
        WHERE
            appointment_id = OLD.appointment_id
    )
WHERE
    id = OLD.appointment_id ; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_appointment_cost_on_update` AFTER UPDATE ON `tasks` FOR EACH ROW BEGIN
    UPDATE
        appointments
    SET
        total_cost =(
        SELECT
            COALESCE(SUM(cost),
            0)
        FROM
            tasks
        WHERE
            appointment_id = NEW.appointment_id
    )
WHERE
    id = NEW.appointment_id ; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `role` enum('visitor','customer','mechanic','secretary') NOT NULL DEFAULT 'visitor',
  `status` enum('inactive','active','suspended') NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `id_number`, `role`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'admin', 'admin@carworkshop.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Creator', 'ΑΤ000000', 'secretary', 'active', '2025-08-12 10:30:57', '2025-08-30 20:47:03', NULL),
(2, 'secretary1', 'secretary@carworkshop.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Μαρία', 'Παπαδοπούλουυ', 'ΑΤ123456', 'secretary', 'active', '2025-08-12 10:30:57', '2025-08-23 14:01:19', NULL),
(3, 'mechanic1', 'mechanic1@carworkshop.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Γιάννηςς', 'Μπακάκος', 'ΑΤ234567', 'mechanic', 'active', '2025-08-12 10:30:57', '2025-08-29 21:18:58', NULL),
(4, 'mechanic2', 'mechanic2@carworkshop.gr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Νίκος', 'Τσέντος', 'ΑΤ345678', 'mechanic', 'active', '2025-08-12 10:30:57', '2025-08-24 21:20:49', NULL),
(5, 'customer1', 'customer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Πέτρος', 'Αντωνόπουλος', 'ΑΤ456789', 'customer', 'active', '2025-08-12 10:30:57', '2025-08-16 12:40:29', NULL),
(6, 'customer2', 'customer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ελένη', 'Γεωργίου', 'ΑΤ567890', 'customer', 'active', '2025-08-12 10:30:57', '2025-08-30 20:51:17', NULL),
(23, 'mbrown', 'mbrown@example.com', '$2y$10$0dTRQPjpce7J1dxrfxVcfOYITg5JUeEwoSCTI5Z4YNSHpNauIbCTu', 'Michael', 'Brown', 'AT654321', 'mechanic', 'active', '2025-08-27 20:18:04', '2025-08-29 19:41:56', NULL),
(34, 'icsd220', 'icsd22012@gmail.com', '$2y$10$gkWebDCVVDGiQeksZUotN.0QyFohyLuncSeDVC1vHkRWnn9wJ.s7K', 'icsd22012', 'username', 'AT444555', 'customer', 'active', '2025-08-29 20:59:58', '2025-08-30 20:47:46', NULL),
(41, 'icsd22', 'icsd22@gmail.com', '$2y$10$AhlczKCD.XD55MBSJEYiguEXjgCGF6ynfLHaVl8WaWCbCFW0APpVe', 'icsd', 'user', 'AT233211', 'customer', 'active', '2025-08-30 20:46:26', '2025-08-30 20:47:17', NULL),
(42, 'elena_pap', 'elena.pap@example.com', '$2y$10$DUWQlaK50x0Bf1GoN3zR..K9.9nNkcm3wwYMCfqh4JR1eStree0xa', 'Elenaa', 'Papadopoulou', 'AT998877', 'customer', 'inactive', '2025-08-30 20:50:53', '2025-08-30 23:13:07', NULL),
(43, 'nikos_m', 'nikos.m@example.com', '$2y$10$SI7DyWggmiL.zYgiRO09FOY6ojj/VRcejDBsbIwU5HYl2P9f54fyS', 'Nikos', 'Mylonas', 'AT777888', 'mechanic', 'inactive', '2025-08-30 20:50:53', '2025-08-30 20:50:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'login', 'Secretary logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-12 10:30:57'),
(2, 6, 'login', 'Customer logged in successfully', '192.168.1.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-12 10:30:57'),
(3, 4, 'appointment_update', 'Updated appointment status to in progress', '192.168.1.102', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-08-12 10:30:57'),
(4, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:31:12'),
(5, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:37:24'),
(6, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:52:13'),
(7, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:52:17'),
(8, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:57:31'),
(9, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 10:57:35'),
(10, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 15:15:32'),
(11, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 18:46:38'),
(12, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:18:08'),
(13, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:18:26'),
(14, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:18:40'),
(15, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:18:43'),
(16, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:19:08'),
(17, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:19:27'),
(18, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-12 19:19:31'),
(19, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 09:27:47'),
(20, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.103.0 Chrome/138.0.7204.100 Electron/37.2.3 Safari/537.36', '2025-08-13 09:43:14'),
(21, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 09:45:45'),
(22, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 09:45:53'),
(23, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:37:20'),
(24, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:37:25'),
(25, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:48:36'),
(26, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:51:08'),
(27, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:52:18'),
(28, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:52:23'),
(29, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 10:52:27'),
(30, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:01:07'),
(31, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:01:10'),
(32, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:01:15'),
(33, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:01:18'),
(34, 2, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:08:59'),
(35, 2, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:17:51'),
(36, 2, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:17:56'),
(37, 2, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:43:37'),
(38, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:43:41'),
(39, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:43:45'),
(40, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 11:43:51'),
(41, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 15:51:00'),
(42, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 15:51:03'),
(43, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 15:51:05'),
(44, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 16:05:24'),
(45, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 16:05:27'),
(46, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 16:38:08'),
(47, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 16:38:11'),
(48, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 16:49:32'),
(49, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:00:29'),
(50, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:00:32'),
(51, 4, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:00:39'),
(52, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:00:42'),
(53, 4, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:46:16'),
(54, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:46:19'),
(55, 4, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:46:23'),
(56, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 17:47:02'),
(57, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 18:01:01'),
(58, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 18:01:11'),
(59, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 13:52:16'),
(60, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 13:53:12'),
(61, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 13:53:41'),
(62, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 13:53:45'),
(63, 4, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 14:00:23'),
(64, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 14:00:26'),
(65, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 14:00:34'),
(66, 4, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 14:00:37'),
(67, 1, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 17:51:53'),
(68, 1, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 17:52:03'),
(69, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-14 17:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `serial_number` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `vehicle_type` enum('επιβατικό','φορτηγό','μοτοσικλέτα','άλλο') NOT NULL,
  `engine_type` enum('βενζινοκίνητο','πετρελαιοκίνητο','υβριδικό','ηλεκτροκίνητο','φυσικό αέριο') NOT NULL,
  `door_count` tinyint(3) UNSIGNED NOT NULL,
  `wheel_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `production_date` date NOT NULL,
  `acquisition_year` year(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `customer_id`, `serial_number`, `model`, `brand`, `vehicle_type`, `engine_type`, `door_count`, `wheel_count`, `production_date`, `acquisition_year`, `created_at`, `updated_at`) VALUES
(1, 1, 'VIN123456789ABCDEF1', 'Corolla', 'Toyota', 'επιβατικό', 'πετρελαιοκίνητο', 5, 4, '2018-03-18', '2019', '2025-08-12 10:30:57', '2025-08-22 17:33:25'),
(2, 2, 'VIN234567890BCDEFG2', 'Golf', 'Volkswagen', 'επιβατικό', 'πετρελαιοκίνητο', 5, 4, '2020-07-22', '2020', '2025-08-12 10:30:57', '2025-08-23 18:14:23'),
(6, 2, '23155421', 'Fiesta', 'Ford', 'επιβατικό', 'βενζινοκίνητο', 4, 4, '2025-08-13', '2025', '2025-08-23 19:26:56', '2025-08-23 19:26:56'),
(7, 2, 'VIN234567890BCDEFD2', 'Questa', 'Forde', 'επιβατικό', 'βενζινοκίνητο', 4, 5, '2025-08-11', '2025', '2025-08-27 13:28:02', '2025-08-30 20:50:24'),
(17, 2, 'DASDA', 'dasda', 'fsdfas', 'επιβατικό', 'βενζινοκίνητο', 3, 5, '2025-08-03', '2025', '2025-08-30 20:51:41', '2025-08-30 20:51:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_mechanic_id` (`mechanic_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `afm` (`afm`);

--
-- Indexes for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointment_id` (`appointment_id`),
  ADD KEY `idx_mechanic_id` (`mechanic_id`),
  ADD KEY `idx_cost` (`cost`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mechanics`
--
ALTER TABLE `mechanics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD CONSTRAINT `mechanics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`id`);

--
-- Constraints for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
