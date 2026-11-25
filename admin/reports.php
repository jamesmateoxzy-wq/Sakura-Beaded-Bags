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
<section class="ops-page" id="operationsPage" data-module="reports">
  <div class="rep-toolbar">
    <div class="rep-filters v2-report-filters">
      <label>Period <select class="select" id="repPeriod"><option value="all">All dates</option><option value="month">Month</option><option value="year">Year</option><option value="range">Date range</option></select></label>
      <label id="repMonthWrap" hidden>Month <input class="input" id="repMonth" type="month"></label>
      <label id="repYearWrap" hidden>Year <select class="select" id="repYear"></select></label>
      <label id="repFromWrap" hidden>From <input class="input" id="repFrom" type="date"></label>
      <label id="repToWrap" hidden>To <input class="input" id="repTo" type="date"></label>
      <label>Sales interval <select class="select" id="repGranularity"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly" selected>Monthly</option><option value="yearly">Yearly</option></select></label>
    </div>
    <div class="rep-actions">
      <button type="button" class="btn" id="repExport" disabled>Export CSV</button>
      <button type="button" class="btn btn-primary" id="repPrint" disabled>Export PDF</button>
    </div>
  </div>
  <p id="repStatus" role="status" aria-live="polite">Loading financial data…</p>
  <section class="rep-stats" aria-label="Financial report summary">
    <article class="card rep-stat"><div><span>Total Sales</span><?= icon('wallet') ?></div><strong id="repRevenue">—</strong><small>Booked orders in period</small></article>
    <article class="card rep-stat"><div><span>Material Expenses</span><?= icon('inventory') ?></div><strong id="repMaterial">—</strong><small>Saved material cost estimates</small></article>
    <article class="card rep-stat"><div><span>Labor Expenses</span><?= icon('users') ?></div><strong id="repLabor">—</strong><small>Saved labor cost estimates</small></article>
    <article class="card rep-stat"><div><span>Gross Profit</span><?= icon('reports') ?></div><strong id="repProfit">—</strong><small>Estimated, before operating expenses</small></article>
  </section>
  <p class="v2-report-note">Sales and gross profit are based on non-cancelled orders created in the selected period, using saved selling prices and production costs. Cash-in uses successful payment dates instead. Receivables and payment statuses reflect the current balances of selected orders. These are management estimates, not audited financial statements.</p>
  <div id="repSections" class="v2-report-grid"></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
