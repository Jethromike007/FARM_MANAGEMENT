<?php
// ============================================================
// core/rbac.php — Role-Based Access Control
// ============================================================

class RBAC {
    // Permissions map: role => allowed actions
    private static array $permissions = [
        'owner' => [
            'farms'    => ['view','create','edit','delete'],
            'animals'  => ['view','create','edit','delete','sell'],
            'crops'    => ['view','create','edit','delete','harvest'],
            'eggs'     => ['view','create','edit','delete'],
            'sales'    => ['view','create'],
            'logs'     => ['view'],
            'users'    => ['view','create','edit','delete'],
            'reports'  => ['view','export'],
        ],
        'manager' => [
            'farms'    => ['view'],
            'animals'  => ['view','create','edit','sell'],
            'crops'    => ['view','create','edit','harvest'],
            'eggs'     => ['view','create','edit'],
            'sales'    => ['view','create'],
            'logs'     => ['view'],
            'users'    => ['view'],
            'reports'  => ['view'],
        ],
        'viewer' => [
            'farms'    => ['view'],
            'animals'  => ['view'],
            'crops'    => ['view'],
            'eggs'     => ['view'],
            'sales'    => ['view'],
            'logs'     => ['view'],
            'users'    => [],
            'reports'  => ['view'],
        ],
    ];

    public static function can(string $module, string $action): bool {
        $role = Auth::role();
        return in_array($action, self::$permissions[$role][$module] ?? [], true);
    }

    // Abort with 403 if not permitted
    public static function require(string $module, string $action): void {
        if (!self::can($module, $action)) {
            http_response_code(403);
            include __DIR__ . '/../templates/403.php';
            exit;
        }
    }

    // Farm-scope: manager/viewer can only see their assigned farm
    public static function farmScope(): ?int {
        $role = Auth::role();
        if ($role === 'owner') return null;  // null = all farms
        return (int)($_SESSION['farm_id'] ?? 0);
    }

    // Build SQL WHERE clause for farm scoping
    public static function farmWhere(string $alias = ''): string {
        $scope = self::farmScope();
        if ($scope === null) return '1=1';
        $col = $alias ? "$alias.farm_id" : 'farm_id';
        return "$col = $scope";
    }
}


// ============================================================
// core/logger.php — Audit Trail
// ============================================================

class Logger {
    public static function log(
        ?int $userId,
        string $actionType,
        ?string $entityType,
        ?int $entityId,
        string $description
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        DB::execute(
            'INSERT INTO logs (user_id, action_type, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $actionType, $entityType, $entityId, $description, $ip]
        );
    }
}


// ============================================================
// core/helpers.php — Utility Functions
// ============================================================

/**
 * Calculate days remaining until maturity/harvest.
 * Negative = overdue/ready.
 */
function days_left(string $startDate, int $maturityDays): int {
    $start    = new DateTime($startDate);
    $maturity = (clone $start)->modify("+{$maturityDays} days");
    $today    = new DateTime();
    return (int)$today->diff($maturity)->days * ($today < $maturity ? 1 : -1);
}

/**
 * Return a Bootstrap color class based on days left.
 * green=ready, yellow=near (within 14d), red=attention/sick
 */
function readiness_class(int $daysLeft): string {
    if ($daysLeft <= 0)  return 'success';
    if ($daysLeft <= 14) return 'warning';
    return 'info';
}

/**
 * Readiness percentage (0–100) for progress bars.
 */
function readiness_pct(string $startDate, int $maturityDays): int {
    $start   = new DateTime($startDate);
    $elapsed = (new DateTime())->diff($start)->days;
    return min(100, (int)round(($elapsed / $maturityDays) * 100));
}

/**
 * Format Nigerian Naira
 */
function money(float $amount): string {
    return '₦' . number_format($amount, 2);
}

/**
 * Flash message setter
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize output
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Egg production status color
 */
function egg_status_class(int $quantity, ?int $target): string {
    if (!$target) return 'info';
    $pct = ($quantity / $target) * 100;
    if ($pct >= 95)  return 'success';
    if ($pct >= 75)  return 'warning';
    return 'danger';
}

/**
 * Health status badge color
 */
function health_class(string $status): string {
    return match($status) {
        'healthy'     => 'success',
        'recovering'  => 'warning',
        'sick'        => 'danger',
        'quarantined' => 'secondary',
        'deceased'    => 'dark',
        default       => 'secondary',
    };
}
