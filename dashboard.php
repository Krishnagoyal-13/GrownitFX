<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

if (!isset($_SESSION['user_email'], $_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
</head>
<body>
<h1>Dashboard</h1>
<p>Welcome, <?= htmlspecialchars((string)$_SESSION['user_email'], ENT_QUOTES, 'UTF-8') ?></p>
<p>MT5 Login ID: <?= htmlspecialchars((string)$_SESSION['user_login_id'], ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="logout.php">Logout</a></p>
</body>
</html>
