<?php
/**
 * Add auditable partial refunds and net payment balances.
 *
 * Preview: php scripts/migrate_partial_refunds.php
 * Apply:   php scripts/migrate_partial_refunds.php --apply
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
    foreach (['customer_orders', 'order_items', 'payments'] as $table) {
        $tableExists->execute([$schema, $table, 'BASE TABLE']);
        if ((int) $tableExists->fetchColumn() !== 1) {
            throw new RuntimeException('Required table missing: ' . $table . '. Check the existing database schema.');
        }
    }

    $tableExists->execute([$schema, 'payment_refunds', 'BASE TABLE']);
    $refundsExist = (int) $tableExists->fetchColumn() === 1;
    $createRefunds = <<<'SQL'
CREATE TABLE payment_refunds (
    refund_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    refund_percent DECIMAL(5,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (refund_id),
    UNIQUE KEY uq_payment_refund_request (request_key),
    KEY idx_refund_payment_date (payment_id, created_at),
    CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

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
    SELECT p.order_id, SUM(p.amount - COALESCE(refunds.refunded_amount, 0)) AS total_paid
    FROM payments AS p
    LEFT JOIN (
        SELECT payment_id, SUM(amount) AS refunded_amount
        FROM payment_refunds GROUP BY payment_id
    ) AS refunds ON refunds.payment_id = p.payment_id
    WHERE p.payment_status = 'Successful'
    GROUP BY p.order_id
) AS receipts ON receipts.order_id = o.order_id
SQL;

    echo "Sakura Beaded Bags — Partial Refund Migration\n";
    echo 'Database: ' . $schema . "\n";
    echo $apply ? "MODE: APPLY CHANGES\n\n" : "MODE: PREVIEW ONLY, NO DATABASE CHANGES\n\n";

    if ($refundsExist) {
        echo "1. Partial refund ledger already exists — SKIPPED\n";
    } else {
        echo "1. Create auditable partial refund ledger";
        if ($apply) {
            $pdo->exec($createRefunds);
            echo " — DONE\n";
        } else {
            echo " — PLANNED\n";
        }
    }

    echo "2. Recalculate order balances from net successful payments";
    if ($apply) {
        if (!$refundsExist) {
            $tableExists->execute([$schema, 'payment_refunds', 'BASE TABLE']);
            if ((int) $tableExists->fetchColumn() !== 1) {
                throw new RuntimeException('Refund ledger table was not created.');
            }
        }
        $pdo->exec($viewSql);
        echo " — DONE\n";
    } else {
        echo " — PLANNED\n";
    }

    echo $apply
        ? "\nSUCCESS: Partial refunds will be included in net payment balances.\n"
        : "\nNothing changed. Back up sakura_beaded_bags before running with --apply.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "MIGRATION STOPPED: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Review the database state before retrying; DDL may auto-commit.\n");
    exit(1);
}
