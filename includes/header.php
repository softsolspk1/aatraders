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

                <!-- User Role Badge -->
                <div class="role-pill">
                    <span style="font-size: 11px; color: var(--slate-500);">Role:</span>
                    <span style="font-weight: 700; color: #0284c7; font-size: 12px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $currentUser['role'] ?? 'User'))) ?></span>
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
