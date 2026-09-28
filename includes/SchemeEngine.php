<?php
// AA TRADERS - Pharmaceutical Bonus Scheme & Discount Engine

require_once __DIR__ . '/../config/database.php';

class SchemeEngine {
    /**
     * Calculate bonus / free quantities or percentage discounts applicable for a product order
     */
    public static function calculateScheme(int $productId, int $quantity): array {
        $db = Database::getConnection();
        $today = date('Y-m-d');

        $stmt = $db->prepare("SELECT * FROM schemes 
                              WHERE product_id = ? 
                                AND is_active = 1 
                                AND (start_date IS NULL OR start_date <= ?)
                                AND (end_date IS NULL OR end_date >= ?)
                              ORDER BY buy_qty DESC LIMIT 1");
        $stmt->execute([$productId, $today, $today]);
        $scheme = $stmt->fetch();

        $result = [
            'scheme_applied' => false,
            'scheme_name' => null,
            'scheme_type' => null,
            'bonus_quantity' => 0,
            'discount_percentage' => 0.0,
            'description' => 'No active scheme'
        ];

        if ($scheme) {
            $result['scheme_applied'] = true;
            $result['scheme_name'] = $scheme['name'];
            $result['scheme_type'] = $scheme['scheme_type'];

            if ($scheme['scheme_type'] === 'bonus_qty') {
                $buyQty = max(1, (int)$scheme['buy_qty']);
                $freeQty = (int)$scheme['free_qty'];
                
                // e.g., if buy_qty is 10 and free_qty is 1, and quantity is 25: floor(25/10) * 1 = 2 free units
                $multiples = floor($quantity / $buyQty);
                $totalFree = $multiples * $freeQty;

                $result['bonus_quantity'] = (int)$totalFree;
                $result['description'] = "Scheme ({$scheme['name']}): Buy {$buyQty} get {$freeQty} Free. Earned: {$totalFree} bonus units.";
            } elseif ($scheme['scheme_type'] === 'percentage_discount') {
                if ($quantity >= (int)$scheme['buy_qty']) {
                    $result['discount_percentage'] = (float)$scheme['discount_pct'];
                    $result['description'] = "Scheme ({$scheme['name']}): {$scheme['discount_pct']}% Trade Discount applied on {$quantity} units.";
                }
            }
        }

        return $result;
    }
}
