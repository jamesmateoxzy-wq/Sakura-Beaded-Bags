<?php
/**
 * DASHBOARD  (frontend)
 * ------------------------------------------------------------
 * The arrays below are SAMPLE DATA copied from the Figma prototype.
 * Backend teammate: replace each array with a MySQL query that
 * returns the same keys, and the page will update by itself.
 *   $month          -> selected month (?month=2026-10)
 *   $stats          -> totals for the selected month
 *   $recentOrders   -> latest 3 orders
 *   $lowStock       -> materials below their reorder threshold
 *   $monthlySales   -> last 6 months of order value
 */
require_once __DIR__ . '/../includes/functions.php';

$month = $_GET['month'] ?? '2026-10';
if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = '2026-10'; }
$monthLabel = date('F Y', strtotime($month . '-01'));

$stats = [
    ['label' => 'Total Sales',     'value' => peso(24500), 'note' => 'October order value',          'icon' => 'wallet'],
    ['label' => 'Total Orders',    'value' => 32,          'note' => 'Orders placed this October',   'icon' => 'orders'],
    ['label' => 'Pending Orders',  'value' => 8,           'note' => 'Production or delivery pending','icon' => 'clock'],
    ['label' => 'Low Stock Items', 'value' => 5,           'note' => 'Materials need replenishing',  'icon' => 'box'],
];

$recentOrders = [
    ['id' => 'ORD-001', 'customer' => 'Maria Santos',  'total' => 1200, 'status' => 'Pending'],
    ['id' => 'ORD-002', 'customer' => 'Ana Reyes',     'total' => 850,  'status' => 'In Progress'],
    ['id' => 'ORD-003', 'customer' => 'Camille Cruz',  'total' => 1500, 'status' => 'Completed'],
];

$lowStock = [
    ['name' => 'Pink Beads',   'left' => '10 pcs'],
    ['name' => 'Nylon Thread', 'left' => '3 rolls'],
    ['name' => 'Bag Handles',  'left' => '5 pcs'],
];
$lowStockTotal = 5;

$monthlySales = [
    'May' => 10000, 'Jun' => 12500, 'Jul' => 14000,
    'Aug' => 16000, 'Sep' => 21500, 'Oct' => 24500,
];
$chartMax = 25000; // top of the chart axis

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Welcome back! Here’s your business overview';
$activePage   = 'dashboard';
$pageCss      = 'dashboard.css';
$pageJs       = 'dashboard.js';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dash-head">
  <h2 class="section-title">Your boutique at a glance</h2>
  <form method="get" id="monthForm" class="month-picker">
    <?= icon('calendar') ?>
    <label class="sr-only" for="monthInput">Choose month</label>
    <input type="month" id="monthInput" name="month" value="<?= e($month) ?>">
  </form>
</div>

<!-- ===== Stat cards ===== -->
<section class="stats">
  <?php foreach ($stats as $s): ?>
    <article class="card stat">
      <div class="stat-top"><span><?= e($s['label']) ?></span><?= icon($s['icon']) ?></div>
      <strong><?= e($s['value']) ?></strong>
      <small><?= e($s['note']) ?></small>
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
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="strong"><?= e($o['id']) ?></td>
            <td><?= e($o['customer']) ?></td>
            <td><?= peso($o['total']) ?></td>
            <td><span class="badge <?= badge_class($o['status']) ?>"><?= e($o['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="note"><?= icon('info') ?> Latest orders · Updated October 28, 2026</p>
  </section>

  <!-- ===== Inventory alerts ===== -->
  <section class="card area-alerts">
    <div class="card-head">
      <h2>Inventory Alerts</h2>
      <span class="badge badge-pink"><?= $lowStockTotal ?> items</span>
    </div>
    <ul class="alert-list">
      <?php foreach ($lowStock as $m): ?>
        <li>
          <div><strong><?= e($m['name']) ?></strong><small><?= e($m['left']) ?> remaining</small></div>
          <span class="low-tag">Low Stock</span>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="link-pink" href="inventory.php">View all <?= $lowStockTotal ?> alerts →</a>
  </section>

  <!-- ===== Sales overview (bars are drawn from $monthlySales) ===== -->
  <section class="card area-chart">
    <div class="card-head">
      <div>
        <h2>Sales Overview</h2>
        <p class="card-sub">Monthly sales · May–October 2026</p>
      </div>
      <strong class="chart-total"><?= peso(end($monthlySales)) ?> this month</strong>
    </div>
    <div class="chart">
      <div class="chart-axis"><span>₱25k</span><span>₱12.5k</span><span>₱0</span></div>
      <div class="chart-bars">
        <?php $last = array_key_last($monthlySales); foreach ($monthlySales as $label => $value): ?>
          <div class="bar-col">
            <div class="bar <?= $label === $last ? 'is-current' : '' ?>"
                 style="--h: <?= round($value / $chartMax * 100, 1) ?>%"
                 title="<?= e($label) ?>: <?= peso($value) ?>"></div>
            <span><?= e($label) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
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