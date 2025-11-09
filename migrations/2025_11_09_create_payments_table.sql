-- Migration: Create payments table
-- Date: 2025-11-09
-- Description: Table to store Razorpay payment transactions

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NULL,
    order_id VARCHAR(100) NOT NULL,
    payment_id VARCHAR(100) NULL,
    amount INT NOT NULL COMMENT 'Amount in paise',
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    metadata JSON NULL COMMENT 'Additional payment metadata',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_player_id (player_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
