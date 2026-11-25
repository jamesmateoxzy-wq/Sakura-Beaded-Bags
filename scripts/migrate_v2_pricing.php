<?php
/** Isolated V2 migration: save the selected pricing method. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo=sbb_db();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sakura_beaded_bags_v2_test') throw new RuntimeException('Only the isolated V2 test database is allowed.');
    $exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='pricing_method'")->fetchColumn();
    if (!$exists) $pdo->exec("ALTER TABLE products ADD COLUMN pricing_method ENUM('markup','target') NOT NULL DEFAULT 'markup'");
    echo "SUCCESS: pricing method column ready. Existing product prices are unchanged.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
