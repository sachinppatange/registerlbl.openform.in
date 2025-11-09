-- Migration: Create payments table
-- Date: 2025-11-09
-- Description: Stores payment records for player registrations

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT UNSIGNED NULL,
  `order_id` VARCHAR(100) NOT NULL,
  `payment_id` VARCHAR(100) NULL,
  `amount` INT UNSIGNED NOT NULL COMMENT 'Amount in paise',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `metadata` JSON NULL,
  INDEX `idx_player_id` (`player_id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_payments_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
