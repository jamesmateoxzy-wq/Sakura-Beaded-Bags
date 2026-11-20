/* Sakura Beaded Bags - Order Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'orders') return;

  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var storageUnavailable = false;
  var storageFailureReported = false;
  var products = [
    { id:'heart', name:'Heart Bag', price:1200, labor:200, packaging:50, materials:[] },
    { id:'pink', name:'Pink Bag', price:850, labor:150, packaging:50, materials:[] },
    { id:'pearl', name:'Pearl Tote', price:1500, labor:250, packaging:50, materials:[] }
  ];
  function clone(value) { return JSON.parse(JSON.stringify(value)); }
  function loadStore() {
    try { return JSON.parse(sessionStorage.getItem(key) || '{}'); }
    catch (error) {
      storageUnavailable = true;
      reportStorageFailure();
      return {};
    }
  }
  function loadOrders() {
    if (!demo) return [];
    var stored = loadStore();
    return Array.isArray(stored.orders) ? stored.orders : [];
  }
  function loadMaterials() {
    if (!demo) return [];
    var stored = loadStore();
    return Array.isArray(stored.materials) ? stored.materials : [];
  }
  function loadProductCatalog() {
    if (!demo) return;
    var stored = loadStore();
    if (!Array.isArray(stored.products)) return;
    stored.products.forEach(function (product) {
      if (!product || !product.name) return;
      var materials = Array.isArray(product.materials) ? product.materials : [];
      var labor = Number(product.labor) || 0;
      var packaging = Number(product.packaging) || 0;
      var materialCost = materials.reduce(function (sum, material) {
        return sum + (Number(material.qty) || 0) * (Number(material.cost) || 0);
      }, 0);
      var price = (materialCost + labor + packaging) * (1 + (Number(product.markup) || 0) / 100);
      var catalogProduct = {
        id:'catalog-' + String(product.id),
        name:String(product.name),
        price:price,
        labor:labor,
        packaging:packaging,
        materials:clone(materials)
      };
      var existingIndex = products.findIndex(function (existing) {
        return existing.name.toLowerCase() === catalogProduct.name.toLowerCase();
      });
      if (existingIndex === -1) products.push(catalogProduct);
      else products[existingIndex] = Object.assign({}, products[existingIndex], catalogProduct, {id:products[existingIndex].id});
    });
  }
  function save() {
    if (!demo) return false;
    if (storageUnavailable) return false;
    try {
      var stored = loadStore();
      if (storageUnavailable) return false;
      stored.orders = orders;
      sessionStorage.setItem(key, JSON.stringify(stored));
      return true;
    } catch (error) {
      storageUnavailable = true;
      reportStorageFailure();
      return false;
    }
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value, decimals) {
    var places = decimals === undefined ? 0 : decimals;
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:places, maximumFractionDigits:places});
  }
  function dateText(value) {
    var date = new Date(value + 'T00:00:00');
    return isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
  }
  function dateValue(value) {
    var date = value instanceof Date ? value : new Date(value);
    if (isNaN(date.getTime())) return '';
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
  }
  function badgeClass(status) {
    return status === 'Completed' || status === 'Delivered' || status === 'Fully Paid' ? 'badge-green' :
      status === 'Pending' ? 'badge-amber' : status === 'In Progress' ? 'badge-blue' : 'badge-pink';
  }
  function badge(status) {
    return '<span class="badge ' + badgeClass(status) + '">' + esc(status) + '</span>';
  }
  function notify(message) {
    if (window.Sakura && Sakura.toast) Sakura.toast(message);
  }
  function reportStorageFailure() {
    if (storageFailureReported) return;
    storageFailureReported = true;
    notify('Could not read or save demo changes in this browser session.');
  }
  function openModal(id) {
    if (window.Sakura) Sakura.openModal(id);
  }
  function closeModal(id) {
    if (window.Sakura) Sakura.closeModal(id);
  }
  function selectedProduct() {
    return products.find(function (product) { return product.id === $('ordProduct').value; }) || products[0];
  }
  function nextOrderNumber() {
    return orders.reduce(function (max, order) {
      return Math.max(max, Number(String(order.id).split('-')[1]) || 0);
    }, 0) + 1;
  }
  function orderId(number) { return 'ORD-' + String(number).padStart(3, '0'); }
  function cloneBom(items) { return clone(items || []); }

  loadProductCatalog();
  var orders = loadOrders();
  var materials = loadMaterials();
  var activeGroup = 'all';
  var activeOrderId = '';
  var currentBom = [];
  var pendingMaterial = null;
  var detailOrder = null;

  function refreshMaterials() {
    materials = loadMaterials();
  }

  function cardMarkup(order, index) {
    return '<article class="order-card' + (order.id === activeOrderId ? ' is-highlighted' : '') + '" data-view="' + esc(order.id) + '" tabindex="0" role="button" aria-label="View order ' + esc(order.id) + '">' +
      '<div class="order-card-top"><span class="order-number">' + String(index + 1).padStart(2, '0') + '</span><span>' + esc(order.id) + '</span></div>' +
      '<h3>' + esc(order.customer) + '</h3>' +
      '<div class="order-card-product"><span>' + esc(order.product) + ' ×' + order.qty + '</span><strong>' + money(order.total) + '</strong></div>' +
      '<p class="order-card-due">Due ' + dateText(order.dueDate || order.date) + '</p>' +
      '<div class="order-card-badges">' + badge(order.status) + badge(order.payment || 'Unpaid') + '</div>' +
      '<span class="order-card-link" data-view="' + esc(order.id) + '">Click to view details →</span></article>';
  }
  function render() {
    var query = $('ordSearch').value.trim().toLowerCase();
    var filter = $('ordFilter').value;
    var shown = orders.filter(function (order) {
      var archived = order.archived || order.status === 'Completed' || order.status === 'Delivered' || order.status === 'Cancelled';
      var inGroup = activeGroup === 'all' ||
        (activeGroup === 'production' && !archived) ||
        (activeGroup === 'archived' && archived);
      return inGroup && (filter === 'all' || order.status === filter) &&
        (!query || (order.id + ' ' + order.customer + ' ' + order.product).toLowerCase().includes(query));
    }).sort(function (a, b) { return a.id.localeCompare(b.id); });

    $('ordTotal').textContent = orders.length;
    $('ordProduction').textContent = orders.filter(function (o) {
      return !o.archived && (o.status === 'Pending' || o.status === 'In Progress');
    }).length;
    $('ordRows').innerHTML = shown.map(function (order) {
      return '<tr data-view="' + esc(order.id) + '"><td>' + esc(order.id) + '</td><td>' + dateText(order.date) +
        '</td><td>' + esc(order.customer) + '</td><td>' + esc(order.product) + ' ×' + order.qty +
        '</td><td>' + money(order.total) + '</td><td>' + badge(order.status) +
        '</td><td>' + badge(order.delivery || 'Pending') + '</td><td>' + badge(order.payment || 'Unpaid') + '</td></tr>';
    }).join('');
    $('ordEmpty').hidden = shown.length > 0;
    $('ordEmpty').textContent = orders.length ?
      'No orders match your search and filters.' : 'No orders yet. Create a new order to get started.';
    $('ordAddMaterial').disabled = !demo;
    var currentOrders = orders.filter(function (order) {
      return !order.archived && (order.status === 'Pending' || order.status === 'In Progress');
    }).sort(function (a, b) { return a.date.localeCompare(b.date) || b.id.localeCompare(a.id); }).slice(0, 3);
    $('ordCards').innerHTML = currentOrders.map(cardMarkup).join('');
    document.querySelectorAll('.orders-tab').forEach(function (tab) {
      var selected = tab.dataset.group === activeGroup;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
  }

  function renderProductOptions() {
    $('ordProduct').innerHTML = products.map(function (product) {
      return '<option value="' + esc(product.id) + '">' + esc(product.name) + ' · ' + money(product.price) + '</option>';
    }).join('');
  }
  function calculateOrder() {
    var product = selectedProduct();
    var qty = Math.max(1, Number($('ordQty').value) || 1);
    var materialCost = currentBom.reduce(function (sum, material) { return sum + Number(material.qty) * Number(material.cost); }, 0);
    var unitCost = materialCost + (Number($('ordLabor').value) || 0) + (Number($('ordPackaging').value) || 0);
    var unitPrice = Number(product.price) || 0;
    var total = unitPrice * qty;
    var dp = total * Math.max(0, Math.min(100, Number($('ordDpPolicy').value) || 0)) / 100;
    $('ordUnitCost').textContent = money(unitCost);
    $('ordUnitPrice').textContent = money(unitPrice);
    $('ordTotalPrice').textContent = money(total);
    $('ordDownpayment').textContent = money(dp);
  }
  function renderBom() {
    var orderQty = Math.max(1, Number($('ordQty').value) || 1);
    $('ordBomRows').innerHTML = currentBom.map(function (item, index) {
      var quantity = Number(item.qty) * orderQty;
      return '<tr><td>' + esc(item.name) + '</td><td>' + quantity + '</td><td>' + money(item.cost) +
        '</td><td>' + money(quantity * Number(item.cost)) + '</td><td><button type="button" class="order-remove-material" data-remove-material="' + index + '" aria-label="Remove ' + esc(item.name) + '">×</button></td></tr>';
    }).join('');
    calculateOrder();
  }
  function resetCreateForm() {
    $('ordCreateForm').reset();
    $('ordId').value = orderId(nextOrderNumber());
    $('ordCustomer').value = '';
    $('ordAddress').value = '';
    $('ordProduct').value = 'heart';
    $('ordQty').value = '1';
    $('ordDueDate').value = '';
    $('ordNotes').value = '';
    $('ordLabor').value = '0';
    $('ordPackaging').value = '0';
    $('ordDpPolicy').value = '50';
    currentBom = [];
    $('ordCreateError').hidden = true;
    renderBom();
  }
  function renderMaterialOptions() {
    $('ordMaterialSelect').innerHTML = materials.map(function (material) {
      return '<option value="' + esc(material.id) + '">' + esc(material.name) + ' · ' + material.stock + ' ' + esc(material.unit) + '</option>';
    }).join('');
    if (!materials.length) $('ordMaterialSelect').innerHTML = '<option value="">No inventory materials available</option>';
  }
  function selectedMaterial() {
    return materials.find(function (material) { return material.id === $('ordMaterialSelect').value; });
  }
  function updateMaterialDialog() {
    var material = selectedMaterial();
    var quantity = Math.max(0, Number($('ordMaterialQty').value) || 0);
    $('ordMaterialError').hidden = true;
    if (!material) {
      $('ordMaterialMeta').textContent = 'Add materials in Inventory Management first.';
      $('ordMaterialCost').value = '';
      $('ordMaterialAmount').textContent = money(0, 2);
      $('ordMaterialCalc').textContent = '';
      $('ordStockWarning').hidden = true;
      return;
    }
    var remainder = Number(material.stock) - quantity;
    var cost = Number(material.cost) || 0;
    $('ordMaterialCost').value = money(cost, 2) + ' / ' + material.unit.replace(/s$/, '');
    $('ordMaterialMeta').textContent = material.id + ' · In stock: ' + material.stock + ' ' + material.unit + ' · Reorder threshold: ' + (material.min || 0) + ' ' + material.unit;
    $('ordMaterialAmount').textContent = money(quantity * cost, 2);
    $('ordMaterialCalc').textContent = quantity + ' ' + material.unit + ' × ' + money(cost, 2);
    $('ordStockWarning').hidden = !(quantity > 0 && remainder < Number(material.min || 0));
    $('ordStockWarningText').textContent = material.stock + ' − ' + quantity + ' = ' + Math.max(0, remainder) + ' ' + material.unit + ' remaining, below the ' + (material.min || 0) + ' ' + material.unit + ' threshold.';
  }
  function addMaterialToOrder() {
    var material = selectedMaterial();
    var quantity = Number($('ordMaterialQty').value);
    if (!material || !Number.isInteger(quantity) || quantity <= 0) {
      $('ordMaterialError').textContent = 'Select a material and enter a whole quantity greater than zero.';
      $('ordMaterialError').hidden = false;
      return;
    }
    if (quantity > Number(material.stock)) {
      $('ordMaterialError').textContent = 'The requested quantity exceeds the available stock.';
      $('ordMaterialError').hidden = false;
      return;
    }
    currentBom.push({ id:material.id, name:material.name, unit:material.unit, qty:quantity, cost:Number(material.cost) || 0 });
    renderBom();
    closeModal('ordMaterialModal');
    notify(material.name + ' added to this order.');
  }
  function updateDetail(order) {
    detailOrder = order;
    $('ordDetailId').value = order.id;
    $('ordDetailCode').textContent = order.id + ' · ' + (order.customerId || 'CUST-' + order.id.slice(-3)) + ' · ' + (order.slipId || 'SLP-' + order.id.slice(-3));
    $('ordDetailCustomer').textContent = order.customer;
    $('ordDetailAddress').textContent = order.address || 'Address not provided';
    $('ordDetailDate').textContent = dateText(order.date);
    $('ordDetailDueDate').textContent = dateText(order.dueDate || order.date);
    $('ordDetailProduct').textContent = order.product + ' ×' + order.qty;
    $('ordDetailItemPrice').textContent = money(order.total);
    $('ordDetailTotal').textContent = money(order.total);
    $('ordDetailProduction').textContent = order.status;
    $('ordDetailProduction').className = 'badge ' + badgeClass(order.status);
    $('ordDetailPayment').textContent = order.payment || 'Unpaid';
    $('ordDetailPayment').className = 'badge ' + badgeClass(order.payment || 'Unpaid');
    $('ordDetailBom').hidden = true;
    $('ordViewBom').textContent = 'View BOM';
  }
  function showDetails(id) {
    var order = orders.find(function (item) { return item.id === id; });
    if (!order) return;
    activeOrderId = order.id;
    render();
    updateDetail(order);
    openModal('ordDetailModal');
  }

  renderProductOptions();
  renderMaterialOptions();
  $('ordSearch').addEventListener('input', render);
  $('ordFilter').addEventListener('change', render);
  document.querySelectorAll('.orders-tab').forEach(function (tab) {
    tab.addEventListener('click', function () { activeGroup = tab.dataset.group; render(); });
  });
  if ($('ordExport')) $('ordExport').addEventListener('click', function () {
    var rows = [['Order ID','Date','Customer','Product','Quantity','Unit Price PHP','Total PHP','Status']]
      .concat(orders.map(function (o) { return [o.id,o.date,o.customer,o.product,o.qty,o.unitPrice,o.total,o.status]; }));
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(function (value) { return '"' + String(value).replace(/"/g,'""') + '"'; }).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-orders-demo.csv'; document.body.appendChild(link); link.click(); link.remove();
    URL.revokeObjectURL(url);
  });
  $('ordNew').addEventListener('click', function () {
    if (!demo) return;
    resetCreateForm();
    openModal('ordCreateModal');
  });
  document.querySelectorAll('[data-focus]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = $(button.dataset.focus);
      field.focus();
      if (field.tagName === 'SELECT' && typeof field.showPicker === 'function') {
        try { field.showPicker(); } catch (error) {}
      }
    });
  });
  $('ordProduct').addEventListener('change', function () {
    var product = selectedProduct();
    currentBom = cloneBom(product.materials);
    $('ordLabor').value = product.labor;
    $('ordPackaging').value = product.packaging;
    renderBom();
  });
  ['ordQty', 'ordLabor', 'ordPackaging', 'ordDpPolicy'].forEach(function (id) {
    $(id).addEventListener('input', renderBom);
  });
  $('ordBomRows').addEventListener('click', function (event) {
    var button = event.target.closest('[data-remove-material]');
    if (!button) return;
    currentBom.splice(Number(button.dataset.removeMaterial), 1);
    renderBom();
  });
  $('ordAddMaterial').addEventListener('click', function () {
    if (!demo) return;
    refreshMaterials();
    var number = $('ordId').value;
    $('ordMaterialContext').textContent = 'Adding to ' + number + ' · ' + selectedProduct().name;
    renderMaterialOptions();
    $('ordMaterialSelect').value = materials[0] ? materials[0].id : '';
    $('ordMaterialQty').value = '1';
    updateMaterialDialog();
    openModal('ordMaterialModal');
  });
  $('ordMaterialSelect').addEventListener('change', function () {
    $('ordMaterialQty').value = '1';
    updateMaterialDialog();
  });
  $('ordMaterialQty').addEventListener('input', updateMaterialDialog);
  $('ordMaterialAdd').addEventListener('click', addMaterialToOrder);
  $('ordCreateForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var customer = $('ordCustomer').value.trim();
    var address = $('ordAddress').value.trim();
    var qty = Number($('ordQty').value);
    var dueDate = dateValue($('ordDueDate').value);
    var product = selectedProduct();
    if (!demo || !customer || !address || !product || !Number.isInteger(qty) || qty < 1 || !dueDate) {
      $('ordCreateError').textContent = 'Complete all required fields with valid values.';
      $('ordCreateError').hidden = false;
      return;
    }
    var number = nextOrderNumber();
    var unitPrice = Number(product.price);
    var total = unitPrice * qty;
    orders.push({
      id:orderId(number), customerId:'CUST-' + String(number).padStart(3,'0'), slipId:'SLP-' + String(number).padStart(3,'0'),
      customer:customer, address:address, product:product.name, qty:qty, unitPrice:unitPrice, total:total,
      date:dateValue(new Date()), dueDate:dueDate, status:'Pending', delivery:'Pending',
      payment:'Unpaid', deposit:0, notes:$('ordNotes').value.trim(), labor:Number($('ordLabor').value) || 0,
      packaging:Number($('ordPackaging').value) || 0, dpPolicy:Number($('ordDpPolicy').value) || 0,
      materials:cloneBom(currentBom)
    });
    if (!save()) {
      orders.pop();
      return;
    }
    closeModal('ordCreateModal');
    activeGroup = 'all';
    render();
    notify('Order ' + orderId(number) + ' added to the production queue.');
  });
  $('ordRows').addEventListener('click', function (event) {
    var row = event.target.closest('[data-view]');
    if (row) showDetails(row.dataset.view);
  });
  $('ordCards').addEventListener('click', function (event) {
    var card = event.target.closest('[data-view]');
    if (card) showDetails(card.dataset.view);
  });
  $('ordCards').addEventListener('keydown', function (event) {
    var card = event.target.closest('.order-card');
    if (card && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      showDetails(card.dataset.view);
    }
  });
  $('ordViewBom').addEventListener('click', function () {
    if (!detailOrder) return;
    var target = $('ordDetailBom');
    if (target.hidden) {
      target.innerHTML = '<ul>' + (detailOrder.materials || []).map(function (item) {
        return '<li><span>' + esc(item.name) + '</span><strong>' + esc(item.qty) + ' ' + esc(item.unit) + '</strong></li>';
      }).join('') + '</ul>';
      target.hidden = false;
      $('ordViewBom').textContent = 'Hide BOM';
    } else {
      target.hidden = true;
      $('ordViewBom').textContent = 'View BOM';
    }
  });
  $('ordCancel').addEventListener('click', function () {
    if (!demo || !detailOrder) return;
    var previousStatus = detailOrder.status;
    detailOrder.status = 'Cancelled';
    if (!save()) { detailOrder.status = previousStatus; return; }
    closeModal('ordDetailModal'); render(); notify(detailOrder.id + ' cancelled.');
  });
  $('ordArchive').addEventListener('click', function () {
    if (!demo || !detailOrder) return;
    detailOrder.archived = true;
    if (!save()) { detailOrder.archived = false; return; }
    closeModal('ordDetailModal'); activeGroup = 'archived'; render(); notify(detailOrder.id + ' archived.');
  });

  render();
})();
