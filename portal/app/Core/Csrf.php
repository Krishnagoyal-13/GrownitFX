<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function verify(?string $token): bool
    {
        $sessionToken = Session::get(self::KEY);
        return is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token);
    }
}
