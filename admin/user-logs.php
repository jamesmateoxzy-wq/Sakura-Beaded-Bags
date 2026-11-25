<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$id=$_SESSION['user_id']??null;
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
$local=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&in_array($host,['127.0.0.1','localhost'],true);
$email=strtolower((string)($_SESSION['user_email']??''));
$allowed=$id==='demo' ? DEMO_MODE&&$local : !empty($id)&&$email!==''&&in_array($email,array_map('strtolower',ALLOWED_ADMIN_EMAILS),true);
if (!$allowed) { http_response_code(403); exit('Authorized administrators only.'); }
header('Cache-Control: no-store');
$rows=[]; $error='';
$search=trim((string)($_GET['q']??''));
try {
    $pdo=sbb_db();
    $pdo->exec("SET time_zone = '+08:00'");
    $stmt=$pdo->prepare("SELECT log_id,user_name,user_email,event_type,sign_in_method,occurred_at FROM user_activity_logs
        WHERE user_name LIKE ? OR user_email LIKE ? ORDER BY log_id DESC LIMIT 200");
    $stmt->execute(['%'.$search.'%','%'.$search.'%']);
    $rows=$stmt->fetchAll();
} catch (Throwable $e) { error_log('SBB user logs: '.$e->getMessage()); $error='Unable to load user logs. Verify the user logs migration and database connection.'; }
$pageTitle='User Logs'; $pageSubtitle='Review administrator sign-in and sign-out activity.';
$activePage=''; $pageCss='account.css';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <div class="card-head"><div><h2>Account Activity</h2><p class="card-sub">Latest 200 matching login and logout events · Philippine time (UTC+8)</p></div></div>
  <form method="get" action="user-logs.php" class="user-logs-filter">
    <div class="field"><label for="logSearch">Search administrator</label><input class="input" name="q" id="logSearch" maxlength="180" value="<?= e($search) ?>" placeholder="Name or email"></div>
    <div class="user-logs-filter-actions"><button class="btn btn-primary" type="submit">Search</button><a class="btn btn-outline" href="user-logs.php">Clear</a></div>
  </form>
  <?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php else: ?>
  <div class="table-wrap" style="overflow-x:auto"><table class="table">
    <thead><tr><th>Log ID</th><th>Administrator</th><th>Email</th><th>Activity</th><th>Sign-in method</th><th>Date and time</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
      <td><?= e(sprintf('LOG-%03d',(int)$row['log_id'])) ?></td>
      <td><?= e($row['user_name']) ?></td><td><?= e($row['user_email'] ?: 'Local demo') ?></td>
      <td><?= e($row['event_type']) ?></td><td><?= e($row['sign_in_method']) ?></td><td><?= e($row['occurred_at']) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6">No matching activity recorded yet.</td></tr><?php endif; ?></tbody>
  </table></div><?php endif; ?>
  <p class="hint">Only explicit successful sign-ins and sign-outs after logging is enabled are recorded. Closing a browser does not count as logout.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
