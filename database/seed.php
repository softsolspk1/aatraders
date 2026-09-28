<?php
// AA TRADERS - PDMS Initial Seeder

function seedDatabase(PDO $pdo): void {
    // 1. Company Settings
    $stmt = $pdo->prepare("INSERT INTO company_settings (
        name, trade_name, ntn, strn, drug_license_no, address, city, phone, email, website, 
        bank_name, bank_account_title, bank_account_no, iban, invoice_footer_note
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'AA TRADERS',
        'AA Traders Pharmaceutical & Healthcare Distribution',
        '7294812-4',
        '3277876123456',
        'DRAP-DL-KHI-40912/2024',
        'Suite 402-405, Pharma Trade Centre, Main Shahrah-e-Faisal',
        'Karachi',
        '+92 21 34567890 / +92 300 1234567',
        'info@aatraders.pk',
        'www.aatraders.pk',
        'Meezan Bank Ltd (Pharma Centre Branch)',
        'AA TRADERS PHARMA DISTRIBUTION',
        '02010103489234',
        'PK65MEZN0002010103489234',
        'Goods once sold according to DRAP regulations cannot be returned without original batch verification.'
    ]);

    // 2. Users
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $users = [
        ['admin', $password, 'Super Administrator', 'admin@aatraders.pk', '+92 300 1111111', 'super_admin'],
        ['sales_mgr', $password, 'Tariq Mehmood', 'sales@aatraders.pk', '+92 300 2222222', 'sales_manager'],
        ['warehouse_mgr', $password, 'Rashid Khan', 'warehouse@aatraders.pk', '+92 300 3333333', 'warehouse_manager'],
        ['accounts_mgr', $password, 'Farhan Siddiqui', 'accounts@aatraders.pk', '+92 300 4444444', 'accounts_manager'],
        ['kamran_rep', $password, 'Kamran Ali (Rep South)', 'kamran@aatraders.pk', '+92 300 5555555', 'sales_rep'],
        ['bilal_rep', $password, 'Bilal Ahmed (Rep East)', 'bilal@aatraders.pk', '+92 300 6666666', 'sales_rep'],
        ['auditor', $password, 'Sarah Malik (Auditor)', 'auditor@aatraders.pk', '+92 300 7777777', 'auditor'],
    ];
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmt->execute($u);
    }

    // 3. Branches
    $branches = [
        ['Head Office Karachi', 'HOKHI', 'Karachi', 'Shahrah-e-Faisal, Karachi', '+92 21 34567890', 1],
        ['Regional Office Lahore', 'ROLHR', 'Lahore', 'Gulberg III, Lahore', '+92 42 35789123', 0],
        ['Regional Office Islamabad', 'ROISB', 'Islamabad', 'Blue Area, Islamabad', '+92 51 2891234', 0]
    ];
    $stmt = $pdo->prepare("INSERT INTO branches (name, code, city, address, phone, is_head_office) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($branches as $b) {
        $stmt->execute($b);
    }

    // 4. Warehouses
    $warehouses = [
        [1, 'Main Central Warehouse Karachi', 'WH-MAIN-KHI', 'main', 'Korangi Industrial Area, Karachi', 'Rashid Khan'],
        [1, 'Cold Storage & Biologics Facility', 'WH-COLD-KHI', 'cold_storage', 'Korangi Industrial Area (2°C-8°C)', 'Dr. Asim Raza'],
        [1, 'Quarantine Inspection Bay', 'WH-QUAR-KHI', 'quarantine', 'Gate 2, Korangi Facility', 'Zahid Hussain'],
        [1, 'Damaged Stock Area', 'WH-DMG-KHI', 'damaged', 'Section D, Korangi Facility', 'Rashid Khan'],
        [1, 'Expired Stock Storage', 'WH-EXP-KHI', 'expired', 'Disposal Cell B, Korangi Facility', 'Rashid Khan'],
        [2, 'Lahore Regional Warehouse', 'WH-MAIN-LHR', 'secondary', 'Multan Road Industrial Estate, Lahore', 'Naveed Akhtar']
    ];
    $stmt = $pdo->prepare("INSERT INTO warehouses (branch_id, name, code, type, location, manager_name) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($warehouses as $w) {
        $stmt->execute($w);
    }

    // 5. Territories & Areas
    $territories = [
        ['Sindh', 'Karachi', 'Karachi South (Clifton/Saddar)', 'TERR-KHI-S'],
        ['Sindh', 'Karachi', 'Karachi East (Gulshan/Jauhar)', 'TERR-KHI-E'],
        ['Sindh', 'Karachi', 'Karachi Central (Nazimabad/FB Area)', 'TERR-KHI-C'],
        ['Punjab', 'Lahore', 'Lahore Central (Gulberg/Model Town)', 'TERR-LHR-C']
    ];
    $stmt = $pdo->prepare("INSERT INTO territories (region, city, territory_name, code) VALUES (?, ?, ?, ?)");
    foreach ($territories as $t) {
        $stmt->execute($t);
    }

    $areas = [
        [1, 'Clifton Block 1-9'],
        [1, 'Defence (DHA Phases 1-8)'],
        [1, 'Saddar & Medical Lane'],
        [2, 'Gulshan-e-Iqbal Block 1-14'],
        [2, 'Gulistan-e-Jauhar'],
        [3, 'North Nazimabad Blocks A-N'],
        [4, 'Gulberg Main Boulevard']
    ];
    $stmt = $pdo->prepare("INSERT INTO areas (territory_id, area_name) VALUES (?, ?)");
    foreach ($areas as $a) {
        $stmt->execute($a);
    }

    // 6. Categories & Therapeutic Classes
    $categories = [
        ['Antibiotics & Antimicrobials', 'Broad and narrow spectrum bacterial infection therapeutics'],
        ['Analgesics & NSAIDs', 'Pain relievers, antipyretics and anti-inflammatory medications'],
        ['Cardiovascular & Antihypertensive', 'Cardiac care, blood pressure, cholesterol therapeutics'],
        ['Gastrointestinal & PPIs', 'Acidity, ulcers, reflux, digestive system drugs'],
        ['Anti-diabetic & Endocrine', 'Insulins, oral hypoglycemics and metabolic regulators'],
        ['Respiratory & Anti-allergy', 'Antihistamines, bronchodilators and cough syrups'],
        ['Vitamins & Nutritional Supplements', 'Multivitamins, minerals, calcium supplements'],
        ['Critical Care & Injectables', 'Emergency, IV fluids, anesthetic and biological injectables']
    ];
    $stmt = $pdo->prepare("INSERT INTO product_categories (name, description) VALUES (?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }

    $therapeutics = [
        ['Beta-lactam Antibiotics', 'Penicillins and cephalosporin derivatives'],
        ['Proton Pump Inhibitors (PPI)', 'Gastric acid secretion inhibitors'],
        ['Non-selective COX Inhibitors', 'Pain, fever and swelling relief'],
        ['H1 Receptor Antagonists', 'Non-sedating allergic relief'],
        ['Human Insulin & Analogues', 'Basal and bolus insulin therapy'],
        ['Fluoroquinolones', 'Gram negative bacterial treatments'],
        ['Sulfonylureas', 'Pancreatic beta cell stimulators']
    ];
    $stmt = $pdo->prepare("INSERT INTO therapeutic_classes (name, description) VALUES (?, ?)");
    foreach ($therapeutics as $th) {
        $stmt->execute($th);
    }

    // 7. Manufacturers
    $manufacturers = [
        ['GlaxoSmithKline (GSK) Pakistan Ltd', 'MFG-GSK', 'Shahid Minhas', '+92 21 111-475-725', 'info@gsk.com.pk', 'F-268, S.I.T.E., Karachi', '0709841-1', 'DRAP-LIC-0001'],
        ['Abbott Laboratories (Pakistan) Ltd', 'MFG-ABT', 'Fauzia Qadir', '+92 21 111-222-688', 'contact@abbott.pk', 'Landhi Industrial Area, Karachi', '0812349-3', 'DRAP-LIC-0004'],
        ['Getz Pharma (Pvt) Ltd', 'MFG-GTZ', 'M. Tariq Aziz', '+92 21 38670000', 'info@getzpharma.com', 'Plot 29-30/27, K.I.A., Karachi', '1423891-9', 'DRAP-LIC-0008'],
        ['The Searle Company Ltd', 'MFG-SRL', 'Zubair Shah', '+92 21 35688001', 'info@searlecompany.com', 'F-208, S.I.T.E., Karachi', '0928374-2', 'DRAP-LIC-0012'],
        ['Hilton Pharma (Pvt) Ltd', 'MFG-HLT', 'Owais Ahmed', '+92 21 35064601', 'info@hiltonpharma.com', 'Progressive Plaza, Beaumont Rd, Karachi', '1827364-5', 'DRAP-LIC-0019'],
        ['Sanofi-Aventis Pakistan Ltd', 'MFG-SNF', 'Adeel Akhtar', '+92 21 111-726-634', 'info@sanofi.com.pk', 'Plot 23, Sector 22, K.I.A., Karachi', '0638291-7', 'DRAP-LIC-0025'],
        ['Novo Nordisk Pharma', 'MFG-NVO', 'Dr. Danish Khan', '+92 21 35378201', 'info@novonordisk.pk', 'Clifton, Karachi', '2019283-8', 'DRAP-LIC-0038']
    ];
    $stmt = $pdo->prepare("INSERT INTO manufacturers (name, code, contact_person, phone, email, address, ntn, drug_license) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($manufacturers as $m) {
        $stmt->execute($m);
    }

    // 8. Suppliers
    $suppliers = [
        ['SUP-GSK', 'GlaxoSmithKline Pakistan Distribution', 'Shahid Minhas', '+92 21 111-475-725', 'orders@gsk.com.pk', 'SITE Industrial Area', 'Karachi', '0709841-1', '32778760001', 'DRAP-D-01', 30, 5000000.0, 0.0, 480000.0],
        ['SUP-GTZ', 'Getz Pharma Distribution Hub', 'M. Tariq Aziz', '+92 21 38670000', 'supply@getzpharma.com', 'Korangi Sector 27', 'Karachi', '1423891-9', '32778760002', 'DRAP-D-02', 45, 4000000.0, 0.0, 320000.0],
        ['SUP-ABT', 'Abbott Laboratories Direct Supply', 'Fauzia Qadir', '+92 21 111-222-688', 'orders.pk@abbott.com', 'Landhi Industrial Zone', 'Karachi', '0812349-3', '32778760003', 'DRAP-D-03', 30, 4500000.0, 0.0, 210000.0],
        ['SUP-SRL', 'Searle Distribution Services', 'Zubair Shah', '+92 21 35688001', 'trade@searlecompany.com', 'S.I.T.E. Area', 'Karachi', '0928374-2', '32778760004', 'DRAP-D-04', 30, 3000000.0, 0.0, 150000.0],
        ['SUP-HLT', 'Hilton Pharma Supply Chain', 'Owais Ahmed', '+92 21 35064601', 'commercial@hiltonpharma.com', 'Korangi Industrial Area', 'Karachi', '1827364-5', '32778760005', 'DRAP-D-05', 30, 2500000.0, 0.0, 95000.0]
    ];
    $stmt = $pdo->prepare("INSERT INTO suppliers (code, name, contact_person, phone, email, address, city, ntn, strn, drug_license, payment_terms_days, credit_limit, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($suppliers as $s) {
        $stmt->execute($s);
    }

    // 9. Sales Representatives
    $reps = [
        [5, 'REP-001', 'Kamran Ali', '+92 300 5555555', 'kamran@aatraders.pk', 1, 3500000.0, 2.5],
        [6, 'REP-002', 'Bilal Ahmed', '+92 300 6666666', 'bilal@aatraders.pk', 2, 2800000.0, 2.0],
        [null, 'REP-003', 'Zeeshan Raza', '+92 300 8888888', 'zeeshan@aatraders.pk', 3, 2500000.0, 2.0]
    ];
    $stmt = $pdo->prepare("INSERT INTO sales_representatives (user_id, employee_code, name, phone, email, territory_id, monthly_target, commission_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($reps as $r) {
        $stmt->execute($r);
    }

    // 10. Customers
    $customers = [
        ['CUST-001', 'Al-Shifa Medicos & Superstore', 'Sheikh Tariq', 'pharmacy', '+92 21 35871234', 'alshifa@gmail.com', 'Shop 4-5, Block 2, Clifton', 'Karachi', 1, 1, 1, '4109823-1', '32778769901', 'DL-KHI-8912', 600000.0, 30, 0.0, 185000.0, 0, null],
        ['CUST-002', 'City Medicos & Chemist', 'Farooq Ahmed', 'pharmacy', '+92 21 32724567', 'citymedicos@yahoo.com', 'Main Saddar Medical Market', 'Karachi', 1, 3, 1, '5298104-3', '32778769902', 'DL-KHI-4012', 500000.0, 30, 0.0, 92000.0, 0, null],
        ['CUST-003', 'Aga Khan Hospital Pharmacy Services', 'Dr. Rehan Munir', 'hospital', '+92 21 34930051', 'pharmacy@aku.edu', 'National Stadium Road', 'Karachi', 2, 4, 2, '0711928-5', '32778769903', 'DL-KHI-0015', 2000000.0, 45, 0.0, 420000.0, 0, null],
        ['CUST-004', 'Green Cross Chemist & Clinic', 'Dr. Shakeel Asghar', 'clinic', '+92 21 34988771', 'greencross@hotmail.com', 'Block 6, Gulshan-e-Iqbal', 'Karachi', 2, 4, 2, '6102938-2', '32778769904', 'DL-KHI-7721', 400000.0, 30, 0.0, 84500.0, 0, null],
        ['CUST-005', 'Liaquat National Hospital Med Store', 'M. Danish', 'hospital', '+92 21 111-456-456', 'lnh.procurement@lnh.edu.pk', 'Stadium Road', 'Karachi', 2, 4, 2, '0819283-7', '32778769905', 'DL-KHI-0028', 1500000.0, 45, 0.0, 310000.0, 0, null],
        ['CUST-006', 'Medix Superstore & Pharmacy', 'Khurram Jamil', 'pharmacy', '+92 21 36641290', 'medix.khi@gmail.com', 'Block H, North Nazimabad', 'Karachi', 3, 6, 3, '7109283-4', '32778769906', 'DL-KHI-3392', 300000.0, 30, 0.0, 290000.0, 0, 'Near credit limit ceiling'],
        ['CUST-007', 'Medicare Medical Store', 'Nadeem Ghafoor', 'pharmacy', '+92 21 34612345', 'medicare.jauhar@gmail.com', 'Block 13, Gulistan-e-Jauhar', 'Karachi', 2, 5, 2, '8291038-1', '32778769907', 'DL-KHI-9941', 150000.0, 21, 0.0, 168000.0, 1, 'Credit limit of Rs. 150,000 exceeded. Invoices overdue > 45 days.']
    ];
    $stmt = $pdo->prepare("INSERT INTO customers (
        code, business_name, owner_name, customer_type, phone, email, address, city, 
        territory_id, area_id, sales_rep_id, ntn, strn, drug_license, credit_limit, credit_days, 
        opening_balance, current_balance, is_blocked, block_reason
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($customers as $c) {
        $stmt->execute($c);
    }

    // 11. Products
    $products = [
        ['PRD-001', 'Panadol 500mg Tablets', 'Paracetamol', 'Panadol', 2, 3, 1, 'Tablet', '500mg', '20x10s (200 Tablets)', 'Box', '89640001001', 'GTIN-001', 'SKU-PAN-500', 0.0, 0, 50, 1000, 100, 50],
        ['PRD-002', 'Augmentin 625mg Tablets', 'Amoxicillin + Clavulanic Acid', 'Augmentin', 1, 1, 1, 'Tablet', '625mg', '2x7s (14 Tablets)', 'Box', '89640001002', 'GTIN-002', 'SKU-AUG-625', 0.0, 1, 30, 600, 60, 30],
        ['PRD-003', 'Risek 40mg Capsules', 'Omeprazole', 'Risek', 4, 2, 3, 'Capsule', '40mg', '14 Capsules', 'Box', '89640001003', 'GTIN-003', 'SKU-RSK-40', 0.0, 1, 40, 800, 80, 40],
        ['PRD-004', 'Brufen 400mg Tablets', 'Ibuprofen', 'Brufen', 2, 3, 2, 'Tablet', '400mg', '30x10s (300 Tablets)', 'Box', '89640001004', 'GTIN-004', 'SKU-BRF-400', 0.0, 0, 30, 500, 50, 25],
        ['PRD-005', 'Softin 10mg Tablets', 'Loratadine', 'Softin', 6, 4, 5, 'Tablet', '10mg', '10x10s (100 Tablets)', 'Box', '89640001005', 'GTIN-005', 'SKU-SFT-10', 0.0, 0, 25, 400, 50, 20],
        ['PRD-006', 'Flagyl 400mg Tablets', 'Metronidazole', 'Flagyl', 1, 1, 6, 'Tablet', '400mg', '20x10s (200 Tablets)', 'Box', '89640001006', 'GTIN-006', 'SKU-FLG-400', 0.0, 1, 30, 500, 60, 30],
        ['PRD-007', 'Novorapid Flexpen 100 U/ml', 'Insulin Aspart', 'Novorapid', 5, 5, 7, 'Injection', '100 U/ml', '5x3ml Prefilled Pens', 'Pack', '89640001011', 'GTIN-011', 'SKU-NVR-100', 0.0, 1, 15, 200, 30, 15],
        ['PRD-008', 'Rocephin 1g IV Injection', 'Ceftriaxone Sodium', 'Rocephin', 1, 1, 4, 'Injection', '1g Vial + Solvent', '1 Vial Pack', 'Pack', '89640001012', 'GTIN-012', 'SKU-ROC-1G', 0.0, 1, 20, 300, 40, 20],
        ['PRD-009', 'Getryl 2mg Tablets', 'Glimepiride', 'Getryl', 5, 7, 3, 'Tablet', '2mg', '30 Tablets', 'Box', '89640001010', 'GTIN-010', 'SKU-GTR-2MG', 0.0, 1, 25, 400, 45, 20],
        ['PRD-010', 'Gravinate 50mg Tablets', 'Dimenhydrinate', 'Gravinate', 4, 4, 4, 'Tablet', '50mg', '10x10s (100 Tablets)', 'Box', '89640001009', 'GTIN-009', 'SKU-GRV-50', 0.0, 0, 20, 350, 40, 20]
    ];
    $stmt = $pdo->prepare("INSERT INTO products (
        code, name, generic_name, brand_name, category_id, therapeutic_id, manufacturer_id, 
        dosage_form, strength, pack_size, unit, barcode, gtin, sku, tax_rate, is_prescription, 
        min_stock, max_stock, reorder_level, safety_stock
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $stmt->execute($p);
    }

    // 12. Product Batches with Expiry Dates (Near expiry, fresh, expired)
    // Local date baseline: 2026-09-28
    $batches = [
        // Panadol
        [1, 1, 'PAN-2601', '2025-10-01', '2027-10-31', 370.00, 420.00, 480.00, 500, 420, 10, 0, 0, 'BAR-PAN-01', 'active'],
        // Panadol near-expiry (< 30 days: 2026-10-18)
        [1, 1, 'PAN-2498', '2024-10-15', '2026-10-18', 370.00, 420.00, 480.00, 100, 35, 0, 0, 0, 'BAR-PAN-02', 'active'],
        // Augmentin
        [2, 1, 'AUG-9081', '2025-12-01', '2027-11-30', 255.00, 290.00, 335.00, 350, 280, 15, 0, 0, 'BAR-AUG-01', 'active'],
        // Augmentin near-expiry (< 90 days: 2026-11-25)
        [2, 1, 'AUG-8820', '2024-11-20', '2026-11-25', 255.00, 290.00, 335.00, 150, 45, 0, 0, 0, 'BAR-AUG-02', 'active'],
        // Risek 40mg
        [3, 1, 'RSK-4109', '2026-01-10', '2028-01-31', 340.00, 385.00, 445.00, 400, 320, 20, 0, 0, 'BAR-RSK-01', 'active'],
        // Risek Expired batch in Expired Bay (Expired 2026-08-15)
        [3, 5, 'RSK-3920', '2024-08-10', '2026-08-15', 340.00, 385.00, 445.00, 50, 0, 0, 0, 18, 'BAR-RSK-EXP', 'expired'],
        // Brufen 400mg
        [4, 1, 'BRF-7741', '2025-08-01', '2027-08-31', 480.00, 540.00, 620.00, 300, 210, 10, 0, 0, 'BAR-BRF-01', 'active'],
        // Softin 10mg
        [5, 1, 'SFT-3012', '2025-09-01', '2027-09-30', 185.00, 210.00, 245.00, 350, 260, 0, 0, 0, 'BAR-SFT-01', 'active'],
        // Flagyl 400mg
        [6, 1, 'FLG-5510', '2025-11-01', '2027-11-30', 215.00, 245.00, 285.00, 400, 310, 5, 0, 0, 'BAR-FLG-01', 'active'],
        // Novorapid (Cold storage warehouse ID 2)
        [7, 2, 'NVR-9921', '2026-02-01', '2027-04-30', 1280.00, 1450.00, 1650.00, 120, 85, 0, 0, 0, 'BAR-NVR-01', 'active'],
        // Rocephin 1g
        [8, 1, 'ROC-8114', '2025-12-15', '2027-12-31', 780.00, 890.00, 1020.00, 200, 140, 10, 0, 0, 'BAR-ROC-01', 'active'],
        // Getryl 2mg
        [9, 1, 'GTR-1190', '2025-10-01', '2027-10-31', 190.00, 220.00, 255.00, 300, 240, 0, 0, 0, 'BAR-GTR-01', 'active'],
        // Gravinate
        [10, 1, 'GRV-4421', '2025-07-01', '2027-07-31', 160.00, 185.00, 215.00, 250, 195, 0, 0, 0, 'BAR-GRV-01', 'active']
    ];
    $stmt = $pdo->prepare("INSERT INTO product_batches (
        product_id, warehouse_id, batch_number, mfg_date, expiry_date, purchase_price, 
        trade_price, mrp_retail_price, quantity_received, quantity_available, quantity_reserved, 
        quantity_damaged, quantity_expired, barcode, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($batches as $b) {
        $stmt->execute($b);
    }

    // 13. Schemes (Pharma Bonus / Quantity Schemes)
    $schemes = [
        ['Panadol Special Monsoon Scheme (10+1 Free)', 'bonus_qty', 1, 10, 1, 0.0, '2026-07-01', '2026-12-31', 1],
        ['Risek Pharmacy Incentive Scheme (20+2 Free)', 'bonus_qty', 3, 20, 2, 0.0, '2026-08-01', '2026-12-31', 1],
        ['Augmentin Institutional Discount 5%', 'percentage_discount', 2, 50, 0, 5.0, '2026-09-01', '2026-11-30', 1],
        ['Brufen Trade Bonus (15+1 Free)', 'bonus_qty', 4, 15, 1, 0.0, '2026-08-15', '2026-10-31', 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO schemes (name, scheme_type, product_id, buy_qty, free_qty, discount_pct, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($schemes as $sch) {
        $stmt->execute($sch);
    }

    // 14. Sample Invoices & Ledger Entries
    $invoices = [
        ['INV-2026-0101', '2026-09-24', '2026-10-24', 1, 1, 1, 46200.0, 0.0, 0.0, 0.0, 0.0, 46200.0, 46200.0, 0.0, 'paid', 'delivered'],
        ['INV-2026-0102', '2026-09-26', '2026-10-26', 2, 1, 1, 92000.0, 0.0, 0.0, 0.0, 0.0, 92000.0, 0.0, 92000.0, 'unpaid', 'dispatched'],
        ['INV-2026-0103', '2026-09-27', '2026-10-27', 3, 2, 1, 245000.0, 0.0, 0.0, 0.0, 0.0, 245000.0, 100000.0, 145000.0, 'partial', 'packed'],
        ['INV-2026-0104', '2026-09-28', '2026-10-28', 4, 2, 1, 84500.0, 0.0, 0.0, 0.0, 0.0, 84500.0, 0.0, 84500.0, 'unpaid', 'picked']
    ];
    $stmt = $pdo->prepare("INSERT INTO sales_invoices (
        invoice_number, invoice_date, due_date, customer_id, sales_rep_id, warehouse_id, 
        subtotal, scheme_discount, trade_discount, cash_discount, tax_amount, net_amount, 
        paid_amount, balance_amount, payment_status, dispatch_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($invoices as $inv) {
        $stmt->execute($inv);
    }

    // Invoice items for INV-2026-0101
    $stmt = $pdo->prepare("INSERT INTO sales_invoice_items (invoice_id, product_id, batch_id, quantity, bonus_quantity, unit_trade_price, mrp, discount_amount, tax_amount, net_total) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 1, 1, 60, 6, 420.00, 480.00, 0.0, 0.0, 25200.0]); // 60 + 6 bonus
    $stmt->execute([1, 2, 3, 50, 0, 290.00, 335.00, 0.0, 0.0, 14500.0]);
    $stmt->execute([1, 4, 7, 12, 0, 540.00, 620.00, 0.0, 0.0, 6480.0]);

    // Customer Ledgers
    $stmt = $pdo->prepare("INSERT INTO customer_ledgers (customer_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, '2026-09-01', 'opening', 'OP-BAL', 0.0, 0.0, 0.0, 'Opening Balance for FY 2026-27']);
    $stmt->execute([1, '2026-09-24', 'invoice', 'INV-2026-0101', 46200.0, 0.0, 46200.0, 'Sales Invoice INV-2026-0101']);
    $stmt->execute([1, '2026-09-25', 'payment', 'REC-001', 0.0, 46200.0, 0.0, 'Cash collection received against INV-2026-0101']);
    $stmt->execute([1, '2026-09-26', 'invoice', 'INV-2026-0099', 185000.0, 0.0, 185000.0, 'Sales Invoice INV-2026-0099']);

    $stmt->execute([2, '2026-09-26', 'invoice', 'INV-2026-0102', 92000.0, 0.0, 92000.0, 'Sales Invoice INV-2026-0102']);
    $stmt->execute([3, '2026-09-20', 'invoice', 'INV-2026-0085', 275000.0, 0.0, 275000.0, 'Monthly supply invoice']);
    $stmt->execute([3, '2026-09-27', 'invoice', 'INV-2026-0103', 245000.0, 0.0, 520000.0, 'Special biological order INV-2026-0103']);
    $stmt->execute([3, '2026-09-27', 'payment', 'REC-002', 0.0, 100000.0, 420000.0, 'Cheque #881290 received via Kamran Ali']);

    // Payments Received
    $stmt = $pdo->prepare("INSERT INTO payments_received (receipt_no, payment_date, customer_id, invoice_id, payment_method, reference_no, bank_name, amount, notes, received_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['REC-2026-001', '2026-09-25', 1, 1, 'cash', 'CSH-0925-1', null, 46200.0, 'Full payment on delivery', 1]);
    $stmt->execute(['REC-2026-002', '2026-09-27', 3, 3, 'cheque', 'CHQ-881290', 'Habib Bank Ltd', 100000.0, 'Partial payment against INV-2026-0103', 1]);

    // Supplier Ledger sample
    $stmt = $pdo->prepare("INSERT INTO supplier_ledgers (supplier_id, transaction_date, transaction_type, reference_no, debit, credit, balance, description) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, '2026-09-10', 'purchase', 'GRN-2026-001', 0.0, 480000.0, 480000.0, 'Goods Received GRN-2026-001 (Panadol & Augmentin)']);
    $stmt->execute([2, '2026-09-15', 'purchase', 'GRN-2026-002', 0.0, 320000.0, 320000.0, 'Goods Received GRN-2026-002 (Risek & Getryl)']);

    // Bank Accounts
    $stmt = $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_title, account_number, iban, branch_name, opening_balance, current_balance) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Meezan Bank Ltd', 'AA TRADERS PHARMA OPERATION', '02010103489234', 'PK65MEZN0002010103489234', 'Pharma Trade Centre Br.', 1500000.0, 2450000.0]);
    $stmt->execute(['Habib Bank Ltd', 'AA TRADERS REVENUE ACCOUNT', '00427901582903', 'PK36HABB0000427901582903', 'Shahrah-e-Faisal Br.', 800000.0, 1120000.0]);

    // Expenses
    $expenses = [
        ['2026-09-25', 'fuel', 18500.0, 'cash', 'PET-01', 'Van diesel for South Karachi distribution routes', 1],
        ['2026-09-26', 'electricity', 45000.0, 'bank_transfer', 'KE-99120', 'K-Electric bill for Korangi Main Warehouse Cold Storage', 1],
        ['2026-09-27', 'vehicle_maintenance', 12000.0, 'cash', 'MAINT-04', 'Routine servicing for Suzuki Bolan delivery vans', 1],
        ['2026-09-28', 'salaries', 285000.0, 'bank_transfer', 'SAL-SEP-26', 'Mid-month staff advances & warehouse team allowances', 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO expenses (expense_date, category, amount, payment_method, reference_no, description, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($expenses as $exp) {
        $stmt->execute($exp);
    }

    // Sales Targets
    $stmt = $pdo->prepare("INSERT INTO sales_targets (sales_rep_id, target_year, target_month, target_amount, achieved_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 2026, 9, 3500000.0, 3120000.0, 'in_progress']); // 89% achievement
    $stmt->execute([2, 2026, 9, 2800000.0, 2450000.0, 'in_progress']); // 87.5% achievement
    $stmt->execute([3, 2026, 9, 2500000.0, 1850000.0, 'in_progress']); // 74% achievement

    // Notifications
    $notifications = [
        ['CRITICAL EXPIRY ALERT', 'Batch PAN-2498 (Panadol 500mg) has 35 units expiring in 20 days (2026-10-18). Immediate clearance recommended!', 'danger', 'batches.php?filter=expiring_30'],
        ['CREDIT CEILING REACHED', 'Customer Medicare Medical Store has exceeded credit limit (Rs. 168,000 / 150,000). Account auto-blocked.', 'warning', 'customers.php?id=7'],
        ['QUARANTINE VERIFICATION', 'Batch RSK-3920 (18 units) has officially passed expiration date and was moved to Expired Stock Bay.', 'info', 'warehouses.php'],
        ['NEW SCHEME ACTIVE', 'Panadol Special Monsoon Scheme (10+1 Free) is active for all South & East territory pharmacies.', 'success', 'schemes.php']
    ];
    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type, link) VALUES (?, ?, ?, ?)");
    foreach ($notifications as $n) {
        $stmt->execute($n);
    }

    // Initial Audit Log
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, ip_address, action, module, record_id, details) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'admin', '127.0.0.1', 'SYSTEM_INITIALIZATION', 'System', 'ALL', 'AA TRADERS Pharmaceutical Distribution ERP initialized successfully with master datasets.']);
}
