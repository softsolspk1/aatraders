<?php
// AA TRADERS - Regulatory Audit Trail & System Activity Log
$pageTitle = 'Regulatory Audit Trail';
require_once __DIR__ . '/includes/header.php';

// Module Filter
$moduleFilter = $_GET['module'] ?? '';
$where = "1=1";
$params = [];
if (!empty($moduleFilter)) {
    $where = "module = ?";
    $params[] = $moduleFilter;
}

$stmt = $db->prepare("SELECT * FROM audit_logs WHERE {$where} ORDER BY id DESC LIMIT 50");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Compliance &amp; Regulatory Audit Trail</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Immutable Ledger of System Operations, Batches, Price Modifications &amp; User Access</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <label style="font-size: 13px; font-weight: 600;">Filter Module:</label>
        <select onchange="window.location.href='audit_logs.php?module=' + this.value" class="form-select" style="width: 200px;">
            <option value="">-- All Modules --</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= $m === $moduleFilter ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button onclick="window.print()" class="btn btn-secondary no-print">🖨️ Export / Print</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Reference / Entity</th>
                        <th>Audit Details / Narrative</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--slate-400); padding: 24px;">No audit events recorded for this selection.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td>#<?= $l['id'] ?></td>
                                <td><?= htmlspecialchars($l['timestamp']) ?></td>
                                <td><strong><?= htmlspecialchars($l['username']) ?></strong></td>
                                <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                                <td>
                                    <?php
                                    $act = $l['action'];
                                    $badge = 'badge-secondary';
                                    if (str_contains($act, 'CREATE')) $badge = 'badge-success';
                                    elseif (str_contains($act, 'DELETE') || str_contains($act, 'RECALL')) $badge = 'badge-danger';
                                    elseif (str_contains($act, 'UPDATE') || str_contains($act, 'APPROVE')) $badge = 'badge-primary';
                                    elseif (str_contains($act, 'LOGIN')) $badge = 'badge-info';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($act) ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($l['module']) ?></strong></td>
                                <td><code><?= htmlspecialchars($l['record_id'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars($l['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
