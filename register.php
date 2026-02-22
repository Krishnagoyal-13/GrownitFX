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

/**
 * Generate MT5-compatible password.
 * - length clamped to 8..16
 * - includes lowercase, uppercase, digit, and special char
 */
function mt5Password(int $len = 12): string
{
    $len = max(8, min(16, $len));

    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    $special = '#@!$%&*?';
    $all = $lower . $upper . $digits . $special;

    $passwordChars = [
        $lower[random_int(0, strlen($lower) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    for ($i = count($passwordChars); $i < $len; $i++) {
        $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($passwordChars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$passwordChars[$i], $passwordChars[$j]] = [$passwordChars[$j], $passwordChars[$i]];
    }

    return implode('', $passwordChars);
}

$errors = [];
$successMessage = '';
$generatedMainPassword = '';
$generatedLoginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if ($name === '' || $country === '' || $email === '') {
        $errors[] = 'All fields are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if ($errors === []) {
        try {
            $repository = new UserRepository(Database::connection());
            if ($repository->findByEmail($email) !== null) {
                $errors[] = 'Email is already registered.';
            } else {
                $mainPassword = mt5Password(12);
                $passInvestor = mt5Password(12);

                $sessionId = session_id();
                $cookieFile = Session::cookieFileFromSessionId($sessionId);
                $client = new HttpClient(MT5_BASE_URL, $cookieFile);

                try {
                    (new Authentication($client))->authenticateManager();

                    $addResponse = (new Add($client))->execute($name, $email, $country, $mainPassword, $passInvestor);

                    $retcode = (string)($addResponse['retcode'] ?? '');
                    $mt5LoginId = (string)($addResponse['answer']['Login'] ?? '');

                    if (!str_starts_with($retcode, '0')) {
                        $errors[] = sprintf('MT5 /api/user/add failed. retcode=%s response=%s', $retcode, json_encode($addResponse));
                    } elseif ($mt5LoginId === '') {
                        $errors[] = sprintf('MT5 /api/user/add failed. Missing answer.Login response=%s', json_encode($addResponse));
                    } else {
                        $repository->create($name, $country, $email, password_hash($mainPassword, PASSWORD_DEFAULT), $mt5LoginId);

                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_login_id'] = $mt5LoginId;

                        $generatedMainPassword = $mainPassword;
                        $generatedLoginId = $mt5LoginId;
                        $successMessage = 'Registration completed. Save your MT5 credentials before continuing.';
                    }
                } finally {
                    $client->close();
                }
            }
        } catch (Throwable $throwable) {
            $errors[] = 'Registration failed: ' . $throwable->getMessage();
        }
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
        .password-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }
        .password-modal__card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
            padding: 24px;
        }
        .cred-box {
            background: #f8f9ff;
            border: 1px solid #dde3f2;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-family: monospace;
            font-size: 15px;
            word-break: break-all;
        }
        .modal-actions { display: flex; gap: 10px; margin-top: 14px; }
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

        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>

    <p class="helper-link"><a href="login.php">Already have an account? Login</a></p>
</div>

<?php if ($generatedMainPassword !== '' && $generatedLoginId !== ''): ?>
    <div class="password-modal" role="dialog" aria-modal="true" aria-labelledby="generated-password-title">
        <div class="password-modal__card">
            <h3 id="generated-password-title" style="margin-top:0;">MT5 Account Created Successfully</h3>
            <p>Please save these credentials now. You may not be able to view the password again.</p>

            <strong>MT5 Login ID</strong>
            <div class="cred-box"><?= htmlspecialchars($generatedLoginId, ENT_QUOTES, 'UTF-8') ?></div>

            <strong>Main Password (PassMain)</strong>
            <div class="cred-box"><?= htmlspecialchars($generatedMainPassword, ENT_QUOTES, 'UTF-8') ?></div>

            <div class="modal-actions">
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="login.php" class="btn btn-default">Go to Login</a>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
