<?php
// AA TRADERS - Operational & Distribution Expense Management
$pageTitle = 'Operating Expenses';
require_once __DIR__ . '/includes/header.php';

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $date = $_POST['expense_date'];
    $category = $_POST['category'];
    $amount = (float)$_POST['amount'];
    $method = $_POST['payment_method'];
    $ref = trim($_POST['reference_no'] ?? '');
    $desc = trim($_POST['description']);

    if ($amount > 0) {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO expenses (expense_date, category, amount, payment_method, reference_no, description, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $category, $amount, $method, $ref, $desc, $currentUser['id']]);

        // If paid in cash, record in cash drawer
        if ($method === 'cash') {
            $db->query("INSERT INTO cash_transactions (transaction_date, type, amount, source_module, reference_id, description, recorded_by) 
                        VALUES ('{$date}', 'out', {$amount}, 'expenses', '{$ref}', 'Expense: {$category} - {$desc}', {$currentUser['id']})");
        }

        $db->commit();
        Database::logAudit('CREATE', 'Expenses', $category, "Recorded expense {$category} - Rs. {$amount}");
        header('Location: expenses.php?msg=expense_added');
        exit;
    }
}

// Fetch Expenses
$expenses = $db->query("
    SELECT e.*, u.full_name as recorder_name
    FROM expenses e
    LEFT JOIN users u ON e.recorded_by = u.id
    ORDER BY e.expense_date DESC, e.id DESC
")->fetchAll();

$monthTotal = $db->query("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE strftime('%Y-%m', expense_date) = strftime('%Y-%m', 'now')")->fetchColumn();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Distribution Operational Expenses</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Fleet Fuel, Salaries, Cold Chain Electricity, Vehicle Upkeep &amp; Administrative Overheads</p>
    </div>
    <button onclick="openModal('addExpModal')" class="btn btn-primary">+ Record Operational Expense</button>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-warning">
        <div class="kpi-info">
            <h3>Expenses This Month</h3>
            <div class="kpi-value">Rs. <?= number_format($monthTotal) ?></div>
            <div class="kpi-sub"><?= date('F Y') ?> Overheads</div>
        </div>
        <div class="kpi-icon amber">📉</div>
    </div>
    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Fleet &amp; Delivery Upkeep</h3>
            <div class="kpi-value">Fuel &amp; Servicing</div>
            <div class="kpi-sub">Cold chain delivery vans</div>
        </div>
        <div class="kpi-icon teal">🚚</div>
    </div>
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Facility Energy</h3>
            <div class="kpi-value">Cold Storage K-Electric</div>
            <div class="kpi-sub">Continuous temperature control</div>
        </div>
        <div class="kpi-icon blue">⚡</div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description / Narrative</th>
                        <th>Payment Mode</th>
                        <th>Reference #</th>
                        <th>Amount (PKR)</th>
                        <th>Authorized By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--slate-400); padding: 24px;">No expenses recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['expense_date']) ?></td>
                                <td>
                                    <span class="badge badge-secondary"><?= strtoupper(str_replace('_', ' ', $e['category'])) ?></span>
                                </td>
                                <td><?= htmlspecialchars($e['description']) ?></td>
                                <td>
                                    <span class="badge <?= $e['payment_method'] === 'cash' ? 'badge-success' : 'badge-primary' ?>">
                                        <?= strtoupper(str_replace('_', ' ', $e['payment_method'])) ?>
                                    </span>
                                </td>
                                <td><code><?= htmlspecialchars($e['reference_no'] ?? '-') ?></code></td>
                                <td><strong style="color: #dc2626; font-size: 14px;">Rs. <?= number_format($e['amount']) ?></strong></td>
                                <td><?= htmlspecialchars($e['recorder_name'] ?? 'Admin') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Expense -->
<div class="modal-overlay" id="addExpModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Record Distribution Operating Expense</h3>
            <button class="modal-close" onclick="closeModal('addExpModal')">&times;</button>
        </div>
        <form method="POST" action="expenses.php">
            <input type="hidden" name="action" value="add_expense">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expense Date *</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="fuel">Fuel &amp; Van Diesel</option>
                            <option value="vehicle_maintenance">Vehicle Maintenance &amp; Servicing</option>
                            <option value="electricity">Cold Storage Electricity &amp; Backup Gen</option>
                            <option value="salaries">Staff Salaries &amp; Rep Allowances</option>
                            <option value="rent">Warehouse &amp; Head Office Rent</option>
                            <option value="internet">Internet &amp; Telecom</option>
                            <option value="warehouse_expenses">Warehouse Packaging &amp; Pallets</option>
                            <option value="marketing">Medical Marketing &amp; Collaterals</option>
                            <option value="travel">Field Travel &amp; Per Diem</option>
                            <option value="office_expenses">Office Stationery &amp; Refreshments</option>
                            <option value="miscellaneous">Miscellaneous Overheads</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Amount (PKR) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Petty Cash</option>
                            <option value="bank_transfer">Bank Transfer / Cheque</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Voucher / Receipt Reference #</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. PET-0928-1">
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Narrative *</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. Suzuki delivery van diesel for North Nazimabad route." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
