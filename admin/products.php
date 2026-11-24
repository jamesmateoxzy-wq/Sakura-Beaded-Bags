<?php
/** Product Costing & Pricing; live data and writes use the authorized MySQL API. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

$categories = [];

$pageTitle    = 'Product Costing & Pricing';
$pageSubtitle = 'Thoughtful pricing for every handcrafted piece.';
$activePage   = 'products';
$pageCss      = 'products.css';
$pageJs = 'products-live.js';
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
      <button type="button" class="btn btn-outline" id="openAddCategory"><?= icon('plus') ?> Add Category</button>
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
          <input class="input" type="text" id="sName" maxlength="160" required>
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
          <input class="input" type="number" min="0.01" max="100000000" step="0.01" required id="sTarget">
        </div>
        <div class="actions">
          <button type="button" class="btn btn-outline" id="sReset">Reset</button>
          <button type="button" class="btn btn-outline" id="sRemoveProduct">Remove Product</button>
          <button type="button" class="btn btn-primary" id="sSave"><?= icon('check') ?> Save Pricing</button>
        </div>
      </div>
      <p class="note"><?= icon('info') ?> <span id="sNote"></span></p>
    </div>
  </section>
</div>

<!-- =====================================================
     MODAL: Add Category
     ===================================================== -->
<div class="modal-overlay" id="addCategoryModal" role="dialog" aria-modal="true" aria-labelledby="acTitle">
  <div class="modal modal-sm">
    <div class="modal-head">
      <div>
        <h2 id="acTitle">Add Category</h2>
        <p>Create a collection for organizing products.</p>
      </div>
      <button type="button" class="icon-btn" data-close-modal="addCategoryModal" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form id="addCategoryForm">
      <div class="modal-body">
        <div class="field">
          <label for="acName">Category name <span class="req">*</span></label>
          <input class="input" type="text" id="acName" name="categoryName" maxlength="100" autocomplete="off" placeholder="e.g. Floral collection" required>
          <p class="hint">Saved categories appear in the filter and product forms.</p>
          <p class="warn" id="acError" role="alert" hidden></p>
        </div>
      </div>
      <div class="modal-foot">
        <span class="hint"><span class="req">*</span> Required field</span>
        <div class="actions">
          <button type="button" class="btn btn-outline" data-close-modal="addCategoryModal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="acSave">Save Category</button>
        </div>
      </div>
    </form>
  </div>
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
          <input class="input" type="text" id="nName" maxlength="160" required placeholder="e.g. Pearl Mini Tote">
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
              <input type="file" id="nPhoto" accept="image/jpeg,image/png,image/webp" hidden>
              <p class="hint">JPG, PNG or WebP. Maximum 4 MB.</p>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

