<?php
// AA TRADERS - Sales Representatives, Targets & Field Activity
$pageTitle = 'Sales Representatives & Targets';
require_once __DIR__ . '/includes/header.php';

// Handle Add Customer Visit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_visit') {
    $rep_id = (int)$_POST['sales_rep_id'];
    $cust_id = (int)$_POST['customer_id'];
    $visit_date = $_POST['visit_date'];
    $purpose = trim($_POST['purpose']);
    $outcome = trim($_POST['outcome']);
    $order_booked = isset($_POST['order_booked']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $db->prepare("INSERT INTO customer_visits (sales_rep_id, customer_id, visit_date, purpose, outcome, order_booked, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$rep_id, $cust_id, $visit_date, $purpose, $outcome, $order_booked, $notes]);

    header('Location: sales_reps.php?msg=visit_logged');
    exit;
}

// Fetch Reps with Target & Achievement
$reps = $db->query("
    SELECT r.*, t.territory_name, t.city,
           COUNT(DISTINCT c.id) as assigned_customers_count,
           IFNULL(st.target_amount, r.monthly_target) as monthly_target,
           IFNULL(st.achieved_amount, 0) as achieved_amount,
           ROUND((IFNULL(st.achieved_amount, 0) / IFNULL(st.target_amount, r.monthly_target)) * 100, 1) as achievement_pct,
           ROUND(IFNULL(st.achieved_amount, 0) * (r.commission_rate / 100), 2) as accrued_commission
    FROM sales_representatives r
    LEFT JOIN territories t ON r.territory_id = t.id
    LEFT JOIN customers c ON r.id = c.sales_rep_id
    LEFT JOIN sales_targets st ON r.id = st.sales_rep_id AND st.target_year = 2026 AND st.target_month = 9
    GROUP BY r.id
    ORDER BY achievement_pct DESC
")->fetchAll();

// Fetch Recent Visits
$visits = $db->query("
    SELECT v.*, r.name as rep_name, c.business_name as customer_name
    FROM customer_visits v
    JOIN sales_representatives r ON v.sales_rep_id = r.id
    JOIN customers c ON v.customer_id = c.id
    ORDER BY v.id DESC LIMIT 10
")->fetchAll();

$customers = $db->query("SELECT id, business_name FROM customers WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Sales Representatives, Targets &amp; Commissions</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Field Force Performance, Monthly Revenue Targets &amp; Pharmacy Visit Logging</p>
    </div>
    <button onclick="openModal('logVisitModal')" class="btn btn-primary">+ Log Field Customer Visit</button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Field activity visit logged into representative diary successfully.
    </div>
<?php endif; ?>

<!-- Rep Performance Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <?php foreach ($reps as $r): ?>
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="background: var(--slate-50);">
                <div>
                    <strong style="font-size: 15px; color: var(--slate-900);"><?= htmlspecialchars($r['name']) ?></strong>
                    <div style="font-size: 11px; color: var(--slate-500);"><code><?= htmlspecialchars($r['employee_code']) ?></code> &bull; <?= htmlspecialchars($r['territory_name']) ?> (<?= htmlspecialchars($r['city']) ?>)</div>
                </div>
                <span class="badge badge-primary"><?= $r['commission_rate'] ?>% Comm</span>
            </div>
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                    <span style="color: var(--slate-600);">Monthly Target:</span>
                    <strong>Rs. <?= number_format($r['monthly_target']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                    <span style="color: var(--slate-600);">Achieved Revenue:</span>
                    <strong style="color: #0284c7;">Rs. <?= number_format($r['achieved_amount']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 8px;">
                    <span style="color: var(--slate-600);">Accrued Commission:</span>
                    <strong style="color: #10b981;">Rs. <?= number_format($r['accrued_commission']) ?></strong>
                </div>

                <!-- Progress Bar -->
                <div style="margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 2px;">
                        <span>Target Progress</span>
                        <strong><?= $r['achievement_pct'] ?>%</strong>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                        <div style="width: <?= min(100, $r['achievement_pct']) ?>%; height: 100%; background: <?= $r['achievement_pct'] >= 85 ? '#10b981' : ($r['achievement_pct'] >= 70 ? '#f59e0b' : '#ef4444') ?>;"></div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); margin-top: 14px; padding-top: 10px; font-size: 11.5px; color: var(--slate-500); display: flex; justify-content: space-between;">
                    <span>👥 Assigned Accounts: <?= $r['assigned_customers_count'] ?></span>
                    <span>📞 <?= htmlspecialchars($r['phone'] ?? '-') ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Field Activity Visit Log -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📝 Daily Pharmacy Field Activity &amp; Booking Visits</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sales Representative</th>
                        <th>Pharmacy Visited</th>
                        <th>Purpose</th>
                        <th>Meeting Outcome</th>
                        <th>Order Booked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visits)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--slate-400); padding: 20px;">No field visits recorded yet. Click above to log visit.</td></tr>
                    <?php else: ?>
                        <?php foreach ($visits as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['visit_date']) ?></td>
                                <td><strong><?= htmlspecialchars($v['rep_name']) ?></strong></td>
                                <td><?= htmlspecialchars($v['customer_name']) ?></td>
                                <td><?= htmlspecialchars($v['purpose']) ?></td>
                                <td><?= htmlspecialchars($v['outcome']) ?></td>
                                <td>
                                    <?= $v['order_booked'] ? '<span class="badge badge-success">✓ Order Booked</span>' : '<span class="badge badge-secondary">Follow-up</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Log Field Visit -->
<div class="modal-overlay" id="logVisitModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Record Representative Field Visit</h3>
            <button class="modal-close" onclick="closeModal('logVisitModal')">&times;</button>
        </div>
        <form method="POST" action="sales_reps.php">
            <input type="hidden" name="action" value="log_visit">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sales Representative *</label>
                        <select name="sales_rep_id" class="form-select" required>
                            <?php foreach ($reps as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Visit Date *</label>
                        <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Customer / Medical Store Visited *</label>
                    <select name="customer_id" class="form-select" required>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Visit Objective *</label>
                        <select name="purpose" class="form-select" required>
                            <option value="Routine Order Booking">Routine Order Booking</option>
                            <option value="Outstanding Collection">Outstanding Collection</option>
                            <option value="New Formulation Introduction">New Formulation Introduction</option>
                            <option value="Scheme & Discount Promotion">Scheme &amp; Discount Promotion</option>
                            <option value="Expiry Verification & Claim">Expiry Verification &amp; Claim</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meeting Outcome</label>
                        <input type="text" name="outcome" class="form-control" placeholder="e.g. Order committed for next Monday">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="order_booked" value="1" checked>
                        <span>Sales Order Booked during visit</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">Observations / Doctor Feedback</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Inquired about Augmentin 625mg stock availability."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logVisitModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Visit Record</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
