<?php
/**
 * MY PROFILE  (frontend)
 * ------------------------------------------------------------
 * Name, email and photo come from the Google sign-in (saved in the session).
 * Backend teammate: save "full_name" and "phone" to the users table, then
 * remove the data-demo attribute from the <form> so it posts for real.
 */
require_once __DIR__ . '/../includes/functions.php';

$pageTitle    = 'My Profile';
$pageSubtitle = 'Your account details.';
$activePage   = '';
$pageCss      = 'account.css';
$pageJs       = 'account.js';
require_once __DIR__ . '/../includes/header.php';   // also defines $userName, $userRole, $initials

$userEmail = $_SESSION['user_email'] ?? '';
$picture   = $_SESSION['user_picture'] ?? '';
$phone     = $_SESSION['user_phone'] ?? '';          // TODO (backend): load from the users table
$signIn    = $userEmail ? 'Google' : 'Demo mode';
?>

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

    <form method="post" action="profile.php" data-demo>
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