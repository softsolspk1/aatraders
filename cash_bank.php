<?php
// AA TRADERS - Cashbook & Bank Accounts Management
$pageTitle = 'Cash & Bank Management';
require_once __DIR__ . '/includes/header.php';

// Handle Add Cash Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_cash') {
    $type = $_POST['type']; // in or out
    $amount = (float)$_POST['amount'];
    $desc = trim($_POST['description']);

    if ($amount > 0) {
        $stmt = $db->prepare("INSERT INTO cash_transactions (transaction_date, type, amount, source_module, reference_id, description, recorded_by) VALUES (date('now'), ?, ?, 'manual', ?, ?, ?)");
        $ref = 'CSH-' . strtoupper(substr(uniqid(), -6));
        $stmt->execute([$type, $amount, $ref, $desc, $currentUser['id']]);
        header('Location: cash_bank.php?msg=cash_saved');
        exit;
    }
}

// Handle Add Bank Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank_trx') {
    $bank_id = (int)$_POST['bank_account_id'];
    $type = $_POST['type']; // deposit, withdrawal
    $amount = (float)$_POST['amount'];
    $ref_no = trim($_POST['reference_no'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    $bank = $db->query("SELECT * FROM bank_accounts WHERE id = {$bank_id}")->fetch();
    if ($bank && $amount > 0) {
        $db->beginTransaction();
        $newBal = $type === 'deposit' ? ($bank['current_balance'] + $amount) : max(0, $bank['current_balance'] - $amount);
        $db->query("UPDATE bank_accounts SET current_balance = {$newBal} WHERE id = {$bank_id}");

        $stmt = $db->prepare("INSERT INTO bank_transactions (bank_account_id, transaction_date, type, amount, reference_no, description, recorded_by) VALUES (?, date('now'), ?, ?, ?, ?, ?)");
        $stmt->execute([$bank_id, $type, $amount, $ref_no, $desc, $currentUser['id']]);

        $db->commit();
        header('Location: cash_bank.php?msg=bank_saved');
        exit;
    }
}

// Cash Balance Calculation
$cashIn = $db->query("SELECT IFNULL(SUM(amount), 0) FROM cash_transactions WHERE type = 'in'")->fetchColumn();
$cashOut = $db->query("SELECT IFNULL(SUM(amount), 0) FROM cash_transactions WHERE type = 'out'")->fetchColumn();
$cashBalance = $cashIn - $cashOut;

// Fetch Bank Accounts
$bankAccounts = $db->query("SELECT * FROM bank_accounts WHERE is_active = 1")->fetchAll();
$totalBankBalance = array_sum(array_column($bankAccounts, 'current_balance'));

// Fetch Recent Cash Transactions
$cashTrx = $db->query("SELECT * FROM cash_transactions ORDER BY id DESC LIMIT 10")->fetchAll();
// Fetch Recent Bank Transactions
$bankTrx = $db->query("
    SELECT bt.*, ba.bank_name, ba.account_number 
    FROM bank_transactions bt 
    JOIN bank_accounts ba ON bt.bank_account_id = ba.id 
    ORDER BY bt.id DESC LIMIT 10
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Cashbook &amp; Banking Operations</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Daily Cash Drawer Reconciliations, Bank Deposits &amp; Operating Liquidity</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="openModal('cashModal')" class="btn btn-secondary">+ Cash Drawer Entry</button>
        <button onclick="openModal('bankModal')" class="btn btn-primary">+ Bank Transaction</button>
    </div>
</div>

<!-- Cash & Bank KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-success">
        <div class="kpi-info">
            <h3>Petty Cash Drawer Balance</h3>
            <div class="kpi-value">Rs. <?= number_format($cashBalance) ?></div>
            <div class="kpi-sub">On-hand counter cash</div>
        </div>
        <div class="kpi-icon green">💵</div>
    </div>
    <div class="kpi-card kpi-primary">
        <div class="kpi-info">
            <h3>Total Bank Balances</h3>
            <div class="kpi-value">Rs. <?= number_format($totalBankBalance) ?></div>
            <div class="kpi-sub">Across <?= count($bankAccounts) ?> commercial accounts</div>
        </div>
        <div class="kpi-icon blue">🏦</div>
    </div>
    <div class="kpi-card kpi-secondary">
        <div class="kpi-info">
            <h3>Total Liquid Funds</h3>
            <div class="kpi-value">Rs. <?= number_format($cashBalance + $totalBankBalance) ?></div>
            <div class="kpi-sub">Cash + Bank liquidity</div>
        </div>
        <div class="kpi-icon teal">💳</div>
    </div>
</div>

<!-- Bank Accounts Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <?php foreach ($bankAccounts as $ba): ?>
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="background: var(--slate-50);">
                <strong><?= htmlspecialchars($ba['bank_name']) ?></strong>
                <span class="badge badge-success">Active</span>
            </div>
            <div class="card-body">
                <div style="font-size: 13px; font-weight: 700; color: var(--slate-800); margin-bottom: 2px;">
                    <?= htmlspecialchars($ba['account_title']) ?>
                </div>
                <div style="font-size: 12px; color: var(--slate-600); margin-bottom: 8px;">
                    Account #: <code><?= htmlspecialchars($ba['account_number']) ?></code>
                </div>
                <div style="font-size: 11px; color: var(--slate-500); margin-bottom: 12px;">
                    IBAN: <code><?= htmlspecialchars($ba['iban'] ?? '-') ?></code>
                </div>
                <div style="border-top: 1px solid var(--border-color); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 12px; color: var(--slate-600);">Available Balance:</span>
                    <strong style="font-size: 16px; color: var(--primary);">Rs. <?= number_format($ba['current_balance'], 2) ?></strong>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Cash & Bank Tables -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Cashbook -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">💵 Cashbook (Daily Drawer Log)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Flow</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cashTrx)): ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--slate-400); padding: 20px;">No cash transactions logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cashTrx as $ct): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ct['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $ct['type'] === 'in' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= strtoupper($ct['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($ct['description']) ?></td>
                                    <td><strong>Rs. <?= number_format($ct['amount']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bank Transactions -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🏦 Bank Activity Log</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bankTrx)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--slate-400); padding: 20px;">No bank transactions logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bankTrx as $bt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($bt['transaction_date']) ?></td>
                                    <td><?= htmlspecialchars($bt['bank_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $bt['type'] === 'deposit' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= strtoupper($bt['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($bt['description']) ?></td>
                                    <td><strong>Rs. <?= number_format($bt['amount']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cash Transaction -->
<div class="modal-overlay" id="cashModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Record Cash Drawer Entry</h3>
            <button class="modal-close" onclick="closeModal('cashModal')">&times;</button>
        </div>
        <form method="POST" action="cash_bank.php">
            <input type="hidden" name="action" value="add_cash">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Flow Direction *</label>
                        <select name="type" class="form-select" required>
                            <option value="in">Cash In (Received / Drawer Top-up)</option>
                            <option value="out">Cash Out (Paid / Petty Withdrawal)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (PKR) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Transaction Purpose / Narrative *</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. Warehouse tea and refreshment petty expense." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('cashModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Cash Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Bank Transaction -->
<div class="modal-overlay" id="bankModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Record Commercial Bank Transaction</h3>
            <button class="modal-close" onclick="closeModal('bankModal')">&times;</button>
        </div>
        <form method="POST" action="cash_bank.php">
            <input type="hidden" name="action" value="add_bank_trx">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Bank Account *</label>
                    <select name="bank_account_id" class="form-select" required>
                        <?php foreach ($bankAccounts as $ba): ?>
                            <option value="<?= $ba['id'] ?>">
                                <?= htmlspecialchars($ba['bank_name']) ?> &mdash; <?= htmlspecialchars($ba['account_title']) ?> (Bal: Rs. <?= number_format($ba['current_balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Transaction Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="deposit">Deposit (Funds Received / Cash Deposit)</option>
                            <option value="withdrawal">Withdrawal (Transfer Out / Cheque Cleared)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (PKR) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Cheque / Reference / UTR Number</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. IBFT-881920">
                </div>

                <div class="form-group">
                    <label class="form-label">Narrative</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. Interbank transfer from sales revenue."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bankModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Bank Transaction</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
