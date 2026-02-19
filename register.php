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
    <title>Register</title>
</head>
<body>
<h1>Create Account</h1>
<?php foreach ($errors as $error): ?>
    <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
<?php if ($successMessage !== ''): ?>
    <p style="color:green;"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="register.php">
    <label>Name <input type="text" name="name" required></label><br><br>
    <label>Country <input type="text" name="country" required></label><br><br>
    <label>Email <input type="email" name="email" required></label><br><br>
    <label>Password <input type="password" name="password" required></label><br><br>
    <button type="submit">Register</button>
</form>
<p><a href="login.php">Already have an account? Login</a></p>
</body>
</html>
