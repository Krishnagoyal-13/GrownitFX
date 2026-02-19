<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\MT5\HttpClient;
use App\MT5\Session;
use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\GET\Get;

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$userData = [];
$loginId = (string)$_SESSION['user_login_id'];

try {
    $sessionId = session_id();
    $cookieFile = Session::cookieFileFromSessionId($sessionId);
    $client = new HttpClient(MT5_BASE_URL, $cookieFile);

    try {
        (new Authentication($client))->authenticateManager();
        $getResponse = (new Get($client))->execute($loginId);
        $retcode = (string)($getResponse['retcode'] ?? '');

        if (!str_starts_with($retcode, '0')) {
            $errors[] = sprintf('MT5 /api/user/get failed. retcode=%s response=%s', $retcode, json_encode($getResponse));
        } else {
            $userData = is_array($getResponse['answer'] ?? null) ? $getResponse['answer'] : [];
            if ($userData === []) {
                $errors[] = sprintf('MT5 /api/user/get returned empty answer. response=%s', json_encode($getResponse));
            }
        }
    } finally {
        $client->close();
    }
} catch (Throwable $throwable) {
    $errors[] = 'Dashboard data fetch failed: ' . $throwable->getMessage();
}

$essentialFields = [
    'Login',
    'Name',
    'Email',
    'Country',
    'Group',
    'Leverage',
    'Balance',
    'Credit',
    'Registration',
    'LastAccess',
    'LastPassChange',
    'LastIP',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/icomoon.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body.dashboard-page { min-height: 100vh; margin: 0; background: #f5f7fb; font-family: "Open Sans", Arial, sans-serif; }
        .topbar { background: #233142; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-wrap { max-width: 980px; margin: 28px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 26px; }
        .meta { color: #5f6670; margin-top: 12px; }
        .alert-error { background: #fce8e8; color: #a94442; border-radius: 6px; padding: 10px; margin-bottom: 10px; font-size: 14px; word-break: break-word; }
        .kv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 18px; }
        .kv-item { border: 1px solid #edf0f4; border-radius: 8px; padding: 10px 12px; background: #fbfcfe; }
        .kv-item strong { display: block; font-size: 12px; color: #667085; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
    </style>
</head>
<body class="dashboard-page">
<header class="topbar">
    <strong><i class="icon-speedometer"></i> GrownitFX Dashboard</strong>
    <a href="logout.php" class="btn btn-primary btn-sm">Logout</a>
</header>

<div class="dashboard-wrap">
    <div class="panel">
        <h1>Welcome<?php if (isset($_SESSION['user_email'])): ?>, <?= htmlspecialchars((string)$_SESSION['user_email'], ENT_QUOTES, 'UTF-8') ?><?php else: ?>, Trader<?php endif; ?>!</h1>
        <p class="meta">Live data fetched from MT5 <code>/api/user/get</code> for login ID <strong><?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></strong>.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php if ($userData !== []): ?>
            <div class="kv-grid">
                <?php foreach ($essentialFields as $field): ?>
                    <?php if (array_key_exists($field, $userData)): ?>
                        <div class="kv-item">
                            <strong><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string)$userData[$field], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
