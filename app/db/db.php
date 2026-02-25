<?php

declare(strict_types=1);

if (!defined('DB_HOST') && file_exists(__DIR__ . '/../config/config.local.php')) {
    require_once __DIR__ . '/../config/config.local.php';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $port = defined('DB_PORT') ? DB_PORT : (int)(getenv('DB_PORT') ?: 3306);
    $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'grownitfx');
    $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int)$port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensure_payment_transactions_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `payment_transactions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    db()->exec($sql);
    $ensured = true;
}
