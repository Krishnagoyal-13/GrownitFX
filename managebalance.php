<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MT5/API/TRADE/BALANCE/balance.php';

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$loginId = (string) $_SESSION['user_login_id'];
$errors = [];
$debug = [];

$action = 'add';
$type = (string) MT5_DEAL_BALANCE;
$amountInput = '';
$comment = '';
$checkMargin = true;

$typeOptions = [
    '2' => '2 — Balance operation',
    '3' => '3 — Credit operation',
    '4' => '4 — Additional add/withdraw',
    '5' => '5 — Corrective operation',
    '6' => '6 — Bonus operation',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'add');
    $type = (string) ($_POST['type'] ?? (string) MT5_DEAL_BALANCE);
    $amountInput = trim((string) ($_POST['amount'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $checkMargin = isset($_POST['check_margin']) && (string) $_POST['check_margin'] === '1';

    if (!in_array($action, ['add', 'withdraw'], true)) {
        $errors[] = 'Invalid action selected.';
    }

    if (!array_key_exists($type, $typeOptions)) {
        $errors[] = 'Invalid MT5 type selected.';
    }

    if (!is_numeric($amountInput)) {
        $errors[] = 'Amount must be numeric.';
    }

    $amount = is_numeric($amountInput) ? round((float) $amountInput, 2) : 0.0;
    if ($amount <= 0 || $amount > 1000000000) {
        $errors[] = 'Amount must be > 0 and <= 1000000000.';
    }

    if ($comment === '') {
        $txPrefix = $action === 'withdraw' ? 'WDR' : 'DEP';
        $comment = $txPrefix . ':' . substr(bin2hex(random_bytes(6)), 0, 12);
    }
    $comment = mb_substr($comment, 0, 32);

    if ($errors === []) {
        try {
            $signedAmount = $action === 'withdraw' ? (-1 * abs($amount)) : abs($amount);
            $marginFlag = $action === 'withdraw' ? ($checkMargin ? 1 : 0) : null;

            $result = mt5_trade_balance(
                (int) $loginId,
                (int) $type,
                number_format($signedAmount, 2, '.', ''),
                $comment,
                $marginFlag
            );

            $debug = [
                'ok' => (bool) ($result['ok'] ?? false),
                'retcode' => $result['retcode'] ?? null,
                'http_code' => $result['http_code'] ?? null,
                'request_method' => $result['request_method'] ?? null,
                'request_url' => $result['request_url'] ?? null,
                'details' => $result['details'] ?? null,
                'raw_response_text' => $result['raw_response_text'] ?? null,
                'raw' => $result['raw'] ?? null,
            ];

            if ((bool) ($result['ok'] ?? false) === true) {
                header('Location: dashboard.php?money=applied');
                exit;
            }

            $errors[] = 'MT5 apply failed.';
        } catch (Throwable $throwable) {
            $errors[] = 'Server exception during manage money apply.';
            $debug = [
                'exception' => $throwable->getMessage(),
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Money | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; font-family: "Open Sans", Arial, sans-serif; background: #f4f7fb; color: #1d2939; }
        .topbar { background: #233142; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .wrap { max-width: 980px; margin: 26px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 12px 30px rgba(15,23,42,.08); padding: 24px; }
        .meta { color: #5f6670; font-size: 14px; margin-top: 8px; }
        .grid { margin-top: 18px; display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 12px; }
        label { font-weight: 600; display:block; margin-bottom:6px; }
        input, select, textarea { width: 100%; border:1px solid #cfd9e5; border-radius:8px; padding:10px; font-size:14px; }
        textarea { min-height: 96px; resize: vertical; }
        .check { display:flex; align-items:center; gap:8px; margin-top: 6px; }
        .check input { width:auto; }
        .actions { margin-top: 16px; display:flex; gap:10px; align-items:center; }
        .btn { border:none; border-radius:8px; padding:10px 14px; cursor:pointer; font-weight:600; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-light { background:#e2e8f0; color:#1f2937; text-decoration:none; display:inline-block; }
        .alert-error { margin-top:12px; background:#fff1f2; border:1px solid #fecdd3; color:#9f1239; border-radius:10px; padding:10px 12px; }
        .debug { margin-top:12px; background:#0f172a; color:#e2e8f0; border-radius:10px; padding:12px; white-space:pre-wrap; word-break:break-word; font-family: ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; }
    </style>
</head>
<body>
<header class="topbar">
    <strong>Manage Money</strong>
    <a href="dashboard.php" class="btn btn-light">Back to Dashboard</a>
</header>

<div class="wrap">
    <div class="card">
        <h1>Manage Balance for Login #<?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="meta">Choose action, deal type, amount and comment. On success you will be redirected to dashboard.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <form method="post" action="managebalance.php">
            <div class="grid">
                <div>
                    <label for="action">Action</label>
                    <select id="action" name="action" required>
                        <option value="add" <?= $action === 'add' ? 'selected' : '' ?>>Add Money</option>
                        <option value="withdraw" <?= $action === 'withdraw' ? 'selected' : '' ?>>Withdraw Money</option>
                    </select>
                </div>

                <div>
                    <label for="type">MT5 Type</label>
                    <select id="type" name="type" required>
                        <?php foreach ($typeOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $type === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" max="1000000000" id="amount" name="amount" value="<?= htmlspecialchars($amountInput, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div>
                    <label for="comment">Comment (max 32)</label>
                    <input type="text" maxlength="32" id="comment" name="comment" value="<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional, auto-generated if empty">
                </div>
            </div>

            <div class="check">
                <input type="checkbox" id="check_margin" name="check_margin" value="1" <?= $checkMargin ? 'checked' : '' ?>>
                <label for="check_margin" style="margin:0; font-weight:500;">Check free margin before withdraw (check_margin=1)</label>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Apply</button>
                <a class="btn btn-light" href="dashboard.php">Cancel</a>
            </div>
        </form>

        <?php if ($debug !== []): ?>
            <h3 style="margin-top:18px;">Debug Trace</h3>
            <div class="debug"><?= htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
