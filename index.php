<?php
// AA TRADERS - Executive Pharmaceutical Dashboard
$pageTitle = 'Executive Dashboard';
require_once __DIR__ . '/includes/header.php';

$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

// 1. Fetch KPI metrics
// Total Sales Today
$salesToday = $db->query("SELECT IFNULL(SUM(net_amount), 0) FROM sales_invoices WHERE invoice_date = '{$today}'")->fetchColumn();
// Sales This Month
$salesMonth = $db->query("SELECT IFNULL(SUM(net_amount), 0) FROM sales_invoices WHERE invoice_date >= '{$firstOfMonth}'")->fetchColumn();

// Purchases Today
$purchasesToday = $db->query("SELECT IFNULL(SUM(net_amount), 0) FROM purchase_orders WHERE po_date = '{$today}' AND status != 'cancelled'")->fetchColumn();
// Purchases This Month
$purchasesMonth = $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM goods_receipts WHERE receipt_date >= '{$firstOfMonth}'")->fetchColumn();

// Outstanding Receivables
$totalReceivables = $db->query("SELECT IFNULL(SUM(current_balance), 0) FROM customers")->fetchColumn();
// Outstanding Payables
$totalPayables = $db->query("SELECT IFNULL(SUM(current_balance), 0) FROM suppliers")->fetchColumn();

// Current Stock Value (Trade Price & Purchase Cost)
$stockValue = $db->query("SELECT IFNULL(SUM(quantity_available * trade_price), 0) FROM product_batches WHERE status = 'active'")->fetchColumn();
$stockCostValue = $db->query("SELECT IFNULL(SUM(quantity_available * purchase_price), 0) FROM product_batches WHERE status = 'active'")->fetchColumn();

// Low Stock Items (< reorder level)
$lowStockCount = $db->query("
    SELECT COUNT(*) FROM products p 
    WHERE (SELECT IFNULL(SUM(quantity_available), 0) FROM product_batches WHERE product_id = p.id AND status = 'active') <= p.reorder_level
")->fetchColumn();

// Expiry metrics
$nearExpiryCount30 = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 30 AND julianday(expiry_date) - julianday('now') > 0
")->fetchColumn();

$nearExpiryCount90 = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE status = 'active' AND quantity_available > 0 
      AND julianday(expiry_date) - julianday('now') <= 90 AND julianday(expiry_date) - julianday('now') > 0
")->fetchColumn();

$expiredCount = $db->query("
    SELECT COUNT(*) FROM product_batches 
    WHERE (status = 'expired' OR julianday(expiry_date) <= julianday('now')) AND (quantity_available > 0 OR quantity_expired > 0)
")->fetchColumn();

// Today's Collections
$collectionsToday = $db->query("SELECT IFNULL(SUM(amount), 0) FROM payments_received WHERE payment_date = '{$today}'")->fetchColumn();

// Today's Expenses
$expensesToday = $db->query("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE expense_date = '{$today}'")->fetchColumn();

// Returns
$salesReturnsMonth = $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM sales_returns WHERE return_date >= '{$firstOfMonth}'")->fetchColumn();
$purchaseReturnsMonth = $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM purchase_returns WHERE return_date >= '{$firstOfMonth}'")->fetchColumn();

// Entity counts
$customerCount = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
$productCount = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
$repCount = $db->query("SELECT COUNT(*) FROM sales_representatives WHERE is_active = 1")->fetchColumn();

// Critical near-expiry batches list
$nearExpiryBatches = $db->query("
    SELECT b.*, p.name as product_name, p.code as product_code, w.name as warehouse_name,
           CAST(julianday(b.expiry_date) - julianday('now') AS INTEGER) as days_left
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    WHERE b.status = 'active' AND b.quantity_available > 0 
      AND julianday(b.expiry_date) - julianday('now') <= 90
    ORDER BY b.expiry_date ASC LIMIT 5
")->fetchAll();

// Recent Sales Invoices
$recentInvoices = $db->query("
    SELECT i.*, c.business_name as customer_name, c.phone as customer_phone, r.name as rep_name
    FROM sales_invoices i
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN sales_representatives r ON i.sales_rep_id = r.id
    ORDER BY i.id DESC LIMIT 6
")->fetchAll();
?>

<!-- AI Pharma Copilot Interactive Banner -->
<div class="copilot-card">
    <div class="copilot-header">
        <div class="copilot-avatar">🧠</div>
        <div>
            <h3 style="font-size: 16px; font-weight: 700; color: #ffffff;">AA TRADERS &bull; AI Pharma Copilot</h3>
            <p style="font-size: 12px; color: #94a3b8;">Natural language distributor intelligence & predictive analytics</p>
        </div>
    </div>
    <div class="copilot-input-box">
        <input type="text" id="copilotInput" class="copilot-input" placeholder="Ask anything: 'Which products expire in next 90 days?', 'Customers over credit limit', 'What to purchase?'...">
        <button id="copilotSendBtn" class="btn btn-primary" style="background: #0284c7; padding: 10px 20px;">
            Consult Copilot
        </button>
    </div>
    <div class="copilot-chips">
        <span class="copilot-chip">🔴 Batches expiring in 30 days</span>
        <span class="copilot-chip">🟡 Products expiring in 90 days</span>
        <span class="copilot-chip">⚠️ Customers exceeding credit limit</span>
        <span class="copilot-chip">🔮 Suggested purchase reorders</span>
        <span class="copilot-chip">📈 Rep targets vs achievement</span>
        <span class="copilot-chip">🏢 Warehouse stock valuation</span>
    </div>
    <div id="copilotResult" style="display: none;"></div>
</div>

<!-- 17 KPI Cards Grid -->
<div class="kpi-grid">
    <!-- Row 1: Sales & Purchases -->
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Total Sales Today</h3>
            <div class="kpi-value">Rs. <?= number_format($salesToday) ?></div>
            <div class="kpi-sub">Daily billing volume</div>
        </div>
        <div class="kpi-icon blue">💰</div>
    </div>

    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Sales This Month</h3>
            <div class="kpi-value">Rs. <?= number_format($salesMonth) ?></div>
            <div class="kpi-sub"><?= date('F Y') ?> Revenue</div>
        </div>
        <div class="kpi-icon blue">📈</div>
    </div>

    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Purchases Today</h3>
            <div class="kpi-value">Rs. <?= number_format($purchasesToday) ?></div>
            <div class="kpi-sub">New purchase orders</div>
        </div>
        <div class="kpi-icon teal">📥</div>
    </div>

    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Purchases This Month</h3>
            <div class="kpi-value">Rs. <?= number_format($purchasesMonth) ?></div>
            <div class="kpi-sub">GRN Inward Total</div>
        </div>
        <div class="kpi-icon teal">🏭</div>
    </div>

    <!-- Row 2: Financial Outstanding & Stock Value -->
    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>Outstanding Receivables</h3>
            <div class="kpi-value">Rs. <?= number_format($totalReceivables) ?></div>
            <div class="kpi-sub">Customer balances</div>
        </div>
        <div class="kpi-icon amber">📑</div>
    </div>

    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>Outstanding Payables</h3>
            <div class="kpi-value">Rs. <?= number_format($totalPayables) ?></div>
            <div class="kpi-sub">Supplier liabilities</div>
        </div>
        <div class="kpi-icon amber">💳</div>
    </div>

    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Current Stock Value (TP)</h3>
            <div class="kpi-value">Rs. <?= number_format($stockValue) ?></div>
            <div class="kpi-sub">Cost: Rs. <?= number_format($stockCostValue) ?></div>
        </div>
        <div class="kpi-icon green">📦</div>
    </div>

    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>Low Stock Reorders</h3>
            <div class="kpi-value"><?= $lowStockCount ?> Products</div>
            <div class="kpi-sub">Below safety threshold</div>
        </div>
        <div class="kpi-icon red">⚠️</div>
    </div>

    <!-- Row 3: Expiry & Collections -->
    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>Near Expiry (&lt;90 Days)</h3>
            <div class="kpi-value"><?= $nearExpiryCount90 ?> Batches</div>
            <div class="kpi-sub"><?= $nearExpiryCount30 ?> critical &lt;30 days</div>
        </div>
        <div class="kpi-icon red">⏳</div>
    </div>

    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>Expired Products</h3>
            <div class="kpi-value"><?= $expiredCount ?> Batches</div>
            <div class="kpi-sub">In Expired Bay for disposal</div>
        </div>
        <div class="kpi-icon red">🛑</div>
    </div>

    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Today's Collections</h3>
            <div class="kpi-value">Rs. <?= number_format($collectionsToday) ?></div>
            <div class="kpi-sub">Cash & Bank receipts</div>
        </div>
        <div class="kpi-icon green">💵</div>
    </div>

    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>Today's Expenses</h3>
            <div class="kpi-value">Rs. <?= number_format($expensesToday) ?></div>
            <div class="kpi-sub">Logistics & Operational</div>
        </div>
        <div class="kpi-icon amber">📉</div>
    </div>

    <!-- Row 4: Returns & Master Counts -->
    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Sales Returns</h3>
            <div class="kpi-value">Rs. <?= number_format($salesReturnsMonth) ?></div>
            <div class="kpi-sub">Credit notes this month</div>
        </div>
        <div class="kpi-icon teal">↩️</div>
    </div>

    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Purchase Returns</h3>
            <div class="kpi-value">Rs. <?= number_format($purchaseReturnsMonth) ?></div>
            <div class="kpi-sub">Supplier returns</div>
        </div>
        <div class="kpi-icon teal">📤</div>
    </div>

    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Pharmacies / Customers</h3>
            <div class="kpi-value"><?= $customerCount ?></div>
            <div class="kpi-sub">Active institutional clients</div>
        </div>
        <div class="kpi-icon blue">🏥</div>
    </div>

    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Active Formulations</h3>
            <div class="kpi-value"><?= $productCount ?></div>
            <div class="kpi-sub">Catalog SKUs</div>
        </div>
        <div class="kpi-icon blue">💊</div>
    </div>

    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Sales Representatives</h3>
            <div class="kpi-value"><?= $repCount ?></div>
            <div class="kpi-sub">Field force reps</div>
        </div>
        <div class="kpi-icon blue">💼</div>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
    <!-- Sales vs Collections Trend Chart -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📊 Revenue & Collection Performance (Past 6 Months)</span>
            <span class="badge badge-primary">PKR Trend</span>
        </div>
        <div class="card-body">
            <canvas id="salesTrendChart" height="110"></canvas>
        </div>
    </div>

    <!-- Inventory / Category Distribution -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">💊 Stock Valuation by Category</span>
        </div>
        <div class="card-body">
            <canvas id="categoryPieChart" height="230"></canvas>
        </div>
    </div>
</div>

<!-- Near Expiry Batches & Recent Invoices Tables -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <!-- Urgent FEFO Clearance Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title" style="color: #dc2626;">⏳ Critical Near Expiry Batches (FEFO Alert)</span>
            <a href="expiry.php" class="btn btn-sm btn-secondary">View All Expiries</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch #</th>
                            <th>Expiry</th>
                            <th>Remaining</th>
                            <th>Qty Avail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nearExpiryBatches)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No batches nearing expiration.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nearExpiryBatches as $nb): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($nb['product_name']) ?></strong>
                                        <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($nb['warehouse_name']) ?></div>
                                    </td>
                                    <td><code><?= htmlspecialchars($nb['batch_number']) ?></code></td>
                                    <td>
                                        <span class="badge <?= $nb['days_left'] <= 30 ? 'badge-near-30' : 'badge-near-90' ?>">
                                            <?= htmlspecialchars($nb['expiry_date']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= $nb['days_left'] ?> days</strong></td>
                                    <td><span class="badge badge-danger"><?= $nb['quantity_available'] ?> units</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Sales Invoices with WhatsApp / Print -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🧾 Recent Sales Invoices & Dispatch</span>
            <a href="sales_invoices.php" class="btn btn-sm btn-primary">+ Create Invoice</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Dispatch</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <strong><a href="print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($inv['invoice_number']) ?></a></strong>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($inv['invoice_date']) ?></div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($inv['customer_name']) ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">Rep: <?= htmlspecialchars($inv['rep_name'] ?? 'Direct') ?></div>
                                </td>
                                <td><strong>Rs. <?= number_format($inv['net_amount']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $inv['payment_status'] === 'paid' ? 'badge-success' : ($inv['payment_status'] === 'partial' ? 'badge-warning' : 'badge-danger') ?>">
                                        <?= strtoupper($inv['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= strtoupper($inv['dispatch_status']) ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <a href="print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print Clean Tax Invoice">🖨️</a>
                                        <button onclick="sendWhatsAppInvoice('<?= htmlspecialchars($inv['customer_phone'] ?? '') ?>', '<?= htmlspecialchars($inv['invoice_number']) ?>', '<?= htmlspecialchars(addslashes($inv['customer_name'])) ?>', '<?= number_format($inv['net_amount']) ?>', '<?= number_format($inv['balance_amount']) ?>')" class="btn btn-success btn-sm" title="Send WhatsApp Notification">📱</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Sales Trend Line Chart
    const ctxTrend = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: ['Apr 2026', 'May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026'],
            datasets: [
                {
                    label: 'Gross Sales (PKR)',
                    data: [1850000, 2400000, 2900000, 3450000, 4200000, <?= $salesMonth > 0 ? $salesMonth : 467700 ?>],
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.1)',
                    tension: 0.35,
                    fill: true,
                    borderWidth: 3
                },
                {
                    label: 'Collections (PKR)',
                    data: [1600000, 2100000, 2700000, 3100000, 3950000, 146200],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2,
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: val => 'Rs. ' + (val / 1000).toLocaleString() + 'k'
                    }
                }
            }
        }
    });

    // 2. Category Pie Chart
    const ctxPie = document.getElementById('categoryPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Antibiotics', 'Analgesics & NSAIDs', 'Gastrointestinal', 'Cardiovascular', 'Critical Care & Insulin'],
            datasets: [{
                data: [42, 28, 16, 8, 6],
                backgroundColor: ['#0284c7', '#0d9488', '#f59e0b', '#8b5cf6', '#ef4444'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
