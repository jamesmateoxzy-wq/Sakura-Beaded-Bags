/* Sakura Beaded Bags - Financial Reports */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'reports') return;
  var $ = function (id) { return document.getElementById(id); };
  var store = window.SakuraStore;
  var state = store.read();
  var expenseCategories = ['Materials', 'Labor', 'Packaging'];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function money2(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function monthText(month, options) {
    var date = new Date(month + '-01T00:00:00');
    return date.toLocaleDateString('en-US', options);
  }
  function monthRange(month) {
    if (!month || month === 'all') return 'All dates';
    var date = new Date(month + '-01T00:00:00');
    var end = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    return monthText(month, {month:'short'}) + ' 1 \u2013 ' + end + ', ' + date.getFullYear();
  }
  function updatePeriods() {
    var oldValue = $('repPeriod').value;
    var months = state.orders.map(function (order) { return order.date.slice(0, 7); })
      .filter(function (month, index, list) { return list.indexOf(month) === index; })
      .sort().reverse();
    $('repPeriod').innerHTML = months.map(function (month) {
      return '<option value="' + esc(month) + '">' + esc(monthRange(month)) + '</option>';
    }).join('');
    if (months.length > 1 || !months.length) {
      $('repPeriod').insertAdjacentHTML('beforeend', '<option value="all">All dates</option>');
    }
    $('repPeriod').value = months.indexOf(oldValue) !== -1 || oldValue === 'all' ? oldValue : (months[0] || 'all');
  }
  function expenseDetails(orders) {
    const totals = [0, 0, 0];
    for (const order of orders) {
      totals[0] += (order.materials || []).reduce((sum, item) => sum + store.cents(item.qty * item.cost), 0) * order.qty;
      totals[1] += store.cents(order.labor || 0) * order.qty;
      totals[2] += store.cents(order.packaging || 0) * order.qty;
    }
    return expenseCategories.map((name, index) => ({name, amount:totals[index] / 100}));
  }

  function reportData() {
    const month = $('repPeriod').value || 'all';
    const all = state.orders.filter(order => month === 'all' || order.date.slice(0, 7) === month);
    const valid = all.filter(order => order.status !== 'Cancelled');
    const revenue = valid.reduce((sum, order) => sum + store.cents(order.total), 0);
    const costs = expenseDetails(valid);
    const expenses = costs.reduce((sum, entry) => sum + store.cents(entry.amount), 0);
    const received = valid.reduce((sum, order) => sum + store.paidCents(state.payments, order.id), 0);
    return {all, valid, month, revenue:revenue / 100, expenses:expenses / 100,
      profit:(revenue - expenses) / 100, received:received / 100,
      balance:(revenue - received) / 100, expenseDetails:costs};
  }

  function groupSeries(orders, granularity) {
    const grouped = {};
    for (const order of orders) {
      const month = order.date.slice(0, 7);
      const year = month.slice(0, 4);
      const key = granularity === 'monthly' ? month : granularity === 'yearly' ? year :
        year + ' Q' + Math.ceil(Number(month.slice(5)) / 3);
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(order);
    }
    return Object.keys(grouped).sort().map(key => {
      const items = grouped[key];
      return {label:granularity === 'monthly' ? monthText(key, {month:'short', year:'numeric'}) : key,
        revenue:items.reduce((sum, order) => sum + store.cents(order.total), 0) / 100,
        expenses:expenseDetails(items).reduce((sum, entry) => sum + store.cents(entry.amount), 0) / 100};
    });
  }
  function chartMax(series) {
    var highest = Math.max.apply(null, [0].concat(series.map(function (row) {
      return Math.max(row.revenue, row.expenses);
    })));
    if (!highest) return 100;
    const step = 10 ** Math.floor(Math.log10(highest));
    return Math.ceil(highest / step) * step;
  }
  function axisLabel(value) {
    if (value < 1000) return '₱' + value.toLocaleString('en-PH');
    var thousands = value / 1000;
    return '₱' + (Number.isInteger(thousands) ? thousands : thousands.toFixed(1)) + 'k';
  }
  function renderCharts() {
    var granularity = $('repGranularity').value;
    var data = reportData();
    var series = groupSeries(data.valid, granularity);
    var max = chartMax(series);
    $('repSalesTitle').textContent = granularity === 'monthly' ? 'Monthly Sales' :
      (granularity === 'quarterly' ? 'Quarterly Sales' : 'Yearly Sales');
    $('repSalesSubtitle').textContent = monthRange(data.month) + ' · Booked order value';
    var previous = series.length > 1 ? series[series.length - 2].revenue : 0;
    var latest = series.length ? series[series.length - 1].revenue : 0;
    var trend = previous ? ((latest - previous) / previous) * 100 : 0;
    $('repSalesTrend').textContent = (trend >= 0 ? '+' : '') + trend.toFixed(1) + '%';
    var axis = '<div class="rep-chart-axis"><span>' + axisLabel(max) + '</span><span>' +
      axisLabel(max / 2) + '</span><span>₱0</span></div>';
    $('repBars').innerHTML = axis + '<div class="rep-chart-plot">' + series.map(function (row) {
      var percent = row.revenue / max * 100;
      return '<div class="rep-chart-column"><div class="rep-sales-track"><div class="rep-sales-bar" style="height:' +
        percent + '%" title="' + esc(money(row.revenue)) + '"></div></div><span class="rep-chart-label">' +
        esc(row.label) + '</span></div>';
    }).join('') + '</div>';
    $('repComparison').innerHTML = axis + '<div class="rep-chart-plot">' + series.map(function (row) {
      var revenueHeight = row.revenue / max * 100;
      var expenseHeight = row.expenses / max * 100;
      return '<div class="rep-chart-column"><div class="rep-compare-track"><div class="rep-compare-bar rep-revenue-bar" style="height:' +
        revenueHeight + '%" title="Revenue: ' + esc(money(row.revenue)) + '"></div><div class="rep-compare-bar rep-expense-bar" style="height:' +
        expenseHeight + '%" title="Expenses: ' + esc(money(row.expenses)) + '"></div></div><span class="rep-chart-label">' +
        esc(row.label) + '</span></div>';
    }).join('') + '</div>';
    $('repChartEmpty').hidden = series.length > 0;
  }
  function render() {
    var data = reportData();
    var margin = data.revenue ? data.profit / data.revenue * 100 : 0;
    var average = data.valid.length ? data.revenue / data.valid.length : 0;
    var selectedMonth = data.month === 'all' ? '' : data.month;
    var selectedMonthName = selectedMonth ? monthText(selectedMonth, {month:'long'}) : 'All periods';
    var selectedYear = selectedMonth ? new Date(selectedMonth + '-01T00:00:00').getFullYear() : '';
    $('repRevenue').textContent = money(data.revenue);
    $('repExpenses').textContent = money(data.expenses);
    $('repProfit').textContent = money(data.profit);
    $('repOrders').textContent = data.valid.length;
    $('repRevenueNote').textContent = 'Booked order value';
    $('repReceived').textContent = money(data.received);
    $('repBalance').textContent = money(data.balance);
    $('repProfitNote').textContent = margin.toFixed(1) + '% estimated profit margin';
    $('repOrderAverage').textContent = money2(average) + ' average order value';
    $('repAnalysisSubtitle').textContent = selectedMonth ?
      selectedMonthName + ' ' + selectedYear + ' · Booked sales less estimated production costs' : 'All dates · Booked sales less estimated production costs';
    $('repExpensesTable').innerHTML = data.expenseDetails.map(function (entry) {
      var share = data.revenue ? entry.amount / data.revenue * 100 : 0;
      return '<tr><td>' + esc(entry.name) + '</td><td>' + money(entry.amount) + '</td><td>' +
        share.toFixed(1) + '%</td></tr>';
    }).join('');
    $('repNetProfit').textContent = money(data.profit) + ' · ' + margin.toFixed(1) + '%';
    $('repGlanceTitle').textContent = selectedMonthName + ' at a glance';
    $('repMargin').textContent = margin.toFixed(1) + '%';
    $('repMarginBar').style.width = Math.max(0, Math.min(100, margin)) + '%';
    $('repGlanceText').textContent = 'Estimated profit uses the material, labor and packaging costs saved on each order.';
    $('repCalculation').textContent = money(data.revenue) + ' booked sales − ' + money(data.expenses) + ' estimated production costs';
    $('repPrepared').textContent = 'Prepared ' + store.today() + ' · Currency PHP (₱)';
    renderCharts();
  }
  $('repPeriod').addEventListener('change', render);
  $('repGranularity').addEventListener('change', renderCharts);
  $('repExport').addEventListener('click', function () {
    var data = reportData();
    var rows = [
      ['SAKURA BEADED BAGS - DEMO FINANCIAL REPORT'],
      ['Period', monthRange(data.month)],
      ['Metric','Amount PHP'],
      ['Booked sales',data.revenue],
      ['Received for selected orders',data.received],
      ['Outstanding for selected orders',data.balance],
      ['Estimated production costs',data.expenses],
      ['Estimated gross profit',data.profit],
      ['Orders',data.valid.length],
      [],
      ['Expense Category','Amount PHP','Share of Revenue']
    ];
    data.expenseDetails.forEach(function (entry) {
      rows.push([entry.name,entry.amount,data.revenue ? (entry.amount / data.revenue * 100).toFixed(1) + '%' : '0.0%']);
    });
    rows.push([],['Order ID','Date','Customer','Product','Qty','Order Value PHP','Status']);
    data.all.forEach(function (order) {
      rows.push([order.id,order.date,order.customer,order.product,order.qty,order.total,order.status]);
    });
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(store.csvCell).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url;
    link.download = 'sakura-financial-report-DEMO.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  });
  $('repPrint').addEventListener('click', function () { window.print(); });
  updatePeriods();
  render();
})();