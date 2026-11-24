/* Sakura Beaded Bags - Financial Reports backed by read-only MySQL API. */
(function () {
  'use strict';
  const root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'reports') return;

  const $ = id => document.getElementById(id);
  const formatMoney = amount => '₱' + Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2, maximumFractionDigits: 2
  });
  const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g,
    char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
  const cents = value => Math.round(Number(value || 0) * 100);
  const monthOf = iso => String(iso || '').slice(0, 7);
  const shortMonth = month => {
    const parts = /^([0-9]{4})-([0-9]{2})$/.exec(month);
    if (!parts) return month;
    return new Date(Number(parts[1]), Number(parts[2]) - 1, 1).toLocaleDateString('en-PH', {
      month: 'short', year: 'numeric'
    });
  };
  const csvCell = value => {
    let s = String(value == null ? '' : value);
    if (/^[\s]*[=+\-@\t\r]/.test(s)) s = "'" + s;
    return '"' + s.replace(/"/g, '""') + '"';
  };

  let orders = [];
  let payments = [];
  let expenses = [];
  let loaded = false;

  function summary() {
    const month = $('repPeriod').value || 'all';
    const match = iso => month === 'all' || monthOf(iso) === month;
    const includedOrders = orders.filter(order => match(order.date));
    const includedPayments = payments.filter(payment => match(payment.date));
    const includedExpenses = expenses.filter(expense => match(expense.date));
    const sales = includedPayments.reduce((sum, payment) => sum + cents(payment.amount), 0);
    const estimatedCost = includedOrders.reduce((sum, order) => sum + cents(order.estimatedCost), 0);
    const paid = sales;
    const orderValue = includedOrders.reduce((sum, order) => sum + cents(order.total), 0);
    const extraExpenses = includedExpenses.reduce((sum, e) => sum + cents(e.amount), 0);
    return {
      month, includedOrders, includedPayments, includedExpenses, sales, estimatedCost, paid, orderValue, extraExpenses,
      totalExpenses: estimatedCost + extraExpenses,
      profit: sales - estimatedCost - extraExpenses,
      balance: includedOrders.reduce((sum, order) => sum + cents(order.balance), 0)
    };
  }

  function loadPeriods() {
    const months = [...new Set(orders.map(order => monthOf(order.date))
      .concat(payments.map(payment => monthOf(payment.date)))
      .concat(expenses.map(expense => monthOf(expense.date))))]
      .filter(value => /^\d{4}-\d{2}$/.test(value)).sort().reverse();
    $('repPeriod').innerHTML = '<option value="all">All dates</option>' + months.map(month =>
      '<option value="' + escapeHtml(month) + '">' + escapeHtml(shortMonth(month)) + '</option>'
    ).join('');
    $('repPeriod').value = months[0] || 'all';
  }

  function expenseCategories(data) {
    const categories = new Map();
    if (data.estimatedCost) categories.set('Estimated production cost', data.estimatedCost);
    for (const item of data.includedExpenses) {
      const label = 'Other expenses: ' + (item.category || 'Uncategorized');
      categories.set(label, (categories.get(label) || 0) + cents(item.amount));
    }
    return [...categories].map(([name, value]) => ({name, value}));
  }

  function groupSeries(data) {
    const granularity = $('repGranularity').value;
    const series = new Map();
    function keyFor(date) {
      const month = monthOf(date);
      const year = Number(month.slice(0, 4));
      const m = Number(month.slice(5, 7));
      if (granularity === 'yearly') return String(year);
      if (granularity === 'quarterly') return year + ' Q' + Math.ceil(m / 3);
      return month;
    }
    function get(key) {
      if (!series.has(key)) series.set(key, {key, revenue:0, expenses:0});
      return series.get(key);
    }
    for (const payment of data.includedPayments) {
      get(keyFor(payment.date)).revenue += cents(payment.amount);
    }
    for (const order of data.includedOrders) {
      const point = get(keyFor(order.date));
      point.expenses += cents(order.estimatedCost);
    }
    for (const expense of data.includedExpenses) {
      get(keyFor(expense.date)).expenses += cents(expense.amount);
    }
    return [...series.values()].sort((a, b) => a.key.localeCompare(b.key)).map(item => ({
      ...item, label:granularity === 'monthly' ? shortMonth(item.key) : item.key
    }));
  }

  function chartMaximum(points) {
    const biggest = Math.max(0, ...points.map(item => Math.max(item.revenue, item.expenses)));
    if (!biggest) return 10000; // cents => ₱100
    const order = 10 ** Math.floor(Math.log10(biggest));
    return Math.ceil(biggest / order) * order;
  }
  function chartAxis(max) {
    return '<div class="rep-chart-axis"><span>' + escapeHtml(formatMoney(max / 100)) + '</span><span>' +
      escapeHtml(formatMoney(max / 200)) + '</span><span>₱0</span></div>';
  }
  function graphBars(points, max, compare) {
    return '<div class="rep-chart-plot">' + points.map(item => {
      const salesPercent = Math.max(0, Math.min(100, item.revenue / max * 100));
      const costPercent = Math.max(0, Math.min(100, item.expenses / max * 100));
      if (!compare) return '<div class="rep-chart-column"><div class="rep-sales-track">' +
        '<div class="rep-sales-bar" style="height:' + salesPercent.toFixed(2) + '%" title="' +
        escapeHtml(formatMoney(item.revenue / 100)) + '"></div></div><span class="rep-chart-label">' +
        escapeHtml(item.label) + '</span></div>';
      return '<div class="rep-chart-column"><div class="rep-compare-track">' +
        '<div class="rep-compare-bar rep-revenue-bar" style="height:' + salesPercent.toFixed(2) + '%" title="Sales ' +
        escapeHtml(formatMoney(item.revenue / 100)) + '"></div>' +
        '<div class="rep-compare-bar rep-expense-bar" style="height:' + costPercent.toFixed(2) + '%" title="Expenses ' +
        escapeHtml(formatMoney(item.expenses / 100)) + '"></div></div><span class="rep-chart-label">' +
        escapeHtml(item.label) + '</span></div>';
    }).join('') + '</div>';
  }

  function renderCharts(data) {
    const granularity = $('repGranularity').value;
    const points = groupSeries(data);
    const max = chartMaximum(points);
    $('repSalesTitle').textContent = granularity === 'monthly' ? 'Monthly Sales' :
      granularity === 'quarterly' ? 'Quarterly Sales' : 'Yearly Sales';
    $('repSalesSubtitle').textContent = (data.month === 'all' ? 'All dates' : shortMonth(data.month)) +
      ' · successful payments received';
    const last = points.length ? points[points.length - 1].revenue : 0;
    const previous = points.length > 1 ? points[points.length - 2].revenue : 0;
    $('repSalesTrend').textContent = previous ?
      ((last - previous) >= 0 ? '+' : '') + ((last - previous) / previous * 100).toFixed(1) + '%' : 'No prior period';
    $('repBars').innerHTML = chartAxis(max) + graphBars(points, max, false);
    $('repComparison').innerHTML = chartAxis(max) + graphBars(points, max, true);
    $('repChartEmpty').hidden = points.some(item => item.revenue !== 0);
    $('repComparisonEmpty').hidden = points.some(item => item.revenue !== 0 || item.expenses !== 0);
  }

  function render() {
    if (!loaded) return;
    const data = summary();
    const total = data.sales / 100;
    const costs = data.totalExpenses / 100;
    const profit = data.profit / 100;
    const margin = data.sales ? data.profit / data.sales * 100 : 0;
    const label = data.month === 'all' ? 'All dates' : shortMonth(data.month);
    $('repRevenue').textContent = formatMoney(total);
    $('repRevenueNote').textContent = 'Successful payments received in this period';
    $('repExpenses').textContent = formatMoney(costs);
    $('repProfit').textContent = formatMoney(profit);
    $('repOrders').textContent = String(data.includedOrders.length);
    $('repOrderAverage').textContent = formatMoney(data.includedOrders.length ?
      data.orderValue / 100 / data.includedOrders.length : 0) + ' average order value';
    $('repProfitNote').textContent = margin.toFixed(1) + '% estimated profit margin';
    $('repAnalysisSubtitle').textContent = label + ' · successful payments minus estimated production costs and recorded expenses';
    const categories = expenseCategories(data);
    $('repExpensesTable').innerHTML = categories.map(item => '<tr><td>' + escapeHtml(item.name) + '</td><td>' +
      formatMoney(item.value / 100) + '</td><td>' +
      (data.sales ? (item.value / data.sales * 100).toFixed(1) : '0.0') + '%</td></tr>').join('');
    $('repExpensesEmpty').hidden = categories.length !== 0;
    $('repNetProfit').textContent = formatMoney(profit) + ' · ' + margin.toFixed(1) + '%';
    $('repGlanceTitle').textContent = label + ' at a glance';
    $('repMargin').textContent = margin.toFixed(1) + '%';
    $('repMarginBar').style.width = Math.max(0, Math.min(100, margin)) + '%';
    $('repGlanceText').textContent = 'Estimated profit uses cost snapshots saved with orders and separately recorded expenses; it is not audited net income.';
    $('repCalculation').textContent = formatMoney(total) + ' successful payments − ' +
      formatMoney(data.estimatedCost / 100) + ' estimated production costs − ' +
      formatMoney(data.extraExpenses / 100) + ' recorded other expenses';
    $('repPrepared').textContent = 'Successful payments received: ' +
      formatMoney(data.paid / 100) + ' · Outstanding: ' + formatMoney(data.balance / 100) +
      ' · Currency PHP (₱)';
    renderCharts(data);
  }

  function exportCsv() {
    if (!loaded) return;
    const data = summary();
    const rows = [
      [String(root.dataset.business || 'Sakura Beaded Bags') + ' - PAYMENT-BASED FINANCIAL REPORT'],
      ['Period', data.month === 'all' ? 'All dates' : data.month],
      ['Metric', 'Amount PHP'],
      ['Total sales (successful payments)', (data.sales / 100).toFixed(2)],
      ['Outstanding balance', (data.balance / 100).toFixed(2)],
      ['Estimated production costs', (data.estimatedCost / 100).toFixed(2)],
      ['Recorded other expenses', (data.extraExpenses / 100).toFixed(2)],
      ['Estimated profit', (data.profit / 100).toFixed(2)],
      ['Orders', data.includedOrders.length], [],
      ['Expense Category', 'Amount PHP']
    ];
    expenseCategories(data).forEach(entry => rows.push([entry.name, (entry.value / 100).toFixed(2)]));
    rows.push([], ['Order', 'Date', 'Status', 'Order Value PHP', 'Estimated Cost PHP', 'Received PHP', 'Balance PHP']);
    data.includedOrders.forEach(item => rows.push([item.id, item.date, item.status,
      item.total.toFixed(2), item.estimatedCost.toFixed(2), item.received.toFixed(2), item.balance.toFixed(2)]));
    rows.push([], ['Recorded Other Expense Date', 'Category', 'Description', 'Amount PHP']);
    data.includedExpenses.forEach(item => rows.push([item.date, item.category, item.description, item.amount.toFixed(2)]));
    rows.push([], ['Notes', 'Sales include successful payments only; cancelled and refunded payments are excluded. Profit is estimated, not audited net income.']);
    const csv = '\ufeff' + rows.map(row => row.map(csvCell).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sakura-financial-report-' + (data.month === 'all' ? 'all' : data.month) + '.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 500);
  }

  // Figures remain estimates; revenue now uses successful payment records.
  function renameCard(valueId, label) {
    const card = $(valueId).closest('.rep-stat');
    const heading = card && card.querySelector('div span');
    if (heading) heading.textContent = label;
  }
  renameCard('repRevenue', 'Total Sales');
  renameCard('repExpenses', 'Est. Costs + Expenses');
  renameCard('repProfit', 'Estimated Profit');
  const profitLabel = $('repNetProfit').previousElementSibling;
  if (profitLabel) profitLabel.textContent = 'Estimated Profit';

  $('repPeriod').addEventListener('change', render);
  $('repGranularity').addEventListener('change', render);
  $('repExport').addEventListener('click', exportCsv);
  $('repPrint').addEventListener('click', () => { if (loaded) window.print(); });
  $('repExport').disabled = true;
  $('repPrint').disabled = true;
  $('repPrepared').textContent = 'Loading financial data from MySQL…';
  fetch('api/reports.php', {credentials: 'same-origin', cache: 'no-store'})
    .then(async response => {
      const data = await response.json();
      if (!response.ok || data.ok !== true) throw new Error(data.error || 'Financial Reports API failed.');
      if (!Array.isArray(data.orders) || !Array.isArray(data.payments) || !Array.isArray(data.expenses)) throw new Error('Unexpected report data.');
      return data;
    })
    .then(data => {
      orders = data.orders;
      payments = data.payments;
      expenses = data.expenses;
      loaded = true;
      loadPeriods();
      $('repExport').disabled = false;
      $('repPrint').disabled = false;
      render();
    })
    .catch(error => {
      $('repPrepared').textContent = 'Could not load reports: ' + error.message;
      if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast('Could not load Financial Reports from MySQL.');
      console.error('SBB Financial Reports:', error);
    });
})();
