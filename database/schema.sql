-- ========================================================
-- Personal Expense Tracker - Database Schema & Initial Data
-- Database: expense_tracker
-- Compatible with MySQL / MariaDB (XAMPP / phpMyAdmin)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `expense_tracker` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `expense_tracker`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `profile_picture` VARCHAR(255) DEFAULT 'default-avatar.png',
  `currency_preference` VARCHAR(10) DEFAULT '$',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL, -- NULL means system default category available to all users
  `name` VARCHAR(50) NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `color` VARCHAR(20) DEFAULT '#6c757d',
  `icon` VARCHAR(50) DEFAULT 'bi-tag',
  `is_default` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transactions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` TEXT,
  `transaction_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'Cash',
  `receipt_image` VARCHAR(255) DEFAULT NULL,
  `is_recurring` TINYINT(1) DEFAULT 0,
  `recurring_frequency` ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: budgets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `monthly_limit` DECIMAL(12,2) NOT NULL,
  `month` INT NOT NULL, -- 1-12
  `year` INT NOT NULL,  -- e.g. 2026
  UNIQUE KEY `user_cat_month_year` (`user_id`, `category_id`, `month`, `year`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA
-- Default System Categories (user_id = NULL, is_default = 1)
-- ========================================================

INSERT INTO `categories` (`user_id`, `name`, `type`, `color`, `icon`, `is_default`) VALUES
-- Income Categories
(NULL, 'Salary', 'income', '#198754', 'bi-cash-stack', 1),
(NULL, 'Freelance', 'income', '#0d6efd', 'bi-laptop', 1),
(NULL, 'Investments', 'income', '#20c997', 'bi-graph-up-arrow', 1),
(NULL, 'Other Income', 'income', '#6c757d', 'bi-wallet2', 1),

-- Expense Categories
(NULL, 'Food & Dining', 'expense', '#fd7e14', 'bi-cup-hot', 1),
(NULL, 'Transport', 'expense', '#0dcaf0', 'bi-car-front', 1),
(NULL, 'Housing & Rent', 'expense', '#6f42c1', 'bi-house-door', 1),
(NULL, 'Utilities', 'expense', '#ffc107', 'bi-lightning-charge', 1),
(NULL, 'Entertainment', 'expense', '#d63384', 'bi-controller', 1),
(NULL, 'Healthcare', 'expense', '#dc3545', 'bi-heart-pulse', 1),
(NULL, 'Shopping', 'expense', '#e83e8c', 'bi-bag-check', 1),
(NULL, 'Education', 'expense', '#0d6efd', 'bi-journal-bookmark', 1),
(NULL, 'Other Expense', 'expense', '#adb5bd', 'bi-three-dots', 1);

-- ========================================================
-- DEMO USER & SAMPLE DATA
-- Email: demo@example.com
-- Password: Password123!
-- Hash generated via password_hash('Password123!', PASSWORD_BCRYPT)
-- ========================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `currency_preference`) VALUES
(1, 'Alex Demo User', 'demo@example.com', '$2y$10$VF8PZ/4.ucwDrYUp/qbmFuLp7PYp.cEBbOP5o5aI325.05KIdFl/i', '$');

-- Sample Budgets for Demo User for Current Year (2026) / Current Month
INSERT INTO `budgets` (`user_id`, `category_id`, `monthly_limit`, `month`, `year`) VALUES
(1, 5, 450.00, 7, 2026), -- Food & Dining ($450)
(1, 6, 200.00, 7, 2026), -- Transport ($200)
(1, 7, 1200.00, 7, 2026), -- Housing & Rent ($1200)
(1, 8, 180.00, 7, 2026), -- Utilities ($180)
(1, 9, 150.00, 7, 2026); -- Entertainment ($150)

-- Sample Transactions for Demo User
INSERT INTO `transactions` (`user_id`, `category_id`, `type`, `amount`, `description`, `transaction_date`, `payment_method`, `is_recurring`, `recurring_frequency`) VALUES
(1, 1, 'income', 4500.00, 'Monthly Tech Salary', '2026-07-01', 'Bank Transfer', 1, 'monthly'),
(1, 2, 'income', 850.00, 'Web Design Freelance Project', '2026-07-15', 'UPI', 0, 'none'),
(1, 7, 'expense', 1150.00, 'Apartment Rent Payment', '2026-07-02', 'Bank Transfer', 1, 'monthly'),
(1, 5, 'expense', 85.50, 'Grocery shopping at Supermarket', '2026-07-04', 'Card', 0, 'none'),
(1, 6, 'expense', 45.00, 'Fuel tank refill', '2026-07-06', 'Card', 0, 'none'),
(1, 8, 'expense', 120.00, 'Electricity & Water Bill', '2026-07-10', 'UPI', 1, 'monthly'),
(1, 5, 'expense', 160.00, 'Dinner party with colleagues', '2026-07-12', 'Card', 0, 'none'),
(1, 9, 'expense', 89.99, 'Annual Streaming Subscription', '2026-07-14', 'Card', 0, 'none'),
(1, 11, 'expense', 210.00, 'New Headphones & Accessories', '2026-07-18', 'Card', 0, 'none'),
(1, 5, 'expense', 145.20, 'Weekly Grocery & Organic Supplies', '2026-07-22', 'Cash', 0, 'none'),
(1, 6, 'expense', 125.00, 'Monthly Transit Pass', '2026-07-24', 'UPI', 1, 'monthly'),
(1, 10, 'expense', 65.00, 'Pharmacy prescription & vitamins', '2026-07-25', 'Cash', 0, 'none');
