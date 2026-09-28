<?php
// AA TRADERS - Accounts Receivable, Payable & Ledgers
$pageTitle = 'Accounts & Ledgers';
require_once __DIR__ . '/includes/header.php';

$tab = $_GET['tab'] ?? 'receivable';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 1;
$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 1;

// 1. Customer Ledger Data
$customerLedger = [];
$selectedCustomer = null;
if ($tab === 'receivable' || $tab === 'cust_ledger') {
    $selectedCustomer = $db->query("SELECT * FROM customers WHERE id = {$customerId}")->fetch();
    $customerLedger = $db->query("
        SELECT * FROM customer_ledgers 
        WHERE customer_id = {$customerId} 
        ORDER BY transaction_date ASC, id ASC
    ")->fetchAll();
}

// 2. Supplier Ledger Data
$supplierLedger = [];
$selectedSupplier = null;
if ($tab === 'payable' || $tab === 'sup_ledger') {
    $selectedSupplier = $db->query("SELECT * FROM suppliers WHERE id = {$supplierId}")->fetch();
    $supplierLedger = $db->query("
        SELECT * FROM supplier_ledgers 
        WHERE supplier_id = {$supplierId} 
        ORDER BY transaction_date ASC, id ASC
    ")->fetchAll();
}

// Receivables Aging summary
$receivables = $db->query("
    SELECT c.*, t.territory_name,
           ROUND((c.current_balance / c.credit_limit) * 100, 1) as utilization_pct
    FROM customers c
    LEFT JOIN territories t ON c.territory_id = t.id
    WHERE c.current_balance > 0
    ORDER BY c.current_balance DESC
")->fetchAll();

$payables = $db->query("
    SELECT s.*
    FROM suppliers s
    WHERE s.current_balance > 0
    ORDER BY s.current_balance DESC
")->fetchAll();

$customersList = $db->query("SELECT id, business_name FROM customers ORDER BY business_name ASC")->fetchAll();
$suppliersList = $db->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Accounts &amp; Financial Ledgers</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Opening Balance &plus; Invoices &minus; Returns &minus; Collections &equals; Running Balance</p>
    </div>
</div>

<!-- Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
    <a href="accounts.php?tab=receivable" class="btn <?= $tab === 'receivable' ? 'btn-primary' : 'btn-secondary' ?>">
        🏥 Accounts Receivable &amp; Customer Ledger
    </a>
    <a href="accounts.php?tab=payable" class="btn <?= $tab === 'payable' ? 'btn-primary' : 'btn-secondary' ?>">
        🏭 Accounts Payable &amp; Supplier Ledger
    </a>
</div>

<?php if ($tab === 'receivable'): ?>
    <!-- Customer Selector Bar -->
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-body" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <label style="font-weight: 700; font-size: 13px;">Select Customer Ledger:</label>
                <select onchange="window.location.href='accounts.php?tab=receivable&customer_id=' + this.value" class="form-select" style="width: 320px;">
                    <?php foreach ($customersList as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $cl['id'] === $customerId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['business_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedCustomer): ?>
                <div style="display: flex; gap: 20px; font-size: 13px;">
                    <div>Credit Limit: <strong>Rs. <?= number_format($selectedCustomer['credit_limit']) ?></strong></div>
                    <div>Outstanding: <strong style="color: #dc2626; font-size: 14px;">Rs. <?= number_format($selectedCustomer['current_balance']) ?></strong></div>
                    <div>Available Credit: <strong style="color: #10b981;">Rs. <?= number_format(max(0, $selectedCustomer['credit_limit'] - $selectedCustomer['current_balance'])) ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer Ledger Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📜 Customer Running Ledger: <?= htmlspecialchars($selectedCustomer['business_name'] ?? '') ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm no-print">🖨️ Print Statement</button>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction Type</th>
                            <th>Reference #</th>
                            <th>Description</th>
                            <th style="text-align: right;">Debit (PKR)</th>
                            <th style="text-align: right;">Credit (PKR)</th>
                            <th style="text-align: right;">Running Balance (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customerLedger)): ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 24px;">No ledger entries found for this customer.</td></tr>
                        <?php else: ?>
                            <?php 
                            $runBal = 0.0;
                            foreach ($customerLedger as $entry): 
                                $runBal += ($entry['debit'] - $entry['credit']);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $entry['transaction_type'] === 'invoice' ? 'badge-primary' : ($entry['transaction_type'] === 'payment' ? 'badge-success' : 'badge-secondary') ?>">
                                            <?= strtoupper($entry['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><code><?= htmlspecialchars($entry['reference_no']) ?></code></td>
                                    <td><?= htmlspecialchars($entry['description']) ?></td>
                                    <td style="text-align: right; color: #dc2626; font-weight: 600;">
                                        <?= $entry['debit'] > 0 ? 'Rs. ' . number_format($entry['debit'], 2) : '-' ?>
                                    </td>
                                    <td style="text-align: right; color: #10b981; font-weight: 600;">
                                        <?= $entry['credit'] > 0 ? 'Rs. ' . number_format($entry['credit'], 2) : '-' ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; font-size: 13.5px;">
                                        Rs. <?= number_format($entry['balance'] > 0 ? $entry['balance'] : $runBal, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Supplier Selector Bar -->
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-body" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <label style="font-weight: 700; font-size: 13px;">Select Supplier Principal:</label>
                <select onchange="window.location.href='accounts.php?tab=payable&supplier_id=' + this.value" class="form-select" style="width: 320px;">
                    <?php foreach ($suppliersList as $sl): ?>
                        <option value="<?= $sl['id'] ?>" <?= $sl['id'] === $supplierId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedSupplier): ?>
                <div style="display: flex; gap: 20px; font-size: 13px;">
                    <div>Credit Terms: <strong><?= $selectedSupplier['payment_terms_days'] ?> Days</strong></div>
                    <div>Outstanding Payable: <strong style="color: #0284c7; font-size: 14px;">Rs. <?= number_format($selectedSupplier['current_balance']) ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Supplier Ledger Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🏭 Supplier Payable Ledger: <?= htmlspecialchars($selectedSupplier['name'] ?? '') ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm no-print">🖨️ Print Statement</button>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction Type</th>
                            <th>Reference #</th>
                            <th>Description</th>
                            <th style="text-align: right;">Debit (Payments/Returns)</th>
                            <th style="text-align: right;">Credit (Purchases)</th>
                            <th style="text-align: right;">Running Payable Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($supplierLedger)): ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 24px;">No ledger records found for this supplier.</td></tr>
                        <?php else: ?>
                            <?php foreach ($supplierLedger as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $entry['transaction_type'] === 'purchase' ? 'badge-primary' : ($entry['transaction_type'] === 'payment' ? 'badge-success' : 'badge-warning') ?>">
                                            <?= strtoupper($entry['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><code><?= htmlspecialchars($entry['reference_no']) ?></code></td>
                                    <td><?= htmlspecialchars($entry['description']) ?></td>
                                    <td style="text-align: right; color: #10b981; font-weight: 600;">
                                        <?= $entry['debit'] > 0 ? 'Rs. ' . number_format($entry['debit'], 2) : '-' ?>
                                    </td>
                                    <td style="text-align: right; color: #0284c7; font-weight: 600;">
                                        <?= $entry['credit'] > 0 ? 'Rs. ' . number_format($entry['credit'], 2) : '-' ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; font-size: 13.5px;">
                                        Rs. <?= number_format($entry['balance'], 2) ?>
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
