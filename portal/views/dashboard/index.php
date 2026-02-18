<?php declare(strict_types=1); ?>
<div class="nav">
  <h1>Dashboard</h1>
  <form method="post" action="/portal/public/index.php?route=/logout">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Logout</button>
  </form>
</div>
<p>Logged in MT5 Login: <strong><?= (int)$mt5Login ?></strong></p>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<h2>MT5 User Info (raw JSON)</h2>
<pre><?= htmlspecialchars(json_encode($userInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>

<h2>MT5 Account Info (raw JSON)</h2>
<pre><?= htmlspecialchars(json_encode($accountInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
