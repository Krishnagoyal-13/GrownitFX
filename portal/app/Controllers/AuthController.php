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
    public function showLogin(): void
    {
        if (Session::get('mt5_login')) {
            $this->redirect('/portal/dashboard');
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
            $this->redirect('/portal/dashboard');
        }

        $this->render('auth/register', [
            'csrf' => Csrf::token(),
            'error' => Session::get('flash_error'),
            'defaultGroup' => (string)($_ENV['DEFAULT_GROUP'] ?? 'demo\\forex-hedge-usd-01'),
            'defaultLeverage' => (int)($_ENV['DEFAULT_LEVERAGE'] ?? 100),
        ]);
        Session::remove('flash_error');
    }

    public function register(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            require dirname(__DIR__, 2) . '/views/errors/419.php';
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rateLimit = new RateLimitModel();
        if ($rateLimit->tooManyAttempts('register', $ip, 5, 15)) {
            Session::set('flash_error', 'Too many requests. Please try again later.');
            $this->redirect('/portal/register');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['mt5_password'] ?? '');
        $group = trim((string)($_POST['group'] ?? ($_ENV['DEFAULT_GROUP'] ?? '')));
        $leverage = (int)($_POST['leverage'] ?? ($_ENV['DEFAULT_LEVERAGE'] ?? 100));

        $errors = [];
        if (!Validator::name($name)) { $errors[] = 'Invalid name.'; }
        if (!Validator::email($email)) { $errors[] = 'Invalid email.'; }
        if (!Validator::password($password)) { $errors[] = 'Invalid password length.'; }
        if (!Validator::group($group)) { $errors[] = 'Invalid group.'; }
        if (!Validator::leverage($leverage)) { $errors[] = 'Invalid leverage.'; }

        if ($errors !== []) {
            $rateLimit->hit('register', $ip);
            Session::set('flash_error', 'Please check your input and try again.');
            $this->redirect('/portal/register');
        }

        $client = new MT5WebApiClient();
        try {
            $investor = $client->generateMt5Password();
            $resp = $client->addUser($group, $name, $leverage, $password, $investor, $email);
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

            Session::regenerate();
            Session::set('user_id', $userId);
            Session::set('mt5_login', $mt5Login);
            $this->redirect('/portal/dashboard');
        } catch (Throwable) {
            $rateLimit->hit('register', $ip);
            Session::set('flash_error', 'Unable to create account at this time.');
            $this->redirect('/portal/register');
        }
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
            $this->redirect('/portal/login');
        }

        $mt5Login = (int)$loginInput;
        $password = (string)($_POST['mt5_password'] ?? '');

        if ($mt5Login <= 0 || !Validator::password($password)) {
            $rateLimit->hit('login', $ip, $identity);
            Session::set('flash_error', 'Invalid credentials.');
            $this->redirect('/portal/login');
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
            $this->redirect('/portal/dashboard');
        } catch (Throwable) {
            $rateLimit->hit('login', $ip, $identity);
            Session::set('flash_error', 'Invalid credentials.');
            $this->redirect('/portal/login');
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
        $this->redirect('/portal/login');
    }
}
