<?php
// AA TRADERS - Sidebar Navigation
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar no-print">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">AA</div>
        <div class="sidebar-brand-text">
            <h1>AA TRADERS</h1>
            <p>PHARMA DISTRIBUTION ERP</p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- 1. Executive -->
        <div class="nav-section-title">Core & Intelligence</div>
        <a href="index.php" class="nav-item <?= $currentPage === 'index.php' || $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>
        <a href="ai_copilot.php" class="nav-item <?= $currentPage === 'ai_copilot.php' ? 'active' : '' ?>">
            <span class="icon">🧠</span>
            <span>AI Pharma Copilot</span>
            <span class="nav-badge" style="background: rgba(56, 189, 248, 0.2); color: #38bdf8;">Smart</span>
        </a>

        <!-- 2. Master Data & Inventory -->
        <div class="nav-section-title">Product & Batches</div>
        <a href="products.php" class="nav-item <?= $currentPage === 'products.php' ? 'active' : '' ?>">
            <span class="icon">💊</span>
            <span>Product Master</span>
        </a>
        <a href="batches.php" class="nav-item <?= $currentPage === 'batches.php' ? 'active' : '' ?>">
            <span class="icon">🏷️</span>
            <span>Batch Management (FEFO)</span>
        </a>
        <a href="schemes.php" class="nav-item <?= $currentPage === 'schemes.php' ? 'active' : '' ?>">
            <span class="icon">🎁</span>
            <span>Pharma Schemes (10+1)</span>
        </a>
        <a href="inventory.php" class="nav-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
            <span class="icon">📦</span>
            <span>Inventory & Valuation</span>
        </a>
        <a href="warehouses.php" class="nav-item <?= $currentPage === 'warehouses.php' ? 'active' : '' ?>">
            <span class="icon">🏢</span>
            <span>Warehouses & Transfers</span>
        </a>

        <!-- 3. Sales & Distribution -->
        <div class="nav-section-title">Sales & Distribution</div>
        <a href="customers.php" class="nav-item <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <span class="icon">🏥</span>
            <span>Customers & Credit</span>
        </a>
        <a href="sales_orders.php" class="nav-item <?= $currentPage === 'sales_orders.php' ? 'active' : '' ?>">
            <span class="icon">📝</span>
            <span>Sales Orders</span>
        </a>
        <a href="sales_invoices.php" class="nav-item <?= $currentPage === 'sales_invoices.php' ? 'active' : '' ?>">
            <span class="icon">🧾</span>
            <span>Sales Invoices</span>
        </a>
        <a href="sales_returns.php" class="nav-item <?= $currentPage === 'sales_returns.php' ? 'active' : '' ?>">
            <span class="icon">↩️</span>
            <span>Sales Returns</span>
        </a>
        <a href="picking_dispatch.php" class="nav-item <?= $currentPage === 'picking_dispatch.php' ? 'active' : '' ?>">
            <span class="icon">🚚</span>
            <span>Picking & Dispatch</span>
        </a>
        <a href="sales_reps.php" class="nav-item <?= $currentPage === 'sales_reps.php' ? 'active' : '' ?>">
            <span class="icon">💼</span>
            <span>Reps, Targets & Visits</span>
        </a>
        <a href="territories.php" class="nav-item <?= $currentPage === 'territories.php' ? 'active' : '' ?>">
            <span class="icon">🗺️</span>
            <span>Territories & Regions</span>
        </a>

        <!-- 4. Procurement -->
        <div class="nav-section-title">Purchases & Inward</div>
        <a href="suppliers.php" class="nav-item <?= $currentPage === 'suppliers.php' ? 'active' : '' ?>">
            <span class="icon">🏭</span>
            <span>Suppliers Master</span>
        </a>
        <a href="purchases.php" class="nav-item <?= $currentPage === 'purchases.php' ? 'active' : '' ?>">
            <span class="icon">📥</span>
            <span>Purchase Orders & GRN</span>
        </a>
        <a href="purchase_returns.php" class="nav-item <?= $currentPage === 'purchase_returns.php' ? 'active' : '' ?>">
            <span class="icon">📤</span>
            <span>Purchase Returns</span>
        </a>
        <a href="forecasting.php" class="nav-item <?= $currentPage === 'forecasting.php' ? 'active' : '' ?>">
            <span class="icon">🔮</span>
            <span>Reorder & Forecasting</span>
        </a>

        <!-- 5. Accounts & Finance -->
        <div class="nav-section-title">Finance & Ledgers</div>
        <a href="collections.php" class="nav-item <?= $currentPage === 'collections.php' ? 'active' : '' ?>">
            <span class="icon">💵</span>
            <span>Payments & Collections</span>
        </a>
        <a href="accounts.php" class="nav-item <?= $currentPage === 'accounts.php' ? 'active' : '' ?>">
            <span class="icon">📑</span>
            <span>Customer/Supplier Ledgers</span>
        </a>
        <a href="cash_bank.php" class="nav-item <?= $currentPage === 'cash_bank.php' ? 'active' : '' ?>">
            <span class="icon">🏦</span>
            <span>Cashbook & Banking</span>
        </a>
        <a href="expenses.php" class="nav-item <?= $currentPage === 'expenses.php' ? 'active' : '' ?>">
            <span class="icon">📉</span>
            <span>Operating Expenses</span>
        </a>
        <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <span class="icon">📈</span>
            <span>Executive Reports</span>
        </a>

        <!-- 6. Quality & Compliance -->
        <div class="nav-section-title">Compliance & Quality</div>
        <a href="expiry.php" class="nav-item <?= $currentPage === 'expiry.php' ? 'active' : '' ?>">
            <span class="icon">⏳</span>
            <span>Expiry Management</span>
        </a>
        <a href="recall.php" class="nav-item <?= $currentPage === 'recall.php' ? 'active' : '' ?>">
            <span class="icon">🚨</span>
            <span>Product Recall Matrix</span>
        </a>
        <a href="stocktaking.php" class="nav-item <?= $currentPage === 'stocktaking.php' ? 'active' : '' ?>">
            <span class="icon">📋</span>
            <span>Physical Stocktaking</span>
        </a>
        <a href="audit_logs.php" class="nav-item <?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">
            <span class="icon">🛡️</span>
            <span>Audit Trail</span>
        </a>

        <!-- 7. Administration -->
        <div class="nav-section-title">Settings & System</div>
        <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span class="icon">⚙️</span>
            <span>Company Settings</span>
        </a>
        <a href="backup.php" class="nav-item <?= $currentPage === 'backup.php' ? 'active' : '' ?>">
            <span class="icon">💾</span>
            <span>SQLite Backup & Restore</span>
        </a>
    </nav>
</aside>
