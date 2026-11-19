/* Sakura Beaded Bags - Order Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'orders') return;
  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var statuses = ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'];
  var samples = [
    { id:'ORD-001', customer:'Maria Santos', product:'Pearl Heart Bag', qty:1, unitPrice:1200, total:1200, date:'2026-10-28', status:'Pending' },
    { id:'ORD-002', customer:'Ana Reyes', product:'Sakura Mini Pouch', qty:1, unitPrice:850, total:850, date:'2026-10-28', status:'In Progress' },
    { id:'ORD-003', customer:'Camille Cruz', product:'Classic Tote', qty:1, unitPrice:1500, total:1500, date:'2026-10-26', status:'Completed' },
    { id:'ORD-004', customer:'Isabel Garcia', product:'Petal Handbag', qty:1, unitPrice:1200, total:1200, date:'2026-10-25', status:'Pending' },
    { id:'ORD-005', customer:'Sofia Ramos', product:'Beaded Phone Bag', qty:1, unitPrice:800, total:800, date:'2026-10-25', status:'In Progress' },
    { id:'ORD-006', customer:'Louise Santos', product:'Daisy Set', qty:1, unitPrice:1800, total:1800, date:'2026-10-23', status:'Delivered' },
    { id:'ORD-007', customer:'Michelle Cruz', product:'Pink Ribbon Bag', qty:1, unitPrice:975, total:975, date:'2026-10-22', status:'Pending' }
  ];

  function load() {
    if (!demo) return [];
    try {
      var stored = JSON.parse(sessionStorage.getItem(key));
      if (stored && Array.isArray(stored.orders)) return stored.orders;
    } catch (_) {}
    return JSON.parse(JSON.stringify(samples));
  }
  function save() {
    if (!demo) return;
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || '{}');
      stored.orders = orders;
      sessionStorage.setItem(key, JSON.stringify(stored));
    } catch (_) {}
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function badge(status) {
    var cls = status === 'Completed' || status === 'Delivered' ? 'badge-green' :
      status === 'Pending' ? 'badge-amber' : status === 'In Progress' ? 'badge-blue' : 'badge-pink';
    return '<span class="badge ' + cls + '">' + esc(status) + '</span>';
  }
  function dateText(value) {
    var date = new Date(value + 'T00:00:00');
    return isNaN(date.getTime()) ? value : date.toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'});
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
  var orders = load();

  function render() {
    var query = $('ordSearch').value.trim().toLowerCase();
    var filter = $('ordFilter').value;
    var shown = orders.filter(function (order) {
      return (filter === 'all' || order.status === filter) &&
        (!query || (order.id + ' ' + order.customer + ' ' + order.product).toLowerCase().includes(query));
    }).sort(function (a, b) { return b.date.localeCompare(a.date) || b.id.localeCompare(a.id); });

    $('ordTotal').textContent = orders.length;
    $('ordPending').textContent = orders.filter(function (o) { return o.status === 'Pending'; }).length;
    $('ordProgress').textContent = orders.filter(function (o) { return o.status === 'In Progress'; }).length;
    $('ordDone').textContent = orders.filter(function (o) { return o.status === 'Completed' || o.status === 'Delivered'; }).length;
    $('ordRows').innerHTML = shown.map(function (order) {
      return '<tr><td class="strong">' + esc(order.id) + '</td><td>' + dateText(order.date) +
        '</td><td>' + esc(order.customer) + '</td><td>' + esc(order.product) + '</td><td>' + order.qty +
        '</td><td>' + money(order.total) + '</td><td>' + badge(order.status) +
        '</td><td><button type="button" class="btn btn-outline btn-sm" data-view="' + esc(order.id) + '">View Details</button></td></tr>';
    }).join('');
    $('ordEmpty').hidden = shown.length > 0;
    $('ordFoot').textContent = 'Showing ' + shown.length + ' of ' + orders.length + ' orders';
  }

  $('ordSearch').addEventListener('input', render);
  $('ordFilter').addEventListener('change', render);
  $('ordExport').addEventListener('click', function () {
    var rows = [['Order ID','Date','Customer','Product','Quantity','Unit Price PHP','Total PHP','Status']]
      .concat(orders.map(function (o) { return [o.id,o.date,o.customer,o.product,o.qty,o.unitPrice,o.total,o.status]; }));
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(function (value) { return '"' + String(value).replace(/"/g,'""') + '"'; }).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-orders-demo.csv'; document.body.appendChild(link); link.click(); link.remove();
  });
  $('ordNew').addEventListener('click', function () {
    if (!demo) return;
    $('ordCreateForm').reset();
    var today = new Date();
    $('ordDate').value = [today.getFullYear(), String(today.getMonth()+1).padStart(2,'0'), String(today.getDate()).padStart(2,'0')].join('-');
    openModal('ordCreateModal');
  });
  $('ordCreateForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var customer = $('ordCustomer').value.trim();
    var product = $('ordProduct').value.trim();
    var qty = Number($('ordQty').value);
    var price = Number($('ordPrice').value);
    if (!demo || !customer || !product || !Number.isInteger(qty) || qty < 1 || price < 0.01) return;
    var max = orders.reduce(function (value, order) {
      return Math.max(value, Number(order.id.split('-')[1]) || 0);
    }, 0);
    orders.push({
      id:'ORD-' + String(max + 1).padStart(3,'0'), customer:customer, product:product, qty:qty,
      unitPrice:price, total:Math.round(price * qty * 100) / 100,
      date:$('ordDate').value, status:$('ordInitialStatus').value
    });
    save(); closeModal('ordCreateModal'); render(); notify('Demo order created.');
  });
  $('ordRows').addEventListener('click', function (event) {
    var button = event.target.closest('[data-view]');
    if (!button) return;
    var order = orders.find(function (item) { return item.id === button.dataset.view; });
    if (!order) return;
    $('ordDetailId').value = order.id;
    $('ordDetailTitle').textContent = 'Order Details · ' + order.id;
    $('ordDetailData').innerHTML = '<dt>Customer</dt><dd>' + esc(order.customer) +
      '</dd><dt>Product</dt><dd>' + esc(order.product) + '</dd><dt>Quantity</dt><dd>' + order.qty +
      '</dd><dt>Unit price</dt><dd>' + money(order.unitPrice) + '</dd><dt>Order total</dt><dd>' +
      money(order.total) + '</dd><dt>Order date</dt><dd>' + dateText(order.date) + '</dd>';
    $('ordDetailStatus').value = order.status;
    openModal('ordDetailModal');
  });
  $('ordDetailForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var order = orders.find(function (item) { return item.id === $('ordDetailId').value; });
    var status = $('ordDetailStatus').value;
    if (!demo || !order || statuses.indexOf(status) === -1) return;
    order.status = status;
    save(); closeModal('ordDetailModal'); render(); notify('Demo order status updated.');
  });
  render();
})();