<?php
// Sakura Beaded Bags — Order Management frontend module.
// This page currently uses illustrative demo data. Connect it to your team's PHP/MySQL backend before production.
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8dce6">
  <title>Order Management | Sakura Beaded Bags</title>
  <link rel="stylesheet" href="assets/app.css">
  <style>
.queue-list{display:grid;gap:7px}.queue-item{display:flex;align-items:flex-start;gap:12px;padding:11px 9px;border-radius:10px}.queue-item b{color:var(--pink);font-size:12px;padding-top:1px}.queue-item strong{font-size:11px}.queue-item.selected{background:#fff1f5}.queue-item .panel-note{margin-top:2px}.table-wrap tbody tr.selected{background:#fff1f5}
@media(max-width:700px){.tabs{width:100%}.tab{flex:1;padding:8px 7px}.grid-two{gap:12px}}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand"><div class="brand-mark">✿</div><div class="brand-name">SAKURA</div><div class="brand-sub">BEADED BAGS</div></div>
    <nav class="nav"><a class="" href="#" aria-current="false"><span class="nav-icon">▦</span><span>Dashboard</span></a>
<a class="active" href="../order-management/index.php" aria-current="page"><span class="nav-icon">▣</span><span>Order Management</span></a>
<a class="" href="../product-custing-and-pricing/index.php" aria-current="false"><span class="nav-icon">◇</span><span>Product Costing & Pricing</span></a>
<a class="" href="../inventory-management/index.php" aria-current="false"><span class="nav-icon">⬡</span><span>Inventory Management</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▤</span><span>Payments & Sales</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▥</span><span>Financial Reports</span></a></nav>
    <div class="sidebar-foot"><strong>Made with love, managed with care.</strong><span>Sakura Beaded Bags · Philippines</span></div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="topbar-left"><button class="mobile-menu" type="button" data-menu-toggle aria-label="Open navigation">☰</button><div><h1 class="page-title">Order Management</h1><p class="page-subtitle">Manage each order, from the first bead to delivery.</p></div></div>
      <div class="profile"><div class="avatar">SA</div><div><div class="profile-name">Sakura Admin</div><div class="profile-role">Admin</div></div><span class="profile-chevron">⌄</span></div>
    </header>
    <section class="content"><?php
$orders = [
 ['id'=>'ORD-001','date'=>'Nov 17, 2026','customer'=>'Maria Santos','item'=>'Heart Bag ×1','total'=>1200,'production'=>'Pending','delivery'=>'Pending','payment'=>'Fully Paid'],
 ['id'=>'ORD-002','date'=>'Nov 16, 2026','customer'=>'Ana Reyes','item'=>'Pink Bag ×1','total'=>850,'production'=>'In Progress','delivery'=>'Pending','payment'=>'Fully Paid'],
 ['id'=>'ORD-003','date'=>'Nov 15, 2026','customer'=>'Camille Cruz','item'=>'Pearl Tote ×1','total'=>1500,'production'=>'Completed','delivery'=>'Delivered','payment'=>'Fully Paid'],
 ['id'=>'ORD-004','date'=>'Nov 14, 2026','customer'=>'Isabel Garcia','item'=>'Heart Bag ×1','total'=>1200,'production'=>'In Progress','delivery'=>'Pending','payment'=>'Partial'],
 ['id'=>'ORD-005','date'=>'Nov 13, 2026','customer'=>'Bea Mendoza','item'=>'Pink Bag ×2','total'=>1700,'production'=>'Pending','delivery'=>'Pending','payment'=>'Partial'],
];
function status_class($s) { $s=strtolower($s); if (str_contains($s,'progress')) return 'progress'; if (str_contains($s,'completed')||str_contains($s,'delivered')||str_contains($s,'paid')) return 'done'; if (str_contains($s,'partial')) return 'partial'; return 'pending'; }
?>
<div class="toolbar">
 <div class="tabs" role="tablist"><button class="tab active" data-order-tab="all">All orders (32)</button><button class="tab" data-order-tab="production">Production (8)</button><button class="tab" data-order-tab="archived">Archived</button></div>
 <span class="spacer"></span><button class="btn btn-primary" data-open-modal="new-order-modal">＋ New Order</button>
</div>
<div class="panel panel-pad">
 <div class="toolbar" style="margin-bottom:12px">
  <div class="search-wrap"><span class="search-symbol">⌕</span><input class="field" id="order-search" placeholder="Search order or customer" aria-label="Search order or customer"></div>
  <span class="spacer"></span><select class="select" id="status-filter" style="width:auto"><option value="all">Status: All</option><option>Pending</option><option>In Progress</option><option>Completed</option></select>
 </div>
 <div class="table-wrap"><table id="orders-table"><thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Item</th><th>Total</th><th>Production</th><th>Delivery</th><th>Payment</th></tr></thead><tbody>
 <?php foreach($orders as $o): ?><tr data-status="<?=h($o['production'])?>"><td><strong><?=h($o['id'])?></strong></td><td><?=h($o['date'])?></td><td><?=h($o['customer'])?></td><td><?=h($o['item'])?></td><td>₱<?=number_format($o['total'],2)?></td><td><span class="badge <?=status_class($o['production'])?>"><?=h($o['production'])?></span></td><td><span class="badge <?=status_class($o['delivery'])?>"><?=h($o['delivery'])?></span></td><td><span class="badge <?=status_class($o['payment'])?>"><?=h($o['payment'])?></span></td></tr><?php endforeach; ?>
 </tbody></table></div>
 <div id="order-empty" class="empty" hidden>No matching orders found.</div>
</div>
<div class="grid-two" style="margin-top:16px">
 <section class="panel panel-pad">
  <div class="panel-heading"><div><h2>ORD-001 · Heart Bag</h2><div class="panel-note">Maria Santos · 1 bag · Due November 20, 2026</div></div><span class="badge pending">Pending</span></div>
  <div class="panel-heading" style="margin-top:18px"><div><h3>Bill of materials</h3><div class="panel-note">Editable quantities and unit costs · sample costing</div></div><button class="btn btn-sm btn-soft" data-add-bom>＋ Add material</button></div>
  <div class="table-wrap"><table style="min-width:460px" id="bom-table"><thead><tr><th>Material</th><th>Quantity</th><th>Unit cost</th><th>Amount</th></tr></thead><tbody>
   <tr><td>Rose Crystal Beads</td><td>100 pcs</td><td>₱2.00</td><td>₱200.00</td></tr><tr><td>Nylon Thread</td><td>1 roll</td><td>₱40.00</td><td>₱40.00</td></tr><tr><td>Bag Handles</td><td>2 pcs</td><td>₱55.00</td><td>₱110.00</td></tr>
  </tbody></table></div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px"><strong class="small">Materials total: <span id="bom-total">₱350.00</span></strong><button class="btn btn-primary btn-sm" id="save-bom">Save BOM</button></div>
 </section>
 <aside class="panel panel-pad">
  <div class="panel-heading"><div><h2>FIFO Production Queue</h2><div class="panel-note">Oldest accepted orders are made first.</div></div><span class="chip">3 orders</span></div>
  <div class="queue-list">
   <div class="queue-item"><b>01</b><div><strong>ORD-004 · Isabel Garcia</strong><div class="panel-note">Nov 14 · In Progress</div></div></div>
   <div class="queue-item"><b>02</b><div><strong>ORD-002 · Ana Reyes</strong><div class="panel-note">Nov 16 · In Progress</div></div></div>
   <div class="queue-item selected"><b>03</b><div><strong>ORD-001 · Maria Santos</strong><div class="panel-note">Nov 17 · Pending</div></div></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:15px"><button class="btn btn-sm btn-danger" id="cancel-order">Cancel Order</button><button class="btn btn-sm" id="archive-order">▣ Archive</button></div>
  <p class="panel-note" style="margin-top:12px">ⓘ Archive after completion or cancellation.</p>
 </aside>
</div>
<div class="modal-backdrop" id="new-order-modal" role="dialog" aria-modal="true" aria-labelledby="new-order-title"><div class="modal"><div class="modal-head"><h2 id="new-order-title">Create New Order</h2><button class="modal-close" data-close-modal aria-label="Close">×</button></div><form id="new-order-form"><div class="form-grid"><div class="form-group"><label>Customer name</label><input class="field" required placeholder="e.g. Maria Santos"></div><div class="form-group"><label>Order date</label><input class="field" type="date" value="2026-11-17" required></div><div class="form-group"><label>Product</label><select class="select"><option>Heart Bag</option><option>Pink Bag</option><option>Pearl Tote</option></select></div><div class="form-group"><label>Quantity</label><input class="field" type="number" min="1" value="1"></div><div class="form-group full"><label>Notes</label><textarea class="textarea" rows="3" placeholder="Color, customization, or delivery notes"></textarea></div></div><div class="modal-actions"><button type="button" class="btn" data-close-modal>Cancel</button><button class="btn btn-primary" type="submit">Create order</button></div></form></div></div>
<div class="footer-note">Sakura Beaded Bags · Frontend preview · Demo data only</div></section>
  </main>
</div>
<script src="assets/app.js" defer></script>

</body></html>