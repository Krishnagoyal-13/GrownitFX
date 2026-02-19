<?php

declare(strict_types=1);

namespace App\MT5\API\USER;

use App\MT5\HttpClient;

final class CheckPassword
{
    public function __construct(private readonly HttpClient $client)
    {
    }

    public function execute(string $loginId, string $password): array
    {
        return $this->client->post('/api/user/check_password', [], [
            'Login' => $loginId,
            'Type' => 'main',
            'Password' => $password,
        ]);
    }
}
