<?php
/**
 * Sakura Beaded Bags - Financial Reports (read-only MySQL API).
 * Sales are successful payment records; profit remains an estimate, not audited accounting.
 * Relies on saved order_items.estimated_unit_cost snapshots, not current product costs.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function report_reply(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$sessionId = $_SESSION['user_id'] ?? null;
$host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array($host, ['127.0.0.1', 'localhost'], true);
$demo = $sessionId === 'demo' && DEMO_MODE && $isLocal;
$email = strtolower((string) ($_SESSION['user_email'] ?? ''));
$allowed = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
$real = $sessionId !== 'demo' && !empty($sessionId) && $email !== '' && in_array($email, $allowed, true);
if (!$demo && !$real) report_reply(['ok' => false, 'error' => 'Sign in as an authorized administrator.'], 403);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    report_reply(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $db = sbb_db();
    // Aggregate items and payments independently to avoid multiplying amounts
    // when an order has more than one item and more than one payment.
    // Exclude cancelled orders and non-successful payments from the report.
    $stmt = $db->query(<<<'SQL'
SELECT o.order_id, DATE(o.order_date) AS order_day, o.production_status,
       COALESCE(items.booked_value, 0.00) AS booked_value,
       COALESCE(items.estimated_cost, 0.00) AS estimated_cost,
       COALESCE(receipts.paid_value, 0.00) AS paid_value
FROM customer_orders AS o
LEFT JOIN (
    SELECT order_id,
           SUM(quantity * unit_price) AS booked_value,
           SUM(quantity * estimated_unit_cost) AS estimated_cost
    FROM order_items
    GROUP BY order_id
) AS items ON items.order_id = o.order_id
LEFT JOIN (
    SELECT order_id, SUM(amount) AS paid_value
    FROM payments
    WHERE payment_status = 'Successful'
    GROUP BY order_id
) AS receipts ON receipts.order_id = o.order_id
WHERE o.production_status <> 'Cancelled'
ORDER BY o.order_date DESC, o.order_id DESC
SQL);

    $orders = [];
    $totals = ['bookedSales' => 0.0, 'totalSales' => 0.0, 'estimatedProductionCost' => 0.0, 'receivedForOrders' => 0.0, 'balanceDue' => 0.0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sale = (float) $row['booked_value'];
        $cost = (float) $row['estimated_cost'];
        $paid = (float) $row['paid_value'];
        $balance = max(0.0, round($sale - $paid, 2));
        $orders[] = [
            'id' => sprintf('ORD-%03d', (int) $row['order_id']),
            'date' => (string) $row['order_day'],
            'status' => (string) $row['production_status'],
            'total' => $sale,
            'estimatedCost' => $cost,
            'received' => $paid,
            'balance' => $balance,
        ];
        $totals['bookedSales'] += $sale;
        $totals['totalSales'] += $paid;
        $totals['estimatedProductionCost'] += $cost;
        $totals['receivedForOrders'] += $paid;
        $totals['balanceDue'] += $balance;
    }

    $paymentStmt = $db->query(<<<'SQL'
SELECT pay.payment_id, pay.order_id, DATE(pay.payment_date) AS payment_day, pay.amount
FROM payments AS pay
INNER JOIN customer_orders AS o ON o.order_id = pay.order_id
WHERE pay.payment_status = 'Successful' AND o.production_status <> 'Cancelled'
ORDER BY pay.payment_date ASC, pay.payment_id ASC
SQL);
    $payments = [];
    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payments[] = [
            'id' => (int) $row['payment_id'],
            'orderId' => sprintf('ORD-%03d', (int) $row['order_id']),
            'date' => (string) $row['payment_day'],
            'amount' => (float) $row['amount'],
        ];
    }

    $expenses = [];
    $operatingTotal = 0.0;
    $stmt = $db->query('SELECT expense_id, expense_date, category, description, amount
        FROM expenses ORDER BY expense_date DESC, expense_id DESC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = (float) $row['amount'];
        $operatingTotal += $amount;
        $expenses[] = [
            'id' => (int) $row['expense_id'],
            'date' => (string) $row['expense_date'],
            'category' => (string) $row['category'],
            'description' => (string) ($row['description'] ?? ''),
            'amount' => $amount,
        ];
    }

    $totals['operatingExpenses'] = $operatingTotal;
    $totals['estimatedProfit'] = $totals['totalSales'] - $totals['estimatedProductionCost'] - $operatingTotal;
    $totals['orderCount'] = count($orders);
    foreach ($totals as $key => $val) {
        if ($key !== 'orderCount') $totals[$key] = round((float) $val, 2);
    }

    report_reply([
        'ok' => true,
        'orders' => $orders,
        'payments' => $payments,
        'expenses' => $expenses,
        'summary' => $totals,
        'methodology' => [
            'sales' => 'Successful payments for non-cancelled orders, grouped by payment date. Cancelled and refunded payments are excluded.',
            'productionCost' => 'Estimated production cost saved on each order item at order creation.',
            'operatingExpenses' => 'Separate entries in the expenses table, grouped by their expense date.',
            'received' => 'Payments received toward selected orders regardless of the payment date.',
            'profit' => 'Estimate: successful payments minus saved estimated production cost and recorded operating expenses. Not audited net income.',
            'limitations' => 'Refunded and cancelled payments are excluded. Taxes, accruals, and automated payroll/expense entry are not included. Do not record production costs in expenses again or they will be double-counted.',
        ],
    ]);
} catch (Throwable $e) {
    error_log('SBB Financial Reports API: ' . $e->getMessage());
    report_reply(['ok' => false, 'error' => 'Could not load financial reports. Check the PHP server terminal.'], 500);
}
