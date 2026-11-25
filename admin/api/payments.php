<?php
/** Sakura Beaded Bags payments ledger: MySQL is the only source of truth. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function pay_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function pay_fail(string $message, int $status = 422): void {
    pay_out(['ok' => false, 'error' => $message], $status);
}
function pay_csrf(): string {
    if (empty($_SESSION['sbb_payment_csrf'])) $_SESSION['sbb_payment_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sbb_payment_csrf'];
}
function pay_id(mixed $v, string $name): int {
    if (!is_int($v) && !is_string($v)) throw new InvalidArgumentException("Invalid $name.");
    if (!preg_match('/^[1-9][0-9]*$/D', (string)$v)) throw new InvalidArgumentException("Invalid $name.");
    $id = filter_var((string)$v, FILTER_VALIDATE_INT);
    if ($id === false || $id > 2147483647) throw new InvalidArgumentException("Invalid $name.");
    return (int)$id;
}
function pay_amount(mixed $v): string {
    if (!is_string($v) && !is_int($v) && !is_float($v)) throw new InvalidArgumentException('Enter a valid payment amount.');
    $s = (string)$v;
    if (!preg_match('/^[0-9]{1,9}(\.[0-9]{1,2})?$/D', $s) || (float)$s <= 0) {
        throw new InvalidArgumentException('Amount must be positive with up to 2 decimal places.');
    }
    return number_format((float)$s, 2, '.', '');
}
function pay_date(mixed $v): string {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)) throw new InvalidArgumentException('Invalid payment date.');
    $tz = new DateTimeZone('Asia/Manila');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v, $tz);
    if (!$d || $d->format('Y-m-d') !== $v || $d > new DateTimeImmutable('today', $tz)) {
        throw new InvalidArgumentException('Payment date cannot be in the future.');
    }
    return $v;
}
function pay_key(mixed $v): string {
    if (!is_string($v) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $v)) {
        throw new InvalidArgumentException('Invalid payment request. Reload this page and try again.');
    }
    return strtolower($v);
}
function pay_cents(string|float|int $v): int { return (int)round((float)$v * 100); }
function pay_code(int $id, string $prefix): string { return $prefix . '-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT); }

$sessionId = $_SESSION['user_id'] ?? null;
$host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true) && in_array($host, ['127.0.0.1','localhost'], true);
$demo = $sessionId === 'demo' && DEMO_MODE && $isLocal;
$email = strtolower((string)($_SESSION['user_email'] ?? ''));
$allowed = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$real = $sessionId !== 'demo' && !empty($sessionId) && $email !== '' && in_array($email, $allowed, true);
if (!$demo && !$real) pay_fail('Sign in as an authorized administrator.', 403);
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST'); pay_fail('Method not allowed.', 405);
}

function pay_read(PDO $pdo): void {
    $rows = $pdo->query("SELECT o.order_id, o.order_date, o.due_date, o.production_status, o.archived_at,
        c.full_name, c.customer_id, b.order_total, b.amount_paid, b.balance_due, b.payment_status, COALESCE(rf.refund_amount,0) AS refund_amount
        FROM customer_orders o JOIN customers c ON c.customer_id = o.customer_id
        JOIN v_order_balances b ON b.order_id = o.order_id
        LEFT JOIN order_refunds rf ON rf.order_id=o.order_id
        ORDER BY o.order_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $orders = []; $byOrder = [];
    $sales = 0; $received = 0; $outstanding = 0; $orderValue = 0;
    foreach ($rows as $r) {
        $id = (int)$r['order_id'];
        $item = [
            'id' => pay_code($id, 'ORD'), 'slipId' => pay_code($id,'SLP'), 'rawId' => $id, 'customer' => (string)$r['full_name'], 'customerId'=>pay_code((int)$r['customer_id'],'CUST'), 'refund'=>(float)$r['refund_amount'],
            'date' => substr((string)$r['order_date'], 0, 10), 'dueDate' => (string)($r['due_date'] ?? ''),
            'total' => (float)$r['order_total'], 'paid' => (float)$r['amount_paid'],
            'balance' => (float)$r['balance_due'], 'status' => (string)$r['production_status'],
            'paymentStatus' => (string)$r['payment_status'], 'archived' => $r['archived_at'] !== null,
        ];
        $orders[] = $item; $byOrder[$id] = $item;
        if ($item['status'] !== 'Cancelled') {
            $orderValue += pay_cents($r['order_total']);
            $sales += pay_cents($r['amount_paid']);
            $received += pay_cents($r['amount_paid']);
            $outstanding += pay_cents($r['balance_due']);
        }
    }
    $payments = []; $running = [];
    $stmt = $pdo->query('SELECT payment_id, order_id, payment_date, amount, payment_status, payment_method, reference_number
        FROM payments ORDER BY payment_date ASC, payment_id ASC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $oid = (int)$r['order_id'];
        if (!isset($byOrder[$oid])) continue;
        $order = $byOrder[$oid];
        $paymentStatus = (string)$r['payment_status'];
        if ($paymentStatus === 'Successful') {
            $running[$oid] = ($running[$oid] ?? 0) + pay_cents($r['amount']);
        }
        $balanceAfter = max(0, pay_cents($order['total']) - ($running[$oid] ?? 0));
        $payments[] = [
            'id' => pay_code((int)$r['payment_id'],'TXN'), 'slipId'=>$order['slipId'], 'rawId' => (int)$r['payment_id'], 'orderStatus'=>$order['status'],
            'orderId' => $order['id'], 'customer' => $order['customer'],
            'total' => $order['total'], 'orderPaid' => $order['paid'],
            'amount' => (float)$r['amount'], 'balanceAfter' => $balanceAfter / 100,
            'method' => (string)$r['payment_method'],
            'reference' => (string)($r['reference_number'] ?? ''),
            'date' => substr((string)$r['payment_date'], 0, 10),
            'paymentStatus' => $paymentStatus,
            'status' => $balanceAfter === 0 ? 'Fully Paid' : 'Downpayment',
        ];
    }
    usort($payments, static fn($a,$b) => $b['rawId'] <=> $a['rawId']);
    pay_out([
        'ok' => true, 'csrf' => pay_csrf(), 'orders' => $orders, 'payments' => $payments,
        'methods'=>$pdo->query("SELECT method_name AS name,requires_reference AS requiresReference FROM payment_methods ORDER BY (method_name='GCash') DESC,method_name")->fetchAll(),
        'summary' => [
            'totalSales' => $sales / 100, 'received' => $received / 100,
            'orderValue' => $orderValue / 100,
            'outstanding' => $outstanding / 100,
            'collectionRate' => $orderValue > 0 ? round($received / $orderValue * 100, 1) : 0,
            'orderCount' => count(array_filter($orders, static fn($r) => $r['status'] !== 'Cancelled')),
        ],
        'note' => 'Total sales include successful payments only; cancelled and refunded payments are excluded.',
    ]);
}

function pay_create(PDO $pdo, array $data): array {
    $orderId = pay_id($data['orderId'] ?? null, 'order');
    $amount = pay_amount($data['amount'] ?? null);
    $date = pay_date($data['date'] ?? null);
    $method = $data['method'] ?? null;
    if (!is_string($method)) throw new InvalidArgumentException('Select a valid payment method.');
    $methodStmt=$pdo->prepare('SELECT method_name,requires_reference FROM payment_methods WHERE method_name=?');
    $methodStmt->execute([$method]);$methodRow=$methodStmt->fetch();
    if(!$methodRow)throw new InvalidArgumentException('Select a valid payment method.');
    $method=$methodRow['method_name'];
    $reference = $data['reference'] ?? '';
    if (!is_string($reference) || strlen(trim($reference)) > 80) throw new InvalidArgumentException('Reference is limited to 80 characters.');
    $reference = trim($reference);
    if ($reference === '' && (int)$methodRow['requires_reference']) throw new InvalidArgumentException('This payment method requires a reference.');
    $key = pay_key($data['requestKey'] ?? null);

    $pdo->beginTransaction();
    $claim = $pdo->prepare('INSERT IGNORE INTO payment_submission_keys (request_key) VALUES (?)');
    $claim->execute([$key]);
    if ($claim->rowCount() === 0) {
        $lookup = $pdo->prepare('SELECT payment_id FROM payment_submission_keys WHERE request_key = ? FOR UPDATE');
        $lookup->execute([$key]);
        $existing = $lookup->fetchColumn();
        if (!$existing) throw new RuntimeException('Payment request still processing. Refresh the page.');
        $pdo->commit();
        return ['ok' => true, 'paymentId' => pay_code((int)$existing, 'TXN'),
            'message' => 'Payment was already saved. No second payment recorded.', 'duplicate' => true];
    }
    // Lock the order BEFORE calculating balance; serializes concurrent payments for the same order.
    $lock = $pdo->prepare('SELECT production_status, DATE(order_date) AS ordered_on FROM customer_orders
        WHERE order_id = ? FOR UPDATE');
    $lock->execute([$orderId]);
    $order = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new InvalidArgumentException('Order does not exist.');
    if ($order['production_status'] === 'Cancelled') throw new InvalidArgumentException('Cannot accept payment for a cancelled order.');
    if ($date < $order['ordered_on']) throw new InvalidArgumentException('Payment date cannot be before order date.');

    $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity * unit_price), 0) FROM order_items WHERE order_id = ?');
    $totalStmt->execute([$orderId]);
    $total = pay_cents((string)$totalStmt->fetchColumn());
    if ($total <= 0) throw new InvalidArgumentException('The order has no billable items.');
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments
        WHERE order_id = ? AND payment_status = 'Successful'");
    $paidStmt->execute([$orderId]);
    $paid = pay_cents((string)$paidStmt->fetchColumn());
    $cents = pay_cents($amount);
    if ($cents > $total - $paid) {
        throw new InvalidArgumentException('Payment exceeds remaining balance of ₱' . number_format(($total-$paid) / 100, 2));
    }
    if ($reference !== '') {
        $dup = $pdo->prepare('SELECT payment_id FROM payments WHERE payment_method = ? AND reference_number = ? LIMIT 1');
        $dup->execute([$method, $reference]);
        if ($dup->fetchColumn()) throw new InvalidArgumentException('That reference is already used with this payment method.');
    }
    $insert = $pdo->prepare('INSERT INTO payments (order_id, payment_date, amount, payment_method, reference_number)
        VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$orderId, $date . ' 12:00:00', $amount, $method, $reference ?: null]);
    $paymentId = (int)$pdo->lastInsertId();
    $mark = $pdo->prepare('UPDATE payment_submission_keys SET payment_id = ? WHERE request_key = ?');
    $mark->execute([$paymentId, $key]);
    $pdo->commit();
    return ['ok' => true, 'paymentId' => pay_code($paymentId,'TXN'), 'slipId'=>pay_code($orderId,'SLP'), 'duplicate' => false,
        'balance' => ($total - $paid - $cents) / 100, 'message' => 'Payment saved successfully.'];
}

function pay_update_status(PDO $pdo, array $data): array {
    $paymentId = pay_id($data['paymentId'] ?? null, 'payment');
    $status = $data['status'] ?? null;
    if (!is_string($status) || !in_array($status, ['Cancelled', 'Refunded'], true)) {
        throw new InvalidArgumentException('Choose Cancelled or Refunded for this payment.');
    }

    $pdo->beginTransaction();
    $findOrder = $pdo->prepare('SELECT order_id FROM payments WHERE payment_id = ?');
    $findOrder->execute([$paymentId]);
    $orderId = $findOrder->fetchColumn();
    if ($orderId === false) throw new InvalidArgumentException('Payment was not found.');

    $lockOrder = $pdo->prepare('SELECT production_status,delivery_status FROM customer_orders WHERE order_id = ? FOR UPDATE');
    $lockOrder->execute([(int)$orderId]);
    $order=$lockOrder->fetch();
    if (!$order) throw new InvalidArgumentException('The payment order was not found.');
    if ($order['production_status']==='Cancelled'||$order['delivery_status']==='Delivered') throw new InvalidArgumentException('Settled order payments are read-only.');

    $lockPayment = $pdo->prepare('SELECT payment_status FROM payments WHERE payment_id = ? FOR UPDATE');
    $lockPayment->execute([$paymentId]);
    $currentStatus = $lockPayment->fetchColumn();
    if ($currentStatus === $status) {
        $pdo->commit();
        return ['ok' => true, 'paymentId' => pay_code($paymentId, 'TXN'),
            'message' => 'Payment is already marked ' . strtolower($status) . '.'];
    }
    if ($currentStatus !== 'Successful') {
        throw new InvalidArgumentException('Only successful payments can be cancelled or refunded.');
    }

    $note = $status === 'Refunded'
        ? 'Marked refunded in Payments & Sales.'
        : 'Marked cancelled in Payments & Sales.';
    $update = $pdo->prepare("UPDATE payments SET payment_status = ?,
        remarks = CONCAT_WS(CHAR(10), NULLIF(remarks, ''), ?) WHERE payment_id = ?");
    $update->execute([$status, $note, $paymentId]);
    $pdo->commit();

    return ['ok' => true, 'paymentId' => pay_code($paymentId, 'TXN'),
        'message' => 'Payment marked ' . strtolower($status) . '. Sales and order balances were recalculated.'];
}

try {
    $pdo = sbb_db();
    if ($method === 'GET') pay_read($pdo);
    if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) pay_fail('JSON required.', 415);
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(pay_csrf(), $token)) pay_fail('Security token expired. Refresh the page.', 403);
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || strlen($raw) > 16384) pay_fail('Request too large.');
    $input = json_decode($raw, true);
    if (!is_array($input)) pay_fail('Invalid JSON.');
    if (($input['action'] ?? '') === 'add_method') {
        $name=$input['name']??null;
        if (!is_string($name)||trim($name)===''||strlen(trim($name))>80||!is_bool($input['requiresReference']??null)) throw new InvalidArgumentException('Invalid payment method.');
        $pdo->prepare('INSERT INTO payment_methods (method_name,requires_reference) VALUES (?,?)')->execute([trim($name),$input['requiresReference']?1:0]);
        pay_out(['ok'=>true,'message'=>'Payment method added.'],201);
    }
    if (($input['action'] ?? '') === 'record_payment') pay_out(pay_create($pdo, $input), 201);
    if (($input['action'] ?? '') === 'update_payment_status') pay_out(pay_update_status($pdo, $input));
    pay_fail('Unknown action.');
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    pay_fail($e->getMessage(), 422);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') pay_fail('Duplicate payment reference or request. Refresh the page.', 409);
    error_log('SBB Payment API: ' . $e->getMessage());
    pay_fail('Could not access payments. Check PHP server terminal.', 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('SBB Payment API: ' . $e->getMessage());
    pay_fail('Could not access payments. Check PHP server terminal.', 500);
}
