<?php
// AA TRADERS - PDMS Database Connection & Configuration

if (!defined('DB_PATH')) {
    $isVercel = getenv('VERCEL') || isset($_ENV['VERCEL']) || (!is_writable(__DIR__ . '/../data') && !is_writable(__DIR__ . '/..'));
    if ($isVercel) {
        $tmpDb = sys_get_temp_dir() . '/aatraders.sqlite';
        if (!file_exists($tmpDb) || filesize($tmpDb) === 0) {
            $bundled = __DIR__ . '/../data/aatraders.sqlite';
            if (file_exists($bundled) && filesize($bundled) > 0) {
                @copy($bundled, $tmpDb);
            }
        }
        define('DB_PATH', $tmpDb);
    } else {
        define('DB_PATH', __DIR__ . '/../data/aatraders.sqlite');
    }
}
define('CLINIC_DB_PATH', 'C:/Users/softs/Downloads/clinic.sqlite'); // Support reading from existing clinic.sqlite if needed

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0777, true);
            }

            $needsInit = !file_exists(DB_PATH) || filesize(DB_PATH) === 0;

            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
            try {
                self::$pdo->exec('PRAGMA journal_mode = WAL;');
            } catch (\Exception $e) {
                try {
                    self::$pdo->exec('PRAGMA journal_mode = MEMORY;');
                } catch (\Exception $e2) {}
            }

            if ($needsInit) {
                self::initializeDatabase();
            }
        }
        return self::$pdo;
    }

    private static function initializeDatabase(): void {
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            self::$pdo->exec($sql);
        }

        // Run Seeder
        $seedFile = __DIR__ . '/../database/seed.php';
        if (file_exists($seedFile)) {
            require_once $seedFile;
            if (function_exists('seedDatabase')) {
                seedDatabase(self::$pdo);
            }
        }
    }

    public static function logAudit(string $action, string $module, ?string $recordId = null, ?string $details = null): void {
        try {
            $db = self::getConnection();
            $userId = $_SESSION['user_id'] ?? 1;
            $username = $_SESSION['username'] ?? 'System';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, username, ip_address, action, module, record_id, details) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $username, $ip, $action, $module, $recordId, $details]);
        } catch (\Exception $e) {
            // Silently fail for audit log so main flow doesn't break
        }
    }
}
