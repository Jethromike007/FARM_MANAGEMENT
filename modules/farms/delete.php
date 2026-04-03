<?php
// modules/farms/delete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('farms', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf()) {
    flash('error', 'Invalid request.');
    redirect(APP_URL . '/modules/farms/index.php');
}

$id   = (int)($_POST['id'] ?? 0);
$farm = DB::row("SELECT * FROM farms WHERE id = ?", [$id]);
if (!$farm) { flash('error', 'Farm not found.'); redirect(APP_URL . '/modules/farms/index.php'); }

// Cascade delete handled by FK, but log first
Logger::log(Auth::id(), 'delete', 'farms', $id, "Deleted farm #{$id}: {$farm['name']}");
DB::execute("DELETE FROM farms WHERE id = ?", [$id]);
flash('success', "Farm '{$farm['name']}' and all its data deleted.");
redirect(APP_URL . '/modules/farms/index.php');
