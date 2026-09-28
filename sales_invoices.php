<?php
// AA TRADERS - Sales Invoicing & FEFO Execution Engine
$pageTitle = 'Sales Invoices & Billing';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/FEFO.php';
require_once __DIR__ . '/includes/SchemeEngine.php';

// Handle Direct Invoice Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {
    $customer_id = (int)$_POST['customer_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $sales_rep_id = !empty($_POST['sales_rep_id']) ? (int)$_POST['sales_rep_id'] : null;
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $items = $_POST['items'] ?? [];
    $notes = trim($_POST['notes'] ?? '');

    // Customer Credit Check
    $cust = $db->query("SELECT * FROM customers WHERE id = {$customer_id}")->fetch();
    if ($cust && $cust['is_blocked']) {
        $error = "Invoice Generation Refused: Customer '{$cust['business_name']}' is credit frozen. Resolve outstanding balance before invoicing.";
    } elseif (empty($items)) {
        $error = "Please specify line items for the invoice.";
    } else {
        $db->beginTransaction();
        $invNum = 'INV-' . date('Y') . '-' . rand(1000, 9999);
        $subtotal = 0.0;
        $totalSchemeDiscount = 0.0;
        $totalTax = 0.0;

        $stmtInv = $db->prepare("INSERT INTO sales_invoices (
            invoice_number, invoice_date, due_date, customer_id, sales_rep_id, warehouse_id, 
            subtotal, scheme_discount, net_amount, balance_amount, payment_status, dispatch_status, notes, created_by
        ) VALUES (?, date('now'), ?, ?, ?, ?, 0, 0, 0, 0, 'unpaid', 'pending', ?, ?)");
        $stmtInv->execute([$invNum, $due_date, $customer_id, $sales_rep_id, $warehouse_id, $notes, $currentUser['id']]);
        $invoiceId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO sales_invoice_items (
            invoice_id, product_id, batch_id, quantity, bonus_quantity, unit_trade_price, mrp, discount_amount, tax_amount, net_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $reqQty = (int)$item['quantity'];

            if ($productId > 0 && $reqQty > 0) {
                // FEFO Allocation: Automatically pick earliest expiring batches
                $fefoAllocation = FEFO::recommendAllocation($productId, $reqQty, $warehouse_id);
                $scheme = SchemeEngine::calculateScheme($productId, $reqQty);
                $bonusAssigned = false;

                if (!empty($fefoAllocation['allocations'])) {
                    foreach ($fefoAllocation['allocations'] as $alloc) {
                        $batchId = $alloc['batch_id'];
                        $allocQty = $alloc['allocated_qty'];
                        $tp = $alloc['trade_price'];
                        $mrp = $alloc['mrp'];
                        $bonusQty = !$bonusAssigned ? $scheme['bonus_quantity'] : 0;
                        $bonusAssigned = true;

                        $lineTotal = $allocQty * $tp;
                        $discAmount = 0.0;
                        if ($scheme['discount_percentage'] > 0) {
                            $discAmount = $lineTotal * ($scheme['discount_percentage'] / 100);
                            $lineTotal -= $discAmount;
                        }

                        $stmtItem->execute([$invoiceId, $productId, $batchId, $allocQty, $bonusQty, $tp, $mrp, $discAmount, 0.0, $lineTotal]);
                        $subtotal += ($allocQty * $tp);
                        $totalSchemeDiscount += $discAmount;

                        // Deduct from batch inventory
                        $totalDeduct = $allocQty + $bonusQty;
                        $db->query("UPDATE product_batches SET quantity_available = quantity_available - {$totalDeduct} WHERE id = {$batchId}");

                        // Record Inventory Movement
                        $stmtMov = $db->prepare("INSERT INTO inventory_transactions (
                            product_id, batch_id, warehouse_id, transaction_type, reference_type, reference_id, quantity, unit_cost, notes, created_by
                        ) VALUES (?, ?, ?, 'sale', 'invoice', ?, ?, ?, ?, ?)");
                        $stmtMov->execute([$productId, $batchId, $warehouse_id, $invNum, -$totalDeduct, $tp, "Sales Invoice {$invNum} ({$allocQty} sold + {$bonusQty} bonus)", $currentUser['id']]);
                    }
                }
            }
        }

        $netAmount = $subtotal - $totalSchemeDiscount;
        // Update Invoice totals
        $db->query("UPDATE sales_invoices SET subtotal = {$subtotal}, scheme_discount = {$totalSchemeDiscount}, net_amount = {$netAmount}, balance_amount = {$netAmount} WHERE id = {$invoiceId}");

        // Update Customer Ledger & Current Balance
        $newCustBalance = $cust['current_balance'] + $netAmount;
        $db->query("UPDATE customers SET current_balance = {$newCustBalance} WHERE id = {$customer_id}");

        $stmtLedger = $db->prepare("INSERT INTO customer_ledgers (
            customer_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description
        ) VALUES (?, date('now'), 'invoice', ?, ?, 0.0, ?, ?)");
        $stmtLedger->execute([$customer_id, $invNum, $netAmount, $newCustBalance, "Sales Invoice {$invNum} with FEFO allocation"]);

        // Auto credit blocking check
        if ($newCustBalance > $cust['credit_limit']) {
            $db->query("UPDATE customers SET is_blocked = 1, block_reason = 'Credit limit of Rs. {$cust['credit_limit']} exceeded by recent invoice {$invNum}' WHERE id = {$customer_id}");
        }

        $db->commit();
        Database::logAudit('CREATE', 'SalesInvoices', $invNum, "Generated Invoice {$invNum} for {$cust['business_name']} - Rs. {$netAmount}");
        header("Location: sales_invoices.php?msg=invoice_created&new_id={$invoiceId}");
        exit;
    }
}

// Fetch Invoices
$invoices = $db->query("
    SELECT i.*, c.business_name as customer_name, c.phone as customer_phone,
           r.name as rep_name, w.name as warehouse_name
    FROM sales_invoices i
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN sales_representatives r ON i.sales_rep_id = r.id
    JOIN warehouses w ON i.warehouse_id = w.id
    ORDER BY i.id DESC
")->fetchAll();

$customers = $db->query("SELECT * FROM customers WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
$products = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$reps = $db->query("SELECT * FROM sales_representatives WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Sales Invoices &amp; Tax Billing (FEFO)</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Automated First-Expiry-First-Out batch assignment, bonus schemes (10+1), customer ledger debit &amp; dispatch tracking</p>
    </div>
    <button onclick="openModal('addInvoiceModal')" class="btn btn-primary">+ Generate Sales Invoice</button>
</div>

<?php if (isset($error)): ?>
    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'invoice_created'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
        <span>✓ Sales Invoice successfully generated! FEFO batches deducted &amp; customer ledger updated.</span>
        <?php if (isset($_GET['new_id'])): ?>
            <a href="print_invoice.php?id=<?= (int)$_GET['new_id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Print Clean Tax Invoice</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date &amp; Due</th>
                        <th>Customer / Pharmacy</th>
                        <th>Sales Rep</th>
                        <th>Subtotal</th>
                        <th>Scheme Disc</th>
                        <th>Net Total</th>
                        <th>Payment Status</th>
                        <th>Dispatch Status</th>
                        <th>Print &amp; WhatsApp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="10" style="text-align: center; color: var(--slate-400); padding: 24px;">No invoices generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td>
                                    <strong><a href="print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($inv['invoice_number']) ?></a></strong>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($inv['invoice_date']) ?></div>
                                    <div style="font-size: 11px; color: var(--slate-500);">Due: <?= htmlspecialchars($inv['due_date'] ?? 'On Receipt') ?></div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($inv['customer_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($inv['rep_name'] ?? 'Direct') ?></td>
                                <td>Rs. <?= number_format($inv['subtotal']) ?></td>
                                <td>
                                    <?= $inv['scheme_discount'] > 0 ? '<span style="color: #10b981;">- Rs. ' . number_format($inv['scheme_discount']) . '</span>' : 'Rs. 0' ?>
                                </td>
                                <td>
                                    <strong style="font-size: 14px; color: var(--slate-900);">Rs. <?= number_format($inv['net_amount']) ?></strong>
                                    <?php if ($inv['balance_amount'] > 0): ?>
                                        <div style="font-size: 10.5px; color: #dc2626;">Due: Rs. <?= number_format($inv['balance_amount']) ?></div>
                                    <?php endif; ?>
                                </td>
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
                                        <a href="print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print Clean Tax Invoice">🖨️ Print</a>
                                        <button onclick="sendWhatsAppInvoice('<?= htmlspecialchars($inv['customer_phone'] ?? '') ?>', '<?= htmlspecialchars($inv['invoice_number']) ?>', '<?= htmlspecialchars(addslashes($inv['customer_name'])) ?>', '<?= number_format($inv['net_amount']) ?>', '<?= number_format($inv['balance_amount']) ?>')" class="btn btn-success btn-sm" title="Send WhatsApp">📱</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Sales Invoice with FEFO Batch Allocation -->
<div class="modal-overlay" id="addInvoiceModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Generate Sales Tax Invoice (FEFO Automated Allocation)</h3>
            <button class="modal-close" onclick="closeModal('addInvoiceModal')">&times;</button>
        </div>
        <form method="POST" action="sales_invoices.php">
            <input type="hidden" name="action" value="create_invoice">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Select Customer / Pharmacy *</label>
                        <select name="customer_id" class="form-select" required>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['business_name']) ?> (Limit: Rs. <?= number_format($c['credit_limit']) ?>, Bal: Rs. <?= number_format($c['current_balance']) ?>) <?= $c['is_blocked'] ? '[BLOCKED]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dispatch Warehouse *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sales Representative</label>
                        <select name="sales_rep_id" class="form-select">
                            <option value="">-- Direct Sale --</option>
                            <?php foreach ($reps as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Due Date *</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" style="margin-bottom: 0;">Pharmaceutical Formulations &amp; Quantities</label>
                        <span style="font-size: 11px; color: #0284c7; font-weight: 600;">✨ System will auto-assign FEFO batches &amp; bonus schemes</span>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
                        <thead>
                            <tr style="background: var(--slate-100); text-align: left; font-size: 12px;">
                                <th style="padding: 8px;">Product Formulation</th>
                                <th style="padding: 8px; width: 140px;">Quantity (Packs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <tr>
                                    <td style="padding: 6px;">
                                        <select name="items[<?= $i ?>][product_id]" class="form-select">
                                            <option value="">-- Select Product Formulation --</option>
                                            <?php foreach ($products as $pr): ?>
                                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['strength']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="padding: 6px;">
                                        <input type="number" name="items[<?= $i ?>][quantity]" class="form-control" min="1" value="<?= $i === 0 ? 10 : '' ?>" placeholder="Packs">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label class="form-label">Special Invoicing Notes / Gate Pass Instructions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Include 10+1 free bonus packs on delivery challan."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addInvoiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Invoice &amp; FEFO Allocate</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
