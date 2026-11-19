<?php
/**
 * Financial Reports — Sakura admin frontend.
 * Fictitious order and payment summary; NOT reconciled accounting information.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isDemo = USE_SAMPLE_DATA || (DEMO_MODE && ($_SESSION['user_id'] ?? '') === 'demo');
$pageTitle = 'Financial Reports';
$pageSubtitle = '';
$activePage = 'reports';
$pageCss = 'reports.css';
$pageJs = 'reports.js';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="reports" data-demo="<?= $isDemo ? '1' : '0' ?>">
  <div class="ops-report-toolbar">
    <div><h2>Business Performance</h2><p class="card-sub">Order value is not the same as received payments.</p></div>
    <div class="ops-toolbar"><label class="sr-only" for="repPeriod">Report period</label><select class="select" id="repPeriod"><option value="all">All dates</option></select>
      <button type="button" class="btn btn-outline btn-sm" id="repExport">Export CSV</button>
      <button type="button" class="btn btn-primary" id="repPrint">Print Report</button></div>
  </div>
  <section class="ops-stats" aria-label="Financial report summary">
    <article class="card ops-stat"><span>Order Value</span><strong id="repSales">₱0</strong><small id="repOrdersNote">0 orders</small></article>
    <article class="card ops-stat"><span>Payments Received</span><strong id="repReceived">₱0</strong><small>Recorded demo collections</small></article>
    <article class="card ops-stat"><span>Unpaid Balance</span><strong id="repDue">₱0</strong><small>Order value less payments</small></article>
    <article class="card ops-stat"><span>Inventory Value</span><strong id="repInventory">₱0</strong><small>Current material stock value</small></article>
  </section>
  <div class="ops-report-grid">
    <section class="card ops-section"><div class="ops-heading"><div><h2>Monthly Order Value</h2><p class="card-sub">Based on the order creation date.</p></div></div>
      <div class="ops-bars" id="repBars" role="img" aria-label="Monthly order value chart"></div><p class="ops-empty" id="repChartEmpty" hidden>No orders in this period.</p>
    </section>
    <section class="card ops-section"><div class="ops-heading"><div><h2>Order Status</h2><p class="card-sub">Current production and fulfillment status.</p></div></div>
      <div class="ops-breakdown" id="repStatuses"></div>
    </section>
    <section class="card ops-section"><div class="ops-heading"><div><h2>Best-Selling Products</h2><p class="card-sub">Ranked by demo order value.</p></div></div>
      <div class="table-wrap"><table class="table ops-table"><thead><tr><th>Product</th><th>Units</th><th>Order Value</th></tr></thead><tbody id="repTop"></tbody></table></div><p class="ops-empty" id="repTopEmpty" hidden>No products sold in this period.</p>
    </section>
    <section class="card ops-section"><div class="ops-heading"><div><h2>Materials to Replenish</h2><p class="card-sub">Live demo stock levels, regardless of reporting month.</p></div><a href="inventory.php" class="btn btn-outline btn-sm">View inventory</a></div>
      <div class="table-wrap"><table class="table ops-table"><thead><tr><th>Material</th><th>Stock</th><th>Reorder At</th></tr></thead><tbody id="repLow"></tbody></table></div><p class="ops-empty" id="repLowEmpty" hidden>No low-stock materials.</p>
    </section>
  </div>
  <p class="ops-foot">Figures are derived from demo records. Payments may be recorded on a different date than their orders. Refunds, taxes, expenses and actual profit are not calculated.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
