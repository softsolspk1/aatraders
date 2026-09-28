<?php
// AA TRADERS - Product Master Module
$pageTitle = 'Product Master';
require_once __DIR__ . '/includes/header.php';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $generic = trim($_POST['generic_name'] ?? '');
    $brand = trim($_POST['brand_name'] ?? '');
    $category_id = (int)$_POST['category_id'];
    $therapeutic_id = !empty($_POST['therapeutic_id']) ? (int)$_POST['therapeutic_id'] : null;
    $manufacturer_id = (int)$_POST['manufacturer_id'];
    $dosage_form = trim($_POST['dosage_form'] ?? 'Tablet');
    $strength = trim($_POST['strength'] ?? '');
    $pack_size = trim($_POST['pack_size'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Box');
    $barcode = trim($_POST['barcode'] ?? '');
    $reorder_level = (int)($_POST['reorder_level'] ?? 25);
    $safety_stock = (int)($_POST['safety_stock'] ?? 15);
    $is_prescription = isset($_POST['is_prescription']) ? 1 : 0;

    $stmt = $db->prepare("INSERT INTO products (
        code, name, generic_name, brand_name, category_id, therapeutic_id, manufacturer_id, 
        dosage_form, strength, pack_size, unit, barcode, reorder_level, safety_stock, is_prescription
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $code, $name, $generic, $brand, $category_id, $therapeutic_id, $manufacturer_id,
        $dosage_form, $strength, $pack_size, $unit, $barcode, $reorder_level, $safety_stock, $is_prescription
    ]);

    Database::logAudit('CREATE', 'Products', $code, "Created formulation {$name} ({$code})");
    header('Location: products.php?msg=added');
    exit;
}

// Fetch categories, manufacturers, therapeutics for dropdowns
$categories = $db->query("SELECT * FROM product_categories ORDER BY name ASC")->fetchAll();
$manufacturers = $db->query("SELECT * FROM manufacturers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$therapeutics = $db->query("SELECT * FROM therapeutic_classes ORDER BY name ASC")->fetchAll();

// Fetch products list with aggregated batch stock
$products = $db->query("
    SELECT p.*, c.name as category_name, m.name as manufacturer_name, t.name as therapeutic_name,
           IFNULL(SUM(b.quantity_available), 0) as current_stock,
           COUNT(b.id) as active_batches
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.id
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    LEFT JOIN therapeutic_classes t ON p.therapeutic_id = t.id
    LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
    GROUP BY p.id
    ORDER BY p.name ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Product Master Catalog</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Manage pharmaceutical formulations, therapeutic classes & packaging</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="productSearch" onkeyup="filterTable('productSearch', 'productsTable')" placeholder="🔍 Search product, generic, code..." class="form-control" style="width: 280px;">
        <button onclick="openModal('addProductModal')" class="btn btn-primary">+ Add New Product</button>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Product formulation added to master catalog successfully.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="productsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product & Generic Name</th>
                        <th>Manufacturer</th>
                        <th>Category</th>
                        <th>Dosage / Strength</th>
                        <th>Pack Size</th>
                        <th>Current Stock</th>
                        <th>Reorder Lvl</th>
                        <th>Rx Flag</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['code']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                                <div style="font-size: 11.5px; color: var(--slate-500);">
                                    Generic: <em><?= htmlspecialchars($p['generic_name'] ?? 'N/A') ?></em>
                                </div>
                                <?php if (!empty($p['barcode'])): ?>
                                    <div style="font-size: 10px; color: #64748b;">🏷️ <?= htmlspecialchars($p['barcode']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['manufacturer_name'] ?? 'N/A') ?></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($p['category_name'] ?? 'General') ?></span></td>
                            <td><?= htmlspecialchars($p['dosage_form']) ?> &bull; <?= htmlspecialchars($p['strength']) ?></td>
                            <td><?= htmlspecialchars($p['pack_size']) ?></td>
                            <td>
                                <?php if ($p['current_stock'] <= $p['reorder_level']): ?>
                                    <span class="badge badge-danger"><?= $p['current_stock'] ?> <?= $p['unit'] ?>s</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $p['current_stock'] ?> <?= $p['unit'] ?>s</span>
                                <?php endif; ?>
                                <div style="font-size: 10px; color: #64748b; margin-top: 2px;"><?= $p['active_batches'] ?> batch(es)</div>
                            </td>
                            <td><?= $p['reorder_level'] ?></td>
                            <td>
                                <?= $p['is_prescription'] ? '<span class="badge badge-warning">Rx Only</span>' : '<span class="badge badge-info">OTC</span>' ?>
                            </td>
                            <td>
                                <a href="batches.php?product_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="View Batches & FEFO">Batches (FEFO)</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Product -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>Add New Pharmaceutical Product</h3>
            <button class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST" action="products.php">
            <input type="hidden" name="action" value="add_product">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. PRD-011" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Trade Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Panadol Extra 500mg" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Generic Name *</label>
                        <input type="text" name="generic_name" class="form-control" placeholder="e.g. Paracetamol + Caffeine" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand Name</label>
                        <input type="text" name="brand_name" class="form-control" placeholder="e.g. Panadol">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Manufacturer *</label>
                        <select name="manufacturer_id" class="form-select" required>
                            <?php foreach ($manufacturers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Category *</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Therapeutic Class</label>
                        <select name="therapeutic_id" class="form-select">
                            <option value="">-- None / General --</option>
                            <?php foreach ($therapeutics as $th): ?>
                                <option value="<?= $th['id'] ?>"><?= htmlspecialchars($th['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dosage Form *</label>
                        <select name="dosage_form" class="form-select">
                            <option value="Tablet">Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Suspension">Suspension</option>
                            <option value="Injection">Injection</option>
                            <option value="Cream">Cream</option>
                            <option value="Ointment">Ointment</option>
                            <option value="Drops">Drops</option>
                            <option value="Inhaler">Inhaler</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Strength</label>
                        <input type="text" name="strength" class="form-control" placeholder="e.g. 500mg, 10mg/ml">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pack Size</label>
                        <input type="text" name="pack_size" class="form-control" placeholder="e.g. 20x10s Box">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" value="Box">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Barcode / GTIN</label>
                        <input type="text" name="barcode" class="form-control" placeholder="e.g. 89640001099">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level (Alert threshold)</label>
                        <input type="number" name="reorder_level" class="form-control" value="25">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Safety Stock</label>
                        <input type="number" name="safety_stock" class="form-control" value="15">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="is_prescription" value="1" checked>
                        <span>Prescription Required (Schedule G / Rx Drug)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
