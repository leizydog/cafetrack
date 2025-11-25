-- Staff table
CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(191) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory unified table
CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  type ENUM('product','ingredient','supply') NOT NULL,
  category VARCHAR(64),
  opening_count INT NOT NULL DEFAULT 0,
  available_in_hand INT NOT NULL DEFAULT 0,
  closing_count INT NOT NULL DEFAULT 0,
  no_utilized INT NOT NULL DEFAULT 0,
  unit VARCHAR(32) DEFAULT 'pcs',
  status VARCHAR(32) DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(type, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Ingredients
CREATE TABLE IF NOT EXISTS product_ingredients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  inventory_id INT UNSIGNED NOT NULL,
  amount_used DECIMAL(10,3) NOT NULL DEFAULT 1,
  unit VARCHAR(32),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Supplies
CREATE TABLE IF NOT EXISTS product_supplies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  inventory_id INT UNSIGNED NOT NULL,
  amount_used DECIMAL(10,3) NOT NULL DEFAULT 1,
  unit VARCHAR(32),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED,
  total_amount DECIMAL(10,2) DEFAULT 0,
  order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Items
CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- cash drawer closeouts + expenses
CREATE TABLE IF NOT EXISTS cash_closeouts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  cashier VARCHAR(191),
  opening DECIMAL(10,2) DEFAULT 0,
  cash_sale DECIMAL(10,2) DEFAULT 0,
  actual_cash DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(`date`),
  INDEX(cashier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  closeout_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (closeout_id) REFERENCES cash_closeouts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Users Table
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

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'duepew', 'duepew002@gmail.com', '$2y$10$a3k803l5stw4OgayLOAk8OX2fisu2XLvH34hLu6zWUhJYBRb3eu1S', 'staff', 'staff', '', NULL, 1, '2025-11-25 09:17:50', '2025-11-24 07:42:36', '2025-11-25 09:17:50'),
(2, 'jerbert', 'jerbert@gmail.com', '$2y$10$/RIXyoeVwYEornJTGRMYQOUDyGX5aPZNP9syJ8OcNvZG/2z.ZHkv6', 'admin', 'staff', '', NULL, 1, '2025-11-24 22:48:54', '2025-11-24 07:56:29', '2025-11-24 22:48:54'),
(3, 'justin', 'justin@gmail.com', '$2y$10$BRcAueE7wWcGwmdt58ycLuwPoWOZ58gkGo8JwcGEjsGSIkc6pSoYm', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 02:41:02', '2025-11-25 02:41:02'),
(4, 'khenet', 'khenet@gmail.com', '$2y$10$SHuGcHFrn/EJ26RndFwi.uFO/724LUxeep0oaM3RssBsxES8lrawu', 'staff', 'staff', '', NULL, 1, '2025-11-25 08:33:30', '2025-11-25 08:33:21', '2025-11-25 08:33:30'),
(5, 'jerbertmape619@gmail.com', 'jerbertmape619@gmail.com', '$2y$10$lo8sq/8XBxHv2nEqMDKlq.r9kGjxVW.JNdSv1JDKtQsrWCmO2dtdG', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 08:46:28', '2025-11-25 08:46:28'),
(6, 'reyeskhenet', 'reyeskhenet7@gmail.com', '$2y$10$wBtlNyGepGwOCvIRjnwUKOSgR18cV0Y4ATP.Urm2uw0F1FIyB1BvS', 'staff', 'staff', '', NULL, 1, NULL, '2025-11-25 09:15:20', '2025-11-25 09:15:20');

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_2` (`email`),
  ADD KEY `username_2` (`username`),
  ADD KEY `role` (`role`),
  ADD KEY `is_active` (`is_active`);

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;