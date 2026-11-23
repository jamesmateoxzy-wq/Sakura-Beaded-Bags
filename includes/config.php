<?php
/**
 * Sakura configuration for local development and hosted deployments.
 *
 * Local (ignored) configuration: includes/config.local.php
 * Hosting: set SBB_* environment variables through the hosting provider.
 * Never commit OAuth client secrets, private DB credentials, or credential JSON.
 */
$localPath = __DIR__ . '/config.local.php';
$localSettings = is_file($localPath) ? require $localPath : [];
if (!is_array($localSettings)) {
    throw new RuntimeException('includes/config.local.php must return an array.');
}
$envValue = static function (string $name): ?string {
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        return null;
    }
    return trim($value);
};

$mode = strtolower($envValue('SBB_ENV') ?? (string) ($localSettings['environment'] ?? 'development'));
if (!in_array($mode, ['development', 'production'], true)) {
    throw new RuntimeException('SBB_ENV must be development or production.');
}
$baseUrl = rtrim($envValue('SBB_BASE_URL') ?? (string) ($localSettings['base_url'] ?? ''), '/');
if ($baseUrl !== '' && (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $baseUrl))) {
    throw new RuntimeException('SBB_BASE_URL must be an absolute HTTP(S) URL.');
}
if ($mode === 'production' && ($baseUrl === '' || !str_starts_with(strtolower($baseUrl), 'https://'))) {
    throw new RuntimeException('Production requires SBB_BASE_URL with HTTPS.');
}

$clientId = $envValue('SBB_GOOGLE_CLIENT_ID') ?? (string) ($localSettings['google_client_id'] ?? '');
$clientId = trim($clientId);
$emailList = $envValue('SBB_ALLOWED_ADMIN_EMAILS');
$rawEmails = $emailList !== null
    ? preg_split('/[,;\r\n]+/', $emailList)
    : ($localSettings['allowed_admin_emails'] ?? []);
if (!is_array($rawEmails)) {
    throw new RuntimeException('Approved admin emails must be an array or a comma-separated environment variable.');
}
$allowedEmails = [];
foreach ($rawEmails as $email) {
    if (!is_string($email)) continue;
    $email = strtolower(trim($email));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $allowedEmails[] = $email;
    }
}

define('SBB_ENV', $mode);
define('SBB_BASE_URL', $baseUrl);
define('GOOGLE_CLIENT_ID', $clientId);
define('ALLOWED_ADMIN_EMAILS', array_values(array_unique($allowedEmails)));

// Hard-off in the shared code. Local previews cannot bypass Google sign-in.
const DEMO_MODE = false;
const USE_SAMPLE_DATA = false;
