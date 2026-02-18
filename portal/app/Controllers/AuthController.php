<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Models\RateLimitModel;
use App\Models\UserModel;
use App\Services\MT5WebApiClient;
use Throwable;

final class AuthController extends Controller
{
    private const REGISTER_GROUP = 'demo\\forex-hedge-usd-01';
    private const REGISTER_LEVERAGE = 100;

    public function showLogin(): void
    {
        if (Session::get('mt5_login')) {
            $this->redirect('/portal/dashboard/index.php');
        }

        $this->render('auth/login', [
            'csrf' => Csrf::token(),
            'error' => Session::get('flash_error'),
        ]);
        Session::remove('flash_error');
    }

    public function showRegister(): void
    {
        if (Session::get('mt5_login')) {
            $this->redirect('/portal/dashboard/index.php');
        }

        $this->render('auth/register', [
            'csrf' => Csrf::token(),
            'error' => Session::get('flash_error'),
        ]);
        Session::remove('flash_error');
    }

    public function showCredentials(): void
    {
        $creds = Session::get('issued_credentials');
        if (!is_array($creds) || empty($creds['loginId']) || !isset($creds['password'])) {
            Session::set('flash_error', 'Please complete registration first.');
            $this->redirect('/portal/register');
        }

        $this->render('auth/credentials', [
            'csrf' => Csrf::token(),
            'error' => Session::get('flash_error'),
        ]);
        Session::remove('flash_error');
    }

    public function apiUserStart(): void
    {
        if (!Csrf::verify($this->csrfFromRequest())) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid CSRF token. Please refresh the page and try again.'], 419);
            return;
        }

        $this->sendJson(['ok' => true, 'step' => 'start']);
    }

    public function apiUserAccess(): void
    {
        if (!Csrf::verify($this->csrfFromRequest())) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid CSRF token. Please refresh the page and try again.'], 419);
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['mt5_password'] ?? '');
        $group = self::REGISTER_GROUP;
        $leverage = self::REGISTER_LEVERAGE;

        $errors = [];
        if (!Validator::name($name)) { $errors[] = 'Invalid name.'; }
        if (!Validator::email($email)) { $errors[] = 'Invalid email.'; }
        if (!Validator::password($password)) { $errors[] = 'Invalid password length.'; }
        if (!Validator::group($group)) { $errors[] = 'Invalid group.'; }
        if (!Validator::leverage($leverage)) { $errors[] = 'Invalid leverage.'; }
        if ($errors !== []) {
            $this->sendJson(['ok' => false, 'error' => implode(' ', $errors)], 422);
            return;
        }

        try {
            $client = new MT5WebApiClient();
            $registration = $client->registerWebsiteUser($name, $email, $password);
            $resp = is_array($registration['response'] ?? null) ? $registration['response'] : null;
            if (!is_array($resp) || !$client->retOk($resp)) {
                throw new \RuntimeException('MT5 register failed');
            }

            $mt5Login = (int)($resp['answer']['Login'] ?? 0);
            if ($mt5Login <= 0) {
                throw new \RuntimeException('MT5 login not returned');
            }

            $userModel = new UserModel();
            $existing = $userModel->findByMt5Login($mt5Login);
            if (!$existing) {
                $userId = $userModel->create([
                    'name' => $name,
                    'email' => $email,
                    'mt5_login' => $mt5Login,
                    'mt5_group' => $group,
                    'mt5_leverage' => $leverage,
                ]);
            } else {
                $userId = (int)$existing['id'];
            }

            Session::set('issued_credentials', [
                'loginId' => (string)$mt5Login,
                'password' => $password,
            ]);
            Session::set('mt5_connected', true);
            Session::set('user_id', $userId);
            Session::set('mt5_login', $mt5Login);

            $this->sendJson([
                'connected' => true,
                'loginId' => (string)$mt5Login,
                'retcode' => (string)($resp['retcode'] ?? ''),
            ]);
        } catch (Throwable $e) {
            $this->sendJson(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function register(): void
    {
        Session::set('flash_error', 'Use the new register flow button to create account.');
        $this->redirect('/portal/register');
    }

    public function apiUserGet(): void
    {
        $creds = Session::get('issued_credentials');
        if (!is_array($creds) || empty($creds['loginId']) || !isset($creds['password'])) {
            $this->sendJson(['ok' => false, 'error' => 'Credentials not available yet. Complete registration first.'], 404);
            return;
        }

        $this->sendJson([
            'loginId' => (string)$creds['loginId'],
            'password' => (string)$creds['password'],
        ]);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            require dirname(__DIR__, 2) . '/views/errors/419.php';
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $loginInput = trim((string)($_POST['mt5_login'] ?? ''));
        $identity = $loginInput;

        $rateLimit = new RateLimitModel();
        if ($rateLimit->tooManyAttempts('login', $ip, 10, 15, $identity)) {
            Session::set('flash_error', 'Invalid credentials.');
            $this->redirect('/portal/login/index.php');
        }

        $mt5Login = (int)$loginInput;
        $password = (string)($_POST['mt5_password'] ?? '');

        if ($mt5Login <= 0 || !Validator::password($password)) {
            $rateLimit->hit('login', $ip, $identity);
            Session::set('flash_error', 'Invalid credentials.');
            $this->redirect('/portal/login/index.php');
        }

        $client = new MT5WebApiClient();

        try {
            $check = $client->checkPassword($mt5Login, $password);
            if (!is_array($check) || !$client->retOk($check)) {
                throw new \RuntimeException('Invalid');
            }

            $userModel = new UserModel();
            $local = $userModel->findByMt5Login($mt5Login);
            if (!$local) {
                $mt5User = $client->getUser($mt5Login);
                $name = (string)($mt5User['answer']['Name'] ?? ('MT5 User ' . $mt5Login));
                $email = (string)($mt5User['answer']['Email'] ?? '');
                $group = (string)($mt5User['answer']['Group'] ?? ($_ENV['DEFAULT_GROUP'] ?? ''));
                $leverage = (int)($mt5User['answer']['Leverage'] ?? ($_ENV['DEFAULT_LEVERAGE'] ?? 100));
                $userId = $userModel->create([
                    'name' => $name,
                    'email' => $email,
                    'mt5_login' => $mt5Login,
                    'mt5_group' => $group,
                    'mt5_leverage' => $leverage,
                ]);
            } else {
                $userId = (int)$local['id'];
            }

            Session::regenerate();
            Session::set('user_id', $userId);
            Session::set('mt5_login', $mt5Login);
            $this->redirect('/portal/dashboard/index.php');
        } catch (Throwable) {
            $rateLimit->hit('login', $ip, $identity);
            Session::set('flash_error', 'Invalid credentials.');
            $this->redirect('/portal/login/index.php');
        }
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            require dirname(__DIR__, 2) . '/views/errors/419.php';
            return;
        }

        Session::destroy();
        $this->redirect('/portal/login/index.php');
    }

    private function sendJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function csrfFromRequest(): ?string
    {
        $token = $_POST['_csrf'] ?? null;
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $headerKeys = [
            'HTTP_X_CSRF_TOKEN',
            'REDIRECT_HTTP_X_CSRF_TOKEN',
            'HTTP_X_CSRFTOKEN',
            'REDIRECT_HTTP_X_CSRFTOKEN',
        ];

        foreach ($headerKeys as $key) {
            $header = $_SERVER[$key] ?? null;
            if (is_string($header) && $header !== '') {
                return $header;
            }
        }

        return null;
    }
}
