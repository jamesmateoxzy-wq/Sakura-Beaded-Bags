<?php
/**
 * PRODUCT COSTING & PRICING  (frontend)
 * ------------------------------------------------------------
 * USE_SAMPLE_DATA (includes/config.php):
 *   false -> what a brand-new database shows (no products, no materials)
 *   true  -> the Figma sample products
 * Backend teammate: replace the two lines that set $materials and $products
 * with MySQL queries that return the SAME keys as the sample arrays.
 * The page prints them as JSON and assets/js/products.js does the rest.
 *
 *   $categories -> product collections
 *   $materials  -> the inventory list (used by the "Add material" pop-up)
 *   $products   -> each product with its materials, labor, packaging, markup
 *
 * Saving is done with fetch() in products.js (see the TODO (backend) notes).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

$categories = ['Signature collection', 'Pink collection', 'Pearl collection'];

$sampleMaterials = [
    ['id' => 'MAT-001', 'name' => 'Pink Beads',         'unit' => 'pcs',  'cost' => 2.00,  'stock' => 10],
    ['id' => 'MAT-002', 'name' => 'Nylon Thread',       'unit' => 'roll', 'cost' => 40.00, 'stock' => 3],
    ['id' => 'MAT-003', 'name' => 'Bag Handles',        'unit' => 'pcs',  'cost' => 55.00, 'stock' => 5],
    ['id' => 'MAT-004', 'name' => 'Rose Crystal Beads', 'unit' => 'pcs',  'cost' => 2.00,  'stock' => 600],
    ['id' => 'MAT-005', 'name' => 'Ivory Pearl Beads',  'unit' => 'pcs',  'cost' => 3.00,  'stock' => 450],
    ['id' => 'MAT-006', 'name' => 'Clasps',             'unit' => 'pcs',  'cost' => 30.00, 'stock' => 4],
    ['id' => 'MAT-007', 'name' => 'Pink Ribbon',        'unit' => 'roll', 'cost' => 25.00, 'stock' => 2],
];

$sampleProducts = [
    [
        'id' => 1, 'sku' => 'SAK-HRT-001', 'name' => 'Heart Bag', 'category' => 'Signature collection',
        'image' => 'heart-bag.jpg',
        'materials' => [
            ['name' => 'Rose Crystal Beads', 'unit' => 'pcs',  'qty' => 100, 'cost' => 2.00],
            ['name' => 'Nylon Thread',       'unit' => 'roll', 'qty' => 1,   'cost' => 40.00],
            ['name' => 'Bag Handles',        'unit' => 'pcs',  'qty' => 2,   'cost' => 55.00],
        ],
        'labor' => 200, 'packaging' => 50, 'markup' => 100,   // = P1,200
    ],
    [
        'id' => 2, 'sku' => 'SAK-PNK-002', 'name' => 'Pink Bag', 'category' => 'Pink collection',
        'image' => 'pink-bag.jpg',
        'materials' => [
            ['name' => 'Pink Beads',   'unit' => 'pcs',  'qty' => 100, 'cost' => 2.00],
            ['name' => 'Nylon Thread', 'unit' => 'roll', 'qty' => 1,   'cost' => 40.00],
            ['name' => 'Clasps',       'unit' => 'pcs',  'qty' => 2,   'cost' => 30.00],
        ],
        'labor' => 150, 'packaging' => 50, 'markup' => 70,    // = P850
    ],
    [
        'id' => 3, 'sku' => 'SAK-PRL-003', 'name' => 'Pearl Tote', 'category' => 'Pearl collection',
        'image' => 'pearl-tote.jpg',
        'materials' => [
            ['name' => 'Ivory Pearl Beads', 'unit' => 'pcs',  'qty' => 100, 'cost' => 3.00],
            ['name' => 'Nylon Thread',      'unit' => 'roll', 'qty' => 1,   'cost' => 40.00],
            ['name' => 'Bag Handles',       'unit' => 'pcs',  'qty' => 2,   'cost' => 55.00],
        ],
        'labor' => 250, 'packaging' => 50, 'markup' => 100,   // = P1,500
    ],
];

// TODO (backend): replace these two lines with database queries
$materials = USE_SAMPLE_DATA ? $sampleMaterials : [];
$products  = USE_SAMPLE_DATA ? $sampleProducts  : [];

$pageTitle    = 'Product Costing & Pricing';
$pageSubtitle = 'Thoughtful pricing for every handcrafted piece.';
$activePage   = 'products';
$pageCss      = 'products.css';
$pageJs       = 'products.js';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="prod-layout">

  <!-- ===== Left: search + product list ===== -->
  <aside class="prod-list-col">
    <div class="search">
      <?= icon('search') ?>
      <input type="search" id="productSearch" placeholder="Search products" aria-label="Search products">
    </div>
    <div id="productList" class="prod-list"></div>
    <p class="note"><?= icon('info') ?> <span id="productCount"></span></p>
  </aside>

  <!-- ===== Right: toolbar + costing sheet ===== -->
  <section class="prod-detail">
    <div class="prod-toolbar">
      <select class="select" id="categoryFilter" aria-label="Filter by category"></select>
      <button type="button" class="btn btn-primary" id="openAddProduct"><?= icon('plus') ?> Add Product</button>
    </div>

    <div class="card" id="emptyState" hidden style="text-align:center;padding:40px 24px">
      <h2>No products yet</h2>
      <p class="card-sub">Click “Add Product” to create your first costing sheet. Materials come from Inventory Management, so add your materials there first.</p>
    </div>

    <div class="card" id="costSheet">
      <h2 id="sTitle"></h2>
      <p class="card-sub" id="sSku"></p>

      <div class="form-row sheet-top">
        <div class="field">
          <label for="sName">Product name</label>
          <input class="input" type="text" id="sName">
        </div>
        <div class="field">
          <label for="sCategory">Category</label>
          <select class="select" id="sCategory">
            <?php foreach ($categories as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="card-head">
        <h3 class="section-title">Materials &amp; quantities</h3>
        <button type="button" class="btn btn-outline btn-sm" id="sAddMaterial"><?= icon('plus') ?> Add material</button>
      </div>
      <div class="mat-head"><span>Material</span><span>Quantity</span><span>Unit cost</span><span>Subtotal</span><span></span></div>
      <div id="sMaterials" class="mat-list"></div>

      <div class="form-row three">
        <div class="field"><label for="sLabor">Labor (₱)</label><input class="input" type="number" min="0" step="0.01" id="sLabor"></div>
        <div class="field"><label for="sPackaging">Packaging (₱)</label><input class="input" type="number" min="0" step="0.01" id="sPackaging"></div>
        <div class="field"><label for="sMarkup">Markup on cost (%)</label><input class="input" type="number" min="0" step="0.01" id="sMarkup"></div>
      </div>

      <div class="summary">
        <div><small>Total cost</small><strong id="sCost">₱0</strong></div>
        <div><small>Selling price</small><strong id="sPrice">₱0</strong></div>
        <div><small>Profit per bag</small><strong id="sProfit">₱0</strong></div>
      </div>

      <div class="sheet-foot">
        <div class="field target">
          <label for="sTarget">Target price</label>
          <input class="input" type="number" min="0" step="0.01" id="sTarget">
        </div>
        <div class="actions">
          <button type="button" class="btn btn-outline" id="sReset">Reset</button>
          <button type="button" class="btn btn-primary" id="sSave"><?= icon('check') ?> Save Pricing</button>
        </div>
      </div>
      <p class="note"><?= icon('info') ?> <span id="sNote"></span></p>
    </div>
  </section>
</div>

<!-- =====================================================
     MODAL: Add Product  (frame 04a)
     ===================================================== -->
<div class="modal-overlay" id="addProductModal" role="dialog" aria-modal="true" aria-labelledby="apTitle">
  <div class="modal modal-lg">
    <div class="modal-head">
      <div>
        <h2 id="apTitle">Add Product</h2>
        <p>Build a complete costing sheet for your new handcrafted piece.</p>
      </div>
      <button type="button" class="icon-btn" data-close-modal="addProductModal" aria-label="Close"><?= icon('x') ?></button>
    </div>

    <div class="modal-body">
      <div class="form-row">
        <div class="field">
          <label for="nName">Product name <span class="req">*</span></label>
          <input class="input" type="text" id="nName" placeholder="e.g. Pearl Mini Tote">
        </div>
        <div class="field">
          <label for="nCategory">Category <span class="req">*</span></label>
          <select class="select" id="nCategory">
            <?php foreach ($categories as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="nSku">SKU (auto)</label>
          <input class="input" type="text" id="nSku" readonly>
        </div>
        <div class="field">
          <label for="nPhoto">Photo upload</label>
          <div class="photo-box">
            <div class="thumb thumb-sm no-img" id="nPhotoPreview" data-initial="+"></div>
            <div>
              <label class="btn btn-outline btn-sm" for="nPhoto"><?= icon('upload') ?> Choose photo</label>
              <input type="file" id="nPhoto" accept="image/*" hidden>
              <p class="hint" id="nPhotoName">No file chosen</p>
            </div>
          </div>
        </div>
      </div>

      <div class="card-head">
        <h3 class="section-title">Materials &amp; quantities <span class="req">*</span></h3>
        <button type="button" class="btn btn-outline btn-sm" id="nAddMaterial"><?= icon('plus') ?> Add material</button>
      </div>
      <div class="mat-head"><span>Material</span><span>Quantity</span><span>Unit cost</span><span>Subtotal</span><span></span></div>
      <div id="nMaterials" class="mat-list"></div>

      <div class="form-row three">
        <div class="field"><label for="nLabor">Labor (₱) <span class="req">*</span></label><input class="input" type="number" min="0" step="0.01" id="nLabor" value="0"></div>
        <div class="field"><label for="nPackaging">Packaging (₱) <span class="req">*</span></label><input class="input" type="number" min="0" step="0.01" id="nPackaging" value="0"></div>
        <div class="field"><label for="nMarkup">Markup on cost (%) <span class="req">*</span></label><input class="input" type="number" min="0" step="0.01" id="nMarkup" value="100"></div>
      </div>

      <div class="summary">
        <div><small>Total cost</small><strong id="nCost">₱0</strong></div>
        <div><small>Selling price</small><strong id="nPrice">₱0</strong></div>
        <div><small>Profit</small><strong id="nProfit">₱0</strong></div>
      </div>
      <p class="note"><?= icon('info') ?> <span id="nNote"></span></p>
    </div>

    <div class="modal-foot">
      <span class="hint"><span class="req">*</span> Required fields</span>
      <div class="actions">
        <button type="button" class="btn btn-outline" data-close-modal="addProductModal">Cancel</button>
        <button type="button" class="btn btn-primary" id="nSave">Save Product</button>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================
     MODAL: Add Material  (frame 03b - also used by the Orders page)
     Opens on top of the sheet / the Add Product modal.
     ===================================================== -->
<div class="modal-overlay" id="addMaterialModal" role="dialog" aria-modal="true" aria-labelledby="amTitle">
  <div class="modal modal-sm">
    <div class="modal-head">
      <div>
        <h2 id="amTitle">Add Material</h2>
        <p id="amContext"></p>
      </div>
      <button type="button" class="icon-btn" data-close-modal="addMaterialModal" aria-label="Close"><?= icon('x') ?></button>
    </div>

    <div class="modal-body">
      <div class="field">
        <label for="amMaterial">Material <span class="req">*</span></label>
        <select class="select" id="amMaterial"></select>
        <span class="hint" id="amStockInfo"></span>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="amQty">Quantity <span class="req">*</span></label>
          <input class="input" type="number" min="0" step="any" id="amQty" value="1">
        </div>
        <div class="field">
          <label for="amCost">Unit cost (auto)</label>
          <input class="input" type="number" min="0" step="0.01" id="amCost">
        </div>
      </div>
      <div class="summary one">
        <div><small>Amount (auto)</small><strong id="amAmount">₱0.00</strong></div>
      </div>
      <div class="warn" id="amWarning" hidden></div>
    </div>

    <div class="modal-foot">
      <span class="hint"><span class="req">*</span> Required fields</span>
      <div class="actions">
        <button type="button" class="btn btn-outline" data-close-modal="addMaterialModal">Cancel</button>
        <button type="button" class="btn btn-primary" id="amAdd">Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Data for products.js (backend: this is where your query results go) -->
<script>
  window.SAKURA_CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  window.SAKURA_MATERIALS  = <?= json_encode($materials,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  window.SAKURA_PRODUCTS   = <?= json_encode($products,   JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

