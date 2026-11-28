<?php
/**
 * Financial Reports — MySQL-backed Sakura admin frontend.
 * Payment-based sales and estimated costs are not audited accounting information.
 * Handoff: docs/OPERATIONS_HANDOFF.md
 */
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isDemo = USE_SAMPLE_DATA || (DEMO_MODE && ($_SESSION['user_id'] ?? '') === 'demo');
$pageTitle = 'Financial Reports';
$pageSubtitle = 'See how your boutique is growing, one bag at a time.';
$activePage = 'reports';
$pageCss = 'reports.css';
$pageJs = 'reports-live.js';
$pageBodyClass = 'reports-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="ops-page" id="operationsPage" data-module="reports" data-demo="<?= $isDemo ? '1' : '0' ?>">
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
      <div><span>Revenue</span><?= icon('wallet') ?></div>
      <strong id="repRevenue">₱0</strong>
      <small id="repRevenueNote">No revenue recorded</small>
    </article>
    <article class="card rep-stat">
      <div><span>Expenses</span><?= icon('payments') ?></div>
      <strong id="repExpenses">₱0</strong>
      <small>Materials, labor &amp; overhead</small>
    </article>
    <article class="card rep-stat">
      <div><span>Net Profit</span><?= icon('reports') ?></div>
      <strong id="repProfit">₱0</strong>
      <small id="repProfitNote">0.0% net profit margin</small>
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
        <div><h2 id="repSalesTitle">Monthly Sales</h2><p id="repSalesSubtitle">No sales recorded</p></div>
        <strong id="repSalesTrend">No trend yet</strong>
      </div>
      <div class="rep-chart" id="repBars" role="img" aria-label="Monthly sales chart"></div>
      <p class="rep-empty" id="repChartEmpty" hidden>No sales in this period.</p>
    </section>
    <section class="card rep-chart-card">
      <div class="rep-chart-heading rep-comparison-heading">
        <div><h2>Revenue vs Expenses</h2><div class="rep-legend">
          <span><i class="rep-legend-revenue"></i>Revenue</span><span><i class="rep-legend-expenses"></i>Expenses</span>
        </div></div>
      </div>
      <div class="rep-chart" id="repComparison" role="img" aria-label="Monthly revenue and expenses chart"></div>
      <p class="rep-empty" id="repComparisonEmpty" hidden>No revenue or expenses recorded.</p>
    </section>
  </div>
  <div class="rep-analysis-grid">
    <section class="card rep-analysis-card">
      <div class="rep-analysis-heading"><h2>Profit Analysis</h2><p id="repAnalysisSubtitle">No financial activity recorded.</p></div>
      <table class="rep-analysis-table">
        <thead><tr><th>Category</th><th>Amount</th><th>Share of revenue</th></tr></thead>
        <tbody id="repExpensesTable"></tbody>
      </table>
      <p class="rep-empty" id="repExpensesEmpty" hidden>No expense records for this period.</p>
      <div class="rep-net-profit"><strong>Net Profit</strong><strong id="repNetProfit">₱0 · 0.0%</strong></div>
    </section>
    <section class="card rep-glance-card">
      <h2 id="repGlanceTitle">No activity yet</h2>
      <div class="rep-margin-row"><span>Net profit margin</span><strong id="repMargin">0.0%</strong></div>
      <div class="rep-margin-track"><span id="repMarginBar"></span></div>
      <p id="repGlanceText">No sales or expenses recorded for this period.</p>
      <small id="repCalculation">Revenue: ₱0 - Expenses: ₱0</small>
      <small id="repPrepared">No report data yet · Currency: PHP (₱)</small>
    </section>
  </div>

  <section class="rep-ledger" id="reportLedger" aria-labelledby="ledgerTitle">
    <div class="rep-ledger-toolbar">
      <div class="rep-ledger-tabs" role="group" aria-label="Ledger reporting period">
        <button type="button" data-ledger-range="weekly" aria-pressed="true">Weekly</button>
        <button type="button" data-ledger-range="monthly" aria-pressed="false">Monthly</button>
        <button type="button" data-ledger-range="yearly" aria-pressed="false">Yearly</button>
      </div>
      <label>
        <span class="sr-only">Select ledger period</span>
        <select class="rep-ledger-period" id="ledgerPeriod" aria-label="Select ledger period" disabled>
          <option>Loading ledger…</option>
        </select>
      </label>
    </div>

    <div class="rep-ledger-heading">
      <div>
        <h2 id="ledgerTitle">Sales Ledger</h2>
        <p id="ledgerSubtitle">Successful payments recorded for the selected period.</p>
      </div>
      <button type="button" class="btn btn-outline" id="ledgerPrint" disabled>Print ledger</button>
    </div>

    <div class="rep-ledger-summary" aria-label="Selected period sales summary">
      <article class="card"><span>Total sales received</span><strong id="ledgerSales">₱0.00</strong></article>
      <article class="card"><span>Sales transactions</span><strong id="ledgerCount">0</strong></article>
    </div>

    <section class="card rep-ledger-card" aria-label="Sales transactions">
      <div class="rep-ledger-card-head"><h2 id="ledgerTableTitle">Weekly transactions</h2></div>
      <p class="rep-ledger-status" id="ledgerStatus" role="status">Loading sales ledger…</p>
      <div class="rep-ledger-content" id="ledgerContent" hidden>
        <div class="table-wrap">
          <table class="table rep-ledger-table">
            <thead>
              <tr><th>Date</th><th>Reference</th><th>Category</th><th>Description</th><th>Debit</th><th>Credit (PHP)</th><th>Balance</th></tr>
            </thead>
            <tbody id="ledgerRows"></tbody>
          </table>
        </div>
        <p class="rep-ledger-empty" id="ledgerEmpty" hidden>No sales recorded in this period.</p>
        <div class="rep-ledger-total"><strong>Period sales total</strong><strong id="ledgerPeriodTotal">₱0.00</strong></div>
      </div>
    </section>
    <p class="rep-ledger-note">Sales are based on successful payments for non-cancelled orders. Refunded and cancelled payments are excluded. This ledger is a payment record, not audited financial statements.</p>
  </section>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
