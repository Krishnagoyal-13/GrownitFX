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

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            self::$connection = new PDO($dsn, DB_USER, DB_PASS, self::pdoOptions());
        } catch (PDOException $exception) {
            $driverCode = (int)($exception->errorInfo[1] ?? 0);

            if ($driverCode !== 1049) {
                throw $exception;
            }

            self::createDatabaseAndSchema();
            self::$connection = new PDO($dsn, DB_USER, DB_PASS, self::pdoOptions());
        }

        return self::$connection;
    }

    /**
     * @return array<int, mixed>
     */
    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private static function createDatabaseAndSchema(): void
    {
        $setupDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
        $setupConnection = new PDO($setupDsn, DB_USER, DB_PASS, self::pdoOptions());

        $setupConnection->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', DB_NAME));
        $setupConnection->exec(sprintf('USE `%s`', DB_NAME));

        $schemaPath = __DIR__ . '/schema.sql';
        $schemaSql = file_get_contents($schemaPath);

        if ($schemaSql === false) {
            throw new PDOException(sprintf('Unable to read schema file at %s', $schemaPath));
        }

        $setupConnection->exec($schemaSql);
    }
}
