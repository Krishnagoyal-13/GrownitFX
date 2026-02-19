<?php

declare(strict_types=1);

namespace App\Db;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, country, email, password_hash, mt5_login_id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }


    public function findByMt5LoginId(string $mt5LoginId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, country, email, password_hash, mt5_login_id FROM users WHERE mt5_login_id = :mt5_login_id LIMIT 1');
        $stmt->execute(['mt5_login_id' => $mt5LoginId]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function create(string $name, string $country, string $email, string $passwordHash, string $mt5LoginId): void
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, country, email, password_hash, mt5_login_id) VALUES (:name, :country, :email, :password_hash, :mt5_login_id)');
        $stmt->execute([
            'name' => $name,
            'country' => $country,
            'email' => $email,
            'password_hash' => $passwordHash,
            'mt5_login_id' => $mt5LoginId,
        ]);
    }
}
