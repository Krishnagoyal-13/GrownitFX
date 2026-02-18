<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Services\MT5WebApiClient;
use Throwable;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $mt5Login = (int)Session::get('mt5_login', 0);
        if ($mt5Login <= 0) {
            $this->redirect('/portal/login/index.php');
        }

        $client = new MT5WebApiClient();
        $userInfo = null;
        $accountInfo = null;
        $error = null;

        try {
            $userInfo = $client->getUser($mt5Login);
            $accountInfo = $client->getUserAccount($mt5Login);
        } catch (Throwable) {
            $error = 'Unable to load MT5 data right now.';
        }

        $this->render('dashboard/index', [
            'csrf' => Csrf::token(),
            'mt5Login' => $mt5Login,
            'userInfo' => $userInfo,
            'accountInfo' => $accountInfo,
            'error' => $error,
        ]);
    }
}
