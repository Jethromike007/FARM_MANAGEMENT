<?php
// auth/logout.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/rbac_logger_helpers.php';

Auth::start();
Auth::logout();
// Auth::logout() handles redirect
