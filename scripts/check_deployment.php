<?php
/**
 * Hosting preflight: CLI only. Checks configuration without printing secrets.
 *
 * Run: php scripts/check_deployment.php
 * Requires the production SBB_* environment settings on the host.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/config.php';

$failures = 0;
$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$check('PHP 8.2 or newer', PHP_VERSION_ID >= 80200);
$check('Production mode enabled', SBB_ENV === 'production');
$check('HTTPS application URL set', SBB_BASE_URL !== '' && str_starts_with(SBB_BASE_URL, 'https://'));
$check('Google Client ID configured', GOOGLE_CLIENT_ID !== '' && str_ends_with(GOOGLE_CLIENT_ID, '.apps.googleusercontent.com'));
$check('At least one approved admin email', ALLOWED_ADMIN_EMAILS !== []);
$check('PDO MySQL extension available', extension_loaded('pdo_mysql'));
$check('OpenSSL extension available', extension_loaded('openssl'));

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
$check('Google PHP Client available (Composer)', class_exists(\Google\Client::class));

// The DB check runs only if the extension exists. Credentials are never echoed.
if (extension_loaded('pdo_mysql')) {
    try {
        require_once __DIR__ . '/../includes/db.php';
        $pdo = sbb_db();
        $pdo->query('SELECT 1')->fetchColumn();
        $check('MySQL connection works', true);
    } catch (Throwable $e) {
        $check('MySQL connection works', false);
        fwrite(STDERR, 'Check the hosting database settings and permissions.' . PHP_EOL);
    }
}

echo PHP_EOL;
if ($failures > 0) {
    echo $failures . ' preflight check(s) failed. Do not deploy yet.' . PHP_EOL;
    exit(1);
}
echo 'Configuration preflight checks passed. Manual HTTP/security testing is still required.' . PHP_EOL;
