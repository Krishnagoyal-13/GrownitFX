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
  const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
  const endpointCandidates = (path) => [
    `${basePath}${path}`,
    `${basePath}/public/index.php?route=${encodeURIComponent(path)}`,
  ];

  const fetchJson = async (path, options = {}) => {
    let lastError = null;

    for (const endpoint of endpointCandidates(path)) {
      try {
        const response = await fetch(endpoint, options);
        const text = await response.text();
        let json;
        try {
          json = JSON.parse(text);
        } catch {
          if (!response.ok) {
            continue;
          }
          throw new Error(`Server returned non-JSON response for ${path}.`);
        }

        return { response, json };
      } catch (error) {
        lastError = error;
      }
    }

    throw lastError instanceof Error ? lastError : new Error(`Request failed for ${path}.`);
  };

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
      const { response: startResp, json: startJson } = await fetchJson('/api/user/start', {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': csrf,
        },
      });
      if (!startResp.ok || !startJson.ok) {
        throw new Error(startJson.error || 'MT5 start handshake failed.');
      }

      btn.textContent = 'Authorizing...';

      const { response: accessResp, json: accessJson } = await fetchJson('/api/user/access', {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': csrf,
        },
      });
      if (!accessResp.ok || accessJson.connected !== true) {
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
