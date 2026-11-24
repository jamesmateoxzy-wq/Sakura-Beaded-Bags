<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/db.php';
$sql = [
"CREATE TABLE IF NOT EXISTS sbb_admin_profiles (google_sub VARCHAR(191) PRIMARY KEY, display_name VARCHAR(80) NOT NULL, phone VARCHAR(20) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS sbb_settings (setting_key VARCHAR(64) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS sbb_activity (activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_email VARCHAR(254) NOT NULL, action_name VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
try { $db=sbb_db(); foreach ($sql as $statement) { if (in_array('--apply', $argv, true)) $db->exec($statement); else echo $statement . PHP_EOL; } echo in_array('--apply',$argv,true) ? "Migration complete.\n" : "Preview only; use --apply.\n"; } catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
