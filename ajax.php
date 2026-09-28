<?php
// AA TRADERS - Asynchronous API Endpoints

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/FEFO.php';
require_once __DIR__ . '/includes/SchemeEngine.php';
require_once __DIR__ . '/includes/CopilotEngine.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $db = Database::getConnection();

    switch ($action) {
        case 'copilot_ask':
            $q = $_GET['q'] ?? '';
            if (empty($q)) {
                echo json_encode(['success' => false, 'message' => 'Please provide a question.']);
                exit;
            }
            $payload = CopilotEngine::ask($q);
            echo json_encode(['success' => true, 'payload' => $payload]);
            break;

        case 'get_fefo_batches':
            $productId = (int)($_GET['product_id'] ?? 0);
            $warehouseId = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : null;
            $batches = FEFO::getAvailableBatches($productId, $warehouseId);
            echo json_encode(['success' => true, 'batches' => $batches]);
            break;

        case 'recommend_fefo':
            $productId = (int)($_GET['product_id'] ?? 0);
            $qty = (int)($_GET['qty'] ?? 1);
            $warehouseId = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : null;
            $recommendation = FEFO::recommendAllocation($productId, $qty, $warehouseId);
            echo json_encode(['success' => true, 'recommendation' => $recommendation]);
            break;

        case 'calc_scheme':
            $productId = (int)($_GET['product_id'] ?? 0);
            $qty = (int)($_GET['qty'] ?? 1);
            $scheme = SchemeEngine::calculateScheme($productId, $qty);
            echo json_encode(['success' => true, 'scheme' => $scheme]);
            break;

        case 'search_products':
            $q = trim($_GET['q'] ?? '');
            $stmt = $db->prepare("
                SELECT p.*, m.name as manufacturer_name, c.name as category_name,
                       IFNULL(SUM(b.quantity_available), 0) as total_stock
                FROM products p
                LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
                LEFT JOIN product_categories c ON p.category_id = c.id
                LEFT JOIN product_batches b ON p.id = b.product_id AND b.status = 'active'
                WHERE p.is_active = 1 
                  AND (p.name LIKE ? OR p.code LIKE ? OR p.generic_name LIKE ? OR p.barcode LIKE ?)
                GROUP BY p.id
                ORDER BY p.name ASC
                LIMIT 15
            ");
            $searchTerm = "%{$q}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
            break;

        case 'search_customers':
            $q = trim($_GET['q'] ?? '');
            $stmt = $db->prepare("
                SELECT c.*, t.territory_name, r.name as rep_name
                FROM customers c
                LEFT JOIN territories t ON c.territory_id = t.id
                LEFT JOIN sales_representatives r ON c.sales_rep_id = r.id
                WHERE c.is_active = 1
                  AND (c.business_name LIKE ? OR c.code LIKE ? OR c.phone LIKE ? OR c.drug_license LIKE ?)
                ORDER BY c.business_name ASC
                LIMIT 15
            ");
            $searchTerm = "%{$q}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            echo json_encode(['success' => true, 'customers' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
