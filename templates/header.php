<?php
// templates/header.php — FarmFlow Premium UI v2
// Required before include: $pageTitle (string), $activeNav (string)

$user     = Auth::user();
$theme    = $user['theme'] ?? 'light';
$initials = strtoupper(substr($user['name'], 0, 2));
$role     = ucfirst($user['role']);
$farmLabel= $user['farm_id'] ? 'Farm #' . $user['farm_id'] : 'All Farms';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    <div class="ff-brand-logo">
      <svg viewBox="0 0 24 24">
        <path d="M12 2C8 2 4 5 4 9c0 5 4 10 8 13 4-3 8-8 8-13 0-4-4-7-8-7z"/>
        <path d="M12 6v5l3 2"/>
        <path d="M7 9c1-2 3-3 5-3"/>
      </svg>
    </div>
    <div class="ff-brand-text">
      <div class="ff-brand-name"><?= APP_NAME ?></div>
      <div class="ff-brand-sub">Management Suite</div>
    </div>
  </div>

  <!-- Quick Search -->
  <div class="ff-sidebar-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <span class="s-hint">Quick search…</span>
    <span class="s-kbd">⌘K</span>
  </div>

  <!-- Navigation -->
  <ul class="ff-nav">

    <li class="ff-nav-section">Overview</li>
    <ul class="ff-nav-items">
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/dashboard.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          </div>
          Dashboard
          <span class="ff-nav-badge live">Live</span>
        </a>
      </li>
    </ul>

    <li class="ff-nav-section">Farm Operations</li>
    <ul class="ff-nav-items">
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'farms' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/farms/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
          </div>
          Farms
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'animals' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/animals/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><path d="M20 7c0 4.4-3.6 8-8 8s-8-3.6-8-8"/><path d="M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2"/><path d="M12 15v6"/><path d="M8 21h8"/></svg>
          </div>
          Animals
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'crops' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/crops/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><path d="M12 22V12"/><path d="M5 3c0 6.3 4 9 7 9s7-2.7 7-9"/><path d="M5 3h14"/></svg>
          </div>
          Crops
        </a>
      </li>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'eggs' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/eggs/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><ellipse cx="12" cy="10" rx="5.5" ry="7.5"/><path d="M6.5 10c0 3 2.5 5.5 5.5 5.5s5.5-2.5 5.5-5.5"/></svg>
          </div>
          Egg Production
        </a>
      </li>
    </ul>

    <div class="ff-nav-divider"></div>

    <li class="ff-nav-section">Finance</li>
    <ul class="ff-nav-items">
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'accounting' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/accounting/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          Accounting
        </a>
      </li>
    </ul>

    <div class="ff-nav-divider"></div>

    <li class="ff-nav-section">System</li>
    <ul class="ff-nav-items">
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'logs' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/logs/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
          </div>
          Audit Logs
        </a>
      </li>
      <?php if (RBAC::can('users', 'view')): ?>
      <li class="ff-nav-item <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/modules/users/index.php">
          <div class="ff-nav-ic">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          Users
        </a>
      </li>
      <?php endif; ?>
    </ul>

  </ul><!-- /ff-nav -->

  <!-- User Card at Bottom -->
  <div class="ff-sidebar-footer">
    <div class="ff-sidebar-user" title="<?= e($user['email']) ?>">
      <div class="ff-sb-avatar"><?= e($initials) ?></div>
      <div class="ff-sb-uinfo">
        <div class="u-name"><?= e($user['name']) ?></div>
        <div class="u-role"><?= e($role) ?> · <?= e($farmLabel) ?></div>
      </div>
      <div class="ff-sb-caret">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
  </div>

</aside><!-- /ff-sidebar -->

<!-- ======================================================
     MAIN AREA
     ====================================================== -->
<div class="ff-main">

  <!-- ======================================================
       TOP HEADER
       ====================================================== -->
  <header class="ff-header">

    <!-- Mobile toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Open sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <!-- Breadcrumb -->
    <div class="ff-breadcrumb">
      <span class="bc-root"><?= APP_NAME ?></span>
      <span class="bc-sep">/</span>
      <span class="bc-page"><?= e($pageTitle ?? 'Dashboard') ?></span>
    </div>

    <!-- Actions -->
    <div class="ff-hdr-actions">

      <!-- Theme toggle -->
      <button class="ff-theme-btn" id="themeToggle" title="Switch theme">
        <svg id="themeIcon" viewBox="0 0 24 24">
          <?php if ($theme === 'dark'): ?>
          <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <?php else: ?>
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          <?php endif; ?>
        </svg>
        <span><?= $theme === 'dark' ? 'Light' : 'Dark' ?></span>
      </button>

      <!-- Notifications -->
      <button class="ff-hdr-btn" title="Notifications">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="ff-notif-dot"></div>
      </button>

      <!-- Settings -->
      <a class="ff-hdr-btn" href="<?= APP_URL ?>/modules/users/edit.php?id=<?= Auth::id() ?>" title="Profile &amp; Settings">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </a>

      <div class="ff-hdr-divider"></div>

      <!-- User chip -->
      <a class="ff-hdr-user" href="<?= APP_URL ?>/modules/users/edit.php?id=<?= Auth::id() ?>">
        <div class="ff-hdr-avatar"><?= e($initials) ?></div>
        <div class="ff-hdr-uinfo">
          <div class="hu-name"><?= e($user['name']) ?></div>
          <div class="hu-role"><?= e($role) ?></div>
        </div>
        <svg class="ff-hdr-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </a>

      <!-- Logout -->
      <a class="ff-hdr-btn" href="<?= APP_URL ?>/auth/logout.php" title="Sign out">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>

    </div>
  </header><!-- /ff-header -->

  <!-- Flash Messages -->
  <?php
  $flash = get_flash();
  if ($flash):
    $alertMap = ['success'=>'ff-alert-success','error'=>'ff-alert-error','warning'=>'ff-alert-warning','info'=>'ff-alert-info'];
    $alertCls = $alertMap[$flash['type']] ?? 'ff-alert-info';
    $iconMap = [
      'success' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>',
      'error'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
      'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    $iconHtml = $iconMap[$flash['type']] ?? $iconMap['warning'];
  ?>
  <div style="padding:14px 24px 0;">
    <div class="ff-alert <?= $alertCls ?>">
      <?= $iconHtml ?>
      <?= e($flash['message']) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <main class="ff-content">