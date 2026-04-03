<?php
// index.php — Root entry point
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/rbac_logger_helpers.php';

Auth::start();

if (Auth::check()) {
    redirect(APP_URL . '/dashboard.php');
} else {
    redirect(APP_URL . '/auth/login.php');
}
