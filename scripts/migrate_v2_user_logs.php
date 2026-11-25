<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo=sbb_db();
    $v2Schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($v2Schema !== 'sakura_beaded_bags_v2_test' && !($v2Schema === 'sakura_beaded_bags_v2' && getenv('SBB_ALLOW_CLOUD_V2') === '1')) throw new RuntimeException('Only isolated V2 databases are allowed; cloud V2 requires SBB_ALLOW_CLOUD_V2=1.');
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
        log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(255) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        event_type ENUM('Login','Logout') NOT NULL,
        sign_in_method ENUM('Google','Demo') NOT NULL,
        occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_activity_date (occurred_at,log_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "SUCCESS: V2 user logs table ready.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
