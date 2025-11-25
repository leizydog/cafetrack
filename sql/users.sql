-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 10:32 AM
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
-- Database: `cafetrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','manager') NOT NULL DEFAULT 'staff',
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'duepew', 'duepew002@gmail.com', '$2y$10$a3k803l5stw4OgayLOAk8OX2fisu2XLvH34hLu6zWUhJYBRb3eu1S', 'staff', 'staff', '', NULL, 1, '2025-11-25 09:17:50', '2025-11-24 07:42:36', '2025-11-25 09:17:50'),
(2, 'jerbert', 'jerbert@gmail.com', '$2y$10$/RIXyoeVwYEornJTGRMYQOUDyGX5aPZNP9syJ8OcNvZG/2z.ZHkv6', 'admin', 'staff', '', NULL, 1, '2025-11-24 22:48:54', '2025-11-24 07:56:29', '2025-11-24 22:48:54'),
(3, 'justin', 'justin@gmail.com', '$2y$10$BRcAueE7wWcGwmdt58ycLuwPoWOZ58gkGo8JwcGEjsGSIkc6pSoYm', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 02:41:02', '2025-11-25 02:41:02'),
(4, 'khenet', 'khenet@gmail.com', '$2y$10$SHuGcHFrn/EJ26RndFwi.uFO/724LUxeep0oaM3RssBsxES8lrawu', 'staff', 'staff', '', NULL, 1, '2025-11-25 08:33:30', '2025-11-25 08:33:21', '2025-11-25 08:33:30'),
(5, 'jerbertmape619@gmail.com', 'jerbertmape619@gmail.com', '$2y$10$lo8sq/8XBxHv2nEqMDKlq.r9kGjxVW.JNdSv1JDKtQsrWCmO2dtdG', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 08:46:28', '2025-11-25 08:46:28'),
(6, 'reyeskhenet', 'reyeskhenet7@gmail.com', '$2y$10$wBtlNyGepGwOCvIRjnwUKOSgR18cV0Y4ATP.Urm2uw0F1FIyB1BvS', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 09:15:20', '2025-11-25 09:15:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_2` (`email`),
  ADD KEY `username_2` (`username`),
  ADD KEY `role` (`role`),
  ADD KEY `is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
