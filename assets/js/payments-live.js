/* Sakura Beaded Bags — Payments & Sales backed by MySQL. */
(function () {
  'use strict';
  const $ = id => document.getElementById(id);
  if (!$('recordPaymentForm')) return;
  const api = 'api/payments.php';
  const peso = value => '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; };
  const cents = n => Math.round(Number(n || 0) * 100);
  const notify = message => { if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast(message); };
  let orders = [], payments = [], summary = {}, csrf = '', busy = false, page = 1, chosenSlip = '', requestKey = '';
  const pageSize = 5;
  const saveBtn = $('savePaymentBtn');
  const createKey = () => window.crypto && typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : '';
  function setError(message) { $('paymentFormError').textContent = message || ''; $('paymentFormError').hidden = !message; }
  function orderFor(id) { return orders.find(item => String(item.rawId) === String(id)); }
  function balance(order) { return order ? cents(order.balance) : 0; }
  function statusOf(payment) { return payment.status === 'Fully Paid' ? 'Fully Paid' : 'Partial'; }
  function statusBadge(label) {
    const color = label === 'Fully Paid' ? 'badge-green' : label === 'Partial' ? 'badge-amber' : 'badge-pink';
    return '<span class="badge ' + color + '">' + esc(label) + '</span>';
  }
  async function request(method, payload) {
    let options = {method, credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'}};
    if (payload) { options.headers['Content-Type'] = 'application/json'; options.headers['X-CSRF-Token'] = csrf; options.body = JSON.stringify(payload); }
    let response = await fetch(api, options);
    let json;
    try { json = await response.json(); } catch(e) { throw new Error('Server did not return JSON. Please check the PHP terminal.'); }
    if (!response.ok || !json.ok) throw new Error(json.error || 'Request failed.');
    return json;
  }
  async function refresh(preferredSlip) {
    let data = await request('GET');
    csrf = data.csrf || '';
    orders = Array.isArray(data.orders) ? data.orders : [];
    payments = Array.isArray(data.payments) ? data.payments : [];
    summary = data.summary || {};
    if (preferredSlip) chosenSlip = preferredSlip;
    else if (!payments.some(p => p.id === chosenSlip)) chosenSlip = payments.length ? payments[0].id : '';
    renderSummary(); renderOrderOptions(); renderTransactions(); renderSlip();
  }
  function renderSummary() {
    $('totalSales').textContent = peso(summary.totalSales);
    $('paymentsReceived').textContent = peso(summary.received);
    $('outstandingBalance').textContent = peso(summary.outstanding);
    $('collectionRate').textContent = String(Number(summary.collectionRate || 0).toFixed(1)) + '%';
    $('salesNote').textContent = String(summary.orderCount || 0) + ' non-cancelled orders · successful payments only';
    $('collectionNote').textContent = peso(summary.received) + ' of ' + peso(summary.orderValue) + ' order value received';
  }
  function renderOrderOptions() {
    const field = $('paymentOrder');
    const selected = field.value;
    const openOrders = orders.filter(o => o.status !== 'Cancelled' && balance(o) > 0);
    field.innerHTML = '<option value="">Select an unpaid order</option>' + openOrders.map(o =>
      '<option value="' + esc(o.rawId) + '">' + esc(o.id + ' · ' + o.customer) + ' · ' + peso(o.balance) + ' due</option>').join('');
    if (openOrders.some(o => String(o.rawId) === selected)) field.value = selected;
    const canRecord = openOrders.length > 0;
    field.disabled = !canRecord;
    saveBtn.disabled = !canRecord || busy;
    $('recordPaymentShortcut').disabled = !canRecord;
    updateBalanceHint();
  }
  function updateBalanceHint() {
    const o = orderFor($('paymentOrder').value);
    const remaining = balance(o);
    $('balanceHint').textContent = o ? `Remaining on ${o.id}: ${peso(remaining/100)}` : 'Select an unpaid order to see its balance.';
    $('paymentAmount').max = o ? (remaining/100).toFixed(2) : '';
  }
  function filtered() {
    const search = $('paymentSearch').value.trim().toLowerCase();
    const f = $('paymentFilter').value;
    return payments.filter(p => (f === 'all' ||
      (f === 'paid' ? p.paymentStatus === 'Successful' && p.status === 'Fully Paid' :
      f === 'partial' ? p.paymentStatus === 'Successful' && p.status === 'Partial' :
      p.paymentStatus.toLowerCase() === f)) &&
      (!search || [p.id,p.orderId,p.customer,p.reference,p.method].join(' ').toLowerCase().includes(search)));
  }
  function renderTransactions() {
    const rows = filtered();
    const pages = Math.max(1, Math.ceil(rows.length / pageSize));
    page = Math.max(1, Math.min(pages, page));
    const shown = rows.slice((page - 1) * pageSize, page * pageSize);
    $('paymentRows').innerHTML = shown.map(p => {
      const state = p.paymentStatus === 'Successful' ? statusOf(p) : p.paymentStatus;
      const actions = p.paymentStatus === 'Successful'
        ? '<button type="button" class="btn btn-outline btn-sm" data-payment-status="Cancelled" data-payment-id="' + esc(p.rawId) + '">Void</button> ' +
          '<button type="button" class="btn btn-outline btn-sm" data-payment-status="Refunded" data-payment-id="' + esc(p.rawId) + '">Refund</button>'
        : '—';
      return `<tr><td>${esc(p.id)}</td><td>${esc(p.orderId)}</td><td>${esc(p.customer)}</td><td>${peso(p.total)}</td><td>${peso(p.amount)}</td><td>${peso(p.balanceAfter)}</td><td>${esc(p.method)}</td><td>${esc(p.date)}</td><td>${statusBadge(state)}</td><td><button type="button" class="btn btn-outline btn-sm" data-slip="${esc(p.id)}">View Slip</button> ${actions}</td></tr>`;
    }).join('');
    $('paymentEmpty').hidden = rows.length > 0;
    $('paymentEmpty').textContent = payments.length ? 'No payments match your search or filter.' : 'No payment records yet.';
    $('paymentCount').textContent = `Showing ${shown.length} of ${rows.length} payments · All amounts in PHP`;
    $('paymentPagination').innerHTML = rows.length > pageSize ? Array.from({length:pages},(_,i)=>`<button type="button" class="btn btn-outline btn-sm" data-page="${i+1}" ${i+1===page ? 'aria-current="page"' : ''}>${i+1}</button>`).join('') : '';
  }
  function renderSlip() {
    const p = payments.find(item => item.id === chosenSlip);
    $('slipEmpty').hidden = !!p;
    $('slipDetails').hidden = !p;
    $('printSlip').disabled = !p || p.paymentStatus !== 'Successful';
    if (!p) return;
    $('slipCustomer').textContent = p.customer;
    $('slipOrder').textContent = p.orderId + ' · ' + p.id;
    $('slipDate').textContent = p.date;
    $('slipReference').textContent = p.reference || '—';
    $('slipTotal').textContent = peso(p.total);
    $('slipPaid').textContent = peso(p.amount);
    $('slipBalance').textContent = peso(p.balanceAfter);
    $('slipNote').textContent = p.paymentStatus === 'Refunded' ? 'This payment was fully refunded and is excluded from sales.' :
      p.paymentStatus === 'Cancelled' ? 'This payment was voided and is excluded from sales.' :
      p.balanceAfter <= 0 ? 'Order balance fully paid.' : 'Partial payment. Remaining balance: ' + peso(p.balanceAfter);
    if ($('slipDisclaimer')) $('slipDisclaimer').hidden = true;
  }
  function updateReference() {
    const cash = $('paymentMethod').value === 'Cash';
    $('referenceRequired').hidden = cash;
    $('paymentReference').required = !cash;
    $('paymentReference').placeholder = cash ? 'Optional cash receipt reference' : 'GCash / bank reference';
  }
  function validate() {
    const order = orderFor($('paymentOrder').value);
    if (!order || order.status === 'Cancelled' || balance(order) <= 0) throw new Error('Select an unpaid, active order.');
    const amountText = $('paymentAmount').value.trim();
    if (!/^\d{1,9}(?:\.\d{1,2})?$/.test(amountText) || cents(amountText) < 1) throw new Error('Enter a positive payment amount with at most two decimals.');
    if (cents(amountText) > balance(order)) throw new Error('Payment exceeds the remaining balance of ' + peso(order.balance) + '.');
    const method = $('paymentMethod').value;
    if (!['Cash','GCash','Bank Transfer'].includes(method)) throw new Error('Select a valid method.');
    const date = $('paymentDate').value;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date > today() || date < order.date) throw new Error('Payment date must be between order date and today.');
    const reference = $('paymentReference').value.trim();
    if (method !== 'Cash' && !reference) throw new Error('GCash and bank transfers require a reference.');
    if (reference.length > 80) throw new Error('Reference must be 80 characters or fewer.');
    return {action:'record_payment', orderId:order.rawId, amount:amountText, method, date, reference, requestKey};
  }
  $('paymentSearch').addEventListener('input', () => {page=1; renderTransactions();});
  $('paymentFilter').addEventListener('change', () => {page=1; renderTransactions();});
  $('paymentRows').addEventListener('click', async e => {
    const statusButton=e.target.closest('[data-payment-status]');
    if(statusButton){
      const status=statusButton.dataset.paymentStatus;
      const paymentId=statusButton.dataset.paymentId;
      const payment=payments.find(item=>String(item.rawId)===String(paymentId));
      if(!payment||busy)return;
      const prompt=status==='Refunded'
        ? 'Confirm the full amount was returned to the customer, then mark '+payment.id+' as refunded?'
        : 'Void '+payment.id+'? Its amount will be removed from sales and the order balance recalculated.';
      if(!window.confirm(prompt))return;
      busy=true;saveBtn.disabled=true;$('recordPaymentShortcut').disabled=true;
      try{
        const result=await request('POST',{action:'update_payment_status',paymentId:payment.rawId,status});
        await refresh(payment.id);
        notify(result.message||'Payment status updated.');
      }catch(error){
        setError(error.message||'Could not update payment status.');
        await refresh().catch(()=>{});
      }finally{busy=false;renderOrderOptions();}
      return;
    }
    const b=e.target.closest('[data-slip]');
    if(b){chosenSlip=b.dataset.slip;renderSlip();}
  });
  $('paymentPagination').addEventListener('click',e=>{const b=e.target.closest('[data-page]'); if(b){page=Number(b.dataset.page);renderTransactions();}});
  $('paymentOrder').addEventListener('change', updateBalanceHint);
  $('paymentMethod').addEventListener('change',updateReference);
  $('recordPaymentShortcut').addEventListener('click',()=>{$('recordPaymentCard').scrollIntoView({behavior:'smooth',block:'center'});$('paymentOrder').focus();});
  $('printSlip').addEventListener('click',()=>{if(chosenSlip)window.print();});
  $('recordPaymentForm').addEventListener('submit', async e => {
    e.preventDefault();
    if (busy) return;
    setError('');
    let payload;
    try { if (!requestKey) requestKey = createKey(); if (!requestKey) throw new Error('This browser cannot generate secure request IDs.'); payload=validate(); }
    catch(error) { setError(error.message); return; }
    busy=true; saveBtn.disabled=true;
    try {
      const result = await request('POST',payload);
      requestKey = '';
      $('recordPaymentForm').reset();
      $('paymentDate').value=today();
      updateReference();
      $('paymentSearch').value='';$('paymentFilter').value='all';page=1;
      await refresh(result.paymentId);
      notify(result.message || 'Payment saved in MySQL.');
    } catch(error) { setError(error.message || 'Could not save payment.'); await refresh().catch(()=>{}); }
    finally { busy=false; renderOrderOptions(); }
  });
  saveBtn.disabled=true;
  $('paymentDate').max=today();$('paymentDate').value=today();
  updateReference();
  refresh().then(()=>{
    const banner = document.querySelector('.pay-mode-banner');
    if (banner) banner.textContent = 'Local MySQL prototype · Payments are recorded in the database. No payment gateway is connected.';
    saveBtn.textContent = 'Save Payment';
    const params=new URLSearchParams(location.search);
    if (params.get('action') === 'record') {
      const wanted=params.get('order') || '';
      const option=Array.from($('paymentOrder').options).find(o=>o.value===wanted || o.textContent.startsWith(wanted+' '));
      if(option) $('paymentOrder').value=option.value;
      updateBalanceHint();
      $('recordPaymentCard').scrollIntoView({block:'center'});
    }
  }).catch(error=>{setError('Could not load payments: '+error.message);notify('Payments API unavailable. Check PHP server terminal.');});
})();
