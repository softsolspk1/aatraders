<?php
// AA TRADERS - Multiple Warehouses & Stock Transfer Workflow
$pageTitle = 'Warehouses & Stock Transfers';
require_once __DIR__ . '/includes/header.php';

// Handle Add Warehouse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_warehouse') {
    $branch_id = (int)$_POST['branch_id'];
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $type = $_POST['type'];
    $location = trim($_POST['location'] ?? '');
    $manager = trim($_POST['manager_name'] ?? '');

    $stmt = $db->prepare("INSERT INTO warehouses (branch_id, name, code, type, location, manager_name) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$branch_id, $name, $code, $type, $location, $manager]);
    header('Location: warehouses.php?msg=wh_created');
    exit;
}

// Handle Transfer Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_transfer') {
    $from_wh = (int)$_POST['from_warehouse_id'];
    $to_wh = (int)$_POST['to_warehouse_id'];
    $batch_id = (int)$_POST['batch_id'];
    $quantity = (int)$_POST['quantity'];
    $notes = trim($_POST['notes'] ?? '');

    if ($from_wh === $to_wh) {
        $error = "Origin and destination warehouses cannot be identical.";
    } else {
        $batch = $db->query("SELECT * FROM product_batches WHERE id = {$batch_id}")->fetch();
        if ($batch && $batch['quantity_available'] >= $quantity) {
            $db->beginTransaction();
            $transferNo = 'TRF-' . date('Ymd') . '-' . rand(100, 999);
            $stmt = $db->prepare("INSERT INTO stock_transfers (transfer_no, transfer_date, from_warehouse_id, to_warehouse_id, status, notes, requested_by) VALUES (?, date('now'), ?, ?, 'dispatched', ?, ?)");
            $stmt->execute([$transferNo, $from_wh, $to_wh, $notes, $currentUser['id']]);
            $transferId = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, batch_id, quantity) VALUES (?, ?, ?, ?)");
            $stmt->execute([$transferId, $batch['product_id'], $batch_id, $quantity]);

            // Deduct from origin batch
            $db->query("UPDATE product_batches SET quantity_available = quantity_available - {$quantity} WHERE id = {$batch_id}");

            // Check if batch already exists in destination warehouse, otherwise create it
            $stmt = $db->prepare("SELECT id FROM product_batches WHERE product_id = ? AND warehouse_id = ? AND batch_number = ?");
            $stmt->execute([$batch['product_id'], $to_wh, $batch['batch_number']]);
            $destBatchId = $stmt->fetchColumn();

            if ($destBatchId) {
                $db->query("UPDATE product_batches SET quantity_available = quantity_available + {$quantity} WHERE id = {$destBatchId}");
            } else {
                $stmt = $db->prepare("INSERT INTO product_batches (
                    product_id, warehouse_id, batch_number, mfg_date, expiry_date, 
                    purchase_price, trade_price, mrp_retail_price, quantity_received, quantity_available, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $batch['product_id'], $to_wh, $batch['batch_number'], $batch['mfg_date'], $batch['expiry_date'],
                    $batch['purchase_price'], $batch['trade_price'], $batch['mrp_retail_price'], $quantity, $quantity
                ]);
            }

            $db->commit();
            Database::logAudit('STOCK_TRANSFER', 'Warehouses', $transferNo, "Transferred {$quantity} units of batch {$batch['batch_number']} from WH-{$from_wh} to WH-{$to_wh}");
            header('Location: warehouses.php?msg=transfer_completed');
            exit;
        } else {
            $error = "Insufficient stock in source batch for transfer.";
        }
    }
}

// Fetch Warehouses with stock metrics
$warehouses = $db->query("
    SELECT w.*, b.name as branch_name,
           COUNT(DISTINCT pb.id) as batch_count,
           IFNULL(SUM(pb.quantity_available), 0) as total_units,
           IFNULL(SUM(pb.quantity_available * pb.trade_price), 0) as total_valuation
    FROM warehouses w
    LEFT JOIN branches b ON w.branch_id = b.id
    LEFT JOIN product_batches pb ON w.id = pb.warehouse_id AND pb.status = 'active'
    GROUP BY w.id
    ORDER BY w.id ASC
")->fetchAll();

// Fetch Transfers History
$transfers = $db->query("
    SELECT t.*, fw.name as from_wh_name, tw.name as to_wh_name, u.full_name as requester_name,
           ti.quantity, p.name as product_name, pb.batch_number
    FROM stock_transfers t
    JOIN warehouses fw ON t.from_warehouse_id = fw.id
    JOIN warehouses tw ON t.to_warehouse_id = tw.id
    LEFT JOIN users u ON t.requested_by = u.id
    LEFT JOIN stock_transfer_items ti ON t.id = ti.transfer_id
    LEFT JOIN products p ON ti.product_id = p.id
    LEFT JOIN product_batches pb ON ti.batch_id = pb.id
    ORDER BY t.id DESC LIMIT 15
")->fetchAll();

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$allBatches = $db->query("
    SELECT b.id, b.batch_number, b.quantity_available, b.warehouse_id, p.name as product_name, w.name as warehouse_name
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    JOIN warehouses w ON b.warehouse_id = w.id
    WHERE b.status = 'active' AND b.quantity_available > 0
    ORDER BY p.name ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Warehouses &amp; Inter-Facility Stock Transfers</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Cold Chain, Quarantine, Central Depot &amp; Multi-Branch Logistic Routing</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="openModal('addWhModal')" class="btn btn-secondary">+ Add Warehouse</button>
        <button onclick="openModal('transferModal')" class="btn btn-primary">⚡ Dispatch Inter-Warehouse Transfer</button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Warehouse Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <?php foreach ($warehouses as $wh): ?>
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="background: var(--slate-50);">
                <div>
                    <strong style="font-size: 14px; color: var(--slate-900);"><?= htmlspecialchars($wh['name']) ?></strong>
                    <div style="font-size: 11px; color: var(--slate-500);"><code><?= htmlspecialchars($wh['code']) ?></code> &bull; <?= htmlspecialchars($wh['branch_name'] ?? 'HQ') ?></div>
                </div>
                <span class="badge <?= $wh['type'] === 'cold_storage' ? 'badge-info' : ($wh['type'] === 'damaged' || $wh['type'] === 'expired' ? 'badge-danger' : 'badge-primary') ?>">
                    <?= strtoupper(str_replace('_', ' ', $wh['type'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div style="font-size: 12.5px; margin-bottom: 6px; color: var(--slate-700);">
                    📍 <strong>Location:</strong> <?= htmlspecialchars($wh['location'] ?? 'Not set') ?>
                </div>
                <div style="font-size: 12.5px; margin-bottom: 12px; color: var(--slate-700);">
                    👤 <strong>Manager:</strong> <?= htmlspecialchars($wh['manager_name'] ?? 'In-charge') ?>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 10px; font-size: 12.5px;">
                    <div>
                        <div style="color: var(--slate-500); font-size: 11px;">Active Units</div>
                        <strong><?= number_format($wh['total_units']) ?> Units</strong>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: var(--slate-500); font-size: 11px;">Stock Value (TP)</div>
                        <strong style="color: var(--primary);">Rs. <?= number_format($wh['total_valuation']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Stock Transfers Workflow Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">🔄 Inter-Warehouse Transfer Log &amp; Custody Audit</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>Date</th>
                        <th>Origin Facility</th>
                        <th>Destination Facility</th>
                        <th>Product &amp; Batch</th>
                        <th>Transferred Qty</th>
                        <th>Status</th>
                        <th>Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--slate-400); padding: 20px;">No inter-warehouse transfers recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($t['transfer_no']) ?></code></td>
                                <td><?= htmlspecialchars($t['transfer_date']) ?></td>
                                <td><?= htmlspecialchars($t['from_wh_name']) ?></td>
                                <td><strong><?= htmlspecialchars($t['to_wh_name']) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($t['product_name'] ?? 'Multiple Items') ?></strong>
                                    <?php if ($t['batch_number']): ?>
                                        <div style="font-size: 11px; color: #64748b;">Batch: <?= htmlspecialchars($t['batch_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-primary"><?= $t['quantity'] ?> units</span></td>
                                <td><span class="badge badge-success"><?= strtoupper($t['status']) ?></span></td>
                                <td><?= htmlspecialchars($t['requester_name'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Stock Transfer -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Initiate Inter-Warehouse Transfer</h3>
            <button class="modal-close" onclick="closeModal('transferModal')">&times;</button>
        </div>
        <form method="POST" action="warehouses.php">
            <input type="hidden" name="action" value="create_transfer">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Batch to Transfer *</label>
                    <select name="batch_id" id="transferBatchSelect" class="form-select" onchange="updateOriginWarehouse()" required>
                        <?php foreach ($allBatches as $ab): ?>
                            <option value="<?= $ab['id'] ?>" data-wh-id="<?= $ab['warehouse_id'] ?>" data-max="<?= $ab['quantity_available'] ?>">
                                <?= htmlspecialchars($ab['product_name']) ?> &mdash; Batch: <?= htmlspecialchars($ab['batch_number']) ?> (<?= $ab['quantity_available'] ?> avail in <?= htmlspecialchars($ab['warehouse_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Origin Facility *</label>
                        <select name="from_warehouse_id" id="fromWhSelect" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destination Facility *</label>
                        <select name="to_warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Transfer Quantity (Units) *</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="20" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Dispatch Notes &amp; Cold Chain Temperature Requirements</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Dispatched via refrigerated delivery van with temperature data logger."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('transferModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Authorize &amp; Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Warehouse -->
<div class="modal-overlay" id="addWhModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Add New Storage Facility</h3>
            <button class="modal-close" onclick="closeModal('addWhModal')">&times;</button>
        </div>
        <form method="POST" action="warehouses.php">
            <input type="hidden" name="action" value="add_warehouse">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Facility Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Islamabad Depot" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Warehouse Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. WH-ISB-01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Branch *</label>
                        <select name="branch_id" class="form-select" required>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Storage Classification *</label>
                        <select name="type" class="form-select" required>
                            <option value="main">Main Warehouse</option>
                            <option value="secondary">Secondary Distribution Depot</option>
                            <option value="cold_storage">Cold Chain &amp; Biologics (2-8°C)</option>
                            <option value="quarantine">Quarantine Inspection Bay</option>
                            <option value="damaged">Damaged Stock Segregation</option>
                            <option value="expired">Expired Destruction Bay</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Location Address</label>
                        <input type="text" name="location" class="form-control" placeholder="Street / Industrial Zone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Warehouse Manager</label>
                        <input type="text" name="manager_name" class="form-control" placeholder="Full Name">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addWhModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Facility</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateOriginWarehouse() {
    const sel = document.getElementById('transferBatchSelect');
    const opt = sel.options[sel.selectedIndex];
    const whId = opt.getAttribute('data-wh-id');
    if (whId) {
        document.getElementById('fromWhSelect').value = whId;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
