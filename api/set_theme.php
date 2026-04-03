<?php
// api/set_theme.php — Persist theme preference via AJAX
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/rbac_logger_helpers.php';

Auth::start();
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$theme = ($data['theme'] ?? '') === 'dark' ? 'dark' : 'light';

DB::execute("UPDATE users SET theme_preference = ? WHERE id = ?", [$theme, Auth::id()]);
$_SESSION['theme'] = $theme;

// Also set a cookie for login page
setcookie('farmflow_theme', $theme, time() + 31536000, '/', '', false, true);

echo json_encode(['ok' => true, 'theme' => $theme]);
