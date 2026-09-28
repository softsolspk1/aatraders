<?php
// AA TRADERS - Payment Receipt Voucher Printout
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT p.*, c.business_name as customer_name, c.owner_name, c.address as customer_address, 
           c.phone as customer_phone, c.current_balance as remaining_balance,
           inv.invoice_number, u.full_name as cashier_name
    FROM payments_received p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN sales_invoices inv ON p.invoice_id = inv.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$pay = $stmt->fetch();

if (!$pay) die("Payment record not found.");
$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - <?= htmlspecialchars($pay['receipt_no']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; padding: 20px; }
        .box { max-width: 650px; margin: 0 auto; border: 1px solid #cbd5e1; padding: 25px; border-radius: 6px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #10b981; padding-bottom: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .btn-bar { text-align: center; margin-bottom: 16px; }
        @media print { .btn-bar { display: none; } body { padding: 0; } .box { border: none; } }
    </style>
</head>
<body>
<div class="btn-bar">
    <button onclick="window.print()" style="padding: 8px 16px; background: #10b981; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 700;">🖨️ Print Official Receipt</button>
</div>
<div class="box">
    <div class="header">
        <div>
            <h1 style="margin: 0; font-size: 20px;"><?= htmlspecialchars($company['name'] ?? 'AA TRADERS') ?></h1>
            <p style="margin: 2px 0; color: #64748b; font-size: 11px;"><?= htmlspecialchars($company['address'] ?? '') ?></p>
            <p style="margin: 2px 0; font-size: 11px;">NTN: <?= htmlspecialchars($company['ntn'] ?? '') ?> &bull; Phone: <?= htmlspecialchars($company['phone'] ?? '') ?></p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; color: #10b981; font-size: 18px;">Payment Receipt</h2>
            <p style="margin: 3px 0; font-weight: 700;">#<?= htmlspecialchars($pay['receipt_no']) ?></p>
            <p style="margin: 2px 0; font-size: 11px;">Date: <?= htmlspecialchars($pay['payment_date']) ?></p>
        </div>
    </div>

    <table>
        <tr>
            <td style="width: 160px; color: #64748b;">Received From:</td>
            <td><strong><?= htmlspecialchars($pay['customer_name']) ?></strong> (<?= htmlspecialchars($pay['owner_name'] ?? '') ?>)</td>
        </tr>
        <tr>
            <td style="color: #64748b;">Amount Received:</td>
            <td><strong style="font-size: 18px; color: #10b981;">Rs. <?= number_format($pay['amount'], 2) ?></strong></td>
        </tr>
        <tr>
            <td style="color: #64748b;">Payment Method:</td>
            <td><strong><?= strtoupper(str_replace('_', ' ', $pay['payment_method'])) ?></strong></td>
        </tr>
        <tr>
            <td style="color: #64748b;">Instrument / Trx Ref:</td>
            <td><?= htmlspecialchars($pay['reference_no'] ?? '-') ?> <?= $pay['bank_name'] ? "({$pay['bank_name']})" : '' ?></td>
        </tr>
        <tr>
            <td style="color: #64748b;">Applied Against:</td>
            <td><?= $pay['invoice_number'] ? "Invoice #{$pay['invoice_number']}" : "On-Account Running Balance" ?></td>
        </tr>
        <tr>
            <td style="color: #64748b;">Remaining Balance:</td>
            <td><strong>Rs. <?= number_format($pay['remaining_balance'], 2) ?></strong></td>
        </tr>
        <tr>
            <td style="color: #64748b;">Cashier / Receiver:</td>
            <td><?= htmlspecialchars($pay['cashier_name'] ?? 'Authorized Cashier') ?></td>
        </tr>
    </table>

    <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 11px; text-align: center;">
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Customer Signature / Stamp</div>
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Authorized Cashier Stamp</div>
    </div>
</div>
</body>
</html>
