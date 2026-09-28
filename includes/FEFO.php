<?php
// AA TRADERS - FEFO (First Expiry, First Out) Inventory Engine

require_once __DIR__ . '/../config/database.php';

class FEFO {
    /**
     * Get active batches for a product sorted by earliest expiry date (FEFO)
     */
    public static function getAvailableBatches(int $productId, ?int $warehouseId = null): array {
        $db = Database::getConnection();
        $sql = "SELECT b.*, w.name as warehouse_name 
                FROM product_batches b
                JOIN warehouses w ON b.warehouse_id = w.id
                WHERE b.product_id = ? 
                  AND b.quantity_available > 0 
                  AND b.status = 'active'
                  AND b.expiry_date >= date('now')";
        $params = [$productId];

        if ($warehouseId !== null) {
            $sql .= " AND b.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        $sql .= " ORDER BY b.expiry_date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $batches = $stmt->fetchAll();

        $today = new DateTime('now');
        foreach ($batches as &$batch) {
            $exp = new DateTime($batch['expiry_date']);
            $diff = $today->diff($exp);
            $daysLeft = (int)$diff->format('%r%a');
            $batch['days_to_expiry'] = $daysLeft;

            if ($daysLeft <= 0) {
                $batch['expiry_status'] = 'expired';
                $batch['status_badge'] = 'danger';
            } elseif ($daysLeft <= 30) {
                $batch['expiry_status'] = 'critical_30';
                $batch['status_badge'] = 'danger';
            } elseif ($daysLeft <= 90) {
                $batch['expiry_status'] = 'near_90';
                $batch['status_badge'] = 'warning';
            } elseif ($daysLeft <= 180) {
                $batch['expiry_status'] = 'moderate_180';
                $batch['status_badge'] = 'info';
            } else {
                $batch['expiry_status'] = 'fresh';
                $batch['status_badge'] = 'success';
            }
        }

        return $batches;
    }

    /**
     * Automatically allocate requested quantity across FEFO batches
     * Returns an array of allocations: [['batch_id' => ..., 'allocated_qty' => ..., 'batch' => ...]]
     */
    public static function recommendAllocation(int $productId, int $requestedQty, ?int $warehouseId = null): array {
        $batches = self::getAvailableBatches($productId, $warehouseId);
        $remaining = $requestedQty;
        $allocations = [];

        foreach ($batches as $b) {
            if ($remaining <= 0) break;

            $take = min($remaining, $b['quantity_available']);
            $allocations[] = [
                'batch_id' => $b['id'],
                'batch_number' => $b['batch_number'],
                'expiry_date' => $b['expiry_date'],
                'days_to_expiry' => $b['days_to_expiry'],
                'trade_price' => $b['trade_price'],
                'mrp' => $b['mrp_retail_price'],
                'allocated_qty' => $take,
                'available_in_batch' => $b['quantity_available'],
                'warehouse_name' => $b['warehouse_name']
            ];
            $remaining -= $take;
        }

        return [
            'product_id' => $productId,
            'requested_qty' => $requestedQty,
            'total_allocated' => $requestedQty - $remaining,
            'unfulfilled_qty' => max(0, $remaining),
            'is_fully_fulfilled' => ($remaining <= 0),
            'allocations' => $allocations
        ];
    }
}
