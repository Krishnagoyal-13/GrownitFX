<?php

declare(strict_types=1);

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../db/db.php';

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

    $txId = 'W' . substr(bin2hex(random_bytes(6)), 0, 12);

    $stmt = db()->prepare('INSERT INTO payment_transactions (tx_id, login, type, amount, status) VALUES (:tx_id, :login, :type, :amount, :status)');
    $stmt->execute([
        ':tx_id' => $txId,
        ':login' => (int)$login,
        ':type' => 'withdraw',
        ':amount' => $amount,
        ':status' => 'pending',
    ]);

    http_response_code(200);
    echo json_encode(['ok' => true, 'tx_id' => $txId, 'status' => 'pending']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
