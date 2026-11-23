<?php
/**
 * Sakura MySQL PDO connection. Credentials are never committed.
 *
 * Local: ignored includes/db.local.php.
 * Hosting: SBB_DB_HOST, SBB_DB_PORT, SBB_DB_NAME, SBB_DB_USER,
 *          SBB_DB_PASSWORD environment variables.
 */
function sbb_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $localPath = __DIR__ . '/db.local.php';
    $localConfig = is_file($localPath) ? require $localPath : [];
    if (!is_array($localConfig)) {
        throw new RuntimeException('includes/db.local.php must return an array.');
    }

    $readEnv = static function (string $key): ?string {
        $value = getenv($key);
        return $value === false ? null : $value;
    };
    $host = $readEnv('SBB_DB_HOST') ?? ($localConfig['host'] ?? '127.0.0.1');
    $port = (int) ($readEnv('SBB_DB_PORT') ?? ($localConfig['port'] ?? 3306));
    $database = $readEnv('SBB_DB_NAME') ?? ($localConfig['database'] ?? 'sakura_beaded_bags');
    $username = $readEnv('SBB_DB_USER') ?? ($localConfig['username'] ?? '');
    $password = $readEnv('SBB_DB_PASSWORD') ?? ($localConfig['password'] ?? '');

    // Do not silently connect with an anonymous user on a new installation.
    if (trim((string) $username) === '') {
        throw new RuntimeException('MySQL credentials missing: set SBB_DB_USER or create includes/db.local.php.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Invalid MySQL port.');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $database)) {
        throw new RuntimeException('Invalid MySQL database name.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
