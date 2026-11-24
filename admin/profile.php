<?php
/**
 * MY PROFILE  (frontend)
 * ------------------------------------------------------------
 * Name, email and photo come from the Google sign-in (saved in the session).
 * Backend teammate: save "full_name" and "phone" to the users table, then
 * remove the data-demo attribute from the <form> so it posts for real.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/final_features.php';
sbb_admin_guard();
$notice = ''; $error = '';
try {
    $db = sbb_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sbb_check_token();
        $name = sbb_text($_POST['full_name'] ?? null,80,true);
        $phone = sbb_phone($_POST['phone'] ?? '');
        $db->beginTransaction();
        $db->prepare('INSERT INTO sbb_admin_profiles (google_sub,display_name,phone) VALUES (?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),phone=VALUES(phone)')->execute([$_SESSION['user_id'],$name,$phone]);
        sbb_activity($db,'Updated personal profile');
        $db->commit();
        $_SESSION['user_name']=$name; $_SESSION['user_phone']=$phone;
        header('Location: profile.php?saved=1'); exit;
    }
    $stmt=$db->prepare('SELECT display_name,phone FROM sbb_admin_profiles WHERE google_sub=?');
    $stmt->execute([$_SESSION['user_id']]);
    if ($profile=$stmt->fetch()) { $_SESSION['user_name']=$profile['display_name']; $_SESSION['user_phone']=$profile['phone']; }
    if (isset($_GET['saved'])) $notice='Profile saved.';
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('SBB profile: '.$e->getMessage());
    $error=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Profile could not be loaded or saved. Check the final improvements migration and server logs.';
}

$pageTitle    = 'My Profile';
$pageSubtitle = 'Your account details.';
$activePage   = '';
$pageCss      = 'account.css';
$pageJs       = 'account.js';
require_once __DIR__ . '/../includes/header.php';   // also defines $userName, $userRole, $initials

$userEmail = $_SESSION['user_email'] ?? '';
$picture   = $_SESSION['user_picture'] ?? '';
$phone     = $_SESSION['user_phone'] ?? '';         
$signIn    = $userEmail ? 'Google' : 'Demo mode';
?>

<?php if ($notice): ?><p role="status" class="note"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="note"><?= e($error) ?></p><?php endif; ?>
<div class="account-grid">

  <!-- ===== Summary card ===== -->
  <section class="card profile-card">
    <div class="avatar-lg">
      <?php if ($picture): ?>
        <img src="<?= e($picture) ?>" alt="" referrerpolicy="no-referrer">
      <?php else: ?>
        <?= e($initials) ?>
      <?php endif; ?>
    </div>
    <h2><?= e($userName) ?></h2>
    <p class="email"><?= e($userEmail ?: 'No email in demo mode') ?></p>
    <span class="badge badge-pink"><?= e($userRole) ?></span>
    <ul class="info-list">
      <li><span>Sign-in method</span><strong><?= e($signIn) ?></strong></li>
      <li><span>Role</span><strong><?= e($userRole) ?></strong></li>
      <li><span>Status</span><strong>Active</strong></li>
    </ul>
  </section>

  <!-- ===== Edit form ===== -->
  <section class="card">
    <div class="card-head">
      <div>
        <h2>Personal information</h2>
        <p class="card-sub">Your name and contact details.</p>
      </div>
    </div>

    <form method="post" action="profile.php"><input type="hidden" name="csrf" value="<?= e(sbb_form_token()) ?>">
      <div class="form-row">
        <div class="field">
          <label for="fullName">Full name <span class="req">*</span></label>
          <input class="input" type="text" id="fullName" name="full_name" required maxlength="80" value="<?= e($userName) ?>">
        </div>
        <div class="field">
          <label for="email">Email (from Google)</label>
          <input class="input" type="email" id="email" value="<?= e($userEmail) ?>" readonly>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="phone">Contact number</label>
          <input class="input" type="tel" id="phone" name="phone" placeholder="09XX XXX XXXX"
                 pattern="[0-9+\s\-]{7,20}" title="Use 7 to 20 digits, spaces, + or -" value="<?= e($phone) ?>">
        </div>
        <div class="field">
          <label for="role">Role</label>
          <input class="input" type="text" id="role" value="<?= e($userRole) ?>" readonly>
        </div>
      </div>

      <p class="hint">Your email comes from your Google account, so it can’t be edited here.</p>

      <div class="form-actions">
        <button type="reset" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save Changes</button>
      </div>
    </form>
  </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>