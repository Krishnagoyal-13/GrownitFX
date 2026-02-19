<?php

declare(strict_types=1);

namespace App\Db;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        try {
            self::$connection = self::connectWithDatabase();
        } catch (PDOException $exception) {
            if (!self::isUnknownDatabaseError($exception)) {
                throw $exception;
            }

            self::createDatabase();
            self::$connection = self::connectWithDatabase();
        }

        self::ensureSchema(self::$connection);

        return self::$connection;
    }

    private static function connectWithDatabase(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function createDatabase(): void
    {
        $adminDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
        $admin = new PDO($adminDsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $databaseName = str_replace('`', '``', DB_NAME);
        $admin->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));
    }

    private static function ensureSchema(PDO $connection): void
    {
        $schemaFile = __DIR__ . '/schema.sql';
        if (!is_file($schemaFile)) {
            return;
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        $connection->exec($sql);
    }

    private static function isUnknownDatabaseError(PDOException $exception): bool
    {
        $message = $exception->getMessage();
        $driverCode = (int)($exception->errorInfo[1] ?? 0);

        return $driverCode === 1049 || str_contains($message, 'Unknown database');
    }
}
