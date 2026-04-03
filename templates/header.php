<?php
// templates/header.php
// Usage: include this at the top of every page.
// $pageTitle must be set before including this file.
// $activeNav must be set (e.g. 'dashboard', 'farms', 'animals', etc.)

$user  = Auth::user();
$theme = $user['theme'] ?? 'light';
$initials = strtoupper(substr($user['name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- FarmFlow CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<div class="ff-overlay" id="sidebarOverlay"></div>

<div class="ff-layout">
  <!-- ======================================================
       SIDEBAR
       ====================================================== -->
  <aside class="ff-sidebar" id="sidebar">

    <!-- Brand -->
    <div class="ff-sidebar-brand">
      <div class="brand-icon">🌾</div>
      <div>
        <div class="brand-name"><?= APP_NAME ?></div>
        <div class="brand-version">v<?= APP_VERSION ?></div>
      </div>
    </div>

    <!-- Navigation -->
    <ul class="ff-nav">

      <li class="ff-nav-section">Overview</li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/dashboard.php">
          <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
          Dashboard
        </a>
      </li>

      <li class="ff-nav-section">Farm Operations</li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'farms' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/farms/index.php">
          <span class="nav-icon"><i class="bi bi-geo-alt"></i></span>
          Farms
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'animals' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/animals/index.php">
          <span class="nav-icon"><i class="bi bi-heart-pulse"></i></span>
          Animals
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'crops' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/crops/index.php">
          <span class="nav-icon"><i class="bi bi-flower1"></i></span>
          Crops
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'eggs' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/eggs/index.php">
          <span class="nav-icon"><i class="bi bi-egg"></i></span>
          Egg Production
        </a>
      </li>

      <li class="ff-nav-section">Finance</li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'accounting' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/accounting/index.php">
          <span class="nav-icon"><i class="bi bi-cash-stack"></i></span>
          Accounting
        </a>
      </li>

      <li class="ff-nav-section">Admin</li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'logs' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/logs/index.php">
          <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
          Audit Logs
        </a>
      </li>
      <?php if (RBAC::can('users', 'view')): ?>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/users/index.php">
          <span class="nav-icon"><i class="bi bi-people"></i></span>
          Users
        </a>
      </li>
      <?php endif; ?>

    </ul>

    <!-- User Card -->
    <div class="ff-sidebar-footer">
      <div class="ff-user-card">
        <div class="ff-user-avatar"><?= e($initials) ?></div>
        <div class="ff-user-info">
          <div class="user-name"><?= e($user['name']) ?></div>
          <div class="user-role"><?= e($user['role']) ?></div>
        </div>
        <a href="<?= APP_URL ?>/auth/logout.php" title="Logout" style="margin-left:auto;color:rgba(255,255,255,.4);font-size:16px;">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>

  </aside><!-- /sidebar -->

  <!-- ======================================================
       MAIN CONTENT AREA
       ====================================================== -->
  <div class="ff-main">

    <!-- Top Header -->
    <header class="ff-header">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>

      <h1 class="ff-header-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>

      <div class="ff-header-actions">
        <!-- Theme Toggle -->
        <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode">
          <span class="toggle-icon" id="themeIcon">
            <?= $theme === 'dark' ? '☀️' : '🌙' ?>
          </span>
          <span id="themeLabel"><?= $theme === 'dark' ? 'Light' : 'Dark' ?></span>
        </button>

        <!-- Notifications bell (placeholder) -->
        <button style="background:none;border:none;font-size:18px;color:var(--text-muted);cursor:pointer;">
          <i class="bi bi-bell"></i>
        </button>
      </div>
    </header>

    <!-- Flash Messages -->
    <?php
    $flash = get_flash();
    if ($flash):
      $alertType = match($flash['type']) {
        'success' => 'ff-alert-success',
        'error'   => 'ff-alert-error',
        'warning' => 'ff-alert-warning',
        default   => 'ff-alert-info',
      };
      $icon = match($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        default   => 'bi-info-circle-fill',
      };
    ?>
    <div style="padding:16px 28px 0;">
      <div class="ff-alert <?= $alertType ?>">
        <i class="bi <?= $icon ?>"></i>
        <?= e($flash['message']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Page Content starts here -->
    <main class="ff-content">
