<?php
require_once __DIR__ . '/db.php';

// Logging failure must not prevent legitimate login or logout.
function sbb_log_user_activity(string $event): void {
    if (!in_array($event,['Login','Logout'],true) || empty($_SESSION['user_id'])) return;
    $demo = $_SESSION['user_id'] === 'demo';
    try {
        $stmt=sbb_db()->prepare('INSERT INTO user_activity_logs (user_name,user_email,event_type,sign_in_method) VALUES (?,?,?,?)');
        $stmt->execute([substr((string)($_SESSION['user_name']??'Sakura Admin'),0,255),$demo?'':substr((string)($_SESSION['user_email']??''),0,255),$event,$demo?'Demo':'Google']);
    } catch (Throwable $e) { error_log('SBB user activity recording failed: '.$e->getMessage()); }
}
