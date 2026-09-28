# AA TRADERS &mdash; Pharmaceutical Distribution Management System (PDMS)

### Enterprise Web-Based ERP &bull; PHP + SQLite (PDO WAL) &bull; Multi-User &bull; Regulatory DRAP Compliant

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)
![Database](https://img.shields.io/badge/SQLite-WAL%20Mode-003B57.svg)
![Architecture](https://img.shields.io/badge/Architecture-Modular%20ERP-0284c7.svg)
![Compliance](https://img.shields.io/badge/Compliance-DRAP%20Validated-10b981.svg)

---

## 📌 Executive Overview

**AA TRADERS** is a complete, production-grade Pharmaceutical Distribution Management System (PDMS) engineered specifically for wholesale pharmaceutical distributors, institutional vendors, and medical supply chain operators. 

Built using PHP and SQLite with Write-Ahead Logging (WAL) mode for maximum concurrency and instant zero-configuration deployment, this system incorporates strict regulatory safeguards mandated by the **Drug Regulatory Authority of Pakistan (DRAP)**.

---

## 🌟 Key Architecture & Capabilities

### 1. Executive Intelligence & Dashboard
- **17 Real-Time KPI Cards:** Total Sales Today, Month Revenue, Purchases Today/Month, Outstanding Receivables, Outstanding Payables, Current Stock Value (Trade Price & Cost), Reorder Alerts, Near Expiry (&lt;30d, &lt;90d, &lt;180d), Expired Batches, Daily Collections, Operating Expenses, Sales/Purchase Returns, Active Pharmacies, Formulations, and Sales Force Representatives.
- **Interactive Visualizations:** Revenue and Collection 6-month trends, Stock Valuation by Category, and live status charts.
- **AI Pharma Copilot Widget:** Natural language query assistant capable of answering instant business questions directly from live database tables.

### 2. Product Master & Batch Inventory (FEFO Core)
- **Product Master:** Trade Name, Generic Formulation, Category, Therapeutic Classification, Manufacturer, Dosage Form, Strength, Pack Size, Unit, Barcode/GTIN, and Prescription (Rx) flags.
- **FEFO (First Expiry, First Out) Engine:** Automatically suggests and allocates batches nearing expiration first to minimize shelf-life loss.
- **Regulatory Gatekeeping:** System actively prevents receipt or sale of already expired batches.

### 3. Pharmaceutical Schemes & Bonus Goods (10+1, 20+2)
- **Automated Scheme Engine:** Supports standard Pakistani pharma incentive schemes (e.g., Buy 10 get 1 Free, Buy 20 get 2 Free, or percentage volume discounts).
- **Auto-Application:** During sales ordering and invoicing, the engine calculates bonus packs and deducts them from inventory transactions while applying credit discounts.

### 4. Warehousing & Multi-Facility Logistics
- **Specialized Storage Facilities:** Main Central Warehouse, Secondary Depots, Cold Chain & Biologics Facility (2°C-8°C with temperature logging), Quarantine Inspection Bay, Damaged Stock Segregation, and Expired Stock Destruction Bay.
- **Inter-Warehouse Transfers:** Request &rarr; Approval &rarr; Cold Chain Dispatch &rarr; Receiving workflow with automatic batch replication.

### 5. Sales Force, Customer Credit & Orders
- **Customer Directory:** Pharmacies, Medical Stores, Hospitals, and Wholesalers with DRAP Drug Sale License numbers, NTN, and assigned territory reps.
- **Credit Limit & Auto-Freeze:** Real-time credit utilization monitoring. Customers exceeding approved credit ceilings are automatically credit-frozen to prevent bad debt exposure.
- **Field Force & Targets:** Rep monthly targets vs actual achievements, commission accruals, and daily pharmacy visit diaries.

### 6. Inward Procurement & Returns
- **Purchase Orders & GRN:** Comprehensive procurement pipeline from supplier PO issuance to Goods Receipt Notes (GRN) with strict expiry date gatekeeping.
- **Purchase Returns:** Return damaged, near-expiry, or recalled batches to principal manufacturers with automatic Debit Note generation.
- **Sales Returns:** Process pharmacy customer returns with Credit Note issuance and segregated inventory routing.

### 7. Accounts, Financial Ledgers & Cashbook
- **Customer Running Ledgers:** `Opening Balance + Invoices - Returns - Payments = Outstanding Balance`.
- **Supplier Payable Ledgers:** `Opening Balance + Purchases - Returns - Payments = Payable`.
- **Collections & Banking:** Cash receipts, online bank transfers (IBFT), and cheque records with automated cash drawer and bank reconciliation.
- **Operational Expenses:** Tracking fleet fuel, van maintenance, cold storage electricity, salaries, and rent.

### 8. Quality, Regulatory Recall & Auditing
- **Dedicated Expiry Management:** 4-tier color-coded shelf-life matrix (Expired 🔴, &lt;30 Days 🟠, &lt;90 Days 🟡, &lt;180 Days 🔵) with 1-click segregation.
- **Product Batch Recall Matrix:** Regulatory batch quarantine tool that instantly traces all warehouse stocks and identifies every downstream pharmacy that received the batch for mandatory recall notifications.
- **Physical Stocktaking:** Shelf count auditing against system stock with discrepancy reconciliation vouchers.
- **Compliance Audit Trail:** Comprehensive event logging recording user, timestamp, IP address, module, and data alterations.
- **SQLite Hot Backup & Disaster Recovery:** 1-click database download and snapshot restoration.

### 9. Document Printing & WhatsApp Automation
- **Printable Tax Invoices:** Clean invoice design with DRAP license credentials, batch & expiry details, bonus packs, bank details, and signature blocks.
- **Delivery Challan / Gate Pass:** Transport manifest with driver and vehicle registration details.
- **Payment Receipts & POs:** Clean accounting vouchers.
- **WhatsApp 1-Click Notifications:** Direct WhatsApp link generator sending invoice totals, outstanding balances, and delivery dispatch notices to customer phones.

---

## 🚀 Quick Setup & Installation

### Prerequisites
- PHP 8.0 or newer with `pdo_sqlite`, `json`, `mbstring`, `session` extensions enabled.
- SQLite 3 (built into PHP PDO).

### Running Locally
```bash
# 1. Clone repository
git clone https://github.com/softsolspk1/aatraders.git
cd aatraders

# 2. Launch built-in PHP development server
php -S 127.0.0.1:8000

# 3. Access in web browser
http://127.0.0.1:8000
```
*Note: The SQLite database (`data/aatraders.sqlite`) initializes and seeds itself automatically upon the first page request.*

---

## 👥 Evaluation & Demo Role Accounts

| Role | Username | Password | Capabilities |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `admin123` | Full ERP control & configuration |
| **Sales Manager** | `sales_mgr` | `admin123` | Sales orders, customer credit & schemes |
| **Warehouse Manager** | `warehouse_mgr` | `admin123` | FEFO batches, transfers & dispatch |
| **Accounts Manager** | `accounts_mgr` | `admin123` | Collections, ledgers & cashbook |
| **Sales Representative** | `kamran_rep` | `admin123` | Order booking & customer visit logs |
| **Internal Auditor** | `auditor` | `admin123` | Audit logs, stocktaking & recall |

*Tip: A fast 1-click role switcher dropdown is available at the top right of every page when logged in.*

---

## 🏛️ Regulatory Compliance
Engineered in accordance with the **Drug Act 1976** and **DRAP Rules 2014 (Good Distribution Practices)**.

Developed for **AA TRADERS** by **Softsols**.
