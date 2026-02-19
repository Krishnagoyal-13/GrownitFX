<?php

declare(strict_types=1);

namespace App\MT5\API\USER;

use App\MT5\HttpClient;

final class Add
{
    public function __construct(private readonly HttpClient $client)
    {
    }

    public function execute(string $name, string $email, string $country, string $passMain, string $passInvestor): array
    {
        return $this->client->post('/api/user/add', [
            'group' => MT5_GROUP,
            'name' => $name,
            'leverage' => MT5_LEVERAGE,
        ], [
            'PassMain' => $passMain,
            'PassInvestor' => $passInvestor,
            'Email' => $email,
            'Country' => $country,
        ]);
    }
}
