<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\GET\Get;
use App\MT5\HttpClient;
use App\MT5\Session;

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$userData = [];
$loginId = (string) $_SESSION['user_login_id'];
$moneyStatus = (string) ($_GET['money'] ?? '');

try {
    $sessionId = session_id();
    $cookieFile = Session::cookieFileFromSessionId($sessionId);
    $client = new HttpClient(MT5_BASE_URL, $cookieFile);

    try {
        (new Authentication($client))->authenticateManager();
        $getResponse = (new Get($client))->execute($loginId);
        $retcode = (string) ($getResponse['retcode'] ?? '');

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
        body.dashboard-page { min-height: 100vh; margin: 0; background: #f4f7fb; font-family: "Open Sans", Arial, sans-serif; color: #1d2939; }
        .topbar { background: #233142; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-wrap { max-width: 1080px; margin: 28px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 16px; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); padding: 28px; }
        .meta { color: #5f6670; margin-top: 12px; }
        .alert-error { background: #fce8e8; color: #a94442; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 14px; word-break: break-word; }
        .alert-success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 14px; word-break: break-word; }
        .kv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 18px; }
        .kv-item { border: 1px solid #edf0f4; border-radius: 10px; padding: 10px 12px; background: #fbfcfe; }
        .kv-item strong { display: block; font-size: 12px; color: #667085; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
        .manage-money { margin-top: 22px; padding-top: 20px; border-top: 1px solid #edf0f4; }
        .action-links { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
        .btn-manage { display: inline-block; background: #2563eb; color: #fff; border-radius: 10px; padding: 10px 16px; text-decoration: none; font-weight: 600; }
        .btn-password { display: inline-block; background: #16a34a; color: #fff; border-radius: 10px; padding: 10px 16px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body class="dashboard-page">
<header class="topbar">
    <strong><i class="icon-speedometer"></i> GrownitFX Dashboard</strong>
    <a href="logout.php" class="btn btn-primary btn-sm">Logout</a>
</header>

<div class="dashboard-wrap">
    <div class="panel">
        <h1>Welcome<?php if (isset($_SESSION['user_email'])): ?>, <?= htmlspecialchars((string) $_SESSION['user_email'], ENT_QUOTES, 'UTF-8') ?><?php else: ?>, Trader<?php endif; ?>!</h1>
        <p class="meta">Live data fetched from MT5 <code>/api/user/get</code> for login ID <strong><?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></strong>.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php if ($moneyStatus === 'applied'): ?>
            <div class="alert-success">Money operation applied successfully. Live balance above is refreshed from MT5.</div>
        <?php endif; ?>

        <?php if ($userData !== []): ?>
            <div class="kv-grid">
                <?php foreach ($essentialFields as $field): ?>
                    <?php if (array_key_exists($field, $userData)): ?>
                        <div class="kv-item">
                            <strong><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) $userData[$field], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="manage-money">
            <h2>Account Actions</h2>
            <p class="meta">Use dedicated pages for money operations and password updates with detailed debug output if anything fails.</p>
            <div class="action-links">
                <a href="managebalance.php" class="btn-manage">Open Manage Money</a>
                <a href="change_password.php" class="btn-password">Change Password</a>
            </div>
        </section>
    </div>
</div>
</body>
</html>
