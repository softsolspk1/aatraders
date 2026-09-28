<?php
// AA TRADERS - Product Batch Recall & Traceability Matrix
$pageTitle = 'Product Recall Matrix';
require_once __DIR__ . '/includes/header.php';

// Handle Add Product Recall
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initiate_recall') {
    $product_id = (int)$_POST['product_id'];
    $batch_number = trim($_POST['batch_number']);
    $reason = trim($_POST['reason']);
    $priority = $_POST['priority']; // critical, high, medium
    $notes = trim($_POST['notes'] ?? '');

    $db->beginTransaction();
    $recallNo = 'RCL-' . date('Ymd') . '-' . rand(100, 999);
    $stmt = $db->prepare("INSERT INTO product_recalls (recall_no, recall_date, product_id, batch_number, reason, priority, status, notes, created_by) VALUES (?, date('now'), ?, ?, ?, ?, 'in_progress', ?, ?)");
    $stmt->execute([$recallNo, $product_id, $batch_number, $reason, $priority, $notes, $currentUser['id']]);

    // Freeze all batches with this batch number in warehouses
    $stmtFreeze = $db->prepare("UPDATE product_batches SET status = 'recalled' WHERE product_id = ? AND batch_number = ?");
    $stmtFreeze->execute([$product_id, $batch_number]);

    $db->commit();
    Database::logAudit('PRODUCT_RECALL', 'Recall', $recallNo, "INITIATED RECALL {$recallNo} for batch {$batch_number}. Immediate sales freeze applied.");
    header("Location: recall.php?msg=recall_initiated&recall_batch={$batch_number}");
    exit;
}

// Traceability Lookup for a specific batch
$searchBatch = trim($_GET['batch_search'] ?? '');
$tracedWarehouses = [];
$tracedCustomers = [];
$totalSold = 0;
$totalWarehouseHolding = 0;

if (!empty($searchBatch)) {
    // 1. Warehouse Stock Holding
    $stmtWh = $db->prepare("
        SELECT b.*, w.name as warehouse_name, p.name as product_name
        FROM product_batches b
        JOIN warehouses w ON b.warehouse_id = w.id
        JOIN products p ON b.product_id = p.id
        WHERE b.batch_number = ?
    ");
    $stmtWh->execute([$searchBatch]);
    $tracedWarehouses = $stmtWh->fetchAll();
    $totalWarehouseHolding = array_sum(array_column($tracedWarehouses, 'quantity_available'));

    // 2. Customers / Pharmacies who received this batch
    $stmtCust = $db->prepare("
        SELECT ii.quantity, ii.bonus_quantity, ii.net_total,
               inv.invoice_number, inv.invoice_date,
               c.business_name, c.phone as customer_phone, c.address, c.city,
               p.name as product_name
        FROM sales_invoice_items ii
        JOIN sales_invoices inv ON ii.invoice_id = inv.id
        JOIN customers c ON inv.customer_id = c.id
        JOIN product_batches pb ON ii.batch_id = pb.id
        JOIN products p ON ii.product_id = p.id
        WHERE pb.batch_number = ?
        ORDER BY inv.invoice_date DESC
    ");
    $stmtCust->execute([$searchBatch]);
    $tracedCustomers = $stmtCust->fetchAll();
    $totalSold = array_sum(array_column($tracedCustomers, 'quantity'));
}

// Fetch Active Recalls
$recalls = $db->query("
    SELECT r.*, p.name as product_name, p.code as product_code, u.full_name as initiator_name
    FROM product_recalls r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.created_by = u.id
    ORDER BY r.id DESC
")->fetchAll();

$products = $db->query("SELECT id, name, code FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Product Batch Recall &amp; Traceability Matrix</h2>
        <p style="font-size: 13px; color: var(--slate-500);">DRAP Batch Quarantine &bull; Warehouse Stock Isolation &bull; Downstream Pharmacy Customer Trace</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="openModal('initiateRecallModal')" class="btn btn-danger">🚨 Initiate Regulatory Recall</button>
    </div>
</div>

<!-- Traceability Search Bar -->
<div class="card" style="margin-bottom: 24px; border: 2px solid #38bdf8;">
    <div class="card-body" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe);">
        <form method="GET" action="recall.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <strong style="color: #0369a1; font-size: 14px;">🔍 Immediate Batch Traceability Lookup:</strong>
            <input type="text" name="batch_search" class="form-control" placeholder="Enter Batch # (e.g. PAN-2601, AUG-9081)" value="<?= htmlspecialchars($searchBatch) ?>" style="width: 320px;" required>
            <button type="submit" class="btn btn-primary">Trace Batch Distribution</button>
            <?php if (!empty($searchBatch)): ?>
                <a href="recall.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($searchBatch)): ?>
    <!-- Traceability Results -->
    <div style="background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--border-color); padding: 20px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            <div>
                <h3 style="font-size: 16px; font-weight: 700; color: #dc2626;">Batch Trace Results: <code><?= htmlspecialchars($searchBatch) ?></code></h3>
                <p style="font-size: 12px; color: var(--slate-500);">Quantities in distribution custody vs supplied to client pharmacies</p>
            </div>
            <div style="display: flex; gap: 20px; font-size: 13px;">
                <div>Warehouse Remaining: <strong style="color: #0284c7; font-size: 15px;"><?= $totalWarehouseHolding ?> Units</strong></div>
                <div>Dispatched to Clients: <strong style="color: #dc2626; font-size: 15px;"><?= $totalSold ?> Units</strong></div>
            </div>
        </div>

        <!-- 1. Warehouse Holdings -->
        <h4 style="font-size: 13px; text-transform: uppercase; color: var(--slate-600); margin-bottom: 8px;">1. Warehouse Facility Holdings</h4>
        <div class="table-responsive" style="margin-bottom: 20px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Storage Facility</th>
                        <th>Product</th>
                        <th>Mfg Date</th>
                        <th>Expiry Date</th>
                        <th>Available Stock</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tracedWarehouses)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--slate-400); padding: 12px;">No active warehouse holdings found for this batch.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tracedWarehouses as $tw): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tw['warehouse_name']) ?></strong></td>
                                <td><?= htmlspecialchars($tw['product_name']) ?></td>
                                <td><?= htmlspecialchars($tw['mfg_date']) ?></td>
                                <td><?= htmlspecialchars($tw['expiry_date']) ?></td>
                                <td><strong style="color: #0284c7; font-size: 14px;"><?= $tw['quantity_available'] ?> units</strong></td>
                                <td><span class="badge <?= $tw['status'] === 'recalled' ? 'badge-danger' : 'badge-success' ?>"><?= strtoupper($tw['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Downstream Customer Invoices -->
        <h4 style="font-size: 13px; text-transform: uppercase; color: var(--slate-600); margin-bottom: 8px;">2. Downstream Pharmacies &amp; Hospitals Supplied (Mandatory Recall Notice List)</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Supply Date</th>
                        <th>Pharmacy / Client</th>
                        <th>Phone / Contact</th>
                        <th>Premises Address</th>
                        <th>Packs Sold</th>
                        <th>Notice Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tracedCustomers)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 12px;">This batch has not yet been invoiced to any customer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tracedCustomers as $tc): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($tc['invoice_number']) ?></code></td>
                                <td><?= htmlspecialchars($tc['invoice_date']) ?></td>
                                <td><strong><?= htmlspecialchars($tc['business_name']) ?></strong></td>
                                <td>📞 <?= htmlspecialchars($tc['customer_phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($tc['address']) ?>, <?= htmlspecialchars($tc['city']) ?></td>
                                <td><strong style="color: #dc2626;"><?= $tc['quantity'] ?> packs</strong></td>
                                <td>
                                    <button onclick="sendWhatsAppInvoice('<?= htmlspecialchars($tc['customer_phone']) ?>', '<?= htmlspecialchars($tc['invoice_number']) ?>', '<?= htmlspecialchars(addslashes($tc['business_name'])) ?>', 'RECALL NOTICE', 'URGENT DRAP RECALL: Please quarantine batch <?= htmlspecialchars($searchBatch) ?> immediately.')" class="btn btn-danger btn-sm">🚨 WhatsApp Notice</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Active Recalls Log -->
<div class="card">
    <div class="card-header">
        <span class="card-title">🚨 DRAP &amp; Internal Quality Recall Log</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Recall #</th>
                        <th>Date</th>
                        <th>Product &amp; Batch</th>
                        <th>Recall Reason</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Initiator</th>
                        <th>Trace</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recalls)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--slate-400); padding: 24px;">No product recalls initiated. Good manufacturing compliance!</td></tr>
                    <?php else: ?>
                        <?php foreach ($recalls as $rc): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($rc['recall_no']) ?></code></td>
                                <td><?= htmlspecialchars($rc['recall_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($rc['product_name']) ?></strong>
                                    <div>Batch: <code><?= htmlspecialchars($rc['batch_number']) ?></code></div>
                                </td>
                                <td><?= htmlspecialchars($rc['reason']) ?></td>
                                <td><span class="badge badge-danger"><?= strtoupper($rc['priority']) ?></span></td>
                                <td><span class="badge badge-warning"><?= strtoupper(str_replace('_', ' ', $rc['status'])) ?></span></td>
                                <td><?= htmlspecialchars($rc['initiator_name'] ?? 'Quality Officer') ?></td>
                                <td>
                                    <a href="recall.php?batch_search=<?= urlencode($rc['batch_number']) ?>" class="btn btn-secondary btn-sm">Trace</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Initiate Recall -->
<div class="modal-overlay" id="initiateRecallModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Initiate DRAP / Quality Batch Recall</h3>
            <button class="modal-close" onclick="closeModal('initiateRecallModal')">&times;</button>
        </div>
        <form method="POST" action="recall.php">
            <input type="hidden" name="action" value="initiate_recall">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Product Formulation *</label>
                    <select name="product_id" class="form-select" required>
                        <?php foreach ($products as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Batch Number to Recall *</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. PAN-2498" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Severity Level *</label>
                        <select name="priority" class="form-select" required>
                            <option value="critical">Class I (Critical - Health Hazard)</option>
                            <option value="high">Class II (High - Quality Defect)</option>
                            <option value="medium">Class III (Medium - Packaging / Labeling)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Recall Reason / DRAP Notice Reference *</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. DRAP Directive No. 4019 - Substandard Dissolution Rate" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Recall Instructions &amp; Action Plan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Immediately freeze warehouse stock and contact all receiving pharmacies for credit note return."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('initiateRecallModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Execute Recall &amp; Freeze Batch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
