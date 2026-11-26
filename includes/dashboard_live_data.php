<?php
/**
 * Dashboard figures from Sakura's existing MySQL tables.
 * Include AFTER $month / $monthName have been calculated in admin/dashboard.php,
 * and BEFORE the $stats array is assembled.
 * Read-only: no stock, order, payment, or database schema modifications.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// The dashboard page itself includes header.php later, but do not run data
// queries for visitors who are not signed in.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $db = sbb_db();
    $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
    if (!$selectedDate) {
        throw new RuntimeException('Invalid dashboard month');
    }
    $start = $selectedDate->format('Y-m-d');
    $end = $selectedDate->modify('+1 month')->format('Y-m-d');

    // Keep order counts independent from item/payment rows to prevent inflated counts.
    $totalsSql = <<<'SQL'
SELECT COUNT(*) AS order_count,
       COALESCE(SUM(CASE WHEN o.production_status <> 'Cancelled'
             AND o.delivery_status <> 'Delivered' THEN 1 ELSE 0 END), 0) AS pending_count
FROM customer_orders AS o
WHERE o.order_date >= ? AND o.order_date < ?
SQL;
    $totalsStmt = $db->prepare($totalsSql);
    $totalsStmt->execute([$start, $end]);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $salesStmt = $db->prepare("SELECT COALESCE(SUM(p.amount - COALESCE(refunds.refunded_amount, 0)), 0)
        FROM payments AS p
        JOIN customer_orders AS o ON o.order_id = p.order_id
        LEFT JOIN (
            SELECT payment_id, SUM(amount) AS refunded_amount
            FROM payment_refunds GROUP BY payment_id
        ) AS refunds ON refunds.payment_id = p.payment_id
        WHERE p.payment_status = 'Successful' AND o.production_status <> 'Cancelled'
          AND p.payment_date >= ? AND p.payment_date < ?");
    $salesStmt->execute([$start, $end]);
    $totalSales = round((float) $salesStmt->fetchColumn(), 2);
    $totalOrders = (int) ($totals['order_count'] ?? 0); // includes cancelled orders
    $pendingOrders = (int) ($totals['pending_count'] ?? 0);

    // The latest three orders in the selected month, including cancelled ones.
    $recentSql = <<<'SQL'
SELECT o.order_id, c.full_name, o.production_status,
       COALESCE(oi.order_total, 0) AS order_total
FROM customer_orders AS o
INNER JOIN customers AS c ON c.customer_id = o.customer_id
LEFT JOIN (
    SELECT order_id, SUM(quantity * unit_price) AS order_total
    FROM order_items GROUP BY order_id
) AS oi ON oi.order_id = o.order_id
WHERE o.order_date >= ? AND o.order_date < ?
ORDER BY o.order_date DESC, o.order_id DESC
LIMIT 3
SQL;
    $recentStmt = $db->prepare($recentSql);
    $recentStmt->execute([$start, $end]);
    $recentOrders = [];
    foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recentOrders[] = [
            'id' => sprintf('ORD-%03d', (int) $row['order_id']),
            'customer' => (string) $row['full_name'],
            'total' => (float) $row['order_total'],
            'status' => (string) $row['production_status'],
        ];
    }

    // Live alerts are based on current stock, not the selected sales month.
    $lowStockRows = $db->query(<<<'SQL'
SELECT material_name, unit, quantity_on_hand
FROM materials
WHERE is_active = 1 AND quantity_on_hand <= low_stock_threshold
ORDER BY quantity_on_hand ASC, material_name ASC
SQL)->fetchAll(PDO::FETCH_ASSOC);
    $lowStockTotal = count($lowStockRows);
    $lowStock = [];
    foreach (array_slice($lowStockRows, 0, 5) as $row) {
        $quantity = (float) $row['quantity_on_hand'];
        $displayQty = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
        $lowStock[] = [
            'name' => (string) $row['material_name'],
            'left' => $displayQty . ' ' . (string) $row['unit'],
        ];
    }

    // Six calendar months ending at the selected month.
    $firstMonth = $selectedDate->modify('-5 months');
    $monthlySales = [];
    $monthKeys = [];
    for ($i = 0; $i < 6; $i++) {
        $date = $firstMonth->modify('+' . $i . ' months');
        $key = $date->format('Y-m');
        $monthKeys[$key] = $date->format('M');
        $monthlySales[$date->format('M')] = 0.0;
    }
    $monthlySql = <<<'SQL'
SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS sales_month,
       COALESCE(SUM(p.amount - COALESCE(refunds.refunded_amount, 0)), 0) AS received_sales
FROM payments AS p
INNER JOIN customer_orders AS o ON o.order_id = p.order_id
LEFT JOIN (
    SELECT payment_id, SUM(amount) AS refunded_amount
    FROM payment_refunds GROUP BY payment_id
) AS refunds ON refunds.payment_id = p.payment_id
WHERE p.payment_status = 'Successful' AND o.production_status <> 'Cancelled'
  AND p.payment_date >= ? AND p.payment_date < ?
GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
ORDER BY sales_month ASC
SQL;
    $monthlyStmt = $db->prepare($monthlySql);
    $monthlyStmt->execute([$firstMonth->format('Y-m-d'), $end]);
    foreach ($monthlyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string) $row['sales_month'];
        if (isset($monthKeys[$key])) {
            $monthlySales[$monthKeys[$key]] = round((float) $row['received_sales'], 2);
        }
    }

    $updatedOn = date('F j, Y');
} catch (Throwable $e) {
    error_log('SBB Dashboard MySQL: ' . $e->getMessage());
    http_response_code(500);
    exit('Dashboard could not load MySQL data. Check your PHP server terminal.');
}
