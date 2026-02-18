<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\DB;

final class UserModel
{
    public function findByMt5Login(int $mt5Login): ?array
    {
        $sql = 'SELECT * FROM users WHERE mt5_login = :mt5_login LIMIT 1';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute(['mt5_login' => $mt5Login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO users (name, email, mt5_login, mt5_group, mt5_leverage, created_at, updated_at)
                VALUES (:name, :email, :mt5_login, :mt5_group, :mt5_leverage, NOW(), NOW())';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mt5_login' => $data['mt5_login'],
            'mt5_group' => $data['mt5_group'],
            'mt5_leverage' => $data['mt5_leverage'],
        ]);
        return (int) DB::pdo()->lastInsertId();
    }

    public function updateFromMt5(int $mt5Login, array $data): void
    {
        $sql = 'UPDATE users SET name = :name, email = :email, mt5_group = :mt5_group, mt5_leverage = :mt5_leverage, updated_at = NOW()
                WHERE mt5_login = :mt5_login';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? null,
            'mt5_group' => $data['mt5_group'] ?? '',
            'mt5_leverage' => $data['mt5_leverage'] ?? 100,
            'mt5_login' => $mt5Login,
        ]);
    }
}
