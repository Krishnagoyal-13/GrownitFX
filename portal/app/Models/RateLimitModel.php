<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\DB;

final class RateLimitModel
{
    public function hit(string $action, string $ip, ?string $identity = null): void
    {
        $sql = 'INSERT INTO rate_limits (action, ip_address, identity_key, created_at) VALUES (:action, :ip, :identity, NOW())';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute([
            'action' => $action,
            'ip' => $ip,
            'identity' => $identity,
        ]);
    }

    public function tooManyAttempts(string $action, string $ip, int $maxAttempts, int $windowMinutes, ?string $identity = null): bool
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM rate_limits
                WHERE action = :action
                  AND ip_address = :ip
                  AND (:identity IS NULL OR identity_key = :identity)
                  AND created_at >= (NOW() - INTERVAL :window MINUTE)';

        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':identity', $identity);
        $stmt->bindValue(':window', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        $count = (int)($stmt->fetch()['cnt'] ?? 0);
        return $count >= $maxAttempts;
    }
}
