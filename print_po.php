<?php
// AA TRADERS - Purchase Order & Requisition Slip
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT po.*, s.name as supplier_name, s.contact_person, s.phone as supplier_phone,
           s.address as supplier_address, s.drug_license as supplier_license, s.ntn as supplier_ntn,
           w.name as warehouse_name, w.location as warehouse_location,
           u.full_name as author_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN warehouses w ON po.warehouse_id = w.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ?
");
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po) die("Purchase order not found.");

$stmtItems = $db->prepare("
    SELECT poi.*, p.name as product_name, p.generic_name, p.dosage_form, p.strength, p.pack_size
    FROM purchase_order_items poi
    JOIN products p ON poi.product_id = p.id
    WHERE poi.po_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - <?= htmlspecialchars($po['po_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; padding: 20px; }
        .box { max-width: 800px; margin: 0 auto; border: 1px solid #cbd5e1; padding: 25px; border-radius: 6px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #0f172a; color: #fff; padding: 8px; text-align: left; font-size: 12px; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .btn-bar { text-align: center; margin-bottom: 16px; }
        @media print { .btn-bar { display: none; } body { padding: 0; } .box { border: none; } }
    </style>
</head>
<body>
<div class="btn-bar">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 700;">🖨️ Print Purchase Order</button>
</div>
<div class="box">
    <div class="header">
        <div>
            <h1 style="margin: 0; font-size: 20px;"><?= htmlspecialchars($company['name'] ?? 'AA TRADERS') ?></h1>
            <p style="margin: 3px 0; color: #64748b; font-size: 11px;">Pharma Distribution &bull; Lic: <?= htmlspecialchars($company['drug_license_no'] ?? '') ?></p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; color: #0284c7; font-size: 18px;">Purchase Order</h2>
            <p style="margin: 3px 0; font-weight: 700;"><?= htmlspecialchars($po['po_number']) ?></p>
            <p style="margin: 2px 0; font-size: 11px;">Date: <?= htmlspecialchars($po['po_date']) ?></p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 12px; background: #f8fafc; padding: 10px; border-radius: 4px;">
        <div>
            <strong>Supplier / Manufacturer:</strong><br>
            <?= htmlspecialchars($po['supplier_name']) ?><br>
            <?= htmlspecialchars($po['supplier_address'] ?? '') ?><br>
            DRAP Lic: <?= htmlspecialchars($po['supplier_license'] ?? '-') ?> &bull; Phone: <?= htmlspecialchars($po['supplier_phone'] ?? '-') ?>
        </div>
        <div>
            <strong>Receiving Facility:</strong><br>
            <?= htmlspecialchars($po['warehouse_name']) ?><br>
            <?= htmlspecialchars($po['warehouse_location'] ?? '') ?><br>
            Authorized By: <?= htmlspecialchars($po['author_name'] ?? 'Procurement') ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product Formulation</th>
                <th>Packaging</th>
                <th style="text-align: right;">Order Qty</th>
                <th style="text-align: right;">Agreed Rate (PKR)</th>
                <th style="text-align: right;">Line Total (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong> (<?= htmlspecialchars($item['strength']) ?>)</td>
                    <td><?= htmlspecialchars($item['pack_size']) ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= $item['quantity'] ?></td>
                    <td style="text-align: right;">Rs. <?= number_format($item['unit_rate'], 2) ?></td>
                    <td style="text-align: right; font-weight: 700;">Rs. <?= number_format($item['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align: right; margin-top: 14px; font-size: 15px;">
        <strong>Total Order Value: Rs. <?= number_format($po['net_amount'], 2) ?></strong>
    </div>

    <div style="margin-top: 24px; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 10px;">
        <strong>Quality Compliance Stipulation:</strong> All supplied batches must have minimum 75% residual shelf-life upon receipt and conform to DRAP registration specifications.
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 11px; text-align: center;">
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Procurement Manager</div>
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">General Manager (Approval)</div>
        <div style="width: 160px; border-top: 1px solid #000; padding-top: 4px;">Supplier Acceptance</div>
    </div>
</div>
</body>
</html>
