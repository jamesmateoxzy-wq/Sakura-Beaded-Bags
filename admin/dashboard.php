<?php
/**
 * DASHBOARD  (frontend)
 * ------------------------------------------------------------
 * USE_SAMPLE_DATA (includes/config.php):
 *   false -> what a brand-new database shows: zeros and empty lists
 *   true  -> the Figma sample numbers
 *
 * Backend teammate: in the `else` branch below, replace each value with a
 * MySQL query (COUNT / SUM) for the selected month. Keep the same variable
 * names and array keys and the page updates by itself.
 *   $totalSales, $totalOrders, $pendingOrders, $lowStockTotal   -> numbers
 *   $recentOrders  -> latest 3 orders  (id, customer, total, status)
 *   $lowStock      -> materials below their reorder threshold (name, left)
 *   $monthlySales  -> last 6 months: ['May' => 10000, ...]
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

$defaultMonth = USE_SAMPLE_DATA ? '2026-10' : date('Y-m');
$month = $_GET['month'] ?? $defaultMonth;
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { $month = $defaultMonth; }
$monthTime = strtotime($month . '-01');
$monthName = date('F', $monthTime);

if (USE_SAMPLE_DATA) {
    $totalSales = 24500; $totalOrders = 32; $pendingOrders = 8; $lowStockTotal = 5;
    $recentOrders = [
        ['id' => 'ORD-001', 'customer' => 'Maria Santos', 'total' => 1200, 'status' => 'Pending'],
        ['id' => 'ORD-002', 'customer' => 'Ana Reyes',    'total' => 850,  'status' => 'In Progress'],
        ['id' => 'ORD-003', 'customer' => 'Camille Cruz', 'total' => 1500, 'status' => 'Completed'],
    ];
    $lowStock = [
        ['name' => 'Pink Beads',   'left' => '10 pcs'],
        ['name' => 'Nylon Thread', 'left' => '3 rolls'],
        ['name' => 'Bag Handles',  'left' => '5 pcs'],
    ];
    $monthlySales = ['May' => 10000, 'Jun' => 12500, 'Jul' => 14000, 'Aug' => 16000, 'Sep' => 21500, 'Oct' => 24500];
    $updatedOn = 'October 28, 2026';
} else {
    // Brand-new database: nothing recorded yet.  TODO (backend): replace with queries.
    $totalSales = 0; $totalOrders = 0; $pendingOrders = 0; $lowStockTotal = 0;
    $recentOrders = [];
    $lowStock = [];
    $monthlySales = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthlySales[date('M', strtotime($month . "-01 -$i month"))] = 0;
    }
    $updatedOn = date('F j, Y');
}
require_once __DIR__ . '/../includes/dashboard_live_data.php';
$stats = [
    ['label' => 'Total Sales',     'value' => peso($totalSales), 'note' => $monthName . ' payments received',     'icon' => 'wallet'],
    ['label' => 'Total Orders',    'value' => $totalOrders,      'note' => 'Orders placed in ' . $monthName,       'icon' => 'orders'],
    ['label' => 'Pending Orders',  'value' => $pendingOrders,    'note' => 'Production or delivery pending',      'icon' => 'clock'],
    ['label' => 'Low Stock Items', 'value' => $lowStockTotal,    'note' => 'Materials need replenishing',         'icon' => 'box'],
];

// chart scale grows automatically when sales pass P25k
$chartMax  = max(25000, (int)(ceil(max($monthlySales) / 5000) * 5000));
$k         = fn($v) => '₱' . rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k';
$rangeText = array_key_first($monthlySales) . '–' . date('M Y', $monthTime);

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Welcome back! Here’s your business overview';
$activePage   = 'dashboard';
$pageCss      = 'dashboard.css';
$pageJs       = 'dashboard.js';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dash-head">
  <h2 class="section-title">Your boutique at a glance</h2>
  <form id="monthForm" class="month-picker">
    <?= icon('calendar') ?>
    <input type="month" id="monthInput" name="month" value="<?= e($month) ?>" aria-label="Choose month">
  </form>
</div>

<!-- ===== Stat cards ===== -->
<section class="stats">
  <?php foreach ([
    ['sales', 'Booked Sales', 'wallet'], ['orders', 'Total Orders', 'orders'],
    ['pending', 'Pending Orders', 'clock'], ['low', 'Low Stock Items', 'box']
  ] as [$id, $label, $icon]): ?>
    <article class="card stat">
      <div class="stat-top"><span><?= e($label) ?></span><?= icon($icon) ?></div>
      <strong id="dash-<?= e($id) ?>">0</strong>
      <small id="dash-note-<?= e($id) ?>"></small>
    </article>
  <?php endforeach; ?>
</section>

<div class="dash-grid">

  <!-- ===== Recent orders ===== -->
  <section class="card area-orders">
    <div class="card-head">
      <h2>Recent Orders</h2>
      <a class="btn btn-outline btn-sm" href="orders.php"><?= icon('arrow') ?> View all</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Order ID</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody id="dashRecent"></tbody>
      </table>
    </div>
    <p class="empty-state" id="dashNoOrders" hidden>No orders in the selected month.</p>
  </section>

  <!-- ===== Inventory alerts ===== -->
  <section class="card area-alerts">
    <div class="card-head">
      <h2>Inventory Alerts</h2>
      <span class="badge badge-pink" id="dashAlertCount"></span>
    </div>
    <ul class="alert-list" id="dashAlertList"></ul>
    <p class="empty-state" id="dashNoAlerts" hidden>No materials below their reorder threshold.</p>
    <a class="link-pink" href="inventory.php">View Inventory →</a>
  </section>

  <section class="card area-chart">
    <div class="card-head">
      <div><h2>Sales Overview</h2><p class="card-sub">Booked sales for the six months ending in the selected month</p></div>
      <strong class="chart-total" id="dashChartTotal"></strong>
    </div>
    <div class="chart">
      <div class="chart-axis" id="dashChartAxis"></div>
      <div class="chart-bars" id="dashChartBars"></div>
    </div>
  </section>

  <!-- ===== Quick actions =====
       These open the modals built on teammates' pages.
       They read ?action=new / stockin / record in the URL. -->
  <section class="card area-quick">
    <div class="card-head"><h2>Quick Actions</h2></div>
    <a class="btn btn-primary" href="orders.php?action=new"><?= icon('plus') ?> New Order</a>
    <div class="quick-row">
      <a class="btn btn-outline btn-sm" href="inventory.php?action=stockin"><?= icon('box') ?> Stock-In</a>
      <a class="btn btn-outline btn-sm" href="payments.php?action=record"><?= icon('wallet') ?> Record Payment</a>
    </div>
    <p class="note"><?= icon('info') ?> Keep every handcrafted order on track.</p>
  </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>