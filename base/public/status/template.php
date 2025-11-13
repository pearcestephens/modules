<?php
// Shared status page template
$code = $code ?? (http_response_code() ?: 500);
$title = $title ?? 'Error';
$message = $message ?? 'An unexpected error occurred.';
$debug = isset($_GET['debug']) ? true : false;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - CIS</title>
  <style>
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-width:800px;width:100%;overflow:hidden}
    .header{background:#343a40;color:#fff;padding:28px;text-align:center}
    .code{font-size:3rem;font-weight:800;opacity:.9}
    .body{padding:26px}
    .msg{background:#f8f9fa;border-left:4px solid #6c757d;padding:14px 18px;border-radius:4px;margin-bottom:18px}
    .actions{display:flex;gap:10px;margin-top:10px}
    .btn{padding:12px 18px;border-radius:6px;text-decoration:none;display:inline-block}
    .btn-primary{background:#667eea;color:#fff}
    .btn-secondary{background:#6c757d;color:#fff}
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="code"><?= (int)$code ?></div>
      <h1><?= htmlspecialchars($title) ?></h1>
    </div>
    <div class="body">
      <div class="msg"><p><?= htmlspecialchars($message) ?></p></div>
      <?php if ($debug && !empty($_SERVER)): ?>
      <pre style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;padding:12px;max-height:260px;overflow:auto"><?php print_r($_SERVER); ?></pre>
      <?php endif; ?>
      <div class="actions">
        <a class="btn btn-secondary" href="javascript:history.back()">← Go Back</a>
        <a class="btn btn-primary" href="/">🏠 Go to Dashboard</a>
      </div>
    </div>
  </div>
</body>
</html>
