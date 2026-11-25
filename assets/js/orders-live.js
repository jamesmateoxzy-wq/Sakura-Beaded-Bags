/* Sakura Beaded Bags — Orders page backed by MySQL (not sessionStorage). */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'orders') return;
  var $ = function (id) { return document.getElementById(id); };
  var api = 'api/orders.php';
  var products = [];
  var orders = [];
  var csrf = '';
  var activeGroup = 'all';
  var activeOrderId = '';
  var detailOrder = null;
  var busy = false;
  var pendingOrderAction = '';
  var requestKey = '';
  var ready = false;

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c];
    });
  }
  function money(v) {
    return '₱' + Number(v || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function notify(v) {
    if (window.Sakura && typeof window.Sakura.toast === 'function') window.Sakura.toast(v);
    else console.info(v);
  }
  function openModal(id) { if (window.Sakura) Sakura.openModal(id); }
  function closeModal(id) { if (window.Sakura) Sakura.closeModal(id); }
  function warn(v) { $('ordCreateError').textContent = v; $('ordCreateError').hidden = !v; }
  function pad(v) { return String(v).padStart(2, '0'); }
  function orderNumber(v) { return 'ORD-' + String(v).padStart(3, '0'); }
  function currentDateISO(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function dueDateISO(value) {
    var raw = String(value || '').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      var date = new Date(raw + 'T12:00:00');
      return !isNaN(date.getTime()) && currentDateISO(date) === raw ? raw : '';
    }
    var parsed = new Date(raw);
    return isNaN(parsed.getTime()) ? '' : currentDateISO(parsed);
  }
  function dateText(v) {
    if (!v) return 'Not set';
    var date = new Date(v + 'T12:00:00');
    return isNaN(date.getTime()) ? String(v) : date.toLocaleDateString('en-PH', {year:'numeric', month:'short', day:'numeric'});
  }
  function deliveryLabel(s) { return s === 'In Transit' ? 'Shipped' : s; }
  function badgeClass(s) {
    return ['Completed','Delivered','Paid'].includes(s) ? 'badge-green' :
      ['Pending','Not Scheduled','Unpaid'].includes(s) ? 'badge-amber' :
      ['In Production','Ready','For Delivery','In Transit','Shipped','Partial'].includes(s) ? 'badge-blue' : 'badge-pink';
  }
  function badge(s) { return '<span class="badge ' + badgeClass(s) + '">' + esc(s) + '</span>'; }
  function product() {
    return products.find(function (p) { return String(p.id) === $('ordProduct').value; }) || null;
  }
  function orderQty() { return Number($('ordQty').value); }
  function formatId(n) { return String(n).padStart(3, '0'); }

  async function fetchJSON(url, options) {
    var response = await fetch(url, Object.assign({credentials:'same-origin', cache:'no-store'}, options || {}));
    var body;
    try { body = await response.json(); }
    catch (e) { throw new Error('The server did not return valid JSON. Check your PHP terminal.'); }
    if (!response.ok || !body.ok) throw new Error(body.error || 'API request failed (' + response.status + ').');
    return body;
  }
  async function loadData() {
    var data = await fetchJSON(api);
    products = data.products || [];
    orders = data.orders || [];
    csrf = data.csrf || '';
    renderProducts();
    renderOrders();
    ready = true;
    $('ordNew').disabled = products.length === 0;
    if (submitButton) submitButton.disabled = false;
    if (!products.length) notify('Create a product with a material recipe before placing an order.');
  }
  function errorNotice(msg) {
    notify(msg);
    if ($('ordEmpty')) { $('ordEmpty').hidden = false; $('ordEmpty').textContent = msg; }
  }
  async function submitAction(data) {
    return fetchJSON(api, {method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-Token': csrf}, body:JSON.stringify(data)});
  }
  function renderProducts() {
    var selected = $('ordProduct').value;
    $('ordProduct').innerHTML = products.map(function (p) {
      return '<option value="' + esc(p.id) + '">' + esc(p.name) + ' · ' + money(p.price) + '</option>';
    }).join('');
    if (products.some(function (p) { return String(p.id) === selected; })) $('ordProduct').value = selected;
    syncProduct();
  }
  function syncProduct() {
    var p = product();
    $('ordLabor').value = p ? p.labor : 0;
    $('ordPackaging').value = p ? p.packaging : 0;
    $('ordDpPolicy').value = p ? p.downPaymentPercent : 50;
    renderBom();
  }
  function renderBom() {
    var p = product();
    var qty = Math.max(1, Math.floor(orderQty() || 1));
    $('ordBomTitle').textContent = p ? 'Bill of Materials — ' + p.name : 'Bill of Materials';
    $('ordBomRows').innerHTML = (p ? p.materials : []).map(function (m) {
      var needed = Number(m.qty) * qty;
      return '<tr><td>' + esc(m.name) + '</td><td>' + esc(needed.toFixed(2)) + ' ' + esc(m.unit) +
        '</td><td>' + money(m.cost) + '</td><td>' + money(needed * m.cost) + '</td><td>—</td></tr>';
    }).join('');
    var unitCost = p ? Number(p.unitCost) : 0;
    var price = p ? Number(p.price) : 0;
    var dpPercent = p ? Number(p.downPaymentPercent) : 0;
    $('ordUnitCost').textContent = money(unitCost);
    $('ordUnitPrice').textContent = money(price);
    $('ordTotalPrice').textContent = money(price * qty);
    $('ordDownpayment').textContent = money(price * qty * dpPercent / 100);
    $('ordFifoNote').textContent = 'Recipe from Products · inventory deducted when production starts';
    if (!p || !p.materials.length) return;
    var short = p.materials.find(function (m) { return Number(m.qty) * qty > Number(m.stock) + 0.00001; });
    if (short) warn('Stock warning: not enough ' + short.name + ': requires ' + (Number(short.qty) * qty).toFixed(2) +
      ' ' + short.unit + ', currently ' + Number(short.stock).toFixed(2) + ' available.');
    else warn('');
  }
  function resetNewForm() {
    $('ordCreateForm').reset();
    $('ordId').value = orderNumber(orders.reduce(function (max, o) { return Math.max(max, Number(o.rawId) || 0); }, 0) + 1) + ' (preview)';
    $('ordCustomer').value = '';
    $('ordAddress').value = '';
    $('ordPhone').value = '';
    $('ordNotes').value = '';
    $('ordQty').value = '1';
    $('ordDueDate').value = '';
    requestKey = window.crypto && crypto.randomUUID ? crypto.randomUUID() : '';
    $('ordProduct').value = products.length ? String(products[0].id) : '';
    syncProduct();
  }
  function archived(o) {
    return Boolean(o.archived);
  }
  function currentProduction(o) {
    return !archived(o) && o.delivery !== 'Delivered' &&
      ['Pending','In Production','Ready','Completed'].includes(o.status);
  }
  function itemSummary(o) {
    return (o.items || []).map(function (v) { return v.product + ' ×' + v.qty; }).join(', ') || 'No items';
  }
  function renderOrders() {
    var query = $('ordSearch').value.trim().toLowerCase();
    var filter = $('ordFilter').value;
    var shown = orders.filter(function (o) {
      var inGroup = (activeGroup === 'all' && !archived(o)) ||
        (activeGroup === 'production' && currentProduction(o)) ||
        (activeGroup === 'archived' && archived(o));
      return inGroup && (filter === 'all' || o.status === filter || (filter === 'In Progress' && o.status === 'In Production') || (filter === 'Delivered' && o.delivery === 'Delivered') || (filter === 'In Transit' && o.delivery === 'In Transit')) &&
        (!query || (o.id + ' ' + o.customer + ' ' + itemSummary(o)).toLowerCase().includes(query));
    }).sort(function (a,b) { return Number(a.rawId) - Number(b.rawId); });
    $('ordTotal').textContent = orders.filter(function (o) {
      return !archived(o);
    }).length;
    $('ordProduction').textContent = orders.filter(currentProduction).length;
    var isArchiveView = activeGroup === 'archived';
    document.querySelector('.orders-current').hidden = isArchiveView;
    $('ordNew').hidden = isArchiveView;
    $('ordArchiveActions').hidden = !isArchiveView;
    $('ordRows').innerHTML = shown.map(function (o) {
      return '<tr data-view="' + esc(o.id) + '"><td>' + esc(o.id) + '</td><td>' + dateText(o.date) +
        '</td><td>' + esc(o.customer) + '</td><td>' + esc(itemSummary(o)) + '</td><td>' + money(o.total) +
        '</td><td>' + badge(o.status) + '</td><td>' + badge(deliveryLabel(o.delivery)) + '</td><td>' + badge(o.payment) + '</td>' + (isArchiveView ? '<td><button type="button" class="btn btn-outline btn-sm" data-view="' + esc(o.id) + '">View Details</button>' + (o.status !== 'Cancelled' && o.delivery !== 'Delivered' ? ' <button type="button" class="btn btn-outline btn-sm" data-unarchive="' + esc(o.id) + '"' + (busy ? ' disabled' : '') + '>Remove from Archive</button>' : '') + '</td>' : '') + '</tr>';
    }).join('');
    $('ordEmpty').hidden = shown.length > 0;
    $('ordEmpty').textContent = orders.length ? 'No orders match your search or filter.' : 'No orders yet. Create an order to start.';
    var cards = orders.filter(currentProduction).sort(function (a,b) { return Number(a.rawId)-Number(b.rawId); }).slice(0,3);
    $('ordCards').innerHTML = cards.map(function (o,i) {
      return '<article class="order-card' + (o.id === activeOrderId ? ' is-highlighted' : '') + '" data-view="' + esc(o.id) +
        '" tabindex="0" role="button" aria-label="View order ' + esc(o.id) + '">' +
        '<div class="order-card-top"><span class="order-number">' + formatId(i+1) + '</span><span>' + esc(o.id) + '</span></div>' +
        '<h3>' + esc(o.customer) + '</h3><div class="order-card-product"><span>' + esc(itemSummary(o)) +
        '</span><strong>' + money(o.total) + '</strong></div><p class="order-card-due">Due ' + dateText(o.dueDate || o.date) + '</p>' +
        '<div class="order-card-badges">' + badge(o.status) + badge(o.payment) + '</div>' +
        '<span class="order-card-link">Click to view details →</span></article>';
    }).join('');
    document.querySelectorAll('.orders-tab').forEach(function (tab) {
      var yes = tab.dataset.group === activeGroup;
      tab.classList.toggle('is-active', yes);
      tab.setAttribute('aria-selected', yes ? 'true' : 'false');
    });
  }
  function showDetails(id) {
    var o = orders.find(function (a) { return a.id === id; });
    if (!o) return;
    activeOrderId = id;
    detailOrder = o;
    renderOrders();
    $('ordDetailId').value = o.rawId;
    $('ordDetailCode').textContent = o.id + ' · ' + o.customerId;
    $('ordDetailCustomer').textContent = o.customer;
    $('ordDetailAddress').textContent = (o.address || 'Address not provided') + (o.phone ? ' · ' + o.phone : '');
    $('ordDetailDate').textContent = o.timestamp || dateText(o.date);
    $('ordDetailDueDate').textContent = dateText(o.dueDate || o.date);
    $('ordDetailProduct').textContent = itemSummary(o);
    $('ordDetailItemPrice').textContent = money(o.total);
    $('ordDetailTotal').textContent = money(o.total);
    $('ordDetailProduction').textContent = o.status;
    $('ordDetailProduction').className = 'badge ' + badgeClass(o.status);
    $('ordDetailPayment').textContent = o.payment;
    $('ordDetailPayment').className = 'badge ' + badgeClass(o.payment);
    $('ordDetailBom').hidden = true;
    $('ordViewBom').textContent = 'View BOM';
    $('ordDetailDelivery').textContent = deliveryLabel(o.delivery);
    $('ordDetailDelivery').className = 'badge ' + badgeClass(o.delivery);
    $('ordShip').hidden = Boolean(o.archived) || o.status === 'Cancelled' || !['Not Scheduled','For Delivery'].includes(o.delivery);
    $('ordShip').disabled = busy || !canShip(o);
    $('ordShip').title = canShip(o) ? '' : 'Complete production before shipping';
    $('ordCancel').disabled = Boolean(o.archived) || o.status === 'Cancelled' ||
      o.delivery === 'In Transit' || o.delivery === 'Delivered' || o.delivery === 'Returned' || Number(o.paid) > 0;
    $('ordArchive').hidden = true;
    $('ordArchive').disabled = true;
    var actionText = statusAction(o);
    advanceButton.hidden = !actionText || Boolean(o.archived);
    advanceButton.style.display = advanceButton.hidden ? 'none' : '';
    $('ordCancel').hidden = Boolean(o.archived) || o.status === 'Cancelled';
    $('ordCancel').style.display = $('ordCancel').hidden ? 'none' : '';
    advanceButton.disabled = busy || !actionText || Boolean(o.archived);
    advanceButton.textContent = actionText || '';
    advanceButton.title = '';
    openModal('ordDetailModal');
  }

  // Status and record actions share the branded confirmation modal.
  function statusAction(o) {
    if (!o || o.archived || o.status === 'Cancelled' || o.delivery === 'Delivered' || o.delivery === 'Returned') return '';
    if (o.status === 'Pending') return 'Start Production';
    if (o.status === 'In Production' || o.status === 'Ready') return 'Mark Completed';
    if (o.status === 'Completed' && o.delivery === 'In Transit') return 'Mark Delivered';
    return '';
  }
  function canShip(o) { return o && !o.archived && o.status === 'Completed' && ['Not Scheduled','For Delivery'].includes(o.delivery); }
  var advanceButton = document.createElement('button');
  advanceButton.type = 'button';
  advanceButton.id = 'ordAdvance';
  advanceButton.className = 'btn btn-primary';
  advanceButton.hidden = true;
  var cancelButton = $('ordCancel');
  cancelButton.parentNode.insertBefore(advanceButton, $('ordShip'));
  function openOrderActionConfirmation(action) {
    if (busy || !detailOrder) return;
    var before = detailOrder;
    var isShip = action === 'Shipped';
    var isDelivery = action === 'Mark Delivered' || isShip;
    var isCancel = action === 'Cancel Order';
    var isArchive = action === 'Archive';
    var isStatusUpdate = !isCancel && !isArchive;
    if (isShip ? !canShip(before) : isStatusUpdate && statusAction(before) !== action) return;

    pendingOrderAction = action;
    $('ordStatusConfirmTitle').textContent = action + '?';
    $('ordStatusConfirmOrder').textContent = before.id;
    $('ordStatusFromLabel').textContent = isDelivery ? 'Current delivery status' : 'Current production status';
    $('ordStatusFrom').textContent = isDelivery ? deliveryLabel(before.delivery) : before.status;
    $('ordStatusToLabel').textContent = isArchive ? 'Order record' :
      (isDelivery ? 'New delivery status' : 'New production status');
    $('ordStatusTo').textContent = isArchive ? 'Archived' :
      (isCancel ? 'Cancelled' : isShip ? 'Shipped' : isDelivery ? 'Delivered' :
        (action === 'Start Production' ? 'In Production' : 'Completed'));
    if (isCancel) {
      $('ordStatusConfirmMessage').textContent = 'Cancel this order? It will automatically move to Archived and remain available for history.';
    } else if (isArchive) {
      $('ordStatusConfirmMessage').textContent = 'Move this order to the Archived tab? It will remain saved for history.';
    } else {
      $('ordStatusConfirmMessage').textContent = 'Confirm this status update for ' + before.id + '?';
    }
    var note = isShip ? 'Production is complete. Shipping does not change inventory or payments.' : isDelivery && Number(before.balance) > 0
      ? 'Outstanding balance: ' + money(before.balance) + '. Record full payment before marking this order delivered.'
      : isDelivery ? 'Full payment is recorded. Marking this order delivered will automatically archive it.'
      : isCancel && before.status === 'Pending' ? 'Pending V2 orders have no stock deduction. Paid orders cannot be cancelled yet.'
      : isCancel ? 'Materials already used in production will remain deducted. Paid orders cannot be cancelled.'
      : '';
    $('ordStatusConfirmNote').textContent = note;
    $('ordStatusConfirmNote').hidden = !note;
    $('ordStatusConfirmAccept').textContent = action;
    openModal('ordStatusConfirmModal');
    $('ordStatusConfirmCancel').focus();
  }
  $('ordShip').addEventListener('click', function () { if (canShip(detailOrder)) openOrderActionConfirmation('Shipped'); });
  advanceButton.addEventListener('click', function () {
    if (!detailOrder) return;
    openOrderActionConfirmation(statusAction(detailOrder));
  });
  $('ordStatusConfirmAccept').addEventListener('click', async function () {
    if (busy || !detailOrder || !pendingOrderAction) return;
    var before = detailOrder;
    var action = pendingOrderAction;
    if (action === 'Cancel Order' &&
        (before.archived || !['Pending','In Production','Ready','Completed'].includes(before.status) ||
         before.delivery === 'In Transit' || before.delivery === 'Delivered' || before.delivery === 'Returned' || Number(before.paid) > 0)) {
      notify('Only unpaid, undelivered orders can be cancelled.');
      return;
    }
    if (action === 'Archive' && before.archived) {
      notify('This order is already archived.');
      return;
    }
    if (action === 'Shipped' && !canShip(before)) { notify('Complete production before shipping.'); return; }
    if (['Start Production','Mark Completed','Mark Delivered'].indexOf(action) !== -1 &&
        statusAction(before) !== action) {
      notify('The order status changed. Refresh and retry.');
      return;
    }

    var payload = {orderId:before.rawId};
    if (action === 'Cancel Order') payload.action = 'cancel_order';
    else if (action === 'Archive') payload.action = 'archive_order';
    else {
      payload.action = 'advance_order';
      payload.expectedProduction = before.status;
      payload.expectedDelivery = before.delivery;
      if (action === 'Shipped') payload.transition = 'ship';
    }
    busy = true;
    advanceButton.disabled = true;
    $('ordCancel').disabled = true;
    $('ordArchive').disabled = true;
    $('ordStatusConfirmAccept').disabled = true;
    try {
      var response = await submitAction(payload);
      closeModal('ordStatusConfirmModal');
      closeModal('ordDetailModal');
      detailOrder = null;
      notify(response.message);
      if (response.archived || action === 'Archive') {
        activeGroup = 'archived';
        $('ordSearch').value = '';
        $('ordFilter').value = 'all';
      } else if (action === 'Cancel Order') {
        activeGroup = 'archived';
        $('ordSearch').value = '';
        $('ordFilter').value = 'all';
      }
      await loadData();
      if (action !== 'Cancel Order' && action !== 'Archive') {
        activeOrderId = before.id;
        renderOrders();
      }
    } catch (error) {
      notify(error.message);
      // Do not trust stale state after a failed transition.
      closeModal('ordStatusConfirmModal');
      closeModal('ordDetailModal');
      detailOrder = null;
      try { await loadData(); } catch (reloadError) { notify(reloadError.message); }
    } finally {
      busy = false;
      pendingOrderAction = '';
      advanceButton.disabled = false;
      $('ordCancel').disabled = false;
      $('ordArchive').disabled = false;
      $('ordStatusConfirmAccept').disabled = false;
    }
  });

  // The BOM, labor, packaging and DP policy are owned by Products: read-only here.
  $('ordMaterialAdd').disabled = true;
  ['ordLabor','ordPackaging','ordDpPolicy'].forEach(function (id) { $(id).readOnly = true; });
  $('ordNew').disabled = true;
  $('ordCancel').disabled = true;
  $('ordArchive').disabled = true;
  var submitButton = $('ordCreateForm').querySelector('button[type="submit"]');
  if (submitButton) submitButton.disabled = true;

  $('ordNew').addEventListener('click', async function () {
    if (!ready || busy || !products.length) return;
    $('ordNew').disabled = true;
    try { await loadData(); }
    catch (error) {
      notify('Could not refresh product recipes: ' + error.message);
      $('ordNew').disabled = false;
      return;
    }
    if (!products.length) {
      notify('There are no active products available for a new order.');
      return;
    }
    resetNewForm();
    openModal('ordCreateModal');
  });
  $('ordProduct').addEventListener('change', syncProduct);
  $('ordQty').addEventListener('input', renderBom);
  $('ordSearch').addEventListener('input', renderOrders);
  $('ordFilter').addEventListener('change', renderOrders);
  document.querySelectorAll('.orders-tab').forEach(function (tab) {
    tab.addEventListener('click', function () { activeGroup = tab.dataset.group; renderOrders(); });
  });
  document.querySelectorAll('[data-focus]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = $(button.dataset.focus);
      if (!field) return;
      field.focus();
      if (field.tagName === 'SELECT' && typeof field.showPicker === 'function') {
        try { field.showPicker(); } catch (e) {}
      }
    });
  });
  $('ordCreateForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (busy || !ready) return;
    var p = product();
    var qty = orderQty();
    var date = dueDateISO($('ordDueDate').value);
    if (!p || !Number.isInteger(qty) || qty < 1 || qty > 1000 || !date ||
        !$('ordCustomer').value.trim() || !$('ordAddress').value.trim() || !$('ordPhone').value.trim()) {
      warn('Complete all required fields. Use a valid delivery date and a quantity from 1 to 1,000.'); return;
    }
    var payload = {
      action:'create_order', customer:$('ordCustomer').value.trim(),
      address:$('ordAddress').value.trim(), phone:$('ordPhone').value.trim(), productId:p.id, quantity:qty,
      dueDate:date, notes:$('ordNotes').value.trim(), requestKey:requestKey
    };
    busy = true; if (submitButton) submitButton.disabled = true;
    warn('');
    try {
      var result = await submitAction(payload);
      closeModal('ordCreateModal');
      notify(result.message);
      requestKey = '';
      activeGroup = 'all';
      await loadData();
      activeOrderId = result.orderId;
      renderOrders();
    } catch (error) { warn(error.message); }
    finally { busy = false; if (submitButton) submitButton.disabled = false; }
  });
  function clickOrder(e) {
    var target = e.target.closest('[data-view]');
    if (target) showDetails(target.dataset.view);
  }
  $('ordRows').addEventListener('click', async function(e) {
    var button = e.target.closest('[data-unarchive]');
    if (!button) { clickOrder(e); return; }
    if (busy || !ready) return;
    var order = orders.find(function(o) { return o.id === button.dataset.unarchive && archived(o); });
    if (!order || !window.confirm('Remove ' + order.id + ' from archive? Its existing status and history will be preserved.')) return;
    busy = true; button.disabled = true;
    try {
      var response = await submitAction({action:'unarchive_order', orderId:order.rawId});
      notify(response.message);
      await loadData();
    } catch(error) { notify(error.message || 'Could not remove order from archive.'); }
    finally { busy = false; renderOrders(); }
  });
  $('ordCards').addEventListener('click', clickOrder);
  $('ordCards').addEventListener('keydown', function (e) {
    var card = e.target.closest('[data-view]');
    if (card && (e.key === 'Enter' || e.key === ' ')) {
      e.preventDefault(); showDetails(card.dataset.view);
    }
  });
  $('ordViewBom').addEventListener('click', function () {
    if (!detailOrder) return;
    var target = $('ordDetailBom');
    if (target.hidden) {
      target.innerHTML = '<ul>' + (detailOrder.materials || []).map(function (m) {
        return '<li><span>' + esc(m.name) + '</span><strong>' + esc(m.qty) + ' ' + esc(m.unit) + '</strong></li>';
      }).join('') + '</ul>';
      if (!detailOrder.materials.length) target.textContent = 'No materials recorded for this order.';
      target.hidden = false;
      $('ordViewBom').textContent = 'Hide BOM';
    } else { target.hidden = true; $('ordViewBom').textContent = 'View BOM'; }
  });
  $('ordCancel').addEventListener('click', async function () {
    if (!detailOrder || busy || detailOrder.archived ||
        !['Pending','In Production','Ready','Completed'].includes(detailOrder.status) ||
        detailOrder.delivery === 'In Transit' || detailOrder.delivery === 'Delivered' || detailOrder.delivery === 'Returned' ||
        Number(detailOrder.paid) > 0) return;
    openOrderActionConfirmation('Cancel Order');
  });
  $('ordArchive').addEventListener('click', async function () {
    if (!detailOrder || busy || detailOrder.archived) return;
    openOrderActionConfirmation('Archive');
  });
  if ($('ordExport')) $('ordExport').addEventListener('click', function () {
    var rows = [['Order ID','Date','Customer','Product','Total PHP','Production','Delivery','Payment']].concat(
      orders.map(function (o) { return [o.id,o.date,o.customer,itemSummary(o),o.total,o.status,o.delivery,o.payment]; }));
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(function (value) { return '"' + String(value).replace(/"/g,'""') + '"'; }).join(',');
    }).join('\r\n');
    var blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-orders-mysql.csv'; document.body.appendChild(link); link.click(); link.remove();
    URL.revokeObjectURL(url);
  });

  loadData().catch(function (e) { errorNotice('Orders could not load: ' + e.message); });
})();
