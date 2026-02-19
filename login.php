<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\MT5\HttpClient;
use App\MT5\Session;
use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\CheckPassword;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($loginId === '' || $password === '') {
        $errors[] = 'Login ID and password are required.';
    }

    if ($errors === []) {
        try {
            $sessionId = session_id();
            $cookieFile = Session::cookieFileFromSessionId($sessionId);
            $client = new HttpClient(MT5_BASE_URL, $cookieFile);

            try {
                (new Authentication($client))->authenticateManager();
                $checkResponse = (new CheckPassword($client))->execute($loginId, $password);
                $retcode = (string)($checkResponse['retcode'] ?? '');

                if (!str_starts_with($retcode, '0')) {
                    $errors[] = sprintf('MT5 /api/user/check_password failed. retcode=%s response=%s', $retcode, json_encode($checkResponse));
                } else {
                    $_SESSION['user_login_id'] = $loginId;

                    header('Location: dashboard.php');
                    exit;
                }
            } finally {
                $client->close();
            }
        } catch (Throwable $throwable) {
            $errors[] = 'Login failed: ' . $throwable->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/icomoon.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body.auth-page {
            min-height: 100vh;
            background: linear-gradient(rgba(20, 20, 20, 0.72), rgba(20, 20, 20, 0.72)), url('images/hero_bg.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }
        .auth-card h1 { margin-top: 0; }
        .form-control { width: 100%; margin-top: 8px; margin-bottom: 16px; }
        .helper-link { margin-top: 14px; text-align: center; }
        .alert-error {
            background: #fce8e8;
            color: #a94442;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 14px;
            word-break: break-word;
        }
    </style>
</head>
<body class="auth-page">
<div class="auth-card">
    <h1><i class="icon-lock"></i> Login</h1>
    <?php foreach ($errors as $error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" action="login.php">
        <label for="login_id">Login ID</label>
        <input id="login_id" class="form-control" type="text" name="login_id" required>

        <label for="password">Password</label>
        <input id="password" class="form-control" type="password" name="password" required>

        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>

    <p class="helper-link"><a href="register.php">Need an account? Register</a></p>
</div>
</body>
</html>
