-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 10, 2026 at 01:27 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `attendance_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `lat_in` varchar(50) DEFAULT NULL,
  `long_in` varchar(50) DEFAULT NULL,
  `lat_out` varchar(50) DEFAULT NULL,
  `long_out` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'hadir',
  `status_out` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `date`, `time_in`, `time_out`, `lat_in`, `long_in`, `lat_out`, `long_out`, `status`, `status_out`) VALUES
(1, 2, '2026-01-10', '15:59:48', NULL, '-6.7465233', '108.53413', NULL, NULL, 'Telat', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `schedule_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `schedule_id`) VALUES
(1, 'Human Resources', 1),
(2, 'IT Department', 1),
(3, 'Finance', 1),
(4, 'Academic Affairs', 1),
(5, 'Bidang Pendidikan', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `full_name`, `phone_number`, `address`, `department_id`, `unit_id`, `email`, `password`, `schedule_id`) VALUES
(1, 'Admin User', '081234567891', '', 2, 3, 'admin@example.com', '$2y$10$G2yfU7cKZX02/9YLz/Y3Oe9Nd2jwnD.kmNic7tHFZGGhfhHrBnaGy', NULL),
(2, 'Andi Muchtaif', '089651804382', 'Jl Kalitanjung No52b Kota Cirebon', 2, 3, 'amuchtaif@gmail.com', '$2y$10$yT5yrE6/39jTjYSdBCjOVOy6gFn6iubkResgkmdeZR1msZ4ztJQK2', NULL),
(3, 'Joni Sujono', '085939392022', 'Komplek Asunah', 1, 1, 'joni@gmail.com', '$2y$10$P0VS.fAI3NL9dhDdPW0eSeProsRrpwmmkoYYBOmkYF/2F2w8bqX.q', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `office_settings`
--

CREATE TABLE `office_settings` (
  `id` int(11) NOT NULL,
  `office_name` varchar(100) DEFAULT 'Kantor Pusat',
  `latitude` varchar(50) NOT NULL,
  `longitude` varchar(50) NOT NULL,
  `radius_meters` int(11) NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `office_settings`
--

INSERT INTO `office_settings` (`id`, `office_name`, `latitude`, `longitude`, `radius_meters`) VALUES
(1, 'Kantor Pusat', '-6.746523809375691', '108.5341313675354', 500);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `class_grade` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `department_id`, `name`) VALUES
(1, 1, 'Recruitment'),
(2, 1, 'Payroll'),
(3, 2, 'Software Development'),
(4, 2, 'Network Admin'),
(5, 4, 'Student Services');

-- --------------------------------------------------------

--
-- Table structure for table `work_schedules`
--

CREATE TABLE `work_schedules` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_schedules`
--

INSERT INTO `work_schedules` (`id`, `name`) VALUES
(1, 'Reguler'),
(2, 'Shift Siang');

-- --------------------------------------------------------

--
-- Table structure for table `work_schedule_details`
--

CREATE TABLE `work_schedule_details` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `day_name` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_day_off` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_schedule_details`
--

INSERT INTO `work_schedule_details` (`id`, `schedule_id`, `day_name`, `start_time`, `end_time`, `is_day_off`) VALUES
(1, 1, 'Monday', '08:00:00', '16:00:00', 0),
(2, 1, 'Tuesday', '08:00:00', '16:00:00', 0),
(3, 1, 'Wednesday', '08:00:00', '16:00:00', 0),
(4, 1, 'Thursday', '08:00:00', '16:00:00', 0),
(5, 1, 'Friday', '08:00:00', '16:00:00', 0),
(6, 1, 'Saturday', '08:00:00', '12:00:00', 0),
(7, 1, 'Sunday', NULL, NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `office_settings`
--
ALTER TABLE `office_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `work_schedules`
--
ALTER TABLE `work_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_schedule_details`
--
ALTER TABLE `work_schedule_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_day_schedule` (`schedule_id`,`day_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `office_settings`
--
ALTER TABLE `office_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `work_schedules`
--
ALTER TABLE `work_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `work_schedule_details`
--
ALTER TABLE `work_schedule_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_schedule_details`
--
ALTER TABLE `work_schedule_details`
  ADD CONSTRAINT `work_schedule_details_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `work_schedules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
