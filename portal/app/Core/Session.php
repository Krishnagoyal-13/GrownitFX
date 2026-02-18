<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static function detectCookiePath(): string
    {
        $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $requestPath = '/' . ltrim($requestPath, '/');
        if (preg_match('#^(.*?/portal)(?:/.*)?$#', $requestPath, $matches) === 1) {
            return rtrim($matches[1], '/');
        }

        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $phpSelf = (string)($_SERVER['PHP_SELF'] ?? '');
        $entryScript = $scriptName !== '' ? $scriptName : $phpSelf;

        if ($entryScript === '') {
            return '/portal';
        }

        $entryDir = rtrim(str_replace('\\', '/', dirname($entryScript)), '/');
        if (str_ends_with($entryDir, '/public')) {
            $entryDir = substr($entryDir, 0, -strlen('/public'));
        }

        $entryDir = '/' . ltrim($entryDir, '/');
        return $entryDir === '/' ? '/portal' : rtrim($entryDir, '/');
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::detectCookiePath(),
            'domain' => '',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }

        session_destroy();
    }
}
