<?php
/**
 * Financial Reports — payment-based sales ledger.
 * Ledger entries are successful payments, not audited accounting records.
 */
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Financial Reports';
$pageSubtitle = 'Review recorded sales by week, month, or year.';
$activePage = 'reports';
$pageCss = 'reports.css';
$pageBodyClass = 'reports-layout';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .fin-ledger{display:flex;max-width:1560px;margin:0 auto;flex-direction:column;gap:18px}
  .fin-ledger-toolbar,.fin-ledger-heading,.fin-ledger-total{display:flex;align-items:center;justify-content:space-between;gap:16px}
  .fin-ledger-heading{align-items:flex-start}
  .fin-ledger-heading h2{font-size:20px;font-weight:600}
  .fin-ledger-heading p,.fin-ledger-note{color:var(--muted);font-size:13px}
  .fin-ledger-tabs{display:flex;gap:6px;padding:4px;border:1px solid var(--line);border-radius:11px;background:#fff}
  .fin-ledger-tabs button{padding:8px 14px;border-radius:8px;color:var(--muted);font-size:13px}
  .fin-ledger-tabs button[aria-pressed="true"]{background:var(--pink-500);color:#fff}
  .fin-ledger-period{min-width:210px;padding:10px 12px;border:1px solid var(--line);border-radius:9px;background:#fff}
  .fin-ledger-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .fin-ledger-summary .card{border:1px solid var(--line);box-shadow:none}
  .fin-ledger-summary span{display:block;color:var(--muted);font-size:13px}
  .fin-ledger-summary strong{display:block;margin-top:5px;font-size:24px;font-weight:600}
  .fin-ledger-card{overflow:hidden;padding:0;border:1px solid var(--line);box-shadow:none}
  .fin-ledger-card-head{padding:18px 20px}
  .fin-ledger-card-head h2{font-size:16px;font-weight:600}
  .fin-ledger-total{padding:14px 20px;border-top:1px solid var(--line);background:var(--pink-50);font-size:14px}
  .fin-ledger-total strong{font-weight:600}
  .fin-ledger-table{min-width:680px}
  .fin-ledger-table td:last-child,.fin-ledger-table th:last-child{text-align:right}
  .fin-ledger-credit{color:var(--green);font-weight:600}
  .fin-ledger-empty{padding:36px 20px;color:var(--muted);text-align:center}
  .fin-ledger-status{padding:14px 20px;color:var(--muted);font-size:13px}
  .fin-ledger-status[hidden],.fin-ledger-content[hidden]{display:none}
  @media(max-width:640px){
    .fin-ledger-toolbar{align-items:stretch;flex-direction:column}
    .fin-ledger-tabs{justify-content:space-between}
    .fin-ledger-tabs button{flex:1;padding:8px 7px}
    .fin-ledger-period{width:100%}
    .fin-ledger-summary{gap:10px}
    .fin-ledger-summary .card{padding:14px}
    .fin-ledger-summary strong{font-size:19px}
    .fin-ledger-heading h2{font-size:18px}
  }
  @media print{
    .fin-ledger-toolbar{display:none}
    .fin-ledger-card,.fin-ledger-summary .card{break-inside:avoid}
  }
</style>

<section class="fin-ledger" id="financialLedger" aria-labelledby="ledgerTitle">
  <div class="fin-ledger-toolbar">
    <div class="fin-ledger-tabs" role="group" aria-label="Ledger reporting period">
      <button type="button" data-range="weekly" aria-pressed="true">Weekly</button>
      <button type="button" data-range="monthly" aria-pressed="false">Monthly</button>
      <button type="button" data-range="yearly" aria-pressed="false">Yearly</button>
    </div>
    <label>
      <span class="sr-only">Select ledger period</span>
      <select class="fin-ledger-period" id="ledgerPeriod" aria-label="Select ledger period" disabled>
        <option>Loading ledger…</option>
      </select>
    </label>
  </div>

  <div class="fin-ledger-heading">
    <div>
      <h2 id="ledgerTitle">Sales Ledger</h2>
      <p id="ledgerSubtitle">Successful payments recorded for the selected period.</p>
    </div>
    <button type="button" class="btn btn-outline" id="ledgerPrint" disabled>Print ledger</button>
  </div>

  <div class="fin-ledger-summary" aria-label="Selected period sales summary">
    <article class="card"><span>Total sales received</span><strong id="ledgerSales">₱0.00</strong></article>
    <article class="card"><span>Sales transactions</span><strong id="ledgerCount">0</strong></article>
  </div>

  <section class="card fin-ledger-card" aria-label="Sales transactions">
    <div class="fin-ledger-card-head"><h2 id="ledgerTableTitle">Weekly transactions</h2></div>
    <p class="fin-ledger-status" id="ledgerStatus" role="status">Loading sales ledger…</p>
    <div class="fin-ledger-content" id="ledgerContent" hidden>
      <div class="table-wrap">
        <table class="table fin-ledger-table">
          <thead>
            <tr><th>Date</th><th>Reference</th><th>Category</th><th>Description</th><th>Debit</th><th>Credit (PHP)</th><th>Balance</th></tr>
          </thead>
          <tbody id="ledgerRows"></tbody>
        </table>
      </div>
      <p class="fin-ledger-empty" id="ledgerEmpty" hidden>No sales recorded in this period.</p>
      <div class="fin-ledger-total"><strong>Period sales total</strong><strong id="ledgerPeriodTotal">₱0.00</strong></div>
    </div>
  </section>
  <p class="fin-ledger-note">Sales are based on successful payments for non-cancelled orders. Refunded and cancelled payments are excluded. This ledger is a payment record, not audited financial statements.</p>
</section>

<script>
(function () {
  'use strict';
  const root = document.getElementById('financialLedger');
  if (!root) return;

  const $ = id => document.getElementById(id);
  const periods = ['weekly', 'monthly', 'yearly'];
  const labels = {weekly:'Weekly', monthly:'Monthly', yearly:'Yearly'};
  const money = cents => '₱' + (cents / 100).toLocaleString('en-PH', {
    minimumFractionDigits:2, maximumFractionDigits:2
  });
  const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g,
    char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
  const isoDate = date => date.toISOString().slice(0, 10);
  const dateLabel = iso => new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', {
    month:'short', day:'numeric', year:'numeric'
  });

  let payments = [];
  let activeRange = 'weekly';
  let loaded = false;

  function periodFor(iso, range) {
    const date = new Date(iso + 'T00:00:00Z');
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    if (range === 'yearly') return {key:String(year), label:String(year)};
    if (range === 'monthly') return {
      key:year + '-' + month,
      label:date.toLocaleDateString('en-PH', {month:'long', year:'numeric', timeZone:'UTC'})
    };
    const monday = new Date(date);
    const day = (monday.getUTCDay() + 6) % 7;
    monday.setUTCDate(monday.getUTCDate() - day);
    const sunday = new Date(monday);
    sunday.setUTCDate(sunday.getUTCDate() + 6);
    const start = isoDate(monday);
    const end = isoDate(sunday);
    return {key:start, label:dateLabel(start) + ' – ' + dateLabel(end), start, end};
  }

  function periodsFor(range) {
    const available = new Map();
    payments.forEach(payment => {
      const period = periodFor(payment.date, range);
      available.set(period.key, period);
    });
    return [...available.values()].sort((a, b) => b.key.localeCompare(a.key));
  }

  function selectedPayments(period) {
    return payments.filter(payment => {
      const item = periodFor(payment.date, activeRange);
      return item.key === period.key;
    }).sort((a, b) => a.date.localeCompare(b.date) || a.id - b.id);
  }

  function render() {
    if (!loaded) return;
    const options = periodsFor(activeRange);
    const select = $('ledgerPeriod');
    const prior = select.value;
    select.innerHTML = options.map(period =>
      '<option value="' + escapeHtml(period.key) + '">' + escapeHtml(period.label) + '</option>'
    ).join('');
    if (options.length) {
      select.value = options.some(period => period.key === prior) ? prior : options[0].key;
    }

    const period = options.find(item => item.key === select.value);
    const rows = period ? selectedPayments(period) : [];
    let balance = 0;
    let total = 0;
    $('ledgerRows').innerHTML = rows.map(payment => {
      const amount = Math.round(payment.amount * 100);
      total += amount;
      balance += amount;
      return '<tr><td>' + escapeHtml(dateLabel(payment.date)) + '</td><td>PAY-' +
        escapeHtml(String(payment.id).padStart(5, '0')) + '</td><td>Sales income</td><td>Payment for ' +
        escapeHtml(payment.orderId) + '</td><td>—</td><td class="fin-ledger-credit">' +
        escapeHtml(money(amount)) + '</td><td>' + escapeHtml(money(balance)) + '</td></tr>';
    }).join('');

    $('ledgerTitle').textContent = labels[activeRange] + ' Sales Ledger';
    $('ledgerTableTitle').textContent = labels[activeRange] + ' transactions';
    $('ledgerSubtitle').textContent = period ? period.label + ' · successful payments received' :
      'No successful payment periods are available.';
    $('ledgerSales').textContent = money(total);
    $('ledgerCount').textContent = rows.length.toLocaleString('en-PH');
    $('ledgerPeriodTotal').textContent = money(total);
    $('ledgerEmpty').hidden = rows.length !== 0;
    $('ledgerContent').hidden = false;
    $('ledgerStatus').hidden = true;
  }

  root.querySelectorAll('[data-range]').forEach(button => {
    button.addEventListener('click', () => {
      activeRange = periods.includes(button.dataset.range) ? button.dataset.range : 'weekly';
      root.querySelectorAll('[data-range]').forEach(tab =>
        tab.setAttribute('aria-pressed', String(tab === button))
      );
      render();
    });
  });
  $('ledgerPeriod').addEventListener('change', render);
  $('ledgerPrint').addEventListener('click', () => { if (loaded) window.print(); });
  $('ledgerStatus').textContent = 'Loading sales ledger…';

  fetch('api/reports.php', {credentials:'same-origin', cache:'no-store'})
    .then(async response => {
      const data = await response.json();
      if (!response.ok || data.ok !== true) throw new Error(data.error || 'Could not load sales ledger.');
      if (!Array.isArray(data.payments)) throw new Error('The sales ledger data is incomplete.');
      return data.payments;
    })
    .then(records => {
      payments = records.filter(payment =>
        payment && /^\d{4}-\d{2}-\d{2}$/.test(String(payment.date || '')) &&
        Number.isFinite(Number(payment.id)) && Number.isFinite(Number(payment.amount)) &&
        Number(payment.amount) >= 0
      ).map(payment => ({
        id:Number(payment.id),
        orderId:String(payment.orderId || 'Order'),
        date:String(payment.date),
        amount:Number(payment.amount)
      }));
      loaded = true;
      $('ledgerPeriod').disabled = false;
      $('ledgerPrint').disabled = false;
      render();
    })
    .catch(error => {
      $('ledgerStatus').textContent = 'Could not load sales ledger: ' + error.message;
      console.error('SBB Sales Ledger:', error);
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>