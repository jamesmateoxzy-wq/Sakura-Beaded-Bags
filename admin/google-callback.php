<?php
/**
 * GOOGLE CALLBACK (backend part of the Google login)
 * Google POSTs two fields here after the admin picks an account:
 *   credential     - a signed ID token (JWT)
 *   g_csrf_token   - must match the g_csrf_token cookie (blocks forged requests)
 * We verify the token with Google, check the email is an allowed admin,
 * then start the session. Backend teammate: swap the ALLOWED_ADMIN_EMAILS
 * check for a database lookup when the admins table exists.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';

function fail(string $message): void {
    header('Location: login.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { fail('Please sign in from the login page.'); }

// 1) CSRF check (double-submit cookie)
$cookie = $_COOKIE['g_csrf_token'] ?? '';
$body   = $_POST['g_csrf_token'] ?? '';
if ($cookie === '' || !hash_equals($cookie, $body)) { fail('Sign-in could not be verified. Please try again.'); }

// 2) Validate Google's signed ID token.
// Production MUST use the official Google PHP client library (Composer vendor/).
// Google's tokeninfo endpoint is only used for LOCAL debugging if the package
// has not been installed. Never send production credentials to tokeninfo.
$credential = $_POST['credential'] ?? '';
if (!is_string($credential) || $credential === '') {
    fail('No Google credential was received.');
}
if (GOOGLE_CLIENT_ID === '' || ALLOWED_ADMIN_EMAILS === []) {
    fail('Google sign-in has not been configured by the administrator.');
}

$info = null;
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
if (class_exists(\\Google\\Client::class)) {
    try {
        $googleClient = new \\Google\\Client(['client_id' => GOOGLE_CLIENT_ID]);
        $payload = $googleClient->verifyIdToken($credential);
        if (is_array($payload)) {
            $info = $payload;
        }
    } catch (Throwable $e) {
        error_log('SBB Google token validation failed: ' . $e->getMessage());
    }
} elseif (SBB_ENV === 'development'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    // LOCAL-ONLY fallback for teammates who have not installed Composer.
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $json = @file_get_contents(
        'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
        false,
        $ctx
    );
    $payload = $json ? json_decode($json, true) : null;
    if (is_array($payload)) {
        $info = $payload;
    }
}
if (!is_array($info) || empty($info['email'])) {
    fail('Google could not verify this sign-in. Check the server configuration.');
}

// 3) Make sure the token was made for OUR app and is still valid
if (($info['aud'] ?? '') !== GOOGLE_CLIENT_ID)                                    { fail('This sign-in was not issued for this app.'); }
if (!in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) { fail('Invalid sign-in issuer.'); }
if (($info['email_verified'] ?? '') !== 'true' && ($info['email_verified'] ?? false) !== true)   { fail('Your Google email is not verified.'); }
if ((int)($info['exp'] ?? 0) < time())                                            { fail('This sign-in expired. Please try again.'); }

// 4) Only allowed admins can enter
$email   = strtolower($info['email']);
$allowed = array_map('strtolower', ALLOWED_ADMIN_EMAILS);
if (!in_array($email, $allowed, true)) { fail('This Google account is not authorized as a Sakura admin.'); }

// 5) Start the session
session_regenerate_id(true);
$_SESSION['user_id']      = $info['sub'];
$_SESSION['user_email']   = $email;
$_SESSION['user_name']    = $info['name'] ?? $email;
$_SESSION['user_picture'] = $info['picture'] ?? '';
$_SESSION['user_role']    = 'Admin';
header('Location: dashboard.php');
exit;
