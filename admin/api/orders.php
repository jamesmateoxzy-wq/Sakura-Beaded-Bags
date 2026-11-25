<?php
/**
 * SBB Orders MySQL API. GET catalog/orders; POST create/cancel/archive/advance.
 * Requires scripts/migrate_order_requests.php --apply for idempotency.
 * V2: snapshot the BOM on creation; deduct once when production starts.
 * No customer payments are recorded here.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function ord_out(array $v, int $status = 200): void {
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
            p.packaging_cost, p.other_cost, p.down_payment_percent, p.markup_percent,
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
            'downPaymentPercent' => (float) $row['down_payment_percent'], 'markup' => (float)$row['markup_percent'],
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

    $catalog = [];
    foreach ($pdo->query('SELECT material_id, material_name, unit, unit_cost, quantity_on_hand FROM materials WHERE is_active=1 ORDER BY material_name, material_id')->fetchAll() as $m) {
        $catalog[] = ['id'=>sprintf('MAT-%03d',(int)$m['material_id']), 'name'=>$m['material_name'], 'unit'=>$m['unit'], 'cost'=>(float)$m['unit_cost'], 'stock'=>(float)$m['quantity_on_hand']];
    }
    $orders = [];
    $rows = $pdo->query('SELECT o.order_id, o.order_date, o.due_date,
            o.production_status, o.delivery_status, o.delivery_address, o.notes, o.archived_at,
            c.customer_id, c.full_name, c.phone, COALESCE(rf.refund_amount,0) AS refund_amount,
            b.order_total, b.amount_paid, b.balance_due, b.payment_status
        FROM customer_orders o
        JOIN customers c ON c.customer_id = o.customer_id
        JOIN v_order_balances b ON b.order_id = o.order_id
        LEFT JOIN order_refunds rf ON rf.order_id=o.order_id
        ORDER BY o.order_date ASC, o.order_id ASC')->fetchAll();
    foreach ($rows as $r) {
        $id = (int) $r['order_id'];
        $orders[$id] = [
            'id' => sprintf('ORD-%03d', $id), 'rawId' => $id, 'paymentSlipId' => sprintf('SLP-%03d', $id),
            'customer' => $r['full_name'], 'customerId' => sprintf('CUST-%03d', (int) $r['customer_id']),
            'address' => (string) ($r['delivery_address'] ?? ''), 'phone' => (string) ($r['phone'] ?? ''),
            'timestamp' => (string) $r['order_date'],
            'notes' => (string) ($r['notes'] ?? ''),
            'date' => substr((string) $r['order_date'], 0, 10), 'dueDate' => $r['due_date'],
            'status' => $r['production_status'], 'delivery' => $r['delivery_status'],
            'payment' => $r['payment_status'],
            'total' => (float) $r['order_total'], 'paid' => (float) $r['amount_paid'],
            'balance' => (float) $r['balance_due'], 'refund' => (float)$r['refund_amount'], 'archived' => $r['archived_at'] !== null, 'archivedAt' => $r['archived_at'],
            'items' => [], 'materials' => [],
        ];
    }
    if ($orders) {
        $ids = array_keys($orders);
        $in = implode(',', array_fill(0, count($ids), '?'));
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
    // V2 snapshots show the planned recipe even while an order is pending.
    if ($orders) {
        $stmt = $pdo->prepare("SELECT s.order_id, s.material_id, m.material_name, m.unit, s.required_qty
            FROM order_material_snapshots s JOIN materials m ON m.material_id = s.material_id
            WHERE s.order_id IN ($in) ORDER BY s.order_id, s.material_id");
        $stmt->execute($ids);
        $seen = [];
        foreach ($stmt->fetchAll() as $r) {
            $oid = (int)$r['order_id'];
            if (!isset($seen[$oid])) { $orders[$oid]['materials'] = []; $seen[$oid] = true; }
            $orders[$oid]['materials'][] = [
                'id' => sprintf('MAT-%03d', (int)$r['material_id']),
                'name' => $r['material_name'], 'unit' => $r['unit'], 'qty' => (float)$r['required_qty'],
            ];
        }
    }
    ord_out([
        'ok' => true, 'products' => array_values($products), 'materialCatalog' => $catalog, 'orders' => array_values($orders),
        'counts' => ['products' => count($products), 'orders' => count($orders)],
        'csrf' => ord_csrf(),
        'stockPolicy' => 'Material quantities are deducted only when production starts. Pending orders do not reserve stock.',
    ]);
}

function ord_create(PDO $pdo, array $data): array {
    $customer = ord_str($data['customer'] ?? null, 'Customer name', 120);
    $address = ord_str($data['address'] ?? null, 'Delivery address', 1000);
    $phone = ord_str($data['phone'] ?? null, 'Contact number', 30);
    if (!preg_match('/^[0-9+() .-]{5,30}$/D', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 5) {
        throw new InvalidArgumentException('Enter a valid contact number.');
    }
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
            'message' => 'This order was already saved. No duplicate order was created.', 'duplicate' => true];
    }

    $stmt = $pdo->prepare('SELECT product_id, product_name, selling_price, labor_cost, packaging_cost, other_cost, markup_percent, is_active
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
    $baseline = [];
    foreach ($recipe as $item) $baseline[(int)$item['material_id']] = number_format((float)$item['quantity_required'], 2, '.', '');
    $chosen = $baseline;
    if (array_key_exists('recipe', $data)) {
        if (!is_array($data['recipe']) || count($data['recipe']) < 1 || count($data['recipe']) > 100) throw new InvalidArgumentException('Choose 1 to 100 materials.');
        $chosen = [];
        foreach ($data['recipe'] as $entry) {
            if (!is_array($entry)) throw new InvalidArgumentException('Invalid recipe entry.');
            $mid = ord_id($entry['materialId'] ?? null, 'material');
            $raw = $entry['qty'] ?? null;
            if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string)$raw) || (float)$raw < 0.01 || (float)$raw > 1000000 || isset($chosen[$mid])) throw new InvalidArgumentException('Invalid or duplicate material quantity.');
            $chosen[$mid] = number_format((float)$raw, 2, '.', '');
        }
    }
    ksort($baseline, SORT_NUMERIC); ksort($chosen, SORT_NUMERIC);
    $customized = $chosen !== $baseline;
    $recipe = [];
    foreach ($chosen as $mid=>$amount) $recipe[] = ['material_id'=>$mid,'quantity_required'=>$amount];
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
        $materialsCost += round($perBag * (float) $mat['unit_cost'], 2);
        $materials[] = ['id' => $mid, 'qty' => number_format($needed, 2, '.', ''), 'cost' => $mat['unit_cost']];
    }
    $materialSnapshot = round($materialsCost, 2);
    $laborSnapshot = round((float)$product['labor_cost'], 2);
    $otherSnapshot = round((float)$product['packaging_cost'] + (float)$product['other_cost'], 2);
    $unitCost = round($materialSnapshot + $laborSnapshot + $otherSnapshot, 2);
    $price = $customized ? round($unitCost * (1 + (float)$product['markup_percent']/100), 2) : (float)$product['selling_price'];
    if ($price <= 0 || $price > 100000000 || !is_finite($price)) throw new InvalidArgumentException('Customized selling price is invalid.');
    $unitPrice = number_format($price, 2, '.', '');
    $unitCostStr = number_format($unitCost, 2, '.', '');

    // Reuse an existing customer only when both name and normalized phone match.
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    $find = $pdo->prepare('SELECT customer_id FROM customers WHERE LOWER(TRIM(full_name))=LOWER(TRIM(?)) AND phone=? ORDER BY customer_id LIMIT 1');
    $find->execute([$customer,$phone]);
    $customerId = (int)$find->fetchColumn();
    if (!$customerId) {
        $stmt = $pdo->prepare('INSERT INTO customers (full_name, delivery_address, phone) VALUES (?, ?, ?)');
        $stmt->execute([$customer, $address, $phone]);
        $customerId = (int)$pdo->lastInsertId();
    }
    $stmt = $pdo->prepare("INSERT INTO customer_orders
            (customer_id, due_date, production_status, delivery_status, delivery_address, notes)
        VALUES (?, ?, 'Pending', 'Not Scheduled', ?, ?)");
    $stmt->execute([$customerId, $dueDate, $address, trim($notes)]);
    $orderId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO order_items
        (order_id, product_id, quantity, unit_price, estimated_unit_cost, material_cost_snapshot, labor_cost_snapshot, other_cost_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$orderId, $productId, $qty, $unitPrice, $unitCostStr, $materialSnapshot, $laborSnapshot, $otherSnapshot]);

    $snapshot = $pdo->prepare('INSERT INTO order_material_snapshots (order_id, material_id, required_qty, unit_cost) VALUES (?, ?, ?, ?)');
    foreach ($materials as $material) {
        $snapshot->execute([$orderId, $material['id'], $material['qty'], $material['cost']]);
    }
    $stmt = $pdo->prepare('UPDATE order_submission_keys SET order_id = ? WHERE request_key = ?');
    $stmt->execute([$orderId, $requestKey]);
    $pdo->commit();
    return ['ok' => true, 'orderId' => sprintf('ORD-%03d', $orderId),
        'message' => 'Pending order saved. Inventory will be deducted when production starts.',
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
        return ['ok' => true, 'archived' => $order['archived_at'] !== null, 'message' => 'Order is already cancelled.'];
    }
    if ($order['archived_at'] !== null) throw new InvalidArgumentException('Archived orders cannot be cancelled.');
    if (in_array($order['delivery_status'], ['In Transit', 'Delivered', 'Returned'], true)) {
        throw new InvalidArgumentException('Shipped, delivered or returned orders cannot be cancelled.');
    }
    if (!in_array($status, ['Pending', 'In Production', 'Ready', 'Completed'], true)) {
        throw new InvalidArgumentException('This order cannot be cancelled.');
    }
    // The order row is locked; payment writers take the same lock.
    $paidQuery = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id=? AND payment_status='Successful'");
    $paidQuery->execute([$orderId]);
    $paid = (int)round((float)$paidQuery->fetchColumn()*100);
    $raw = $data['refundAmount'] ?? ($paid===0 ? '0' : null);
    if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) || !preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',(string)$raw)) throw new InvalidArgumentException('Enter the amount actually refunded (0 if none).');
    $refund = (int)round((float)$raw*100);
    if ($refund < 0 || $refund > $paid) throw new InvalidArgumentException('Refund cannot exceed successful payments received.');
    if ($paid > 0 && ($data['refundConfirmed'] ?? false) !== true) throw new InvalidArgumentException('Confirm the refund has already been handled outside SBB.');
    $stmt = $pdo->prepare("SELECT material_id, SUM(-quantity_change) AS used_qty
        FROM inventory_movements WHERE order_id=? AND movement_type IN ('Production Use','Return')
        GROUP BY material_id HAVING SUM(-quantity_change)>0 ORDER BY material_id");
    $stmt->execute([$orderId]);
    $materials = $stmt->fetchAll();
    if ($materials && $status !== 'Pending' && ($data['restoreConfirmed'] ?? false) !== true) throw new InvalidArgumentException('Confirm all deducted materials are physically recoverable before cancelling.');
    $lockStock = $pdo->prepare('SELECT quantity_on_hand FROM materials WHERE material_id=? FOR UPDATE');
    $addBack = $pdo->prepare('UPDATE materials SET quantity_on_hand=quantity_on_hand+? WHERE material_id=?');
    $record = $pdo->prepare("INSERT INTO inventory_movements (material_id,order_id,movement_type,quantity_change,note,previous_quantity,resulting_quantity)
        VALUES (?,?,'Return',?,'Restored on order cancellation',?,?)");
    foreach ($materials as $m) {
        $mid = (int)$m['material_id']; $qty = number_format((float)$m['used_qty'],2,'.','');
        $lockStock->execute([$mid]); $before = $lockStock->fetchColumn();
        if ($before===false) throw new RuntimeException('Material not found.');
        $after = round((float)$before+(float)$qty,2);
        if ($after>1000000) throw new InvalidArgumentException('Restoring materials would exceed maximum stock.');
        $addBack->execute([$qty,$mid]);
        $record->execute([$mid,$orderId,$qty,$before,$after]);
    }
    if ($paid>0) {
        $pdo->prepare('INSERT INTO order_refunds (order_id,refund_amount,note) VALUES (?,?,?)')->execute([$orderId,number_format($refund/100,2,'.',''),'Cancellation settlement recorded manually; original receipts retained']);
    }
    $stmt = $pdo->prepare("UPDATE customer_orders SET production_status = 'Cancelled', archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP) WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $history = $pdo->prepare("INSERT INTO order_status_history (order_id, status_type, old_status, new_status, notes)
        VALUES (?, 'Production', ?, 'Cancelled', 'Cancelled and automatically archived')");
    $history->execute([$orderId, $status]);
    $pdo->commit();
    $message = 'Order cancelled. ' . ($materials ? 'Deducted materials restored. ' : 'No stock needed to be restored. ') . ($paid > 0 ? 'Refund recorded: ₱' . number_format($refund/100,2) . '. ' : '');
    return ['ok' => true, 'archived' => true, 'message' => $message . ' Automatically archived.'];
}

/**
 * Advance exactly one stage, with a row lock and expected-state check.
 * The client must include the state it last displayed. A repeated POST
 * will fail rather than accidentally skipping another stage.
 * V2 stock is deducted atomically only when transitioning from Pending.
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
        $snap = $pdo->prepare('SELECT material_id, required_qty FROM order_material_snapshots WHERE order_id = ? ORDER BY material_id');
        $snap->execute([$orderId]);
        $recipe = $snap->fetchAll();
        // Do not guess the inventory state of pre-V2 orders.
        if (!$recipe) throw new InvalidArgumentException('Order has no V2 material snapshot. Review legacy stock before starting production.');
        $lock = $pdo->prepare('SELECT material_name, quantity_on_hand, is_active FROM materials WHERE material_id = ? FOR UPDATE');
        $decrement = $pdo->prepare('UPDATE materials SET quantity_on_hand = quantity_on_hand - ? WHERE material_id = ? AND quantity_on_hand >= ?');
        $movement = $pdo->prepare("INSERT INTO inventory_movements (material_id, order_id, movement_type, quantity_change, note, previous_quantity, resulting_quantity)
            VALUES (?, ?, 'Production Use', ?, 'Deducted when production started', ?, ?)");
        foreach ($recipe as $item) {
            $mid = (int)$item['material_id'];
            $qty = (string)$item['required_qty'];
            $lock->execute([$mid]);
            $material = $lock->fetch();
            if (!$material || !(int)$material['is_active'] || (float)$qty <= 0) throw new InvalidArgumentException('A required material is unavailable.');
            if ((float)$material['quantity_on_hand'] < (float)$qty) throw new InvalidArgumentException('Insufficient stock for ' . $material['material_name'] . '. Replenish inventory first.');
            $decrement->execute([$qty, $mid, $qty]);
            if ($decrement->rowCount() !== 1) throw new RuntimeException('Stock changed; retry production.');
            $movement->execute([$mid, $orderId, '-' . $qty, $material['quantity_on_hand'], round((float)$material['quantity_on_hand'] - (float)$qty, 2)]);
        }
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
    $autoArchive = $type === 'Delivery' && $newStatus === 'Delivered';
    $update = $pdo->prepare('UPDATE customer_orders SET ' . $column . ' = ?' .
        ($autoArchive ? ', archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP)' : '') . ' WHERE order_id = ?');
    $update->execute([$newStatus, $orderId]);
    $history = $pdo->prepare('INSERT INTO order_status_history
        (order_id, status_type, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)');
    $history->execute([$orderId, $type, $oldStatus, $newStatus, $autoArchive ? 'Delivered, fully paid and automatically archived' : 'Updated from Orders management page']);
    $pdo->commit();
    return ['ok' => true, 'orderId' => sprintf('ORD-%03d', $orderId),
        'status' => $type === 'Production' ? $newStatus : $production,
        'delivery' => $type === 'Delivery' ? $newStatus : $delivery,
        'archived' => $autoArchive,
        'message' => sprintf('Order %s updated to %s.', sprintf('ORD-%03d', $orderId), $newStatus) . ($autoArchive ? ' Automatically archived.' : '')];
}

function ord_archive(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT production_status, delivery_status, archived_at FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new InvalidArgumentException('Order not found.');
    if ($order['archived_at'] === null) {
        $balance = $pdo->prepare('SELECT order_total, amount_paid FROM v_order_balances WHERE order_id = ?');
        $balance->execute([$orderId]);
        $amounts = $balance->fetch();
        $settled = $amounts && (float)$amounts['amount_paid'] == 0.0;
        $paid = $amounts && (float)$amounts['order_total'] > 0 &&
            (float)$amounts['amount_paid'] >= (float)$amounts['order_total'];
        if (!(($order['production_status'] === 'Cancelled' && $settled) ||
            ($order['production_status'] === 'Completed' && $order['delivery_status'] === 'Delivered' && $paid))) {
            throw new InvalidArgumentException('Only settled cancelled orders and fully paid delivered orders can be archived.');
        }
        $pdo->prepare('UPDATE customer_orders SET archived_at = CURRENT_TIMESTAMP WHERE order_id = ?')->execute([$orderId]);
    }
    $pdo->commit();
    return ['ok' => true, 'archived' => true, 'message' => 'Order archived. No inventory changed.'];
}

function ord_unarchive(PDO $pdo, array $data): array {
    $orderId = ord_id($data['orderId'] ?? null, 'order');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT production_status, delivery_status FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new InvalidArgumentException('Order not found.');
    if ($order['production_status'] === 'Cancelled' || $order['delivery_status'] === 'Delivered') {
        throw new InvalidArgumentException('Cancelled and delivered orders stay in the archive for history.');
    }
    // Allow recovery of active orders manually archived by the older version.
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
