<?php
// AA TRADERS - Sales Order Booking & Approval Engine
$pageTitle = 'Sales Orders';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/SchemeEngine.php';

// Handle Add Sales Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_so') {
    $customer_id = (int)$_POST['customer_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $sales_rep_id = !empty($_POST['sales_rep_id']) ? (int)$_POST['sales_rep_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    // Verify Customer Credit Freeze
    $cust = $db->query("SELECT * FROM customers WHERE id = {$customer_id}")->fetch();
    if ($cust && $cust['is_blocked']) {
        $error = "Order Blocked: Customer '{$cust['business_name']}' is credit frozen ({$cust['block_reason']}). Cannot accept new booking.";
    } elseif (empty($items)) {
        $error = "Please specify at least one product formulation to book the order.";
    } else {
        $db->beginTransaction();
        $soNumber = 'SO-' . date('Ymd') . '-' . rand(100, 999);
        $totalAmount = 0.0;

        $stmt = $db->prepare("INSERT INTO sales_orders (so_number, order_date, customer_id, sales_rep_id, warehouse_id, status, notes, created_by) 
                               VALUES (?, date('now'), ?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$soNumber, $customer_id, $sales_rep_id, $warehouse_id, $notes, $currentUser['id']]);
        $soId = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO sales_order_items (so_id, product_id, quantity, bonus_quantity, unit_trade_price, discount_pct, total_amount) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            if ($productId > 0 && $qty > 0) {
                // Get trade price from first active batch
                $tp = (float)$db->query("SELECT trade_price FROM product_batches WHERE product_id = {$productId} AND status = 'active' LIMIT 1")->fetchColumn() ?: 100.0;
                
                // Calculate pharma scheme bonus
                $scheme = SchemeEngine::calculateScheme($productId, $qty);
                $bonusQty = $scheme['bonus_quantity'];
                $discPct = $scheme['discount_percentage'];
                $itemTotal = ($qty * $tp) * (1 - ($discPct / 100));

                $stmtItem->execute([$soId, $productId, $qty, $bonusQty, $tp, $discPct, $itemTotal]);
                $totalAmount += $itemTotal;
            }
        }

        $db->query("UPDATE sales_orders SET total_amount = {$totalAmount}, net_amount = {$totalAmount} WHERE id = {$soId}");
        $db->commit();

        Database::logAudit('CREATE', 'SalesOrders', $soNumber, "Booked Sales Order {$soNumber} for {$cust['business_name']} - Rs. {$totalAmount}");
        header('Location: sales_orders.php?msg=so_created');
        exit;
    }
}

// Handle Order Approval & Convert to Invoice
if (isset($_GET['approve_id'])) {
    $soId = (int)$_GET['approve_id'];
    $so = $db->query("SELECT * FROM sales_orders WHERE id = {$soId}")->fetch();
    if ($so && $so['status'] === 'pending') {
        $db->query("UPDATE sales_orders SET status = 'approved' WHERE id = {$soId}");
        Database::logAudit('APPROVE', 'SalesOrders', (string)$soId, "Approved Sales Order {$so['so_number']}");
        header("Location: sales_invoices.php?create_from_so={$soId}");
        exit;
    }
}

// Fetch Orders
$orders = $db->query("
    SELECT o.*, c.business_name as customer_name, c.credit_limit, c.current_balance, c.is_blocked,
           r.name as rep_name, w.name as warehouse_name,
           COUNT(oi.id) as item_count
    FROM sales_orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN sales_representatives r ON o.sales_rep_id = r.id
    JOIN warehouses w ON o.warehouse_id = w.id
    LEFT JOIN sales_order_items oi ON o.id = oi.so_id
    GROUP BY o.id
    ORDER BY o.id DESC
")->fetchAll();

$customers = $db->query("SELECT * FROM customers WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
$products = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$reps = $db->query("SELECT * FROM sales_representatives WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Sales Order Management &amp; Stock Check</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Order Booking &rarr; Real-time Stock Allocation &rarr; Credit Audit &rarr; Invoice Generation</p>
    </div>
    <button onclick="openModal('addSoModal')" class="btn btn-primary">+ Book Sales Order</button>
</div>

<?php if (isset($error)): ?>
    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Order Date</th>
                        <th>Pharmacy / Client</th>
                        <th>Sales Rep</th>
                        <th>Warehouse</th>
                        <th>Lines</th>
                        <th>Order Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--slate-400); padding: 24px;">No sales orders booked yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($o['so_number']) ?></code></td>
                                <td><?= htmlspecialchars($o['order_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($o['customer_name']) ?></strong>
                                    <?php if ($o['is_blocked']): ?>
                                        <span class="badge badge-danger">Credit Frozen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($o['rep_name'] ?? 'Direct') ?></td>
                                <td><?= htmlspecialchars($o['warehouse_name']) ?></td>
                                <td><?= $o['item_count'] ?> item(s)</td>
                                <td><strong>Rs. <?= number_format($o['net_amount']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $o['status'] === 'approved' ? 'badge-success' : ($o['status'] === 'invoiced' ? 'badge-primary' : 'badge-warning') ?>">
                                        <?= strtoupper($o['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($o['status'] === 'pending'): ?>
                                        <a href="sales_orders.php?approve_id=<?= $o['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this sales order and proceed to FEFO Invoicing?')">
                                            ✓ Approve &amp; Invoice
                                        </a>
                                    <?php elseif ($o['status'] === 'approved'): ?>
                                        <a href="sales_invoices.php?create_from_so=<?= $o['id'] ?>" class="btn btn-primary btn-sm">
                                            Convert to Invoice
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #10b981; font-weight: 600;">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Book Sales Order -->
<div class="modal-overlay" id="addSoModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Book New Sales Order</h3>
            <button class="modal-close" onclick="closeModal('addSoModal')">&times;</button>
        </div>
        <form method="POST" action="sales_orders.php">
            <input type="hidden" name="action" value="create_so">
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
                        <label class="form-label">Fulfillment Warehouse *</label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Sales Representative</label>
                    <select name="sales_rep_id" class="form-select">
                        <option value="">-- Direct Booking --</option>
                        <?php foreach ($reps as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 16px;">
                    <label class="form-label">Order Line Items (Pharmaceutical Formulations)</label>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;" id="soItemsTable">
                        <thead>
                            <tr style="background: var(--slate-100); text-align: left; font-size: 12px;">
                                <th style="padding: 8px;">Product Formulation</th>
                                <th style="padding: 8px; width: 140px;">Quantity (Packs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 3; $i++): ?>
                                <tr>
                                    <td style="padding: 6px;">
                                        <select name="items[<?= $i ?>][product_id]" class="form-select">
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($products as $pr): ?>
                                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['strength']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="padding: 6px;">
                                        <input type="number" name="items[<?= $i ?>][quantity]" class="form-control" min="1" value="<?= $i === 0 ? 20 : '' ?>" placeholder="Qty">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label class="form-label">Special Delivery / Order Booking Instructions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Deliver before 2:00 PM; check cold pack storage on arrival."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSoModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Sales Order</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
