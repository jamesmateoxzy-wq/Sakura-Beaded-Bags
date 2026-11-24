<?php
/**
 * Add payment states and material soft deletion without removing existing data.
 *
 * Preview: php scripts/migrate_sales_soft_deletion.php
 * Apply:   php scripts/migrate_sales_soft_deletion.php --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command-line only.');
}

require_once __DIR__ . '/../includes/db.php';

$apply = in_array('--apply', $argv, true);

try {
    $pdo = sbb_db();
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($schema !== 'sakura_beaded_bags') {
        throw new RuntimeException('Unexpected database: ' . $schema . '. Expected sakura_beaded_bags.');
    }

    $tableExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = ?'
    );
    foreach (['customer_orders', 'order_items', 'payments', 'materials'] as $table) {
        $tableExists->execute([$schema, $table, 'BASE TABLE']);
        if ((int) $tableExists->fetchColumn() !== 1) {
            throw new RuntimeException('Required table missing: ' . $table . '. Check the existing database schema.');
        }
    }

    $columnExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $actions = [];

    $columnExists->execute([$schema, 'payments', 'payment_status']);
    if ((int) $columnExists->fetchColumn() === 0) {
        $actions[] = [
            'Add payment status (existing records remain successful)',
            "ALTER TABLE payments ADD COLUMN payment_status
                ENUM('Successful','Cancelled','Refunded') NOT NULL DEFAULT 'Successful' AFTER amount"
        ];
    }

    $columnExists->execute([$schema, 'materials', 'is_active']);
    if ((int) $columnExists->fetchColumn() === 0) {
        $actions[] = [
            'Add material active flag (existing materials remain active)',
            'ALTER TABLE materials ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER low_stock_threshold'
        ];
    }

    $viewSql = <<<'SQL'
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW v_order_balances AS
SELECT o.order_id, o.customer_id, o.order_date, o.production_status, o.delivery_status,
       COALESCE(item_totals.order_total, 0.00) AS order_total,
       COALESCE(receipts.total_paid, 0.00) AS amount_paid,
       GREATEST(COALESCE(item_totals.order_total, 0.00) - COALESCE(receipts.total_paid, 0.00), 0.00) AS balance_due,
       CASE
           WHEN COALESCE(item_totals.order_total, 0.00) = 0 THEN 'No Items'
           WHEN COALESCE(receipts.total_paid, 0.00) = 0 THEN 'Unpaid'
           WHEN COALESCE(receipts.total_paid, 0.00) < item_totals.order_total THEN 'Partial'
           ELSE 'Paid'
       END AS payment_status
FROM customer_orders AS o
LEFT JOIN (
    SELECT order_id, SUM(quantity * unit_price) AS order_total
    FROM order_items GROUP BY order_id
) AS item_totals ON item_totals.order_id = o.order_id
LEFT JOIN (
    SELECT order_id, SUM(amount) AS total_paid
    FROM payments
    WHERE payment_status = 'Successful'
    GROUP BY order_id
) AS receipts ON receipts.order_id = o.order_id
SQL;
    $actions[] = ['Recalculate order balances from successful payments only', $viewSql];

    echo "Sakura Beaded Bags — Sales and Soft-Deletion Migration\n";
    echo 'Database: ' . $schema . "\n";
    echo $apply ? "MODE: APPLY CHANGES\n\n" : "MODE: PREVIEW ONLY, NO DATABASE CHANGES\n\n";

    foreach ($actions as $i => [$label, $sql]) {
        echo ($i + 1) . '. ' . $label;
        if ($apply) {
            $pdo->exec($sql);
            echo " — DONE\n";
        } else {
            echo " — PLANNED\n";
        }
    }

    if ($apply) {
        echo "\nSUCCESS: Existing payments and materials were preserved.\n";
    } else {
        echo "\nNothing changed. Back up sakura_beaded_bags before running with --apply.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "MIGRATION STOPPED: " . $e->getMessage() . "\n");
    fwrite(STDERR, "DDL may auto-commit; after reviewing the state, this migration is safe to re-run.\n");
    exit(1);
}
