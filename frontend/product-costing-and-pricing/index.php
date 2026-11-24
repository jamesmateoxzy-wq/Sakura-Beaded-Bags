<?php
// Sakura Beaded Bags — Product Costing & Pricing frontend module.
// This page currently uses illustrative demo data. Connect it to your team's PHP/MySQL backend before production.
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8dce6">
  <title>Product Costing & Pricing | Sakura Beaded Bags</title>
  <link rel="stylesheet" href="assets/app.css">
  <style>
.product-layout{display:grid;grid-template-columns:minmax(250px,.82fr) minmax(0,1.65fr);gap:16px;align-items:start}.product-list{display:grid;gap:12px}.product-card{display:flex;gap:13px;align-items:center;text-align:left;background:#fff;border:1px solid var(--line);border-radius:14px;padding:11px;box-shadow:var(--shadow);width:100%;color:var(--ink)}.product-card.selected{border:1.5px solid var(--pink);box-shadow:0 0 0 2px rgba(217,103,145,.08)}.product-card img{width:94px;height:112px;object-fit:cover;border-radius:9px;background:#f8e8ee}.product-info{display:grid;gap:5px;min-width:0}.product-info strong{font-size:13px}.product-info small{font-size:10px;color:var(--muted)}.product-info b{font-size:15px;color:var(--pink-dark)}.product-info em{font-size:10px;color:#bf6687;font-style:normal}.product-count{padding:4px 3px}.costing-panel{padding:18px}.product-basics .field{font-size:11px}.cost-table-wrap{overflow:auto}.cost-table{min-width:590px}.cost-table th{background:#fff}.cost-table td{padding:7px 5px}.cost-table .field{padding:8px;font-size:11px}.cost-table td:first-child{width:31%}.cost-table td:nth-child(2),.cost-table td:nth-child(3){width:17%}.cost-table td:nth-child(4){font-weight:750}.remove-row{border:0;background:#fff2f5;color:#bd6d87;width:25px;height:25px;border-radius:7px;font-size:17px}.totals-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;background:#fff1f5;border-radius:12px;padding:14px 15px;margin-top:15px}.totals-strip div{display:grid;gap:4px}.totals-strip small{font-size:10px;color:#a17d8b}.totals-strip strong{font-size:21px;color:var(--pink-dark);letter-spacing:-.5px}.pricing-footer{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:14px}.target-price{display:grid;gap:6px;max-width:300px;flex:1}.target-price label{font-size:10px;color:var(--muted);font-weight:700}.target-price small{font-size:10px;color:var(--muted)}.pricing-actions{display:flex;gap:8px}.category-filter{max-width:180px}.product-card[hidden]{display:none}
@media(max-width:1000px){.product-layout{grid-template-columns:1fr}.product-list{grid-template-columns:repeat(3,minmax(0,1fr))}.product-card{display:grid;align-items:start}.product-card img{width:100%;height:145px}.product-count{grid-column:1/-1}}
@media(max-width:700px){.product-list{grid-template-columns:1fr 1fr}.product-card{grid-template-columns:85px minmax(0,1fr);display:flex;padding:8px;gap:9px}.product-card img{width:78px;height:90px}.product-info strong{font-size:11px}.product-info b{font-size:13px}.costing-panel{padding:13px}.totals-strip{gap:7px;padding:11px}.totals-strip strong{font-size:16px}.pricing-footer{align-items:stretch;flex-direction:column}.pricing-actions{justify-content:flex-end}.category-filter{max-width:none;flex:1}}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand"><div class="brand-mark">✿</div><div class="brand-name">SAKURA</div><div class="brand-sub">BEADED BAGS</div></div>
    <nav class="nav"><a class="" href="#" aria-current="false"><span class="nav-icon">▦</span><span>Dashboard</span></a>
<a class="" href="../order-management/index.php" aria-current="false"><span class="nav-icon">▣</span><span>Order Management</span></a>
<a class="active" href="../product-custing-and-pricing/index.php" aria-current="page"><span class="nav-icon">◇</span><span>Product Costing & Pricing</span></a>
<a class="" href="../inventory-management/index.php" aria-current="false"><span class="nav-icon">⬡</span><span>Inventory Management</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▤</span><span>Payments & Sales</span></a>
<a class="" href="#" aria-current="false"><span class="nav-icon">▥</span><span>Financial Reports</span></a></nav>
    <div class="sidebar-foot"><strong>Made with love, managed with care.</strong><span>Sakura Beaded Bags · Philippines</span></div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="topbar-left"><button class="mobile-menu" type="button" data-menu-toggle aria-label="Open navigation">☰</button><div><h1 class="page-title">Product Costing & Pricing</h1><p class="page-subtitle">Thoughtful pricing for every handcrafted piece.</p></div></div>
      <div class="profile"><div class="avatar">SA</div><div><div class="profile-name">Sakura Admin</div><div class="profile-role">Admin</div></div><span class="profile-chevron">⌄</span></div>
    </header>
    <section class="content"><?php
$products = [
 ['name'=>'Heart Bag','collection'=>'Signature collection','sku'=>'SAK-HRT-001','price'=>1200,'image'=>'assets/images/heart-bag.jpg','tone'=>'heart'],
 ['name'=>'Pink Bag','collection'=>'Pink collection','sku'=>'SAK-PNK-002','price'=>850,'image'=>'assets/images/pink-bag.jpg','tone'=>'pink'],
 ['name'=>'Pearl Tote','collection'=>'Pearl collection','sku'=>'SAK-PRL-003','price'=>1500,'image'=>'assets/images/pearl-tote.jpg','tone'=>'pearl'],
];
?>
<div class="toolbar"><div class="search-wrap"><span class="search-symbol">⌕</span><input id="product-search" class="field" placeholder="Search products" aria-label="Search products"></div><select class="select category-filter" id="category-filter"><option value="all">All categories</option><option>Signature collection</option><option>Pink collection</option><option>Pearl collection</option></select><span class="spacer"></span><button class="btn btn-primary" data-open-modal="add-product-modal">＋ Add Product</button></div>
<div class="product-layout">
 <section class="product-list" id="product-list">
  <?php foreach($products as $i=>$p): ?><button class="product-card <?= $i===0?'selected':'' ?>" type="button" data-product="<?=h($p['name'])?>" data-category="<?=h($p['collection'])?>" data-price="<?=h($p['price'])?>" data-sku="<?=h($p['sku'])?>">
   <img src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" loading="lazy"><span class="product-info"><strong><?=h($p['name'])?></strong><small><?=h($p['collection'])?></small><b>₱<?=number_format($p['price'],2)?></b><em>View costing →</em></span>
  </button><?php endforeach; ?>
  <div class="panel-note product-count">3 active products · Prices per bag</div>
 </section>
 <section class="panel panel-pad costing-panel">
  <div class="panel-heading"><div><h2 id="sheet-title">Heart Bag · Costing sheet</h2><div class="panel-note" id="sheet-subtitle">SKU: SAK-HRT-001 · Signature collection</div></div><span class="chip">Per product</span></div>
  <div class="form-grid product-basics"><div class="form-group"><label>Product name</label><input class="field" id="product-name" value="Heart Bag"></div><div class="form-group"><label>Category</label><input class="field" id="product-category" value="Signature collection"></div></div>
  <div class="panel-heading" style="margin-top:14px"><h3>Materials & quantities</h3><button class="btn btn-sm btn-soft" id="add-material">＋ Add material</button></div>
  <div class="cost-table-wrap"><table class="cost-table" id="cost-table"><thead><tr><th>Material</th><th>Quantity</th><th>Unit cost</th><th>Subtotal</th><th></th></tr></thead><tbody>
   <tr><td><input class="field mat-name" value="Rose Crystal Beads"></td><td><input class="field mat-qty" type="number" min="0" step="0.01" value="100"></td><td><input class="field mat-cost" type="number" min="0" step="0.01" value="2"></td><td class="mat-subtotal">₱200.00</td><td><button class="remove-row" type="button" aria-label="Remove material">×</button></td></tr>
   <tr><td><input class="field mat-name" value="Nylon Thread"></td><td><input class="field mat-qty" type="number" min="0" step="0.01" value="1"></td><td><input class="field mat-cost" type="number" min="0" step="0.01" value="40"></td><td class="mat-subtotal">₱40.00</td><td><button class="remove-row" type="button" aria-label="Remove material">×</button></td></tr>
   <tr><td><input class="field mat-name" value="Bag Handles"></td><td><input class="field mat-qty" type="number" min="0" step="0.01" value="2"></td><td><input class="field mat-cost" type="number" min="0" step="0.01" value="55"></td><td class="mat-subtotal">₱110.00</td><td><button class="remove-row" type="button" aria-label="Remove material">×</button></td></tr>
  </tbody></table></div>
  <div class="form-grid cost-overheads" style="margin-top:13px"><div class="form-group"><label>Labor hours</label><input class="field" id="labor-hours" type="number" min="0" step="0.25" value="2"></div><div class="form-group"><label>Labor rate / hour (₱)</label><input class="field" id="labor-rate" type="number" min="0" step="0.01" value="100"></div><div class="form-group"><label>Packaging (₱)</label><input class="field" id="packaging" type="number" min="0" step="0.01" value="50"></div><div class="form-group"><label>Markup on cost (%)</label><input class="field" id="markup" type="number" min="0" step="0.1" value="100"></div></div>
  <div class="totals-strip"><div><small>Total cost</small><strong id="total-cost">₱600.00</strong></div><div><small>Selling price</small><strong id="selling-price">₱1,200.00</strong></div><div><small>Profit per bag</small><strong id="profit">₱600.00</strong></div></div>
  <div class="pricing-footer"><div class="target-price"><label for="target-price">Target price (₱)</label><input class="field" id="target-price" type="number" min="0" step="0.01" value="1200"><small id="margin-note">Materials ₱350 + labor ₱200 + packaging ₱50 = ₱600 · Profit margin: 50%</small></div><div class="pricing-actions"><button class="btn" id="reset-pricing">Reset</button><button class="btn btn-primary" id="save-pricing">✓ Save Pricing</button></div></div>
 </section>
</div>
<div class="modal-backdrop" id="add-product-modal" role="dialog" aria-modal="true"><div class="modal"><div class="modal-head"><h2>Add Product</h2><button class="modal-close" data-close-modal aria-label="Close">×</button></div><form id="add-product-form"><div class="form-grid"><div class="form-group"><label>Product name</label><input class="field" required placeholder="Product name"></div><div class="form-group"><label>Category</label><select class="select"><option>Signature collection</option><option>Pink collection</option><option>Pearl collection</option></select></div><div class="form-group"><label>Target price (₱)</label><input class="field" type="number" min="0" placeholder="0.00"></div><div class="form-group"><label>SKU</label><input class="field" placeholder="SAK-NEW-004"></div></div><div class="modal-actions"><button class="btn" type="button" data-close-modal>Cancel</button><button class="btn btn-primary">Add product</button></div></form></div></div>
<div class="footer-note">Sakura Beaded Bags · Frontend preview · Demo data only</div></section>
  </main>
</div>
<script src="assets/app.js" defer></script>

</body></html>