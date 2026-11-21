/* Dashboard summaries use the same orders and material balances as the modules. */
(function () {
  'use strict';

  const store = window.SakuraStore;
  const data = store.read();
  const get = id => document.getElementById(id);
  const monthInput = get('monthInput');

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
      '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
    })[char]);
  }

  function render() {
    const month = monthInput.value || store.today().slice(0, 7);
    const orders = data.orders.filter(order => order.date.slice(0, 7) === month);
    const included = orders.filter(order => order.status !== 'Cancelled');
    const total = included.reduce((sum, order) => sum + store.cents(order.total), 0);
    const low = data.materials.filter(material => material.stock <= material.min);
    get('dash-sales').textContent = Sakura.peso(total / 100);
    get('dash-orders').textContent = orders.length;
    get('dash-pending').textContent = included.filter(order => order.status !== 'Delivered').length;
    get('dash-low').textContent = low.length;
    get('dash-note-sales').textContent = 'Non-cancelled orders in the selected month';
    get('dash-note-orders').textContent = 'Orders placed in the selected month';
    get('dash-note-pending').textContent = 'Awaiting production or delivery';
    get('dash-note-low').textContent = 'Current material balances';

    const recent = orders.slice().sort((a, b) => b.date.localeCompare(a.date) ||
      Number(b.id.split('-').pop()) - Number(a.id.split('-').pop())).slice(0, 3);
    get('dashRecent').innerHTML = recent.map(order => '<tr><td class="strong">' + escapeHtml(order.id) +
      '</td><td>' + escapeHtml(order.customer) + '</td><td>' + Sakura.peso(order.total) +
      '</td><td>' + escapeHtml(order.status) + '</td></tr>').join('');
    get('dashNoOrders').hidden = recent.length > 0;
    get('dashAlertCount').textContent = low.length + ' items';
    get('dashAlertList').innerHTML = low.slice(0, 5).map(material => '<li><div><strong>' +
      escapeHtml(material.name) + '</strong><small>' + escapeHtml(material.stock + ' ' + material.unit) +
      ' available</small></div><span class="low-tag">' +
      (material.stock === 0 ? 'Out of Stock' : 'Low Stock') + '</span></li>').join('');
    get('dashNoAlerts').hidden = low.length > 0;

    const [year, number] = month.split('-').map(Number);
    const chart = Array.from({length:6}, (_, index) => {
      const date = new Date(year, number - 6 + index, 1);
      const key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
      const sales = data.orders.reduce((sum, order) => sum +
        (order.status !== 'Cancelled' && order.date.slice(0, 7) === key ? store.cents(order.total) : 0), 0);
      return {label:date.toLocaleDateString('en-PH', {month:'short'}), sales};
    });
    const max = Math.max(100, ...chart.map(entry => entry.sales));
    get('dashChartTotal').textContent = Sakura.peso(total / 100) + ' selected month';
    get('dashChartAxis').innerHTML = [max, max / 2, 0].map(value =>
      '<span>' + Sakura.peso(value / 100) + '</span>').join('');
    get('dashChartBars').innerHTML = chart.map((entry, index) => '<div class="bar-col"><div class="bar' +
      (index === 5 ? ' is-current' : '') + '" style="--h:' + entry.sales / max * 100 + '%" title="' +
      escapeHtml(entry.label) + ': ' + Sakura.peso(entry.sales / 100) + '"></div><span>' +
      escapeHtml(entry.label) + '</span></div>').join('');
  }

  const requestedMonth = new URLSearchParams(location.search).get('month') || '';
  monthInput.value = /^\d{4}-(0[1-9]|1[0-2])$/.test(requestedMonth) ?
    requestedMonth : store.today().slice(0, 7);
  get('monthForm').addEventListener('submit', event => event.preventDefault());
  monthInput.addEventListener('change', render);
  render();
})();