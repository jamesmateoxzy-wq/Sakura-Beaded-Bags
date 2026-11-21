<?php
/**
 * Financial Reports — Sakura admin frontend.
 * Fictitious order and payment summary; NOT reconciled accounting information.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
$pageTitle = 'Financial Reports';
$pageSubtitle = 'See how your boutique is growing, one bag at a time.';
$activePage = 'reports';
$pageCss = 'reports.css';
$pageJs = 'reports.js';
$pageBodyClass = 'reports-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="reports">
  <div class="rep-toolbar">
    <div class="rep-filters">
      <label class="rep-period-control">
        <?= icon('calendar') ?>
        <span class="sr-only">Report date range</span>
        <select id="repPeriod" aria-label="Report date range"></select>
      </label>
      <label class="rep-granularity-control">
        <span class="sr-only">Chart interval</span>
        <?= icon('chevron') ?>
        <select id="repGranularity" aria-label="Chart interval">
          <option value="monthly" selected>Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="yearly">Yearly</option>
        </select>
      </label>
    </div>
    <div class="rep-actions">
      <button type="button" class="btn rep-export-csv" id="repExport"><?= icon('download') ?><span>Export CSV</span></button>
      <button type="button" class="btn btn-primary rep-export-pdf" id="repPrint"><?= icon('document') ?><span>Export PDF</span></button>
    </div>
  </div>
  <section class="rep-stats" aria-label="Financial report summary">
    <article class="card rep-stat">
      <div><span>Booked Sales</span><?= icon('wallet') ?></div>
      <strong id="repRevenue">₱0</strong>
      <small id="repRevenueNote"></small>
    </article>
    <article class="card rep-stat">
      <div><span>Estimated Costs</span><?= icon('payments') ?></div>
      <strong id="repExpenses">₱0</strong>
      <small>Materials, labor &amp; packaging</small>
    </article>
    <article class="card rep-stat">
      <div><span>Estimated Gross Profit</span><?= icon('reports') ?></div>
      <strong id="repProfit">₱0</strong>
      <small id="repProfitNote"></small>
    </article>
    <article class="card rep-stat">
      <div><span>Orders</span><?= icon('orders') ?></div>
      <strong id="repOrders">0</strong>
      <small id="repOrderAverage">₱0.00 average order value</small>
    </article>
  </section>
  <div class="rep-chart-grid">
    <section class="card rep-chart-card">
      <div class="rep-chart-heading">
        <div><h2 id="repSalesTitle">Monthly Sales</h2><p id="repSalesSubtitle"></p></div>
        <strong id="repSalesTrend"></strong>
      </div>
      <div class="rep-chart" id="repBars" role="img" aria-label="Monthly sales chart"></div>
      <p class="rep-empty" id="repChartEmpty" hidden>No sales in this period.</p>
    </section>
    <section class="card rep-chart-card">
      <div class="rep-chart-heading rep-comparison-heading">
        <div><h2>Booked Sales vs Estimated Costs</h2><div class="rep-legend">
          <span><i class="rep-legend-revenue"></i>Revenue</span><span><i class="rep-legend-expenses"></i>Expenses</span>
        </div></div>
      </div>
      <div class="rep-chart" id="repComparison" role="img" aria-label="Monthly revenue and expenses chart"></div>
    </section>
  </div>
  <div class="rep-analysis-grid">
    <section class="card rep-analysis-card">
      <div class="rep-analysis-heading"><h2>Profit Analysis</h2><p id="repAnalysisSubtitle"></p></div>
      <table class="rep-analysis-table">
        <thead><tr><th>Category</th><th>Amount</th><th>Share of revenue</th></tr></thead>
        <tbody id="repExpensesTable"></tbody>
      </table>
      <div class="rep-net-profit"><strong>Estimated Gross Profit</strong><strong id="repNetProfit">₱0 · 0.0%</strong></div>
    </section>
    <section class="card rep-glance-card">
      <p>Received for selected orders <strong id="repReceived">₱0</strong></p>
      <p>Outstanding for selected orders <strong id="repBalance">₱0</strong></p>
      <h2 id="repGlanceTitle"></h2>
      <div class="rep-margin-row"><span>Estimated profit margin</span><strong id="repMargin"></strong></div>
      <div class="rep-margin-track"><span id="repMarginBar"></span></div>
      <p id="repGlanceText"></p>
      <small id="repCalculation"></small>
      <small id="repPrepared"></small>
    </section>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>