<?php declare(strict_types=1); ?>
<h1>Open Account</h1>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form method="post" action="<?= htmlspecialchars((string)$basePath, ENT_QUOTES, 'UTF-8') ?>/register" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">

  <label for="name">Name</label>
  <input id="name" name="name" type="text" required minlength="2" maxlength="100">

  <label for="email">Email</label>
  <input id="email" name="email" type="email" required>

  <label for="mt5_password">MT5 Password (8-32 chars)</label>
  <input id="mt5_password" name="mt5_password" type="password" required minlength="8" maxlength="32">

  <label for="group">Group</label>
  <input id="group" name="group" type="text" required value="<?= htmlspecialchars((string)$defaultGroup, ENT_QUOTES, 'UTF-8') ?>">

  <label for="leverage">Leverage (1-2000)</label>
  <input id="leverage" name="leverage" type="number" min="1" max="2000" required value="<?= (int)$defaultLeverage ?>">

  <button type="submit">Register</button>
</form>
<p><a href="<?= htmlspecialchars((string)$basePath, ENT_QUOTES, 'UTF-8') ?>/login">Have an account? Login</a></p>
