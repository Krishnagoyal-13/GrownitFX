<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/app/config/config.local.php';
require_once __DIR__ . '/app/bootstrap.php';

use App\MT5\HttpClient;
use App\MT5\Session;
use App\MT5\API\AUTH\Authentication;
use App\MT5\API\USER\GET\Get;

if (!isset($_SESSION['user_login_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$userData = [];
$loginId = (string)$_SESSION['user_login_id'];

try {
    $sessionId = session_id();
    $cookieFile = Session::cookieFileFromSessionId($sessionId);
    $client = new HttpClient(MT5_BASE_URL, $cookieFile);

    try {
        (new Authentication($client))->authenticateManager();
        $getResponse = (new Get($client))->execute($loginId);
        $retcode = (string)($getResponse['retcode'] ?? '');

        if (!str_starts_with($retcode, '0')) {
            $errors[] = sprintf('MT5 /api/user/get failed. retcode=%s response=%s', $retcode, json_encode($getResponse));
        } else {
            $userData = is_array($getResponse['answer'] ?? null) ? $getResponse['answer'] : [];
            if ($userData === []) {
                $errors[] = sprintf('MT5 /api/user/get returned empty answer. response=%s', json_encode($getResponse));
            }
        }
    } finally {
        $client->close();
    }
} catch (Throwable $throwable) {
    $errors[] = 'Dashboard data fetch failed: ' . $throwable->getMessage();
}

$essentialFields = [
    'Login',
    'Name',
    'Email',
    'Country',
    'Group',
    'Leverage',
    'Balance',
    'Credit',
    'Registration',
    'LastAccess',
    'LastPassChange',
    'LastIP',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | GrownitFX</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/icomoon.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body.dashboard-page { min-height: 100vh; margin: 0; background: #f4f7fb; font-family: "Open Sans", Arial, sans-serif; color: #1d2939; }
        .topbar { background: #233142; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-wrap { max-width: 1080px; margin: 28px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 16px; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); padding: 28px; }
        .meta { color: #5f6670; margin-top: 12px; }
        .alert-error { background: #fce8e8; color: #a94442; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 14px; word-break: break-word; }
        .kv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 18px; }
        .kv-item { border: 1px solid #edf0f4; border-radius: 10px; padding: 10px 12px; background: #fbfcfe; }
        .kv-item strong { display: block; font-size: 12px; color: #667085; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }

        .wallet { margin-top: 28px; border-top: 1px solid #edf0f4; padding-top: 24px; }
        .wallet h2 { margin: 0 0 8px 0; }
        .wallet-grid { margin-top: 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 14px; }
        .wallet-card { border: 1px solid #e7edf5; border-radius: 14px; padding: 16px; background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%); }
        .wallet-card h3 { margin: 0 0 6px 0; font-size: 18px; }
        .wallet-card p { margin: 0 0 12px 0; color: #667085; font-size: 13px; }
        .wallet-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; }
        .wallet-row input { border: 1px solid #cfd9e5; border-radius: 8px; padding: 10px; font-size: 14px; }
        .wallet-row button { border-radius: 8px; border: none; color: #fff; cursor: pointer; padding: 10px 14px; font-weight: 600; }
        .wallet-row button:disabled { opacity: .65; cursor: not-allowed; }
        .btn-deposit { background: #16a34a; }
        .btn-withdraw { background: #2563eb; }
        .wallet-status { margin-top: 14px; border-radius: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 14px; }
        .wallet-status.is-error { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
        .wallet-status.is-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    </style>
</head>
<body class="dashboard-page">
<header class="topbar">
    <strong><i class="icon-speedometer"></i> GrownitFX Dashboard</strong>
    <a href="logout.php" class="btn btn-primary btn-sm">Logout</a>
</header>

<div class="dashboard-wrap">
    <div class="panel">
        <h1>Welcome<?php if (isset($_SESSION['user_email'])): ?>, <?= htmlspecialchars((string)$_SESSION['user_email'], ENT_QUOTES, 'UTF-8') ?><?php else: ?>, Trader<?php endif; ?>!</h1>
        <p class="meta">Live data fetched from MT5 <code>/api/user/get</code> for login ID <strong><?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?></strong>.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php if ($userData !== []): ?>
            <div class="kv-grid">
                <?php foreach ($essentialFields as $field): ?>
                    <?php if (array_key_exists($field, $userData)): ?>
                        <div class="kv-item">
                            <strong><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string)$userData[$field], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="wallet">
            <h2>Wallet</h2>
            <p class="meta">Create deposit or withdraw and apply directly on MT5.</p>

            <div class="wallet-grid">
                <div class="wallet-card">
                    <h3>Deposit</h3>
                    <p>Submit a positive amount to credit your MT5 balance.</p>
                    <div class="wallet-row">
                        <input type="number" step="0.01" min="0.01" id="depositAmount" placeholder="Enter deposit amount" inputmode="decimal">
                        <button type="button" class="btn-deposit" id="depositBtn">Apply</button>
                    </div>
                </div>

                <div class="wallet-card">
                    <h3>Withdraw</h3>
                    <p>Submit a positive amount to debit your MT5 balance (with margin check).</p>
                    <div class="wallet-row">
                        <input type="number" step="0.01" min="0.01" id="withdrawAmount" placeholder="Enter withdrawal amount" inputmode="decimal">
                        <button type="button" class="btn-withdraw" id="withdrawBtn">Apply</button>
                    </div>
                </div>
            </div>

            <div class="wallet-status" id="walletStatus">No request submitted yet.</div>
        </section>
    </div>
</div>
<script>
(function () {
    const statusBox = document.getElementById('walletStatus');
    const depositBtn = document.getElementById('depositBtn');
    const withdrawBtn = document.getElementById('withdrawBtn');

    function setStatus(message, type) {
        statusBox.textContent = message;
        statusBox.classList.remove('is-error', 'is-success');
        if (type === 'error') {
            statusBox.classList.add('is-error');
        } else if (type === 'success') {
            statusBox.classList.add('is-success');
        }
    }

    async function submitWalletApply(url, amount, clickedBtn) {
        const parsed = Number(amount);
        if (!Number.isFinite(parsed) || parsed <= 0) {
            setStatus('Please enter a valid amount greater than 0.', 'error');
            return;
        }

        const body = new URLSearchParams();
        body.set('amount', parsed.toFixed(2));

        depositBtn.disabled = true;
        withdrawBtn.disabled = true;
        setStatus('Submitting request...', null);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: body.toString(),
                credentials: 'same-origin'
            });

            const rawText = await response.text();
            let payload = null;

            try {
                payload = JSON.parse(rawText);
            } catch (parseError) {
                const snippet = rawText.replace(/\s+/g, ' ').trim().slice(0, 160);
                throw new Error('Server returned non-JSON response (HTTP ' + response.status + '). ' + snippet);
            }

            const replyText = payload && payload.server_replied ? ' | server_reply=YES' : ' | server_reply=NO';

            if (!response.ok || !payload.ok) {
                const retcodeText = payload && payload.retcode ? (' | retcode=' + payload.retcode) : '';
                const detailText = payload && payload.details ? (' | ' + payload.details) : '';
                setStatus('Apply failed: ' + (payload.error || ('HTTP ' + response.status)) + retcodeText + replyText + detailText, 'error');
                return;
            }

            const ticketText = payload.ticket ? (', ticket=' + payload.ticket) : '';
            const retcodeText = payload.retcode ? (', retcode=' + payload.retcode) : '';
            setStatus('MT5 request processed. tx_id=' + payload.tx_id + ', status=' + payload.status + ticketText + retcodeText + replyText, 'success');
        } catch (error) {
            setStatus(error.message || 'Unknown request error.', 'error');
        } finally {
            depositBtn.disabled = false;
            withdrawBtn.disabled = false;
            if (clickedBtn && clickedBtn.previousElementSibling) {
                clickedBtn.previousElementSibling.focus();
            }
        }
    }

    depositBtn.addEventListener('click', function () {
        submitWalletApply('app/MT5/API/PAYMENTS/deposit_request.php', document.getElementById('depositAmount').value, depositBtn);
    });

    withdrawBtn.addEventListener('click', function () {
        submitWalletApply('app/MT5/API/PAYMENTS/withdraw_request.php', document.getElementById('withdrawAmount').value, withdrawBtn);
    });
})();
</script>
</body>
</html>
