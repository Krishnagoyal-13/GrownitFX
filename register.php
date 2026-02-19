<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\Db\Database;
use App\Db\UserRepository;
use App\MT5\HttpClient;
use App\MT5\Session;
use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\Add;

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $country === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    $validPassword = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,16}$/', $password) === 1;
    if (!$validPassword) {
        $errors[] = 'Password must be 8-16 chars with lowercase, uppercase, and number (letters and digits only).';
    }

    if ($errors === []) {
        try {
            $repository = new UserRepository(Database::connection());
            if ($repository->findByEmail($email) !== null) {
                $errors[] = 'Email is already registered.';
            } else {
                $sessionId = session_id();
                $cookieFile = Session::cookieFileFromSessionId($sessionId);
                $client = new HttpClient(MT5_BASE_URL, $cookieFile);

                try {
                    (new Authentication($client))->authenticateManager();

                    $passInvestor = substr(bin2hex(random_bytes(16)), 0, 16);
                    $addResponse = (new Add($client))->execute($name, $email, $country, $password, $passInvestor);

                    $retcode = (string)($addResponse['retcode'] ?? '');
                    $mt5LoginId = (string)($addResponse['answer']['Login'] ?? '');

                    if (!str_starts_with($retcode, '0')) {
                        $errors[] = sprintf('MT5 /api/user/add failed. retcode=%s response=%s', $retcode, json_encode($addResponse));
                    } elseif ($mt5LoginId === '') {
                        $errors[] = sprintf('MT5 /api/user/add failed. Missing answer.Login response=%s', json_encode($addResponse));
                    } else {
                        $repository->create($name, $country, $email, password_hash($password, PASSWORD_DEFAULT), $mt5LoginId);

                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_login_id'] = $mt5LoginId;

                        header('Location: dashboard.php');
                        exit;
                    }
                } finally {
                    $client->close();
                }
            }
        } catch (Throwable $throwable) {
            $errors[] = 'Registration failed: ' . $throwable->getMessage();
        }
    }

    if ($errors === []) {
        $successMessage = 'Registration completed.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/icomoon.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body.auth-page {
            min-height: 100vh;
            background: linear-gradient(rgba(18, 18, 18, 0.72), rgba(18, 18, 18, 0.72)), url('images/bg.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }
        .auth-card h1 { margin-top: 0; }
        .form-control { width: 100%; margin-top: 8px; margin-bottom: 14px; }
        .alert-error, .alert-success {
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 14px;
            word-break: break-word;
        }
        .alert-error { background: #fce8e8; color: #a94442; }
        .alert-success { background: #e6f7ec; color: #2f6b43; }
        .helper-link { margin-top: 12px; text-align: center; }
    </style>
</head>
<body class="auth-page">
<div class="auth-card">
    <h1><i class="icon-user-follow"></i> Create Account</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="register.php">
        <label for="name">Name</label>
        <input id="name" class="form-control" type="text" name="name" required>

        <label for="country">Country</label>
        <input id="country" class="form-control" type="text" name="country" required>

        <label for="email">Email</label>
        <input id="email" class="form-control" type="email" name="email" required>

        <label for="password">Password</label>
        <input id="password" class="form-control" type="password" name="password" required>

        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>

    <p class="helper-link"><a href="login.php">Already have an account? Login</a></p>
</div>
</body>
</html>
