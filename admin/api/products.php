<?php
/**
 * Sakura Beaded Bags — Products/Costing JSON API.
 * Requires the original schema plus scripts/migrate_product_catalog.php --apply.
 * GET returns live materials/categories/products and a session CSRF token.
 * POST creates/updates products and optionally creates a category.
 * Product recipes never deduct inventory: that belongs to order processing.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function prod_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function prod_fail(string $message, int $status = 422): never {
    prod_out(['ok' => false, 'error' => $message], $status);
}
function prod_string(mixed $raw, string $label, int $max): string {
    if (!is_string($raw)) throw new InvalidArgumentException("$label is required.");
    $value = trim($raw);
    if ($value === '' || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $max) {
        throw new InvalidArgumentException("$label must be between 1 and $max characters.");
    }
    return $value;
}
function prod_number(mixed $raw, string $label, float $max, bool $zero = true): string {
    if (!is_int($raw) && !is_float($raw) && !is_string($raw)) {
        throw new InvalidArgumentException("$label must be a number.");
    }
    $value = (string) $raw;
    if (!preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $value)) {
        throw new InvalidArgumentException("$label must have at most two decimal places.");
    }
    $num = (float) $value;
    if (!is_finite($num) || $num > $max || (!$zero && $num <= 0)) {
        throw new InvalidArgumentException("$label is outside the allowed range.");
    }
    return number_format($num, 2, '.', '');
}
function prod_positive_id(mixed $value, string $label): int {
    if (!is_int($value) && !is_string($value)) {
        throw new InvalidArgumentException("Invalid $label.");
    }
    $raw = (string) $value;
    if (!preg_match('/^[1-9][0-9]*$/D', $raw)) throw new InvalidArgumentException("Invalid $label.");
    $n = filter_var($raw, FILTER_VALIDATE_INT);
    if ($n === false || $n > 2147483647) throw new InvalidArgumentException("Invalid $label.");
    return $n;
}
function prod_material_id(mixed $value): int {
    if (!is_string($value) || !preg_match('/^MAT-0*([1-9][0-9]*)$/D', $value, $m)) {
        throw new InvalidArgumentException('Choose a valid material.');
    }
    return prod_positive_id($m[1], 'material ID');
}
function prod_money(float $n): string { return number_format(round($n + 0.00000001, 2), 2, '.', ''); }

/**
 * Accept only real JPEG/PNG/WebP uploads. The disk file is removed if a
 * product database transaction fails, and the photo filename is random.
 */
function prod_store_photo(array $photo): array {
    $error = $photo['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new InvalidArgumentException('Photo exceeds the PHP upload size limit. Check php.ini.');
        }
        throw new InvalidArgumentException('Photo could not be uploaded. Please choose it again.');
    }
    $bytes = $photo['size'] ?? 0;
    $tmp = $photo['tmp_name'] ?? '';
    if (!is_int($bytes) || $bytes < 1 || $bytes > 4 * 1024 * 1024) {
        throw new InvalidArgumentException('Photo must be non-empty and no larger than 4 MB.');
    }
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('Invalid photo upload.');
    }
    $dimensions = @getimagesize($tmp);
    $formats = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = is_array($dimensions) ? ($dimensions['mime'] ?? '') : '';
    $width = (int) ($dimensions[0] ?? 0);
    $height = (int) ($dimensions[1] ?? 0);
    if (!isset($formats[$mime]) || $width < 1 || $height < 1 || $width > 6000 || $height > 6000) {
        throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP image up to 6000 × 6000 pixels.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($tmp) !== $mime) {
        throw new InvalidArgumentException('The photo format does not match its contents.');
    }
    $directory = dirname(__DIR__, 2) . '/uploads/product-photos';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create product photo folder. Check server permissions.');
    }
    $filename = 'product-' . bin2hex(random_bytes(16)) . '.' . $formats[$mime];
    $target = $directory . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Cannot save product photo. Check server write permissions.');
    }
    return ['disk_path'=>$target, 'image_path'=>'../uploads/product-photos/' . $filename];
}

$sessionId = $_SESSION['user_id'] ?? null;
$isDemo = $sessionId === 'demo';
$host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array($host, ['127.0.0.1', 'localhost'], true);
$demoAuthorized = $isDemo && DEMO_MODE && $isLocal;
$email = strtolower((string) ($_SESSION['user_email'] ?? ''));
$allowed = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$adminAuthorized = !$isDemo && !empty($sessionId) && $email !== '' && in_array($email, $allowed, true);
if (!$demoAuthorized && !$adminAuthorized) prod_fail('Please sign in as an authorized administrator.', 403);
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) {
    header('Allow: GET, POST'); prod_fail('Method not allowed.', 405);
}

function prod_catalog(PDO $pdo): array {
    $categories = array_map(static fn(array $r): array => [
        'id' => (int) $r['category_id'], 'name' => $r['category_name'],
    ], $pdo->query('SELECT category_id, category_name FROM product_categories ORDER BY category_id')->fetchAll());
    $materials = array_map(static fn(array $r): array => [
        'id' => sprintf('MAT-%03d', (int) $r['material_id']),
        'name' => $r['material_name'], 'unit' => $r['unit'],
        'cost' => (float) $r['unit_cost'], 'stock' => (float) $r['quantity_on_hand'],
        'min' => (float) $r['low_stock_threshold'],
    ], $pdo->query('SELECT material_id, material_name, unit, unit_cost, quantity_on_hand, low_stock_threshold FROM materials ORDER BY material_id')->fetchAll());
    $items = $pdo->query('SELECT pm.product_id, pm.material_id, pm.quantity_required, m.material_name, m.unit, m.unit_cost
        FROM product_materials pm JOIN materials m ON m.material_id = pm.material_id
        ORDER BY pm.product_id, pm.material_id')->fetchAll();
    $byProduct = [];
    foreach ($items as $r) {
        $byProduct[(int) $r['product_id']][] = [
            'id' => sprintf('MAT-%03d', (int) $r['material_id']),
            'name' => $r['material_name'], 'unit' => $r['unit'],
            'qty' => (float) $r['quantity_required'], 'cost' => (float) $r['unit_cost'],
        ];
    }
    $rows = $pdo->query('SELECT p.product_id, p.sku, p.product_name, p.category_id, c.category_name,
            p.image_path, p.labor_cost, p.packaging_cost, p.other_cost, p.markup_percent, p.selling_price
            FROM products p LEFT JOIN product_categories c ON c.category_id = p.category_id
            WHERE p.is_active = 1 ORDER BY p.product_id')->fetchAll();
    $products = array_map(static function (array $r) use ($byProduct): array {
        $id = (int) $r['product_id'];
        return [
            'id' => $id, 'sku' => $r['sku'] ?? '', 'name' => $r['product_name'],
            'categoryId' => (int) $r['category_id'], 'category' => $r['category_name'] ?? '',
            'image' => $r['image_path'],
            'labor' => (float) $r['labor_cost'], 'packaging' => (float) $r['packaging_cost'],
            'otherCost' => (float) $r['other_cost'], 'markup' => (float) $r['markup_percent'],
            'sellingPrice' => (float) $r['selling_price'], 'materials' => $byProduct[$id] ?? [],
        ];
    }, $rows);
    if (empty($_SESSION['sbb_products_csrf'])) {
        $_SESSION['sbb_products_csrf'] = bin2hex(random_bytes(32));
    }
    return ['ok' => true, 'categories' => $categories, 'materials' => $materials,
        'products' => $products, 'csrf' => $_SESSION['sbb_products_csrf']];
}

$uploadedPhotoDiskPath = null;
try {
    $pdo = sbb_db();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') prod_out(prod_catalog($pdo));

    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !isset($_SESSION['sbb_products_csrf'])
        || !hash_equals($_SESSION['sbb_products_csrf'], $token)) {
        prod_fail('Security token invalid. Refresh the page and retry.', 403);
    }
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $photoUpload = null;
    if (stripos($contentType, 'multipart/form-data') === 0) {
        $body = json_decode((string) ($_POST['data'] ?? ''), true);
        if (!is_array($body)) prod_fail('Invalid product data. Check PHP post_max_size.');
        $photoUpload = $_FILES['photo'] ?? null;
        if (!is_array($photoUpload)) prod_fail('Photo was not received. Check PHP upload_max_filesize.');
    } elseif (stripos($contentType, 'application/json') === 0) {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) prod_fail('Invalid JSON request.');
    } else {
        prod_fail('JSON or multipart form data required.', 415);
    }
    $action = $body['action'] ?? '';
    if ($photoUpload !== null && $action !== 'add_product') prod_fail('Photos are only supported for new products.');

    if ($action === 'add_category') {
        $name = prod_string($body['name'] ?? null, 'Category name', 100);
        if (strtolower($name) === 'all') throw new InvalidArgumentException('Category name is reserved.');
        $stmt = $pdo->prepare('INSERT INTO product_categories (category_name) VALUES (?)');
        $stmt->execute([$name]);
        prod_out(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Category saved in MySQL.'], 201);
    }

    if ($action !== 'add_product' && $action !== 'save_pricing') prod_fail('Unknown action.');
    $name = prod_string($body['name'] ?? null, 'Product name', 160);
    $categoryId = prod_positive_id($body['categoryId'] ?? null, 'category');
    $labor = prod_number($body['labor'] ?? null, 'Labor cost', 1000000);
    $packaging = prod_number($body['packaging'] ?? null, 'Packaging cost', 1000000);
    $markup = prod_number($body['markup'] ?? null, 'Markup percent', 999.99);
    $materialItems = $body['materials'] ?? null;
    if (!is_array($materialItems) || !array_is_list($materialItems) || count($materialItems) < 1 || count($materialItems) > 100) {
        throw new InvalidArgumentException('Choose 1 to 100 recipe materials.');
    }
    $recipe = [];
    foreach ($materialItems as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Invalid recipe entry.');
        $materialId = prod_material_id($item['id'] ?? null);
        if (isset($recipe[$materialId])) throw new InvalidArgumentException('This recipe contains the same material twice.');
        $recipe[$materialId] = prod_number($item['qty'] ?? null, 'Recipe quantity', 1000000, false);
    }
    $productId = $action === 'save_pricing' ? prod_positive_id($body['id'] ?? null, 'product') : null;

    $pdo->beginTransaction();
    $categoryStmt = $pdo->prepare('SELECT category_id FROM product_categories WHERE category_id = ?');
    $categoryStmt->execute([$categoryId]);
    if (!$categoryStmt->fetchColumn()) throw new InvalidArgumentException('Category was not found. Refresh and retry.');

    // Use actual current catalog unit costs, not client-provided prices.
    $matStmt = $pdo->prepare('SELECT unit_cost FROM materials WHERE material_id = ? FOR UPDATE');
    $materialTotal = 0.0;
    foreach ($recipe as $matId => $qty) {
        $matStmt->execute([$matId]);
        $cost = $matStmt->fetchColumn();
        if ($cost === false) throw new InvalidArgumentException('A material no longer exists. Refresh and retry.');
        $materialTotal += round((float) $qty * (float) $cost, 2);
    }
    $total = $materialTotal + (float) $labor + (float) $packaging;
    $price = round($total * (1 + (float) $markup / 100), 2);
    if ($total <= 0 || !is_finite($price) || $price > 100000000) {
        throw new InvalidArgumentException('Calculated price must be positive and at most ₱100,000,000.');
    }
    $priceSql = prod_money($price);

    if ($action === 'add_product') {
        $stmt = $pdo->prepare('INSERT INTO products
            (product_name, category_id, labor_cost, packaging_cost, markup_percent, selling_price)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $categoryId, $labor, $packaging, $markup, $priceSql]);
        $productId = (int) $pdo->lastInsertId();
        $letters = substr(preg_replace('/[AEIOU]/', '', preg_replace('/[^A-Z]/', '', strtoupper($name))), 0, 3);
        $prefix = str_pad($letters, 3, 'X');
        $sku = 'SAK-' . $prefix . '-' . str_pad((string) $productId, 3, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('UPDATE products SET sku = ? WHERE product_id = ?');
        $stmt->execute([$sku, $productId]);
    } else {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$productId]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Product not found. Refresh and retry.');
        $stmt = $pdo->prepare('UPDATE products SET product_name = ?, category_id = ?, labor_cost = ?,
            packaging_cost = ?, markup_percent = ?, selling_price = ? WHERE product_id = ?');
        $stmt->execute([$name, $categoryId, $labor, $packaging, $markup, $priceSql, $productId]);
        $pdo->prepare('DELETE FROM product_materials WHERE product_id = ?')->execute([$productId]);
    }
    $insert = $pdo->prepare('INSERT INTO product_materials (product_id, material_id, quantity_required) VALUES (?, ?, ?)');
    foreach ($recipe as $matId => $qty) $insert->execute([$productId, $matId, $qty]);
    if ($photoUpload !== null) {
        $storedPhoto = prod_store_photo($photoUpload);
        $uploadedPhotoDiskPath = $storedPhoto['disk_path'];
        $pdo->prepare('UPDATE products SET image_path = ? WHERE product_id = ?')
            ->execute([$storedPhoto['image_path'], $productId]);
    }
    $pdo->commit();
    prod_out(['ok' => true, 'id' => $productId, 'price' => (float) $priceSql,
        'message' => $action === 'add_product' ? 'Product saved in MySQL.' : 'Pricing updated in MySQL.'],
        $action === 'add_product' ? 201 : 200);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($uploadedPhotoDiskPath !== null && is_file($uploadedPhotoDiskPath)) @unlink($uploadedPhotoDiskPath);
    prod_fail($e->getMessage());
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($uploadedPhotoDiskPath !== null && is_file($uploadedPhotoDiskPath)) @unlink($uploadedPhotoDiskPath);
    if ($e->getCode() === '23000') prod_fail('This name or SKU already exists, or a referenced item was removed.');
    error_log('SBB products API database error: ' . $e->getMessage());
    prod_fail('Database request failed. Check PHP server logs.', 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($uploadedPhotoDiskPath !== null && is_file($uploadedPhotoDiskPath)) @unlink($uploadedPhotoDiskPath);
    error_log('SBB products API: ' . $e->getMessage());
    prod_fail('Unexpected server error. Check PHP server logs.', 500);
}
