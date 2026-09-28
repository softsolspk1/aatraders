<?php
// AA TRADERS - AI Pharma Copilot Engine

require_once __DIR__ . '/../config/database.php';

class CopilotEngine {
    public static function ask(string $question): array {
        $db = Database::getConnection();
        $q = strtolower(trim($question));

        // 1. Expiry Queries
        if (str_contains($q, 'expire') || str_contains($q, 'expiry') || str_contains($q, 'near expiry')) {
            $days = 90;
            if (str_contains($q, '30')) $days = 30;
            if (str_contains($q, '60')) $days = 60;
            if (str_contains($q, '180')) $days = 180;

            $stmt = $db->query("
                SELECT p.name as product_name, p.dosage_form, b.batch_number, b.expiry_date, 
                       b.quantity_available, w.name as warehouse_name,
                       CAST(julianday(b.expiry_date) - julianday('now') AS INTEGER) as days_remaining
                FROM product_batches b
                JOIN products p ON b.product_id = p.id
                JOIN warehouses w ON b.warehouse_id = w.id
                WHERE b.quantity_available > 0 
                  AND b.status = 'active'
                  AND julianday(b.expiry_date) - julianday('now') <= {$days}
                ORDER BY b.expiry_date ASC
            ");
            $rows = $stmt->fetchAll();

            return [
                'type' => 'table',
                'title' => "Batches Expiring Within {$days} Days",
                'summary' => count($rows) > 0 
                    ? "Identified " . count($rows) . " batch(es) nearing expiration within {$days} days. Immediate liquidation or promotional scheme recommended."
                    : "Excellent news! No active saleable batches found expiring within {$days} days.",
                'data' => $rows
            ];
        }

        // 2. Credit Limit & Overdue Customers
        if (str_contains($q, 'credit') || str_contains($q, 'overdue') || str_contains($q, 'outstanding') || str_contains($q, 'blocked')) {
            $stmt = $db->query("
                SELECT c.code, c.business_name, c.phone, c.credit_limit, c.current_balance,
                       ROUND((c.current_balance / c.credit_limit) * 100, 1) as utilization_pct,
                       c.is_blocked, c.block_reason
                FROM customers c
                WHERE c.current_balance > 0
                ORDER BY utilization_pct DESC
            ");
            $rows = $stmt->fetchAll();

            return [
                'type' => 'table',
                'title' => "Customer Credit Utilization & Receivables Risk",
                'summary' => "Displaying customers with outstanding balances sorted by credit utilization percentage.",
                'data' => $rows
            ];
        }

        // 3. Purchase Reorder & Safety Stock Forecasting
        if (str_contains($q, 'purchase') || str_contains($q, 'reorder') || str_contains($q, 'suggest') || str_contains($q, 'low stock')) {
            $stmt = $db->query("
                SELECT p.code, p.name as product_name, p.dosage_form, p.strength,
                       IFNULL(SUM(b.quantity_available), 0) as current_stock,
                       p.min_stock, p.reorder_level, p.safety_stock,
                       m.name as manufacturer_name,
                       CASE 
                           WHEN IFNULL(SUM(b.quantity_available), 0) <= p.reorder_level 
                           THEN (p.max_stock - IFNULL(SUM(b.quantity_available), 0))
                           ELSE 0 
                       END as suggested_purchase_qty
                FROM products p
                LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
                LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
                GROUP BY p.id
                HAVING current_stock <= p.reorder_level OR current_stock <= p.min_stock
                ORDER BY current_stock ASC
            ");
            $rows = $stmt->fetchAll();

            return [
                'type' => 'table',
                'title' => "Purchase Forecasting & Suggested Replenishment",
                'summary' => count($rows) > 0 
                    ? "Found " . count($rows) . " product(s) at or below reorder threshold. Recommended PO quantities calculated based on maximum holding capacity."
                    : "All product stock levels are currently above reorder thresholds.",
                'data' => $rows
            ];
        }

        // 4. Sales Rep Performance vs Targets
        if (str_contains($q, 'target') || str_contains($q, 'rep') || str_contains($q, 'achievement') || str_contains($q, 'commission')) {
            $stmt = $db->query("
                SELECT r.employee_code, r.name as rep_name, t.territory_name,
                       st.target_amount, st.achieved_amount,
                       ROUND((st.achieved_amount / st.target_amount) * 100, 1) as achievement_pct,
                       ROUND(st.achieved_amount * (r.commission_rate / 100), 2) as estimated_commission
                FROM sales_representatives r
                JOIN territories t ON r.territory_id = t.id
                JOIN sales_targets st ON r.id = st.sales_rep_id
                WHERE st.target_year = 2026 AND st.target_month = 9
                ORDER BY achievement_pct DESC
            ");
            $rows = $stmt->fetchAll();

            return [
                'type' => 'table',
                'title' => "Sales Representative Target Achievement (September 2026)",
                'summary' => "Rep performance evaluation showing monthly targets, revenue achievement, and accrued commission.",
                'data' => $rows
            ];
        }

        // 5. Stock Valuation & Inventory Position
        if (str_contains($q, 'stock') || str_contains($q, 'inventory') || str_contains($q, 'valuation') || str_contains($q, 'value')) {
            $stmt = $db->query("
                SELECT w.name as warehouse_name, w.type,
                       COUNT(b.id) as batch_count,
                       SUM(b.quantity_available) as total_units,
                       ROUND(SUM(b.quantity_available * b.purchase_price), 2) as purchase_valuation,
                       ROUND(SUM(b.quantity_available * b.trade_price), 2) as trade_valuation
                FROM warehouses w
                LEFT JOIN product_batches b ON w.id = b.warehouse_id AND b.status = 'active'
                GROUP BY w.id
            ");
            $rows = $stmt->fetchAll();

            $totalVal = array_sum(array_column($rows, 'trade_valuation'));

            return [
                'type' => 'table',
                'title' => "Warehouse Inventory Valuation & Stock Position",
                'summary' => "Total distributor stock valuation across all active facilities stands at Rs. " . number_format($totalVal, 2),
                'data' => $rows
            ];
        }

        // Default: Top Selling & Business Snapshot
        $stmt = $db->query("
            SELECT p.code, p.name as product_name, 
                   IFNULL(SUM(ii.quantity), 0) as units_sold,
                   IFNULL(SUM(ii.net_total), 0) as total_revenue
            FROM products p
            LEFT JOIN sales_invoice_items ii ON p.id = ii.product_id
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $rows = $stmt->fetchAll();

        return [
            'type' => 'table',
            'title' => "Top Selling Pharmaceutical Formulations",
            'summary' => "I analyzed your ERP dataset. Here are the leading revenue-generating products in the current cycle.",
            'data' => $rows
        ];
    }
}
