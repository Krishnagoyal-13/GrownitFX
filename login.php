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
    <title>Login</title>
</head>
<body>
<h1>Login</h1>
<?php foreach ($errors as $error): ?>
    <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
<form method="post" action="login.php">
    <label>Login ID <input type="text" name="login_id" required></label><br><br>
    <label>Password <input type="password" name="password" required></label><br><br>
    <button type="submit">Login</button>
</form>
<p><a href="register.php">Need an account? Register</a></p>
</body>
</html>
