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

    public static function purgeDemoData(string $mode = 'all'): array {
        $pdo = self::getConnection();
        try {
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            $transactionalTables = [
                'sales_invoice_items', 'sales_invoices',
                'sales_order_items', 'sales_orders',
                'sales_return_items', 'sales_returns',
                'goods_receipt_items', 'goods_receipts',
                'purchase_order_items', 'purchase_orders',
                'purchase_return_items', 'purchase_returns',
                'payments_received', 'supplier_payments',
                'customer_ledgers', 'supplier_ledgers',
                'inventory_transactions',
                'stock_transfer_items', 'stock_transfers',
                'stock_adjustment_items', 'stock_adjustments',
                'bank_transactions',
                'expenses',
                'customer_visits',
                'sales_targets',
                'deliveries',
                'product_recalls',
                'notifications'
            ];

            foreach ($transactionalTables as $tbl) {
                $pdo->exec("DELETE FROM {$tbl};");
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}';");
            }

            if ($mode === 'transactions_only') {
                $pdo->exec("UPDATE customers SET current_balance = 0.0, is_blocked = 0, block_reason = NULL;");
                $pdo->exec("UPDATE suppliers SET current_balance = 0.0;");
                $pdo->exec("UPDATE product_batches SET quantity_available = 0, quantity_reserved = 0, quantity_damaged = 0, quantity_expired = 0;");
                $pdo->exec("UPDATE bank_accounts SET current_balance = opening_balance;");
                $summary = "All demo transactions, invoices, orders, payments, ledgers, deliveries, and expenses have been successfully deleted. Balances and inventory stock reset to 0.";
            } else {
                $masterTables = [
                    'product_batches',
                    'schemes',
                    'products',
                    'customers',
                    'suppliers',
                    'sales_representatives'
                ];
                foreach ($masterTables as $tbl) {
                    $pdo->exec("DELETE FROM {$tbl};");
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}';");
                }
                $pdo->exec("DELETE FROM users WHERE username != 'admin';");
                $pdo->exec("UPDATE bank_accounts SET current_balance = 0.0, opening_balance = 0.0;");
                $summary = "All demo data (products, batches, schemes, customers, suppliers, sales reps, and transactions) has been permanently deleted. Super admin account and company profile preserved.";
            }

            $pdo->exec('PRAGMA foreign_keys = ON;');

            self::logAudit('PURGE_DEMO_DATA', 'System', $mode, $summary);
            return ['success' => true, 'message' => $summary];
        } catch (\Exception $e) {
            $pdo->exec('PRAGMA foreign_keys = ON;');
            return ['success' => false, 'message' => 'Failed to purge demo data: ' . $e->getMessage()];
        }
    }

    public static function reseedDemoData(): array {
        $pdo = self::getConnection();
        try {
            $pdo->exec('PRAGMA foreign_keys = OFF;');
            $tables = [
                'sales_invoice_items', 'sales_invoices', 'sales_order_items', 'sales_orders',
                'sales_return_items', 'sales_returns', 'goods_receipt_items', 'goods_receipts',
                'purchase_order_items', 'purchase_orders', 'purchase_return_items', 'purchase_returns',
                'payments_received', 'supplier_payments', 'customer_ledgers', 'supplier_ledgers',
                'inventory_transactions', 'stock_transfer_items', 'stock_transfers',
                'stock_adjustment_items', 'stock_adjustments', 'bank_transactions', 'expenses',
                'customer_visits', 'sales_targets', 'deliveries', 'product_recalls', 'notifications',
                'schemes', 'product_batches', 'products', 'customers', 'suppliers', 'sales_representatives',
                'manufacturers', 'product_categories', 'therapeutic_classes', 'areas', 'territories',
                'warehouses', 'branches', 'bank_accounts', 'users', 'company_settings'
            ];
            foreach ($tables as $tbl) {
                $pdo->exec("DELETE FROM {$tbl};");
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}';");
            }
            $pdo->exec('PRAGMA foreign_keys = ON;');

            $seedFile = __DIR__ . '/../database/seed.php';
            if (file_exists($seedFile)) {
                require_once $seedFile;
                if (function_exists('seedDatabase')) {
                    seedDatabase($pdo);
                }
            }

            self::logAudit('RESEED_DEMO_DATA', 'System', 'ALL', 'Demo dataset re-seeded successfully.');
            return ['success' => true, 'message' => 'Demo data has been successfully restored and re-seeded.'];
        } catch (\Exception $e) {
            $pdo->exec('PRAGMA foreign_keys = ON;');
            return ['success' => false, 'message' => 'Failed to re-seed demo data: ' . $e->getMessage()];
        }
    }
}
