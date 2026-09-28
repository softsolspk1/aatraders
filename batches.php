<?php
// AA TRADERS - Product Batch Management & FEFO Engine
$pageTitle = 'Batch Management (FEFO)';
require_once __DIR__ . '/includes/header.php';

// Handle Add Batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_batch') {
    $product_id = (int)$_POST['product_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $batch_number = trim($_POST['batch_number']);
    $mfg_date = $_POST['mfg_date'];
    $expiry_date = $_POST['expiry_date'];
    $purchase_price = (float)$_POST['purchase_price'];
    $trade_price = (float)$_POST['trade_price'];
    $mrp = (float)$_POST['mrp_retail_price'];
    $qty = (int)$_POST['quantity'];
    $barcode = trim($_POST['barcode'] ?? '');

    // Validation: prevent adding already expired batch
    $today = date('Y-m-d');
    if ($expiry_date <= $today) {
        $error = "Regulatory Error: DRAP regulations strictly prohibit receiving or creating an expired batch ({$expiry_date}).";
    } else {
        $stmt = $db->prepare("INSERT INTO product_batches (
            product_id, warehouse_id, batch_number, mfg_date, expiry_date, 
            purchase_price, trade_price, mrp_retail_price, quantity_received, 
            quantity_available, barcode, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $product_id, $warehouse_id, $batch_number, $mfg_date, $expiry_date,
            $purchase_price, $trade_price, $mrp, $qty, $qty, $barcode
        ]);

        Database::logAudit('CREATE', 'Batches', $batch_number, "Added batch {$batch_number} for product ID {$product_id}");
        header('Location: batches.php?msg=batch_created');
        exit;
    }
}

// Filter handling
$filter = $_GET['filter'] ?? 'all';
$selectedProduct = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

$whereClauses = ["1=1"];
$params = [];

if ($selectedProduct) {
    $whereClauses[] = "b.product_id = ?";
    $params[] = $selectedProduct;
}

if ($filter === 'expiring_30') {
    $whereClauses[] = "b.status = 'active' AND b.quantity_available > 0 AND julianday(b.expiry_date) - julianday('now') <= 30 AND julianday(b.expiry_date) - julianday('now') > 0";
} elseif ($filter === 'expiring_90') {
    $whereClauses[] = "b.status = 'active' AND b.quantity_available > 0 AND julianday(b.expiry_date) - julianday('now') <= 90 AND julianday(b.expiry_date) - julianday('now') > 0";
} elseif ($filter === 'expired') {
    $whereClauses[] = "(b.status = 'expired' OR julianday(b.expiry_date) <= julianday('now'))";
} elseif ($filter === 'active') {
    $whereClauses[] = "b.status = 'active' AND b.quantity_available > 0 AND julianday(b.expiry_date) > julianday('now')";
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch batches ordered by FEFO (earliest expiry first)
$sql = "
    SELECT b.*, p.name as product_name, p.code as product_code, p.dosage_form, p.strength,
           w.name as warehouse_name,
           CAST(julianday(b.expiry_date) - julianday('now') AS INTEGER) as days_left
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    WHERE {$whereSql}
    ORDER BY b.expiry_date ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll();

// Fetch products & warehouses for modal
$products = $db->query("SELECT id, name, code FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$warehouses = $db->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Product Batch Management & FEFO Engine</h2>
        <p style="font-size: 13px; color: var(--slate-500);">First Expiry, First Out (FEFO) automated allocation, shelf-life & pricing control</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="batchSearch" onkeyup="filterTable('batchSearch', 'batchesTable')" placeholder="🔍 Search batch #, formulation..." class="form-control" style="width: 260px;">
        <button onclick="openModal('addBatchModal')" class="btn btn-primary">+ Register New Batch</button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Expiry Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <a href="batches.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Batches</a>
    <a href="batches.php?filter=active" class="btn <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Active &amp; Saleable</a>
    <a href="batches.php?filter=expiring_30" class="btn <?= $filter === 'expiring_30' ? 'btn-danger' : 'btn-secondary' ?> btn-sm">🔴 Critical &lt; 30 Days</a>
    <a href="batches.php?filter=expiring_90" class="btn <?= $filter === 'expiring_90' ? 'btn-warning' : 'btn-secondary' ?> btn-sm">🟡 Near Expiry &lt; 90 Days</a>
    <a href="batches.php?filter=expired" class="btn <?= $filter === 'expired' ? 'btn-danger' : 'btn-secondary' ?> btn-sm">🛑 Expired / Quarantine</a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="batchesTable">
                <thead>
                    <tr>
                        <th>Batch Number</th>
                        <th>Product Details</th>
                        <th>Warehouse</th>
                        <th>Mfg Date</th>
                        <th>Expiry Date (FEFO)</th>
                        <th>Shelf Life Status</th>
                        <th>Purchase Rate</th>
                        <th>Trade Price (TP)</th>
                        <th>Retail (MRP)</th>
                        <th>Avail Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="11" style="text-align: center; color: var(--slate-400); padding: 24px;">No batches match the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td>
                                    <code><?= htmlspecialchars($b['batch_number']) ?></code>
                                    <?php if (!empty($b['barcode'])): ?>
                                        <div style="font-size: 10px; color: #64748b;">🏷️ <?= htmlspecialchars($b['barcode']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($b['product_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($b['dosage_form']) ?> &bull; <?= htmlspecialchars($b['strength']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($b['warehouse_name']) ?></td>
                                <td><?= htmlspecialchars($b['mfg_date']) ?></td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($b['expiry_date']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($b['days_left'] <= 0): ?>
                                        <span class="badge badge-expired">EXPIRED (<?= abs($b['days_left']) ?>d ago)</span>
                                    <?php elseif ($b['days_left'] <= 30): ?>
                                        <span class="badge badge-near-30">⚠️ <?= $b['days_left'] ?> DAYS LEFT</span>
                                    <?php elseif ($b['days_left'] <= 90): ?>
                                        <span class="badge badge-near-90"><?= $b['days_left'] ?> days left</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= $b['days_left'] ?> days left</span>
                                    <?php endif; ?>
                                </td>
                                <td>Rs. <?= number_format($b['purchase_price'], 2) ?></td>
                                <td><strong>Rs. <?= number_format($b['trade_price'], 2) ?></strong></td>
                                <td>Rs. <?= number_format($b['mrp_retail_price'], 2) ?></td>
                                <td>
                                    <?php if ($b['quantity_available'] > 0): ?>
                                        <strong style="color: var(--primary-dark); font-size: 14px;"><?= $b['quantity_available'] ?></strong>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $b['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= strtoupper($b['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Batch -->
<div class="modal-overlay" id="addBatchModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Register New Product Batch</h3>
            <button class="modal-close" onclick="closeModal('addBatchModal')">&times;</button>
        </div>
        <form method="POST" action="batches.php">
            <input type="hidden" name="action" value="add_batch">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Product *</label>
                    <select name="product_id" class="form-select" required>
                        <?php foreach ($products as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Batch Number *</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. BT-9021" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Warehouse Storage *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Manufacturing Date (Mfg) *</label>
                        <input type="date" name="mfg_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date (Exp) *</label>
                        <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Purchase Rate (Cost) *</label>
                        <input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trade Price (TP) *</label>
                        <input type="number" step="0.01" name="trade_price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Maximum Retail Price (MRP) *</label>
                        <input type="number" step="0.01" name="mrp_retail_price" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Initial Quantity Received *</label>
                        <input type="number" name="quantity" class="form-control" value="100" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Barcode</label>
                        <input type="text" name="barcode" class="form-control" placeholder="e.g. BAR-BT-9021">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBatchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Batch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
