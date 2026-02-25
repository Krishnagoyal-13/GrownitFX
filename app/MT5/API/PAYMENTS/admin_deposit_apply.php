<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/mt5.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../TRADE/BALANCE/balance.php';

function get_header_value(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return $_SERVER[$key] ?? null;
}

function assert_admin_access(array $cfg): void
{
    $provided = (string)(get_header_value('X-ADMIN-TOKEN') ?? '');
    if ($provided === '' || !hash_equals((string)$cfg['admin_token'], $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }

    $allowIps = $cfg['admin_allow_ips'] ?? [];
    if ($allowIps !== []) {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (!in_array($remote, $allowIps, true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Forbidden by IP']);
            exit;
        }
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    $cfg = mt5_config();
    assert_admin_access($cfg);

    $txId = $_POST['tx_id'] ?? null;
    if ($txId === null) {
        $body = json_decode((string)file_get_contents('php://input'), true);
        $txId = is_array($body) ? ($body['tx_id'] ?? null) : null;
    }

    if (!is_string($txId) || $txId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'tx_id is required']);
        exit;
    }

    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM payment_transactions WHERE tx_id = :tx_id FOR UPDATE');
    $stmt->execute([':tx_id' => $txId]);
    $tx = $stmt->fetch();

    if (!$tx) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if ($tx['status'] === 'applied') {
        $pdo->commit();
        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'tx_id' => $txId,
            'status' => 'applied',
            'ticket' => $tx['mt5_ticket'],
            'retcode' => $tx['retcode'],
        ]);
        exit;
    }

    if ($tx['type'] !== 'deposit' || !in_array($tx['status'], ['pending', 'paid', 'approved'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Transaction cannot be applied', 'status' => $tx['status']]);
        exit;
    }

    $comment = mb_substr('DEP:' . $txId, 0, 32);
    $result = mt5_trade_balance((int)$tx['login'], 2, number_format((float)$tx['amount'], 2, '.', ''), $comment);

    $newStatus = $result['ok'] ? 'applied' : 'failed';
    $update = $pdo->prepare('UPDATE payment_transactions SET status = :status, mt5_ticket = :ticket, retcode = :retcode, details_json = :details WHERE tx_id = :tx_id');
    $update->execute([
        ':status' => $newStatus,
        ':ticket' => $result['ticket'],
        ':retcode' => $result['retcode'] ?? null,
        ':details' => json_encode($result['raw'] ?? $result),
        ':tx_id' => $txId,
    ]);

    $pdo->commit();

    http_response_code($result['ok'] ? 200 : 500);
    echo json_encode([
        'ok' => (bool)$result['ok'],
        'tx_id' => $txId,
        'status' => $newStatus,
        'ticket' => $result['ticket'] ?? null,
        'retcode' => $result['retcode'] ?? null,
        'details' => $result['raw'] ?? null,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
