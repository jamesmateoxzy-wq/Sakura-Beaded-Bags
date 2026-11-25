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
    $db->beginTransaction();
    // Order cohort uses saved selling prices and saved costs, never current catalog pricing.
    $orders = $db->query("SELECT o.order_id AS id, DATE(o.order_date) AS date, o.production_status AS status,
        c.customer_id AS customerId, c.full_name AS customer, b.order_total AS total,
        b.amount_paid AS received, b.balance_due AS balance
        FROM customer_orders o JOIN customers c ON c.customer_id = o.customer_id
        JOIN v_order_balances b ON b.order_id = o.order_id
        WHERE o.production_status <> 'Cancelled' ORDER BY o.order_date, o.order_id")->fetchAll(PDO::FETCH_ASSOC);
    $items = $db->query("SELECT i.order_id AS orderId, i.product_id AS productId, p.product_name AS product,
        i.quantity, i.unit_price AS price, i.estimated_unit_cost AS cost,
        i.material_cost_snapshot AS materialCost, i.labor_cost_snapshot AS laborCost,
        i.other_cost_snapshot AS otherCost
        FROM order_items i JOIN customer_orders o ON o.order_id = i.order_id
        JOIN products p ON p.product_id = i.product_id
        WHERE o.production_status <> 'Cancelled' ORDER BY i.order_id, i.order_item_id")->fetchAll(PDO::FETCH_ASSOC);
    $payments = $db->query("SELECT pay.payment_id AS id, pay.order_id AS orderId,
        DATE(pay.payment_date) AS date, pay.amount
        FROM payments pay JOIN customer_orders o ON o.order_id = pay.order_id
        WHERE pay.payment_status = 'Successful'
        ORDER BY pay.payment_date, pay.payment_id")->fetchAll(PDO::FETCH_ASSOC);
    $expenses = $db->query('SELECT expense_id AS id, expense_date AS date, category, description, amount
        FROM expenses ORDER BY expense_date, expense_id')->fetchAll(PDO::FETCH_ASSOC);
    $refunds = $db->query('SELECT order_id AS orderId, DATE(refunded_at) AS date, refund_amount AS amount FROM order_refunds ORDER BY refunded_at')->fetchAll(PDO::FETCH_ASSOC);
    $db->commit();
    report_reply(['ok' => true, 'orders' => $orders, 'items' => $items, 'payments' => $payments, 'expenses' => $expenses, 'refunds' => $refunds]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('SBB Financial Reports API: ' . $e->getMessage());
    report_reply(['ok' => false, 'error' => 'Could not load financial reports. Check that the V2 snapshot migration is applied.'], 500);
}
