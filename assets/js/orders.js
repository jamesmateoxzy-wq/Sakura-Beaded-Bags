/* Sakura Beaded Bags - Order Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'orders') return;

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
    var places = decimals === undefined ? 2 : decimals;
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:places, maximumFractionDigits:places});
  }
  function dateText(value) {
    var date = new Date(value + 'T00:00:00');
    return isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
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
  function openModal(id) {
    if (window.Sakura) Sakura.openModal(id);
  }
  function closeModal(id) {
    if (window.Sakura) Sakura.closeModal(id);
  }
  function selectedProduct() {
    return products.find(function (product) { return String(product.id) === $('ordProduct').value; });
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
      '<div class="order-card-badges">' + badge(order.status) + badge(paymentStatus(order)) + '</div>' +
      '<span class="order-card-link" data-view="' + esc(order.id) + '">Click to view details →</span></article>';
  }
  function render() {
    var query = $('ordSearch').value.trim().toLowerCase();
    var filter = $('ordFilter').value;
    var shown = orders.filter(function (order) {
      var archived = order.archived;
      var inGroup = activeGroup === 'all' ||
        (activeGroup === 'production' && ['Pending', 'In Progress', 'Completed'].includes(order.status)) ||
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
        '</td><td>' + badge(order.delivery || 'Pending') + '</td><td>' + badge(paymentStatus(order)) + '</td></tr>';
    }).join('');
    $('ordEmpty').hidden = shown.length > 0;
    $('ordEmpty').textContent = orders.length ?
      'No orders match your search and filters.' : 'No orders yet. Create a new order to get started.';
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
      return '<option value="' + esc(product.id) + '">' + esc(product.name) + ' · ' + money(store.costing(product).price) + '</option>';
    }).join('');
  }
  function calculateOrder() {
    var product = selectedProduct();
    var qty = Math.max(1, Number($('ordQty').value) || 1);
    const unit = store.costing(product);
    const total = store.cents(unit.price) * qty;
    const policy = Number($('ordDpPolicy').value);
    $('ordUnitCost').textContent = money(unit.cost);
    $('ordUnitPrice').textContent = money(unit.price);
    $('ordTotalPrice').textContent = money(total / 100);
    $('ordDownpayment').textContent = money(Math.round(total * policy / 100) / 100);
  }
  function renderBom() {
    var orderQty = Math.max(1, Number($('ordQty').value) || 1);
    $('ordBomRows').innerHTML = currentBom.map(function (item) {
      var quantity = Math.round(item.qty * orderQty * 1000) / 1000;
      return '<tr><td>' + esc(item.name) + '</td><td>' + quantity + '</td><td>' + money(item.cost) +
        '</td><td>' + money(store.cents(item.qty * item.cost) * orderQty / 100) + '</td></tr>';
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
    loadProduct();
  }

  function loadProduct() {
    const product = selectedProduct();
    currentBom = cloneBom(product.materials);
    $('ordLabor').value = product.labor;
    $('ordPackaging').value = product.packaging;
    renderBom();
  }

  function paymentStatus(order) {
    const paid = store.paidCents(data.payments, order.id);
    return paid >= store.cents(order.total) ? 'Fully Paid' : paid > 0 ? 'Partial' : 'Unpaid';
  }

  const nextStatus = {'Pending':'In Progress', 'In Progress':'Completed', 'Completed':'Delivered'};
  const actionLabel = {'Pending':'Start Production', 'In Progress':'Mark Completed', 'Completed':'Mark Delivered'};

  function updateDetail(order) {
    detailOrder = order;
    $('ordDetailId').value = order.id;
    $('ordDetailCode').textContent = order.id + ' · ' + (order.customerId || 'CUST-' + order.id.slice(-3));
    $('ordDetailCustomer').textContent = order.customer;
    $('ordDetailAddress').textContent = order.address || 'Address not provided';
    $('ordDetailDate').textContent = dateText(order.date);
    $('ordDetailDueDate').textContent = dateText(order.dueDate || order.date);
    $('ordDetailProduct').textContent = order.product + ' ×' + order.qty;
    $('ordDetailItemPrice').textContent = money(order.total);
    $('ordDetailTotal').textContent = money(order.total);
    $('ordDetailProduction').textContent = order.status;
    $('ordDetailProduction').className = 'badge ' + badgeClass(order.status);
    $('ordDetailPayment').textContent = paymentStatus(order);
    $('ordDetailPayment').className = 'badge ' + badgeClass(paymentStatus(order));
    $('ordAdvance').hidden = !nextStatus[order.status];
    $('ordAdvance').textContent = actionLabel[order.status] || '';
    $('ordCancel').hidden = order.status !== 'Pending';
    $('ordArchive').hidden = order.archived || !['Cancelled', 'Delivered'].includes(order.status);
    const paid = store.paidCents(data.payments, order.id);
    const balance = Math.max(0, store.cents(order.total) - paid);
    const deposit = Math.max(0, store.cents(order.requiredDeposit || 0) - paid);
    $('ordDetailBalance').textContent = order.status === 'Cancelled' ? '' :
      'Balance ' + money(balance / 100) + (deposit ? ' · Deposit still due ' + money(deposit / 100) : '');
    $('ordDetailNotes').textContent = order.notes || '';
    $('ordRecordPayment').hidden = order.status === 'Cancelled' || balance === 0;
    $('ordRecordPayment').href = 'payments.php?action=record&order=' + encodeURIComponent(order.id);
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
  $('ordSearch').addEventListener('input', render);
  $('ordFilter').addEventListener('change', render);
  document.querySelectorAll('.orders-tab').forEach(function (tab) {
    tab.addEventListener('click', function () { activeGroup = tab.dataset.group; render(); });
  });
  if ($('ordExport')) $('ordExport').addEventListener('click', function () {
    var rows = [['Order ID','Date','Customer','Product','Quantity','Unit Price PHP','Total PHP','Status']]
      .concat(orders.map(function (o) { return [o.id,o.date,o.customer,o.product,o.qty,o.unitPrice,o.total,o.status]; }));
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(store.csvCell).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-orders-demo.csv'; document.body.appendChild(link); link.click(); link.remove();
    URL.revokeObjectURL(url);
  });
  $('ordNew').addEventListener('click', function () {
    if (!products.length) { notify('Add a product in Product Costing & Pricing first.'); return; }
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
    const dueDate = $('ordDueDate').value;
    const product = selectedProduct();
    const policy = Number($('ordDpPolicy').value);
    function fail(message) {
      $('ordCreateError').textContent = message;
      $('ordCreateError').hidden = false;
    }
    if (!customer || !address || !product || !Number.isInteger(qty) || qty < 1 || qty > 1000 ||
        !store.validDate(dueDate) || dueDate < store.today() || policy < 0 || policy > 100) {
      fail('Enter a valid order quantity, customer, address and due date.');
      return;
    }
    const number = nextOrderNumber();
    const id = orderId(number);
    const unitPrice = store.costing(product).price;
    const total = store.cents(unitPrice) * qty / 100;
    const allocations = currentBom.map(item => ({
      material:materials.find(material => material.id === item.id),
      qty:Math.round(item.qty * qty * 1000) / 1000
    }));
    const short = allocations.find(entry => !entry.material || entry.qty > entry.material.stock);
    if (short) {
      fail(short.material ? 'Insufficient stock for ' + short.material.name + '. Record a Stock-In first.' :
        'A material is missing. Update the product recipe in Product Costing & Pricing.');
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
    if (!store.save(data)) {
      orders.pop();
      allocations.forEach((entry, index) => { entry.material.stock = previousStocks[index]; });
      data.inventoryHistory.splice(0, allocations.length);
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
        return '<li><span>' + esc(item.name) + '</span><strong>' + esc(Math.round(item.qty * detailOrder.qty * 1000) / 1000) + ' ' + esc(item.unit) + '</strong></li>';
      }).join('') + '</ul>';
      target.hidden = false;
      $('ordViewBom').textContent = 'Hide BOM';
    } else {
      target.hidden = true;
      $('ordViewBom').textContent = 'View BOM';
    }
  });
  $('ordAdvance').addEventListener('click', function () {
    if (!detailOrder || !nextStatus[detailOrder.status]) return;
    const paid = store.paidCents(data.payments, detailOrder.id);
    if (detailOrder.status === 'Pending' && paid < store.cents(detailOrder.requiredDeposit || 0)) {
      notify('Record the required deposit in Payments & Sales before starting production.');
      return;
    }
    if (detailOrder.status === 'Completed' && paid < store.cents(detailOrder.total)) {
      notify('Record the remaining payment before marking the order delivered.');
      return;
    }
    const previous = {...detailOrder};
    detailOrder.status = nextStatus[detailOrder.status];
    if (detailOrder.status === 'Delivered') detailOrder.delivery = 'Delivered';
    if (!store.save(data)) { Object.assign(detailOrder, previous); return; }
    closeModal('ordDetailModal');
    render();
    notify(detailOrder.id + ' updated to ' + detailOrder.status + '.');
  });

  $('ordCancel').addEventListener('click', function () {
    if (!detailOrder || detailOrder.status !== 'Pending') return;
    if (store.paidCents(data.payments, detailOrder.id) > 0) {
      notify('This order has a recorded payment. The prototype has no refund workflow.');
      return;
    }
    if (!confirm('Cancel ' + detailOrder.id + ' and release its reserved stock?')) return;
    const released = [];
    if (detailOrder.stockReserved) {
      for (const item of detailOrder.materials) {
        const material = materials.find(entry => entry.id === item.id);
        if (!material || material.stock + item.qty * detailOrder.qty > 100000) {
          notify('Inventory could not accept the returned stock. Review the material balance.');
          return;
        }
        released.push({material, stock:material.stock, qty:item.qty * detailOrder.qty});
      }
    }
    released.forEach(entry => store.movement(data, entry.material, detailOrder.id + ' cancelled', entry.qty));
    const reserved = detailOrder.stockReserved;
    const previousDelivery = detailOrder.delivery;
    detailOrder.status = 'Cancelled';
    detailOrder.delivery = 'Cancelled';
    detailOrder.stockReserved = false;
    if (!store.save(data)) {
      detailOrder.status = 'Pending';
      detailOrder.delivery = previousDelivery;
      detailOrder.stockReserved = reserved;
      released.forEach(entry => { entry.material.stock = entry.stock; });
      data.inventoryHistory.splice(0, released.length);
      return;
    }
    closeModal('ordDetailModal');
    render();
    notify(detailOrder.id + ' cancelled.');
  });

  $('ordArchive').addEventListener('click', function () {
    if (!detailOrder || !['Cancelled', 'Delivered'].includes(detailOrder.status)) return;
    detailOrder.archived = true;
    if (!store.save(data)) { detailOrder.archived = false; return; }
    closeModal('ordDetailModal');
    activeGroup = 'archived';
    render();
  });

  render();
  if (new URLSearchParams(location.search).get('action') === 'new') $('ordNew').click();
})();