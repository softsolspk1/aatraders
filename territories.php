<?php
// AA TRADERS - Territory & Geographic Distribution Hierarchy
$pageTitle = 'Territory Management';
require_once __DIR__ . '/includes/header.php';

// Handle Add Territory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_territory') {
    $region = trim($_POST['region']);
    $city = trim($_POST['city']);
    $name = trim($_POST['territory_name']);
    $code = trim($_POST['code']);

    $stmt = $db->prepare("INSERT INTO territories (region, city, territory_name, code) VALUES (?, ?, ?, ?)");
    $stmt->execute([$region, $city, $name, $code]);
    header('Location: territories.php?msg=territory_added');
    exit;
}

// Fetch Territories with area and customer counts
$territories = $db->query("
    SELECT t.*, 
           COUNT(DISTINCT a.id) as area_count,
           COUNT(DISTINCT c.id) as customer_count,
           COUNT(DISTINCT r.id) as rep_count
    FROM territories t
    LEFT JOIN areas a ON t.id = a.territory_id
    LEFT JOIN customers c ON t.id = c.territory_id
    LEFT JOIN sales_representatives r ON t.id = r.territory_id
    GROUP BY t.id
    ORDER BY t.region ASC, t.city ASC
")->fetchAll();

$areas = $db->query("
    SELECT a.*, t.territory_name, t.city
    FROM areas a
    JOIN territories t ON a.territory_id = t.id
    ORDER BY t.territory_name ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Territory &amp; Geographic Distribution Hierarchy</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Region &rarr; City &rarr; Territory &rarr; Area &rarr; Customer Structure</p>
    </div>
    <button onclick="openModal('addTerritoryModal')" class="btn btn-primary">+ Add New Territory</button>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">🗺️ Active Distribution Territories &amp; Coverage</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Region / Province</th>
                        <th>City</th>
                        <th>Territory Name</th>
                        <th>Areas Covered</th>
                        <th>Assigned Pharmacies</th>
                        <th>Field Reps</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($territories as $t): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($t['code']) ?></code></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($t['region']) ?></span></td>
                            <td><strong><?= htmlspecialchars($t['city']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($t['territory_name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= $t['area_count'] ?> Areas</span></td>
                            <td><span class="badge badge-success"><?= $t['customer_count'] ?> Pharmacies</span></td>
                            <td><?= $t['rep_count'] ?> Rep(s)</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Territory -->
<div class="modal-overlay" id="addTerritoryModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Add New Commercial Territory</h3>
            <button class="modal-close" onclick="closeModal('addTerritoryModal')">&times;</button>
        </div>
        <form method="POST" action="territories.php">
            <input type="hidden" name="action" value="add_territory">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Region / Province *</label>
                        <select name="region" class="form-select" required>
                            <option value="Sindh">Sindh</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Khyber Pakhtunkhwa">Khyber Pakhtunkhwa</option>
                            <option value="Balochistan">Balochistan</option>
                            <option value="Federal / ICT">Federal / ICT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-control" placeholder="e.g. Karachi" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Territory Name *</label>
                        <input type="text" name="territory_name" class="form-control" placeholder="e.g. Karachi West / SITE" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Territory Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. TERR-KHI-W" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTerritoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Territory</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
