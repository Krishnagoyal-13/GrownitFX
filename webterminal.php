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
    <link rel="stylesheet" href="css/portal-ui.css">
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
