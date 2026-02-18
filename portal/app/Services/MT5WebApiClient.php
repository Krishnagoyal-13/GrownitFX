<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class MT5WebApiClient
{
    private string $server;
    private string $managerLogin;
    private string $managerPassword;
    private string $version;
    private string $agent;
    private $ch = null;
    private string $cookieFile = '';

    public function __construct()
    {
        $this->server = rtrim((string)($_ENV['MT5_SERVER'] ?? ''), '/');
        $this->managerLogin = (string)($_ENV['MT5_MANAGER_LOGIN'] ?? '');
        $this->managerPassword = (string)($_ENV['MT5_MANAGER_PASSWORD'] ?? '');
        $this->version = (string)($_ENV['MT5_VERSION'] ?? '1');
        $this->agent = (string)($_ENV['MT5_AGENT'] ?? 'PortalMT5Client/1.0');
    }

    public function withSession(callable $callback): mixed
    {
        $this->cookieFile = sys_get_temp_dir() . '/mt5_' . bin2hex(random_bytes(12)) . '.cookie';
        try {
            $this->init();
            if (!$this->auth($this->managerLogin, $this->managerPassword)) {
                throw new RuntimeException('MT5 auth failed.');
            }
            $result = $callback($this);
            $this->request('GET', '/api/quit');
            return $result;
        } finally {
            $this->shutdown();
            if ($this->cookieFile !== '' && is_file($this->cookieFile)) {
                @unlink($this->cookieFile);
            }
        }
    }

    private function init(): void
    {
        $this->ch = curl_init();
        if (!$this->ch) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Connection: Keep-Alive',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
    }

    private function shutdown(): void
    {
        if (is_resource($this->ch) || $this->ch instanceof \CurlHandle) {
            curl_close($this->ch);
        }
        $this->ch = null;
    }

    private function request(string $method, string $path, array $queryParams = [], ?array $jsonBody = null): array|false
    {
        if (!$this->ch) {
            throw new RuntimeException('cURL not initialized.');
        }

        $url = $this->server . $path;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        }

        curl_setopt($this->ch, CURLOPT_URL, $url);

        if (strtoupper($method) === 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            $payload = $jsonBody !== null ? json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
            curl_setopt($this->ch, CURLOPT_POST, false);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        }

        $response = curl_exec($this->ch);
        if ($response === false) {
            return false;
        }

        $headerSize = (int)curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : false;
    }

    public function retOk(array $resp): bool
    {
        $ret = (string)($resp['retcode'] ?? '');
        return str_starts_with($ret, '0');
    }

    private function auth(string $login, string $password): bool
    {
        $start = $this->request('GET', '/api/auth/start', [
            'version' => $this->version,
            'agent' => $this->agent,
            'login' => $login,
            'type' => 'manager',
        ]);

        if (!is_array($start) || !$this->retOk($start) || empty($start['srv_rand'])) {
            return false;
        }

        $pwUtf16le = mb_convert_encoding($password, 'UTF-16LE', 'UTF-8');
        $md5PwRaw = md5($pwUtf16le, true);
        $passHashRaw = md5($md5PwRaw . 'WebAPI', true);

        $srvRandHex = (string)$start['srv_rand'];
        $srvRandBin = hex2bin($srvRandHex);
        if ($srvRandBin === false) {
            return false;
        }

        $srvRandAnswer = md5($passHashRaw . $srvRandBin);

        $cliRandBin = random_bytes(16);
        $cliRandHex = bin2hex($cliRandBin);

        $ans = $this->request('GET', '/api/auth/answer', [
            'srv_rand_answer' => $srvRandAnswer,
            'cli_rand' => $cliRandHex,
        ]);

        if (!is_array($ans) || !$this->retOk($ans) || empty($ans['cli_rand_answer'])) {
            return false;
        }

        $expectedCliRandAnswer = md5($passHashRaw . $cliRandBin);
        if (!hash_equals($expectedCliRandAnswer, (string)$ans['cli_rand_answer'])) {
            return false;
        }

        return true;
    }

    public function ping(): array|false
    {
        return $this->withSession(fn(self $c) => $c->request('GET', '/api/ping'));
    }

    public function generateMt5Password(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public function callGet(string $path, array $queryParams = []): array|false
    {
        return $this->withSession(fn(self $c) => $c->request('GET', $path, $queryParams));
    }

    public function callPost(string $path, array $queryParams = [], array $jsonBody = []): array|false
    {
        return $this->withSession(fn(self $c) => $c->request('POST', $path, $queryParams, $jsonBody));
    }

    public function addUser(string $group, string $name, int $leverage, string $passMain, string $passInvestor, ?string $email = null, array $optionalBody = []): array|false
    {
        if ($group === '' || $name === '' || $leverage < 1 || $passMain === '' || $passInvestor === '') {
            throw new RuntimeException('Missing required parameters for /api/user/add');
        }
        $body = array_merge($optionalBody, ['PassMain' => $passMain, 'PassInvestor' => $passInvestor]);
        if ($email !== null) {
            $body['Email'] = $email;
        }
        return $this->callPost('/api/user/add', ['group' => $group, 'name' => $name, 'leverage' => $leverage], $body);
    }

    public function checkPassword(int $login, string $password): array|false
    {
        if ($login <= 0 || $password === '') {
            throw new RuntimeException('Missing required parameters for /api/user/check_password');
        }
        return $this->callPost('/api/user/check_password', ['login' => $login], ['Password' => $password]);
    }

    public function getUser(int $login): array|false
    {
        if ($login <= 0) {
            throw new RuntimeException('Missing required parameter login for /api/user/get');
        }
        return $this->callGet('/api/user/get', ['login' => $login]);
    }

    public function getUserAccount(int $login): array|false
    {
        if ($login <= 0) {
            throw new RuntimeException('Missing required parameter login for /api/user/account/get');
        }
        return $this->callGet('/api/user/account/get', ['login' => $login]);
    }

    public function userUpdate(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/update', $queryParams, $jsonBody); }
    public function userDelete(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/delete', $queryParams, $jsonBody); }
    public function userGetExternal(array $queryParams = []): array|false { return $this->callGet('/api/user/get_external', $queryParams); }
    public function userGetBatch(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/get_batch', $queryParams, $jsonBody); }
    public function userChangePassword(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/change_password', $queryParams, $jsonBody); }
    public function userAccountGetBatch(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/account/get_batch', $queryParams, $jsonBody); }
    public function userLogins(array $queryParams = []): array|false { return $this->callGet('/api/user/logins', $queryParams); }
    public function userTotal(array $queryParams = []): array|false { return $this->callGet('/api/user/total', $queryParams); }
    public function userGroup(array $queryParams = []): array|false { return $this->callGet('/api/user/group', $queryParams); }
    public function userCertificateUpdate(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/certificate/update', $queryParams, $jsonBody); }
    public function userCertificateGet(array $queryParams = []): array|false { return $this->callGet('/api/user/certificate/get', $queryParams); }
    public function userCertificateDelete(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/certificate/delete', $queryParams, $jsonBody); }
    public function userCertificateConfirm(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/certificate/confirm', $queryParams, $jsonBody); }
    public function userOtpSecretGet(array $queryParams = []): array|false { return $this->callGet('/api/user/otp_secret/get', $queryParams); }
    public function userOtpSecretSet(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/otp_secret/set', $queryParams, $jsonBody); }
    public function userSyncExternal(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/sync_external', $queryParams, $jsonBody); }
    public function userCheckBalance(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/check_balance', $queryParams, $jsonBody); }
    public function userArchiveAdd(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/archive/add', $queryParams, $jsonBody); }
    public function userArchiveGet(array $queryParams = []): array|false { return $this->callGet('/api/user/archive/get', $queryParams); }
    public function userArchiveGetBatch(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/archive/get_batch', $queryParams, $jsonBody); }
    public function userRestore(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/user/restore', $queryParams, $jsonBody); }
    public function userBackupList(array $queryParams = []): array|false { return $this->callGet('/api/user/backup/list', $queryParams); }
    public function userBackupGet(array $queryParams = []): array|false { return $this->callGet('/api/user/backup/get', $queryParams); }
    public function notificationSend(array $queryParams = [], array $jsonBody = []): array|false { return $this->callPost('/api/notification/send', $queryParams, $jsonBody); }
}
