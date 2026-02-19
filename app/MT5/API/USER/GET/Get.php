<?php

declare(strict_types=1);

namespace App\MT5\API\USER\GET;

use App\MT5\HttpClient;

final class Get
{
    public function __construct(private readonly HttpClient $client)
    {
    }

    public function execute(string $loginId): array
    {
        return $this->client->get('/api/user/get', [
            'login' => $loginId,
        ]);
    }
}
