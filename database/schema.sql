-- AA TRADERS - Pharmaceutical Distribution Management System (PDMS)
-- SQLite Database Schema

PRAGMA foreign_keys = ON;

-- 1. Users and Authentication
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    role TEXT NOT NULL DEFAULT 'sales_rep', -- super_admin, company_admin, general_manager, sales_manager, warehouse_manager, purchase_manager, accounts_manager, sales_rep, warehouse_staff, accountant, cashier, auditor
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Company & Branch Settings
CREATE TABLE IF NOT EXISTS company_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    trade_name TEXT,
    ntn TEXT,
    strn TEXT,
    drug_license_no TEXT,
    address TEXT,
    city TEXT,
    country TEXT DEFAULT 'Pakistan',
    phone TEXT,
    email TEXT,
    website TEXT,
    bank_name TEXT,
    bank_account_title TEXT,
    bank_account_no TEXT,
    iban TEXT,
    invoice_footer_note TEXT,
    currency_symbol TEXT DEFAULT 'Rs.',
    currency_code TEXT DEFAULT 'PKR',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS branches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT UNIQUE NOT NULL,
    city TEXT NOT NULL,
    address TEXT,
    phone TEXT,
    is_head_office INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS warehouses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER,
    name TEXT NOT NULL,
    code TEXT UNIQUE NOT NULL,
    type TEXT NOT NULL DEFAULT 'main', -- main, secondary, cold_storage, quarantine, damaged, expired
    location TEXT,
    manager_name TEXT,
    is_active INTEGER DEFAULT 1,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- 3. Territories & Geographic Hierarchy
CREATE TABLE IF NOT EXISTS territories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    region TEXT NOT NULL, -- e.g., Sindh, Punjab, Federal
    city TEXT NOT NULL,   -- e.g., Karachi, Lahore, Islamabad
    territory_name TEXT NOT NULL, -- e.g., Karachi South, Clifton, Gulshan
    code TEXT UNIQUE
);

CREATE TABLE IF NOT EXISTS areas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    territory_id INTEGER NOT NULL,
    area_name TEXT NOT NULL,
    FOREIGN KEY (territory_id) REFERENCES territories(id)
);

-- 4. Product Categories, Therapeutics, Manufacturers
CREATE TABLE IF NOT EXISTS product_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS therapeutic_classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS manufacturers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT UNIQUE,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    ntn TEXT,
    drug_license TEXT,
    is_active INTEGER DEFAULT 1
);

-- 5. Product Master
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    generic_name TEXT,
    brand_name TEXT,
    category_id INTEGER,
    therapeutic_id INTEGER,
    manufacturer_id INTEGER,
    dosage_form TEXT, -- Tablet, Capsule, Syrup, Injection, Cream, Drops, Suspension, Ointment
    strength TEXT,    -- e.g., 500mg, 10mg/ml, 1g
    pack_size TEXT,   -- e.g., 20 tablets, 1 vial, 120ml
    unit TEXT DEFAULT 'Box', -- Box, Pack, Strip, Bottle, Ampoule
    barcode TEXT,
    gtin TEXT,
    sku TEXT,
    tax_category TEXT DEFAULT 'exempt', -- exempt, standard, reduced
    tax_rate REAL DEFAULT 0.0,
    is_prescription INTEGER DEFAULT 1,
    min_stock INTEGER DEFAULT 10,
    max_stock INTEGER DEFAULT 1000,
    reorder_level INTEGER DEFAULT 25,
    safety_stock INTEGER DEFAULT 15,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id),
    FOREIGN KEY (therapeutic_id) REFERENCES therapeutic_classes(id),
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id)
);

-- 6. Product Batches (FEFO Inventory Core)
CREATE TABLE IF NOT EXISTS product_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    warehouse_id INTEGER NOT NULL,
    batch_number TEXT NOT NULL,
    mfg_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    purchase_price REAL NOT NULL DEFAULT 0.0,  -- Purchase rate per pack
    trade_price REAL NOT NULL DEFAULT 0.0,     -- Distributor Trade Price (TP)
    mrp_retail_price REAL NOT NULL DEFAULT 0.0,-- Maximum Retail Price (MRP)
    quantity_received INTEGER DEFAULT 0,
    quantity_available INTEGER DEFAULT 0,
    quantity_reserved INTEGER DEFAULT 0,
    quantity_damaged INTEGER DEFAULT 0,
    quantity_expired INTEGER DEFAULT 0,
    barcode TEXT,
    status TEXT DEFAULT 'active', -- active, quarantined, recalled, expired
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);
CREATE INDEX IF NOT EXISTS idx_batch_expiry ON product_batches(expiry_date);
CREATE INDEX IF NOT EXISTS idx_batch_product ON product_batches(product_id);

-- 7. Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    city TEXT,
    ntn TEXT,
    strn TEXT,
    drug_license TEXT,
    payment_terms_days INTEGER DEFAULT 30,
    credit_limit REAL DEFAULT 0.0,
    opening_balance REAL DEFAULT 0.0,
    current_balance REAL DEFAULT 0.0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 8. Sales Representatives
CREATE TABLE IF NOT EXISTS sales_representatives (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    employee_code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    territory_id INTEGER,
    monthly_target REAL DEFAULT 0.0,
    commission_rate REAL DEFAULT 2.0, -- percentage
    manager_id INTEGER,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (territory_id) REFERENCES territories(id)
);

-- 9. Customers
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    business_name TEXT NOT NULL,
    owner_name TEXT,
    customer_type TEXT DEFAULT 'pharmacy', -- pharmacy, medical_store, hospital, clinic, institution, wholesaler
    phone TEXT,
    email TEXT,
    address TEXT,
    city TEXT,
    territory_id INTEGER,
    area_id INTEGER,
    sales_rep_id INTEGER,
    ntn TEXT,
    strn TEXT,
    drug_license TEXT,
    credit_limit REAL DEFAULT 100000.0,
    credit_days INTEGER DEFAULT 30,
    opening_balance REAL DEFAULT 0.0,
    current_balance REAL DEFAULT 0.0,
    is_blocked INTEGER DEFAULT 0,
    block_reason TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (territory_id) REFERENCES territories(id),
    FOREIGN KEY (area_id) REFERENCES areas(id),
    FOREIGN KEY (sales_rep_id) REFERENCES sales_representatives(id)
);

-- 10. Pharmaceutical Schemes & Discounts
CREATE TABLE IF NOT EXISTS schemes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    scheme_type TEXT NOT NULL DEFAULT 'bonus_qty', -- bonus_qty, percentage_discount
    product_id INTEGER,
    buy_qty INTEGER DEFAULT 10,
    free_qty INTEGER DEFAULT 1,
    discount_pct REAL DEFAULT 0.0,
    start_date DATE,
    end_date DATE,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 11. Purchases: Orders & Goods Receipt (GRN)
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_number TEXT UNIQUE NOT NULL,
    po_date DATE NOT NULL,
    supplier_id INTEGER NOT NULL,
    warehouse_id INTEGER NOT NULL,
    status TEXT DEFAULT 'pending', -- pending, approved, received, cancelled
    total_amount REAL DEFAULT 0.0,
    tax_amount REAL DEFAULT 0.0,
    net_amount REAL DEFAULT 0.0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    unit_rate REAL NOT NULL,
    total_amount REAL NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS goods_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grn_number TEXT UNIQUE NOT NULL,
    po_id INTEGER,
    supplier_id INTEGER NOT NULL,
    supplier_invoice_no TEXT,
    receipt_date DATE NOT NULL,
    warehouse_id INTEGER NOT NULL,
    total_amount REAL DEFAULT 0.0,
    status TEXT DEFAULT 'completed',
    notes TEXT,
    received_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grn_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_number TEXT NOT NULL,
    mfg_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    quantity_received INTEGER NOT NULL,
    free_quantity INTEGER DEFAULT 0,
    purchase_rate REAL NOT NULL,
    trade_price REAL NOT NULL,
    mrp REAL NOT NULL,
    total_amount REAL NOT NULL,
    FOREIGN KEY (grn_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS purchase_returns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_no TEXT UNIQUE NOT NULL,
    return_date DATE NOT NULL,
    supplier_id INTEGER NOT NULL,
    warehouse_id INTEGER NOT NULL,
    reason TEXT NOT NULL, -- damaged_stock, wrong_product, near_expiry, expired, recall, quality_issue, excess_stock
    total_amount REAL DEFAULT 0.0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS purchase_return_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    unit_cost REAL NOT NULL,
    total_amount REAL NOT NULL,
    FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

-- 12. Sales Orders & Invoices
CREATE TABLE IF NOT EXISTS sales_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    so_number TEXT UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    customer_id INTEGER NOT NULL,
    sales_rep_id INTEGER,
    warehouse_id INTEGER NOT NULL,
    status TEXT DEFAULT 'pending', -- pending, approved, picked, invoiced, cancelled
    total_amount REAL DEFAULT 0.0,
    discount_amount REAL DEFAULT 0.0,
    net_amount REAL DEFAULT 0.0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (sales_rep_id) REFERENCES sales_representatives(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS sales_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    so_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER,
    quantity INTEGER NOT NULL,
    bonus_quantity INTEGER DEFAULT 0,
    unit_trade_price REAL NOT NULL,
    discount_pct REAL DEFAULT 0.0,
    total_amount REAL NOT NULL,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

CREATE TABLE IF NOT EXISTS sales_invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    customer_id INTEGER NOT NULL,
    sales_rep_id INTEGER,
    warehouse_id INTEGER NOT NULL,
    so_id INTEGER,
    subtotal REAL DEFAULT 0.0,
    scheme_discount REAL DEFAULT 0.0,
    trade_discount REAL DEFAULT 0.0,
    cash_discount REAL DEFAULT 0.0,
    tax_amount REAL DEFAULT 0.0,
    net_amount REAL DEFAULT 0.0,
    paid_amount REAL DEFAULT 0.0,
    balance_amount REAL DEFAULT 0.0,
    payment_status TEXT DEFAULT 'unpaid', -- unpaid, partial, paid
    dispatch_status TEXT DEFAULT 'pending', -- pending, picked, packed, dispatched, delivered, returned
    driver_name TEXT,
    vehicle_no TEXT,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (sales_rep_id) REFERENCES sales_representatives(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (so_id) REFERENCES sales_orders(id)
);

CREATE TABLE IF NOT EXISTS sales_invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    bonus_quantity INTEGER DEFAULT 0,
    unit_trade_price REAL NOT NULL,
    mrp REAL NOT NULL,
    discount_amount REAL DEFAULT 0.0,
    tax_amount REAL DEFAULT 0.0,
    net_total REAL NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

CREATE TABLE IF NOT EXISTS sales_returns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_no TEXT UNIQUE NOT NULL,
    return_date DATE NOT NULL,
    invoice_id INTEGER,
    customer_id INTEGER NOT NULL,
    warehouse_id INTEGER NOT NULL,
    reason TEXT NOT NULL, -- expired, near_expiry, damaged, wrong_product, customer_rejection, recall, quality_issue
    total_amount REAL DEFAULT 0.0,
    refund_type TEXT DEFAULT 'credit_note', -- credit_note, cash
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS sales_return_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price REAL NOT NULL,
    total_amount REAL NOT NULL,
    FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

-- 13. Financial Transactions: Collections & Payments
CREATE TABLE IF NOT EXISTS payments_received (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_no TEXT UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    customer_id INTEGER NOT NULL,
    invoice_id INTEGER,
    payment_method TEXT DEFAULT 'cash', -- cash, bank_transfer, cheque, online
    reference_no TEXT,
    bank_name TEXT,
    cheque_date DATE,
    amount REAL NOT NULL,
    notes TEXT,
    received_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id)
);

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_no TEXT UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    supplier_id INTEGER NOT NULL,
    payment_method TEXT DEFAULT 'bank_transfer',
    reference_no TEXT,
    bank_name TEXT,
    amount REAL NOT NULL,
    notes TEXT,
    paid_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- 14. Customer & Supplier Ledgers
CREATE TABLE IF NOT EXISTS customer_ledgers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type TEXT NOT NULL, -- opening, invoice, payment, return, debit_note, credit_note
    reference_no TEXT,
    debit REAL DEFAULT 0.0,
    credit REAL DEFAULT 0.0,
    balance REAL DEFAULT 0.0,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS supplier_ledgers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type TEXT NOT NULL, -- opening, purchase, payment, return, debit_note, credit_note
    reference_no TEXT,
    debit REAL DEFAULT 0.0,
    credit REAL DEFAULT 0.0,
    balance REAL DEFAULT 0.0,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- 15. Inventory Movements, Transfers, Adjustments
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    warehouse_id INTEGER NOT NULL,
    transaction_type TEXT NOT NULL, -- purchase, sale, sales_return, purchase_return, transfer_in, transfer_out, adjustment_add, adjustment_sub, damage, expiry
    reference_type TEXT, -- grn, invoice, return, transfer, adjustment, recall
    reference_id TEXT,
    quantity INTEGER NOT NULL,
    unit_cost REAL DEFAULT 0.0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS stock_transfers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transfer_no TEXT UNIQUE NOT NULL,
    transfer_date DATE NOT NULL,
    from_warehouse_id INTEGER NOT NULL,
    to_warehouse_id INTEGER NOT NULL,
    status TEXT DEFAULT 'requested', -- requested, approved, dispatched, received, cancelled
    notes TEXT,
    requested_by INTEGER,
    approved_by INTEGER,
    dispatched_by INTEGER,
    received_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transfer_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    adjustment_no TEXT UNIQUE NOT NULL,
    adjustment_date DATE NOT NULL,
    warehouse_id INTEGER NOT NULL,
    reason TEXT NOT NULL, -- physical_count_difference, damage, expiry, shortage, excess
    notes TEXT,
    adjusted_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS stock_adjustment_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    adjustment_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    system_quantity INTEGER NOT NULL,
    physical_quantity INTEGER NOT NULL,
    difference_quantity INTEGER NOT NULL,
    unit_cost REAL NOT NULL,
    FOREIGN KEY (adjustment_id) REFERENCES stock_adjustments(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

-- 16. Expenses & Banking
CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_date DATE NOT NULL,
    category TEXT NOT NULL, -- fuel, salaries, rent, electricity, internet, vehicle_maintenance, warehouse_expenses, marketing, travel, office_expenses, miscellaneous
    amount REAL NOT NULL,
    payment_method TEXT DEFAULT 'cash',
    reference_no TEXT,
    description TEXT,
    recorded_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_name TEXT NOT NULL,
    account_title TEXT NOT NULL,
    account_number TEXT NOT NULL,
    iban TEXT,
    branch_name TEXT,
    opening_balance REAL DEFAULT 0.0,
    current_balance REAL DEFAULT 0.0,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS bank_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_account_id INTEGER NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type TEXT NOT NULL, -- deposit, withdrawal, transfer
    amount REAL NOT NULL,
    reference_no TEXT,
    description TEXT,
    recorded_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
);

-- 17. Product Recalls
CREATE TABLE IF NOT EXISTS product_recalls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recall_no TEXT UNIQUE NOT NULL,
    recall_date DATE NOT NULL,
    product_id INTEGER NOT NULL,
    batch_number TEXT NOT NULL,
    reason TEXT NOT NULL,
    priority TEXT DEFAULT 'high', -- critical, high, medium
    status TEXT DEFAULT 'initiated', -- initiated, in_progress, completed
    total_recalled_qty INTEGER DEFAULT 0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 18. Sales Targets & Visits
CREATE TABLE IF NOT EXISTS customer_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sales_rep_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    visit_date DATE NOT NULL,
    purpose TEXT,
    outcome TEXT,
    order_booked INTEGER DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sales_rep_id) REFERENCES sales_representatives(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS sales_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sales_rep_id INTEGER NOT NULL,
    target_year INTEGER NOT NULL,
    target_month INTEGER NOT NULL,
    target_amount REAL NOT NULL,
    achieved_amount REAL DEFAULT 0.0,
    status TEXT DEFAULT 'in_progress',
    FOREIGN KEY (sales_rep_id) REFERENCES sales_representatives(id)
);

-- 19. Deliveries & Order Picking
CREATE TABLE IF NOT EXISTS deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    delivery_no TEXT UNIQUE NOT NULL,
    invoice_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    driver_name TEXT,
    driver_phone TEXT,
    vehicle_no TEXT,
    dispatch_time DATETIME,
    delivery_time DATETIME,
    status TEXT DEFAULT 'pending', -- pending, picked, packed, in_transit, delivered, returned
    recipient_name TEXT,
    proof_notes TEXT,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- 20. Audit Trail & Notifications
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address TEXT,
    action TEXT NOT NULL, -- CREATE, UPDATE, DELETE, LOGIN, APPROVE, RECALL, ADJUSTMENT
    module TEXT NOT NULL,
    record_id TEXT,
    details TEXT
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info', -- danger, warning, info, success
    link TEXT,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
