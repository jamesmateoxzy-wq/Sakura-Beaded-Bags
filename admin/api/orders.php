<?php
/**
 * SBB Orders MySQL API. GET catalog/orders; POST create/cancel/archive/advance.
 * Requires scripts/migrate_order_requests.php --apply for idempotency.
 * This prototype allocates/deducts BOM on creation and returns it on cancellation.
 * No customer payments are recorded here.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/final_features.php';

function ord_out(array $v, int $status = 200): void {
    if (($v['ok']??false) && !($v['duplicate']??false) && ($_SERVER['REQUEST_METHOD']??'')==='POST') sbb_log_success('Orders',$GLOBALS['action']??null);
    http_response_code($status);
    echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function ord_fail(string $message, int $status = 422): void {
    ord_out(['ok' => false, 'error' => $message], $status);
}
function ord_id(mixed $v, string $label): int {
    if (!is_int($v) && !is_string($v)) throw new InvalidArgumentException("Invalid $label.");
    $s = (string) $v;
    if (!preg_match('/^[1-9][0-9]*$/D', $s)) throw new InvalidArgumentException("Invalid $label.");
    $id = filter_var($s, FILTER_VALIDATE_INT);
    if ($id === false || $id > 2147483647) throw new InvalidArgumentException("Invalid $label.");
    return $id;
}
function ord_str(mixed $v, string $label, int $max): string {
    if (!is_string($v)) throw new InvalidArgumentException("$label is required.");
    $v = trim($v);
    $length = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    if ($v === '' || $length > $max) throw new InvalidArgumentException("$label must be 1 to $max characters.");
    return $v;
}
function ord_date(mixed $v): string {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)) throw new InvalidArgumentException('Enter a valid due date.');
    $tz = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $v, $tz);
    if (!$date || $date->format('Y-m-d') !== $v) throw new InvalidArgumentException('Enter a valid due date.');
    $today = new DateTimeImmutable('today', $tz);
    if ($date < $today || $date > $today->modify('+3 years')) throw new InvalidArgumentException('Due date must be today or within 3 years.');
    return $v;
}
function ord_key(mixed $v): string {
    if (!is_string($v) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $v)) {
        throw new InvalidArgumentException('Invalid order request key. Reload and retry.');
    }
    return strtolower($v);
}
function ord_csrf(): string {
    if (empty($_SESSION['sbb_orders_csrf'])) $_SESSION['sbb_orders_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sbb_orders_csrf'];
}

$sessionId = $_SESSION['user_id'] ?? null;
$host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isLoopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array($host, ['127.0.0.1', 'localhost'], true);
$demoAuthorized = $sessionId === 'demo' && DEMO_MODE && $isLoopback;
$email = strtolower((string) ($_SESSION['user_email'] ?? ''));
$allowedEmails = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$adminAuthorized = $sessionId !== 'demo' && !empty($sessionId) && $email !== ''
    && in_array($email, $allowedEmails, true);
if (!$demoAuthorized && !$adminAuthorized) ord_fail('Please sign in as an authorized administrator.', 403);
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); ord_fail('Method not allowed.', 405);
}

function ord_get(PDO $pdo): void {
    $products = [];
    $stmt = $pdo->query('SELECT p.product_id, p.sku, p.product_name, p.selling_price, p.labor_cost,
            p.packaging_cost, p.other_cost, p.down_payment_percent,
            c.category_name, v.material_cost, v.total_cost
        FROM products p
        LEFT JOIN product_categories c ON c.category_id = p.category_id
        JOIN v_product_costing v ON v.product_id = p.product_id
        WHERE p.is_active = 1
          AND NOT EXISTS (
              SELECT 1 FROM product_materials pm
              JOIN materials m ON m.material_id = pm.material_id
              WHERE pm.product_id = p.product_id AND m.is_active = 0
          )
        ORDER BY p.product_id');
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['product_id'];
        $products[$id] = [
            'id' => $id, 'sku' => (string) ($row['sku'] ?? ''),
            'name' => $row['product_name'], 'category' => $row['category_name'] ?? '',
            'price' => (float) $row['selling_price'],
            'labor' => (float) $row['labor_cost'], 'packaging' => (float) $row['packaging_cost'],
            'otherCost' => (float) $row['other_cost'], 'unitCost' => (float) $row['total_cost'],
            'materialCost' => (float) $row['material_cost'],
            'downPaymentPercent' => (float) $row['down_payment_percent'],
            'materials' => [],
        ];
    }
    foreach ($pdo->query('SELECT pm.product_id, pm.material_id, pm.quantity_required,
            m.material_name, m.unit, m.unit_cost, m.quantity_on_hand
        FROM product_materials pm JOIN materials m ON m.material_id = pm.material_id
        ORDER BY pm.product_id, pm.material_id')->fetchAll() as $row) {
        $pid = (int) $row['product_id'];
        if (!isset($products[$pid])) continue;
        $products[$pid]['materials'][] = [
            'id' => sprintf('MAT-%03d', (int) $row['material_id']),
            'name' => $row['material_name'], 'unit' => $row['unit'],
            'qty' => (float) $row['quantity_required'], 'cost' => (float) $row['unit_cost'],
            'stock' => (float) $row['quantity_on_hand'],
        ];
    }

    $orders = [];
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $orderQuery = $pdo->prepare('SELECT o.order_id, o.order_date, o.due_date,
            o.production_status, o.delivery_status, o.delivery_address, o.notes, o.archived_at,
            c.customer_id, c.full_name,
            b.order_total, b.amount_paid, b.balance_due, b.payment_status,
            COALESCE(refunds.refunded_amount,0) AS refunded_amount
        FROM customer_orders o
        JOIN customers c ON c.customer_id = o.customer_id
        JOIN v_order_balances b ON b.order_id = o.order_id
        LEFT JOIN (SELECT order_id,SUM(amount) AS refunded_amount FROM payments WHERE payment_status = \'Refunded\' GROUP BY order_id) refunds ON refunds.order_id=o.order_id
        LEFT JOIN (SELECT order_id FROM customer_orders ORDER BY order_id DESC LIMIT 100) recent ON recent.order_id=o.order_id
        WHERE recent.order_id IS NOT NULL OR (o.due_date < ? AND o.archived_at IS NULL AND o.production_status <> \'Cancelled\' AND o.delivery_status NOT IN (\'Delivered\',\'Returned\'))
        ORDER BY o.order_id DESC');
    $orderQuery->execute([$today]); $rows=$orderQuery->fetchAll();
    foreach ($rows as $r) {
        $id = (int) $r['order_id'];
        $orders[$id] = [
            'id' => sprintf('ORD-%03d', $id), 'rawId' => $id,
            'customer' => $r['full_name'], 'customerId' => sprintf('CUST-%03d', (int) $r['customer_id']),
            'address' => (string) ($r['delivery_address'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'date' => substr((string) $r['order_date'], 0, 10), 'dueDate' => $r['due_date'],
            'status' => $r['production_status'], 'delivery' => $r['delivery_status'],
            'payment' => $r['payment_status'], 'refundedAmount'=>(float)$r['refunded_amount'],
            'total' => (float) $r['order_total'], 'paid' => (float) $r['amount_paid'],
            'balance' => (float) $r['balance_due'], 'archived' => $r['archived_at'] !== null,
            'items' => [], 'materials' => [], 'history'=>[],
            'overdue'=>$r['due_date']!==null && $r['due_date']<$today && $r['production_status']!=='Cancelled' && !in_array($r['delivery_status'],['Delivered','Returned'],true) && $r['archived_at']===null,
        ];
    }
    if ($orders) {
        $ids = array_keys($orders);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $history=$pdo->prepare('SELECT order_id,status_type,old_status,new_status,changed_at FROM order_status_history WHERE order_id IN ('.$in.') ORDER BY changed_at,history_id');
        $history->execute($ids);
        foreach($history->fetchAll() as $h) $orders[(int)$h['order_id']]['history'][]=$h;
        $stmt = $pdo->prepare('SELECT oi.order_id, oi.product_id, p.product_name,
                oi.quantity, oi.unit_price, oi.estimated_unit_cost
            FROM order_items oi JOIN products p ON p.product_id = oi.product_id
            WHERE oi.order_id IN (' . $in . ') ORDER BY oi.order_id, oi.order_item_id');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) {
            $orders[(int) $r['order_id']]['items'][] = [
                'productId' => (int) $r['product_id'], 'product' => $r['product_name'],
                'qty' => (int) $r['quantity'], 'unitPrice' => (float) $r['unit_price'],
                'unitCost' => (float) $r['estimated_unit_cost'],
            ];
        }
        $stmt = $pdo->prepare("SELECT im.order_id, im.material_id, m.material_name, m.unit,
                SUM(-im.quantity_change) AS allocated
            FROM inventory_movements im JOIN materials m ON m.material_id = im.material_id
            WHERE im.order_id IN ($in) AND im.movement_type = 'Production Use'
            GROUP BY im.order_id, im.material_id, m.material_name, m.unit
            ORDER BY im.order_id, im.material_id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) {
            $orders[(int) $r['order_id']]['materials'][] = [
                'id' => sprintf('MAT-%03d', (int) $r['material_id']),
                'name' => $r['material_name'], 'unit' => $r['unit'],
                'qty' => (float) $r['allocated'],
            ];
        }
    }
    ord_out([
        'ok' => true, 'products' => array_values($products), 'orders' => array_values($orders),
        'counts' => ['products' => count($products), 'orders' => count($orders)],
        'csrf' => ord_csrf(),
        'stockPolicy' => 'Material quantities are deducted at order submission, restored only when a pending order is cancelled.',
    ]);
}

function ord_create(PDO $pdo, array $data): array {
    $customer = ord_str($data['customer'] ?? null, 'Customer name', 120);
    $address = ord_str($data['address'] ?? null, 'Delivery address', 1000);
    $productId = ord_id($data['productId'] ?? null, 'product');
    $qty = ord_id($data['quantity'] ?? null, 'quantity');
    if ($qty > 1000) throw new InvalidArgumentException('Maximum order quantity is 1,000 bags.');
    $dueDate = ord_date($data['dueDate'] ?? null);
    $notes = $data['notes'] ?? '';
    if (!is_string($notes) || strlen($notes) > 2000) throw new InvalidArgumentException('Notes must be 2,000 characters or fewer.');
    $requestKey = ord_key($data['requestKey'] ?? null);

    $pdo->beginTransaction();
    // Unique key serializes identical retries, preventing duplicate orders/stock deductions.
    $keyInsert = $pdo->prepare('INSERT IGNORE INTO order_submission_keys (request_key) VALUES (?)');
    $keyInsert->execute([$requestKey]);
    if ($keyInsert->rowCount() === 0) {
        $existing = $pdo->prepare('SELECT order_id FROM order_submission_keys WHERE request_key = ? FOR UPDATE');
        $existing->execute([$requestKey]);
        $existingId = $existing->fetchColumn();
        if (!$existingId) throw new RuntimeException('Order request is already in progress; please refresh.');
        $pdo->commit();
        return ['ok' => true, 'orderId' => sprintf('ORD-%03d', (int) $existingId),
            'message' => 'This order was already saved. No additional stock was deducted.', 'duplicate' => true];
    }

    $stmt = $pdo->prepare('SELECT product_id, product_name, selling_price, labor_cost, packaging_cost, other_cost, is_active
        FROM products WHERE product_id = ? FOR UPDATE');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || !(int) $product['is_active']) throw new InvalidArgumentException('Product is unavailable. Refresh and retry.');
    if ((float) $product['selling_price'] <= 0) throw new InvalidArgumentException('Product selling price must be positive.');

    $stmt = $pdo->prepare('SELECT material_id, quantity_required FROM product_materials
        WHERE product_id = ? ORDER BY material_id');
    $stmt->execute([$productId]);
    $recipe = $stmt->fetchAll();
    if (!$recipe) throw new InvalidArgumentException('This product has no material recipe. Set it up in Products first.');
    $materials = [];
    $materialsCost = 0.0;
    $lock = $pdo->prepare('SELECT material_id, material_name, unit_cost, quantity_on_hand, is_active FROM materials
        WHERE material_id = ? FOR UPDATE');
    foreach ($recipe as $item) {
        $mid = (int) $item['material_id'];
        $lock->execute([$mid]);
        $mat = $lock->fetch();
        if (!$mat) throw new InvalidArgumentException('A product material no longer exists. Refresh and retry.');
        if (!(int)$mat['is_active']) throw new InvalidArgumentException('A product material is inactive. Update the product recipe before accepting new orders.');
        $perBag = (float) $item['quantity_required'];
        $needed = round($perBag * $qty, 2);
        if ($perBag <= 0 || $needed <= 0 || $needed > 1000000) {
            throw new InvalidArgumentException('Recipe quantity is invalid for this order size.');
        }
        if ((float) $mat['quantity_on_hand'] + 0.00001 < $needed) {
            throw new InvalidArgumentException('Not enough ' . $mat['material_name'] . ': need ' . $needed . ', available ' . $mat['quantity_on_hand'] . '.');
        }
        $materialsCost += $perBag * (float) $mat['unit_cost'];
        $materials[] = ['id' => $mid, 'qty' => number_format($needed, 2, '.', '')];
    }
    $unitCost = round($materialsCost + (float) $product['labor_cost'] + (float) $product['packaging_cost'] + (float) $product['other_cost'], 2);
    $unitPrice = number_format((float) $product['selling_price'], 2, '.', '');
    $unitCostStr = number_format($unitCost, 2, '.', '');

    $stmt = $pdo->prepare('INSERT INTO customers (full_name, delivery_address) VALUES (?, ?)');
    $stmt->execute([$customer, $address]);
    $customerId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO customer_orders
            (customer_id, due_date, production_status, delivery_status, delivery_address, notes)
        VALUES (?, ?, 'Pending', 'Not Scheduled', ?, ?)");
    $stmt->execute([$customerId, $dueDate, $address, trim($notes)]);
    $orderId = (int) $pdo->lastInsertId();
    $history=$pdo->prepare('INSERT INTO order_status_history (order_id,status_type,old_status,new_status,notes) VALUES (?,?,NULL,?,?)');
    $history->execute([$orderId,'Production','Pending','Order created']);
    $history->execute([$orderId,'Delivery','Not Scheduled','Order created']);
    $stmt = $pdo->prepare('INSERT INTO order_items
        (order_id, product_id, quantity, unit_price, estimated_unit_cost) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$orderId, $productId, $qty, $unitPrice, $unitCostStr]);

    $decrement = $pdo->prepare('UPDATE materials SET quantity_on_hand = quantity_on_hand - ?
        WHERE material_id = ? AND quantity_on_hand >= ?');
    $movement = $pdo->prepare("INSERT INTO inventory_movements
        (material_id, order_id, movement_type, quantity_change, note)
        VALUES (?, ?, 'Production Use', ?, 'Allocated when order was created')");
    foreach ($materials as $material) {
        $decrement->execute([$material['qty'], $material['id'], $material['qty']]);
        if ($decrement->rowCount() !== 1) throw new InvalidArgumentException('Stock changed during checkout; refresh and retry.');
        $movement->execute([$material['id'], $orderId, '-' . $material['qty']]);
    }
    $stmt = $pdo->prepare('UPDATE order_submission_keys SET order_id = ? WHERE request_key = ?');
    $stmt->execute([$orderId, $requestKey]);
    $pdo->commit();
    return ['ok' => true, 'orderId' => sprintf('ORD-%03d', $orderId),
        'message' => 'Order saved to MySQL. Materials allocated from inventory.',
        'total' => round((float) $unitPrice * $qty, 2), 'duplicate' => false];
}

function ord_cancel(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT production_status, delivery_status, archived_at
        FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new InvalidArgumentException('Order not found.');
    $status = (string) $order['production_status'];
    if ($status === 'Cancelled') {
        $pdo->commit();
        return ['ok' => true, 'message' => 'Order is already marked Cancelled in Production.'];
    }
    if ($order['archived_at'] !== null) throw new InvalidArgumentException('Archived orders cannot be cancelled.');
    if (in_array($order['delivery_status'], ['In Transit', 'Delivered', 'Returned'], true)) {
        throw new InvalidArgumentException('Shipped, delivered or returned orders cannot be cancelled.');
    }
    if (!in_array($status, ['Pending', 'In Production', 'Ready', 'Completed'], true)) {
        throw new InvalidArgumentException('This order cannot be cancelled.');
    }
    // Paid orders require an explicit refund workflow. Never restore stock automatically.
    $paidQuery = $pdo->prepare("SELECT COUNT(*) FROM payments
        WHERE order_id = ? AND payment_status = 'Successful'");
    $paidQuery->execute([$orderId]);
    if ((int) $paidQuery->fetchColumn() > 0) {
        throw new InvalidArgumentException('This order has a recorded payment. Refund/void the payment before cancelling the order.');
    }
    if ($status === 'Pending') {
        $stmt = $pdo->prepare("SELECT material_id, SUM(-quantity_change) AS used_qty
            FROM inventory_movements WHERE order_id = ? AND movement_type = 'Production Use'
            GROUP BY material_id ORDER BY material_id");
        $stmt->execute([$orderId]);
        $materials = $stmt->fetchAll();
        $addBack = $pdo->prepare('UPDATE materials SET quantity_on_hand = quantity_on_hand + ? WHERE material_id = ?');
        $record = $pdo->prepare("INSERT INTO inventory_movements
            (material_id, order_id, movement_type, quantity_change, note)
            VALUES (?, ?, 'Return', ?, 'Restored when pending order was cancelled')");
        foreach ($materials as $r) {
            $qty = number_format((float) $r['used_qty'], 2, '.', '');
            if ((float) $qty <= 0) continue;
            $addBack->execute([$qty, (int) $r['material_id']]);
            if ($addBack->rowCount() !== 1) throw new RuntimeException('Could not restore material stock.');
            $record->execute([(int) $r['material_id'], $orderId, $qty]);
        }
    }
    $stmt = $pdo->prepare("UPDATE customer_orders SET production_status = 'Cancelled' WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $pdo->prepare("INSERT INTO order_status_history (order_id,status_type,old_status,new_status,notes) VALUES (?,'Production',?,'Cancelled','Cancelled from Orders')")->execute([$orderId,$status]);
    $pdo->commit();
    $message = $status === 'Pending'
        ? 'Order marked Cancelled in Production. Reserved materials were returned to inventory.'
        : 'Order marked Cancelled in Production. Materials already used were not returned to inventory.';
    return ['ok' => true, 'message' => $message];
}

/**
 * Advance exactly one stage, with a row lock and expected-state check.
 * The client must include the state it last displayed. A repeated POST
 * will fail rather than accidentally skipping another stage.
 * Stock was already allocated when the order was created: never deduct again.
 */
function ord_advance(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $expectedProduction = $data['expectedProduction'] ?? null;
    $expectedDelivery = $data['expectedDelivery'] ?? null;
    if (!is_string($expectedProduction) || !is_string($expectedDelivery)) {
        throw new InvalidArgumentException('Order status changed. Refresh and retry.');
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT production_status, delivery_status, archived_at
        FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Order not found.');
    if ($row['archived_at'] !== null) throw new InvalidArgumentException('Archived orders cannot be advanced.');
    $production = (string)$row['production_status'];
    $delivery = (string)$row['delivery_status'];
    if ($production !== $expectedProduction || $delivery !== $expectedDelivery) {
        throw new InvalidArgumentException('The order status has already changed. Refresh and check the latest value.');
    }
    if ($production === 'Cancelled' || $delivery === 'Delivered' || $delivery === 'Returned') {
        throw new InvalidArgumentException('This order cannot advance any further.');
    }

    $type = 'Production';
    $oldStatus = $production;
    if (($data['transition'] ?? '') === 'ship') {
        if ($production !== 'Completed' || !in_array($delivery, ['Not Scheduled', 'For Delivery'], true)) {
            throw new InvalidArgumentException('Only completed, unshipped orders can be marked as shipped.');
        }
        $newStatus = 'In Transit';
        $oldStatus = $delivery;
        $column = 'delivery_status';
        $type = 'Delivery';
    } elseif ($production === 'Pending') {
        $newStatus = 'In Production';
        $column = 'production_status';
    } elseif ($production === 'In Production' || $production === 'Ready') {
        $newStatus = 'Completed';
        $column = 'production_status';
    } elseif ($production === 'Completed') {
        if ($delivery !== 'In Transit') throw new InvalidArgumentException('Mark this order as shipped before marking it delivered.');
        // Payments API takes the same order-row lock before recording money.
        // These totals therefore cannot change concurrently with this check.
        $totals = $pdo->prepare('SELECT COALESCE(SUM(quantity * unit_price), 0)
            FROM order_items WHERE order_id = ?');
        $totals->execute([$orderId]);
        $total = (int) round((float)$totals->fetchColumn() * 100);
        $paid = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments
            WHERE order_id = ? AND payment_status = 'Successful'");
        $paid->execute([$orderId]);
        $received = (int) round((float)$paid->fetchColumn() * 100);
        if ($total <= 0 || $received < $total) {
            throw new InvalidArgumentException('Record full payment before marking this order Delivered.');
        }
        $newStatus = 'Delivered';
        $oldStatus = $delivery;
        $column = 'delivery_status';
        $type = 'Delivery';
    } else {
        throw new InvalidArgumentException('Unsupported production status.');
    }

    // Column and new status are fixed constants from the controlled state machine.
    $update = $pdo->prepare('UPDATE customer_orders SET ' . $column . ' = ? WHERE order_id = ?');
    $update->execute([$newStatus, $orderId]);
    $history = $pdo->prepare('INSERT INTO order_status_history
        (order_id, status_type, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)');
    $history->execute([$orderId, $type, $oldStatus, $newStatus, 'Updated from Orders management page']);
    $pdo->commit();
    return ['ok' => true, 'orderId' => sprintf('ORD-%03d', $orderId),
        'status' => $type === 'Production' ? $newStatus : $production,
        'delivery' => $type === 'Delivery' ? $newStatus : $delivery,
        'message' => sprintf('Order %s updated to %s. Saved to MySQL.', sprintf('ORD-%03d', $orderId), $newStatus)];
}

function ord_archive(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $stmt = $pdo->prepare("UPDATE customer_orders SET archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP)
        WHERE order_id = ?");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount() < 1) {
        $stmt = $pdo->prepare('SELECT production_status, archived_at FROM customer_orders WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $current = $stmt->fetch();
        if (!$current) throw new InvalidArgumentException('Order not found.');
        if ($current['archived_at'] === null) throw new RuntimeException('Order could not be archived.');
    }
    return ['ok' => true, 'message' => 'Order archived. No inventory changed.'];
}

function ord_unarchive(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT order_id FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    if ($stmt->fetchColumn() === false) throw new InvalidArgumentException('Order not found.');
    $pdo->prepare('UPDATE customer_orders SET archived_at = NULL WHERE order_id = ?')->execute([$orderId]);
    $pdo->commit();
    return ['ok' => true, 'message' => 'Order removed from archive. Status, payments and inventory were unchanged.'];
}

try {
    $pdo = sbb_db();
    if ($method === 'GET') ord_get($pdo);
    if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        ord_fail('JSON content type required.', 415);
    }
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(ord_csrf(), $token)) ord_fail('Security token expired. Refresh the page.', 403);
    $json = file_get_contents('php://input');
    if (!is_string($json) || strlen($json) > 65536) ord_fail('Request is too large.');
    $data = json_decode($json, true);
    if (!is_array($data)) ord_fail('Invalid request body.');
    $action = $data['action'] ?? '';
    if ($action === 'create_order') ord_out(ord_create($pdo, $data), 201);
    if ($action === 'cancel_order') ord_out(ord_cancel($pdo, $data));
    if ($action === 'archive_order') ord_out(ord_archive($pdo, $data));
    if ($action === 'unarchive_order') ord_out(ord_unarchive($pdo, $data));
    if ($action === 'advance_order') ord_out(ord_advance($pdo, $data));
    ord_fail('Unknown action.');
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ord_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('SBB Orders write API: ' . $e->getMessage());
    ord_fail('Could not save or load orders. Check the PHP server terminal.', 500);
}
