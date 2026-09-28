<?php
// AA TRADERS - Inventory Management & Stock Valuation
$pageTitle = 'Inventory & Valuation';
require_once __DIR__ . '/includes/header.php';

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $batch_id = (int)$_POST['batch_id'];
    $adj_type = $_POST['adjustment_type']; // add or deduct
    $quantity = (int)$_POST['quantity'];
    $reason = trim($_POST['reason']);
    $notes = trim($_POST['notes'] ?? '');

    $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batch_id}")->fetch();
    if ($batch) {
        $newQty = $adj_type === 'add' ? ($batch['quantity_available'] + $quantity) : max(0, $batch['quantity_available'] - $quantity);
        $diff = $adj_type === 'add' ? +$quantity : -$quantity;

        $db->beginTransaction();
        // Update batch available qty
        $stmt = $db->prepare("UPDATE product_batches SET quantity_available = ? WHERE id = ?");
        $stmt->execute([$newQty, $batch_id]);

        // Insert inventory transaction
        $transType = $adj_type === 'add' ? 'adjustment_add' : 'adjustment_sub';
        $stmt = $db->prepare("INSERT INTO inventory_transactions (
            product_id, batch_id, warehouse_id, transaction_type, reference_type, 
            reference_id, quantity, unit_cost, notes, created_by
        ) VALUES (?, ?, ?, ?, 'manual_adjustment', ?, ?, ?, ?, ?)");
        $adjRef = 'ADJ-' . strtoupper(substr(uniqid(), -6));
        $stmt->execute([
            $batch['product_id'], $batch_id, $batch['warehouse_id'], $transType,
            $adjRef, $diff, $batch['purchase_price'], "Reason: {$reason}. {$notes}", $currentUser['id']
        ]);

        $db->commit();
        Database::logAudit('STOCK_ADJUSTMENT', 'Inventory', (string)$batch_id, "Adjusted batch {$batch['batch_number']} by {$diff} units. Reason: {$reason}");
        header('Location: inventory.php?msg=adjusted');
        exit;
    }
}

// Aggregated Inventory Valuation
$totals = $db->query("
    SELECT 
        SUM(quantity_available) as total_available_units,
        SUM(quantity_reserved) as total_reserved_units,
        SUM(quantity_damaged) as total_damaged_units,
        SUM(quantity_expired) as total_expired_units,
        SUM(quantity_available * purchase_price) as total_purchase_valuation,
        SUM(quantity_available * trade_price) as total_trade_valuation,
        SUM(quantity_available * mrp_retail_price) as total_mrp_valuation
    FROM product_batches
    WHERE status = 'active'
")->fetch();

// Stock by Formulation
$stockRows = $db->query("
    SELECT p.id, p.code, p.name, p.generic_name, p.dosage_form, p.strength, p.unit,
           m.name as manufacturer_name, c.name as category_name,
           IFNULL(SUM(b.quantity_available), 0) as available_qty,
           IFNULL(SUM(b.quantity_reserved), 0) as reserved_qty,
           IFNULL(SUM(b.quantity_damaged), 0) as damaged_qty,
           IFNULL(SUM(b.quantity_expired), 0) as expired_qty,
           ROUND(SUM(b.quantity_available * b.purchase_price), 2) as purchase_val,
           ROUND(SUM(b.quantity_available * b.trade_price), 2) as trade_val,
           p.reorder_level
    FROM products p
    LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    GROUP BY p.id
    ORDER BY trade_val DESC
")->fetchAll();

// Active batches for adjustment dropdown
$allBatches = $db->query("
    SELECT b.id, b.batch_number, b.quantity_available, b.expiry_date, p.name as product_name, w.name as warehouse_name
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    WHERE b.status = 'active'
    ORDER BY p.name ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Inventory Management &amp; Stock Valuation</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Live stock balance: Saleable, Reserved, Damaged, Expired &amp; Valuation Ledgers</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="invSearch" onkeyup="filterTable('invSearch', 'invTable')" placeholder="🔍 Search product or code..." class="form-control" style="width: 250px;">
        <button onclick="openModal('adjustModal')" class="btn btn-secondary">⚡ Stock Adjustment</button>
    </div>
</div>

<!-- Stock Valuation Overview KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Trade Valuation (TP)</h3>
            <div class="kpi-value">Rs. <?= number_format($totals['total_trade_valuation'] ?? 0) ?></div>
            <div class="kpi-sub">Commercial distributor valuation</div>
        </div>
        <div class="kpi-icon green">📦</div>
    </div>
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Purchase Cost Valuation</h3>
            <div class="kpi-value">Rs. <?= number_format($totals['total_purchase_valuation'] ?? 0) ?></div>
            <div class="kpi-sub">Estimated cost of goods</div>
        </div>
        <div class="kpi-icon blue">💰</div>
    </div>
    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Saleable Units</h3>
            <div class="kpi-value"><?= number_format($totals['total_available_units'] ?? 0) ?> Units</div>
            <div class="kpi-sub">Available for immediate picking</div>
        </div>
        <div class="kpi-icon teal">✅</div>
    </div>
    <div class="kpi-card kpi-danger">
        <div class="kpi-info">
            <h3>Damaged &amp; Expired</h3>
            <div class="kpi-value"><?= number_format(($totals['total_damaged_units'] ?? 0) + ($totals['total_expired_units'] ?? 0)) ?> Units</div>
            <div class="kpi-sub">Isolated in quarantine/disposal</div>
        </div>
        <div class="kpi-icon red">🛑</div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="invTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product &amp; Category</th>
                        <th>Manufacturer</th>
                        <th>Saleable Stock</th>
                        <th>Reserved</th>
                        <th>Damaged</th>
                        <th>Cost Valuation</th>
                        <th>Trade Value (TP)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockRows as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($row['category_name']) ?> &bull; <?= htmlspecialchars($row['strength']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['manufacturer_name']) ?></td>
                            <td>
                                <strong style="font-size: 14px; color: var(--primary-dark);"><?= number_format($row['available_qty']) ?></strong> <?= $row['unit'] ?>s
                            </td>
                            <td><?= $row['reserved_qty'] ?></td>
                            <td>
                                <?php if ($row['damaged_qty'] > 0 || $row['expired_qty'] > 0): ?>
                                    <span class="badge badge-danger"><?= $row['damaged_qty'] + $row['expired_qty'] ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">0</span>
                                <?php endif; ?>
                            </td>
                            <td>Rs. <?= number_format($row['purchase_val'] ?? 0) ?></td>
                            <td><strong>Rs. <?= number_format($row['trade_val'] ?? 0) ?></strong></td>
                            <td>
                                <?php if ($row['available_qty'] <= $row['reorder_level']): ?>
                                    <span class="badge badge-danger">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Optimal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="batches.php?product_id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm">FEFO Batches</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Stock Adjustment -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Manual Inventory Adjustment</h3>
            <button class="modal-close" onclick="closeModal('adjustModal')">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="adjust_stock">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Batch to Adjust *</label>
                    <select name="batch_id" class="form-select" required>
                        <?php foreach ($allBatches as $ab): ?>
                            <option value="<?= $ab['id'] ?>">
                                <?= htmlspecialchars($ab['product_name']) ?> &mdash; Batch: <?= htmlspecialchars($ab['batch_number']) ?> (Avail: <?= $ab['quantity_available'] ?>, Wh: <?= htmlspecialchars($ab['warehouse_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Adjustment Direction *</label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="deduct">Deduct Quantity (Shortage / Damage / Sampling)</option>
                            <option value="add">Add Quantity (Found / Excess / Recount)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="10" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason *</label>
                    <select name="reason" class="form-select" required>
                        <option value="Physical Count Difference">Physical Count Difference</option>
                        <option value="Damaged in Handling">Damaged in Handling</option>
                        <option value="Transit Breakage">Transit Breakage</option>
                        <option value="Doctor Sampling / Promotion">Doctor Sampling / Promotion</option>
                        <option value="Shortage during Receipt">Shortage during Receipt</option>
                        <option value="Auditor Adjustment">Auditor Adjustment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Detailed Notes / Authorization Reference</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Approved by Warehouse Manager Rashid Khan after physical audit."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Stock Adjustment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
