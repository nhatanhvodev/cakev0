-- Cleanup unused schema objects and align runtime tables.
-- Safe to run multiple times.

SET @db_name := DATABASE();

-- Ensure missing runtime tables exist.
CREATE TABLE IF NOT EXISTS `cart_coupons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `discount_percent` DECIMAL(5,2) NOT NULL,
  `min_subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `starts_at` DATE DEFAULT NULL,
  `ends_at` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cart_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `contact_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('pending','replied') NOT NULL DEFAULT 'pending',
  `reply_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `replied_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_requests_status` (`status`),
  KEY `idx_contact_requests_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Drop truly unused blog/comment/like tables.
DROP TABLE IF EXISTS `likes`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `blogs`;

-- Drop unused columns if present.
SELECT
  IF(
    COUNT(*) > 0,
    'ALTER TABLE `banh` DROP COLUMN `is_featured`',
    'SELECT 1'
  ) INTO @sql_stmt
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'banh'
  AND COLUMN_NAME = 'is_featured';
PREPARE dyn_stmt FROM @sql_stmt;
EXECUTE dyn_stmt;
DEALLOCATE PREPARE dyn_stmt;

SELECT
  IF(
    COUNT(*) > 0,
    'ALTER TABLE `banh` DROP COLUMN `stock`',
    'SELECT 1'
  ) INTO @sql_stmt
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'banh'
  AND COLUMN_NAME = 'stock';
PREPARE dyn_stmt FROM @sql_stmt;
EXECUTE dyn_stmt;
DEALLOCATE PREPARE dyn_stmt;

SELECT
  IF(
    COUNT(*) > 0,
    'ALTER TABLE `users` DROP COLUMN `remember_token`',
    'SELECT 1'
  ) INTO @sql_stmt
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'remember_token';
PREPARE dyn_stmt FROM @sql_stmt;
EXECUTE dyn_stmt;
DEALLOCATE PREPARE dyn_stmt;
