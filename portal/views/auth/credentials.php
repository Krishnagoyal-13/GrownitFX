<?php declare(strict_types=1); ?>
<h1>Credentials</h1>
<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<p id="creds-loading">Loading credentials...</p>
<div id="creds-error" class="error" style="display:none"></div>
<div id="creds-data" style="display:none">
  <p><strong>Login ID:</strong> <span id="login-id"></span></p>
  <p><strong>Password:</strong> <span id="login-password"></span></p>
</div>
<p><a href="<?= htmlspecialchars((string)$basePath, ENT_QUOTES, 'UTF-8') ?>/login">Proceed to Login</a></p>

<script>
(() => {
  const basePath = <?= json_encode((string)$basePath, JSON_UNESCAPED_SLASHES) ?>;
  const loading = document.getElementById('creds-loading');
  const err = document.getElementById('creds-error');
  const data = document.getElementById('creds-data');
  const loginId = document.getElementById('login-id');
  const loginPassword = document.getElementById('login-password');
  const endpointCandidates = [
    `${basePath}/api/user/get`,
    `${basePath}/public/index.php?route=${encodeURIComponent('/api/user/get')}`,
  ];

  const fetchCredentials = async () => {
    for (const endpoint of endpointCandidates) {
      const res = await fetch(endpoint, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });

      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch {
        if (!res.ok) {
          continue;
        }
        throw new Error('Server returned non-JSON response for /api/user/get.');
      }

      return { res, json };
    }

    throw new Error('Unable to fetch credentials endpoint.');
  };

  fetchCredentials()
    .then(async (res) => {
      if (!res.res.ok) {
        throw new Error(res.json.error || 'Unable to fetch credentials.');
      }
      loginId.textContent = res.json.loginId || '';
      loginPassword.textContent = res.json.password || '';
      loading.style.display = 'none';
      data.style.display = 'block';
    })
    .catch((e) => {
      loading.style.display = 'none';
      err.textContent = e instanceof Error ? e.message : 'Unable to fetch credentials.';
      err.style.display = 'block';
    });
})();
</script>
