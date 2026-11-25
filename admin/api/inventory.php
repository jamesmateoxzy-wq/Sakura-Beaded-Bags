<?php
/**
 * Sakura Beaded Bags: local-first Inventory JSON API.
 * Requires the SBB shared PDO connection and sales/soft-deletion migration.
 * GET: get materials and recent inventory movements.
 * POST: add_material, stock_in, correction. Writes use transactions.
 *
 * WARNING: A demo login is permitted ONLY when the request originates from
 * the local loopback interface and DEMO_MODE is on. Disable DEMO_MODE before
 * public deployment, configure real login, and audit authorization.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function api_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function api_error(string $message, int $code = 422): void {
    api_out(['ok' => false, 'error' => $message], $code);
}
function check_whole($value, string $label, bool $allowZero = true): int {
    if (!is_int($value) && !is_string($value)) {
        throw new InvalidArgumentException("$label must be a whole number.");
    }
    $raw = (string) $value;
    if (!preg_match('/^(0|[1-9][0-9]*)$/D', $raw)) {
        throw new InvalidArgumentException("$label must be a whole number.");
    }
    $number = filter_var($raw, FILTER_VALIDATE_INT);
    if ($number === false || $number > 1000000 || (!$allowZero && $number < 1)) {
        throw new InvalidArgumentException("$label is outside the allowed range.");
    }
    return $number;
}
function check_money($value, string $label): string {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        throw new InvalidArgumentException("$label is invalid.");
    }
    $raw = (string) $value;
    if (!preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D', $raw)) {
        throw new InvalidArgumentException("$label must be 0 to 1,000,000 with at most 2 decimals.");
    }
    if ((float) $raw > 1000000.00) {
        throw new InvalidArgumentException("$label must not exceed 1,000,000.");
    }
    return $raw;
}
function check_id($value): int {
    if (!is_string($value) || !preg_match('/^MAT-0*([1-9][0-9]*)$/D', $value, $m)) {
        throw new InvalidArgumentException('Choose a valid material.');
    }
    $id = filter_var($m[1], FILTER_VALIDATE_INT);
    if ($id === false) throw new InvalidArgumentException('Invalid material ID.');
    return $id;
}
function check_date($value): string {
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
        throw new InvalidArgumentException('Enter a valid date.');
    }
    $tz = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
    if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTimeImmutable('today', $tz)) {
        throw new InvalidArgumentException('Date cannot be invalid or in the future.');
    }
    return $value . ' ' . (new DateTimeImmutable('now', $tz))->format('H:i:s');
}

$userId = $_SESSION['user_id'] ?? null;
$demoLogin = $userId === 'demo';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = explode(':', $host)[0];
$localOrigin = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array($host, ['127.0.0.1', 'localhost'], true);
$demoAuthorized = $demoLogin && DEMO_MODE && $localOrigin;
$email = strtolower((string) ($_SESSION['user_email'] ?? ''));
$allowedEmails = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$googleAuthorized = !$demoLogin && !empty($userId) && $email !== ''
    && in_array($email, $allowedEmails, true);
if (!$demoAuthorized && !$googleAuthorized) api_error('Please sign in as an authorized administrator.', 403);
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    api_error('Method not allowed.', 405);
}

try {
    $pdo = sbb_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['sbb_inventory_csrf'])) {
            $_SESSION['sbb_inventory_csrf'] = bin2hex(random_bytes(32));
        }
        $rows = $pdo->query('SELECT material_id, material_name, unit, unit_cost, quantity_on_hand, low_stock_threshold FROM materials WHERE is_active = 1 ORDER BY material_id')->fetchAll();
        $materials = array_map(static function (array $r): array {
            return [
                'id' => sprintf('MAT-%03d', (int) $r['material_id']),
                'name' => $r['material_name'],
                'unit' => $r['unit'],
                'cost' => (float) $r['unit_cost'],
                'stock' => (float) $r['quantity_on_hand'],
                'min' => (float) $r['low_stock_threshold'],
            ];
        }, $rows);
        $historyRows = $pdo->query('SELECT i.movement_id, i.movement_type, i.quantity_change, i.note, i.moved_at, i.previous_quantity, i.resulting_quantity, m.material_name, m.unit FROM inventory_movements i JOIN materials m ON m.material_id=i.material_id ORDER BY i.moved_at DESC, i.movement_id DESC ')->fetchAll();
        $history = array_map(static function (array $r): array {
            return [
                'material' => $r['material_name'],
                'date' => substr((string) $r['moved_at'], 0, 10),
                'activity' => $r['note'] === 'Opening balance' ? 'Opening balance' : $r['movement_type'],
                'delta' => (float) $r['quantity_change'],
                'timestamp' => (string)$r['moved_at'], 'reason' => $r['note'] ?: $r['movement_type'],
                'before' => $r['previous_quantity'] === null ? null : (float)$r['previous_quantity'],
                'after' => $r['resulting_quantity'] === null ? null : (float)$r['resulting_quantity'],
                'unit' => $r['unit'],
            ];
        }, $historyRows);
        $stockInCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE movement_type = 'Stock In' AND (note IS NULL OR note <> 'Opening balance') AND YEAR(moved_at)=YEAR(CURRENT_DATE()) AND MONTH(moved_at)=MONTH(CURRENT_DATE())")->fetchColumn();
        api_out(['ok' => true, 'materials' => $materials, 'history' => $history, 'stockInCount' => $stockInCount, 'csrf' => $_SESSION['sbb_inventory_csrf']]);
    }

    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !isset($_SESSION['sbb_inventory_csrf']) || !hash_equals($_SESSION['sbb_inventory_csrf'], $token)) {
        api_error('Security token missing or invalid. Refresh the page and try again.', 403);
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) api_error('Invalid request data.');
    $action = $body['action'] ?? '';

    if ($action === 'add_material') {
        $name = trim((string) ($body['name'] ?? ''));
        $unit = (string) ($body['unit'] ?? '');
        if ($name === '' || strlen($name) > 80) throw new InvalidArgumentException('Material name is required (80 characters maximum).');
        if (!in_array($unit, ['pcs', 'roll', 'pack', 'meter', 'set'], true)) throw new InvalidArgumentException('Choose a valid unit.');
        $cost = check_money($body['cost'] ?? null, 'Unit cost');
        $stock = check_whole($body['stock'] ?? null, 'Opening quantity');
        $min = check_whole($body['min'] ?? null, 'Reorder level');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT material_id FROM materials WHERE LOWER(material_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        if ($stmt->fetch()) throw new InvalidArgumentException('A material with this name already exists.');
        $stmt = $pdo->prepare('INSERT INTO materials (material_name, unit, unit_cost, quantity_on_hand, low_stock_threshold) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $unit, $cost, $stock, $min]);
        $id = (int) $pdo->lastInsertId();
        if ($stock > 0) {
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (material_id, movement_type, quantity_change, note, previous_quantity, resulting_quantity) VALUES (?, 'Stock In', ?, 'Opening balance', 0, ?)");
            $stmt->execute([$id, $stock, $stock]);
        }
        $pdo->commit();
        api_out(['ok' => true, 'message' => 'Material saved in MySQL.']);
    }

    if ($action === 'remove_material') {
        $id = check_id($body['id'] ?? null);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT is_active FROM materials WHERE material_id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $active = $stmt->fetchColumn();
        if ($active === false) throw new InvalidArgumentException('Material not found. Refresh and retry.');

        $assigned = $pdo->prepare('SELECT COUNT(*) FROM product_materials WHERE material_id = ?');
        $assigned->execute([$id]);
        $productCount = (int) $assigned->fetchColumn();
        $movements = $pdo->prepare('SELECT COUNT(*) FROM inventory_movements WHERE material_id = ?');
        $movements->execute([$id]);
        $movementCount = (int) $movements->fetchColumn();

        if ((int)$active === 1) {
            $pdo->prepare('UPDATE materials SET is_active = 0 WHERE material_id = ?')->execute([$id]);
        }
        $pdo->commit();
        $message = $productCount > 0 || $movementCount > 0
            ? 'Material removed from active inventory. ' . $productCount . ' product recipe(s) and '
                . $movementCount . ' inventory movement(s) remain intact.'
            : 'Material removed from active inventory.';
        api_out(['ok' => true, 'id' => sprintf('MAT-%03d', $id), 'message' => $message,
            'productRecipes' => $productCount, 'inventoryMovements' => $movementCount]);
    }

    if ($action === 'stock_in' || $action === 'correction') {
        $id = check_id($body['id'] ?? null);
        $quantity = check_whole($body['quantity'] ?? null, 'Quantity', false);
        $dateTime = $action === 'stock_in' ? check_date($body['date'] ?? null) : null;
        $unitCost = $action === 'stock_in' ? check_money($body['cost'] ?? null, 'Unit cost') : null;
        $direction = (string) ($body['direction'] ?? '');
        if ($action === 'correction' && !in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException('Choose a valid correction direction.');
        }
        $delta = $action === 'correction' && $direction === 'out' ? -$quantity : $quantity;
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT quantity_on_hand FROM materials WHERE material_id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) throw new InvalidArgumentException('Material not found. Refresh and try again.');
        $newStock = (float) $found['quantity_on_hand'] + $delta;
        if ($newStock < 0 || $newStock > 1000000) {
            throw new InvalidArgumentException('Stock cannot become negative or exceed 1,000,000.');
        }
        if ($action === 'stock_in') {
            $stmt = $pdo->prepare('UPDATE materials SET quantity_on_hand = ?, unit_cost = ? WHERE material_id = ?');
            $stmt->execute([$newStock, $unitCost, $id]);
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (material_id, movement_type, quantity_change, moved_at, note, previous_quantity, resulting_quantity) VALUES (?, 'Stock In', ?, ?, 'Stock replenishment', ?, ?)");
            $stmt->execute([$id, $delta, $dateTime, $found['quantity_on_hand'], $newStock]);
        } else {
            $stmt = $pdo->prepare('UPDATE materials SET quantity_on_hand = ? WHERE material_id = ?');
            $stmt->execute([$newStock, $id]);
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (material_id, movement_type, quantity_change, note, previous_quantity, resulting_quantity) VALUES (?, 'Correction', ?, 'Stock correction', ?, ?)");
            $stmt->execute([$id, $delta, $found['quantity_on_hand'], $newStock]);
        }
        $pdo->commit();
        api_out(['ok' => true, 'message' => $action === 'stock_in' ? 'Stock-In saved in MySQL.' : 'Correction saved in MySQL.']);
    }
    api_error('Unknown action.');
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    api_error($error->getMessage(), 422);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('SBB inventory API: ' . $error->getMessage());
    api_error('Database operation failed. Check the PHP server terminal.', 500);
}
