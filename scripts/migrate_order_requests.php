<?php
/** Create a small idempotency table for Orders. No existing data is changed. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
$apply = in_array('--apply', $argv, true);
try {
    $pdo = sbb_db();
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Database: $schema\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'order_submission_keys'");
    if ((int) $stmt->fetchColumn() === 1) {
        echo "Order request idempotency table already exists. Nothing to do.\n";
        exit(0);
    }
    echo "Proposed change: create order_submission_keys (request_key, order_id, created_at).\n";
    echo "This table prevents retrying the same order submission from creating a second order or deducting stock twice.\n";
    echo "No existing tables/rows will be dropped or changed.\n";
    if (!$apply) {
        echo "Preview only. Back up your database, then rerun with --apply to create it.\n";
        exit(0);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_submission_keys (
        request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        order_id INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_order_request_order FOREIGN KEY (order_id)
            REFERENCES customer_orders(order_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    echo "SUCCESS: order_submission_keys created. No existing order or material changed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration error: ' . $error->getMessage() . "\n");
    exit(1);
}
