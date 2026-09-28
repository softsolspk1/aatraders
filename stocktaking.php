<?php
// AA TRADERS - Physical Stocktaking & Audited Inventory Reconciliation
$pageTitle = 'Physical Stocktaking';
require_once __DIR__ . '/includes/header.php';

// Handle Stocktaking Audit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_audit') {
    $batch_id = (int)$_POST['batch_id'];
    $physical_qty = (int)$_POST['physical_quantity'];
    $notes = trim($_POST['notes'] ?? '');

    $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batch_id}")->fetch();
    if ($batch) {
        $system_qty = $batch['quantity_available'];
        $diff = $physical_qty - $system_qty;

        $db->beginTransaction();
        $adjNo = 'STK-' . date('Ymd') . '-' . rand(100, 999);
        $reason = $diff >= 0 ? 'excess' : 'shortage';

        $stmtAdj = $db->prepare("INSERT INTO stock_adjustments (adjustment_no, adjustment_date, warehouse_id, reason, notes, adjusted_by) 
                                 VALUES (?, date('now'), ?, ?, ?, ?)");
        $stmtAdj->execute([$adjNo, $batch['warehouse_id'], 'physical_count_difference', "Physical count discrepancy ({$diff} units). {$notes}", $currentUser['id']]);
        $adjId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO stock_adjustment_items (adjustment_id, product_id, batch_id, system_quantity, physical_quantity, difference_quantity, unit_cost) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtItem->execute([$adjId, $batch['product_id'], $batch_id, $system_qty, $physical_qty, $diff, $batch['purchase_price']]);

        // Reconcile batch to physical count
        $db->query("UPDATE product_batches SET quantity_available = {$physical_qty} WHERE id = {$batch_id}");

        // Record Inventory Movement
        $transType = $diff >= 0 ? 'adjustment_add' : 'adjustment_sub';
        $stmtMov = $db->prepare("INSERT INTO inventory_transactions (product_id, batch_id, warehouse_id, transaction_type, reference_type, reference_id, quantity, unit_cost, notes, created_by) 
                                 VALUES (?, ?, ?, ?, 'stocktaking', ?, ?, ?, ?, ?)");
        $stmtMov->execute([$batch['product_id'], $batch_id, $batch['warehouse_id'], $transType, $adjNo, $diff, $batch['purchase_price'], "Physical stocktaking reconciliation: Sys={$system_qty}, Phys={$physical_qty}", $currentUser['id']]);

        $db->commit();
        Database::logAudit('STOCKTAKING', 'Auditing', $adjNo, "Stocktaking reconciliation on batch {$batch['batch_number']}. Difference: {$diff} units.");
        header('Location: stocktaking.php?msg=reconciled');
        exit;
    }
}

// Fetch Active Batches for physical audit
$warehouseFilter = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : 1;
$batches = $db->query("
    SELECT b.*, p.name as product_name, p.code as product_code, p.dosage_form, p.strength,
           w.name as warehouse_name
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    WHERE b.warehouse_id = {$warehouseFilter} AND b.status = 'active'
    ORDER BY p.name ASC
")->fetchAll();

$warehouses = $db->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Recent Stocktaking Adjustments
$recentAdjustments = $db->query("
    SELECT a.*, ai.system_quantity, ai.physical_quantity, ai.difference_quantity,
           p.name as product_name, pb.batch_number, u.full_name as auditor_name
    FROM stock_adjustments a
    JOIN stock_adjustment_items ai ON a.id = ai.adjustment_id
    JOIN products p ON ai.product_id = p.id
    JOIN product_batches pb ON ai.batch_id = pb.id
    LEFT JOIN users u ON a.adjusted_by = u.id
    ORDER BY a.id DESC LIMIT 10
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Physical Stocktaking &amp; Shelf Auditing</h2>
        <p style="font-size: 13px; color: var(--slate-500);">System Stock vs Physical Shelf Count Discrepancy &bull; Instant Variance Adjustments</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <label style="font-weight: 600; font-size: 13px;">Facility:</label>
        <select onchange="window.location.href='stocktaking.php?warehouse_id=' + this.value" class="form-select" style="width: 250px;">
            <?php foreach ($warehouses as $wh): ?>
                <option value="<?= $wh['id'] ?>" <?= $wh['id'] === $warehouseFilter ? 'selected' : '' ?>>
                    <?= htmlspecialchars($wh['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Physical count discrepancy reconciled! Batch stock has been matched to actual physical shelf count.
    </div>
<?php endif; ?>

<!-- Physical Stock Audit Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">📋 Floor Stock Verification Sheet</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch #</th>
                        <th>Product Details</th>
                        <th>Expiry Date</th>
                        <th>System Stock</th>
                        <th>Count Physical Shelf Stock</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $b): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($b['batch_number']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($b['product_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($b['dosage_form']) ?> &bull; <?= htmlspecialchars($b['strength']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($b['expiry_date']) ?></td>
                            <td>
                                <strong style="font-size: 14px; color: var(--primary-dark);"><?= $b['quantity_available'] ?></strong> units
                            </td>
                            <td>
                                <form method="POST" action="stocktaking.php" style="display: flex; gap: 8px; align-items: center;">
                                    <input type="hidden" name="action" value="submit_audit">
                                    <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                    <input type="number" name="physical_quantity" class="form-control form-control-sm" style="width: 100px;" value="<?= $b['quantity_available'] ?>" min="0" required>
                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Confirm physical count reconciliation for batch <?= htmlspecialchars($b['batch_number']) ?>?')">
                                        Reconcile Count
                                    </button>
                                </form>
                            </td>
                            <td>
                                <span style="font-size: 11px; color: #10b981;">Ready for Audit</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Stocktaking Variance Log -->
<div class="card">
    <div class="card-header">
        <span class="card-title">🔍 Audited Discrepancy &amp; Variance History</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Audit Voucher</th>
                        <th>Date</th>
                        <th>Product &amp; Batch</th>
                        <th>System Qty</th>
                        <th>Physical Qty</th>
                        <th>Variance (Diff)</th>
                        <th>Auditor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAdjustments)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 20px;">No variance reconciliations logged. Zero discrepancies!</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentAdjustments as $ra): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($ra['adjustment_no']) ?></code></td>
                                <td><?= htmlspecialchars($ra['adjustment_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($ra['product_name']) ?></strong>
                                    <div>Batch: <code><?= htmlspecialchars($ra['batch_number']) ?></code></div>
                                </td>
                                <td><?= $ra['system_quantity'] ?></td>
                                <td><strong><?= $ra['physical_quantity'] ?></strong></td>
                                <td>
                                    <?php if ($ra['difference_quantity'] < 0): ?>
                                        <span class="badge badge-danger"><?= $ra['difference_quantity'] ?> Shortage</span>
                                    <?php elseif ($ra['difference_quantity'] > 0): ?>
                                        <span class="badge badge-success">+<?= $ra['difference_quantity'] ?> Excess</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">0 Exact</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ra['auditor_name'] ?? 'Auditor') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
