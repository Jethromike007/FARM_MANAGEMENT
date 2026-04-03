<?php
// modules/eggs/delete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('eggs', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf()) {
    flash('error', 'Invalid request.');
    redirect(APP_URL . '/modules/eggs/index.php');
}

$id  = (int)($_POST['id'] ?? 0);
$rec = DB::row("SELECT * FROM egg_production WHERE id = ?", [$id]);
if (!$rec) { flash('error', 'Record not found.'); redirect(APP_URL . '/modules/eggs/index.php'); }

if (RBAC::farmScope() && $rec['farm_id'] != RBAC::farmScope()) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/modules/eggs/index.php');
}

DB::execute("DELETE FROM egg_production WHERE id = ?", [$id]);
Logger::log(Auth::id(), 'delete', 'egg_production', $id, "Deleted egg record #{$id}");
flash('success', 'Egg record deleted.');
redirect(APP_URL . '/modules/eggs/index.php');
