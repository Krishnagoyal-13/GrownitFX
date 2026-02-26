<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$loginId = (string) $_SESSION['user_login_id'];

$defaultWebterminal = rtrim((string) MT5_BASE_URL, '/') . '/terminal';
$configuredWebterminal = defined('WEBTERMINAL_URL') ? trim((string) WEBTERMINAL_URL) : '';
$webterminalBase = $configuredWebterminal !== '' ? $configuredWebterminal : $defaultWebterminal;

$params = [
    'mode' => 'connect',
    'login' => $loginId,
    'marketwatch' => 'EURUSD,GBPUSD,USDJPY',
    'utm_campaign' => 'webterminal',
    'utm_source' => 'dashboard',
];

$iframeSrc = $webterminalBase . (str_contains($webterminalBase, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WebTerminal | GrownitFX</title>
    <style>
        body { margin: 0; font-family: "Open Sans", Arial, sans-serif; background: #f4f7fb; color: #1d2939; }
        .topbar { background: #233142; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .wrap { max-width: 1280px; margin: 20px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); padding: 16px; }
        .meta { color: #5f6670; margin: 8px 0 12px; font-size: 14px; }
        .iframe-wrap { border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
        iframe { width: 100%; height: 900px; border: 0; }
        .btn-back { background: #e2e8f0; color: #1f2937; border-radius: 8px; padding: 8px 12px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<header class="topbar">
    <strong>MetaTrader 5 WebTerminal</strong>
    <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
</header>
<div class="wrap">
    <div class="card">
        <h1 style="margin: 0;">WebTerminal (Login pre-filled)</h1>
        <p class="meta">Your MT5 login ID <strong><?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></strong> is pre-filled. If you want to use a custom domain, set <code>WEBTERMINAL_URL</code> in config.</p>
        <p class="meta">Source: <code><?= htmlspecialchars($webterminalBase, ENT_QUOTES, 'UTF-8') ?></code></p>
        <div class="iframe-wrap">
            <iframe id="mt5-webterminal" src="<?= htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8') ?>" allow="clipboard-read; clipboard-write; fullscreen"></iframe>
        </div>
    </div>
</div>
</body>
</html>
