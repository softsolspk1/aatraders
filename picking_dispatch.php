<?php
// AA TRADERS - Warehouse Order Picking & Dispatch Management
$pageTitle = 'Picking & Dispatch';
require_once __DIR__ . '/includes/header.php';

// Handle Dispatch Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_dispatch') {
    $invoice_id = (int)$_POST['invoice_id'];
    $status = $_POST['dispatch_status']; // picked, packed, dispatched, delivered
    $driver = trim($_POST['driver_name'] ?? '');
    $vehicle = trim($_POST['vehicle_no'] ?? '');

    $stmt = $db->prepare("UPDATE sales_invoices SET dispatch_status = ?, driver_name = ?, vehicle_no = ? WHERE id = ?");
    $stmt->execute([$status, $driver, $vehicle, $invoice_id]);

    Database::logAudit('UPDATE_DISPATCH', 'Dispatch', (string)$invoice_id, "Updated invoice #{$invoice_id} status to {$status} (Driver: {$driver})");
    header('Location: picking_dispatch.php?msg=dispatch_updated');
    exit;
}

// Fetch Invoices for Picking & Dispatch
$dispatches = $db->query("
    SELECT inv.*, c.business_name as customer_name, c.address as delivery_address, c.phone as customer_phone,
           w.name as warehouse_name,
           (SELECT COUNT(*) FROM sales_invoice_items WHERE invoice_id = inv.id) as item_count
    FROM sales_invoices inv
    JOIN customers c ON inv.customer_id = c.id
    JOIN warehouses w ON inv.warehouse_id = w.id
    ORDER BY inv.id DESC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Order Picking Lists &amp; Fleet Dispatch</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Warehouse Picking &bull; Cold Chain Packing &bull; Delivery Vans &bull; Delivery Challans &bull; Proof of Delivery</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Dispatch and delivery status updated successfully.
    </div>
<?php endif; ?>

<!-- Dispatch Overview KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>Pending / Picking</h3>
            <div class="kpi-value"><?= count(array_filter($dispatches, fn($d) => in_array($d['dispatch_status'], ['pending', 'picked']))) ?> Invoices</div>
            <div class="kpi-sub">On warehouse floor</div>
        </div>
        <div class="kpi-icon amber">📋</div>
    </div>
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Dispatched / In-Transit</h3>
            <div class="kpi-value"><?= count(array_filter($dispatches, fn($d) => in_array($d['dispatch_status'], ['packed', 'dispatched']))) ?> Invoices</div>
            <div class="kpi-sub">Van distribution routes</div>
        </div>
        <div class="kpi-icon blue">🚚</div>
    </div>
    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Delivered &amp; Acknowledged</h3>
            <div class="kpi-value"><?= count(array_filter($dispatches, fn($d) => $d['dispatch_status'] === 'delivered')) ?> Invoices</div>
            <div class="kpi-sub">Acknowledged by pharmacist</div>
        </div>
        <div class="kpi-icon green">✅</div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Billing Date</th>
                        <th>Customer / Destination</th>
                        <th>Facility</th>
                        <th>Line Items</th>
                        <th>Driver &amp; Vehicle</th>
                        <th>Dispatch Status</th>
                        <th>Gate Pass / Challan</th>
                        <th>Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dispatches)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--slate-400); padding: 24px;">No dispatch invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dispatches as $d): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($d['invoice_number']) ?></code></td>
                                <td><?= htmlspecialchars($d['invoice_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($d['customer_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($d['delivery_address']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($d['warehouse_name']) ?></td>
                                <td><?= $d['item_count'] ?> item(s)</td>
                                <td>
                                    <div><?= htmlspecialchars($d['driver_name'] ?? 'Unassigned') ?></div>
                                    <?php if ($d['vehicle_no']): ?>
                                        <div style="font-size: 11px; color: #64748b;">🚐 <?= htmlspecialchars($d['vehicle_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $s = $d['dispatch_status'];
                                    $badge = 'badge-secondary';
                                    if ($s === 'delivered') $badge = 'badge-success';
                                    elseif ($s === 'dispatched') $badge = 'badge-primary';
                                    elseif ($s === 'packed') $badge = 'badge-info';
                                    elseif ($s === 'picked') $badge = 'badge-warning';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= strtoupper($s) ?></span>
                                </td>
                                <td>
                                    <a href="print_gatepass.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print Delivery Challan">📄 Gate Pass</a>
                                </td>
                                <td>
                                    <button onclick="openDispatchModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['invoice_number']) ?>', '<?= $d['dispatch_status'] ?>', '<?= htmlspecialchars(addslashes($d['driver_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($d['vehicle_no'] ?? '')) ?>')" class="btn btn-primary btn-sm">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Update Dispatch Status -->
<div class="modal-overlay" id="dispatchModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Update Logistics &amp; Dispatch Status</h3>
            <button class="modal-close" onclick="closeModal('dispatchModal')">&times;</button>
        </div>
        <form method="POST" action="picking_dispatch.php">
            <input type="hidden" name="action" value="update_dispatch">
            <input type="hidden" name="invoice_id" id="modalInvId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" id="modalInvNum" class="form-control" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Dispatch Status *</label>
                    <select name="dispatch_status" id="modalStatus" class="form-select" required>
                        <option value="pending">Pending Warehouse Allocation</option>
                        <option value="picked">Picked (FEFO Verified from Shelves)</option>
                        <option value="packed">Packed (Insulated Box / Cold Pack)</option>
                        <option value="dispatched">Dispatched (Out for Delivery)</option>
                        <option value="delivered">Delivered &amp; Signed by Pharmacist</option>
                        <option value="returned">Returned / Failed Delivery</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delivery Driver / Rider Name</label>
                        <input type="text" name="driver_name" id="modalDriver" class="form-control" placeholder="e.g. Asif Qureshi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Registration Number</label>
                        <input type="text" name="vehicle_no" id="modalVehicle" class="form-control" placeholder="e.g. KHI-B-4921">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('dispatchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDispatchModal(invId, invNum, status, driver, vehicle) {
    document.getElementById('modalInvId').value = invId;
    document.getElementById('modalInvNum').value = invNum;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalDriver').value = driver || '';
    document.getElementById('modalVehicle').value = vehicle || '';
    openModal('dispatchModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
