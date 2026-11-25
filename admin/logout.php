<?php
// Ends the session and returns to the login page.
session_start();
require_once __DIR__ . '/../includes/user_activity.php';
sbb_log_user_activity('Logout');
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;