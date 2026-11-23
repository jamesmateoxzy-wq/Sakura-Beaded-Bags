<?php
/**
 * Shared Sakura configuration: safe defaults for every installation.
 *
 * Put per-machine Google settings in includes/config.local.php, copied from
 * config.local.php.example. That local file is .gitignored. The Google OAuth
 * CLIENT ID can be shared, but never commit a CLIENT SECRET or credentials JSON.
 *
 * Only verified Google accounts on the local allowlist can access this
 * internal admin system. Demo access is disabled by default.
 */
$localPath = __DIR__ . '/config.local.php';
$localSettings = is_file($localPath) ? require $localPath : [];
if (!is_array($localSettings)) {
    throw new RuntimeException('includes/config.local.php must return a settings array.');
}

$clientId = trim((string) ($localSettings['google_client_id'] ?? ''));
$rawEmails = $localSettings['allowed_admin_emails'] ?? [];
if (!is_array($rawEmails)) {
    throw new RuntimeException('allowed_admin_emails must be an array.');
}
$allowedEmails = [];
foreach ($rawEmails as $email) {
    if (!is_string($email)) {
        continue;
    }
    $normalized = strtolower(trim($email));
    if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        $allowedEmails[] = $normalized;
    }
}

define('GOOGLE_CLIENT_ID', $clientId);
define('ALLOWED_ADMIN_EMAILS', array_values(array_unique($allowedEmails)));

// Fail closed: do not expose a demo admin bypass on shared installations.
const DEMO_MODE = false;
const USE_SAMPLE_DATA = false;
