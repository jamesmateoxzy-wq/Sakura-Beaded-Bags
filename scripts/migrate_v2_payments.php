<?php
/** Isolated V2 migration: configurable payment methods and order cancellation refund records. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $v2Schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    if (!in_array('--apply', $argv, true)) { echo "Preview: payment methods and order refund ledger. Run with --apply.\n"; exit(0); }
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
        method_name VARCHAR(80) PRIMARY KEY,
        requires_reference TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO payment_methods (method_name,requires_reference) VALUES ('GCash',1),('Bank Transfer',1),('Other',1)");
    $type = $pdo->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payments' AND column_name='payment_method'")->fetchColumn();
    if ($type !== 'varchar') $pdo->exec("ALTER TABLE payments MODIFY payment_method VARCHAR(80) NOT NULL");
    $pdo->exec("INSERT IGNORE INTO payment_methods (method_name,requires_reference) SELECT DISTINCT payment_method,1 FROM payments WHERE payment_method<>''");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_refunds (
        order_id INT UNSIGNED PRIMARY KEY,
        refund_amount DECIMAL(12,2) NOT NULL,
        refunded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(500) NOT NULL,
        CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES customer_orders(order_id)
    ) ENGINE=InnoDB");
    echo "SUCCESS: V2 payment methods and refund ledger are ready. No original payment deleted.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
