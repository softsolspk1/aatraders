<?php
// AA TRADERS - Purchase Returns & Debit Notes
$pageTitle = 'Purchase Returns & Debit Notes';
require_once __DIR__ . '/includes/header.php';

// Handle Add Purchase Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_pr') {
    $supplier_id = (int)$_POST['supplier_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $batch_id = (int)$_POST['batch_id'];
    $quantity = (int)$_POST['quantity'];
    $reason = $_POST['reason'];
    $notes = trim($_POST['notes'] ?? '');

    $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batch_id}")->fetch();
    $sup = $db->query("SELECT * FROM suppliers WHERE id = {$supplier_id}")->fetch();

    if ($batch && $sup && $quantity > 0) {
        $db->beginTransaction();
        $returnNo = 'PRTN-' . date('Ymd') . '-' . rand(100, 999);
        $totalDebit = $quantity * $batch['purchase_price'];

        $stmtRet = $db->prepare("INSERT INTO purchase_returns (return_no, return_date, supplier_id, warehouse_id, reason, total_amount, notes, created_by) 
                                 VALUES (?, date('now'), ?, ?, ?, ?, ?, ?)");
        $stmtRet->execute([$returnNo, $supplier_id, $warehouse_id, $reason, $totalDebit, $notes, $currentUser['id']]);
        $returnId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO purchase_return_items (return_id, product_id, batch_id, quantity, unit_cost, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtItem->execute([$returnId, $batch['product_id'], $batch_id, $quantity, $batch['purchase_price'], $totalDebit]);

        // Deduct from batch inventory
        $db->query("UPDATE product_batches SET quantity_available = max(0, quantity_available - {$quantity}) WHERE id = {$batch_id}");

        // Record Inventory Movement
        $stmtMov = $db->prepare("INSERT INTO inventory_transactions (
            product_id, batch_id, warehouse_id, transaction_type, reference_type, reference_id, quantity, unit_cost, notes, created_by
        ) VALUES (?, ?, ?, 'purchase_return', 'debit_note', ?, ?, ?, ?, ?)");
        $stmtMov->execute([$batch['product_id'], $batch_id, $warehouse_id, $returnNo, -$quantity, $batch['purchase_price'], "Purchase Return to {$sup['name']}. Reason: {$reason}", $currentUser['id']]);

        // Debit Supplier Ledger (Reduces liability)
        $newPayable = max(0, $sup['current_balance'] - $totalDebit);
        $db->query("UPDATE suppliers SET current_balance = {$newPayable} WHERE id = {$supplier_id}");

        $stmtLed = $db->prepare("INSERT INTO supplier_ledgers (supplier_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description) 
                                 VALUES (?, date('now'), 'debit_note', ?, ?, 0.0, ?, ?)");
        $stmtLed->execute([$supplier_id, $returnNo, $totalDebit, $newPayable, "Debit Note {$returnNo} for returned stock ({$reason})"]);

        $db->commit();
        Database::logAudit('PURCHASE_RETURN', 'Purchases', $returnNo, "Dispatched Purchase Return {$returnNo} to {$sup['name']} - Rs. {$totalDebit}");
        header('Location: purchase_returns.php?msg=pr_logged');
        exit;
    }
}

// Fetch Returns
$returns = $db->query("
    SELECT r.*, s.name as supplier_name, w.name as warehouse_name,
           pri.quantity, p.name as product_name, pb.batch_number
    FROM purchase_returns r
    JOIN suppliers s ON r.supplier_id = s.id
    JOIN warehouses w ON r.warehouse_id = w.id
    LEFT JOIN purchase_return_items pri ON r.id = pri.return_id
    LEFT JOIN products p ON pri.product_id = p.id
    LEFT JOIN product_batches pb ON pri.batch_id = pb.id
    ORDER BY r.id DESC
")->fetchAll();

$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allBatches = $db->query("
    SELECT b.id, b.batch_number, b.purchase_price, b.quantity_available, p.name as product_name, w.name as warehouse_name
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    ORDER BY p.name ASC
")->fetchAll();
$warehouses = $db->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Purchase Returns &amp; Debit Notes (Supplier Returns)</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Return Damaged, Near Expiry or Recalled Batches to Principal Manufacturers &amp; Debit Payable Balances</p>
    </div>
    <button onclick="openModal('addPrModal')" class="btn btn-primary">+ Dispatch Purchase Return</button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Purchase return processed and Debit Note issued. Supplier liability has been debited.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Debit Note #</th>
                        <th>Return Date</th>
                        <th>Principal Supplier</th>
                        <th>Product &amp; Batch</th>
                        <th>Returned Qty</th>
                        <th>Debit Amount</th>
                        <th>Return Justification</th>
                        <th>Source Facility</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--slate-400); padding: 24px;">No supplier purchase returns logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($r['return_no']) ?></code></td>
                                <td><?= htmlspecialchars($r['return_date']) ?></td>
                                <td><strong><?= htmlspecialchars($r['supplier_name']) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['product_name'] ?? 'Multiple Items') ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">Batch: <?= htmlspecialchars($r['batch_number'] ?? '-') ?></div>
                                </td>
                                <td><span class="badge badge-warning"><?= $r['quantity'] ?> units</span></td>
                                <td><strong style="color: #dc2626;">Rs. <?= number_format($r['total_amount']) ?></strong></td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['reason']))) ?></span></td>
                                <td><?= htmlspecialchars($r['warehouse_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Purchase Return -->
<div class="modal-overlay" id="addPrModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Dispatch Supplier Purchase Return &amp; Debit Note</h3>
            <button class="modal-close" onclick="closeModal('addPrModal')">&times;</button>
        </div>
        <form method="POST" action="purchase_returns.php">
            <input type="hidden" name="action" value="create_pr">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Principal Supplier Receiving Return *</label>
                    <select name="supplier_id" class="form-select" required>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Batch Being Returned *</label>
                    <select name="batch_id" class="form-select" required>
                        <?php foreach ($allBatches as $ab): ?>
                            <option value="<?= $ab['id'] ?>">
                                <?= htmlspecialchars($ab['product_name']) ?> &mdash; Batch: <?= htmlspecialchars($ab['batch_number']) ?> (Avail: <?= $ab['quantity_available'] ?>, Cost: Rs. <?= number_format($ab['purchase_price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Units to Return *</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dispatching Warehouse *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Return Reason *</label>
                    <select name="reason" class="form-select" required>
                        <option value="near_expiry">Near Expiry / Shelf Life Non-compliance</option>
                        <option value="expired">Expired Stock</option>
                        <option value="damaged_stock">Damaged in Transit / Factory Defect</option>
                        <option value="wrong_product">Wrong Formulation / Pack Size Supplied</option>
                        <option value="recall">DRAP Regulatory Recall</option>
                        <option value="quality_issue">Quality / Precipitate / Color Change Issue</option>
                        <option value="excess_stock">Excess Inventory Replenishment Return</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes &amp; Debit Voucher Details</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Attached Quality Control report with Return Gate Pass."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPrModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Return &amp; Issue Debit Note</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
