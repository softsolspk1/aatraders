<?php
// AA TRADERS - Purchase Forecasting & Reorder Intelligence
$pageTitle = 'Purchase Forecasting';
require_once __DIR__ . '/includes/header.php';

// Calculate monthly consumption and replenishment suggestions
$forecastRows = $db->query("
    SELECT p.*, m.name as manufacturer_name, c.name as category_name,
           IFNULL(SUM(b.quantity_available), 0) as current_stock,
           -- Calculate sales volume past 30 days
           (
               SELECT IFNULL(SUM(ii.quantity), 0)
               FROM sales_invoice_items ii
               JOIN sales_invoices inv ON ii.invoice_id = inv.id
               WHERE ii.product_id = p.id AND inv.invoice_date >= date('now', '-30 days')
           ) as monthly_sales_volume,
           -- Suggested purchase = (Max Stock - Current Stock) if Current Stock <= Reorder Level
           CASE 
               WHEN IFNULL(SUM(b.quantity_available), 0) <= p.reorder_level 
               THEN (p.max_stock - IFNULL(SUM(b.quantity_available), 0))
               ELSE 0 
           END as suggested_purchase_qty
    FROM products p
    LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    GROUP BY p.id
    ORDER BY suggested_purchase_qty DESC, current_stock ASC
")->fetchAll();

$urgentReorders = array_filter($forecastRows, fn($r) => $r['suggested_purchase_qty'] > 0);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Predictive Purchase Forecasting &amp; Safety Stock</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Historical Run-Rates &bull; Buffer Thresholds &bull; Auto Suggested Purchase Quantities</p>
    </div>
    <a href="purchases.php" class="btn btn-primary">+ Go to Purchase Orders</a>
</div>

<!-- Forecast Overview Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>Urgent Reorders Needed</h3>
            <div class="kpi-value"><?= count($urgentReorders) ?> Products</div>
            <div class="kpi-sub">At or below reorder threshold</div>
        </div>
        <div class="kpi-icon red">⚠️</div>
    </div>
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Estimated Replenishment Qty</h3>
            <div class="kpi-value"><?= number_format(array_sum(array_column($forecastRows, 'suggested_purchase_qty'))) ?> Packs</div>
            <div class="kpi-sub">Total suggested procurement</div>
        </div>
        <div class="kpi-icon blue">📦</div>
    </div>
    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Safety Stock Compliance</h3>
            <div class="kpi-value"><?= count($forecastRows) - count($urgentReorders) ?> Formulations</div>
            <div class="kpi-sub">Above buffer inventory line</div>
        </div>
        <div class="kpi-icon green">🛡️</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">🔮 Suggested Purchase Orders &amp; Holding Capacity Analysis</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product &amp; Manufacturer</th>
                        <th>Monthly Sales Volume</th>
                        <th>Current Available Stock</th>
                        <th>Safety Stock</th>
                        <th>Reorder Level</th>
                        <th>Max Capacity</th>
                        <th>Suggested Purchase Qty</th>
                        <th>Procurement Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forecastRows as $r): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($r['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($r['name']) ?></strong>
                                <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($r['manufacturer_name'] ?? 'General') ?> &bull; <?= htmlspecialchars($r['strength']) ?></div>
                            </td>
                            <td><?= $r['monthly_sales_volume'] ?> packs/mo</td>
                            <td>
                                <strong style="color: <?= $r['current_stock'] <= $r['reorder_level'] ? '#dc2626' : 'var(--slate-800)' ?>; font-size: 14px;">
                                    <?= $r['current_stock'] ?>
                                </strong> <?= $r['unit'] ?>s
                            </td>
                            <td><?= $r['safety_stock'] ?></td>
                            <td><?= $r['reorder_level'] ?></td>
                            <td><?= $r['max_stock'] ?></td>
                            <td>
                                <?php if ($r['suggested_purchase_qty'] > 0): ?>
                                    <strong style="color: #dc2626; font-size: 15px;">+<?= $r['suggested_purchase_qty'] ?> <?= $r['unit'] ?>s</strong>
                                <?php else: ?>
                                    <span style="color: #10b981; font-weight: 600;">Sufficient Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['current_stock'] <= $r['safety_stock']): ?>
                                    <span class="badge badge-danger">CRITICAL LOW</span>
                                <?php elseif ($r['current_stock'] <= $r['reorder_level']): ?>
                                    <span class="badge badge-warning">REORDER DUE</span>
                                <?php else: ?>
                                    <span class="badge badge-success">OPTIMAL</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
