<?php
// AA TRADERS - Professional Pharmaceutical Tax Invoice Printout
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

// Fetch invoice with customer, rep, and warehouse
$stmt = $db->prepare("
    SELECT i.*, 
           c.business_name as customer_name, c.owner_name, c.address as customer_address, 
           c.city as customer_city, c.phone as customer_phone, c.drug_license as customer_license,
           c.ntn as customer_ntn, c.strn as customer_strn, c.current_balance as customer_total_outstanding,
           r.name as rep_name, r.phone as rep_phone,
           w.name as warehouse_name,
           u.full_name as biller_name
    FROM sales_invoices i
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN sales_representatives r ON i.sales_rep_id = r.id
    JOIN warehouses w ON i.warehouse_id = w.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

// Fetch invoice line items with batch and expiry
$stmtItems = $db->prepare("
    SELECT ii.*, p.name as product_name, p.generic_name, p.dosage_form, p.strength, p.pack_size, p.barcode,
           pb.batch_number, pb.expiry_date
    FROM sales_invoice_items ii
    JOIN products p ON ii.product_id = p.id
    JOIN product_batches pb ON ii.batch_id = pb.id
    WHERE ii.invoice_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

// Company Settings
$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice - <?= htmlspecialchars($invoice['invoice_number']) ?> - AA TRADERS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            padding: 20px;
            margin: 0;
        }
        .invoice-box {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .brand h1 {
            margin: 0;
            font-size: 24px;
            color: #0f172a;
            font-weight: 800;
        }
        .brand p {
            margin: 3px 0;
            font-size: 11.5px;
            color: #475569;
        }
        .invoice-badge {
            text-align: right;
        }
        .invoice-badge h2 {
            margin: 0;
            color: #0284c7;
            font-size: 20px;
            text-transform: uppercase;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 14px 18px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .details-col h4 {
            margin: 0 0 6px;
            font-size: 12px;
            text-transform: uppercase;
            color: #0284c7;
        }
        .details-col p {
            margin: 2px 0;
            font-size: 12.5px;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items-table th {
            background: #0f172a;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 11.5px;
            text-transform: uppercase;
            text-align: left;
        }
        table.items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }
        table.items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .summary-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .bank-info {
            font-size: 11.5px;
            background: #f1f5f9;
            padding: 10px 14px;
            border-radius: 6px;
            max-width: 380px;
        }
        .totals-table {
            width: 320px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 12.5px;
        }
        .totals-table tr.grand-total td {
            border-top: 2px solid #0284c7;
            font-size: 15px;
            font-weight: 800;
            color: #0284c7;
            padding-top: 8px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            font-size: 11px;
            text-align: center;
        }
        .sig-line {
            width: 180px;
            border-top: 1px solid #64748b;
            padding-top: 4px;
        }
        .print-btn-bar {
            text-align: center;
            margin-bottom: 16px;
        }
        .btn {
            background: #0284c7;
            color: #fff;
            padding: 9px 18px;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
        }
        @media print {
            body { padding: 0; background: #fff; }
            .invoice-box { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .print-btn-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button class="btn" onclick="window.print()">🖨️ Print Official Tax Invoice</button>
    <button class="btn" style="background: #475569;" onclick="window.close()">Close Window</button>
</div>

<div class="invoice-box">
    <!-- Header -->
    <div class="header">
        <div class="brand">
            <h1><?= htmlspecialchars($company['name'] ?? 'AA TRADERS') ?></h1>
            <p><strong><?= htmlspecialchars($company['trade_name'] ?? 'Pharmaceutical Distribution ERP') ?></strong></p>
            <p><?= htmlspecialchars($company['address'] ?? '') ?>, <?= htmlspecialchars($company['city'] ?? '') ?></p>
            <p>DRAP License: <strong><?= htmlspecialchars($company['drug_license_no'] ?? '') ?></strong> &bull; NTN: <strong><?= htmlspecialchars($company['ntn'] ?? '') ?></strong></p>
            <p>Phone: <?= htmlspecialchars($company['phone'] ?? '') ?> &bull; Email: <?= htmlspecialchars($company['email'] ?? '') ?></p>
        </div>
        <div class="invoice-badge">
            <h2>Sales Tax Invoice</h2>
            <p style="font-size: 15px; font-weight: 700; margin: 4px 0;">#<?= htmlspecialchars($invoice['invoice_number']) ?></p>
            <p style="margin: 2px 0;">Date: <strong><?= htmlspecialchars($invoice['invoice_date']) ?></strong></p>
            <p style="margin: 2px 0;">Due Date: <strong><?= htmlspecialchars($invoice['due_date'] ?? 'Immediate') ?></strong></p>
            <div style="margin-top: 6px; font-family: monospace; font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; display: inline-block;">
                STATUS: <?= strtoupper($invoice['payment_status']) ?>
            </div>
        </div>
    </div>

    <!-- Client & Dispatch Details -->
    <div class="details-grid">
        <div class="details-col">
            <h4>Billed To (Licensed Purchaser):</h4>
            <p style="font-size: 14px; font-weight: 700;"><?= htmlspecialchars($invoice['customer_name']) ?></p>
            <p>Proprietor: <?= htmlspecialchars($invoice['owner_name'] ?? 'Pharmacist In-charge') ?></p>
            <p>Address: <?= htmlspecialchars($invoice['customer_address']) ?>, <?= htmlspecialchars($invoice['customer_city']) ?></p>
            <p>DRAP Drug License: <strong><?= htmlspecialchars($invoice['customer_license'] ?? 'Verified') ?></strong></p>
            <p>NTN / STRN: <?= htmlspecialchars($invoice['customer_ntn'] ?: 'Unregistered') ?></p>
            <p>Phone / WhatsApp: <?= htmlspecialchars($invoice['customer_phone'] ?? '-') ?></p>
        </div>
        <div class="details-col">
            <h4>Fulfillment &amp; Field Force:</h4>
            <p>Dispatch Facility: <strong><?= htmlspecialchars($invoice['warehouse_name']) ?></strong></p>
            <p>Assigned Territory Rep: <strong><?= htmlspecialchars($invoice['rep_name'] ?? 'Direct Corporate') ?></strong></p>
            <p>Payment Terms: <strong>Credit 30 Days</strong></p>
            <p>Dispatch Status: <strong><?= strtoupper($invoice['dispatch_status']) ?></strong></p>
            <p>Billed By: <?= htmlspecialchars($invoice['biller_name'] ?? 'Billing Desk') ?></p>
        </div>
    </div>

    <!-- Line Items Table with FEFO Batches & Bonus Schemes -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Product Formulation &amp; Generic</th>
                <th>Batch #</th>
                <th>Exp Date</th>
                <th style="text-align: right;">Qty</th>
                <th style="text-align: right;">Bonus</th>
                <th style="text-align: right;">TP (PKR)</th>
                <th style="text-align: right;">MRP (PKR)</th>
                <th style="text-align: right;">Disc</th>
                <th style="text-align: right;">Net Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($items as $item): 
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                        <div style="font-size: 10.5px; color: #64748b;"><?= htmlspecialchars($item['generic_name']) ?> &bull; <?= htmlspecialchars($item['strength']) ?></div>
                    </td>
                    <td><code><?= htmlspecialchars($item['batch_number']) ?></code></td>
                    <td><?= htmlspecialchars($item['expiry_date']) ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= $item['quantity'] ?></td>
                    <td style="text-align: right; color: #10b981; font-weight: 700;">
                        <?= $item['bonus_quantity'] > 0 ? "+{$item['bonus_quantity']} Free" : '-' ?>
                    </td>
                    <td style="text-align: right;">Rs. <?= number_format($item['unit_trade_price'], 2) ?></td>
                    <td style="text-align: right;">Rs. <?= number_format($item['mrp'], 2) ?></td>
                    <td style="text-align: right; color: #10b981;">
                        <?= $item['discount_amount'] > 0 ? '- Rs. ' . number_format($item['discount_amount'], 2) : '0' ?>
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        Rs. <?= number_format($item['net_total'], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Summary & Banking -->
    <div class="summary-box">
        <div class="bank-info">
            <h4 style="margin: 0 0 4px; color: #0f172a;">Direct Bank Settlement Instructions:</h4>
            <p style="margin: 2px 0;">Bank: <strong><?= htmlspecialchars($company['bank_name'] ?? 'Meezan Bank Ltd') ?></strong></p>
            <p style="margin: 2px 0;">Title: <strong><?= htmlspecialchars($company['bank_account_title'] ?? 'AA TRADERS') ?></strong></p>
            <p style="margin: 2px 0;">Account: <code><?= htmlspecialchars($company['bank_account_no'] ?? '-') ?></code></p>
            <p style="margin: 2px 0;">IBAN: <code><?= htmlspecialchars($company['iban'] ?? '-') ?></code></p>
            <p style="margin: 6px 0 0; font-size: 10.5px; color: #64748b;">
                * Please quote invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> in your payment description.
            </p>
        </div>

        <table class="totals-table">
            <tr>
                <td>Subtotal (Gross TP):</td>
                <td style="text-align: right;"><strong>Rs. <?= number_format($invoice['subtotal'], 2) ?></strong></td>
            </tr>
            <?php if ($invoice['scheme_discount'] > 0): ?>
                <tr>
                    <td style="color: #10b981;">Scheme Incentive Discount:</td>
                    <td style="text-align: right; color: #10b981;"><strong>- Rs. <?= number_format($invoice['scheme_discount'], 2) ?></strong></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td>Sales Tax / Federal Levy:</td>
                <td style="text-align: right;">Rs. <?= number_format($invoice['tax_amount'], 2) ?></td>
            </tr>
            <tr class="grand-total">
                <td>Net Invoice Payable:</td>
                <td style="text-align: right;">Rs. <?= number_format($invoice['net_amount'], 2) ?></td>
            </tr>
            <tr>
                <td style="padding-top: 10px; font-size: 11.5px; color: #64748b;">Amount Paid:</td>
                <td style="padding-top: 10px; text-align: right; color: #10b981; font-weight: 700;">Rs. <?= number_format($invoice['paid_amount'], 2) ?></td>
            </tr>
            <tr>
                <td style="font-size: 12px; color: #dc2626; font-weight: 700;">Outstanding Invoice Due:</td>
                <td style="text-align: right; color: #dc2626; font-weight: 700;">Rs. <?= number_format($invoice['balance_amount'], 2) ?></td>
            </tr>
        </table>
    </div>

    <!-- Regulatory Footer -->
    <div style="font-size: 11px; color: #64748b; line-height: 1.4; border-top: 1px solid #e2e8f0; padding-top: 10px;">
        <strong>Regulatory DRAP Compliance Notice:</strong> <?= htmlspecialchars($company['invoice_footer_note'] ?? 'Medicines sold are warranted under Drug Act 1976. Keep in recommended storage temperature.') ?>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div>
            <div class="sig-line">Prepared &amp; Verified By</div>
            <div style="color: #64748b; margin-top: 2px;">Billing Executive</div>
        </div>
        <div>
            <div class="sig-line">Warehouse Dispensed / FEFO Checked</div>
            <div style="color: #64748b; margin-top: 2px;">Warehouse In-charge</div>
        </div>
        <div>
            <div class="sig-line">Received in Sound Condition</div>
            <div style="color: #64748b; margin-top: 2px;">Pharmacist / Store Stamp</div>
        </div>
    </div>
</div>

</body>
</html>
