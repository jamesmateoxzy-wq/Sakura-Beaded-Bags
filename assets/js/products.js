/* Product Costing & Pricing */
(function () {
  var S = window.Sakura;
  var store = window.SakuraStore;
  var data = store.read();
  var products = data.products;
  var inventory = data.materials;
  var categories = data.categories;
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

  var calc = store.costing;

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
            '<input class="input" type="number" min="0.001" step="0.001" value="' + m.qty + '" data-k="qty" aria-label="Quantity of ' + esc(m.name) + '"><em>' + esc(m.unit) + '</em></div></label>' +
          '<label class="mat-cell"><span class="mat-label">Unit cost</span>' +
            '<input class="input" type="number" min="0" step="0.01" value="' + m.cost + '" data-k="cost" aria-label="Unit cost of ' + esc(m.name) + '"></label>' +
          '<div class="mat-sub"><span class="mat-label">Subtotal</span><b>' + money(store.cents(m.qty * m.cost) / 100) + '</b></div>' +
          '<button type="button" class="icon-btn" data-remove aria-label="Remove ' + esc(m.name) + '"><svg class="icon" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
          '</div>';
      }).join('');
    }
    container.oninput = function (e) {
      var row = e.target.closest('.mat-row');
      if (!row || !e.target.dataset.k) return;
      var m = list[+row.dataset.i];
      m[e.target.dataset.k] = num(e.target.value);
      row.querySelector('.mat-sub b').textContent = money(store.cents(m.qty * m.cost) / 100);
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
    var same = list.filter(function (m) { return m.id === item.id; })[0];
    if (same) { same.qty = Math.round((same.qty + item.qty) * 1000) / 1000; same.cost = item.cost; } else { list.push(item); }
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
        '<div><h3>' + esc(p.name) + '</h3><div class="cat">' + esc(p.category) + '</div>' +
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
    $('sMarkup').value = Number(draft.markup.toFixed(2));
    renderRows($('sMaterials'), draft.materials, updateSheetTotals);
    updateSheetTotals();
  }

  function updateSheetTotals(skipTarget) {
    var c = fillSummary('s', draft);
    if (skipTarget !== true) {
      $('sTarget').setCustomValidity('');
      $('sTarget').value = c.price.toFixed(2);
    }
  }

  $('sName').addEventListener('input', function () { draft.name = this.value; $('sTitle').textContent = (draft.name || 'New product') + ' · Costing sheet'; });
  $('sCategory').addEventListener('change', function () { draft.category = this.value; });
  ['Labor', 'Packaging', 'Markup'].forEach(function (f) {
    $('s' + f).addEventListener('input', function () { draft[f.toLowerCase()] = num(this.value); updateSheetTotals(); });
  });
  // typing a target price works backwards to the markup %
  $('sTarget').addEventListener('input', function () {
    var cost = calc(draft).cost;
    this.setCustomValidity(num(this.value) < cost ? 'Target price must cover the unit cost.' : '');
    draft.markup = cost > 0 ? Math.max(0, (num(this.value) / cost - 1) * 100) : 0;
    $('sMarkup').value = Number(draft.markup.toFixed(2));
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

  function validProduct(product, prefix) {
    const fields = document.querySelectorAll(prefix === 's' ? '#costSheet input[type=number]' : '#addProductModal input[type=number]');
    if (!product.name.trim() || !categories.includes(product.category) || !product.materials.length ||
        [...fields].some(field => !field.value || !field.checkValidity()) ||
        product.materials.some(item => item.qty < 0.001 || item.qty > 1000000 || item.cost > 1000000) ||
        !Number.isFinite(calc(product).price) || calc(product).price <= 0 || calc(product).price > 100000000) {
      S.toast('Enter a name, category, valid material quantities and costs, and a positive selling price.');
      return false;
    }
    product.name = product.name.trim();
    return true;
  }

  $('sSave').addEventListener('click', function () {
    if (!validProduct(draft, 's')) return;
    var index = products.indexOf(findProduct(selectedId));
    var previous = products[index];
    products[index] = clone(draft);
    if (!store.save(data)) {
      products[index] = previous;
      return;
    }
    renderList();
    renderSheet();
    S.toast('Pricing saved for ' + draft.name);
  });

  $('sDelete').addEventListener('click', function () {
    const product = findProduct(selectedId);
    if (!confirm('Delete ' + product.name + ' from the product catalog? Existing orders will keep their details.')) return;
    const index = products.indexOf(product);
    products.splice(index, 1);
    if (!store.save(data)) {
      products.splice(index, 0, product);
      return;
    }
    selectedId = null;
    draft = null;
    $('costSheet').hidden = true;
    $('emptyState').hidden = false;
    renderList();
    S.toast('Product deleted.');
  });

  function renderCategories() {
    const options = categories.map(category => '<option>' + esc(category) + '</option>').join('');
    for (const id of ['categoryFilter', 'sCategory', 'nCategory']) {
      const field = $(id);
      const previous = field.value;
      field.innerHTML = (id === 'categoryFilter' ? '<option value="all">All categories</option>' : '') + options;
      if ([...field.options].some(option => option.value === previous)) field.value = previous;
    }
  }

  $('categoryForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const name = $('categoryName').value.trim();
    if (!name || name.toLowerCase() === 'all' || categories.some(category => category.toLowerCase() === name.toLowerCase())) {
      S.toast('Enter a unique category name.');
      return;
    }
    categories.push(name);
    if (!store.save(data)) {
      categories.pop();
      return;
    }
    renderCategories();
    this.reset();
    S.closeModal('categoryModal');
    S.toast('Category added.');
  });

  renderCategories();
  $('categoryFilter').addEventListener('change', renderList);
  $('productSearch').addEventListener('input', renderList);

  // ---------- Add Material modal ----------
  function inv() { return inventory[$('amMaterial').selectedIndex] || inventory[0]; }

  function updateAmount() {
    var m = inv(), qty = num($('amQty').value), cost = num($('amCost').value);
    $('amAmount').textContent = money(store.cents(qty * cost) / 100);
    $('amStockInfo').textContent = m ? m.id + ' · In stock: ' + m.stock + ' ' + m.unit : '';
    var warn = $('amWarning');
    $('amAdd').disabled = !m;
    if (m && qty > m.stock) {
      warn.hidden = false;
      warn.innerHTML = '<strong>Not enough stock</strong>Only ' + m.stock + ' ' + esc(m.unit) + ' available. Record a Stock-In before accepting orders that need this.';
    } else { warn.hidden = true; }
  }

  function openAddMaterial(context, onAdd) {
    amOnAdd = onAdd;
    $('amContext').textContent = context;
    renderMaterialChoices();
    $('amQty').value = 1;
    $('amCost').value = inv() ? inv().cost : 0;
    updateAmount();
    S.openModal('addMaterialModal');
  }

  function renderMaterialChoices() {
    $('amMaterial').innerHTML = inventory.map(function (m) {
      return '<option>' + esc(m.name) + ' · ' + m.stock + ' ' + esc(m.unit) + ' available</option>';
    }).join('');
    if (!inventory.length) { $('amMaterial').innerHTML = '<option>No materials in inventory yet</option>'; }
  }

  $('materialCatalogForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const name = $('materialName').value.trim();
    const unit = $('materialUnit').value.trim();
    if (!name || !unit || inventory.some(material => material.name.toLowerCase() === name.toLowerCase())) {
      S.toast('Enter a unique material name and a unit.');
      return;
    }
    const next = inventory.reduce((max, item) => Math.max(max, Number(item.id.split('-').pop()) || 0), 0) + 1;
    const material = {id:'MAT-' + String(next).padStart(3, '0'), name, unit,
      cost:Number($('materialCost').value), min:Number($('materialMin').value), stock:0};
    inventory.push(material);
    if (!store.save(data)) {
      inventory.pop();
      return;
    }
    renderMaterialChoices();
    $('amMaterial').selectedIndex = inventory.length - 1;
    $('amCost').value = material.cost;
    updateAmount();
    this.reset();
    S.closeModal('materialCatalogModal');
    S.toast('Material registered. Use Inventory to record stock received.');
  });

  $('amMaterial').addEventListener('change', function () { $('amCost').value = inv().cost; updateAmount(); });
  $('amQty').addEventListener('input', updateAmount);
  $('amCost').addEventListener('input', updateAmount);
  $('amAdd').addEventListener('click', function () {
    if (!inv()) { S.toast('Register a material first.'); return; }
    var qty = num($('amQty').value);
    if (qty <= 0 || qty > 1000000 || !$('amQty').checkValidity() || !$('amCost').value || !$('amCost').checkValidity()) { $('amQty').classList.add('is-error'); S.toast('Enter a valid positive quantity and unit cost.'); return; }
    $('amQty').classList.remove('is-error');
    var m = inv();
    if (amOnAdd) amOnAdd({ id: m.id, name: m.name, unit: m.unit, qty: qty, cost: num($('amCost').value) });
    S.closeModal('addMaterialModal');
    S.toast(m.name + ' added');
  });

  // ---------- Add Product modal ----------
  function nextProductId() {
    return Math.max(data.lastProductId || 0, ...products.map(product => product.id)) + 1;
  }

  function makeSku(name) {
    var letters = name.toUpperCase().replace(/[^A-Z]/g, '').replace(/[AEIOU]/g, '').slice(0, 3);
    while (letters.length < 3) letters += 'X';
    return 'SAK-' + letters + '-' + String(nextProductId()).padStart(3, '0');
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
    const draftForPhoto = newDraft;
    draftForPhoto.photo = '';
    $('nPhotoPreview').innerHTML = '';
    $('nPhotoName').textContent = 'No file chosen';
    if (!file) return;
    if (file.size > 500000 || !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
      this.value = '';
      S.toast('Choose a JPG, PNG, WebP or GIF under 500 KB.');
      return;
    }
    $('nPhotoName').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (ev) {
      if (newDraft !== draftForPhoto || $('nPhoto').files[0] !== file) return;
      newDraft.photo = ev.target.result;
      $('nPhotoPreview').className = 'thumb thumb-sm';
      $('nPhotoPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(file);
  });

  $('nSave').addEventListener('click', function () {
    if (!validProduct(newDraft, 'n')) return;
    var next = nextProductId();
    var item = clone(newDraft);
    item.id = next;
    const previousId = data.lastProductId;
    data.lastProductId = next;
    products.push(item);
    if (!store.save(data)) {
      products.pop();
      data.lastProductId = previousId;
      return;
    }
    $('categoryFilter').value = 'all';
    $('productSearch').value = '';
    S.closeModal('addProductModal');
    selectProduct(next);
    S.toast(item.name + ' added to your products');
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