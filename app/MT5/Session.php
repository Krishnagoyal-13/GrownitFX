<?php

declare(strict_types=1);

namespace App\MT5;

final class Session
{
    public static function cookieFileFromSessionId(string $sessionId): string
    {
        return rtrim(MT5_COOKIE_DIR, '/\\') . '/mt5_' . $sessionId . '.txt';
    }
}
