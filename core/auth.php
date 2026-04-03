<?php
// ============================================================
// core/auth.php — Session Authentication
// ============================================================

class Auth {

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false,   // Set true in HTTPS production
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        $user = DB::row(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        // Regenerate session ID on login (session fixation prevention)
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['farm_id']   = $user['farm_id'];
        $_SESSION['theme']     = $user['theme_preference'];
        $_SESSION['email_notifications'] = (bool)$user['email_notifications'];
        $_SESSION['csrf_token']= bin2hex(random_bytes(32));

        // Update last login
        DB::execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);

        Logger::log($user['id'], 'login', null, null, "User {$user['name']} logged in");
        return true;
    }

    public static function logout(): void {
        if (self::check()) {
            Logger::log($_SESSION['user_id'], 'logout', null, null, "User {$_SESSION['user_name']} logged out");
        }
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function require(): void {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/auth/login.php');
            exit;
        }
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return [
            'id'    => $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role'  => $_SESSION['user_role'],
            'farm_id' => $_SESSION['farm_id'],
            'theme' => $_SESSION['theme'] ?? 'light',
            'email_notifications' => $_SESSION['email_notifications'] ?? false,
        ];
    }

    public static function id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function role(): string {
        return $_SESSION['user_role'] ?? 'viewer';
    }

    // CSRF helpers
    public static function csrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verifyCsrf(): bool {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::csrfToken() . '">';
    }
}
