<?php
// Ends the session and returns to the login page.
session_start();
require_once __DIR__ . '/../includes/final_features.php';
sbb_log_success('Account','sign_out');
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;