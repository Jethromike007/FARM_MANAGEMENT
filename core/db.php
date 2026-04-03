<?php
// ============================================================
// core/db.php — PDO Database Singleton
// ============================================================

class DB {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log and show friendly error — never expose credentials
                error_log('DB Connection failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed. Please contact support.']));
            }
        }
        return self::$instance;
    }

    // Convenience: run a prepared statement and return the statement
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Fetch single row
    public static function row(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    // Fetch all rows
    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    // Insert and return last insert ID
    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    // Execute and return affected rows
    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }
}
