<?php declare(strict_types=1); ?>
<h1>Open Account</h1>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<div id="register-error" class="error" style="display:none"></div>
<form id="register-form" method="post" action="<?= htmlspecialchars((string)$basePath, ENT_QUOTES, 'UTF-8') ?>/register" autocomplete="off">
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

  <button id="register-btn" type="submit">Register</button>
</form>
<p><a href="<?= htmlspecialchars((string)$basePath, ENT_QUOTES, 'UTF-8') ?>/login">Have an account? Login</a></p>

<script>
(() => {
  const form = document.getElementById('register-form');
  const btn = document.getElementById('register-btn');
  const err = document.getElementById('register-error');
  const basePath = <?= json_encode((string)$basePath, JSON_UNESCAPED_SLASHES) ?>;
  const apiBase = `${basePath}/public/index.php`;

  const setError = (msg) => {
    err.textContent = msg;
    err.style.display = msg ? 'block' : 'none';
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setError('');
    btn.disabled = true;
    btn.textContent = 'Connecting...';

    const payload = new FormData(form);

    try {
      const startResp = await fetch(`${apiBase}?route=/api/user/start`, {
        method: 'POST',
        body: payload,
        headers: { 'Accept': 'application/json' },
      });
      const startText = await startResp.text();
      let startJson;
      try { startJson = JSON.parse(startText); } catch { throw new Error('Server returned non-JSON response for /api/user/start.'); }
      if (!startResp.ok || !startJson.ok) {
        throw new Error(startJson.error || 'MT5 start handshake failed.');
      }

      btn.textContent = 'Authorizing...';

      const accessResp = await fetch(`${apiBase}?route=/api/user/access`, {
        method: 'POST',
        body: payload,
        headers: { 'Accept': 'application/json' },
      });
      const accessText = await accessResp.text();
      let accessJson;
      try { accessJson = JSON.parse(accessText); } catch { throw new Error('Server returned non-JSON response for /api/user/access.'); }
      if (!accessResp.ok || !accessJson.ok || accessJson.connected !== true) {
        throw new Error(accessJson.error || 'MT5 access handshake failed.');
      }

      btn.textContent = 'Connected';
      window.location.href = `${basePath}/credentials`;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Registration failed.');
      btn.disabled = false;
      btn.textContent = 'Register';
    }
  });
})();
</script>
