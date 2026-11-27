<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = sbb_db();
    $column = $pdo->query("SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'customer_orders' AND column_name = 'is_rush'");
    if ($column->fetchColumn() !== false) {
        echo "Rush Order column already exists.\n";
        exit;
    }

    if (!in_array('--apply', $argv, true)) {
        echo "Preview: add customer_orders.is_rush with Standard as the default.\n";
        echo "Run with --apply to update the database.\n";
        exit;
    }

    $pdo->exec('ALTER TABLE customer_orders ADD COLUMN is_rush TINYINT(1) UNSIGNED NOT NULL DEFAULT 0');
    echo "SUCCESS: Rush Order priority is available. Existing orders are Standard.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Rush Order migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}