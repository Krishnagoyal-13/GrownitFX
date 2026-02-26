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

$balanceText = isset($userData['Balance']) ? number_format((float) $userData['Balance'], 2) : '0.00';
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
<body class="dashboard-page premium-dashboard">
<header class="topbar topbar-sticky">
    <div class="brand-wrap">
        <img src="images/grownit-logo-w.png" alt="GrownitFX" class="brand-logo">
        <div>
            <div class="brand-name">GrownitFX</div>
            <small class="brand-sub">Client Dashboard</small>
        </div>
    </div>

    <div class="header-cta-group">
        <a href="managebalance.php" class="btn-manage">Open Manage Money</a>
        <a href="change_password.php" class="btn-password">Change Password</a>
        <a href="webterminal.php" class="btn-webterminal">Open WebTerminal</a>
    </div>
</header>

<main class="dashboard-wrap premium-wrap">
    <?php foreach ($errors as $error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if ($moneyStatus === 'applied'): ?>
        <div class="alert-success">Money operation applied successfully. Live balance has been refreshed from MT5.</div>
    <?php endif; ?>

    <section class="verify-banner card-hover">
        <div class="verify-left">
            <img src="images/kyc.jpg" alt="Verify identity" class="verify-illustration">
            <div>
                <h2>Verify Your Identity</h2>
                <p>Boost your limits and secure your profile by completing quick KYC verification.</p>
            </div>
        </div>
        <button class="btn-verify" type="button">Verify Now</button>
    </section>

    <section class="dashboard-grid">
        <article class="card asset-card card-hover">
            <h3>Total assets estimate</h3>
            <div class="asset-value-row">
                <div class="asset-value"><?= htmlspecialchars($balanceText, ENT_QUOTES, 'UTF-8') ?></div>
                <select class="currency-select" aria-label="Currency selector">
                    <option>USD</option>
                    <option>EUR</option>
                    <option>AED</option>
                </select>
            </div>
            <div class="pill-actions">
                <a href="managebalance.php" class="pill active">Deposit</a>
                <a href="managebalance.php" class="pill">Withdrawal</a>
                <a href="managebalance.php" class="pill">Transfer</a>
                <a href="managebalance.php" class="pill">History</a>
            </div>
        </article>

        <article class="card trading-card card-hover">
            <h3>Trading Account</h3>
            <div class="account-alert">
                <img src="images/signup.png" alt="Account ready" class="account-alert-icon">
                <div>
                    <strong>MT5 account created and active</strong>
                    <p>Login #<?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?> is ready. Complete setup and start trading global markets.</p>
                </div>
                <a href="webterminal.php" class="arrow-cta">→</a>
            </div>
            <a href="logout.php" class="tiny-link">Sign out</a>
        </article>
    </section>

    <section class="card markets-card card-hover">
        <h3>Markets</h3>
        <div class="market-tabs">
            <button class="tab active" type="button">Forex</button>
            <button class="tab" type="button">Crypto</button>
            <button class="tab" type="button">Shares</button>
            <button class="tab" type="button">Indices</button>
            <button class="tab" type="button">Metals</button>
            <button class="tab" type="button">Energy</button>
            <button class="tab" type="button">ETFs</button>
        </div>

        <div class="table-wrap">
            <table class="market-table">
                <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Bid</th>
                    <th>Change</th>
                    <th>Markets</th>
                    <th>Percentage</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>EURUSD</td><td>1.08425</td><td class="up">+0.0012</td><td>Forex</td><td class="up">+0.11%</td></tr>
                <tr><td>BTCUSD</td><td>64,215.20</td><td class="down">-420.35</td><td>Crypto</td><td class="down">-0.65%</td></tr>
                <tr><td>XAUUSD</td><td>2,025.10</td><td class="up">+4.20</td><td>Metals</td><td class="up">+0.21%</td></tr>
                <tr><td>US500</td><td>5,041.80</td><td class="up">+18.60</td><td>Indices</td><td class="up">+0.37%</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
