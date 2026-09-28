<?php
// AA TRADERS - Pharmaceutical Schemes & Bonus Engine
$pageTitle = 'Pharma Schemes & Bonus Goods';
require_once __DIR__ . '/includes/header.php';

// Handle Add Scheme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_scheme') {
    $name = trim($_POST['name']);
    $scheme_type = $_POST['scheme_type']; // bonus_qty or percentage_discount
    $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $buy_qty = (int)($_POST['buy_qty'] ?? 10);
    $free_qty = (int)($_POST['free_qty'] ?? 1);
    $discount_pct = (float)($_POST['discount_pct'] ?? 0.0);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    $stmt = $db->prepare("INSERT INTO schemes (name, scheme_type, product_id, buy_qty, free_qty, discount_pct, start_date, end_date, is_active) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$name, $scheme_type, $product_id, $buy_qty, $free_qty, $discount_pct, $start_date, $end_date]);

    Database::logAudit('CREATE', 'Schemes', $name, "Created scheme {$name}");
    header('Location: schemes.php?msg=scheme_added');
    exit;
}

// Handle Toggle Scheme
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $db->query("UPDATE schemes SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = {$id}");
    header('Location: schemes.php?msg=toggled');
    exit;
}

// Fetch all schemes
$schemes = $db->query("
    SELECT s.*, p.name as product_name, p.code as product_code, p.dosage_form
    FROM schemes s
    LEFT JOIN products p ON s.product_id = p.id
    ORDER BY s.id DESC
")->fetchAll();

$products = $db->query("SELECT id, name, code, dosage_form FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Pharmaceutical Schemes &amp; Bonus Offers</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Automated bonus quantity calculation (10+1, 20+2) and volume trade discounts</p>
    </div>
    <button onclick="openModal('addSchemeModal')" class="btn btn-primary">+ Launch New Scheme</button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Scheme updated successfully. Active orders and billing will reflect these terms automatically.
    </div>
<?php endif; ?>

<!-- Scheme Cards Overview -->
<div class="kpi-grid" style="margin-bottom: 20px;">
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Active Incentive Programs</h3>
            <div class="kpi-value"><?= count(array_filter($schemes, fn($s) => $s['is_active'] == 1)) ?> Active</div>
            <div class="kpi-sub">Field force trade schemes</div>
        </div>
        <div class="kpi-icon blue">🎁</div>
    </div>
    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Standard Bonus Scheme</h3>
            <div class="kpi-value">10 + 1 Free</div>
            <div class="kpi-sub">Every 10 boxes gives 1 bonus</div>
        </div>
        <div class="kpi-icon green">✨</div>
    </div>
    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Special Bulk Bonus</h3>
            <div class="kpi-value">20 + 2 / 50 + 5</div>
            <div class="kpi-sub">Hospital & institutional tiers</div>
        </div>
        <div class="kpi-icon teal">📦</div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Scheme Title</th>
                        <th>Target Product</th>
                        <th>Scheme Type</th>
                        <th>Condition &amp; Benefit</th>
                        <th>Validity Window</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schemes as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($s['name']) ?></strong>
                            </td>
                            <td>
                                <?php if ($s['product_name']): ?>
                                    <strong><?= htmlspecialchars($s['product_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--slate-500);"><?= htmlspecialchars($s['dosage_form']) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-primary">All Formulations</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['scheme_type'] === 'bonus_qty'): ?>
                                    <span class="badge badge-success">Free Goods (Bonus)</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Percentage Discount</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['scheme_type'] === 'bonus_qty'): ?>
                                    <strong style="color: #0284c7; font-size: 14px;">Buy <?= $s['buy_qty'] ?> + <?= $s['free_qty'] ?> FREE</strong>
                                    <div style="font-size: 11px; color: #64748b;">(e.g., Order 30 &rarr; 3 Free Bonus Units)</div>
                                <?php else: ?>
                                    <strong style="color: #10b981; font-size: 14px;"><?= $s['discount_pct'] ?>% Trade Discount</strong>
                                    <div style="font-size: 11px; color: #64748b;">Min Order: <?= $s['buy_qty'] ?> units</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 12px;">
                                    Start: <?= $s['start_date'] ?? 'Immediate' ?><br>
                                    End: <?= $s['end_date'] ?? 'Ongoing' ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= $s['is_active'] ? 'ACTIVE' : 'PAUSED' ?>
                                </span>
                            </td>
                            <td>
                                <a href="schemes.php?toggle_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">
                                    <?= $s['is_active'] ? 'Pause' : 'Activate' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Scheme -->
<div class="modal-overlay" id="addSchemeModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Create Pharmaceutical Trade Scheme</h3>
            <button class="modal-close" onclick="closeModal('addSchemeModal')">&times;</button>
        </div>
        <form method="POST" action="schemes.php">
            <input type="hidden" name="action" value="add_scheme">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Scheme Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Winter Bonus Scheme 10+1" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Scheme Type *</label>
                        <select name="scheme_type" id="schemeTypeSelect" class="form-select" onchange="toggleSchemeFields()">
                            <option value="bonus_qty">Bonus Quantity (e.g. 10 + 1 Free)</option>
                            <option value="percentage_discount">Percentage Discount (e.g. 5% Off)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Product *</label>
                        <select name="product_id" class="form-select">
                            <option value="">-- Apply to All Products --</option>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Buy Quantity (Requirement) *</label>
                        <input type="number" name="buy_qty" class="form-control" value="10" min="1" required>
                    </div>
                    <div class="form-group" id="freeQtyGroup">
                        <label class="form-label">Free Bonus Quantity *</label>
                        <input type="number" name="free_qty" class="form-control" value="1" min="1">
                    </div>
                    <div class="form-group" id="discPctGroup" style="display: none;">
                        <label class="form-label">Discount Percentage (%) *</label>
                        <input type="number" step="0.1" name="discount_pct" class="form-control" value="5.0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= date('Y-12-31') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSchemeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Activate Scheme</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSchemeFields() {
    const type = document.getElementById('schemeTypeSelect').value;
    const freeQtyGroup = document.getElementById('freeQtyGroup');
    const discPctGroup = document.getElementById('discPctGroup');

    if (type === 'bonus_qty') {
        freeQtyGroup.style.display = 'block';
        discPctGroup.style.display = 'none';
    } else {
        freeQtyGroup.style.display = 'none';
        discPctGroup.style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
