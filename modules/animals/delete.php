<?php
// modules/animals/delete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf()) {
    flash('error', 'Invalid request.'); redirect(APP_URL . '/modules/animals/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$animal = DB::row("SELECT * FROM animals WHERE id = ?", [$id]);
if (!$animal) { flash('error', 'Animal not found.'); redirect(APP_URL . '/modules/animals/index.php'); }

if (RBAC::farmScope() && $animal['farm_id'] != RBAC::farmScope()) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/modules/animals/index.php');
}

DB::execute("DELETE FROM animals WHERE id = ?", [$id]);
Logger::log(Auth::id(), 'delete', 'animals', $id, "Deleted animal #{$id}: {$animal['type']}");
flash('success', "Animal '{$animal['type']}' deleted.");
redirect(APP_URL . '/modules/animals/index.php');
