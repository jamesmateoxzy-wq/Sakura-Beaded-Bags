<?php
/** V2 local-only migration: preserve the order recipe without deducting stock. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $schema = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $v2Schema = $schema;
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'order_material_snapshots'")->fetchColumn();
    if ($exists) { echo "V2 order snapshot table already exists.\n"; exit(0); }
    if ((int)$pdo->query('SELECT COUNT(*) FROM customer_orders')->fetchColumn() !== 0) throw new RuntimeException('First V2 migration requires an empty order table. Do not delete existing records; use a separate fresh test database.');
    if (!in_array('--apply', $argv, true)) { echo "Preview: create order_material_snapshots in $schema. No rows will change.\n"; exit(0); }
    $pdo->exec("CREATE TABLE order_material_snapshots (
        order_id INT UNSIGNED NOT NULL,
        material_id INT UNSIGNED NOT NULL,
        required_qty DECIMAL(12,2) NOT NULL,
        unit_cost DECIMAL(12,2) NOT NULL,
        PRIMARY KEY (order_id, material_id),
        CONSTRAINT fk_oms_order FOREIGN KEY (order_id) REFERENCES customer_orders(order_id),
        CONSTRAINT fk_oms_material FOREIGN KEY (material_id) REFERENCES materials(material_id)
    ) ENGINE=InnoDB");
    echo "SUCCESS: V2 order material snapshots created in $schema.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration stopped: " . $e->getMessage() . "\n");
    exit(1);
}
