<?php
/**
 * Sakura Beaded Bags: read-only MySQL product catalog endpoint (development step 1).
 *
 * GET /admin/api/product_catalog.php
 * Reads real materials, products, and product recipes from the existing 11-table
 * Sakura Beaded Bags schema. Makes no database writes and changes no existing UI.
 *
 * This is a protected API, NOT a public catalog. Demo login is accepted only
 * when both the browser and PHP server run on the local loopback address.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function catalog_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    catalog_response(['ok' => false, 'error' => 'GET requests only.'], 405);
}

$userId = $_SESSION['user_id'] ?? null;
$isDemo = $userId === 'demo';
$host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isLoopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array($host, ['127.0.0.1', 'localhost'], true);
$demoAuthorized = $isDemo && DEMO_MODE && $isLoopback;
$email = strtolower((string) ($_SESSION['user_email'] ?? ''));
$allowed = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$adminAuthorized = !$isDemo && !empty($userId) && $email !== ''
    && in_array($email, $allowed, true);

if (!$demoAuthorized && !$adminAuthorized) {
    catalog_response(['ok' => false, 'error' => 'Please sign in as an authorized administrator.'], 403);
}

try {
    $pdo = sbb_db();

    $materialRows = $pdo->query(
        'SELECT material_id, material_name, unit, unit_cost, quantity_on_hand, low_stock_threshold
         FROM materials ORDER BY material_id'
    )->fetchAll();

    $materials = array_map(static fn (array $r): array => [
        'id' => sprintf('MAT-%03d', (int) $r['material_id']),
        'name' => (string) $r['material_name'],
        'unit' => (string) $r['unit'],
        'cost' => (float) $r['unit_cost'],
        'stock' => (float) $r['quantity_on_hand'],
        'min' => (float) $r['low_stock_threshold'],
    ], $materialRows);

    // Existing DB stores costs and prices, but DOES NOT yet contain SKU/category.
    // Do not fabricate database-backed SKU or category values.
    $productRows = $pdo->query(
        'SELECT p.product_id, p.product_name, p.description, p.image_path,
                p.labor_cost, p.packaging_cost, p.other_cost,
                p.markup_percent, p.selling_price,
                v.material_cost, v.total_cost, v.suggested_selling_price
         FROM products p
         JOIN v_product_costing v ON v.product_id = p.product_id
         WHERE p.is_active = 1 ORDER BY p.product_id'
    )->fetchAll();

    $bomRows = $pdo->query(
        'SELECT pm.product_id, pm.material_id, pm.quantity_required,
                m.material_name, m.unit, m.unit_cost
         FROM product_materials pm
         INNER JOIN materials m ON m.material_id = pm.material_id
         ORDER BY pm.product_id, pm.material_id'
    )->fetchAll();

    $bomByProduct = [];
    foreach ($bomRows as $row) {
        $bomByProduct[(int) $row['product_id']][] = [
            'id' => sprintf('MAT-%03d', (int) $row['material_id']),
            'name' => (string) $row['material_name'],
            'unit' => (string) $row['unit'],
            'qty' => (float) $row['quantity_required'],
            'cost' => (float) $row['unit_cost'],
        ];
    }

    $products = array_map(static function (array $r) use ($bomByProduct): array {
        $id = (int) $r['product_id'];
        return [
            'id' => $id,
            'name' => (string) $r['product_name'],
            'description' => $r['description'],
            'image' => $r['image_path'],
            'labor' => (float) $r['labor_cost'],
            'packaging' => (float) $r['packaging_cost'],
            'otherCost' => (float) $r['other_cost'],
            'markup' => (float) $r['markup_percent'],
            'sellingPrice' => (float) $r['selling_price'],
            'materialCost' => (float) $r['material_cost'],
            'totalCost' => (float) $r['total_cost'],
            'suggestedPrice' => (float) $r['suggested_selling_price'],
            'materials' => $bomByProduct[$id] ?? [],
        ];
    }, $productRows);

    catalog_response([
        'ok' => true,
        'materials' => $materials,
        'products' => $products,
        'counts' => [
            'materials' => count($materials),
            'products' => count($products),
        ],
        'note' => 'Read-only. Categories and SKUs require an agreed database migration.',
    ]);
} catch (Throwable $error) {
    error_log('SBB product catalog API: ' . $error->getMessage());
    catalog_response([
        'ok' => false,
        'error' => 'Could not read product catalog. Check the PHP server terminal.',
    ], 500);
}
