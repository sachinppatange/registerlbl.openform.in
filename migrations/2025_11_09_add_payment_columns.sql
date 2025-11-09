-- Create payments table to store Razorpay transactions (minimal PII)
CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id BIGINT UNSIGNED NULL,
  `order_id` VARCHAR(128) NOT NULL,
  `payment_id` VARCHAR(128) NULL,
  amount INT NOT NULL, -- amount in paise
  currency VARCHAR(8) NOT NULL DEFAULT 'INR',
  status VARCHAR(16) NOT NULL DEFAULT 'pending', -- pending | paid | failed
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_player_id (player_id),
  KEY idx_order_id (order_id),
  CONSTRAINT fk_payments_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;