CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tx_id` VARCHAR(13) NOT NULL,
  `login` BIGINT NOT NULL,
  `type` ENUM('deposit','withdraw') NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `status` ENUM('pending','paid','approved','applied','failed') NOT NULL DEFAULT 'pending',
  `mt5_ticket` VARCHAR(64) DEFAULT NULL,
  `retcode` VARCHAR(255) DEFAULT NULL,
  `details_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tx_id` (`tx_id`),
  KEY `idx_login_type_status` (`login`,`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
