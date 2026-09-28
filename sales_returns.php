<?php
// AA TRADERS - Sales Returns & Credit Note Engine
$pageTitle = 'Sales Returns & Credit Notes';
require_once __DIR__ . '/includes/header.php';

// Handle Add Sales Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_return') {
    $customer_id = (int)$_POST['customer_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $batch_id = (int)$_POST['batch_id'];
    $quantity = (int)$_POST['quantity'];
    $reason = $_POST['reason'];
    $notes = trim($_POST['notes'] ?? '');

    $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batch_id}")->fetch();
    $cust = $db->query("SELECT * FROM customers WHERE id = {$customer_id}")->fetch();

    if ($batch && $cust && $quantity > 0) {
        $db->beginTransaction();
        $returnNo = 'SRTN-' . date('Ymd') . '-' . rand(100, 999);
        $refundAmount = $quantity * $batch['trade_price'];

        $stmtRet = $db->prepare("INSERT INTO sales_returns (
            return_no, return_date, customer_id, warehouse_id, reason, total_amount, refund_type, notes, created_by
        ) VALUES (?, date('now'), ?, ?, ?, ?, 'credit_note', ?, ?)");
        $stmtRet->execute([$returnNo, $customer_id, $warehouse_id, $reason, $refundAmount, $notes, $currentUser['id']]);
        $returnId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO sales_return_items (return_id, product_id, batch_id, quantity, unit_price, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtItem->execute([$returnId, $batch['product_id'], $batch_id, $quantity, $batch['trade_price'], $refundAmount]);

        // Stock Update based on reason: if damaged or expired, put into damaged/expired count, else restore available
        if (str_contains($reason, 'damage')) {
            $db->query("UPDATE product_batches SET quantity_damaged = quantity_damaged + {$quantity} WHERE id = {$batch_id}");
        } elseif (str_contains($reason, 'expire')) {
            $db->query("UPDATE product_batches SET quantity_expired = quantity_expired + {$quantity} WHERE id = {$batch_id}");
        } else {
            $db->query("UPDATE product_batches SET quantity_available = quantity_available + {$quantity} WHERE id = {$batch_id}");
        }

        // Record Inventory Transaction
        $stmtMov = $db->prepare("INSERT INTO inventory_transactions (
            product_id, batch_id, warehouse_id, transaction_type, reference_type, reference_id, quantity, unit_cost, notes, created_by
        ) VALUES (?, ?, ?, 'sales_return', 'sales_return', ?, ?, ?, ?, ?)");
        $stmtMov->execute([$batch['product_id'], $batch_id, $warehouse_id, $returnNo, $quantity, $batch['trade_price'], "Sales return from {$cust['business_name']}. Reason: {$reason}", $currentUser['id']]);

        // Credit Customer Ledger (reduces balance)
        $newBalance = max(0, $cust['current_balance'] - $refundAmount);
        $db->query("UPDATE customers SET current_balance = {$newBalance} WHERE id = {$customer_id}");

        $stmtLed = $db->prepare("INSERT INTO customer_ledgers (
            customer_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description
        ) VALUES (?, date('now'), 'credit_note', ?, 0.0, ?, ?, ?)");
        $stmtLed->execute([$customer_id, $returnNo, $refundAmount, $newBalance, "Credit Note {$returnNo} for returned goods ({$reason})"]);

        $db->commit();
        Database::logAudit('SALES_RETURN', 'Sales', $returnNo, "Processed Sales Return {$returnNo} from {$cust['business_name']} - Rs. {$refundAmount}");
        header('Location: sales_returns.php?msg=return_processed');
        exit;
    }
}

// Fetch Returns
$returns = $db->query("
    SELECT r.*, c.business_name as customer_name, w.name as warehouse_name,
           ri.quantity, p.name as product_name, pb.batch_number
    FROM sales_returns r
    JOIN customers c ON r.customer_id = c.id
    JOIN warehouses w ON r.warehouse_id = w.id
    LEFT JOIN sales_return_items ri ON r.id = ri.return_id
    LEFT JOIN products p ON ri.product_id = p.id
    LEFT JOIN product_batches pb ON ri.batch_id = pb.id
    ORDER BY r.id DESC
")->fetchAll();

$customers = $db->query("SELECT id, business_name FROM customers WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
$allBatches = $db->query("
    SELECT b.id, b.batch_number, b.trade_price, p.name as product_name
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    ORDER BY p.name ASC
")->fetchAll();
$warehouses = $db->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Sales Returns &amp; Credit Notes (DRAP Compliant)</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Process batch returns (Expired, Near-Expiry, Damaged, Quality Recall) &amp; Customer Ledger Credit</p>
    </div>
    <button onclick="openModal('addReturnModal')" class="btn btn-primary">+ Process Sales Return</button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Sales Return processed and Credit Note issued. Customer balance was credited and inventory isolated.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Credit Note #</th>
                        <th>Return Date</th>
                        <th>Pharmacy / Client</th>
                        <th>Product &amp; Batch</th>
                        <th>Qty Returned</th>
                        <th>Credit Amount</th>
                        <th>Return Reason</th>
                        <th>Warehouse Bay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--slate-400); padding: 24px;">No sales returns logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($r['return_no']) ?></code></td>
                                <td><?= htmlspecialchars($r['return_date']) ?></td>
                                <td><strong><?= htmlspecialchars($r['customer_name']) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['product_name'] ?? 'Multiple Items') ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">Batch: <?= htmlspecialchars($r['batch_number'] ?? '-') ?></div>
                                </td>
                                <td><span class="badge badge-warning"><?= $r['quantity'] ?> units</span></td>
                                <td><strong style="color: #10b981;">Rs. <?= number_format($r['total_amount']) ?></strong></td>
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

<!-- Modal: Process Sales Return -->
<div class="modal-overlay" id="addReturnModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Issue Sales Return &amp; Credit Note</h3>
            <button class="modal-close" onclick="closeModal('addReturnModal')">&times;</button>
        </div>
        <form method="POST" action="sales_returns.php">
            <input type="hidden" name="action" value="create_return">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Returning Customer / Pharmacy *</label>
                    <select name="customer_id" class="form-select" required>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Batch Being Returned *</label>
                    <select name="batch_id" class="form-select" required>
                        <?php foreach ($allBatches as $ab): ?>
                            <option value="<?= $ab['id'] ?>">
                                <?= htmlspecialchars($ab['product_name']) ?> &mdash; Batch: <?= htmlspecialchars($ab['batch_number']) ?> (TP: Rs. <?= number_format($ab['trade_price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantity Returned (Packs) *</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="5" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Receiving Warehouse Bay *</label>
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
                        <option value="near_expiry">Near Expiry Return (Shelf-life clearance)</option>
                        <option value="expired">Expired Stock</option>
                        <option value="damaged">Damaged in Handling / Leaked</option>
                        <option value="wrong_product">Wrong Formulation Dispatched</option>
                        <option value="customer_rejection">Customer / Doctor Prescription Rejection</option>
                        <option value="quality_issue">DRAP Quality Recall</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes / Physical Inspection Observations</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Returned with intact seals. Inspected by QA Officer."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addReturnModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Return &amp; Credit Ledger</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
