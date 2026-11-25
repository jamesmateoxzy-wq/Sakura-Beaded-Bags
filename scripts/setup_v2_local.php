<?php
/** Run all V2 migrations against the dedicated local test database only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $v2Schema = sbb_db()->query('SELECT DATABASE()')->fetchColumn();
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $scripts = [
        'migrate_v2_order_stock.php',
        'migrate_v2_financial_snapshots.php',
        'migrate_v2_inventory_audit.php',
        'migrate_v2_payments.php',
        'migrate_v2_pricing.php',
        'migrate_v2_user_logs.php',
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
