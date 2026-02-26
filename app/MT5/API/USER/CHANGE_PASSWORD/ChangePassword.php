<?php

declare(strict_types=1);

namespace App\MT5\API\USER\CHANGE_PASSWORD;

use App\MT5\HttpClient;

final class ChangePassword
{
    public function __construct(private readonly HttpClient $client)
    {
    }

    public function execute(string $loginId, string $type, string $password): array
    {
        return $this->client->post('/api/user/change_password', [], [
            'Login' => $loginId,
            'Type' => $type,
            'Password' => $password,
        ]);
    }
}
