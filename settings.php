<?php
// AA TRADERS - Company Settings & Compliance Profile
$pageTitle = 'Company Settings';
require_once __DIR__ . '/includes/header.php';

Auth::requireRole(['super_admin', 'company_admin']);

$actionResult = null;

// Handle Demo Data Purge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purge_demo_data') {
    Auth::requireRole(['super_admin']);
    $mode = $_POST['purge_mode'] ?? 'all';
    $confirm = strtoupper(trim($_POST['confirm_delete'] ?? ''));

    if ($confirm !== 'DELETE') {
        $actionResult = [
            'success' => false,
            'message' => 'Confirmation keyword mismatch. Please type "DELETE" in capital letters to confirm.'
        ];
    } else {
        $actionResult = Database::purgeDemoData($mode);
    }
}

// Handle Demo Data Reseed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reseed_demo_data') {
    Auth::requireRole(['super_admin']);
    $actionResult = Database::reseedDemoData();
}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $name = trim($_POST['name']);
    $trade_name = trim($_POST['trade_name']);
    $ntn = trim($_POST['ntn']);
    $strn = trim($_POST['strn']);
    $drug_license_no = trim($_POST['drug_license_no']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    $bank_name = trim($_POST['bank_name']);
    $bank_account_title = trim($_POST['bank_account_title']);
    $bank_account_no = trim($_POST['bank_account_no']);
    $iban = trim($_POST['iban']);
    $invoice_footer_note = trim($_POST['invoice_footer_note']);

    $stmt = $db->prepare("UPDATE company_settings SET 
        name = ?, trade_name = ?, ntn = ?, strn = ?, drug_license_no = ?, 
        address = ?, city = ?, phone = ?, email = ?, website = ?, 
        bank_name = ?, bank_account_title = ?, bank_account_no = ?, iban = ?, 
        invoice_footer_note = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = 1");
    $stmt->execute([
        $name, $trade_name, $ntn, $strn, $drug_license_no,
        $address, $city, $phone, $email, $website,
        $bank_name, $bank_account_title, $bank_account_no, $iban,
        $invoice_footer_note
    ]);

    Database::logAudit('UPDATE_SETTINGS', 'Settings', 'COMPANY', "Updated company profile & DRAP license credentials.");
    header('Location: settings.php?msg=saved');
    exit;
}

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();

// Live demo data counts
$prodCount = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$batchCount = (int)$db->query("SELECT COUNT(*) FROM product_batches")->fetchColumn();
$custCount = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$suppCount = (int)$db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$invCount = (int)$db->query("SELECT COUNT(*) FROM sales_invoices")->fetchColumn();
$orderCount = (int)$db->query("SELECT COUNT(*) FROM sales_orders")->fetchColumn();
$payCount = (int)$db->query("SELECT COUNT(*) FROM payments_received")->fetchColumn();
$expCount = (int)$db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Company Profile &amp; Regulatory Credentials</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Corporate Entity Details, DRAP Drug Distribution License, Tax Registrations &amp; System Reset</p>
    </div>
</div>

<?php if ($actionResult): ?>
    <div style="background: <?= $actionResult['success'] ? 'var(--success-light)' : 'var(--danger-light)' ?>; color: <?= $actionResult['success'] ? 'var(--success)' : 'var(--danger)' ?>; border: 1px solid <?= $actionResult['success'] ? '#bbf7d0' : '#fecaca' ?>; padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 18px;"><?= $actionResult['success'] ? '✓' : '⚠️' ?></span>
        <span><?= htmlspecialchars($actionResult['message']) ?></span>
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Company profile and legal distribution credentials updated successfully.
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr; gap: 24px; max-width: 960px;">

    <!-- Company Settings Card -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🏢 Enterprise Distribution Credentials</span>
        </div>
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="save_settings">
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Company Corporate Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($company['name'] ?? 'AA TRADERS') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trade / Commercial Name</label>
                        <input type="text" name="trade_name" class="form-control" value="<?= htmlspecialchars($company['trade_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">DRAP Drug Distribution License # *</label>
                        <input type="text" name="drug_license_no" class="form-control" value="<?= htmlspecialchars($company['drug_license_no'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Federal NTN (National Tax Number) *</label>
                        <input type="text" name="ntn" class="form-control" value="<?= htmlspecialchars($company['ntn'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sales Tax Registration # (STRN)</label>
                        <input type="text" name="strn" class="form-control" value="<?= htmlspecialchars($company['strn'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Corporate Phone Numbers *</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Principal Office / Warehouse Address *</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($company['address'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($company['city'] ?? 'Karachi') ?>" required>
                    </div>
                </div>

                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--slate-700); margin: 20px 0 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                    🏦 Commercial Banking Profile (Displayed on Invoices)
                </h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Corporate Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($company['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Title</label>
                        <input type="text" name="bank_account_title" class="form-control" value="<?= htmlspecialchars($company['bank_account_title'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account_no" class="form-control" value="<?= htmlspecialchars($company['bank_account_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">IBAN Number</label>
                        <input type="text" name="iban" class="form-control" value="<?= htmlspecialchars($company['iban'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label class="form-label">Standard Tax Invoice Terms &amp; Regulatory Note</label>
                    <textarea name="invoice_footer_note" class="form-control" rows="2"><?= htmlspecialchars($company['invoice_footer_note'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Save Company Profile</button>
            </div>
        </form>
    </div>

    <!-- Demo Data Purge & System Reset Card -->
    <div class="card" style="border: 1px solid #fca5a5;">
        <div class="card-header" style="background: #fff1f2; border-bottom: 1px solid #fecdd3; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">🧹</span>
                <span class="card-title" style="color: #991b1b;">Delete Demo Data &amp; Reset System</span>
            </div>
            <span style="font-size: 11px; background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 9999px; font-weight: 700; border: 1px solid #fecaca;">
                Super Admin Only
            </span>
        </div>
        <div class="card-body">
            <p style="font-size: 13.5px; color: var(--slate-600); margin-bottom: 16px;">
                Use this utility when transitioning from testing/evaluation into real-world production operations. You can selectively wipe demo transactions or delete all demo records to start with a pristine, empty system.
            </p>

            <!-- Current Dataset Statistics -->
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 14px 18px; margin-bottom: 20px;">
                <div style="font-size: 12px; font-weight: 700; color: var(--slate-500); text-transform: uppercase; margin-bottom: 8px;">
                    Current Active Records in Database
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; font-size: 12.5px;">
                    <div><span style="color: var(--slate-500);">Products:</span> <strong><?= $prodCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Batches:</span> <strong><?= $batchCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Customers:</span> <strong><?= $custCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Suppliers:</span> <strong><?= $suppCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Sales Invoices:</span> <strong><?= $invCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Sales Orders:</span> <strong><?= $orderCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Collections:</span> <strong><?= $payCount ?></strong></div>
                    <div><span style="color: var(--slate-500);">Expenses:</span> <strong><?= $expCount ?></strong></div>
                </div>
            </div>

            <form method="POST" action="settings.php" onsubmit="return confirmPurge(event);">
                <input type="hidden" name="action" value="purge_demo_data">

                <div style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Choose Purge Level:</label>
                    
                    <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff;">
                            <input type="radio" name="purge_mode" value="all" checked style="margin-top: 3px;">
                            <div>
                                <strong style="color: #991b1b; font-size: 13.5px;">Delete All Demo Data (Complete Clean Slate &bull; Recommended for Fresh Setup)</strong>
                                <p style="font-size: 12px; color: var(--slate-500); margin-top: 2px;">
                                    Permanently wipes all demo products, batches, schemes, customers, suppliers, sales reps, demo users, invoices, orders, payments, ledgers, and inventory movements. Preserves your Super Admin credentials (<code style="background:#f1f5f9; padding:2px 4px; border-radius:3px;">admin</code>), branch settings, and company credentials so you can start entering real inventory.
                                </p>
                            </div>
                        </label>

                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff;">
                            <input type="radio" name="purge_mode" value="transactions_only" style="margin-top: 3px;">
                            <div>
                                <strong style="color: #0369a1; font-size: 13.5px;">Delete Demo Transactions Only (Keep Master Catalog)</strong>
                                <p style="font-size: 12px; color: var(--slate-500); margin-top: 2px;">
                                    Wipes all sales invoices, orders, returns, payments, ledgers, and expenses. Resets all customer/supplier ledger balances to 0 and batch inventory quantities to 0, while keeping the product master list and customer directories intact.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="background: #fff7ed; border: 1px solid #ffedd5; padding: 14px 16px; border-radius: 6px; margin-bottom: 18px;">
                    <label class="form-label" style="color: #c2410c; font-weight: 700;">
                        Type <code style="background: #fed7aa; padding: 2px 6px; border-radius: 4px; color: #7c2d12;">DELETE</code> to confirm:
                    </label>
                    <input type="text" name="confirm_delete" id="confirmDeleteInput" class="form-control" placeholder="Type DELETE here..." autocomplete="off" required style="max-width: 280px; border-color: #fb923c;">
                    <span style="font-size: 11.5px; color: #9a3412; display: block; margin-top: 4px;">
                        This action cannot be undone. Always download an active database backup first from the <a href="backup.php" style="color: #c2410c; font-weight: 600; text-decoration: underline;">Backup Page</a>.
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-weight: 700;">
                        🗑️ Delete Demo Data Now
                    </button>
            </form>

            <!-- Optional Re-seed Action for Evaluators -->
            <form method="POST" action="settings.php" onsubmit="return confirm('Restore all default pharmaceutical demo datasets (Panadol, Augmentin, Karachi pharmacies, demo invoices)?');">
                <input type="hidden" name="action" value="reseed_demo_data">
                <button type="submit" class="btn btn-secondary" style="font-size: 12px;" title="Re-populate demo data if testing">
                    🔄 Restore Demo Datasets
                </button>
            </form>
                </div>
        </div>
    </div>

</div>

<script>
function confirmPurge(e) {
    const input = document.getElementById('confirmDeleteInput');
    if (!input || input.value.trim().toUpperCase() !== 'DELETE') {
        alert('Please type "DELETE" in the confirmation field to proceed.');
        if (input) input.focus();
        e.preventDefault();
        return false;
    }
    return confirm('ARE YOU ABSOLUTELY SURE?\n\nThis will permanently delete the selected demo records from AA TRADERS database. Click OK to proceed with data purge.');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
