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
$pageSubtitle = '';
$activePage = 'orders';
$pageCss = 'orders.css';
$pageJs = 'orders.js';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="orders" data-demo="<?= $isDemo ? '1' : '0' ?>">
  <section class="ops-stats" aria-label="Order overview">
    <article class="card ops-stat"><span>Total Orders</span><strong id="ordTotal">0</strong><small>All recorded orders</small></article>
    <article class="card ops-stat"><span>Pending</span><strong id="ordPending">0</strong><small>Awaiting production</small></article>
    <article class="card ops-stat"><span>In Progress</span><strong id="ordProgress">0</strong><small>Being handcrafted</small></article>
    <article class="card ops-stat"><span>Completed / Delivered</span><strong id="ordDone">0</strong><small>Ready or fulfilled</small></article>
  </section>
  <section class="card ops-section">
    <div class="ops-heading"><div><h2>Customer Orders</h2><p class="card-sub">Review order details and update production status.</p></div>
      <div class="ops-toolbar"><button type="button" class="btn btn-outline btn-sm" id="ordExport">Export CSV</button>
        <button type="button" class="btn btn-primary" id="ordNew" <?= $isDemo ? '' : 'disabled' ?>><?= icon('plus') ?> New Order</button></div></div>
    <div class="ops-filters">
      <label class="ops-field-search"><span class="sr-only">Search orders</span><input class="input" id="ordSearch" type="search" placeholder="Search order ID, customer or product"></label>
      <label class="sr-only" for="ordFilter">Order status</label>
      <select class="select" id="ordFilter"><option value="all">All statuses</option><option value="Pending">Pending</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option><option value="Delivered">Delivered</option><option value="Cancelled">Cancelled</option></select>
    </div>
    <div class="table-wrap"><table class="table ops-table">
      <thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="ordRows"></tbody>
    </table></div>
    <p class="ops-empty" id="ordEmpty" hidden>No orders match your search.</p><p class="ops-foot" id="ordFoot">0 orders</p>
  </section>
  <div class="modal-overlay" id="ordCreateModal" role="dialog" aria-modal="true" aria-labelledby="ordModalTitle">
    <div class="modal"><div class="modal-head"><div><h2 id="ordModalTitle">Create Order</h2><p>Add a new handcrafted order to the demo.</p></div><button type="button" class="icon-btn" data-close-modal="ordCreateModal" aria-label="Close"><?= icon('x') ?></button></div>
      <form id="ordCreateForm"><div class="modal-body">
        <div class="field"><label for="ordCustomer">Customer full name *</label><input class="input" id="ordCustomer" maxlength="90" required></div>
        <div class="field"><label for="ordProduct">Product name *</label><input class="input" id="ordProduct" maxlength="90" placeholder="e.g. Pearl Heart Bag" required></div>
        <div class="form-row"><div class="field"><label for="ordQty">Quantity *</label><input class="input" id="ordQty" type="number" min="1" max="1000" step="1" value="1" required></div>
        <div class="field"><label for="ordPrice">Unit price (₱) *</label><input class="input" id="ordPrice" type="number" min="0.01" max="1000000" step="0.01" required></div></div>
        <div class="form-row"><div class="field"><label for="ordDate">Order date *</label><input class="input" id="ordDate" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="field"><label for="ordInitialStatus">Initial status</label><select class="select" id="ordInitialStatus"><option>Pending</option><option>In Progress</option></select></div></div>
        <p class="ops-error" id="ordCreateError" role="alert" hidden></p>
      </div><div class="modal-foot"><button type="button" class="btn btn-outline" data-close-modal="ordCreateModal">Cancel</button><button type="submit" class="btn btn-primary">Save Demo Order</button></div></form>
    </div>
  </div>
  <div class="modal-overlay" id="ordDetailModal" role="dialog" aria-modal="true" aria-labelledby="ordDetailTitle">
    <div class="modal"><div class="modal-head"><div><h2 id="ordDetailTitle">Order Details</h2><p>View the order and change its progress.</p></div><button type="button" class="icon-btn" data-close-modal="ordDetailModal" aria-label="Close"><?= icon('x') ?></button></div>
      <form id="ordDetailForm"><div class="modal-body"><input type="hidden" id="ordDetailId"><dl class="ops-details" id="ordDetailData"></dl>
        <div class="field"><label for="ordDetailStatus">Order status</label><select class="select" id="ordDetailStatus" <?= $isDemo ? '' : 'disabled' ?>><option>Pending</option><option>In Progress</option><option>Completed</option><option>Delivered</option><option>Cancelled</option></select></div>
        <p class="ops-error" id="ordDetailError" role="alert" hidden></p>
      </div><div class="modal-foot"><button type="button" class="btn btn-outline" data-close-modal="ordDetailModal">Close</button>
        <button type="submit" class="btn btn-primary" <?= $isDemo ? '' : 'disabled' ?>>Update Demo Status</button></div></form>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
