<?php
/**
 * Inventory Management — Sakura admin frontend.
 * Demo changes are browser-session only; production requires a protected PHP/MySQL API.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
$pageTitle = 'Inventory Management';
$pageSubtitle = 'Every bead accounted for, every bag ready to begin.';
$activePage = 'inventory';
$pageCss = 'inventory.css';
$pageJs = 'inventory-live.js';
$pageBodyClass = 'inventory-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="inventory">
  <section class="ops-stats" aria-label="Inventory overview">
    <article class="card inv-stat">
      <div><span>Material Types</span><?= icon('inventory') ?></div>
      <strong id="invTotal">0</strong>
      <small>Materials tracked</small>
    </article>
    <article class="card inv-stat">
      <div><span>Inventory Value</span><?= icon('wallet') ?></div>
      <strong id="invValue">₱0</strong>
      <small>Current stock at unit cost</small>
    </article>
    <article class="card inv-stat">
      <div><span>Low Stock Items</span><?= icon('alert') ?></div>
      <strong id="invLow">0</strong>
      <small>At or below reorder levels</small>
    </article>
    <article class="card inv-stat">
      <div><span>Stock-In This Month</span><?= icon('box') ?></div>
      <strong id="invStockInCount">0</strong>
      <small>Receipts recorded this month</small>
    </article>
  </section>
  <section class="card inv-inventory-panel">
    <div class="inv-toolbar">
      <label class="inv-search">
        <span class="sr-only">Search material ID or name</span>
        <?= icon('search') ?>
        <input type="search" id="invSearch" placeholder="Search material ID or name">
      </label>
      <div class="inv-toolbar-actions">
        <button type="button" class="btn inv-correction-button" id="invOpenCreate" <?= $isDemo ? '' : 'disabled' ?>>
          <?= icon('plus') ?><span>Add<br>Material</span>
        </button>
        <button type="button" class="btn inv-correction-button" id="invOpenCorrection" <?= $isDemo ? '' : 'disabled' ?>>
          <?= icon('sliders') ?><span>Stock<br>Correction</span>
        </button>
        <button type="button" class="btn btn-primary inv-stockin-button" id="invOpenStockIn">
          <?= icon('plus') ?><span>Stock-In</span>
        </button>
      </div>
    </div>
    <div class="inv-table-wrap">
      <table class="inv-table">
        <thead><tr><th>ID</th><th>Name</th><th>Unit</th><th>Cost</th><th>Quantity</th><th>Threshold</th><th>Status</th></tr></thead>
        <tbody id="invRows"></tbody>
      </table>
      <p class="inv-empty" id="invEmpty" hidden>No materials found.</p>
    </div>
  </section>
  <section class="inv-lower-grid">
    <article class="card inv-history-panel">
      <div class="inv-panel-heading">
        <h2>Inventory History</h2>
        <button type="button" id="invHistoryToggle">View all <span aria-hidden="true">→</span></button>
      </div>
      <ul class="inv-history-list" id="invHistoryRows"></ul>
      <p class="inv-empty" id="invHistoryEmpty" hidden>No inventory activity yet.</p>
    </article>
    <article class="card inv-alert-panel">
      <h2>Low Stock Alerts</h2>
      <p>Replenish these before accepting new orders.</p>
      <ul class="inv-alert-list" id="invLowAlerts"></ul>
      <p class="inv-empty" id="invAlertsEmpty" hidden>No low-stock items.</p>
    </article>
  </section>
  <div class="modal-overlay inv-overlay" id="invCreateModal" role="dialog" aria-modal="true" aria-labelledby="invCreateTitle">
    <div class="modal inv-dialog inv-create-dialog">
      <div class="modal-head inv-dialog-head">
        <div><h2 id="invCreateTitle">Add Material</h2><p>Create a material record to begin tracking stock.</p></div>
        <button type="button" class="icon-btn" data-close-modal="invCreateModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <form id="invCreateForm">
        <div class="modal-body inv-dialog-body">
          <div class="inv-dialog-field">
            <label for="invCreateName">Material name *</label>
            <input id="invCreateName" type="text" maxlength="80" required>
          </div>
          <div class="inv-field-row">
            <div class="inv-dialog-field">
              <label for="invCreateUnit">Unit *</label>
              <select id="invCreateUnit" required>
                <option value="pcs">Pieces</option>
                <option value="roll">Roll</option>
                <option value="pack">Pack</option>
                <option value="meter">Meter</option>
                <option value="set">Set</option>
              </select>
            </div>
            <div class="inv-dialog-field">
              <label for="invCreateCost">Unit cost (PHP) *</label>
              <input id="invCreateCost" type="number" min="0" max="1000000" step="0.01" value="0" required>
            </div>
          </div>
          <div class="inv-field-row">
            <div class="inv-dialog-field">
              <label for="invCreateStock">Opening quantity *</label>
              <input id="invCreateStock" type="number" min="0" max="1000000" step="1" value="0" required>
            </div>
            <div class="inv-dialog-field">
              <label for="invCreateMin">Reorder level *</label>
              <input id="invCreateMin" type="number" min="0" max="1000000" step="1" value="0" required>
            </div>
          </div>
          <p class="inv-dialog-error" id="invCreateError" role="alert" hidden></p>
        </div>
        <div class="modal-foot inv-dialog-foot">
          <span>* Required fields</span>
          <div>
            <button type="button" class="btn inv-cancel-button" data-close-modal="invCreateModal">Cancel</button>
            <button type="submit" class="btn btn-primary inv-save-button">Add Material</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="modal-overlay inv-overlay" id="invStockInModal" role="dialog" aria-modal="true" aria-labelledby="invStockInTitle">
    <div class="modal inv-dialog inv-stockin-dialog">
      <div class="modal-head inv-dialog-head">
        <div><h2 id="invStockInTitle">Stock-In</h2><p>Receive a delivery and update the material balance.</p></div>
        <button type="button" class="icon-btn" data-close-modal="invStockInModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <form id="invStockInForm">
        <div class="modal-body inv-dialog-body">
          <div class="inv-dialog-field">
            <label for="invStockInMaterial">Material *</label>
            <div class="inv-control">
              <select id="invStockInMaterial" required></select>
              <span class="inv-select-caret" aria-hidden="true"><?= icon('chevron') ?></span>
              <button type="button" class="inv-edit" data-focus="invStockInMaterial" aria-label="Edit material"><?= icon('edit') ?></button>
            </div>
          </div>
          <div class="inv-balance">
            <span>Current balance</span>
            <strong id="invCurrentBalance">0 pcs</strong>
          </div>
          <div class="inv-field-row">
            <div class="inv-dialog-field">
              <label for="invStockInQty">Received quantity *</label>
              <div class="inv-control inv-quantity-control">
                <input id="invStockInQty" type="number" min="1" max="1000000" step="1" value="1" required>
                <span class="inv-unit" id="invStockInUnit">pcs</span>
                <button type="button" class="inv-edit" data-focus="invStockInQty" aria-label="Edit received quantity"><?= icon('edit') ?></button>
              </div>
            </div>
            <div class="inv-dialog-field">
              <label for="invStockInCost">Catalog unit cost</label>
              <div class="inv-control inv-cost-control">
                <span class="inv-currency">₱</span>
                <input id="invStockInCost" type="number" min="0" max="1000000" step="0.01" value="0.00" required>
                <button type="button" class="inv-edit" data-focus="invStockInCost" aria-label="Edit unit cost"><?= icon('edit') ?></button>
              </div>
            </div>
          </div>
          <div class="inv-dialog-field inv-date-field">
            <label for="invStockInDate">Date *</label>
            <div class="inv-control">
              <input id="invStockInDate" type="text" value="<?= e(date('M j, Y')) ?>" required>
              <button type="button" class="inv-edit" data-focus="invStockInDate" aria-label="Edit date"><?= icon('edit') ?></button>
            </div>
          </div>
          <div class="inv-stockin-summary">
            <span id="invStockInMath">0 pcs + 1 pcs received</span>
            <strong id="invStockInNewBalance">New balance: 1 pcs</strong>
            <small id="invStockInValue">Value: ₱0.00</small>
          </div>
          <p class="inv-dialog-error" id="invStockInError" role="alert" hidden></p>
        </div>
        <div class="modal-foot inv-dialog-foot">
          <span>* Required fields</span>
          <div><button type="button" class="btn inv-cancel-button" data-close-modal="invStockInModal">Cancel</button>
            <button type="submit" class="btn btn-primary inv-save-button">Save Stock-In</button></div>
        </div>
      </form>
    </div>
  </div>
  <div class="modal-overlay" id="invAdjustModal" role="dialog" aria-modal="true" aria-labelledby="invAdjustTitle">
    <div class="modal inv-dialog inv-correction-dialog">
      <div class="modal-head inv-dialog-head">
        <div><h2 id="invAdjustTitle">Stock Correction</h2><p>Record a manual stock balance correction.</p></div>
        <button type="button" class="icon-btn" data-close-modal="invAdjustModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <form id="invAdjustForm">
        <div class="modal-body inv-dialog-body">
          <div class="inv-dialog-field">
            <label for="invAdjustId">Material *</label>
            <select id="invAdjustId" required></select>
          </div>
          <div class="inv-balance"><span>Current balance</span><strong id="invAdjustBalance">0 pcs</strong></div>
          <div class="inv-field-row">
            <div class="inv-dialog-field">
              <label for="invDirection">Correction *</label>
              <select id="invDirection"><option value="in">Add stock (+)</option><option value="out">Remove stock (−)</option></select>
            </div>
            <div class="inv-dialog-field">
              <label for="invAdjustQty">Quantity *</label>
              <input id="invAdjustQty" type="number" min="0.001" max="1000000" step="0.001" value="1" required>
            </div>
          </div>
          <p class="inv-dialog-error" id="invAdjustError" role="alert" hidden></p>
        </div>
        <div class="modal-foot inv-dialog-foot">
          <span>* Required fields</span>
          <div><button type="button" class="btn inv-cancel-button" data-close-modal="invAdjustModal">Cancel</button>
            <button type="submit" class="btn btn-primary inv-save-button">Save Correction</button></div>
        </div>
      </form>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
