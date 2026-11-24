<?php
// Sakura Beaded Bags — Inventory Management frontend module.
// This page currently uses illustrative demo data. Connect it to your team's PHP/MySQL backend before production.
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8dce6">
  <title>Inventory Management | Sakura Beaded Bags</title>
  <link rel="stylesheet" href="assets/app.css">
  <style>
.stock-row{margin-bottom:16px}.stock-row:last-child{margin-bottom:0}.stock-row-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;gap:10px}.stock-row-head strong{font-size:11px}.progress-fill.warn{background:#e5a64e}.stock-row .panel-note{margin-top:6px}.movement-list{display:grid}.movement{display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid #f4e9ed}.movement:last-child{border-bottom:0}.movement-icon{width:29px;height:29px;border-radius:9px;display:grid;place-items:center;font-size:17px;font-weight:800}.movement-icon.plus{background:var(--green);color:var(--green-ink)}.movement-icon.minus{background:var(--amber);color:var(--amber-ink)}.movement>div{flex:1;min-width:0}.movement strong{font-size:11px}.movement b{font-size:11px;white-space:nowrap}.stock-row .progress-track{height:6px}
@media(max-width:700px){#inventory-category,#inventory-status{flex:1;min-width:0}.toolbar>.btn{min-width:calc(50% - 5px)}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand"><div class="brand-mark">✿</div><div class="brand-name">SAKURA</div><div class="brand-sub">BEADED BAGS</div></div>
    <nav class="nav"><a class="" href="#" aria-current="false"><span class="nav-icon">▦</span><span>Dashboard</span></a>
<a class="" href="../order-management/index.php" aria-current="false"><span class="nav-icon">▣</span><span>Order Management</span></a>
<a class="" href="../product-custing-and-pricing/index.php" aria-current="false"><span class="nav-icon">◇</span><span>Product Costing & Pricing</span></a>
<a class="active" href="../inventory-management/index.php" aria-current="page"><span class="nav-icon">⬡</span><span>Inventory Management</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▤</span><span>Payments & Sales</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▥</span><span>Financial Reports</span></a></nav>
    <div class="sidebar-foot"><strong>Made with love, managed with care.</strong><span>Sakura Beaded Bags · Philippines</span></div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="topbar-left"><button class="mobile-menu" type="button" data-menu-toggle aria-label="Open navigation">☰</button><div><h1 class="page-title">Inventory Management</h1><p class="page-subtitle">Keep every bead, thread, and accessory accounted for.</p></div></div>
      <div class="profile"><div class="avatar">SA</div><div><div class="profile-name">Sakura Admin</div><div class="profile-role">Admin</div></div><span class="profile-chevron">⌄</span></div>
    </header>
    <section class="content"><?php
$materials = [
 ['sku'=>'MAT-001','name'=>'Rose Crystal Beads','category'=>'Beads','qty'=>1250,'unit'=>'pcs','minimum'=>300,'cost'=>2.00,'updated'=>'Nov 17, 2026'],
 ['sku'=>'MAT-002','name'=>'Nylon Thread','category'=>'Thread','qty'=>18,'unit'=>'rolls','minimum'=>10,'cost'=>40.00,'updated'=>'Nov 16, 2026'],
 ['sku'=>'MAT-003','name'=>'Bag Handles','category'=>'Accessories','qty'=>24,'unit'=>'pcs','minimum'=>12,'cost'=>55.00,'updated'=>'Nov 15, 2026'],
 ['sku'=>'MAT-004','name'=>'Pearl Beads','category'=>'Beads','qty'=>180,'unit'=>'pcs','minimum'=>250,'cost'=>3.50,'updated'=>'Nov 14, 2026'],
 ['sku'=>'MAT-005','name'=>'Clasps and Rings','category'=>'Accessories','qty'=>0,'unit'=>'sets','minimum'=>15,'cost'=>18.00,'updated'=>'Nov 12, 2026'],
 ['sku'=>'MAT-006','name'=>'Packaging Pouch','category'=>'Packaging','qty'=>75,'unit'=>'pcs','minimum'=>30,'cost'=>12.00,'updated'=>'Nov 11, 2026'],
];
function stock_status($qty,$min){if($qty<=0)return ['Out of stock','out'];if($qty<=$min)return ['Low stock','low'];return ['In stock','good'];}
$totalMaterials=count($materials);$low=count(array_filter($materials,fn($m)=>$m['qty']>0&&$m['qty']<=$m['minimum']));$out=count(array_filter($materials,fn($m)=>$m['qty']<=0));$stockValue=array_sum(array_map(fn($m)=>$m['qty']*$m['cost'],$materials));
?>
<div class="metrics">
 <div class="metric"><span class="metric-icon">▦</span><div class="metric-label">Total materials</div><div class="metric-value"><?=$totalMaterials?></div><div class="metric-foot">Active material records</div></div>
 <div class="metric"><span class="metric-icon">◉</span><div class="metric-label">Low stock items</div><div class="metric-value"><?=$low?></div><div class="metric-foot">At or below minimum level</div></div>
 <div class="metric"><span class="metric-icon">!</span><div class="metric-label">Out of stock</div><div class="metric-value"><?=$out?></div><div class="metric-foot">Restock required</div></div>
 <div class="metric"><span class="metric-icon">₱</span><div class="metric-label">Inventory value</div><div class="metric-value">₱<?=number_format($stockValue,2)?></div><div class="metric-foot">Quantity × unit cost</div></div>
</div>
<div class="toolbar"><div class="search-wrap"><span class="search-symbol">⌕</span><input class="field" id="inventory-search" placeholder="Search materials or SKU" aria-label="Search materials"></div><select class="select" id="inventory-category" style="width:auto"><option value="all">All categories</option><option>Beads</option><option>Thread</option><option>Accessories</option><option>Packaging</option></select><select class="select" id="inventory-status" style="width:auto"><option value="all">All stock status</option><option value="good">In stock</option><option value="low">Low stock</option><option value="out">Out of stock</option></select><span class="spacer"></span><button class="btn" data-open-modal="stock-in-modal">＋ Stock In</button><button class="btn btn-primary" data-open-modal="add-material-modal">＋ Add Material</button></div>
<div class="panel panel-pad"><div class="panel-heading"><div><h2>Materials Inventory</h2><div class="panel-note">Monitor material quantities, costs, and reorder levels.</div></div><span class="chip"><?=count($materials)?> materials</span></div>
 <div class="table-wrap"><table id="inventory-table"><thead><tr><th>Material / SKU</th><th>Category</th><th>On hand</th><th>Unit cost</th><th>Stock status</th><th>Last updated</th><th>Action</th></tr></thead><tbody>
 <?php foreach($materials as $m): [$label,$class]=stock_status($m['qty'],$m['minimum']); ?><tr data-category="<?=h($m['category'])?>" data-status="<?=$class?>" data-sku="<?=h($m['sku'])?>"><td><strong><?=h($m['name'])?></strong><div class="panel-note"><?=h($m['sku'])?> · Reorder at <?=h($m['minimum'])?> <?=h($m['unit'])?></div></td><td><?=h($m['category'])?></td><td><strong><?=number_format($m['qty'])?> <?=h($m['unit'])?></strong></td><td>₱<?=number_format($m['cost'],2)?></td><td><span class="badge <?=$class?>"><?=$label?></span></td><td><?=h($m['updated'])?></td><td><button class="btn btn-sm" data-adjust="<?=h($m['name'])?>">Adjust</button></td></tr><?php endforeach; ?>
 </tbody></table></div><div id="inventory-empty" class="empty" hidden>No matching materials found.</div>
</div>
<div class="grid-two" style="margin-top:16px">
 <section class="panel panel-pad"><div class="panel-heading"><div><h2>Stock level overview</h2><div class="panel-note">Quantity compared with reorder thresholds.</div></div></div>
  <div class="stock-levels">
  <?php foreach(array_slice($materials,0,4) as $m): [$label,$class]=stock_status($m['qty'],$m['minimum']); $pct=min(100,($m['qty']/max(1,$m['minimum']*2))*100); ?><div class="stock-row"><div class="stock-row-head"><strong><?=h($m['name'])?></strong><span class="small muted"><?=number_format($m['qty'])?> <?=h($m['unit'])?></span></div><div class="progress-track"><div class="progress-fill <?=$class==='low'||$class==='out'?'warn':''?>" style="width:<?=$pct?>%"></div></div><div class="panel-note">Reorder level: <?=number_format($m['minimum'])?> <?=h($m['unit'])?> · <?=$label?></div></div><?php endforeach; ?>
  </div>
 </section>
 <aside class="panel panel-pad"><div class="panel-heading"><div><h2>Recent stock movements</h2><div class="panel-note">Illustrative transaction history.</div></div><span class="chip">Latest</span></div>
  <div class="movement-list"><div class="movement"><span class="movement-icon plus">＋</span><div><strong>Rose Crystal Beads</strong><div class="panel-note">Stock received · Purchase</div></div><b>+500 pcs</b></div><div class="movement"><span class="movement-icon minus">−</span><div><strong>Nylon Thread</strong><div class="panel-note">Reserved for ORD-002</div></div><b>−1 roll</b></div><div class="movement"><span class="movement-icon plus">＋</span><div><strong>Packaging Pouch</strong><div class="panel-note">Stock received · Purchase</div></div><b>+25 pcs</b></div></div>
 </aside>
</div>
<div class="modal-backdrop" id="add-material-modal" role="dialog" aria-modal="true"><div class="modal"><div class="modal-head"><h2>Add Material</h2><button class="modal-close" data-close-modal>×</button></div><form class="demo-form"><div class="form-grid"><div class="form-group"><label>Material name</label><input class="field" required placeholder="Material name"></div><div class="form-group"><label>Category</label><select class="select"><option>Beads</option><option>Thread</option><option>Accessories</option><option>Packaging</option></select></div><div class="form-group"><label>Opening quantity</label><input class="field" type="number" min="0" value="0"></div><div class="form-group"><label>Unit</label><input class="field" placeholder="pcs, roll, set"></div><div class="form-group"><label>Unit cost (₱)</label><input class="field" type="number" min="0" step="0.01"></div><div class="form-group"><label>Reorder level</label><input class="field" type="number" min="0"></div></div><div class="modal-actions"><button type="button" class="btn" data-close-modal>Cancel</button><button class="btn btn-primary">Add material</button></div></form></div></div>
<div class="modal-backdrop" id="stock-in-modal" role="dialog" aria-modal="true"><div class="modal"><div class="modal-head"><h2>Record Stock In</h2><button class="modal-close" data-close-modal>×</button></div><form class="demo-form"><div class="form-grid"><div class="form-group full"><label>Material</label><select class="select"><option>Rose Crystal Beads</option><option>Nylon Thread</option><option>Bag Handles</option><option>Pearl Beads</option><option>Packaging Pouch</option></select></div><div class="form-group"><label>Quantity received</label><input class="field" type="number" min="1" required></div><div class="form-group"><label>Unit cost (₱)</label><input class="field" type="number" min="0" step="0.01"></div><div class="form-group full"><label>Reference / supplier note</label><input class="field" placeholder="Invoice number or supplier"></div></div><div class="modal-actions"><button type="button" class="btn" data-close-modal>Cancel</button><button class="btn btn-primary">Save stock-in</button></div></form></div></div>
<div class="modal-backdrop" id="adjust-modal" role="dialog" aria-modal="true"><div class="modal"><div class="modal-head"><h2>Adjust Stock</h2><button class="modal-close" data-close-modal>×</button></div><form class="demo-form"><div class="form-group"><label>Material</label><input id="adjust-material-name" class="field" readonly></div><div class="form-grid" style="margin-top:12px"><div class="form-group"><label>Adjustment</label><select class="select"><option>Increase</option><option>Decrease</option></select></div><div class="form-group"><label>Quantity</label><input class="field" type="number" min="1" required></div><div class="form-group full"><label>Reason</label><input class="field" placeholder="Count correction, damage, etc."></div></div><div class="modal-actions"><button type="button" class="btn" data-close-modal>Cancel</button><button class="btn btn-primary">Save adjustment</button></div></form></div></div>
<div class="footer-note">Sakura Beaded Bags · Frontend preview · Demo data only</div></section>
  </main>
</div>
<script src="assets/app.js" defer></script>

</body></html>