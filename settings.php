<?php
// AA TRADERS - Company Settings & Compliance Profile
$pageTitle = 'Company Settings';
require_once __DIR__ . '/includes/header.php';

Auth::requireRole(['super_admin', 'company_admin']);

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
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Company Profile &amp; Regulatory Credentials</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Corporate Entity Details, DRAP Drug Distribution License, Tax Registrations &amp; Invoice Settings</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Company profile and legal distribution credentials updated successfully.
    </div>
<?php endif; ?>

<div class="card" style="max-width: 900px;">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
