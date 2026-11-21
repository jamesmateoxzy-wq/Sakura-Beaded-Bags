<?php
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
    <label class="sr-only" for="monthInput">Choose month</label>
    <input type="month" id="monthInput" name="month" required>
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