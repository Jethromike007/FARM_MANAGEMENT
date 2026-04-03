<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 — Access Denied</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);padding:24px;">
  <div style="text-align:center;max-width:480px;">
    <div style="font-size:72px;margin-bottom:16px;">🚫</div>
    <h1 style="font-size:32px;font-weight:800;color:var(--text-primary);margin-bottom:8px;">Access Denied</h1>
    <p style="color:var(--text-muted);font-size:15px;margin-bottom:28px;">
      You don't have permission to access this page.<br>
      Contact your administrator if you believe this is a mistake.
    </p>
    <a href="<?= APP_URL ?>/dashboard.php" 
       style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--color-brand);color:#fff;border-radius:8px;font-weight:600;text-decoration:none;">
      ← Back to Dashboard
    </a>
  </div>
</div>
</body>
</html>
