<?php
// AA TRADERS - Supplier Management & Creditor Accounts
$pageTitle = 'Suppliers Master';
require_once __DIR__ . '/includes/header.php';

// Handle Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? 'Karachi');
    $ntn = trim($_POST['ntn'] ?? '');
    $strn = trim($_POST['strn'] ?? '');
    $drug_license = trim($_POST['drug_license'] ?? '');
    $terms = (int)($_POST['payment_terms_days'] ?? 30);
    $limit = (float)($_POST['credit_limit'] ?? 1000000.0);

    $stmt = $db->prepare("INSERT INTO suppliers (code, name, contact_person, phone, email, address, city, ntn, strn, drug_license, payment_terms_days, credit_limit, opening_balance, current_balance) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.0, 0.0)");
    $stmt->execute([$code, $name, $contact, $phone, $email, $address, $city, $ntn, $strn, $drug_license, $terms, $limit]);

    Database::logAudit('CREATE', 'Suppliers', $code, "Added supplier {$name}");
    header('Location: suppliers.php?msg=supplier_added');
    exit;
}

// Fetch Suppliers
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Suppliers &amp; Principal Manufacturers</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Pharma Principals, Drug Manufacturing Licenses &amp; Commercial Accounts Payable</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="supSearch" onkeyup="filterTable('supSearch', 'supTable')" placeholder="🔍 Search supplier, license..." class="form-control" style="width: 260px;">
        <button onclick="openModal('addSupModal')" class="btn btn-primary">+ Register Supplier</button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Supplier profile created successfully.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="supTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Supplier / Manufacturer</th>
                        <th>Contact Person</th>
                        <th>DRAP License &amp; NTN</th>
                        <th>Payment Terms</th>
                        <th>Credit Facility</th>
                        <th>Outstanding Payable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($s['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($s['name']) ?></strong>
                                <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($s['city']) ?> &bull; 📞 <?= htmlspecialchars($s['phone'] ?? '-') ?></div>
                            </td>
                            <td><?= htmlspecialchars($s['contact_person'] ?? 'N/A') ?></td>
                            <td>
                                <div>📜 <?= htmlspecialchars($s['drug_license'] ?? 'N/A') ?></div>
                                <div style="font-size: 11px; color: var(--slate-500);">NTN: <?= htmlspecialchars($s['ntn'] ?? '-') ?></div>
                            </td>
                            <td><?= $s['payment_terms_days'] ?> Days</td>
                            <td>Rs. <?= number_format($s['credit_limit']) ?></td>
                            <td>
                                <strong style="font-size: 14px; color: var(--primary-dark);">Rs. <?= number_format($s['current_balance']) ?></strong>
                            </td>
                            <td>
                                <a href="accounts.php?tab=payable&supplier_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Ledger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Supplier -->
<div class="modal-overlay" id="addSupModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Register Principal Pharmaceutical Supplier</h3>
            <button class="modal-close" onclick="closeModal('addSupModal')">&times;</button>
        </div>
        <form method="POST" action="suppliers.php">
            <input type="hidden" name="action" value="add_supplier">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. SUP-FEROZ" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier Corporate Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Ferozsons Laboratories Ltd" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Key Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Full Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone / Fax *</label>
                        <input type="text" name="phone" class="form-control" placeholder="+92 21 ..." required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="supply@pharma.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-control" value="Karachi" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">DRAP Drug Manufacturing / Distribution License</label>
                        <input type="text" name="drug_license" class="form-control" placeholder="e.g. DRAP-D-09">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NTN / STRN</label>
                        <input type="text" name="ntn" class="form-control" placeholder="e.g. 1928374-5">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Payment Terms (Credit Days) *</label>
                        <input type="number" name="payment_terms_days" class="form-control" value="30" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Approved Credit Limit (PKR) *</label>
                        <input type="number" name="credit_limit" class="form-control" value="3000000" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Plant / Distribution Center Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Industrial Area, Street"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSupModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
