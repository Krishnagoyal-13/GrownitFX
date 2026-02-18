<?php declare(strict_types=1); ?>
<h1>Portal Login</h1>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form method="post" action="/portal/public/index.php?route=/login" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">
  <label for="mt5_login">MT5 Login</label>
  <input id="mt5_login" name="mt5_login" type="number" required min="1">

  <label for="mt5_password">MT5 Password</label>
  <input id="mt5_password" name="mt5_password" type="password" required minlength="8" maxlength="32">

  <button type="submit">Login</button>
</form>
<p><a href="/portal/public/index.php?route=/register">Create account</a></p>
