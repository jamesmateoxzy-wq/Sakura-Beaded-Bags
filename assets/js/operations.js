/* Sakura Beaded Bags — Inventory, Orders and Financial Reports.
 * DEMO ONLY. No writes to MySQL or payment provider. See docs/OPERATIONS_HANDOFF.md.
 */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root) return;
  var moduleName = root.getAttribute('data-module');
  var demo = root.getAttribute('data-demo') === '1';
  var key = 'sakura-operations-v1';
  var $ = function (id) { return document.getElementById(id); };
  var allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'];
  var samples = { materials:[], orders:[] };

  function cleanState(raw) {
    if (!raw || !Array.isArray(raw.materials) || !Array.isArray(raw.orders) ||
        raw.materials.length > 500 || raw.orders.length > 500) return null;
    var materials = raw.materials.filter(function (m) {
      return m && /^MAT-\d{3,}$/.test(m.id) && typeof m.name === 'string' && m.name.length <= 80 &&
        ['pcs','roll','pack','meter','set'].indexOf(m.unit) !== -1 &&
        isMoney(m.cost, true) && isWhole(m.stock, true) && isWhole(m.min, true);
    });
    var orders = raw.orders.filter(function (o) {
      return o && /^ORD-\d{3,}$/.test(o.id) &&
        typeof o.customer === 'string' && o.customer.length <= 90 &&
        typeof o.product === 'string' && o.product.length <= 90 &&
        isWhole(o.qty, false) && isMoney(o.unitPrice, false) &&
        isMoney(o.total, true) && validDate(o.date) &&
        allowedStatuses.indexOf(o.status) !== -1;
    });
    return { materials:materials, orders:orders };
  }
  function isWhole(n, allowZero) {
    return typeof n === 'number' && Number.isSafeInteger(n) && n >= (allowZero ? 0 : 1) && n <= 1000000;
  }
  function isMoney(n, allowZero) {
    return typeof n === 'number' && Number.isFinite(n) && n >= (allowZero ? 0 : .01) &&
      n <= 1000000000 && Math.abs(n * 100 - Math.round(n * 100)) < .000001;
  }
  function validDate(s) {
    if (typeof s !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
    var d = new Date(s + 'T00:00:00');
    return !Number.isNaN(d.getTime()) && [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'),
      String(d.getDate()).padStart(2,'0')].join('-') === s;
  }
  function load() {
    if (!demo) return { materials:[], orders:[] };
    try {
      var raw = JSON.parse(sessionStorage.getItem(key) || 'null');
      var restored = cleanState(raw);
      if (restored) return restored;
    } catch (_) {}
    return JSON.parse(JSON.stringify(samples));
  }
  var state = load();
  function save() {
    if (!demo) return;
    try { sessionStorage.setItem(key, JSON.stringify(state)); }
    catch (_) { notify('Browser storage unavailable. Changes may reset on navigation.'); }
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
  }
  function money(n) {
    return '₱' + (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
  }
  function cents(n) { return Math.round(Number(n || 0) * 100); }
  function notify(s) { if (window.Sakura && Sakura.toast) Sakura.toast(s); }
  function open(id) { if (window.Sakura) Sakura.openModal(id); }
  function close(id) { if (window.Sakura) Sakura.closeModal(id); }
  function error(id, message) { var e = $(id); e.textContent = message || ''; e.hidden = !message; }
  function dateText(s) {
    if (!validDate(s)) return s;
    return new Date(s + 'T00:00:00').toLocaleDateString('en-PH',
      { month:'short', day:'numeric', year:'numeric' });
  }
  function today() {
    var d = new Date();
    return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
  }
  function nextId(rows, prefix) {
    var max = rows.reduce(function (n, row) {
      var x = Number(String(row.id).split('-')[1]);
      return Math.max(n, Number.isFinite(x) ? x : 0);
    }, 0);
    return prefix + '-' + String(max + 1).padStart(3, '0');
  }
  function csvCell(value) { return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"'; }
  function exportCsv(name, rows) {
    var csv = '\ufeff' + rows.map(function (r) { return r.map(csvCell).join(','); }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], { type:'text/csv;charset=utf-8;' }));
    var a = document.createElement('a');
    a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }
  function stockStatus(m) { return m.stock === 0 ? 'Out of Stock' : m.stock <= m.min ? 'Low Stock' : 'In Stock'; }
  function badge(status) {
    var cls = ['In Stock','Completed','Delivered'].indexOf(status) !== -1 ? 'badge-green' :
      ['Pending','Low Stock'].indexOf(status) !== -1 ? 'badge-amber' :
      status === 'In Progress' ? 'badge-blue' : 'badge-pink';
    return '<span class="badge ' + cls + '">' + esc(status) + '</span>';
  }

  function setupInventory() {
    function render() {
      var list = state.materials;
      $('invTotal').textContent = list.length;
      $('invLow').textContent = list.filter(function (m) { return m.stock <= m.min; }).length;
      $('invOut').textContent = list.filter(function (m) { return m.stock === 0; }).length;
      $('invValue').textContent = money(list.reduce(function (sum, m) { return sum + cents(m.cost) * m.stock; }, 0) / 100);
      var q = $('invSearch').value.trim().toLowerCase();
      var filter = $('invFilter').value;
      var shown = list.filter(function (m) {
        var status = stockStatus(m);
        return (filter === 'all' || (filter === 'low' && m.stock <= m.min) ||
          (filter === 'out' && m.stock === 0) || (filter === 'good' && status === 'In Stock')) &&
          (!q || (m.id + ' ' + m.name).toLowerCase().includes(q));
      });
      $('invRows').innerHTML = shown.map(function (m) {
        return '<tr><td class="strong">' + esc(m.id) + '</td><td>' + esc(m.name) + '</td><td>' +
          esc(m.unit) + '</td><td>' + money(m.cost) + '</td><td>' + m.stock +
          '</td><td>' + m.min + '</td><td>' + money(m.stock * m.cost) + '</td><td>' +
          badge(stockStatus(m)) + '</td><td><button type="button" class="btn btn-outline btn-sm" data-adjust="' +
          esc(m.id) + '"' + (demo ? '' : ' disabled') + '>Adjust Stock</button></td></tr>';
      }).join('');
      $('invEmpty').hidden = shown.length > 0;
      $('invFoot').textContent = 'Showing ' + shown.length + ' of ' + list.length + ' materials';
    }
    $('invSearch').addEventListener('input', render);
    $('invFilter').addEventListener('change', render);
    $('invExport').addEventListener('click', function () {
      exportCsv('sakura-inventory-demo.csv', [['Material ID','Material','Unit','Unit Cost PHP','Stock','Reorder At','Status']]
        .concat(state.materials.map(function (m) { return [m.id,m.name,m.unit,m.cost,m.stock,m.min,stockStatus(m)]; })));
    });
    $('invNew').addEventListener('click', function () {
      if (!demo) return;
      $('invCreateForm').reset(); error('invCreateError',''); open('invCreateModal');
    });
    $('invCreateForm').addEventListener('submit', function (event) {
      event.preventDefault();
      if (!demo) return;
      var name = $('invName').value.trim();
      var unit = $('invUnit').value;
      var cost = Number($('invCost').value);
      var qty = Number($('invQty').value);
      var min = Number($('invMin').value);
      if (!name || name.length > 80 || state.materials.some(function (m) { return m.name.toLowerCase() === name.toLowerCase(); })) {
        error('invCreateError','Enter a unique material name.'); return;
      }
      if (!isMoney(cost,true) || cost > 1000000 || !isWhole(qty,true) || !isWhole(min,true)) {
        error('invCreateError','Enter a valid cost (2 decimals max), stock quantity and reorder point.'); return;
      }
      state.materials.push({ id:nextId(state.materials,'MAT'), name:name, unit:unit, cost:cost,
        stock:qty, min:min });
      save(); close('invCreateModal'); render(); notify('Demo material added.');
    });
    $('invRows').addEventListener('click', function (event) {
      var btn = event.target.closest('[data-adjust]');
      if (!btn || !demo) return;
      var material = state.materials.find(function (m) { return m.id === btn.getAttribute('data-adjust'); });
      if (!material) return;
      $('invAdjustId').value = material.id;
      $('invAdjustDescription').textContent = material.name + ' · ' + material.stock + ' ' + material.unit + ' on hand';
      $('invAdjustForm').reset(); error('invAdjustError',''); open('invAdjustModal');
    });
    $('invAdjustForm').addEventListener('submit', function (event) {
      event.preventDefault();
      if (!demo) return;
      var material = state.materials.find(function (m) { return m.id === $('invAdjustId').value; });
      var qty = Number($('invAdjustQty').value);
      var delta = $('invDirection').value === 'in' ? qty : -qty;
      if (!material || !isWhole(qty,false) || material.stock + delta < 0 || material.stock + delta > 1000000) {
        error('invAdjustError','Enter a valid quantity. Stock cannot become negative or exceed 1,000,000.'); return;
      }
      material.stock += delta;
      save(); close('invAdjustModal'); render(); notify('Demo stock adjusted.');
    });
    if (demo && new URLSearchParams(window.location.search).get('action') === 'stockin') {
      $('invFilter').value = 'all';
      render();
      var first = $('invRows').querySelector('[data-adjust]');
      if (first) first.click();
    } else render();
  }

  function setupOrders() {
    function render() {
      var list = state.orders;
      $('ordTotal').textContent = list.length;
      $('ordPending').textContent = list.filter(function (o) { return o.status === 'Pending'; }).length;
      $('ordProgress').textContent = list.filter(function (o) { return o.status === 'In Progress'; }).length;
      $('ordDone').textContent = list.filter(function (o) { return o.status === 'Completed' || o.status === 'Delivered'; }).length;
      var q = $('ordSearch').value.trim().toLowerCase();
      var filter = $('ordFilter').value;
      var shown = list.filter(function (o) {
        return (filter === 'all' || o.status === filter) &&
          (!q || (o.id + ' ' + o.customer + ' ' + o.product).toLowerCase().includes(q));
      }).slice().sort(function (a,b) { return b.date.localeCompare(a.date) || b.id.localeCompare(a.id); });
      $('ordRows').innerHTML = shown.map(function (o) {
        return '<tr><td class="strong">' + esc(o.id) + '</td><td>' + esc(dateText(o.date)) +
          '</td><td>' + esc(o.customer) + '</td><td>' + esc(o.product) + '</td><td>' +
          o.qty + '</td><td>' + money(o.total) + '</td><td>' + badge(o.status) +
          '</td><td><button type="button" class="btn btn-outline btn-sm" data-view="' +
          esc(o.id) + '">View Details</button></td></tr>';
      }).join('');
      $('ordEmpty').hidden = shown.length > 0;
      $('ordFoot').textContent = 'Showing ' + shown.length + ' of ' + list.length + ' orders';
    }
    $('ordSearch').addEventListener('input',render);
    $('ordFilter').addEventListener('change',render);
    $('ordExport').addEventListener('click',function () {
      exportCsv('sakura-orders-demo.csv',[['Order ID','Date','Customer','Product','Quantity','Unit Price PHP','Total PHP','Status']]
        .concat(state.orders.map(function (o) { return [o.id,o.date,o.customer,o.product,o.qty,o.unitPrice,o.total,o.status]; })));
    });
    $('ordNew').addEventListener('click',function () {
      if (!demo) return;
      $('ordCreateForm').reset(); $('ordDate').value = today();
      error('ordCreateError',''); open('ordCreateModal');
    });
    $('ordCreateForm').addEventListener('submit',function (event) {
      event.preventDefault();
      if (!demo) return;
      var customer = $('ordCustomer').value.trim(), product = $('ordProduct').value.trim();
      var qty = Number($('ordQty').value), price = Number($('ordPrice').value);
      var date = $('ordDate').value, status = $('ordInitialStatus').value;
      if (!customer || !product || customer.length > 90 || product.length > 90) {
        error('ordCreateError','Enter customer and product names.'); return;
      }
      if (!isWhole(qty,false) || qty > 1000 || !isMoney(price,false) ||
          price > 1000000 || !validDate(date) || date > today() ||
          ['Pending','In Progress'].indexOf(status) === -1) {
        error('ordCreateError','Check the quantity, price (2 decimals max), and order date (not in the future).'); return;
      }
      var total = Math.round(price * qty * 100) / 100;
      state.orders.push({ id:nextId(state.orders,'ORD'), customer:customer, product:product, qty:qty,
        unitPrice:price, total:total, date:date, status:status });
      save(); close('ordCreateModal'); render(); notify('Demo order created.');
    });
    $('ordRows').addEventListener('click',function (event) {
      var btn = event.target.closest('[data-view]');
      if (!btn) return;
      var order = state.orders.find(function (o) { return o.id === btn.getAttribute('data-view'); });
      if (!order) return;
      $('ordDetailId').value = order.id;
      $('ordDetailTitle').textContent = 'Order Details · ' + order.id;
      $('ordDetailData').innerHTML =
        '<dt>Customer</dt><dd>' + esc(order.customer) + '</dd>' +
        '<dt>Product</dt><dd>' + esc(order.product) + '</dd>' +
        '<dt>Quantity</dt><dd>' + order.qty + '</dd>' +
        '<dt>Unit price</dt><dd>' + money(order.unitPrice) + '</dd>' +
        '<dt>Order total</dt><dd>' + money(order.total) + '</dd>' +
        '<dt>Order date</dt><dd>' + esc(dateText(order.date)) + '</dd>';
      $('ordDetailStatus').value = order.status;
      error('ordDetailError',''); open('ordDetailModal');
    });
    $('ordDetailForm').addEventListener('submit',function (event) {
      event.preventDefault();
      if (!demo) return;
      var order = state.orders.find(function (o) { return o.id === $('ordDetailId').value; });
      var status = $('ordDetailStatus').value;
      if (!order || allowedStatuses.indexOf(status) === -1) {
        error('ordDetailError','Select a valid order status.'); return;
      }
      order.status = status;
      save(); close('ordDetailModal'); render(); notify('Demo order status updated.');
    });
    render();
    if (demo && new URLSearchParams(window.location.search).get('action') === 'new') $('ordNew').click();
  }

  // These 5 payments match the existing Payments & Sales page's fictitious seed.
  var seededPayments = [];
  function knownPayments() {
    var result = seededPayments.slice();
    if (!demo) return [];
    try {
      var stored = JSON.parse(sessionStorage.getItem('sakura-demo-payments-v1') || '[]');
      if (!Array.isArray(stored)) return result;
      var seen = {};
      result.forEach(function (p) { seen[p.id] = true; });
      stored.slice(0,200).forEach(function (p) {
        if (!p || typeof p.id !== 'string' || !/^SLP-\d+$/.test(p.id) || seen[p.id] ||
          typeof p.orderId !== 'string' || !isMoney(Number(p.amount),false)) return;
        // Reports can display additional payments only for existing sample order IDs.
        if (!state.orders.some(function (o) { return o.id === p.orderId && o.status !== 'Cancelled'; })) return;
        seen[p.id] = true;
        result.push({id:p.id,orderId:p.orderId,amount:Number(p.amount)});
      });
    } catch (_) {}
    return result;
  }

  function setupReports() {
    var period = $('repPeriod');
    function updatePeriods() {
      var oldValue = period.value || 'all';
      var months = [...new Set(state.orders.map(function (o) { return o.date.slice(0,7); }))].sort().reverse();
      period.innerHTML = '<option value="all">All dates</option>' + months.map(function (ym) {
        var text = new Date(ym + '-01T00:00:00').toLocaleDateString('en-PH', {month:'long',year:'numeric'});
        return '<option value="' + esc(ym) + '">' + esc(text) + '</option>';
      }).join('');
      period.value = months.indexOf(oldValue) !== -1 ? oldValue : 'all';
    }
    function reportData() {
      var month = period.value;
      var all = state.orders.filter(function (o) { return month === 'all' || o.date.slice(0,7) === month; });
      var valid = all.filter(function (o) { return o.status !== 'Cancelled'; });
      var byId = {};
      valid.forEach(function (o) { byId[o.id] = cents(o.total); });
      var total = valid.reduce(function (sum,o) { return sum + cents(o.total); },0);
      // Collection is applied to the cohort of selected orders, regardless of payment date.
      var collected = {};
      knownPayments().forEach(function (p) {
        if (byId[p.orderId] == null) return;
        collected[p.orderId] = (collected[p.orderId] || 0) + cents(p.amount);
      });
      var received = Object.keys(collected).reduce(function (sum,id) {
        return sum + Math.min(byId[id],collected[id]);
      },0);
      return { all:all, valid:valid, total:total, received:received, due:Math.max(0,total-received), month:month };
    }
    function render() {
      var data = reportData();
      $('repSales').textContent = money(data.total/100);
      $('repReceived').textContent = money(data.received/100);
      $('repDue').textContent = money(data.due/100);
      $('repInventory').textContent = money(state.materials.reduce(function (sum,m) { return sum + m.stock*cents(m.cost); },0)/100);
      $('repOrdersNote').textContent = data.valid.length + ' active order' + (data.valid.length===1?'':'s');
      var months = {};
      data.valid.forEach(function (o) {
        var m = o.date.slice(0,7);
        months[m] = (months[m] || 0) + cents(o.total);
      });
      var monthsKeys = Object.keys(months).sort();
      var max = Math.max.apply(null,[1].concat(monthsKeys.map(function (k) { return months[k]; })));
      $('repBars').innerHTML = monthsKeys.map(function (m) {
        var label = new Date(m+'-01T00:00:00').toLocaleDateString('en-PH',{month:'short',year:'2-digit'});
        var percent = Math.max(2,Math.round(months[m]/max*100));
        return '<div class="ops-bar-group"><strong class="ops-bar-value">'+money(months[m]/100)+
          '</strong><div class="ops-bar-track"><div class="ops-bar-fill" style="height:'+percent+
          '%"></div></div><span class="ops-bar-label">'+esc(label)+'</span></div>';
      }).join('');
      $('repChartEmpty').hidden = monthsKeys.length > 0;
      var statuses = allowedStatuses.map(function (status) {
        return { label:status,count:data.all.filter(function(o){return o.status===status;}).length };
      });
      $('repStatuses').innerHTML = statuses.map(function (s) {
        var width = data.all.length ? Math.round(s.count/data.all.length*100) : 0;
        return '<div class="ops-breakdown-row"><span>'+esc(s.label)+'</span><div class="ops-breakdown-track">'+
          '<div class="ops-breakdown-fill" style="width:'+width+'%"></div></div><span class="ops-breakdown-count">'+s.count+'</span></div>';
      }).join('');
      var products = {};
      data.valid.forEach(function (o) {
        var k = o.product.trim().toLowerCase();
        if (!products[k]) products[k] = {name:o.product,units:0,amount:0};
        products[k].units += o.qty;
        products[k].amount += cents(o.total);
      });
      var ranked = Object.keys(products).map(function (k) {return products[k];})
        .sort(function (a,b) {return b.amount-a.amount;}).slice(0,8);
      $('repTop').innerHTML = ranked.map(function (p) {
        return '<tr><td>'+esc(p.name)+'</td><td>'+p.units+'</td><td>'+money(p.amount/100)+'</td></tr>';
      }).join('');
      $('repTopEmpty').hidden = ranked.length > 0;
      var low = state.materials.filter(function (m) {return m.stock<=m.min;});
      $('repLow').innerHTML = low.map(function (m) {
        return '<tr><td>'+esc(m.name)+'</td><td>'+m.stock+' '+esc(m.unit)+'</td><td>'+m.min+' '+esc(m.unit)+'</td></tr>';
      }).join('');
      $('repLowEmpty').hidden = low.length > 0;
    }
    period.addEventListener('change',render);
    $('repExport').addEventListener('click',function () {
      var d = reportData();
      var rows = [
        ['SAKURA BEADED BAGS - DEMO REPORT'],['Period',d.month==='all'?'All dates':d.month],
        ['Metric','Amount PHP'],['Order Value',d.total/100],['Payments Received',d.received/100],
        ['Unpaid Balance',d.due/100],
        ['Inventory Value',state.materials.reduce(function(s,m){return s+m.stock*cents(m.cost);},0)/100],
        [],['Order ID','Date','Customer','Product','Qty','Order Value PHP','Status']
      ];
      d.all.forEach(function (o) {rows.push([o.id,o.date,o.customer,o.product,o.qty,o.total,o.status]);});
      exportCsv('sakura-financial-report-DEMO.csv',rows);
    });
    $('repPrint').addEventListener('click',function () {window.print();});
    updatePeriods(); render();
  }

  if (moduleName === 'inventory') setupInventory();
  if (moduleName === 'orders') setupOrders();
  if (moduleName === 'reports') setupReports();
})();
