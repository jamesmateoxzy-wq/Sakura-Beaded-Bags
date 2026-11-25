<?php
/** V2 local migration. Legacy balances are unknown, not fabricated. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = sbb_db();
    $v2Schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='inventory_movements' AND column_name=?");
    foreach (['previous_quantity','resulting_quantity'] as $field) {
        $check->execute([$field]);
        if (!(int)$check->fetchColumn()) {
            echo "Adding $field\n";
            $pdo->exec("ALTER TABLE inventory_movements ADD COLUMN $field DECIMAL(12,2) NULL");
        }
    }
    $triggerCheck=$pdo->prepare("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?");
    foreach (['UPDATE'=>'v2_inventory_no_update','DELETE'=>'v2_inventory_no_delete'] as $event=>$name) {
        $triggerCheck->execute([$name]);
        if (!(int)$triggerCheck->fetchColumn()) {
            $pdo->exec("CREATE TRIGGER $name BEFORE $event ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Inventory history is append-only; use a correction movement'");
        }
    }
    echo "SUCCESS: inventory audit columns and append-only triggers ready. Older unknown balances remain NULL.\\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
