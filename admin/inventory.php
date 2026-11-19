<?php
/**
 * Inventory Management — Sakura admin frontend.
 * Demo changes are browser-session only; production requires a protected PHP/MySQL API.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isDemo = USE_SAMPLE_DATA || (DEMO_MODE && ($_SESSION['user_id'] ?? '') === 'demo');
$pageTitle = 'Inventory Management';
$pageSubtitle = 'Track materials, stock levels and replenishment.';
$activePage = 'inventory';
$pageCss = 'operations.css';
$pageJs = 'operations.js';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="inventory" data-demo="<?= $isDemo ? '1' : '0' ?>">
  <div class="ops-banner" role="status"><strong><?= $isDemo ? 'Demo preview' : 'Database connection pending' ?></strong> &middot;
  <?= $isDemo ? 'Fictitious materials. Changes are saved only in this browser tab, not in MySQL.' : 'Connect the authenticated inventory API to view and update actual stock. Editing is disabled.' ?></div>
  <section class="ops-stats" aria-label="Inventory overview">
    <article class="card ops-stat"><span>Materials</span><strong id="invTotal">0</strong><small>Tracked material types</small></article>
    <article class="card ops-stat"><span>Low Stock</span><strong id="invLow">0</strong><small>At or below reorder point</small></article>
    <article class="card ops-stat"><span>Out of Stock</span><strong id="invOut">0</strong><small>Materials to replenish</small></article>
    <article class="card ops-stat"><span>Inventory Value</span><strong id="invValue">₱0</strong><small>Quantity × unit cost</small></article>
  </section>
  <section class="card ops-section">
    <div class="ops-heading">
      <div><h2>Materials Inventory</h2><p class="card-sub">Monitor stock and record incoming or used materials.</p></div>
      <div class="ops-toolbar">
        <button type="button" class="btn btn-outline btn-sm" id="invExport">Export CSV</button>
        <button type="button" class="btn btn-primary" id="invNew" <?= $isDemo ? '' : 'disabled' ?>><?= icon('plus') ?> Add Material</button>
      </div>
    </div>
    <div class="ops-filters">
      <label class="ops-field-search"><span class="sr-only">Search inventory</span><input class="input" type="search" id="invSearch" placeholder="Search material or ID"></label>
      <label class="sr-only" for="invFilter">Stock status</label>
      <select class="select" id="invFilter"><option value="all">All stock levels</option><option value="low">Low stock</option><option value="out">Out of stock</option><option value="good">In stock</option></select>
    </div>
    <div class="table-wrap"><table class="table ops-table">
      <thead><tr><th>Material ID</th><th>Material</th><th>Unit</th><th>Unit Cost</th><th>On Hand</th><th>Reorder At</th><th>Value</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="invRows"></tbody>
    </table></div>
    <p class="ops-empty" id="invEmpty" hidden>No materials match your search.</p>
    <p class="ops-foot" id="invFoot">0 materials</p>
  </section>
  <div class="modal-overlay" id="invCreateModal" role="dialog" aria-modal="true" aria-labelledby="invModalTitle">
    <div class="modal"><div class="modal-head"><div><h2 id="invModalTitle">Add Material</h2><p>Set the unit, cost and initial stock.</p></div><button type="button" class="icon-btn" data-close-modal="invCreateModal" aria-label="Close"><?= icon('x') ?></button></div>
      <form id="invCreateForm"><div class="modal-body">
        <div class="field"><label for="invName">Material name *</label><input class="input" id="invName" maxlength="80" required></div>
        <div class="form-row"><div class="field"><label for="invUnit">Unit *</label><select class="select" id="invUnit"><option>pcs</option><option>roll</option><option>pack</option><option>meter</option><option>set</option></select></div>
        <div class="field"><label for="invCost">Unit cost (₱) *</label><input class="input" id="invCost" type="number" min="0" max="1000000" step="0.01" value="0" required></div></div>
        <div class="form-row"><div class="field"><label for="invQty">Opening quantity *</label><input class="input" id="invQty" type="number" min="0" max="1000000" step="1" value="0" required></div>
        <div class="field"><label for="invMin">Reorder point *</label><input class="input" id="invMin" type="number" min="0" max="1000000" step="1" value="10" required></div></div>
        <p class="ops-error" id="invCreateError" role="alert" hidden></p>
      </div><div class="modal-foot"><button type="button" class="btn btn-outline" data-close-modal="invCreateModal">Cancel</button><button type="submit" class="btn btn-primary">Add Demo Material</button></div></form>
    </div>
  </div>
  <div class="modal-overlay" id="invAdjustModal" role="dialog" aria-modal="true" aria-labelledby="invAdjustTitle">
    <div class="modal"><div class="modal-head"><div><h2 id="invAdjustTitle">Stock Adjustment</h2><p id="invAdjustDescription">Record stock movement.</p></div><button type="button" class="icon-btn" data-close-modal="invAdjustModal" aria-label="Close"><?= icon('x') ?></button></div>
      <form id="invAdjustForm"><div class="modal-body">
        <input type="hidden" id="invAdjustId">
        <div class="field"><label for="invDirection">Movement *</label><select class="select" id="invDirection"><option value="in">Stock In (+)</option><option value="out">Stock Out (−)</option></select></div>
        <div class="field"><label for="invAdjustQty">Quantity *</label><input class="input" id="invAdjustQty" type="number" min="1" max="1000000" step="1" value="1" required></div>
        <p class="ops-error" id="invAdjustError" role="alert" hidden></p>
      </div><div class="modal-foot"><button type="button" class="btn btn-outline" data-close-modal="invAdjustModal">Cancel</button><button type="submit" class="btn btn-primary">Save Demo Adjustment</button></div></form>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
