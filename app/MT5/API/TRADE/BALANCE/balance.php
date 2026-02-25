<?php

declare(strict_types=1);

require_once __DIR__ . '/../../AUTH/ensure_manager.php';
require_once __DIR__ . '/../../../../config/mt5.php';

const MT5_DEAL_BALANCE = 2;
const MT5_DEAL_CHARGE = 4;

function mt5_trade_balance($login, $type, $balance, $comment, $check_margin = null): array
{
    $cfg = mt5_config();
    $cookiePath = mt5_ensure_manager_cookie();

    $comment = mb_substr((string)$comment, 0, 32);
    $query = [
        'login' => (int)$login,
        'type' => (int)$type,
        'balance' => (string)$balance,
        'comment' => $comment,
    ];
    if ($check_margin !== null) {
        $query['check_margin'] = (int)$check_margin;
    }

    $url = rtrim((string)$cfg['base_url'], '/') . '/api/trade/balance?' . http_build_query($query);

    $curl = curl_init($url);
    if ($curl === false) {
        return ['ok' => false, 'error' => 'Failed to initialize cURL'];
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPGET => true,
        CURLOPT_POST => false,
        CURLOPT_COOKIEFILE => $cookiePath,
        CURLOPT_COOKIEJAR => $cookiePath,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $responseBody = curl_exec($curl);
    $curlErr = curl_error($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        return [
            'ok' => false,
            'error' => 'MT5 call failed',
            'details' => $curlErr,
            'http_code' => $httpCode,
            'server_replied' => $httpCode > 0,
            'request_url' => $url,
            'raw_response_text' => (string)$responseBody,
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid MT5 JSON response',
            'details' => $responseBody,
            'http_code' => $httpCode,
            'server_replied' => $httpCode > 0,
            'request_url' => $url,
            'raw_response_text' => (string)$responseBody,
        ];
    }

    $retcode = (string)($decoded['retcode'] ?? '');
    $ticket = isset($decoded['answer']['Ticket']) ? (string)$decoded['answer']['Ticket'] : null;

    return [
        'ok' => str_starts_with($retcode, '0'),
        'retcode' => $retcode,
        'ticket' => $ticket,
        'answer' => $decoded['answer'] ?? null,
        'http_code' => $httpCode,
        'server_replied' => $httpCode > 0,
        'request_url' => $url,
        'raw_response_text' => (string)$responseBody,
        'raw' => $decoded,
    ];
}
