<?php

declare(strict_types=1);

if (!defined('MT5_BASE_URL') && file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

function mt5_config(): array
{
    $defaultCookiePath = realpath(__DIR__ . '/../../storage/mt5cookies') ?: (__DIR__ . '/../../storage/mt5cookies');

    $adminAllowIps = getenv('ADMIN_ALLOW_IPS') ?: '';
    $allowList = array_values(array_filter(array_map('trim', explode(',', $adminAllowIps))));

    return [
        'base_url' => defined('MT5_BASE_URL') ? MT5_BASE_URL : (getenv('MT5_BASE_URL') ?: ''),
        'manager_login' => defined('MT5_MANAGER_LOGIN') ? (string)MT5_MANAGER_LOGIN : (getenv('MT5_MANAGER_LOGIN') ?: ''),
        'manager_password' => defined('MT5_MANAGER_PASSWORD') ? (string)MT5_MANAGER_PASSWORD : (getenv('MT5_MANAGER_PASSWORD') ?: ''),
        'version' => defined('MT5_VERSION') ? (int)MT5_VERSION : (int)(getenv('MT5_VERSION') ?: 484),
        'agent' => defined('MT5_AGENT') ? (string)MT5_AGENT : (getenv('MT5_AGENT') ?: 'agent'),
        'manager_cookie' => getenv('MT5_MANAGER_COOKIE') ?: ($defaultCookiePath . '/manager.cookie'),
        'admin_token' => getenv('ADMIN_TOKEN') ?: 'CHANGE_ME_ADMIN_TOKEN',
        'admin_allow_ips' => $allowList,
    ];
}
