<?php
/** Backfill eligible archived orders in the isolated V2 test database. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $schema = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $v2Schema = $schema;
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $apply = in_array('--apply', $argv, true);
    $pdo->beginTransaction();
    $orders = $pdo->query("SELECT order_id, production_status, delivery_status FROM customer_orders
        WHERE archived_at IS NULL AND (production_status = 'Cancelled' OR
        (production_status = 'Completed' AND delivery_status = 'Delivered'))
        ORDER BY order_id FOR UPDATE")->fetchAll();
    $balance = $pdo->prepare('SELECT order_total, amount_paid FROM v_order_balances WHERE order_id = ?');
    $update = $pdo->prepare('UPDATE customer_orders SET archived_at = CURRENT_TIMESTAMP WHERE order_id = ? AND archived_at IS NULL');
    $count = 0;
    foreach ($orders as $order) {
        $balance->execute([$order['order_id']]);
        $b = $balance->fetch();
        if (!$b) continue;
        $paid = (int)round((float)$b['amount_paid'] * 100);
        $total = (int)round((float)$b['order_total'] * 100);
        $eligible = $order['production_status'] === 'Cancelled' ? $paid === 0 : ($total > 0 && $paid >= $total);
        if (!$eligible) continue;
        $count++;
        if ($apply) $update->execute([$order['order_id']]);
    }
    if ($apply) $pdo->commit(); else $pdo->rollBack();
    echo ($apply ? 'Archived ' : 'Eligible for archive: ') . $count . " order(s). No payment, stock or order status changed.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Archive backfill stopped: ' . $e->getMessage() . "\n");
    exit(1);
}
