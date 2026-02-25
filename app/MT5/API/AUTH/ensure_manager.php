<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/mt5.php';
require_once __DIR__ . '/../../../config/config.local.php';
require_once __DIR__ . '/../../../MT5/HttpClient.php';
require_once __DIR__ . '/Hash.php';
require_once __DIR__ . '/Authentication.php';

use App\MT5\API\AUTH\Authentication;
use App\MT5\HttpClient;

function mt5_ensure_manager_cookie(): string
{
    $cfg = mt5_config();
    $cookiePath = $cfg['manager_cookie'];
    $cookieDir = dirname($cookiePath);

    if (!is_dir($cookieDir) && !mkdir($cookieDir, 0755, true) && !is_dir($cookieDir)) {
        throw new RuntimeException('Unable to create MT5 cookie directory: ' . $cookieDir);
    }

    if (is_file($cookiePath) && (time() - (int)filemtime($cookiePath) < 1500)) {
        return $cookiePath;
    }

    if (!class_exists(Authentication::class)) {
        throw new RuntimeException('TODO: Implement manager authentication bootstrap in /app/MT5/API/AUTH before calling MT5 balance operations.');
    }

    if ($cfg['base_url'] === '' || $cfg['manager_login'] === '' || $cfg['manager_password'] === '') {
        throw new RuntimeException('Missing MT5 manager credentials/base URL. Update app/config/config.local.php or environment variables.');
    }

    $client = new HttpClient($cfg['base_url'], $cookiePath);
    try {
        (new Authentication($client))->authenticateManager();
    } catch (Throwable $e) {
        throw new RuntimeException('Failed to refresh MT5 manager cookie via existing AUTH flow: ' . $e->getMessage(), 0, $e);
    } finally {
        $client->close();
    }

    if (!is_file($cookiePath)) {
        throw new RuntimeException('Manager authentication completed but cookie file was not created: ' . $cookiePath);
    }

    return $cookiePath;
}
