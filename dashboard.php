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
    <link rel="stylesheet" href="css/portal-ui.css">
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
                <a href="webterminal.php" class="btn-webterminal">Open WebTerminal</a>
            </div>
        </section>
    </div>
</div>
</body>
</html>
