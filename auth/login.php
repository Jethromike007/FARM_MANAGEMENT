<?php
// auth/login.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/rbac_logger_helpers.php';

Auth::start();

// Already logged in? Redirect to dashboard
if (Auth::check()) {
    redirect(APP_URL . '/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Please enter your email and password.';
    } elseif (!Auth::login($email, $password)) {
        $errors[] = 'Invalid email or password. Please try again.';
    } else {
        redirect(APP_URL . '/dashboard.php');
    }
}

$theme = $_COOKIE['farmflow_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<div class="ff-login-page">
  <div class="ff-login-card">

    <div class="ff-login-logo">
      <div class="logo-icon">🌾</div>
      <h1><?= APP_NAME ?></h1>
      <p>Farm Management Platform</p>
    </div>

    <?php foreach ($errors as $err): ?>
    <div class="ff-alert ff-alert-error" style="margin-bottom:20px;">
      <i class="bi bi-x-circle-fill"></i> <?= e($err) ?>
    </div>
    <?php endforeach; ?>

    <?php $flash = get_flash(); if ($flash): ?>
    <div class="ff-alert ff-alert-<?= $flash['type'] === 'success' ? 'success' : 'info' ?>" style="margin-bottom:20px;">
      <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div style="margin-bottom:18px;">
        <label class="ff-form-label">Email Address</label>
        <input type="email" name="email" class="ff-form-control" required
               placeholder="admin@farmflow.com" value="<?= e($email) ?>"
               autofocus autocomplete="email">
      </div>

      <div style="margin-bottom:24px;">
        <label class="ff-form-label">Password</label>
        <div style="position:relative;">
          <input type="password" name="password" class="ff-form-control" required
                 placeholder="Your password" id="passwordInput" autocomplete="current-password">
          <button type="button" onclick="togglePassword()" 
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="ff-btn ff-btn-primary w-100" style="padding:12px;font-size:15px;justify-content:center;">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border-color);text-align:center;">
      <p style="font-size:12px;color:var(--text-muted);margin:0;">
        No public registration. Contact your administrator for access.
      </p>
    </div>

    <div style="margin-top:16px;text-align:center;">
      <button onclick="toggleTheme()" 
              style="background:none;border:none;font-size:12px;color:var(--text-muted);cursor:pointer;">
        <span id="themeToggleIcon"><?= $theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode' ?></span>
      </button>
    </div>

  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const icon  = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

function toggleTheme() {
  const html  = document.documentElement;
  const label = document.getElementById('themeToggleIcon');
  const current = html.dataset.theme;
  const next = current === 'dark' ? 'light' : 'dark';
  html.dataset.theme = next;
  label.textContent = next === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
  localStorage.setItem('farmflow_theme', next);
  document.cookie = 'farmflow_theme=' + next + '; path=/; max-age=31536000';
}

// Apply saved theme
const saved = localStorage.getItem('farmflow_theme');
if (saved) document.documentElement.dataset.theme = saved;
</script>
</body>
</html>
