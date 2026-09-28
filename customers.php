<?php
// AA TRADERS - Customer Master & Credit Risk Management
$pageTitle = 'Customers & Credit Control';
require_once __DIR__ . '/includes/header.php';

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    $code = trim($_POST['code']);
    $business_name = trim($_POST['business_name']);
    $owner = trim($_POST['owner_name'] ?? '');
    $type = $_POST['customer_type'] ?? 'pharmacy';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? 'Karachi');
    $territory_id = (int)$_POST['territory_id'];
    $sales_rep_id = !empty($_POST['sales_rep_id']) ? (int)$_POST['sales_rep_id'] : null;
    $ntn = trim($_POST['ntn'] ?? '');
    $strn = trim($_POST['strn'] ?? '');
    $drug_license = trim($_POST['drug_license'] ?? '');
    $credit_limit = (float)($_POST['credit_limit'] ?? 100000.0);
    $credit_days = (int)($_POST['credit_days'] ?? 30);

    $stmt = $db->prepare("INSERT INTO customers (
        code, business_name, owner_name, customer_type, phone, email, address, city, 
        territory_id, sales_rep_id, ntn, strn, drug_license, credit_limit, credit_days, 
        opening_balance, current_balance, is_blocked
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.0, 0.0, 0)");
    $stmt->execute([
        $code, $business_name, $owner, $type, $phone, $email, $address, $city,
        $territory_id, $sales_rep_id, $ntn, $strn, $drug_license, $credit_limit, $credit_days
    ]);

    Database::logAudit('CREATE', 'Customers', $code, "Registered customer {$business_name} with credit limit Rs. {$credit_limit}");
    header('Location: customers.php?msg=customer_added');
    exit;
}

// Handle Credit Block Toggle
if (isset($_GET['toggle_block_id'])) {
    $id = (int)$_GET['toggle_block_id'];
    $cust = $db->query("SELECT * FROM customers WHERE id = {$id}")->fetch();
    if ($cust) {
        $newBlocked = $cust['is_blocked'] ? 0 : 1;
        $reason = $newBlocked ? "Manual management block by {$currentUser['name']}" : null;
        $stmt = $db->prepare("UPDATE customers SET is_blocked = ?, block_reason = ? WHERE id = ?");
        $stmt->execute([$newBlocked, $reason, $id]);
        Database::logAudit('CREDIT_BLOCK_CHANGE', 'Customers', (string)$id, "Toggled block status to {$newBlocked}");
        header('Location: customers.php?msg=status_updated');
        exit;
    }
}

// Fetch all customers with territory and rep names
$customers = $db->query("
    SELECT c.*, t.territory_name, r.name as rep_name,
           ROUND((c.current_balance / c.credit_limit) * 100, 1) as utilization_pct
    FROM customers c
    LEFT JOIN territories t ON c.territory_id = t.id
    LEFT JOIN sales_representatives r ON c.sales_rep_id = r.id
    ORDER BY c.current_balance DESC
")->fetchAll();

$territories = $db->query("SELECT * FROM territories ORDER BY territory_name ASC")->fetchAll();
$reps = $db->query("SELECT * FROM sales_representatives WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Customer Directory &amp; Credit Control</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Pharmacies, Hospitals, Clinics, Drug Licenses &amp; Real-time Credit Risk Ceiling</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="custSearch" onkeyup="filterTable('custSearch', 'custTable')" placeholder="🔍 Search pharmacy, license, rep..." class="form-control" style="width: 260px;">
        <button onclick="openModal('addCustModal')" class="btn btn-primary">+ Register Customer</button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Customer records and credit profiles updated successfully.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="custTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Business &amp; Owner</th>
                        <th>Type &amp; Drug License</th>
                        <th>Territory &amp; Rep</th>
                        <th>Credit Terms</th>
                        <th>Credit Limit</th>
                        <th>Outstanding Balance</th>
                        <th>Credit Utilization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($c['business_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--slate-500);">
                                    Owner: <?= htmlspecialchars($c['owner_name'] ?? 'N/A') ?> &bull; 📞 <?= htmlspecialchars($c['phone'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= strtoupper($c['customer_type']) ?></span>
                                <div style="font-size: 11px; color: #0284c7; font-weight: 600; margin-top: 3px;">
                                    📜 <?= htmlspecialchars($c['drug_license'] ?? 'Pending') ?>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($c['territory_name'] ?? 'General') ?></div>
                                <div style="font-size: 11px; color: var(--slate-500);">Rep: <?= htmlspecialchars($c['rep_name'] ?? 'Direct') ?></div>
                            </td>
                            <td><?= $c['credit_days'] ?> Days</td>
                            <td><strong>Rs. <?= number_format($c['credit_limit']) ?></strong></td>
                            <td>
                                <strong style="font-size: 13.5px; color: <?= $c['current_balance'] > $c['credit_limit'] ? '#dc2626' : 'var(--slate-800)' ?>;">
                                    Rs. <?= number_format($c['current_balance']) ?>
                                </strong>
                            </td>
                            <td style="min-width: 130px;">
                                <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 2px;">
                                    <span><?= $c['utilization_pct'] ?>%</span>
                                    <span><?= $c['current_balance'] > $c['credit_limit'] ? 'EXCEEDED' : 'OK' ?></span>
                                </div>
                                <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?= min(100, $c['utilization_pct']) ?>%; height: 100%; background: <?= $c['utilization_pct'] >= 100 ? '#ef4444' : ($c['utilization_pct'] >= 80 ? '#f59e0b' : '#10b981') ?>;"></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($c['is_blocked']): ?>
                                    <span class="badge badge-danger" title="<?= htmlspecialchars($c['block_reason'] ?? 'Blocked') ?>">BLOCKED</span>
                                <?php else: ?>
                                    <span class="badge badge-success">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px;">
                                    <a href="accounts.php?customer_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="View Ledger">Ledger</a>
                                    <a href="customers.php?toggle_block_id=<?= $c['id'] ?>" class="btn <?= $c['is_blocked'] ? 'btn-success' : 'btn-danger' ?> btn-sm" title="Toggle Credit Freeze">
                                        <?= $c['is_blocked'] ? 'Unblock' : 'Freeze' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Customer -->
<div class="modal-overlay" id="addCustModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Register Institutional / Pharmacy Customer</h3>
            <button class="modal-close" onclick="closeModal('addCustModal')">&times;</button>
        </div>
        <form method="POST" action="customers.php">
            <input type="hidden" name="action" value="add_customer">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Customer Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. CUST-008" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pharmacy / Hospital Trade Name *</label>
                        <input type="text" name="business_name" class="form-control" placeholder="e.g. HealthCare Pharmacy &amp; Chemist" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Proprietor / Pharmacist Name</label>
                        <input type="text" name="owner_name" class="form-control" placeholder="e.g. Dr. M. Farooq">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Establishment Type *</label>
                        <select name="customer_type" class="form-select" required>
                            <option value="pharmacy">Retail Pharmacy</option>
                            <option value="medical_store">Medical Store</option>
                            <option value="hospital">Hospital &amp; Clinical Centre</option>
                            <option value="clinic">Doctor Clinic</option>
                            <option value="wholesaler">Sub-Distributor / Wholesaler</option>
                            <option value="institution">Government / Armed Forces Institution</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">DRAP Drug Sale License # *</label>
                        <input type="text" name="drug_license" class="form-control" placeholder="e.g. DL-KHI-9812" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">NTN / STRN</label>
                        <input type="text" name="ntn" class="form-control" placeholder="e.g. 7192834-1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Phone / WhatsApp *</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. +92 300 1234567" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="pharmacy@gmail.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Territory Assignment *</label>
                        <select name="territory_id" class="form-select" required>
                            <?php foreach ($territories as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['territory_name']) ?> (<?= htmlspecialchars($t['city']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assigned Sales Representative</label>
                        <select name="sales_rep_id" class="form-select">
                            <option value="">-- Direct Corporate Account --</option>
                            <?php foreach ($reps as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['employee_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Approved Credit Limit (PKR) *</label>
                        <input type="number" step="1000" name="credit_limit" class="form-control" value="200000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credit Payment Terms (Days) *</label>
                        <input type="number" name="credit_days" class="form-control" value="30" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Premises Address *</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Shop #, Street, Commercial Area" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCustModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer Profile</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
