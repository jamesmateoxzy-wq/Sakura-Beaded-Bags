<?php
/**
 * Order Management — Sakura admin frontend.
 * Order records are kept in the browser session.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
$pageTitle = 'Order Management';
$pageSubtitle = 'Manage each order, from the first bead to delivery.';
$activePage = 'orders';
$pageCss = 'orders.css';
$pageJs = 'orders.js';
$pageBodyClass = 'orders-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="orders">
  <div class="orders-toolbar">
    <div class="orders-tabs" role="tablist" aria-label="Order groups">
      <button type="button" class="orders-tab is-active" role="tab" aria-selected="true" data-group="all">All orders (<span id="ordTotal">0</span>)</button>
      <button type="button" class="orders-tab" role="tab" aria-selected="false" data-group="production">Production (<span id="ordProduction">0</span>)</button>
      <button type="button" class="orders-tab" role="tab" aria-selected="false" data-group="archived">Archived</button>
    </div>
    <button type="button" class="btn btn-primary orders-new" id="ordNew"><?= icon('plus') ?> New Order</button>
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
          <option value="Cancelled">Status: Cancelled</option>
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
            <div class="order-field"><label for="ordId">Order ID · auto</label><input class="input" id="ordId" readonly></div>
            <div class="order-field"><label for="ordCustomer">Customer *</label><div class="order-control"><input class="input" id="ordCustomer" maxlength="90" required><button type="button" class="mini-edit" data-focus="ordCustomer" aria-label="Edit customer"><?= icon('edit') ?></button></div></div>
            <div class="order-field"><label for="ordAddress">Address *</label><div class="order-control"><input class="input" id="ordAddress" maxlength="160" required><button type="button" class="mini-edit" data-focus="ordAddress" aria-label="Edit address"><?= icon('edit') ?></button></div></div>
          </div>
          <div class="order-create-grid order-create-middle">
            <div class="order-field order-field-product"><label for="ordProduct">Product *</label><div class="order-control"><select class="select" id="ordProduct" required></select><button type="button" class="mini-edit" data-focus="ordProduct" aria-label="Choose product"><?= icon('edit') ?></button></div></div>
            <div class="order-field"><label for="ordQty">Quantity *</label><div class="order-control"><input class="input" id="ordQty" type="number" min="1" max="1000" step="1" value="1" required><button type="button" class="mini-edit" data-focus="ordQty" aria-label="Edit quantity"><?= icon('edit') ?></button></div></div>
            <div class="order-field"><label for="ordDueDate">Due date *</label><div class="order-control"><input class="input" id="ordDueDate" type="date" required><button type="button" class="mini-edit" data-focus="ordDueDate" aria-label="Edit due date"><?= icon('edit') ?></button></div></div>
            <div class="order-field order-field-notes"><label for="ordNotes">Notes</label><div class="order-control"><textarea class="textarea" id="ordNotes" rows="2" maxlength="500" placeholder="Add order notes"></textarea><button type="button" class="mini-edit" data-focus="ordNotes" aria-label="Edit notes"><?= icon('edit') ?></button></div></div>
          </div>

          <div class="order-bom-head"><h3>Bill of Materials</h3></div>
          <div class="order-bom-wrap">
            <table class="order-bom-table"><thead><tr><th>Material</th><th>Qty</th><th>Unit cost</th><th>Subtotal</th></tr></thead><tbody id="ordBomRows"></tbody></table>
          </div>

          <div class="order-cost-inputs">
            <div class="order-field"><label for="ordLabor">Labor</label><input class="input" id="ordLabor" readonly type="number" min="0" step="0.01"></div>
            <div class="order-field"><label for="ordPackaging">Packaging</label><input class="input" id="ordPackaging" readonly type="number" min="0" step="0.01" value="50"></div>
            <div class="order-field"><label for="ordDpPolicy">DP Policy</label><div class="order-percent"><input class="input" id="ordDpPolicy" type="number" required min="0" max="100" step="1" value="50"><span>%</span></div></div>
          </div>
          <div class="order-total-summary" aria-live="polite">
            <div><small>Unit Cost</small><strong id="ordUnitCost">₱0</strong><small id="ordFifoNote">Per bag</small></div>
            <div><small>Unit Price</small><strong id="ordUnitPrice">₱0</strong></div>
            <div><small>Total</small><strong id="ordTotalPrice">₱0</strong></div>
            <div><small>Required deposit</small><strong id="ordDownpayment">₱0</strong></div>
          </div>
          <p class="ops-error" id="ordCreateError" role="alert" hidden></p>
        </div>
        <div class="modal-foot order-dialog-foot"><span>* Required fields</span><div><button type="button" class="btn btn-outline" data-close-modal="ordCreateModal">Cancel</button><button type="submit" class="btn btn-primary">Submit Order</button></div></div>
      </form>
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
          <div class="order-detail-status"><span id="ordDetailProduction" class="badge badge-amber">Pending</span><button type="button" class="order-view-bom" id="ordViewBom">View BOM</button></div>
          <div class="order-detail-bom" id="ordDetailBom" hidden></div>
        </div>
        <div class="order-detail-total"><span>Total</span><strong id="ordDetailTotal">₱0</strong></div>
        <span id="ordDetailPayment" class="badge"></span>
        <p id="ordDetailBalance"></p>
        <p id="ordDetailNotes"></p>
        <a class="btn btn-outline btn-sm" id="ordRecordPayment" href="payments.php">Record Payment</a>
      </div>
      <div class="modal-foot order-detail-foot"><div><button type="button" class="btn btn-primary" id="ordAdvance" hidden></button><button type="button" class="btn btn-outline order-cancel-btn" id="ordCancel">Cancel Order</button><button type="button" class="btn btn-outline" id="ordArchive">Archive</button></div><button type="button" class="btn btn-primary" data-close-modal="ordDetailModal">Close</button></div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
