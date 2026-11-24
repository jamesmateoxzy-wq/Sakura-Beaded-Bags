<?php
/**
 * Order Management — Sakura admin frontend.
 * All sample orders and edits are demonstration data until a MySQL API exists.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isDemo = USE_SAMPLE_DATA || (DEMO_MODE && ($_SESSION['user_id'] ?? '') === 'demo');
$pageTitle = 'Order Management';
$pageSubtitle = 'Manage each order, from the first bead to delivery.';
$activePage = 'orders';
$pageCss = 'orders.css';
$pageJs = 'orders-live.js';
$pageBodyClass = 'orders-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="orders" data-demo="<?= $isDemo ? '1' : '0' ?>">
  <div class="orders-toolbar">
    <div class="orders-tabs" role="tablist" aria-label="Order groups">
      <button type="button" class="orders-tab is-active" role="tab" aria-selected="true" data-group="all">All orders (<span id="ordTotal">0</span>)</button>
      <button type="button" class="orders-tab" role="tab" aria-selected="false" data-group="production">Production (<span id="ordProduction">0</span>)</button>
      <button type="button" class="orders-tab" role="tab" aria-selected="false" data-group="archived">Archived</button>
    </div>
    <button type="button" class="btn btn-primary orders-new" id="ordNew" <?= $isDemo ? '' : 'disabled' ?>><?= icon('plus') ?> New Order</button>
  </div>
  <section class="orders-current" aria-labelledby="ordCurrentTitle">
    <div class="orders-current-heading">
      <h2 id="ordCurrentTitle">Current Orders (FIFO)</h2>
      <p>Oldest accepted orders are made first. Select a card to view its details.</p>
    </div>
    <div class="orders-cards" id="ordCards"></div>
  </section>
  <section class="card orders-list" aria-label="All customer orders">
    <div class="orders-filters">
      <label class="orders-search">
        <?= icon('search') ?>
        <span class="sr-only">Search orders or customers</span>
        <input id="ordSearch" type="search" placeholder="Search order or customer">
      </label>
      <label class="orders-status-filter">
        <span class="sr-only">Filter by status</span>
        <select id="ordFilter" class="select">
          <option value="all">Status: All</option>
          <option value="Pending">Status: Pending</option>
          <option value="In Progress">Status: In Progress</option>
          <option value="Completed">Status: Completed</option>
          <option value="Delivered">Status: Delivered</option>
        </select>
      </label>
    </div>
    <div class="table-wrap"><table class="table orders-table">
      <thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Item</th><th>Total</th><th>Production</th><th>Delivery</th><th>Payment</th></tr></thead>
      <tbody id="ordRows"></tbody>
    </table></div>
    <p class="ops-empty" id="ordEmpty" hidden>No orders match your search.</p>
  </section>
  <div class="modal-overlay order-overlay" id="ordCreateModal" role="dialog" aria-modal="true" aria-labelledby="ordModalTitle">
    <div class="modal order-modal order-create-modal">
      <div class="modal-head order-dialog-head">
        <div><h2 id="ordModalTitle">New Order</h2><p>Create a handmade order and add it to the production queue.</p></div>
        <button type="button" class="icon-btn" data-close-modal="ordCreateModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <form id="ordCreateForm">
        <div class="modal-body order-create-body">
          <div class="order-create-grid order-create-top">
            <div class="order-field"><label for="ordId">Order ID · auto</label><input class="input" id="ordId" readonly value="ORD-001"></div>
            <div class="order-field"><label for="ordCustomer">Customer *</label><div class="order-control"><input class="input" id="ordCustomer" maxlength="90" required><button type="button" class="mini-edit" data-focus="ordCustomer" aria-label="Edit customer"><?= icon('edit') ?></button></div></div>
            <div class="order-field"><label for="ordAddress">Address *</label><div class="order-control"><input class="input" id="ordAddress" maxlength="160" required><button type="button" class="mini-edit" data-focus="ordAddress" aria-label="Edit address"><?= icon('edit') ?></button></div></div>
          </div>
          <div class="order-create-grid order-create-middle">
            <div class="order-field order-field-product"><label for="ordProduct">Product *</label><div class="order-control"><select class="select" id="ordProduct" required></select><button type="button" class="mini-edit" data-focus="ordProduct" aria-label="Choose product"><?= icon('edit') ?></button></div></div>
            <div class="order-field"><label for="ordQty">Quantity *</label><input class="input" id="ordQty" type="number" min="1" max="1000" step="1" value="1" required></div>
            <div class="order-field"><label for="ordDueDate">Due date *</label><input class="input" id="ordDueDate" type="date" required></div>
            <div class="order-field order-field-notes"><label for="ordNotes">Notes</label><div class="order-control"><textarea class="textarea" id="ordNotes" rows="2" placeholder="Add order notes"></textarea><button type="button" class="mini-edit" data-focus="ordNotes" aria-label="Edit notes"><?= icon('edit') ?></button></div></div>
          </div>

          <div class="order-bom-head"><div><h3 id="ordBomTitle">Bill of Materials</h3><p class="order-material-meta">Products sets the recipe, labor, packaging and down-payment policy. Orders use those saved values.</p></div></div>
          <div class="order-bom-wrap">
            <table class="order-bom-table"><thead><tr><th>Material</th><th>Qty</th><th>Unit cost</th><th>Subtotal</th><th></th></tr></thead><tbody id="ordBomRows"></tbody></table>
          </div>

          <div class="order-cost-inputs">
            <div class="order-field"><label for="ordLabor">Labor</label><input class="input" id="ordLabor" type="number" min="0" step="0.01" value="0"></div>
            <div class="order-field"><label for="ordPackaging">Packaging</label><input class="input" id="ordPackaging" type="number" min="0" step="0.01" value="0"></div>
            <div class="order-field"><label for="ordDpPolicy">DP Policy</label><div class="order-percent"><input class="input" id="ordDpPolicy" type="number" min="0" max="100" step="1" value="50"><span>%</span></div></div>
          </div>
          <div class="order-total-summary" aria-live="polite">
            <div><small>Unit Cost</small><strong id="ordUnitCost">₱0</strong><small id="ordFifoNote">FIFO queue #01</small></div>
            <div><small>Unit Price</small><strong id="ordUnitPrice">₱0</strong></div>
            <div><small>Total</small><strong id="ordTotalPrice">₱0</strong></div>
            <div><small>Downpayment</small><strong id="ordDownpayment">₱0</strong></div>
          </div>
          <p class="ops-error" id="ordCreateError" role="alert" hidden></p>
        </div>
        <div class="modal-foot order-dialog-foot"><span>* Required fields</span><div><button type="button" class="btn btn-outline" data-close-modal="ordCreateModal">Cancel</button><button type="submit" class="btn btn-primary" <?= $isDemo ? '' : 'disabled' ?>>Submit Order</button></div></div>
      </form>
    </div>
  </div>

  <div class="modal-overlay order-overlay" id="ordMaterialModal" role="dialog" aria-modal="true" aria-labelledby="ordMaterialTitle">
    <div class="modal order-modal order-material-modal">
      <div class="modal-head order-dialog-head">
        <div><h2 id="ordMaterialTitle">Add Material</h2><p id="ordMaterialContext">Adding material to the new order.</p></div>
        <button type="button" class="icon-btn" data-close-modal="ordMaterialModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <div class="modal-body order-material-body">
        <div class="order-field"><label for="ordMaterialSelect">Material *</label><div class="order-control"><select class="select" id="ordMaterialSelect"></select><button type="button" class="mini-edit" data-focus="ordMaterialSelect" aria-label="Choose material"><?= icon('edit') ?></button></div><small class="order-material-meta" id="ordMaterialMeta"></small></div>
        <div class="order-material-grid">
          <div class="order-field"><label for="ordMaterialQty">Quantity (pcs) *</label>          <div class="order-control"><input class="input" id="ordMaterialQty" type="number" min="1" step="1" value="1" required><button type="button" class="mini-edit" data-focus="ordMaterialQty" aria-label="Edit quantity"><?= icon('edit') ?></button></div></div>
          <div class="order-field"><label for="ordMaterialCost">Unit cost · auto</label><input class="input" id="ordMaterialCost" readonly value="₱0.00 / unit"></div>
        </div>
        <div class="order-material-amount"><small>Amount · auto</small><div><strong id="ordMaterialAmount">₱0.00</strong><span id="ordMaterialCalc">1 unit × ₱0.00</span></div></div>
        <div class="order-stock-warning" id="ordStockWarning" hidden><strong>⚠ Low stock after allocation</strong><span id="ordStockWarningText"></span></div>
        <p class="ops-error" id="ordMaterialError" role="alert" hidden></p>
      </div>
      <div class="modal-foot order-dialog-foot"><span>* Required fields</span><div><button type="button" class="btn btn-outline" data-close-modal="ordMaterialModal">Cancel</button><button type="button" class="btn btn-primary" id="ordMaterialAdd" <?= $isDemo ? '' : 'disabled' ?>>Add</button></div></div>
    </div>
  </div>

  <div class="modal-overlay order-overlay" id="ordDetailModal" role="dialog" aria-modal="true" aria-labelledby="ordDetailTitle">
    <div class="modal order-modal order-detail-modal">
      <div class="modal-head order-dialog-head">
        <div><h2 id="ordDetailTitle">Order Details</h2><p class="order-detail-code" id="ordDetailCode"></p></div>
        <button type="button" class="icon-btn" data-close-modal="ordDetailModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <div class="modal-body order-detail-body">
        <input type="hidden" id="ordDetailId">
        <div class="order-customer-block"><h3 id="ordDetailCustomer"></h3><p id="ordDetailAddress"></p></div>
        <div class="order-detail-dates"><div><small>Order date</small><strong id="ordDetailDate"></strong></div><div><small>Due date</small><strong id="ordDetailDueDate"></strong></div></div>
        <div class="order-detail-item">
          <div class="order-detail-product"><strong id="ordDetailProduct"></strong><strong id="ordDetailItemPrice">₱0</strong></div>
          <div class="order-detail-status"><span id="ordDetailProduction" class="badge badge-amber"></span><button type="button" class="order-view-bom" id="ordViewBom">View BOM</button></div>
          <div class="order-detail-bom" id="ordDetailBom" hidden></div>
        </div>
        <div class="order-detail-total"><span>Total</span><strong id="ordDetailTotal">₱0</strong></div>
        <span id="ordDetailPayment" class="badge badge-green"></span>
      </div>
      <div class="modal-foot order-detail-foot"><div><button type="button" class="btn btn-outline order-cancel-btn" id="ordCancel">Cancel Order</button><button type="button" class="btn btn-outline" id="ordArchive">Archive</button></div><button type="button" class="btn btn-primary" data-close-modal="ordDetailModal">Close</button></div>
    </div>
  </div>

  <div class="modal-overlay order-overlay" id="ordStatusConfirmModal" role="dialog" aria-modal="true" aria-labelledby="ordStatusConfirmTitle">
    <div class="modal order-modal order-confirm-modal">
      <div class="modal-head order-dialog-head">
        <div><h2 id="ordStatusConfirmTitle">Confirm status update</h2><p id="ordStatusConfirmOrder"></p></div>
        <button type="button" class="icon-btn" data-close-modal="ordStatusConfirmModal" aria-label="Close"><?= icon('x') ?></button>
      </div>
      <div class="modal-body order-confirm-body">
        <p class="order-confirm-message" id="ordStatusConfirmMessage"></p>
        <div class="order-confirm-transition">
          <div><small id="ordStatusFromLabel">Current status</small><strong id="ordStatusFrom"></strong></div>
          <span aria-hidden="true">&rarr;</span>
          <div><small id="ordStatusToLabel">New status</small><strong id="ordStatusTo"></strong></div>
        </div>
        <p class="order-confirm-note" id="ordStatusConfirmNote" hidden></p>
      </div>
      <div class="modal-foot order-dialog-foot order-confirm-foot">
        <button type="button" class="btn btn-outline" id="ordStatusConfirmCancel" data-close-modal="ordStatusConfirmModal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ordStatusConfirmAccept">Confirm</button>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
