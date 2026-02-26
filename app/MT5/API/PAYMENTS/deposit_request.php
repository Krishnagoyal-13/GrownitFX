<?php

declare(strict_types=1);

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../TRADE/BALANCE/balance.php';

try {
    ensure_payment_transactions_table();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    $login = $_SESSION['mt5_login'] ?? $_SESSION['user_login_id'] ?? null;
    if ($login === null || !is_numeric((string)$login)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $amountRaw = $_POST['amount'] ?? null;
    if ($amountRaw === null) {
        $jsonBody = json_decode((string)file_get_contents('php://input'), true);
        $amountRaw = is_array($jsonBody) ? ($jsonBody['amount'] ?? null) : null;
    }

    if (!is_numeric((string)$amountRaw)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid amount']);
        exit;
    }

    $amount = round((float)$amountRaw, 2);
    if ($amount <= 0 || $amount > 1000000000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Amount must be > 0 and <= 1000000000']);
        exit;
    }

    $txId = 'D' . substr(bin2hex(random_bytes(6)), 0, 12);

    $pdo = db();
    $insert = $pdo->prepare('INSERT INTO payment_transactions (tx_id, login, type, amount, status) VALUES (:tx_id, :login, :type, :amount, :status)');
    $insert->execute([
        ':tx_id' => $txId,
        ':login' => (int)$login,
        ':type' => 'deposit',
        ':amount' => $amount,
        ':status' => 'pending',
    ]);

    $comment = mb_substr('DEP:' . $txId, 0, 32);
    $mt5Result = mt5_trade_balance(
        (int)$login,
        MT5_DEAL_BALANCE,
        number_format(abs($amount), 2, '.', ''),
        $comment
    );

    $newStatus = $mt5Result['ok'] ? 'applied' : 'failed';
    $update = $pdo->prepare('UPDATE payment_transactions SET status = :status, mt5_ticket = :ticket, retcode = :retcode, details_json = :details WHERE tx_id = :tx_id');
    $update->execute([
        ':status' => $newStatus,
        ':ticket' => $mt5Result['ticket'] ?? null,
        ':retcode' => $mt5Result['retcode'] ?? null,
        ':details' => json_encode($mt5Result['raw'] ?? $mt5Result),
        ':tx_id' => $txId,
    ]);

    http_response_code($mt5Result['ok'] ? 200 : 500);
    $retcode = (string)($mt5Result['retcode'] ?? '');
    $httpCode = (int)($mt5Result['http_code'] ?? 0);
    $requestMethod = (string)($mt5Result['request_method'] ?? '');
    $requestUrl = (string)($mt5Result['request_url'] ?? '');
    $rawResponseText = (string)($mt5Result['raw_response_text'] ?? '');
    $transportDetails = $mt5Result['details'] ?? null;

    $detailsPayload = $mt5Result['raw'] ?? $transportDetails;

    $error = null;
    if (!$mt5Result['ok']) {
        $parts = [];
        if ($retcode !== '') {
            $parts[] = 'retcode=' . $retcode;
        }
        if ($httpCode > 0) {
            $parts[] = 'http=' . $httpCode;
        }
        if ($requestMethod !== '') {
            $parts[] = 'method=' . $requestMethod;
        }
        if ($requestUrl !== '') {
            $parts[] = 'url=' . $requestUrl;
        }

        $mt5Error = trim((string)($mt5Result['error'] ?? ''));
        if ($mt5Error !== '') {
            $parts[] = 'reason=' . $mt5Error;
        }

        if (is_string($transportDetails) && trim($transportDetails) !== '') {
            $parts[] = 'details=' . trim($transportDetails);
        }

        if ($rawResponseText !== '') {
            $parts[] = 'raw=' . mb_substr($rawResponseText, 0, 500);
        }

        $error = 'MT5 deposit apply failed' . ($parts !== [] ? ('. ' . implode(' | ', $parts)) : '');
    }

    echo json_encode([
        'ok' => (bool)$mt5Result['ok'],
        'tx_id' => $txId,
        'status' => $newStatus,
        'ticket' => $mt5Result['ticket'] ?? null,
        'retcode' => $retcode !== '' ? $retcode : null,
        'details' => $detailsPayload,
        'http_code' => $mt5Result['http_code'] ?? null,
        'server_replied' => (bool)($mt5Result['server_replied'] ?? false),
        'request_url' => $mt5Result['request_url'] ?? null,
        'request_method' => $mt5Result['request_method'] ?? null,
        'mt5_response' => $mt5Result['raw'] ?? null,
        'mt5_raw_response_text' => $mt5Result['raw_response_text'] ?? null,
        'error' => $error,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
