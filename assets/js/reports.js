/* Sakura Beaded Bags - Financial Reports */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'reports') return;
  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var reportSeries = [
    {month:'2026-05', revenue:11500, expenses:6500},
    {month:'2026-06', revenue:14000, expenses:7700},
    {month:'2026-07', revenue:16000, expenses:9000},
    {month:'2026-08', revenue:17500, expenses:9800},
    {month:'2026-09', revenue:21500, expenses:13000},
    {month:'2026-10', revenue:24500, expenses:14200}
  ];
  var expenseCategories = ['Materials', 'Labor', 'Packaging & overhead'];
  var statuses = ['Pending', 'In Progress', 'Completed', 'Delivered'];

  function makeSampleOrders() {
    var products = ['Pearl Heart Bag', 'Sakura Mini Pouch', 'Classic Tote', 'Petal Handbag'];
    var orders = [];
    for (var index = 0; index < 32; index += 1) {
      orders.push({
        id:'ORD-' + String(index + 1).padStart(3, '0'),
        customer:'Customer ' + String(index + 1).padStart(2, '0'),
        product:products[index % products.length],
        qty:1,
        unitPrice:index === 31 ? 785 : 765,
        total:index === 31 ? 785 : 765,
        date:'2026-10-' + String(index % 28 + 1).padStart(2, '0'),
        status:statuses[index % statuses.length]
      });
    }
    return orders;
  }
  function loadState() {
    var state = {materials:[], orders:[]};
    if (!demo) return state;
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || 'null');
      if (stored && Array.isArray(stored.materials)) state.materials = stored.materials;
      if (stored && Array.isArray(stored.orders)) state.orders = stored.orders;
    } catch (error) {
      notify('Could not read demo report data from this browser session.');
    }
    if (!state.orders.length) state.orders = makeSampleOrders();
    return state;
  }
  var state = loadState();
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {maximumFractionDigits:0});
  }
  function money2(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function notify(message) {
    if (window.Sakura && Sakura.toast) Sakura.toast(message);
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
  function expenseDetails(month) {
    var expenses = 0;
    if (month === 'all') {
      var months = state.orders.map(function (order) { return order.date.slice(0, 7); })
        .filter(function (item, index, list) { return list.indexOf(item) === index; });
      months.forEach(function (item) {
        var fixture = reportSeries.find(function (row) { return row.month === item; });
        if (fixture) expenses += fixture.expenses;
      });
    } else {
      var fixture = reportSeries.find(function (row) { return row.month === month; });
      if (fixture) expenses = fixture.expenses;
    }
    if (!demo) expenses = 0;
    var materials = Math.round(expenses * 0.5774647887);
    var labor = Math.round(expenses * 0.2816901408);
    var overhead = expenses - materials - labor;
    return [
      {name:expenseCategories[0], amount:materials},
      {name:expenseCategories[1], amount:labor},
      {name:expenseCategories[2], amount:overhead}
    ];
  }
  function reportData() {
    var month = $('repPeriod').value || 'all';
    var all = state.orders.filter(function (order) {
      return month === 'all' || order.date.slice(0, 7) === month;
    });
    var valid = all.filter(function (order) { return order.status !== 'Cancelled'; });
    var revenue = valid.reduce(function (sum, order) { return sum + Number(order.total || 0); }, 0);
    var expenses = expenseDetails(month).reduce(function (sum, entry) { return sum + entry.amount; }, 0);
    return {
      all:all,
      valid:valid,
      month:month,
      revenue:revenue,
      expenses:expenses,
      profit:revenue - expenses,
      expenseDetails:expenseDetails(month)
    };
  }
  function groupSeries(granularity) {
    if (granularity === 'monthly') {
      return reportSeries.map(function (row) {
        return {label:monthText(row.month, {month:'short'}), revenue:row.revenue, expenses:row.expenses};
      });
    }
    var grouped = {};
    reportSeries.forEach(function (row) {
      var date = new Date(row.month + '-01T00:00:00');
      var key = granularity === 'yearly' ? String(date.getFullYear()) :
        String(date.getFullYear()) + '-Q' + Math.ceil((date.getMonth() + 1) / 3);
      if (!grouped[key]) grouped[key] = {label:granularity === 'yearly' ? key : key.slice(-2), revenue:0, expenses:0};
      grouped[key].revenue += row.revenue;
      grouped[key].expenses += row.expenses;
    });
    return Object.keys(grouped).sort().map(function (item) { return grouped[item]; });
  }
  function chartMax(series) {
    var highest = Math.max.apply(null, [0].concat(series.map(function (row) {
      return Math.max(row.revenue, row.expenses);
    })));
    return Math.max(25000, Math.ceil(highest / 5000) * 5000);
  }
  function axisLabel(value) {
    if (!value) return '₱0';
    var thousands = value / 1000;
    return '₱' + (Number.isInteger(thousands) ? thousands : thousands.toFixed(1)) + 'k';
  }
  function renderCharts() {
    var granularity = $('repGranularity').value;
    var series = demo ? groupSeries(granularity) : [];
    var max = chartMax(series);
    var monthRangeText = reportSeries.length ?
      monthText(reportSeries[0].month, {month:'long'}) + '\u2013' +
      monthText(reportSeries[reportSeries.length - 1].month, {month:'long', year:'numeric'}) : '';
    $('repSalesTitle').textContent = granularity === 'monthly' ? 'Monthly Sales' :
      (granularity === 'quarterly' ? 'Quarterly Sales' : 'Yearly Sales');
    $('repSalesSubtitle').textContent = monthRangeText + ' · Order revenue';
    var previous = series.length > 1 ? series[series.length - 2].revenue : 0;
    var latest = series.length ? series[series.length - 1].revenue : 0;
    var trend = previous ? ((latest - previous) / previous) * 100 : 0;
    $('repSalesTrend').textContent = (trend >= 0 ? '+' : '') + trend.toFixed(1) + '%';
    var axis = '<div class="rep-chart-axis"><span>' + axisLabel(max) + '</span><span>' +
      axisLabel(max / 2) + '</span><span>₱0</span></div>';
    $('repBars').innerHTML = axis + '<div class="rep-chart-plot">' + series.map(function (row) {
      var percent = Math.max(2, row.revenue / max * 100);
      return '<div class="rep-chart-column"><div class="rep-sales-track"><div class="rep-sales-bar" style="height:' +
        percent + '%" title="' + esc(money(row.revenue)) + '"></div></div><span class="rep-chart-label">' +
        esc(row.label) + '</span></div>';
    }).join('') + '</div>';
    $('repComparison').innerHTML = axis + '<div class="rep-chart-plot">' + series.map(function (row) {
      var revenueHeight = Math.max(2, row.revenue / max * 100);
      var expenseHeight = Math.max(2, row.expenses / max * 100);
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
    var previousRow = reportSeries.find(function (row) { return row.month === '2026-09'; });
    var currentRow = reportSeries.find(function (row) { return row.month === '2026-10'; });
    var revenueChange = currentRow && previousRow ? currentRow.revenue - previousRow.revenue : 0;
    var previousProfit = previousRow ? previousRow.revenue - previousRow.expenses : 0;
    var profitChange = currentRow && previousRow ? data.profit - previousProfit : 0;
    $('repRevenue').textContent = money(data.revenue);
    $('repExpenses').textContent = money(data.expenses);
    $('repProfit').textContent = money(data.profit);
    $('repOrders').textContent = data.valid.length;
    $('repRevenueNote').textContent = selectedMonth === '2026-10' ? '+14.0% vs September' :
      (data.revenue ? 'Current period revenue' : 'No revenue recorded');
    $('repProfitNote').textContent = margin.toFixed(1) + '% net profit margin';
    $('repOrderAverage').textContent = money2(average) + ' average order value';
    $('repAnalysisSubtitle').textContent = selectedMonth ?
      selectedMonthName + ' ' + selectedYear + ' · Revenue less recorded expenses' : 'All dates · Revenue less recorded expenses';
    $('repExpensesTable').innerHTML = data.expenseDetails.map(function (entry) {
      var share = data.revenue ? entry.amount / data.revenue * 100 : 0;
      return '<tr><td>' + esc(entry.name) + '</td><td>' + money(entry.amount) + '</td><td>' +
        share.toFixed(1) + '%</td></tr>';
    }).join('');
    $('repNetProfit').textContent = money(data.profit) + ' · ' + margin.toFixed(1) + '%';
    $('repGlanceTitle').textContent = selectedMonthName + ' at a glance';
    $('repMargin').textContent = margin.toFixed(1) + '%';
    $('repMarginBar').style.width = Math.max(0, Math.min(100, margin)) + '%';
    $('repGlanceText').textContent = selectedMonth === '2026-10' ?
      'Revenue grew by ' + money(revenueChange) + ' from September, while net profit increased by ' +
      money(profitChange) + '.' : 'Revenue and expenses for the selected reporting period.';
    $('repCalculation').textContent = 'Revenue: ' + money(data.revenue) + ' - Expenses: ' + money(data.expenses);
    $('repPrepared').textContent = 'Prepared ' + (selectedMonth ? monthText(selectedMonth, {month:'long'}) + ' 28, ' + selectedYear : 'current period') +
      ' · Currency: PHP (₱)';
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
      ['Revenue',data.revenue],
      ['Expenses',data.expenses],
      ['Net Profit',data.profit],
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
      return row.map(function (value) { return '"' + String(value).replace(/"/g, '""') + '"'; }).join(',');
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
