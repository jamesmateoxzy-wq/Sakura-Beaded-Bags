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
  let orders = [], payments = [], methods = [], summary = {}, csrf = '', busy = false, page = 1, chosenSlip = '', requestKey = '';
  const pageSize = 5;
  const saveBtn = $('savePaymentBtn');
  const createKey = () => window.crypto && typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : '';
  function setError(message) { $('paymentFormError').textContent = message || ''; $('paymentFormError').hidden = !message; }
  function orderFor(id) { return orders.find(item => String(item.rawId) === String(id)); }
  function balance(order) { return order ? cents(order.balance) : 0; }
  function statusOf(payment) { return payment.status === 'Fully Paid' ? 'Fully Paid' : 'Downpayment'; }
  function statusBadge(label) {
    const color = label === 'Fully Paid' ? 'badge-green' : label === 'Downpayment' ? 'badge-amber' : 'badge-pink';
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
    methods = data.methods || [];
    const methodSelect=$('paymentMethod'), currentMethod=methodSelect.value;
    methodSelect.innerHTML=methods.map(m=>'<option value="'+esc(m.name)+'">'+esc(m.name)+'</option>').join('');
    if(methods.some(m=>m.name===currentMethod))methodSelect.value=currentMethod;
    updateReference();
    if (preferredSlip) {const t=payments.find(p=>p.id===preferredSlip);chosenSlip=t?t.slipId:preferredSlip;}
    if (!orders.some(o => o.slipId === chosenSlip)) chosenSlip = orders.length ? orders[0].slipId : '';
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
      f === 'partial' ? p.paymentStatus === 'Successful' && p.status === 'Downpayment' :
      p.paymentStatus.toLowerCase() === f)) &&
      (!search || [p.id,p.slipId,p.orderId,p.customer,p.reference,p.method].join(' ').toLowerCase().includes(search)));
  }
  function renderTransactions() {
    const rows = filtered();
    const pages = Math.max(1, Math.ceil(rows.length / pageSize));
    page = Math.max(1, Math.min(pages, page));
    const shown = rows.slice((page - 1) * pageSize, page * pageSize);
    $('paymentRows').innerHTML = shown.map(p => {
      const state = p.orderStatus==='Cancelled'?'Order Cancelled':p.paymentStatus === 'Successful' ? statusOf(p) : p.paymentStatus;
      const actions = p.paymentStatus === 'Successful' && p.orderStatus!=='Cancelled'
        ? '<button type="button" class="btn btn-outline btn-sm" data-payment-status="Cancelled" data-payment-id="' + esc(p.rawId) + '">Void</button> ' +
          '<button type="button" class="btn btn-outline btn-sm" data-payment-status="Refunded" data-payment-id="' + esc(p.rawId) + '">Refund</button>'
        : '—';
      return `<tr><td>${esc(p.slipId)}</td><td>${esc(p.id)}</td><td>${esc(p.orderId)}</td><td>${esc(p.customer)}</td><td>${peso(p.total)}</td><td>${peso(p.amount)}</td><td>${peso(p.balanceAfter)}</td><td>${esc(p.method)}</td><td>${esc(p.date)}</td><td>${statusBadge(state)}</td><td><button type="button" class="btn btn-outline btn-sm" data-slip="${esc(p.slipId)}">View Slip</button> ${actions}</td></tr>`;
    }).join('');
    $('paymentEmpty').hidden = rows.length > 0;
    $('paymentEmpty').textContent = payments.length ? 'No payments match your search or filter.' : 'No payment records yet.';
    $('paymentCount').textContent = `Showing ${shown.length} of ${rows.length} payments · All amounts in PHP`;
    $('paymentPagination').innerHTML = rows.length > pageSize ? Array.from({length:pages},(_,i)=>`<button type="button" class="btn btn-outline btn-sm" data-page="${i+1}" ${i+1===page ? 'aria-current="page"' : ''}>${i+1}</button>`).join('') : '';
  }
  function renderSlip() {
    const o=orders.find(x=>x.slipId===chosenSlip);
    $('slipEmpty').hidden=!!o;$('slipDetails').hidden=!o;$('printSlip').disabled=!o;
    if(!o)return;
    const history=payments.filter(p=>p.slipId===o.slipId).slice().reverse();
    $('slipCustomer').textContent=o.customer+' · '+o.customerId;
    $('slipOrder').textContent=o.id+' · '+o.slipId;
    $('slipDate').textContent=o.date;$('slipReference').textContent=history.length+' transaction(s)';
    $('slipTotal').textContent=peso(o.total);$('slipPaid').textContent=peso(o.paid);
    $('slipBalance').textContent=peso(o.status==='Cancelled'?0:o.balance);
    $('slipNote').textContent=o.status==='Cancelled'?'Cancelled order · Refund recorded: '+peso(o.refund)+' · No outstanding amount due.':o.balance<=0?'Fully paid':o.paid>0?'Downpayment received':'Pending payment';
    $('slipTransactions').innerHTML=history.map(p=>'<tr><td>'+esc(p.id)+'</td><td>'+esc(p.paymentStatus==='Successful'?(p.status==='Fully Paid'?'Full Payment':'Downpayment'):p.paymentStatus)+'</td><td>'+esc(p.date)+'</td><td>'+esc(p.method+' / '+(p.reference||'—'))+'</td><td>'+peso(p.amount)+'</td></tr>').join('');
    if(!history.length)$('slipTransactions').innerHTML='<tr><td colspan="5">No payments yet.</td></tr>';
    if($('slipDisclaimer'))$('slipDisclaimer').hidden=true;
  }
  function updateReference() {
    const m=methods.find(x=>x.name===$('paymentMethod').value);
    const required=!!m&&Number(m.requiresReference)===1;
    $('referenceRequired').hidden=!required;
    $('paymentReference').required=required;
    $('paymentReference').placeholder=required?'Transaction reference':'Optional reference';
  }
  function validate() {
    const order = orderFor($('paymentOrder').value);
    if (!order || order.status === 'Cancelled' || balance(order) <= 0) throw new Error('Select an unpaid, active order.');
    const amountText = $('paymentAmount').value.trim();
    if (!/^\d{1,9}(?:\.\d{1,2})?$/.test(amountText) || cents(amountText) < 1) throw new Error('Enter a positive payment amount with at most two decimals.');
    if (cents(amountText) > balance(order)) throw new Error('Payment exceeds the remaining balance of ' + peso(order.balance) + '.');
    const method = $('paymentMethod').value;
    const methodConfig=methods.find(m=>m.name===method);if(!methodConfig)throw new Error('Select a valid method.');
    const date = $('paymentDate').value;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date > today() || date < order.date) throw new Error('Payment date must be between order date and today.');
    const reference = $('paymentReference').value.trim();
    if (!reference && Number(methodConfig.requiresReference)===1) throw new Error('This method requires a reference.');
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
        notify(error.message||'Could not update payment status.');
        await refresh().catch(()=>{});
      }finally{busy=false;renderOrderOptions();}
      return;
    }
    const b=e.target.closest('[data-slip]');
    if(b){chosenSlip=b.dataset.slip;renderSlip();Sakura.openModal('paymentSlipModal');}
  });
  $('paymentPagination').addEventListener('click',e=>{const b=e.target.closest('[data-page]'); if(b){page=Number(b.dataset.page);renderTransactions();}});
  $('addPaymentMethod').addEventListener('click',async()=>{
    if(busy)return;const name=window.prompt('New payment method name:');if(name===null||!name.trim())return;
    const required=window.confirm('Does this payment method require a reference number? OK = Yes, Cancel = No.');
    busy=true;try{await request('POST',{action:'add_method',name:name.trim(),requiresReference:required});await refresh();$('paymentMethod').value=name.trim();updateReference();notify('Payment method added.');}catch(e){setError(e.message);}finally{busy=false;renderOrderOptions();}
  });
  $('paymentOrder').addEventListener('change', updateBalanceHint);
  $('paymentMethod').addEventListener('change',updateReference);
  $('recordPaymentShortcut').addEventListener('click',()=>{setError('');Sakura.openModal('recordPaymentModal');});
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
      await refresh(result.slipId || result.paymentId);
      Sakura.closeModal('recordPaymentModal');
      Sakura.openModal('paymentSlipModal');
      notify(result.message || 'Payment saved successfully.');
    } catch(error) { setError(error.message || 'Could not save payment.'); await refresh().catch(()=>{}); }
    finally { busy=false; renderOrderOptions(); }
  });
  saveBtn.disabled=true;
  $('paymentDate').max=today();$('paymentDate').value=today();
  updateReference();
  refresh().then(()=>{
    const banner = document.querySelector('.pay-mode-banner');
    if (banner) banner.textContent = 'Payments are recorded in the system. Payment verification is handled manually by authorized staff.';
    saveBtn.textContent = 'Save Payment';
    const params=new URLSearchParams(location.search);
    if (params.get('action') === 'record') {
      const wanted=params.get('order') || '';
      const option=Array.from($('paymentOrder').options).find(o=>o.value===wanted || o.textContent.startsWith(wanted+' '));
      if(option) $('paymentOrder').value=option.value;
      updateBalanceHint();
      Sakura.openModal('recordPaymentModal');
    }
  }).catch(error=>{setError('Could not load payments: '+error.message);notify('Unable to load payments. Please try again or contact the system administrator.');});
})();
