CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NULL,
  mt5_login BIGINT UNSIGNED NOT NULL UNIQUE,
  mt5_group VARCHAR(120) NOT NULL,
  mt5_leverage INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(40) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  identity_key VARCHAR(190) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_rl_action_ip_time (action, ip_address, created_at),
  INDEX idx_rl_identity_time (identity_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
