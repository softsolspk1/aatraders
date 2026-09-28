<?php
// AA TRADERS - Collections & Customer Payment Receipts
$pageTitle = 'Collections & Receipts';
require_once __DIR__ . '/includes/header.php';

// Handle Add Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $customer_id = (int)$_POST['customer_id'];
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $method = $_POST['payment_method']; // cash, bank_transfer, cheque, online
    $ref_no = trim($_POST['reference_no'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $amount = (float)$_POST['amount'];
    $notes = trim($_POST['notes'] ?? '');

    $cust = $db->query("SELECT * FROM customers WHERE id = {$customer_id}")->fetch();
    if ($cust && $amount > 0) {
        $db->beginTransaction();
        $receiptNo = 'REC-' . date('Ymd') . '-' . rand(100, 999);

        $stmtPay = $db->prepare("INSERT INTO payments_received (receipt_no, payment_date, customer_id, invoice_id, payment_method, reference_no, bank_name, amount, notes, received_by) 
                                 VALUES (?, date('now'), ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtPay->execute([$receiptNo, $customer_id, $invoice_id, $method, $ref_no, $bank_name, $amount, $notes, $currentUser['id']]);
        $receiptId = $db->lastInsertId();

        // Credit Customer Balance
        $newBalance = max(0, $cust['current_balance'] - $amount);
        $db->query("UPDATE customers SET current_balance = {$newBalance} WHERE id = {$customer_id}");

        // If customer was blocked and now within credit limit, unblock automatically!
        if ($cust['is_blocked'] && $newBalance <= $cust['credit_limit']) {
            $db->query("UPDATE customers SET is_blocked = 0, block_reason = NULL WHERE id = {$customer_id}");
        }

        // Ledger Entry
        $stmtLed = $db->prepare("INSERT INTO customer_ledgers (customer_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description) 
                                 VALUES (?, date('now'), 'payment', ?, 0.0, ?, ?, ?)");
        $desc = "Payment received via " . strtoupper($method) . ($ref_no ? " (Ref: {$ref_no})" : "");
        $stmtLed->execute([$customer_id, $receiptNo, $amount, $newBalance, $desc]);

        // If invoice specified, update invoice paid amount
        if ($invoice_id) {
            $inv = $db->query("SELECT * FROM sales_invoices WHERE id = {$invoice_id}")->fetch();
            if ($inv) {
                $newPaid = $inv['paid_amount'] + $amount;
                $newInvBal = max(0, $inv['net_amount'] - $newPaid);
                $status = $newInvBal <= 0 ? 'paid' : 'partial';
                $db->query("UPDATE sales_invoices SET paid_amount = {$newPaid}, balance_amount = {$newInvBal}, payment_status = '{$status}' WHERE id = {$invoice_id}");
            }
        }

        // Cash / Bank account balance update
        if ($method === 'cash') {
            $db->query("INSERT INTO cash_transactions (transaction_date, type, amount, source_module, reference_id, description, recorded_by) 
                        VALUES (date('now'), 'in', {$amount}, 'collections', '{$receiptNo}', 'Cash receipt from {$cust['business_name']}', {$currentUser['id']})");
        } else {
            // Update first active bank account
            $bankId = $db->query("SELECT id FROM bank_accounts WHERE is_active = 1 LIMIT 1")->fetchColumn();
            if ($bankId) {
                $db->query("UPDATE bank_accounts SET current_balance = current_balance + {$amount} WHERE id = {$bankId}");
                $db->query("INSERT INTO bank_transactions (bank_account_id, transaction_date, type, amount, reference_no, description, recorded_by) 
                            VALUES ({$bankId}, date('now'), 'deposit', {$amount}, '{$receiptNo}', 'Receipt deposit from {$cust['business_name']}', {$currentUser['id']})");
            }
        }

        $db->commit();
        Database::logAudit('COLLECTION', 'Finance', $receiptNo, "Recorded collection {$receiptNo} from {$cust['business_name']} - Rs. {$amount}");
        header("Location: collections.php?msg=payment_recorded&receipt_id={$receiptId}");
        exit;
    }
}

// Fetch Collections
$collections = $db->query("
    SELECT p.*, c.business_name as customer_name, c.phone as customer_phone,
           inv.invoice_number, u.full_name as receiver_name
    FROM payments_received p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN sales_invoices inv ON p.invoice_id = inv.id
    LEFT JOIN users u ON p.received_by = u.id
    ORDER BY p.id DESC
")->fetchAll();

$customers = $db->query("SELECT id, business_name, current_balance, credit_limit FROM customers WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
$invoices = $db->query("SELECT id, invoice_number, balance_amount, customer_id FROM sales_invoices WHERE payment_status != 'paid' ORDER BY id DESC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Customer Collections &amp; Payment Receipts</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Cash Receipts, Online Wire Transfers, Cheques &amp; Customer Ledger Settlements</p>
    </div>
    <button onclick="openModal('addPaymentModal')" class="btn btn-primary">+ Record Customer Payment</button>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'payment_recorded'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
        <span>✓ Payment recorded successfully! Customer account credited and banking balances updated.</span>
        <?php if (isset($_GET['receipt_id'])): ?>
            <a href="print_receipt.php?id=<?= (int)$_GET['receipt_id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Print Payment Receipt</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Payment Date</th>
                        <th>Pharmacy / Client</th>
                        <th>Invoice Reference</th>
                        <th>Payment Mode</th>
                        <th>Instrument Ref / Bank</th>
                        <th>Amount Collected</th>
                        <th>Receiver</th>
                        <th>Print Voucher</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($collections)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--slate-400); padding: 24px;">No customer collections recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($collections as $c): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($c['receipt_no']) ?></code></td>
                                <td><?= htmlspecialchars($c['payment_date']) ?></td>
                                <td><strong><?= htmlspecialchars($c['customer_name']) ?></strong></td>
                                <td>
                                    <?php if ($c['invoice_number']): ?>
                                        <a href="print_invoice.php?id=<?= $c['invoice_id'] ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                            <?= htmlspecialchars($c['invoice_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #64748b;">On Account Ledger</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $c['payment_method'] === 'cash' ? 'badge-success' : 'badge-primary' ?>">
                                        <?= strtoupper(str_replace('_', ' ', $c['payment_method'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($c['reference_no'] ?? '-') ?></div>
                                    <?php if ($c['bank_name']): ?>
                                        <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($c['bank_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 14px; color: #10b981;">Rs. <?= number_format($c['amount']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($c['receiver_name'] ?? 'Cashier') ?></td>
                                <td>
                                    <a href="print_receipt.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print Receipt">🖨️ Receipt</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record Payment -->
<div class="modal-overlay" id="addPaymentModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Record Customer Payment &amp; Collection</h3>
            <button class="modal-close" onclick="closeModal('addPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="collections.php">
            <input type="hidden" name="action" value="record_payment">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Customer / Pharmacy *</label>
                    <select name="customer_id" class="form-select" required>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['id'] ?>">
                                <?= htmlspecialchars($cust['business_name']) ?> (Outstanding: Rs. <?= number_format($cust['current_balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Payment Mode *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash Collection</option>
                            <option value="bank_transfer">Online Bank Transfer / IBFT</option>
                            <option value="cheque">Bank Cheque</option>
                            <option value="online">Debit / Credit Card / QR</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Collected Amount (PKR) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Instrument / Cheque / Trx #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. CHQ-991823 or Trx #">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Customer Bank Name (For Cheques)</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank, HBL">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Link to Specific Unpaid Invoice (Optional)</label>
                    <select name="invoice_id" class="form-select">
                        <option value="">-- Apply to Customer Ledger Balance --</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= $inv['id'] ?>">
                                <?= htmlspecialchars($inv['invoice_number']) ?> (Due: Rs. <?= number_format($inv['balance_amount']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Collection Remarks</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Collected by Rep Kamran Ali at pharmacy counter."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Collection &amp; Issue Receipt</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
