<?php
require_once __DIR__.'/../includes/final_features.php';
require_once __DIR__.'/../includes/functions.php';
sbb_admin_guard();
$rows=[];$error='';
try { $rows=sbb_db()->query('SELECT actor_email,action_name,created_at FROM sbb_activity ORDER BY activity_id DESC LIMIT 200')->fetchAll(); }
catch(Throwable $e){error_log('SBB activity: '.$e->getMessage());$error='Activity log unavailable. Check the migration and server logs.';}
$pageTitle='Administrator Activity';$pageCss='account.css';
require __DIR__.'/../includes/header.php';
?>
<section class="card"><h2>Recent activity</h2><p class="hint">Most recent 200 recorded administrator actions. Earlier actions before this feature are not reconstructed.</p>
<?php if($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="final-table-scroll"><table class="final-table"><thead><tr><th>Time (server)</th><th>Administrator</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['actor_email']) ?></td><td><?= e($row['action_name']) ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="3">No activity recorded.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
