<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function name(string $name): bool
    {
        $len = mb_strlen(trim($name));
        return $len >= 2 && $len <= 100;
    }

    public static function password(string $password): bool
    {
        $len = strlen($password);
        return $len >= 8 && $len <= 32;
    }

    public static function leverage(int $leverage): bool
    {
        return $leverage >= 1 && $leverage <= 2000;
    }

    public static function group(string $group): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\\\\\-\/]+$/', $group);
    }
}
