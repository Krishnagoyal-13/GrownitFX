<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}
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
        body.dashboard-page {
            min-height: 100vh;
            margin: 0;
            background: #f5f7fb;
            font-family: "Open Sans", Arial, sans-serif;
        }
        .topbar {
            background: #233142;
            color: #fff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-wrap {
            max-width: 880px;
            margin: 28px auto;
            padding: 0 16px;
        }
        .panel {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 26px;
        }
        .meta {
            color: #5f6670;
            margin-top: 12px;
        }
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
        <p class="meta">You are now authenticated with MT5.</p>
        <hr>
        <p><strong>MT5 Login ID:</strong> <?= htmlspecialchars((string)$_SESSION['user_login_id'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
</body>
</html>
