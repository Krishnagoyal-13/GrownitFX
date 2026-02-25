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
