<?php
/**
 * LOGIN PAGE (frontend) - "Continue with Google"
 * ------------------------------------------------------------
 * Flow:  Google button -> Google account chooser -> Google POSTs a signed
 *        token to google-callback.php -> that file verifies it and starts the session.
 * Settings (Client ID, allowed admin emails) live in ignored includes/config.local.php
 */
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

// Only keep a session if it still belongs to an approved Google admin.
// This also clears any old demo session after demo mode is disabled.
if (!empty($_SESSION['user_id'])) {
    $savedEmail = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    if ($_SESSION['user_id'] !== 'demo'
        && $savedEmail !== ''
        && in_array($savedEmail, ALLOWED_ADMIN_EMAILS, true)) {
        header('Location: dashboard.php');
        exit;
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

// DEMO ONLY (DEMO_MODE in config.php): lets you preview the system before Google is set up
if (DEMO_MODE && isset($_GET['demo'])) {
    $_SESSION['user_id']   = 'demo';
    $_SESSION['user_name'] = 'Sakura Admin';
    $_SESSION['user_role'] = 'Admin';
    header('Location: dashboard.php');
    exit;
}

$error = substr($_GET['error'] ?? '', 0, 200);
$notSetUp = GOOGLE_CLIENT_ID === '' || strpos(GOOGLE_CLIENT_ID, 'PASTE-') === 0;

// Production uses the trusted configured HTTPS origin, never an untrusted Host header.
// Local development keeps the existing 127.0.0.1:8000 callback behavior.
if (SBB_BASE_URL !== '') {
    $loginUri = SBB_BASE_URL . '/admin/google-callback.php';
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $loginUri = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/google-callback.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Sakura Beaded Bags</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/login.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="login-body">

  <section class="login-brand">
    <img src="../assets/images/logo.png" alt="Sakura Beaded Bags" class="login-logo"
         onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'brand-fallback big',textContent:'SAKURA'}))">
    <p>Handcrafted with love, made just for you.</p>
  </section>

  <section class="login-form-side">
    <div class="login-card">
      <h1>Welcome!</h1>
      <p class="login-sub">Sign in with your Google account</p>

      <?php if ($error): ?>
        <div class="form-alert" role="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($notSetUp): ?>
        <div class="form-alert" role="alert">Google sign-in isn’t set up yet. Copy includes/config.local.php.example to includes/config.local.php and add your Client ID.</div>
      <?php endif; ?>

      <div class="google-wrap">
        <div id="googleBtn"
             data-client-id="<?= e(GOOGLE_CLIENT_ID) ?>"
             data-login-uri="<?= e($loginUri) ?>"></div>
      </div>
      <p class="login-note">Only authorized Sakura admin accounts can sign in.</p>

      <?php if (DEMO_MODE): ?>
        <a class="demo-link" href="login.php?demo=1">Preview without Google (demo)</a>
      <?php endif; ?>

      <p class="login-foot">© <?= date('Y') ?> Sakura Beaded Bags. All rights reserved.</p>
    </div>
  </section>

  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <script src="../assets/js/login.js"></script>
</body>
</html>
