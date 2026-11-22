<?php
/** Apply once after backing up database: php scripts/migrate_payment_requests.php --apply */
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($db !== 'sakura_beaded_bags') throw new RuntimeException('Unexpected database.');
    $dup = $pdo->query("SELECT payment_method, reference_number, COUNT(*) AS n FROM payments
        WHERE reference_number IS NOT NULL AND reference_number <> ''
        GROUP BY payment_method, reference_number HAVING n > 1 LIMIT 1")->fetch();
    echo "Database: $db\n";
    echo "Proposed: create payment_submission_keys; ensure unique (payment_method, reference_number) index on payments.\n";
    echo "No existing payment or inventory rows are modified.\n";
    if ($dup) throw new RuntimeException('Existing duplicate payment references need review before enforcing uniqueness.');
    $exists = (bool) $pdo->query("SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'payment_submission_keys'")->fetchColumn();
    $index = (bool) $pdo->query("SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'ux_payments_method_ref'")->fetchColumn();
    echo 'Submission-key table: ' . ($exists ? 'exists' : 'missing') . "; unique reference index: " . ($index ? 'exists' : 'missing') . "\n";
    if (!in_array('--apply', $argv, true)) {
        echo "Preview only. Back up database, then rerun with --apply.\n";
        exit;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_submission_keys (
        request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL PRIMARY KEY,
        payment_id INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_paykey_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE RESTRICT,
        INDEX idx_paykey_created (created_at)
    ) ENGINE=InnoDB");
    if (!$index) {
        $pdo->exec('CREATE UNIQUE INDEX ux_payments_method_ref ON payments(payment_method, reference_number)');
    }
    echo "SUCCESS: payment request safety objects are ready; existing data preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
