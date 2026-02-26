<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\CHANGE_PASSWORD\ChangePassword;
use App\MT5\HttpClient;
use App\MT5\Session;

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$loginId = (string) $_SESSION['user_login_id'];
$errors = [];
$success = '';
$debug = [];

$type = 'main';
$newPassword = '';
$confirmPassword = '';

function isStrongMt5Password(string $password): bool
{
    if (strlen($password) < 8 || strlen($password) > 16) {
        return false;
    }

    $hasLower = preg_match('/[a-z]/', $password) === 1;
    $hasUpper = preg_match('/[A-Z]/', $password) === 1;
    $hasDigit = preg_match('/\d/', $password) === 1;
    $hasSpecial = preg_match('/[^a-zA-Z\d]/', $password) === 1;

    return $hasLower && $hasUpper && $hasDigit && $hasSpecial;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string) ($_POST['type'] ?? 'main');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!in_array($type, ['main', 'investor', 'api'], true)) {
        $errors[] = 'Invalid password type selected.';
    }

    if ($newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Both password fields are required.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirm password do not match.';
    }

    if ($newPassword !== '' && !isStrongMt5Password($newPassword)) {
        $errors[] = 'Password must be 8-16 chars and include lowercase, uppercase, number, and special character.';
    }

    if ($errors === []) {
        try {
            $sessionId = session_id();
            $cookieFile = Session::cookieFileFromSessionId($sessionId);
            $client = new HttpClient(MT5_BASE_URL, $cookieFile);

            try {
                (new Authentication($client))->authenticateManager();
                $response = (new ChangePassword($client))->execute($loginId, $type, $newPassword);
                $retcode = (string) ($response['retcode'] ?? '');

                $debug = [
                    'retcode' => $retcode,
                    'response' => $response,
                    'session_id' => $sessionId,
                    'login_id' => $loginId,
                    'type' => $type,
                ];

                if (!str_starts_with($retcode, '0')) {
                    $errors[] = 'MT5 /api/user/change_password failed.';
                } else {
                    $success = 'Password changed successfully.';
                }
            } finally {
                $client->close();
            }
        } catch (Throwable $throwable) {
            $errors[] = 'Password change failed with server exception.';
            $debug = [
                'exception' => $throwable->getMessage(),
                'session_id' => session_id(),
                'login_id' => $loginId,
                'type' => $type,
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/portal-ui.css">
</head>
<body>
<header class="topbar">
    <strong>Change Password</strong>
    <a href="dashboard.php" class="btn btn-light">Back to Dashboard</a>
</header>

<div class="wrap">
    <div class="card">
        <h1>Change Password for Login #<?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="meta">This uses MT5 <code>/api/user/change_password</code>. Password is sent in request body (recommended).</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <div class="alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="change_password.php">
            <div class="grid">
                <div>
                    <label for="type">Password Type</label>
                    <select id="type" name="type" required>
                        <option value="main" <?= $type === 'main' ? 'selected' : '' ?>>main</option>
                        <option value="investor" <?= $type === 'investor' ? 'selected' : '' ?>>investor</option>
                        <option value="api" <?= $type === 'api' ? 'selected' : '' ?>>api</option>
                    </select>
                </div>

                <div>
                    <label for="new_password">New Password</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" maxlength="16" required>
                </div>

                <div>
                    <label for="confirm_password">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="16" required>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Change Password</button>
                <a class="btn btn-light" href="dashboard.php">Cancel</a>
            </div>
        </form>

        <?php if ($debug !== []): ?>
            <h3 style="margin-top:18px;">Debug Trace</h3>
            <div class="debug"><?= htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
