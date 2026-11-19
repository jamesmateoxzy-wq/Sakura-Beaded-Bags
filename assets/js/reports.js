/* Sakura Beaded Bags - Financial Reports */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'reports') return;
  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var statuses = ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'];
  var seededPayments = [
    {id:'SLP-001', orderId:'ORD-001', amount:1200},
    {id:'SLP-002', orderId:'ORD-002', amount:850},
    {id:'SLP-003', orderId:'ORD-003', amount:1500},
    {id:'SLP-004', orderId:'ORD-004', amount:600},
    {id:'SLP-005', orderId:'ORD-005', amount:200}
  ];
  function loadState() {
    var state = {materials:[], orders:[]};
    if (!demo) return state;
    var sampleState = {
      materials:[
        {id:'MAT-001',name:'Pink Beads',unit:'pcs',cost:2,stock:10,min:50},
        {id:'MAT-002',name:'Nylon Thread',unit:'roll',cost:40,stock:3,min:5},
        {id:'MAT-003',name:'Bag Handles',unit:'pcs',cost:55,stock:5,min:10},
        {id:'MAT-004',name:'Rose Crystal Beads',unit:'pcs',cost:2,stock:600,min:100},
        {id:'MAT-005',name:'Ivory Pearl Beads',unit:'pcs',cost:3,stock:450,min:100},
        {id:'MAT-006',name:'Clasps',unit:'pcs',cost:30,stock:4,min:10},
        {id:'MAT-007',name:'Pink Ribbon',unit:'roll',cost:25,stock:2,min:5}
      ],
      orders:[
        {id:'ORD-001',customer:'Maria Santos',product:'Pearl Heart Bag',qty:1,unitPrice:1200,total:1200,date:'2026-10-28',status:'Pending'},
        {id:'ORD-002',customer:'Ana Reyes',product:'Sakura Mini Pouch',qty:1,unitPrice:850,total:850,date:'2026-10-28',status:'In Progress'},
        {id:'ORD-003',customer:'Camille Cruz',product:'Classic Tote',qty:1,unitPrice:1500,total:1500,date:'2026-10-26',status:'Completed'},
        {id:'ORD-004',customer:'Isabel Garcia',product:'Petal Handbag',qty:1,unitPrice:1200,total:1200,date:'2026-10-25',status:'Pending'},
        {id:'ORD-005',customer:'Sofia Ramos',product:'Beaded Phone Bag',qty:1,unitPrice:800,total:800,date:'2026-10-25',status:'In Progress'},
        {id:'ORD-006',customer:'Louise Santos',product:'Daisy Set',qty:1,unitPrice:1800,total:1800,date:'2026-10-23',status:'Delivered'},
        {id:'ORD-007',customer:'Michelle Cruz',product:'Pink Ribbon Bag',qty:1,unitPrice:975,total:975,date:'2026-10-22',status:'Pending'}
      ]
    };
    try {
      var stored = JSON.parse(sessionStorage.getItem(key));
      if (stored && Array.isArray(stored.materials)) state.materials = stored.materials;
      if (stored && Array.isArray(stored.orders)) state.orders = stored.orders;
      if (!Array.isArray(state.materials) || !state.materials.length) state.materials = sampleState.materials;
      if (!Array.isArray(state.orders) || !state.orders.length) state.orders = sampleState.orders;
    } catch (_) {}
    if (!state.materials.length) state.materials = sampleState.materials;
    if (!state.orders.length) state.orders = sampleState.orders;
    return state;
  }
  var state = loadState();
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function reportData() {
    var month = $('repPeriod').value;
    var all = state.orders.filter(function (order) { return month === 'all' || order.date.slice(0,7) === month; });
    var valid = all.filter(function (order) { return order.status !== 'Cancelled'; });
    var total = valid.reduce(function (sum, order) { return sum + Math.round(order.total * 100); }, 0);
    var orderTotals = {};
    valid.forEach(function (order) { orderTotals[order.id] = Math.round(order.total * 100); });
    var collected = {};
    var payments = seededPayments.slice();
    try {
      var stored = JSON.parse(sessionStorage.getItem('sakura-demo-payments-v1') || '[]');
      if (Array.isArray(stored)) payments = payments.concat(stored);
    } catch (_) {}
    payments.forEach(function (payment) {
      if (orderTotals[payment.orderId] == null) return;
      collected[payment.orderId] = (collected[payment.orderId] || 0) + Math.round(Number(payment.amount || 0) * 100);
    });
    var received = Object.keys(collected).reduce(function (sum, id) {
      return sum + Math.min(orderTotals[id], collected[id]);
    }, 0);
    return {all:all, valid:valid, total:total, received:received, due:Math.max(0,total-received), month:month};
  }
  function updatePeriods() {
    var oldValue = $('repPeriod').value || 'all';
    var months = state.orders.map(function (order) { return order.date.slice(0,7); })
      .filter(function (month, index, list) { return list.indexOf(month) === index; }).sort().reverse();
    $('repPeriod').innerHTML = '<option value="all">All dates</option>' + months.map(function (month) {
      var text = new Date(month + '-01T00:00:00').toLocaleDateString('en-PH', {month:'long',year:'numeric'});
      return '<option value="' + esc(month) + '">' + esc(text) + '</option>';
    }).join('');
    $('repPeriod').value = months.indexOf(oldValue) !== -1 ? oldValue : 'all';
  }
  function render() {
    var data = reportData();
    $('repSales').textContent = money(data.total / 100);
    $('repReceived').textContent = money(data.received / 100);
    $('repDue').textContent = money(data.due / 100);
    $('repInventory').textContent = money(state.materials.reduce(function (sum, material) {
      return sum + Math.round(material.stock * material.cost * 100);
    }, 0) / 100);
    $('repOrdersNote').textContent = data.valid.length + ' active order' + (data.valid.length === 1 ? '' : 's');

    var months = {};
    data.valid.forEach(function (order) {
      var month = order.date.slice(0,7);
      months[month] = (months[month] || 0) + Math.round(order.total * 100);
    });
    var monthKeys = Object.keys(months).sort();
    var max = Math.max.apply(null, [1].concat(monthKeys.map(function (month) { return months[month]; })));
    $('repBars').innerHTML = monthKeys.map(function (month) {
      var label = new Date(month + '-01T00:00:00').toLocaleDateString('en-PH', {month:'short',year:'2-digit'});
      var percent = Math.max(2, Math.round(months[month] / max * 100));
      return '<div class="ops-bar-group"><strong class="ops-bar-value">' + money(months[month]/100) +
        '</strong><div class="ops-bar-track"><div class="ops-bar-fill" style="height:' + percent +
        '%"></div></div><span class="ops-bar-label">' + esc(label) + '</span></div>';
    }).join('');
    $('repChartEmpty').hidden = monthKeys.length > 0;

    var statusCounts = statuses.map(function (status) {
      return {label:status, count:data.all.filter(function (order) { return order.status === status; }).length};
    });
    $('repStatuses').innerHTML = statusCounts.map(function (status) {
      var width = data.all.length ? Math.round(status.count / data.all.length * 100) : 0;
      return '<div class="ops-breakdown-row"><span>' + esc(status.label) +
        '</span><div class="ops-breakdown-track"><div class="ops-breakdown-fill" style="width:' +
        width + '%"></div></div><span class="ops-breakdown-count">' + status.count + '</span></div>';
    }).join('');

    var products = {};
    data.valid.forEach(function (order) {
      var key = order.product.trim().toLowerCase();
      if (!products[key]) products[key] = {name:order.product, units:0, amount:0};
      products[key].units += order.qty;
      products[key].amount += Math.round(order.total * 100);
    });
    var ranked = Object.keys(products).map(function (key) { return products[key]; })
      .sort(function (a,b) { return b.amount-a.amount; }).slice(0,8);
    $('repTop').innerHTML = ranked.map(function (product) {
      return '<tr><td>' + esc(product.name) + '</td><td>' + product.units + '</td><td>' +
        money(product.amount/100) + '</td></tr>';
    }).join('');
    $('repTopEmpty').hidden = ranked.length > 0;

    var low = state.materials.filter(function (material) {
      return material.stock <= material.min;
    });
    $('repLow').innerHTML = low.map(function (material) {
      return '<tr><td>' + esc(material.name) + '</td><td>' + material.stock + ' ' + esc(material.unit) +
        '</td><td>' + material.min + ' ' + esc(material.unit) + '</td></tr>';
    }).join('');
    $('repLowEmpty').hidden = low.length > 0;
  }
  $('repPeriod').addEventListener('change', render);
  $('repExport').addEventListener('click', function () {
    var data = reportData();
    var rows = [['SAKURA BEADED BAGS - DEMO REPORT'],['Period',data.month === 'all' ? 'All dates' : data.month],
      ['Metric','Amount PHP'],['Order Value',data.total/100],['Payments Received',data.received/100],
      ['Unpaid Balance',data.due/100],
      ['Inventory Value',state.materials.reduce(function (sum,m) { return sum + m.stock*m.cost; },0)],
      [],['Order ID','Date','Customer','Product','Qty','Order Value PHP','Status']];
    data.all.forEach(function (order) {
      rows.push([order.id,order.date,order.customer,order.product,order.qty,order.total,order.status]);
    });
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(function (value) { return '"' + String(value).replace(/"/g,'""') + '"'; }).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-financial-report-DEMO.csv'; document.body.appendChild(link); link.click(); link.remove();
  });
  $('repPrint').addEventListener('click', function () { window.print(); });
  updatePeriods();
  render();
})();