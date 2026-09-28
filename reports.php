<?php
// AA TRADERS - Management & Profitability Reports
$pageTitle = 'Executive Reports';
require_once __DIR__ . '/includes/header.php';

$reportType = $_GET['type'] ?? 'product_profit';

// 1. Product Profitability
$productProfit = $db->query("
    SELECT p.code, p.name as product_name, p.dosage_form,
           IFNULL(SUM(ii.quantity), 0) as total_sold,
           IFNULL(SUM(ii.net_total), 0) as total_sales_revenue,
           -- Estimated cost
           IFNULL(SUM(ii.quantity * pb.purchase_price), 0) as total_cost,
           -- Gross profit
           IFNULL(SUM(ii.net_total) - SUM(ii.quantity * pb.purchase_price), 0) as gross_profit,
           ROUND(
               CASE WHEN IFNULL(SUM(ii.net_total), 0) > 0 
               THEN ((SUM(ii.net_total) - SUM(ii.quantity * pb.purchase_price)) / SUM(ii.net_total)) * 100 
               ELSE 0 END, 1
           ) as profit_margin_pct
    FROM products p
    LEFT JOIN sales_invoice_items ii ON p.id = ii.product_id
    LEFT JOIN product_batches pb ON ii.batch_id = pb.id
    GROUP BY p.id
    ORDER BY total_sales_revenue DESC
")->fetchAll();

// 2. Customer Profitability
$customerProfit = $db->query("
    SELECT c.code, c.business_name, c.customer_type,
           COUNT(DISTINCT inv.id) as invoice_count,
           IFNULL(SUM(inv.net_amount), 0) as total_revenue,
           IFNULL(SUM(inv.paid_amount), 0) as total_collected,
           c.current_balance as outstanding_balance
    FROM customers c
    LEFT JOIN sales_invoices inv ON c.id = inv.customer_id
    GROUP BY c.id
    ORDER BY total_revenue DESC
")->fetchAll();

// 3. Slow-Moving Formulations (Products with no sales in past 30 days)
$slowMoving = $db->query("
    SELECT p.*, m.name as manufacturer_name,
           IFNULL(SUM(b.quantity_available), 0) as current_stock,
           ROUND(SUM(b.quantity_available * b.trade_price), 2) as tied_up_capital
    FROM products p
    LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    WHERE p.id NOT IN (
        SELECT DISTINCT product_id FROM sales_invoice_items ii
        JOIN sales_invoices inv ON ii.invoice_id = inv.id
        WHERE inv.invoice_date >= date('now', '-30 days')
    )
    GROUP BY p.id
    HAVING current_stock > 0
    ORDER BY tied_up_capital DESC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Commercial Analytics &amp; Management Reports</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Gross Profit Margins &bull; Account Performance &bull; Velocity &amp; Dead-Stock Analysis</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary no-print">🖨️ Print Report</button>
</div>

<!-- Report Navigation Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;" class="no-print">
    <a href="reports.php?type=product_profit" class="btn <?= $reportType === 'product_profit' ? 'btn-primary' : 'btn-secondary' ?>">
        💊 Product Profitability &amp; Margins
    </a>
    <a href="reports.php?type=customer_profit" class="btn <?= $reportType === 'customer_profit' ? 'btn-primary' : 'btn-secondary' ?>">
        🏥 Pharmacy Account Commercial Value
    </a>
    <a href="reports.php?type=slow_moving" class="btn <?= $reportType === 'slow_moving' ? 'btn-primary' : 'btn-secondary' ?>">
        🐢 Slow-Moving &amp; Dormant Inventory
    </a>
</div>

<?php if ($reportType === 'product_profit'): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">📈 Product Profitability Analysis (Sales Revenue vs Formulation Cost)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Product Formulation</th>
                            <th>Units Sold</th>
                            <th>Gross Sales Revenue</th>
                            <th>Cost of Goods (COGS)</th>
                            <th>Gross Profit (PKR)</th>
                            <th>Profit Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productProfit as $pp): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($pp['code']) ?></code></td>
                                <td><strong><?= htmlspecialchars($pp['product_name']) ?></strong></td>
                                <td><?= number_format($pp['total_sold']) ?></td>
                                <td><strong>Rs. <?= number_format($pp['total_sales_revenue']) ?></strong></td>
                                <td>Rs. <?= number_format($pp['total_cost']) ?></td>
                                <td><strong style="color: #10b981; font-size: 14px;">Rs. <?= number_format($pp['gross_profit']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $pp['profit_margin_pct'] >= 15 ? 'badge-success' : 'badge-primary' ?>">
                                        <?= $pp['profit_margin_pct'] ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'customer_profit'): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">🏥 Pharmacy Client Account Commercial Value</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Pharmacy / Medical Store</th>
                            <th>Category</th>
                            <th>Invoices Billed</th>
                            <th>Total Gross Sales</th>
                            <th>Total Collections</th>
                            <th>Outstanding Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerProfit as $cp): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($cp['code']) ?></code></td>
                                <td><strong><?= htmlspecialchars($cp['business_name']) ?></strong></td>
                                <td><span class="badge badge-secondary"><?= strtoupper($cp['customer_type']) ?></span></td>
                                <td><?= $cp['invoice_count'] ?> Invoices</td>
                                <td><strong style="color: #0284c7; font-size: 14px;">Rs. <?= number_format($cp['total_revenue']) ?></strong></td>
                                <td><strong style="color: #10b981;">Rs. <?= number_format($cp['total_collected']) ?></strong></td>
                                <td>
                                    <strong style="color: <?= $cp['outstanding_balance'] > 0 ? '#dc2626' : '#64748b' ?>;">
                                        Rs. <?= number_format($cp['outstanding_balance']) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">🐢 Slow-Moving &amp; Dormant Inventory (No Sales Recorded in Past 30 Days)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Product Formulation</th>
                            <th>Manufacturer</th>
                            <th>Dosage &amp; Strength</th>
                            <th>Current Unsold Stock</th>
                            <th>Tied-Up Working Capital</th>
                            <th>Suggested Strategy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slowMoving)): ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 24px;">All formulations have active sales transactions. Great inventory turnover!</td></tr>
                        <?php else: ?>
                            <?php foreach ($slowMoving as $sm): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($sm['code']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($sm['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($sm['manufacturer_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($sm['dosage_form']) ?> &bull; <?= htmlspecialchars($sm['strength']) ?></td>
                                    <td><strong style="color: #dc2626;"><?= $sm['current_stock'] ?></strong> <?= $sm['unit'] ?>s</td>
                                    <td><strong>Rs. <?= number_format($sm['tied_up_capital']) ?></strong></td>
                                    <td>
                                        <a href="schemes.php" class="btn btn-secondary btn-sm">+ Launch Bonus Scheme</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
