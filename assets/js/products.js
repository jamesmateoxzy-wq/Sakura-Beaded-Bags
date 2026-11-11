/* =========================================================
   Products (Product Costing & Pricing)
   Data comes from products.php:
     window.SAKURA_PRODUCTS, SAKURA_MATERIALS, SAKURA_CATEGORIES
   Formulas:
     materials = sum(qty x unit cost)
     total cost = materials + labor + packaging
     selling price = total cost x (1 + markup / 100)
     profit = selling price - total cost
   ========================================================= */
(function () {
  var S = window.Sakura;
  var products   = window.SAKURA_PRODUCTS   || [];
  var inventory  = window.SAKURA_MATERIALS  || [];
  var categories = window.SAKURA_CATEGORIES || [];
  var IMG_PATH = '../assets/images/products/';

  var selectedId = products.length ? products[0].id : null;
  var draft = null;       // the copy being edited in the costing sheet
  var newDraft = null;    // the product being built in the Add Product modal
  var amOnAdd = null;     // what to do when "Add" is pressed in the Add Material modal

  // ---------- helpers ----------
  function $(id) { return document.getElementById(id); }
  function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function money(n) { return Math.round(n * 100) % 100 === 0 ? S.peso(n, 0) : S.peso(n, 2); }
  function findProduct(id) { return products.filter(function (p) { return p.id === id; })[0]; }

  function calc(p) {
    var mat = p.materials.reduce(function (s, m) { return s + m.qty * m.cost; }, 0);
    var cost = mat + num(p.labor) + num(p.packaging);
    var price = cost * (1 + num(p.markup) / 100);
    var profit = price - cost;
    return { materials: mat, cost: cost, price: price, profit: profit, margin: price ? profit / price * 100 : 0 };
  }

  function thumbHtml(p, extra) {
    var initial = esc((p.name || '?').charAt(0).toUpperCase());
    var src = p.photo || (p.image ? IMG_PATH + p.image : '');
    return '<div class="thumb ' + (extra || '') + '" data-initial="' + initial + '">' +
      (src ? '<img src="' + esc(src) + '" alt="' + esc(p.name) + '" onerror="this.parentElement.classList.add(\'no-img\')">' : '') + '</div>';
  }

  // ---------- material rows (used by the sheet AND the Add Product modal) ----------
  function renderRows(container, list, onChange) {
    if (!list.length) {
      container.innerHTML = '<p class="empty">No materials yet. Click “Add material”.</p>';
    } else {
      container.innerHTML = list.map(function (m, i) {
        return '<div class="mat-row" data-i="' + i + '">' +
          '<div class="mat-name">' + esc(m.name) + '</div>' +
          '<label class="mat-cell"><span class="mat-label">Quantity</span><div class="qty-wrap">' +
            '<input class="input" type="number" min="0" step="any" value="' + m.qty + '" data-k="qty" aria-label="Quantity of ' + esc(m.name) + '"><em>' + esc(m.unit) + '</em></div></label>' +
          '<label class="mat-cell"><span class="mat-label">Unit cost</span>' +
            '<input class="input" type="number" min="0" step="0.01" value="' + m.cost + '" data-k="cost" aria-label="Unit cost of ' + esc(m.name) + '"></label>' +
          '<div class="mat-sub"><span class="mat-label">Subtotal</span><b>' + S.peso(m.qty * m.cost) + '</b></div>' +
          '<button type="button" class="icon-btn" data-remove aria-label="Remove ' + esc(m.name) + '"><svg class="icon" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
          '</div>';
      }).join('');
    }
    container.oninput = function (e) {
      var row = e.target.closest('.mat-row');
      if (!row || !e.target.dataset.k) return;
      var m = list[+row.dataset.i];
      m[e.target.dataset.k] = num(e.target.value);
      row.querySelector('.mat-sub b').textContent = S.peso(m.qty * m.cost);
      onChange();
    };
    container.onclick = function (e) {
      var btn = e.target.closest('[data-remove]');
      if (!btn) return;
      list.splice(+btn.closest('.mat-row').dataset.i, 1);
      renderRows(container, list, onChange);
      onChange();
    };
  }

  function addToList(list, item) {   // same material twice -> add the quantities
    var same = list.filter(function (m) { return m.name === item.name; })[0];
    if (same) { same.qty += item.qty; same.cost = item.cost; } else { list.push(item); }
  }

  function summaryNote(c, p) {
    return money(c.materials) + ' materials + ' + money(num(p.labor)) + ' labor + ' + money(num(p.packaging)) +
      ' packaging = ' + money(c.cost) + ' · Profit margin: ' + Number(c.margin.toFixed(1)) + '%';
  }
  function fillSummary(prefix, p) {
    var c = calc(p);
    $(prefix + 'Cost').textContent = money(c.cost);
    $(prefix + 'Price').textContent = money(c.price);
    $(prefix + 'Profit').textContent = money(c.profit);
    $(prefix + 'Note').textContent = summaryNote(c, p);
    return c;
  }

  // ---------- left column: product list ----------
  function renderList() {
    var q = $('productSearch').value.trim().toLowerCase();
    var cat = $('categoryFilter').value;
    var items = products.filter(function (p) {
      return (!q || p.name.toLowerCase().indexOf(q) > -1) && (cat === 'all' || p.category === cat);
    });
    var list = $('productList');
    list.innerHTML = items.length ? '' : '<p class="empty">' + (products.length ? 'No products match your search.' : 'No products yet.') + '</p>';
    items.forEach(function (p) {
      var selected = p.id === selectedId;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'prod-card' + (selected ? ' is-selected' : '');
      btn.innerHTML = thumbHtml(p) +
        '<div><h3>' + esc(p.name) + '</h3><div class="cat">' + esc(p.category.replace(' collection', '')) + ' collection</div>' +
        '<div class="price">' + money(calc(p).price) + '</div>' +
        '<div class="go">' + (selected ? 'Editing price →' : 'View costing →') + '</div></div>';
      btn.addEventListener('click', function () { selectProduct(p.id); });
      list.appendChild(btn);
    });
    $('productCount').textContent = products.length + ' active product' + (products.length === 1 ? '' : 's') + ' · Prices per bag';
  }

  function selectProduct(id) {
    selectedId = id;
    draft = clone(findProduct(id));
    $('costSheet').hidden = false;
    $('emptyState').hidden = true;
    renderList();
    renderSheet();
  }

  // ---------- right column: costing sheet ----------
  function renderSheet() {
    if (!draft) return;
    $('sTitle').textContent = draft.name + ' · Costing sheet';
    $('sSku').textContent = 'SKU: ' + draft.sku + ' · ' + draft.category;
    $('sName').value = draft.name;
    $('sCategory').value = draft.category;
    $('sLabor').value = draft.labor;
    $('sPackaging').value = draft.packaging;
    $('sMarkup').value = draft.markup;
    renderRows($('sMaterials'), draft.materials, updateSheetTotals);
    updateSheetTotals();
  }

  function updateSheetTotals(skipTarget) {
    var c = fillSummary('s', draft);
    if (skipTarget !== true) $('sTarget').value = c.price.toFixed(2);
  }

  $('sName').addEventListener('input', function () { draft.name = this.value; $('sTitle').textContent = (draft.name || 'New product') + ' · Costing sheet'; });
  $('sCategory').addEventListener('change', function () { draft.category = this.value; });
  ['Labor', 'Packaging', 'Markup'].forEach(function (f) {
    $('s' + f).addEventListener('input', function () { draft[f.toLowerCase()] = num(this.value); updateSheetTotals(); });
  });
  // typing a target price works backwards to the markup %
  $('sTarget').addEventListener('input', function () {
    var cost = calc(draft).cost;
    draft.markup = cost > 0 ? Math.max(0, Math.round((num(this.value) / cost - 1) * 10000) / 100) : 0;
    $('sMarkup').value = draft.markup;
    updateSheetTotals(true);
  });

  $('sAddMaterial').addEventListener('click', function () {
    openAddMaterial('Adding to ' + (draft.name || 'this product') + ' costing sheet', function (item) {
      addToList(draft.materials, item);
      renderRows($('sMaterials'), draft.materials, updateSheetTotals);
      updateSheetTotals();
    });
  });

  $('sReset').addEventListener('click', function () {
    draft = clone(findProduct(selectedId));
    renderSheet();
    S.toast('Changes reset');
  });

  $('sSave').addEventListener('click', function () {
    if (!draft.name.trim()) { $('sName').classList.add('is-error'); S.toast('Enter a product name first.'); return; }
    $('sName').classList.remove('is-error');
    var i = products.indexOf(findProduct(selectedId));
    products[i] = clone(draft);
    renderList();
    renderSheet();
    S.toast('Pricing saved for ' + draft.name);
    // TODO (backend): save to MySQL, for example
    // fetch('../includes/api/save_product.php', { method: 'POST',
    //   headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(draft) });
  });

  // ---------- filters ----------
  $('categoryFilter').innerHTML = '<option value="all">All categories</option>' +
    categories.map(function (c) { return '<option>' + esc(c) + '</option>'; }).join('');
  $('categoryFilter').addEventListener('change', renderList);
  $('productSearch').addEventListener('input', renderList);

  // ---------- Add Material modal ----------
  function inv() { return inventory[$('amMaterial').selectedIndex] || inventory[0]; }

  function updateAmount() {
    var m = inv(), qty = num($('amQty').value), cost = num($('amCost').value);
    $('amAmount').textContent = S.peso(qty * cost);
    $('amStockInfo').textContent = m ? m.id + ' · In stock: ' + m.stock + ' ' + m.unit : '';
    var warn = $('amWarning');
    if (m && qty > m.stock) {
      warn.hidden = false;
      warn.innerHTML = '<strong>Not enough stock</strong>Only ' + m.stock + ' ' + esc(m.unit) + ' available. Record a Stock-In before accepting orders that need this.';
    } else { warn.hidden = true; }
  }

  function openAddMaterial(context, onAdd) {
    amOnAdd = onAdd;
    $('amContext').textContent = context;
    $('amMaterial').innerHTML = inventory.map(function (m) {
      return '<option>' + esc(m.name) + ' · ' + m.stock + ' ' + esc(m.unit) + ' available</option>';
    }).join('');
    if (!inventory.length) { $('amMaterial').innerHTML = '<option>No materials in inventory yet</option>'; }
    $('amQty').value = 1;
    $('amCost').value = inventory.length ? inventory[0].cost : 0;
    updateAmount();
    S.openModal('addMaterialModal');
  }

  $('amMaterial').addEventListener('change', function () { $('amCost').value = inv().cost; updateAmount(); });
  $('amQty').addEventListener('input', updateAmount);
  $('amCost').addEventListener('input', updateAmount);
  $('amAdd').addEventListener('click', function () {
    if (!inv()) { S.toast('Add materials in Inventory Management first.'); return; }
    var qty = num($('amQty').value);
    if (qty <= 0) { $('amQty').classList.add('is-error'); S.toast('Enter a quantity greater than 0.'); return; }
    $('amQty').classList.remove('is-error');
    var m = inv();
    if (amOnAdd) amOnAdd({ name: m.name, unit: m.unit, qty: qty, cost: num($('amCost').value) });
    S.closeModal('addMaterialModal');
    S.toast(m.name + ' added');
  });

  // ---------- Add Product modal ----------
  function makeSku(name) {
    var letters = name.toUpperCase().replace(/[^A-Z]/g, '').replace(/[AEIOU]/g, '').slice(0, 3);
    while (letters.length < 3) letters += 'X';
    return 'SAK-' + letters + '-' + String(products.length + 1).padStart(3, '0');
  }

  function resetNewProduct() {
    newDraft = { name: '', category: categories[0] || '', sku: makeSku(''), photo: '', materials: [], labor: 0, packaging: 0, markup: 100 };
    $('nName').value = '';
    $('nName').classList.remove('is-error');
    $('nCategory').value = newDraft.category;
    $('nSku').value = newDraft.sku;
    $('nLabor').value = 0; $('nPackaging').value = 0; $('nMarkup').value = 100;
    $('nPhoto').value = '';
    $('nPhotoName').textContent = 'No file chosen';
    $('nPhotoPreview').className = 'thumb thumb-sm no-img';
    $('nPhotoPreview').innerHTML = '';
    renderRows($('nMaterials'), newDraft.materials, updateNewTotals);
    updateNewTotals();
  }
  function updateNewTotals() { fillSummary('n', newDraft); }

  $('openAddProduct').addEventListener('click', function () { resetNewProduct(); S.openModal('addProductModal'); });

  $('nName').addEventListener('input', function () {
    newDraft.name = this.value;
    newDraft.sku = makeSku(this.value);
    $('nSku').value = newDraft.sku;
    this.classList.remove('is-error');
  });
  $('nCategory').addEventListener('change', function () { newDraft.category = this.value; });
  ['Labor', 'Packaging', 'Markup'].forEach(function (f) {
    $('n' + f).addEventListener('input', function () { newDraft[f.toLowerCase()] = num(this.value); updateNewTotals(); });
  });
  $('nAddMaterial').addEventListener('click', function () {
    openAddMaterial('Adding to ' + (newDraft.name || 'new product') + ' costing sheet', function (item) {
      addToList(newDraft.materials, item);
      renderRows($('nMaterials'), newDraft.materials, updateNewTotals);
      updateNewTotals();
    });
  });

  // photo preview (the real upload is the backend's job, see TODO below)
  $('nPhoto').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    $('nPhotoName').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (ev) {
      newDraft.photo = ev.target.result;
      $('nPhotoPreview').className = 'thumb thumb-sm';
      $('nPhotoPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(file);
  });

  $('nSave').addEventListener('click', function () {
    if (!newDraft.name.trim()) { $('nName').classList.add('is-error'); $('nName').focus(); S.toast('Enter a product name.'); return; }
    if (!newDraft.materials.length) { S.toast('Add at least one material.'); return; }
    var next = products.reduce(function (mx, p) { return Math.max(mx, p.id); }, 0) + 1;
    var item = clone(newDraft);
    item.id = next;
    products.push(item);
    $('categoryFilter').value = 'all';
    $('productSearch').value = '';
    S.closeModal('addProductModal');
    selectProduct(next);
    S.toast(item.name + ' added to your products');
    // TODO (backend): send as FormData so the photo file is uploaded too, for example
    // var fd = new FormData(); fd.append('data', JSON.stringify(item)); fd.append('photo', $('nPhoto').files[0]);
    // fetch('../includes/api/add_product.php', { method: 'POST', body: fd });
  });

  // ---------- start ----------
  if (selectedId !== null) {
    selectProduct(selectedId);
  } else {
    $('costSheet').hidden = true;
    $('emptyState').hidden = false;
    renderList();
  }
})();