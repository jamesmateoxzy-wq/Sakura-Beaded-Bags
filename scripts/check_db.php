<?php
/** Local CLI smoke test. Must be run from Terminal, not through a browser. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = sbb_db();
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
    $materialCount = (int) $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();

    echo "SUCCESS: PHP connected to MySQL!\n";
    echo "Database: {$dbName}\n";
    echo "Base tables: {$tableCount} (original SBB starter schema has 11)\n";
    echo "Material rows: {$materialCount} (zero is okay if no materials added yet)\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAILED: Could not verify MySQL.\n");
    fwrite(STDERR, "Check that MySQL is running, the SQL was imported, PDO MySQL is enabled, and includes/db.local.php has the right username/password.\n");
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    exit(1);
}
