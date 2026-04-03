<?php
// modules/users/delete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('users', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf()) {
    flash('error', 'Invalid request.'); redirect(APP_URL . '/modules/users/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id === Auth::id()) { flash('error', 'You cannot delete your own account.'); redirect(APP_URL . '/modules/users/index.php'); }

$user = DB::row("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) { flash('error', 'User not found.'); redirect(APP_URL . '/modules/users/index.php'); }

DB::execute("DELETE FROM users WHERE id = ?", [$id]);
Logger::log(Auth::id(), 'delete', 'users', $id, "Deleted user #{$id}: {$user['name']}");
flash('success', "User '{$user['name']}' deleted.");
redirect(APP_URL . '/modules/users/index.php');
