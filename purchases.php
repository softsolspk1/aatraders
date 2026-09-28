<?php
// AA TRADERS - Purchase Orders & Goods Receipt (GRN) Engine
$pageTitle = 'Purchase Orders & GRN';
require_once __DIR__ . '/includes/header.php';

// Handle Add Purchase Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_po') {
    $supplier_id = (int)$_POST['supplier_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $items = $_POST['items'] ?? [];
    $notes = trim($_POST['notes'] ?? '');

    if (empty($items)) {
        $error = "Please include at least one product in the purchase order.";
    } else {
        $db->beginTransaction();
        $poNumber = 'PO-' . date('Ymd') . '-' . rand(100, 999);
        $totalAmount = 0.0;

        $stmtPo = $db->prepare("INSERT INTO purchase_orders (po_number, po_date, supplier_id, warehouse_id, status, notes, created_by) VALUES (?, date('now'), ?, ?, 'pending', ?, ?)");
        $stmtPo->execute([$poNumber, $supplier_id, $warehouse_id, $notes, $currentUser['id']]);
        $poId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_rate, total_amount) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $rate = (float)$item['unit_rate'];
            if ($productId > 0 && $qty > 0 && $rate > 0) {
                $lineTotal = $qty * $rate;
                $stmtItem->execute([$poId, $productId, $qty, $rate, $lineTotal]);
                $totalAmount += $lineTotal;
            }
        }

        $db->query("UPDATE purchase_orders SET total_amount = {$totalAmount}, net_amount = {$totalAmount} WHERE id = {$poId}");
        $db->commit();

        Database::logAudit('CREATE', 'PurchaseOrders', $poNumber, "Created PO {$poNumber} for Supplier #{$supplier_id} - Rs. {$totalAmount}");
        header('Location: purchases.php?msg=po_created');
        exit;
    }
}

// Handle Goods Receipt Note (GRN) Inward Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_grn') {
    $po_id = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : null;
    $supplier_id = (int)$_POST['supplier_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $supplier_invoice_no = trim($_POST['supplier_invoice_no'] ?? '');
    $product_id = (int)$_POST['product_id'];
    $batch_number = trim($_POST['batch_number']);
    $mfg_date = $_POST['mfg_date'];
    $expiry_date = $_POST['expiry_date'];
    $quantity = (int)$_POST['quantity'];
    $free_quantity = (int)($_POST['free_quantity'] ?? 0);
    $purchase_rate = (float)$_POST['purchase_rate'];
    $trade_price = (float)$_POST['trade_price'];
    $mrp = (float)$_POST['mrp'];

    // CRITICAL REGULATORY CHECK: Prevent receiving expired batch
    $today = date('Y-m-d');
    if ($expiry_date <= $today) {
        $error = "Regulatory Violation (DRAP): Expiry date ({$expiry_date}) has already lapsed. Inward receiving of expired batches is prohibited.";
    } elseif ($quantity <= 0 || $purchase_rate <= 0) {
        $error = "Quantity and purchase rate must be greater than zero.";
    } else {
        $db->beginTransaction();
        $grnNumber = 'GRN-' . date('Ymd') . '-' . rand(100, 999);
        $totalAmount = $quantity * $purchase_rate;

        $stmtGrn = $db->prepare("INSERT INTO goods_receipts (grn_number, po_id, supplier_id, supplier_invoice_no, receipt_date, warehouse_id, total_amount, status, received_by) 
                                 VALUES (?, ?, ?, ?, date('now'), ?, ?, 'completed', ?)");
        $stmtGrn->execute([$grnNumber, $po_id, $supplier_id, $supplier_invoice_no, $warehouse_id, $totalAmount, $currentUser['id']]);
        $grnId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO goods_receipt_items (grn_id, product_id, batch_number, mfg_date, expiry_date, quantity_received, free_quantity, purchase_rate, trade_price, mrp, total_amount) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtItem->execute([$grnId, $product_id, $batch_number, $mfg_date, $expiry_date, $quantity, $free_quantity, $purchase_rate, $trade_price, $mrp, $totalAmount]);

        // Insert / Update Product Batch
        $totalStockIn = $quantity + $free_quantity;
        $stmtBatch = $db->prepare("INSERT INTO product_batches (
            product_id, warehouse_id, batch_number, mfg_date, expiry_date, 
            purchase_price, trade_price, mrp_retail_price, quantity_received, quantity_available, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmtBatch->execute([$product_id, $warehouse_id, $batch_number, $mfg_date, $expiry_date, $purchase_rate, $trade_price, $mrp, $totalStockIn, $totalStockIn]);
        $batchId = $db->lastInsertId();

        // Record Inventory Movement
        $stmtMov = $db->prepare("INSERT INTO inventory_transactions (
            product_id, batch_id, warehouse_id, transaction_type, reference_type, reference_id, quantity, unit_cost, notes, created_by
        ) VALUES (?, ?, ?, 'purchase', 'grn', ?, ?, ?, ?, ?)");
        $stmtMov->execute([$product_id, $batchId, $warehouse_id, $grnNumber, $totalStockIn, $purchase_rate, "GRN Inward receiving {$grnNumber} (Inv: {$supplier_invoice_no})", $currentUser['id']]);

        // Update Supplier Ledger & Outstanding Payable
        $sup = $db->query("SELECT current_balance FROM suppliers WHERE id = {$supplier_id}")->fetch();
        $newPayable = ($sup['current_balance'] ?? 0.0) + $totalAmount;
        $db->query("UPDATE suppliers SET current_balance = {$newPayable} WHERE id = {$supplier_id}");

        $stmtLed = $db->prepare("INSERT INTO supplier_ledgers (supplier_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description) 
                                 VALUES (?, date('now'), 'purchase', ?, 0.0, ?, ?, ?)");
        $stmtLed->execute([$supplier_id, $grnNumber, $totalAmount, $newPayable, "Goods Received {$grnNumber} (Invoice #{$supplier_invoice_no})"]);

        // If PO was attached, mark PO as received
        if ($po_id) {
            $db->query("UPDATE purchase_orders SET status = 'received' WHERE id = {$po_id}");
        }

        $db->commit();
        Database::logAudit('CREATE', 'GoodsReceipt', $grnNumber, "Received {$totalStockIn} packs into batch {$batch_number}. Supplier balance updated by Rs. {$totalAmount}");
        header('Location: purchases.php?msg=grn_completed');
        exit;
    }
}

// Fetch GRNs
$grnList = $db->query("
    SELECT g.*, s.name as supplier_name, w.name as warehouse_name, u.full_name as receiver_name,
           gi.quantity_received, gi.free_quantity, gi.batch_number, gi.expiry_date, p.name as product_name
    FROM goods_receipts g
    JOIN suppliers s ON g.supplier_id = s.id
    JOIN warehouses w ON g.warehouse_id = w.id
    LEFT JOIN users u ON g.received_by = u.id
    LEFT JOIN goods_receipt_items gi ON g.id = gi.grn_id
    LEFT JOIN products p ON gi.product_id = p.id
    ORDER BY g.id DESC LIMIT 15
")->fetchAll();

// Fetch POs
$poList = $db->query("
    SELECT po.*, s.name as supplier_name, w.name as warehouse_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN warehouses w ON po.warehouse_id = w.id
    ORDER BY po.id DESC LIMIT 10
")->fetchAll();

$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$products = $db->query("SELECT id, name, code FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$warehouses = $db->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Purchase Orders &amp; Goods Receiving (GRN)</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Procurement Pipeline: Requisition &rarr; PO &rarr; Quality Inspection &rarr; Batch Entry &rarr; Payables</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="openModal('createPoModal')" class="btn btn-secondary">+ Issue Purchase Order</button>
        <button onclick="openModal('receiveGrnModal')" class="btn btn-primary">📥 Inward GRN Receipt (Batch Entry)</button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Transaction recorded successfully! Inventory balances, batches and supplier ledgers have been updated.
    </div>
<?php endif; ?>

<!-- GRN Receiving Log Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">📥 Completed Goods Receipt Notes (GRN Inward Log)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>Receipt Date</th>
                        <th>Supplier</th>
                        <th>Inv / DC #</th>
                        <th>Product &amp; Batch Created</th>
                        <th>Expiry Date</th>
                        <th>Units Inward</th>
                        <th>Total Cost</th>
                        <th>Receiver</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($grnList)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--slate-400); padding: 20px;">No goods receipts logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grnList as $g): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($g['grn_number']) ?></code></td>
                                <td><?= htmlspecialchars($g['receipt_date']) ?></td>
                                <td><strong><?= htmlspecialchars($g['supplier_name']) ?></strong></td>
                                <td><?= htmlspecialchars($g['supplier_invoice_no'] ?? '-') ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($g['product_name'] ?? 'Multiple Items') ?></strong>
                                    <?php if ($g['batch_number']): ?>
                                        <div style="font-size: 11px; color: #64748b;">Batch: <code><?= htmlspecialchars($g['batch_number']) ?></code></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($g['expiry_date'] ?? '-') ?></td>
                                <td>
                                    <strong style="color: #0284c7;"><?= $g['quantity_received'] ?></strong>
                                    <?= $g['free_quantity'] > 0 ? " (+{$g['free_quantity']} free)" : '' ?>
                                </td>
                                <td><strong>Rs. <?= number_format($g['total_amount']) ?></strong></td>
                                <td><?= htmlspecialchars($g['receiver_name'] ?? 'Warehouse') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📑 Issued Purchase Orders (POs)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>PO Date</th>
                        <th>Supplier</th>
                        <th>Receiving Warehouse</th>
                        <th>Total Order Value</th>
                        <th>Status</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($poList)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 20px;">No purchase orders issued.</td></tr>
                    <?php else: ?>
                        <?php foreach ($poList as $po): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($po['po_number']) ?></code></td>
                                <td><?= htmlspecialchars($po['po_date']) ?></td>
                                <td><strong><?= htmlspecialchars($po['supplier_name']) ?></strong></td>
                                <td><?= htmlspecialchars($po['warehouse_name']) ?></td>
                                <td><strong>Rs. <?= number_format($po['net_amount']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $po['status'] === 'received' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= strtoupper($po['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="print_po.php?id=<?= $po['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">🖨️ Print PO</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Inward GRN Receipt (Batch Creation) -->
<div class="modal-overlay" id="receiveGrnModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Goods Receipt Note (GRN) Inward Stock &amp; Batch Entry</h3>
            <button class="modal-close" onclick="closeModal('receiveGrnModal')">&times;</button>
        </div>
        <form method="POST" action="purchases.php">
            <input type="hidden" name="action" value="receive_grn">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Principal Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier Delivery Challan / Invoice #</label>
                        <input type="text" name="supplier_invoice_no" class="form-control" placeholder="e.g. GSK-INV-9901" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Formulation Inward *</label>
                        <select name="product_id" class="form-select" required>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Batch Number (From Packaging) *</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. BATCH-2026-X" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturing Date (Mfg) *</label>
                        <input type="date" name="mfg_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date (DRAP Validated) *</label>
                        <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Inward Quantity (Packs) *</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Free / Bonus Quantity (Free Goods)</label>
                        <input type="number" name="free_quantity" class="form-control" min="0" value="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Purchase Rate (Cost/Pack) *</label>
                        <input type="number" step="0.01" name="purchase_rate" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Distributor Trade Price (TP) *</label>
                        <input type="number" step="0.01" name="trade_price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Retail Price (MRP) *</label>
                        <input type="number" step="0.01" name="mrp" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('receiveGrnModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process GRN &amp; Commit to Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create Purchase Order -->
<div class="modal-overlay" id="createPoModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Issue Purchase Order (PO)</h3>
            <button class="modal-close" onclick="closeModal('createPoModal')">&times;</button>
        </div>
        <form method="POST" action="purchases.php">
            <input type="hidden" name="action" value="create_po">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Receiving Facility *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Formulation *</label>
                        <select name="items[0][product_id]" class="form-select" required>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Order Quantity *</label>
                        <input type="number" name="items[0][quantity]" class="form-control" value="200" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Agreed Rate (PKR) *</label>
                        <input type="number" step="0.01" name="items[0][unit_rate]" class="form-control" value="350.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Procurement Terms &amp; Conditions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Minimum 24 months shelf life remaining required upon receipt."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPoModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Purchase Order</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
