<?php
// AA TRADERS - Delivery Challan & Gate Pass Printout
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT i.*, 
           c.business_name as customer_name, c.address as delivery_address, c.phone as customer_phone,
           w.name as warehouse_name
    FROM sales_invoices i
    JOIN customers c ON i.customer_id = c.id
    JOIN warehouses w ON i.warehouse_id = w.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) die("Invoice not found.");

$stmtItems = $db->prepare("
    SELECT ii.*, p.name as product_name, p.dosage_form, p.strength, p.pack_size,
           pb.batch_number, pb.expiry_date
    FROM sales_invoice_items ii
    JOIN products p ON ii.product_id = p.id
    JOIN product_batches pb ON ii.batch_id = pb.id
    WHERE ii.invoice_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Challan / Gate Pass - <?= htmlspecialchars($inv['invoice_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; padding: 20px; }
        .box { max-width: 800px; margin: 0 auto; border: 1px solid #cbd5e1; padding: 25px; border-radius: 6px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #0f172a; color: #fff; padding: 8px; text-align: left; font-size: 12px; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .btn-bar { text-align: center; margin-bottom: 16px; }
        @media print { .btn-bar { display: none; } body { padding: 0; } .box { border: none; } }
    </style>
</head>
<body>
<div class="btn-bar">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 700;">🖨️ Print Delivery Challan / Gate Pass</button>
</div>
<div class="box">
    <div class="header">
        <div>
            <h1 style="margin: 0; font-size: 20px;"><?= htmlspecialchars($company['name'] ?? 'AA TRADERS') ?></h1>
            <p style="margin: 2px 0; color: #64748b; font-size: 11px;">Pharma Logistics &bull; Lic: <?= htmlspecialchars($company['drug_license_no'] ?? '') ?></p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 18px; color: #0f172a;">Delivery Challan / Gate Pass</h2>
            <p style="margin: 3px 0; font-weight: 700;">Invoice Ref: <?= htmlspecialchars($inv['invoice_number']) ?></p>
            <p style="margin: 2px 0; font-size: 11px;">Date: <?= htmlspecialchars($inv['invoice_date']) ?></p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 12px; background: #f8fafc; padding: 10px; border-radius: 4px;">
        <div>
            <strong>Deliver To:</strong><br>
            <?= htmlspecialchars($inv['customer_name']) ?><br>
            <?= htmlspecialchars($inv['delivery_address']) ?><br>
            Phone: <?= htmlspecialchars($inv['customer_phone'] ?? '-') ?>
        </div>
        <div>
            <strong>Logistics &amp; Transport:</strong><br>
            Dispatching WH: <?= htmlspecialchars($inv['warehouse_name']) ?><br>
            Driver / Rider: <strong><?= htmlspecialchars($inv['driver_name'] ?? 'Warehouse Van Driver') ?></strong><br>
            Vehicle Reg: <strong><?= htmlspecialchars($inv['vehicle_no'] ?? 'Distribution Fleet') ?></strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product Formulation</th>
                <th>Batch Number</th>
                <th>Expiry Date</th>
                <th style="text-align: right;">Sale Qty</th>
                <th style="text-align: right;">Bonus Qty</th>
                <th style="text-align: right;">Total Dispatch Units</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; $totalUnits = 0; foreach ($items as $item): 
                $units = $item['quantity'] + $item['bonus_quantity'];
                $totalUnits += $units;
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong> (<?= htmlspecialchars($item['strength']) ?>)</td>
                    <td><code><?= htmlspecialchars($item['batch_number']) ?></code></td>
                    <td><?= htmlspecialchars($item['expiry_date']) ?></td>
                    <td style="text-align: right;"><?= $item['quantity'] ?></td>
                    <td style="text-align: right; color: #10b981; font-weight: 700;"><?= $item['bonus_quantity'] > 0 ? "+{$item['bonus_quantity']}" : '-' ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= $units ?> packs</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align: right; margin-top: 14px; font-size: 15px;">
        <strong>Total Packages Dispatched: <?= number_format($totalUnits) ?> Packs</strong>
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 11px; text-align: center;">
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Gate Security Cleared</div>
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Driver Dispatch Signature</div>
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Pharmacy Receiver Stamp</div>
    </div>
</div>
</body>
</html>
