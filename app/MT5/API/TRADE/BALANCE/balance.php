<?php

declare(strict_types=1);

require_once __DIR__ . '/../../AUTH/ensure_manager.php';
require_once __DIR__ . '/../../../../config/mt5.php';

const MT5_DEAL_BALANCE = 2;
const MT5_DEAL_CHARGE = 4;

/**
 * @return array{body:string|false,http_code:int,curl_error:string,request_url:string,method:string}
 */
function mt5_trade_balance_call(string $baseUrl, string $cookiePath, array $query, string $method): array
{
    $url = rtrim($baseUrl, '/') . '/api/trade/balance?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $curl = curl_init($url);

    if ($curl === false) {
        return [
            'body' => false,
            'http_code' => 0,
            'curl_error' => 'Failed to initialize cURL',
            'request_url' => $url,
            'method' => $method,
        ];
    }

    $methodUpper = strtoupper($method);
    $isPost = $methodUpper === 'POST';

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookiePath,
        CURLOPT_COOKIEJAR => $cookiePath,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPGET => !$isPost,
        CURLOPT_POST => $isPost,
        CURLOPT_POSTFIELDS => $isPost ? '' : null,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Connection: Keep-Alive',
            'User-Agent: ' . (defined('MT5_AGENT') ? (string) MT5_AGENT : 'agent'),
        ],
    ]);

    $responseBody = curl_exec($curl);
    $curlErr = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return [
        'body' => $responseBody,
        'http_code' => $httpCode,
        'curl_error' => $curlErr,
        'request_url' => $url,
        'method' => $methodUpper,
    ];
}

function mt5_trade_balance($login, $type, $balance, $comment, $check_margin = null): array
{
    $cfg = mt5_config();
    $cookiePath = mt5_ensure_manager_cookie();

    $comment = mb_substr((string) $comment, 0, 32);
    $query = [
        'login' => (int) $login,
        'type' => (int) $type,
        'balance' => (string) $balance,
        'comment' => $comment,
    ];

    if ($check_margin !== null) {
        $query['check_margin'] = (int) $check_margin;
    }

    $attempts = [
        mt5_trade_balance_call((string) $cfg['base_url'], $cookiePath, $query, 'GET'),
    ];

    if (($attempts[0]['http_code'] ?? 0) === 403) {
        $attempts[] = mt5_trade_balance_call((string) $cfg['base_url'], $cookiePath, $query, 'POST');
    }

    $final = $attempts[count($attempts) - 1];
    $responseBody = $final['body'];
    $httpCode = (int) ($final['http_code'] ?? 0);

    if ($responseBody === false) {
        return [
            'ok' => false,
            'error' => 'MT5 call failed',
            'details' => (string) ($final['curl_error'] ?? ''),
            'http_code' => $httpCode,
            'server_replied' => $httpCode > 0,
            'request_url' => $final['request_url'] ?? null,
            'request_method' => $final['method'] ?? null,
            'raw_response_text' => '',
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
            'request_url' => $final['request_url'] ?? null,
            'request_method' => $final['method'] ?? null,
            'raw_response_text' => (string) $responseBody,
        ];
    }

    $retcode = (string) ($decoded['retcode'] ?? '');
    $ticket = isset($decoded['answer']['Ticket']) ? (string) $decoded['answer']['Ticket'] : null;

    return [
        'ok' => str_starts_with($retcode, '0'),
        'retcode' => $retcode,
        'ticket' => $ticket,
        'answer' => $decoded['answer'] ?? null,
        'http_code' => $httpCode,
        'server_replied' => $httpCode > 0,
        'request_url' => $final['request_url'] ?? null,
        'request_method' => $final['method'] ?? null,
        'raw_response_text' => (string) $responseBody,
        'raw' => $decoded,
    ];
}
