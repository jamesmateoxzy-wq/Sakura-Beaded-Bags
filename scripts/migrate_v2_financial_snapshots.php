<?php
/** Local V2 migration: historical cost components. Never infer old costs from today's prices. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $schema = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $v2Schema = $schema;
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $apply = in_array('--apply', $argv, true);
    $fields = ['material_cost_snapshot','labor_cost_snapshot','other_cost_snapshot'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = ?");
    foreach ($fields as $field) {
        $check->execute([$field]);
        if ((int)$check->fetchColumn() === 0) {
            echo ($apply ? 'Adding ' : 'Would add ') . $field . "\n";
            if ($apply) $pdo->exec("ALTER TABLE order_items ADD COLUMN $field DECIMAL(12,2) NULL");
        }
    }
    echo ($apply ? 'SUCCESS: V2 financial snapshot columns ready.' : 'Preview only; rerun with --apply.') . "\n";
    echo "Existing rows retain their saved total cost. Unknown historical breakdowns remain NULL.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration stopped: ' . $e->getMessage() . "\n");
    exit(1);
}
