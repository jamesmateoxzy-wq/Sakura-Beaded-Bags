<?php
/**
 * ACCOUNT SETTINGS  (frontend)
 * ------------------------------------------------------------
 * Backend teammate: load $settings from a `settings` table and save the two
 * forms below, then remove the data-demo attribute from each <form>.
 * The pricing/payment defaults are meant to pre-fill new products and orders.
 */
require_once __DIR__ . '/../includes/functions.php';

// TODO (backend): load these from the database
$settings = [
    'boutique_name' => 'Sakura Beaded Bags',
    'contact'       => '',
    'address'       => '',
    'labor_rate'    => 100,   // pesos per hour
    'packaging'     => 50,    // pesos per bag
    'markup'        => 100,   // % on cost
    'dp_policy'     => 50,    // required downpayment %
];

$pageTitle    = 'Account Settings';
$pageSubtitle = 'Manage your boutique details and connected account.';
$activePage   = '';
$pageCss      = 'account.css';
$pageJs       = 'account.js';
require_once __DIR__ . '/../includes/header.php';

$userEmail = $_SESSION['user_email'] ?? '';
?>

<div class="settings-stack">

  <!-- ===== Boutique details ===== -->
  <section class="card">
    <div class="card-head">
      <div>
        <h2>Boutique details</h2>
        <p class="card-sub">Shown on payment slips and reports.</p>
      </div>
    </div>
    <form method="post" action="settings.php" data-demo>
      <input type="hidden" name="form" value="boutique">
      <div class="field">
        <label for="boutiqueName">Boutique name <span class="req">*</span></label>
        <input class="input" type="text" id="boutiqueName" name="boutique_name" required maxlength="80" value="<?= e($settings['boutique_name']) ?>">
      </div>
      <div class="form-row">
        <div class="field">
          <label for="contact">Contact number</label>
          <input class="input" type="tel" id="contact" name="contact" placeholder="09XX XXX XXXX"
                 pattern="[0-9+\s\-]{7,20}" title="Use 7 to 20 digits, spaces, + or -" value="<?= e($settings['contact']) ?>">
        </div>
        <div class="field">
          <label for="address">Address</label>
          <input class="input" type="text" id="address" name="address" maxlength="150" placeholder="City, Philippines" value="<?= e($settings['address']) ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="reset" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save Details</button>
      </div>
    </form>
  </section>

  <!-- ===== Pricing and payment defaults ===== -->
  <section class="card">
    <div class="card-head">
      <div>
        <h2>Pricing &amp; payment defaults</h2>
        <p class="card-sub">These values pre-fill new products and orders. You can still change them each time.</p>
      </div>
    </div>
    <form method="post" action="settings.php" data-demo>
      <input type="hidden" name="form" value="defaults">
      <div class="form-row">
        <div class="field">
          <label for="laborRate">Labor rate per hour (₱)</label>
          <input class="input" type="number" id="laborRate" name="labor_rate" min="0" step="0.01" required value="<?= e($settings['labor_rate']) ?>">
        </div>
        <div class="field">
          <label for="packaging">Packaging cost per bag (₱)</label>
          <input class="input" type="number" id="packaging" name="packaging" min="0" step="0.01" required value="<?= e($settings['packaging']) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="markup">Markup on cost (%)</label>
          <input class="input" type="number" id="markup" name="markup" min="0" step="0.01" required value="<?= e($settings['markup']) ?>">
        </div>
        <div class="field">
          <label for="dpPolicy">Required downpayment (%)</label>
          <input class="input" type="number" id="dpPolicy" name="dp_policy" min="0" max="100" step="1" required value="<?= e($settings['dp_policy']) ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="reset" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save Defaults</button>
      </div>
    </form>
  </section>

  <!-- ===== Connected account ===== -->
  <section class="card">
    <div class="card-head">
      <div>
        <h2>Connected account</h2>
        <p class="card-sub">You sign in with Google, so there is no password to manage here.</p>
      </div>
      <span class="badge badge-green"><?= $userEmail ? 'Connected' : 'Demo mode' ?></span>
    </div>
    <div class="connected">
      <div>
        <strong>Google account</strong>
        <p class="hint"><?= e($userEmail ?: 'You are in demo mode, so no Google account is connected.') ?></p>
      </div>
      <a class="btn btn-outline" href="logout.php"><?= icon('logout') ?> Logout</a>
    </div>
    <p class="note"><?= icon('info') ?> To use a different Google account, log out and sign in again.</p>
  </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>