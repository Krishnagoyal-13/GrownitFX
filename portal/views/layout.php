<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>GrowItFX Portal</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f7f8fa;margin:0;padding:0;color:#222}
    .wrap{max-width:900px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px}
    .error{background:#fee2e2;color:#991b1b;padding:10px;border-radius:6px;margin-bottom:12px}
    label{display:block;margin-top:12px;font-weight:600}
    input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px}
    button{margin-top:16px;padding:10px 16px;border:none;background:#111827;color:#fff;border-radius:6px;cursor:pointer}
    a{color:#2563eb;text-decoration:none}
    pre{white-space:pre-wrap;word-wrap:break-word;background:#f3f4f6;padding:12px;border-radius:6px;border:1px solid #e5e7eb}
    .nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
  </style>
</head>
<body>
  <div class="wrap">
    <?php require $contentView; ?>
  </div>
</body>
</html>
