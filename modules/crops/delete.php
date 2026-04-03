<?php
// modules/crops/delete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('crops', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf()) {
    flash('error', 'Invalid request.');
    redirect(APP_URL . '/modules/crops/index.php');
}

$id   = (int)($_POST['id'] ?? 0);
$crop = DB::row("SELECT * FROM crops WHERE id = ?", [$id]);
if (!$crop) { flash('error', 'Crop not found.'); redirect(APP_URL . '/modules/crops/index.php'); }

if (RBAC::farmScope() && $crop['farm_id'] != RBAC::farmScope()) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/modules/crops/index.php');
}

DB::execute("DELETE FROM crops WHERE id = ?", [$id]);
Logger::log(Auth::id(), 'delete', 'crops', $id, "Deleted crop #{$id}: {$crop['type']}");
flash('success', "Crop '{$crop['type']}' deleted.");
redirect(APP_URL . '/modules/crops/index.php');
