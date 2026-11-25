<?php
/** Run all V2 migrations against the dedicated local test database only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    if (sbb_db()->query('SELECT DATABASE()')->fetchColumn() !== 'sakura_beaded_bags_v2_test') throw new RuntimeException('Only the isolated V2 test database is allowed.');
    $scripts = [
        'migrate_v2_order_stock.php',
        'migrate_v2_financial_snapshots.php',
        'migrate_v2_inventory_audit.php',
        'migrate_v2_payments.php',
        'migrate_v2_pricing.php',
        'migrate_product_catalog.php',
        'backfill_v2_order_archive.php',
    ];
    foreach ($scripts as $script) {
        echo "\n--- $script ---\n";
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.DIRECTORY_SEPARATOR.$script).' --apply', $code);
        if ($code !== 0) throw new RuntimeException("Stopped at $script; do not launch V2 until corrected.");
    }
    echo "\nSUCCESS: All V2 local migrations completed.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
