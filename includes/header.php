<?php
// AA TRADERS - PDMS Header Component
require_once __DIR__ . '/auth.php';
Auth::requireLogin();

$currentUser = Auth::user();
$db = Database::getConnection();

// Fetch unread critical notifications count
$unreadNotifs = $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$nearExpiryCount = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' 
      AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 90
")->fetchColumn();

// Company name & settings
$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
$companyName = $company['name'] ?? 'AA TRADERS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - AA TRADERS Pharmaceutical ERP</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar no-print">
            <div class="topbar-left">
                <div>
                    <h2 class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Overview') ?></h2>
                    <p class="topbar-subtitle"><?= htmlspecialchars($companyName) ?> &bull; Pharmaceutical Distribution Management System</p>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Expiry Alert Bell / Badge -->
                <?php if ($nearExpiryCount > 0): ?>
                    <a href="expiry.php" class="btn btn-sm btn-danger" title="<?= $nearExpiryCount ?> Batches Near Expiry (< 90 Days)">
                        ⚠️ <?= $nearExpiryCount ?> Near Expiry
                    </a>
                <?php endif; ?>

                <!-- Fast Demo Role Switcher -->
                <div class="role-pill">
                    <span>Role:</span>
                    <select id="demoRoleSwitcher" class="demo-role-select" title="Switch Demo Role instantly">
                        <option value="admin" <?= $currentUser['username'] === 'admin' ? 'selected' : '' ?>>🛡️ Super Admin</option>
                        <option value="sales_mgr" <?= $currentUser['username'] === 'sales_mgr' ? 'selected' : '' ?>>📈 Sales Manager</option>
                        <option value="warehouse_mgr" <?= $currentUser['username'] === 'warehouse_mgr' ? 'selected' : '' ?>>🏭 Warehouse Mgr</option>
                        <option value="accounts_mgr" <?= $currentUser['username'] === 'accounts_mgr' ? 'selected' : '' ?>>💰 Accounts Mgr</option>
                        <option value="kamran_rep" <?= $currentUser['username'] === 'kamran_rep' ? 'selected' : '' ?>>💼 Kamran (Rep)</option>
                        <option value="auditor" <?= $currentUser['username'] === 'auditor' ? 'selected' : '' ?>>🔍 Auditor</option>
                    </select>
                </div>

                <!-- User profile badge -->
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 34px; height: 34px; border-radius: 50%; background: #0284c7; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 12.5px; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></span>
                        <span style="font-size: 11px; color: #64748b;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $currentUser['role'] ?? ''))) ?></span>
                    </div>
                </div>

                <a href="logout.php" class="btn btn-secondary btn-sm" title="Sign Out">Sign Out</a>
            </div>
        </header>

        <!-- Main Body -->
        <main class="page-body">
