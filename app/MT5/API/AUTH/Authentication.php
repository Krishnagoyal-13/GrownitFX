<?php

declare(strict_types=1);

namespace App\MT5\API\AUTH;

use App\MT5\HttpClient;
use RuntimeException;

final class Authentication
{
    public function __construct(private readonly HttpClient $client)
    {
    }

    public function authenticateManager(): void
    {
        $start = $this->client->get('/api/auth/start', [
            'version' => MT5_VERSION,
            'agent' => MT5_AGENT,
            'login' => MT5_MANAGER_LOGIN,
            'type' => 'manager',
        ]);

        $srvRand = (string)($start['srv_rand'] ?? '');
        if (!$this->retOk($start) || $srvRand === '') {
            throw new RuntimeException('MT5 auth/start failed.');
        }

        $passHashRaw = Hash::passHashRaw(MT5_MANAGER_PASSWORD);
        $srvRandAnswer = Hash::managerAnswer($passHashRaw, $srvRand);

        $cliRandBin = random_bytes(16);
        $cliRandHex = bin2hex($cliRandBin);

        $answer = $this->client->get('/api/auth/answer', [
            'srv_rand_answer' => $srvRandAnswer,
            'cli_rand' => $cliRandHex,
        ]);

        $serverAnswer = (string)($answer['cli_rand_answer'] ?? '');
        $expected = md5($passHashRaw . $cliRandBin);

        if (!$this->retOk($answer) || $serverAnswer === '' || !hash_equals($expected, $serverAnswer)) {
            throw new RuntimeException('MT5 auth/answer validation failed.');
        }
    }

    private function retOk(array $response): bool
    {
        return str_starts_with((string)($response['retcode'] ?? ''), '0');
    }
}
