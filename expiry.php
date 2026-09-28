<?php
// AA TRADERS - Dedicated Pharmaceutical Expiry Management
$pageTitle = 'Expiry Management';
require_once __DIR__ . '/includes/header.php';

// Handle Move to Expired Bay
if (isset($_GET['quarantine_batch_id'])) {
    $batchId = (int)$_GET['quarantine_batch_id'];
    $expWhId = $db->query("SELECT id FROM warehouses WHERE type = 'expired' LIMIT 1")->fetchColumn() ?: 5;
    $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batchId}")->fetch();
    if ($batch) {
        $db->query("UPDATE product_batches SET status = 'expired', warehouse_id = {$expWhId}, quantity_expired = quantity_available, quantity_available = 0 WHERE id = {$batchId}");
        Database::logAudit('QUARANTINE_EXPIRY', 'Expiry', (string)$batchId, "Moved batch {$batch['batch_number']} to Expired Stock Bay for destruction / return.");
        header('Location: expiry.php?msg=quarantined');
        exit;
    }
}

// Counts
$countExpired = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE (status = 'expired' OR julianday(expiry_date) <= julianday('now')) AND (quantity_available > 0 OR quantity_expired > 0)
")->fetchColumn();

$count30 = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 30 AND julianday(expiry_date) - julianday('now') > 0
")->fetchColumn();

$count90 = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 90 AND julianday(expiry_date) - julianday('now') > 30
")->fetchColumn();

$count180 = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 180 AND julianday(expiry_date) - julianday('now') > 90
")->fetchColumn();

// Fetch Batches
$filter = $_GET['filter'] ?? 'all_near';
$whereClause = "1=1";
if ($filter === 'expired') {
    $whereClause = "(b.status = 'expired' OR julianday(b.expiry_date) <= julianday('now'))";
} elseif ($filter === '30') {
    $whereClause = "b.status = 'active' AND b.quantity_available > 0 AND julianday(b.expiry_date) - julianday('now') <= 30 AND julianday(b.expiry_date) - julianday('now') > 0";
} elseif ($filter === '90') {
    $whereClause = "b.status = 'active' AND b.quantity_available > 0 AND julianday(b.expiry_date) - julianday('now') <= 90 AND julianday(b.expiry_date) - julianday('now') > 0";
} else {
    $whereClause = "b.quantity_available > 0 AND julianday(b.expiry_date) - julianday('now') <= 180";
}

$batches = $db->query("
    SELECT b.*, p.name as product_name, p.code as product_code, p.dosage_form, p.strength,
           m.name as manufacturer_name, w.name as warehouse_name,
           CAST(julianday(b.expiry_date) - julianday('now') AS INTEGER) as days_left,
           ROUND(b.quantity_available * b.trade_price, 2) as exposure_value
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    WHERE {$whereClause}
    ORDER BY b.expiry_date ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Pharmaceutical Shelf-Life &amp; Expiry Control</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Color-coded DRAP Expiry Tiers: Expired, &lt;30 Days, &lt;90 Days, &lt;180 Days &amp; Clearance Campaigns</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary no-print">🖨️ Print Expiry Audit Report</button>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'quarantined'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Expired batch successfully segregated into Expired Stock Destruction Bay.
    </div>
<?php endif; ?>

<!-- 4 Expiry Tier Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>🔴 Expired Batches</h3>
            <div class="kpi-value"><?= $countExpired ?> Batches</div>
            <div class="kpi-sub">Lapsed shelf life - Segregate</div>
        </div>
        <div class="kpi-icon red">🛑</div>
    </div>

    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>🟠 Critical (&lt; 30 Days)</h3>
            <div class="kpi-value"><?= $count30 ?> Batches</div>
            <div class="kpi-sub">Urgent clearance or return</div>
        </div>
        <div class="kpi-icon red">⏳</div>
    </div>

    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>🟡 Near Expiry (&lt; 90 Days)</h3>
            <div class="kpi-value"><?= $count90 ?> Batches</div>
            <div class="kpi-sub">Incentivize via FEFO schemes</div>
        </div>
        <div class="kpi-icon amber">⚠️</div>
    </div>

    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>🔵 Moderate (&lt; 180 Days)</h3>
            <div class="kpi-value"><?= $count180 ?> Batches</div>
            <div class="kpi-sub">Planned consumption monitoring</div>
        </div>
        <div class="kpi-icon blue">📅</div>
    </div>
</div>

<!-- Filter Buttons -->
<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;" class="no-print">
    <a href="expiry.php?filter=all_near" class="btn <?= $filter === 'all_near' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Shelf-Life Risk (&lt;180 Days)</a>
    <a href="expiry.php?filter=30" class="btn <?= $filter === '30' ? 'btn-danger' : 'btn-secondary' ?> btn-sm">🔴 Critical &lt; 30 Days</a>
    <a href="expiry.php?filter=90" class="btn <?= $filter === '90' ? 'btn-warning' : 'btn-secondary' ?> btn-sm">🟡 Near Expiry &lt; 90 Days</a>
    <a href="expiry.php?filter=expired" class="btn <?= $filter === 'expired' ? 'btn-danger' : 'btn-secondary' ?> btn-sm">🛑 Expired Bay Batches</a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch #</th>
                        <th>Product &amp; Manufacturer</th>
                        <th>Storage Facility</th>
                        <th>Expiry Date</th>
                        <th>Shelf-Life Clock</th>
                        <th>Remaining Stock</th>
                        <th>Financial Exposure (TP)</th>
                        <th>Recommended Action</th>
                        <th class="no-print">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--slate-400); padding: 24px;">No batches found matching the selected expiry threshold.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($b['batch_number']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($b['product_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($b['manufacturer_name'] ?? '-') ?> &bull; <?= htmlspecialchars($b['strength']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($b['warehouse_name']) ?></td>
                                <td><strong style="font-family: monospace; font-size: 13.5px;"><?= htmlspecialchars($b['expiry_date']) ?></strong></td>
                                <td>
                                    <?php if ($b['days_left'] <= 0): ?>
                                        <span class="badge badge-expired">EXPIRED (<?= abs($b['days_left']) ?>d)</span>
                                    <?php elseif ($b['days_left'] <= 30): ?>
                                        <span class="badge badge-near-30">⚠️ <?= $b['days_left'] ?> DAYS LEFT</span>
                                    <?php elseif ($b['days_left'] <= 90): ?>
                                        <span class="badge badge-near-90"><?= $b['days_left'] ?> days left</span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?= $b['days_left'] ?> days left</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 14px;"><?= $b['quantity_available'] > 0 ? $b['quantity_available'] : $b['quantity_expired'] ?></strong> units
                                </td>
                                <td><strong>Rs. <?= number_format($b['exposure_value']) ?></strong></td>
                                <td>
                                    <?php if ($b['days_left'] <= 0): ?>
                                        <span style="color: #dc2626; font-weight: 700; font-size: 12px;">Move to Destruction / Supplier Claim</span>
                                    <?php elseif ($b['days_left'] <= 30): ?>
                                        <span style="color: #e11d48; font-weight: 700; font-size: 12px;">Urgent Bonus Scheme / Hospital Sale</span>
                                    <?php else: ?>
                                        <span style="color: #d97706; font-weight: 600; font-size: 12px;">Prioritize on FEFO Invoices</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <?php if ($b['days_left'] <= 0 && $b['status'] !== 'expired'): ?>
                                        <a href="expiry.php?quarantine_batch_id=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Segregate this expired batch into Expired Stock Storage Bay?')">
                                            Segregate
                                        </a>
                                    <?php elseif ($b['days_left'] <= 90): ?>
                                        <a href="schemes.php" class="btn btn-secondary btn-sm" title="Create clearance scheme">
                                            + Scheme
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #10b981;">Monitored</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
