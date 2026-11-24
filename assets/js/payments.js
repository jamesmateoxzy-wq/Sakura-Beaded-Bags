/* Sakura Beaded Bags — Payments & Sales frontend.
 * DEMO ONLY: payments are held in sessionStorage for this browser tab.
 * No bank/GCash charge is initiated and no MySQL writes take place.
 * See docs/PAYMENTS_SALES_HANDOFF.md for backend integration notes.
 */
(function () {
  'use strict';

  var store = window.SakuraStore;
  var state = store.read();
  var orders = state.orders.filter(order => order.status !== 'Cancelled');
  var payments = state.payments.filter(payment => orders.some(order => order.id === payment.orderId));
  var selectedSlip = null;
  var page = 1;
  var pageSize = 5;
  var busy = false;
  var $ = function (id) { return document.getElementById(id); };

  function peso(number) {
    var amount = Number(number) || 0;
    return '₱' + amount.toLocaleString('en-PH', {
      minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
      maximumFractionDigits: 2
    });
  }
  var cents = store.cents;
  function toPesos(amountInCents) { return amountInCents / 100; }
  function safe(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function dateText(iso) {
    var d = new Date(String(iso) + 'T00:00:00');
    return isNaN(d.getTime()) ? iso : d.toLocaleDateString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric'
    });
  }
  function todayLocal() {
    var d = new Date();
    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
  }
  function validLocalDate(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    var parts = value.split('-').map(Number);
    var date = new Date(parts[0], parts[1] - 1, parts[2]);
    return date.getFullYear() === parts[0] && date.getMonth() === parts[1] - 1 &&
      date.getDate() === parts[2];
  }
  function orderFor(id) {
    return orders.filter(function (o) { return o.id === id; })[0] || null;
  }
  function balanceCents(order) {
    return Math.max(0, cents(order.total) - store.paidCents(payments, order.id));
  }
  function statusFor(p) {
    return cents(p.balanceAfter) <= 0 ? 'Fully Paid' : 'Partial';
  }
  function notice(message) {
    if (window.Sakura && Sakura.toast) Sakura.toast(message);
  }

  function loadDemoOrders() {
    if (!demo) return;
    try {
      var stored = JSON.parse(sessionStorage.getItem('sakura-operations-v1') || '{}');
      if (!stored || !Array.isArray(stored.orders)) return;
      orders = stored.orders.filter(function (order) {
        return order && typeof order.id === 'string' && /^ORD-\d+$/.test(order.id) &&
          typeof order.customer === 'string' && Number.isFinite(Number(order.total)) &&
          Number(order.total) >= 0;
      });
    } catch (error) {
      notice('Could not read orders from this browser session.');
    }
  }

  function syncOrderPayment(orderId, persist) {
    var order = orderFor(orderId);
    if (!order) return;
    var paid = paidCents(orderId);
    var total = cents(order.total);
    order.payment = paid >= total && total > 0 ? 'Fully Paid' : paid > 0 ? 'Partial' : 'Unpaid';
    order.deposit = toPesos(paid);
    if (!persist) return;
    try {
      var stored = JSON.parse(sessionStorage.getItem('sakura-operations-v1') || '{}');
      if (!stored || !Array.isArray(stored.orders)) return;
      var storedOrder = stored.orders.find(function (item) { return item.id === orderId; });
      if (!storedOrder) return;
      storedOrder.payment = order.payment;
      storedOrder.deposit = order.deposit;
      sessionStorage.setItem('sakura-operations-v1', JSON.stringify(stored));
    } catch (error) {
      notice('Payment saved, but the order payment status could not be updated.');
    }
  }

  // Validate restored demo data so altered/stale browser storage cannot be
  // treated as trusted records. Actual payment validation belongs on the server.
  function loadDemoPayments() {
    if (!demo) return;
    try {
      var stored = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
      if (!Array.isArray(stored)) return;
      var seen = {};
      seed.forEach(function (p) { seen[p.id] = true; });
      stored.slice(0, 200).forEach(function (p) {
        if (!p || typeof p !== 'object' || typeof p.id !== 'string' ||
            !/^SLP-\d+$/.test(p.id) || seen[p.id] || !orderFor(p.orderId) ||
            !['Cash', 'GCash', 'Bank Transfer'].includes(p.method) ||
            typeof p.date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(p.date) ||
            typeof p.reference !== 'string' || p.reference.length > 80 ||
            !Number.isFinite(Number(p.amount)) || Number(p.amount) <= 0) return;

        var o = orderFor(p.orderId);
        var balance = balanceCents(o);
        var amount = cents(p.amount);
        if (amount <= 0 || amount > balance) return;
        var clean = {
          id: p.id, orderId: o.id, amount: toPesos(amount), method: p.method,
          date: p.date, reference: p.reference, balanceAfter: toPesos(balance - amount)
        };
        seen[p.id] = true;
        added.push(clean);
        payments.push(clean);
      });
    } catch (err) {
      // Session storage can be unavailable (e.g. strict private browsing).
      added = [];
    }
  }

  function renderSummary() {
    var sales = orders.filter(function (order) { return order.status !== 'Cancelled'; })
      .reduce(function (sum, o) { return sum + cents(o.total); }, 0);
    var received = payments.reduce(function (sum, p) { return sum + cents(p.amount); }, 0);
    var outstanding = Math.max(0, sales - received);
    $('totalSales').textContent = peso(toPesos(sales));
    $('salesNote').textContent = orders.length + ' order' + (orders.length === 1 ? '' : 's');
    $('paymentsReceived').textContent = peso(toPesos(received));
    $('outstandingBalance').textContent = peso(toPesos(outstanding));
    $('collectionRate').textContent = (sales ? (received / sales * 100) : 0).toFixed(1) + '%';
    $('collectionNote').textContent = peso(toPesos(received)) + ' of ' + peso(toPesos(sales)) + ' received';
  }

  function getFilteredPayments() {
    var query = $('paymentSearch').value.trim().toLowerCase();
    var filter = $('paymentFilter').value;
    return payments.filter(function (p) {
      var order = orderFor(p.orderId);
      var status = statusFor(p);
      if (filter === 'paid' && status !== 'Fully Paid') return false;
      if (filter === 'partial' && status !== 'Partial') return false;
      if (!query) return true;
      return [p.id, p.orderId, order ? order.customer : '', p.method, p.reference]
        .some(function (value) { return String(value).toLowerCase().includes(query); });
    }).slice().reverse();
  }

  function renderTransactions() {
    var filtered = getFilteredPayments();
    var pages = Math.max(1, Math.ceil(filtered.length / pageSize));
    page = Math.max(1, Math.min(pages, page));
    var from = (page - 1) * pageSize;
    var shown = filtered.slice(from, from + pageSize);
    $('paymentRows').innerHTML = shown.map(function (p) {
      var order = orderFor(p.orderId) || { customer: 'Unknown', total: 0 };
      var status = statusFor(p);
      var active = p.id === selectedSlip ? ' pay-selected-row' : '';
      return '<tr class="' + active.trim() + '">' +
        '<td class="strong">' + safe(p.id) + '</td>' +
        '<td>' + safe(p.orderId) + '</td>' +
        '<td>' + safe(order.customer) + '</td>' +
        '<td>' + peso(order.total) + '</td>' +
        '<td>' + peso(p.amount) + '</td>' +
        '<td>' + peso(p.balanceAfter) + '</td>' +
        '<td>' + safe(p.method === 'Bank Transfer' ? 'Bank' : p.method) + '</td>' +
        '<td>' + safe(dateText(p.date)) + '</td>' +
        '<td><span class="badge ' + (status === 'Fully Paid' ? 'badge-green' : 'badge-pink') + '">' + status + '</span></td>' +
        '<td><button type="button" class="btn btn-outline btn-sm pay-view-btn" data-slip="' + safe(p.id) + '">View Slip</button></td>' +
        '</tr>';
    }).join('');
    $('paymentEmpty').hidden = filtered.length !== 0;
    $('paymentEmpty').textContent = payments.length ?
      'No transactions match your search or filters.' : 'No payment records yet.';
    $('paymentCount').textContent = filtered.length
      ? 'Showing ' + (from + 1) + '–' + Math.min(from + pageSize, filtered.length) +
        ' of ' + filtered.length + ' payments · All amounts in PHP'
      : 'Showing 0 payments · All amounts in PHP';

    var nav = $('paymentPagination');
    nav.innerHTML = '';
    if (pages <= 1) return;
    function control(label, targetPage, disabled, current) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = label;
      btn.disabled = disabled;
      if (current) btn.setAttribute('aria-current', 'page');
      btn.addEventListener('click', function () {
        page = targetPage;
        renderTransactions();
      });
      nav.appendChild(btn);
    }
    control('‹ Previous', page - 1, page === 1, false);
    for (var i = 1; i <= pages; i++) control(String(i), i, false, page === i);
    control('Next ›', page + 1, page === pages, false);
  }

  function renderOrderChoices() {
    var select = $('paymentOrder');
    var previous = select.value;
    select.innerHTML = '';
    var available = orders.filter(function (o) {
      return o.status !== 'Cancelled' && balanceCents(o) > 0;
    });
    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = available.length ? 'Select unpaid order' : 'No unpaid orders available';
    select.appendChild(placeholder);
    available.forEach(function (o) {
      var option = document.createElement('option');
      option.value = o.id;
      option.textContent = o.id + ' · ' + o.customer + ' (balance ' + peso(toPesos(balanceCents(o))) + ')';
      select.appendChild(option);
    });
    if (available.some(function (o) { return o.id === previous; })) select.value = previous;
    updateBalanceHint();
    $('savePaymentBtn').disabled = available.length === 0;
    $('recordPaymentShortcut').disabled = available.length === 0;
  }

  function updateBalanceHint() {
    var order = orderFor($('paymentOrder').value);
    var left = order ? balanceCents(order) : 0;
    $('balanceHint').textContent = order
      ? 'Remaining balance: ' + peso(toPesos(left))
      : 'Select an unpaid order to see its balance.';
    $('paymentDate').min = order ? order.date : '';
    $('paymentAmount').max = order ? String(toPesos(left)) : '';
  }

  function renderSlip() {
    var p = payments.filter(function (entry) { return entry.id === selectedSlip; })[0];
    $('slipEmpty').hidden = Boolean(p);
    $('slipDetails').hidden = !p;
    $('printSlip').disabled = !p;
    if (!p) {
      $('slipHeading').textContent = 'Payment Slip';
      return;
    }
    var order = orderFor(p.orderId) || { customer: 'Unknown', item: '', total: 0 };
    $('slipHeading').textContent = 'Payment Slip · ' + p.id;
    $('slipCustomer').textContent = order.customer;
    $('slipOrder').textContent = p.orderId + (order.item ? ' · ' + order.item + ' ×1' : '');
    $('slipDate').textContent = dateText(p.date) + ' · ' + p.method;
    $('slipReference').textContent = 'Ref: ' + (p.reference || 'N/A');
    $('slipTotal').textContent = peso(order.total);
    $('slipPaid').textContent = peso(p.amount);
    $('slipBalance').textContent = peso(p.balanceAfter);
    $('slipNote').textContent = statusFor(p) === 'Fully Paid'
      ? '✓ Fully paid · Thank you for supporting handmade.'
      : 'ⓘ Partial payment · Remaining balance: ' + peso(p.balanceAfter);
  }

  function showSlip(id) {
    selectedSlip = id;
    renderSlip();
    renderTransactions();
    if (window.matchMedia('(max-width: 740px)').matches) {
      $('slipCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function updateReferenceRequired() {
    var isCash = $('paymentMethod').value === 'Cash';
    $('referenceRequired').hidden = isCash;
    $('paymentReference').required = !isCash;
    $('paymentReference').placeholder = isCash ? 'Optional cash receipt reference' : 'GCash / bank reference';
  }

  function showError(message) {
    $('paymentFormError').textContent = message;
    $('paymentFormError').hidden = false;
  }

  function validatePayment() {
    var order = orderFor($('paymentOrder').value);
    var amountStr = $('paymentAmount').value.trim();
    var number = Number(amountStr);
    var method = $('paymentMethod').value;
    var date = $('paymentDate').value;
    var ref = $('paymentReference').value.trim();

    if (!order) return { error: 'Please select an unpaid order.' };
    if (!amountStr || !Number.isFinite(number) || number < 0.01 ||
        Math.abs(number * 100 - Math.round(number * 100)) > 0.00001) {
      return { error: 'Enter a positive amount with at most two decimal places.' };
    }
    var amount = cents(number);
    if (amount > balanceCents(order)) {
      return { error: 'Payment is greater than the remaining balance of ' + peso(toPesos(balanceCents(order))) + '.' };
    }
    if (!['Cash', 'GCash', 'Bank Transfer'].includes(method)) {
      return { error: 'Choose a valid payment method.' };
    }
    if (!validLocalDate(date) || date > todayLocal()) {
      return { error: 'Choose a valid payment date that is not in the future.' };
    }
    if (method !== 'Cash' && !ref) return { error: 'Enter the GCash or bank reference number.' };
    if (ref.length > 80) return { error: 'Reference is limited to 80 characters.' };
    if (ref && payments.some(function (p) {
      return p.method === method && String(p.reference || '').toLowerCase() === ref.toLowerCase();
    })) return { error: 'That reference already exists for this payment method.' };
    return { order: order, amount: toPesos(amount), method: method, date: date, reference: ref };
  }

  function nextSlipId() {
    var maxId = state.payments.reduce(function (max, p) {
      var match = /^SLP-(\d+)$/.exec(p.id);
      return match ? Math.max(max, Number(match[1])) : max;
    }, 0);
    return 'SLP-' + String(maxId + 1).padStart(3, '0');
  }

  function submitPayment(event) {
    event.preventDefault();
    if (busy) return;
    $('paymentFormError').hidden = true;
    var data = validatePayment();
    if (data.error) {
      showError(data.error);
      return;
    }
    busy = true;
    $('savePaymentBtn').disabled = true;
    try {
      var balanceAfter = toPesos(balanceCents(data.order) - cents(data.amount));
      var id = nextSlipId();
      var entry = {
        id: id, orderId: data.order.id, amount: data.amount,
        method: data.method, date: data.date,
        reference: data.reference || 'CASH-' + id,
        balanceAfter: balanceAfter
      };
      state.payments.push(entry);
      if (!store.save(state)) {
        state.payments.pop();
        showError('Could not save the payment.');
        return;
      }
      payments.push(entry);
      var stored = true;
      try {
        sessionStorage.setItem(storageKey, JSON.stringify(added));
      } catch (storageError) {
        stored = false;
      }
      syncOrderPayment(entry.orderId, stored);
      $('recordPaymentForm').reset();
      $('paymentDate').value = todayLocal();
      $('paymentMethod').value = 'GCash';
      updateReferenceRequired();
      $('paymentSearch').value = '';
      $('paymentFilter').value = 'all';
      page = 1;
      selectedSlip = id;
      renderSummary();
      renderOrderChoices();
      renderTransactions();
      renderSlip();
      notice('Payment recorded.');
    } finally {
      busy = false;
      $('savePaymentBtn').disabled = orders.every(function (o) { return balanceCents(o) === 0; });
    }
  }

  function init() {
    if (!demo) {
      seed = [];
      payments = [];
    } else {
      loadDemoOrders();
      loadDemoPayments();
    }
    $('paymentDate').max = todayLocal();
    $('paymentDate').value = todayLocal();
    $('paymentSearch').addEventListener('input', function () { page = 1; renderTransactions(); });
    $('paymentFilter').addEventListener('change', function () { page = 1; renderTransactions(); });
    $('paymentRows').addEventListener('click', function (event) {
      var btn = event.target.closest('[data-slip]');
      if (btn) showSlip(btn.getAttribute('data-slip'));
    });
    $('paymentOrder').addEventListener('change', updateBalanceHint);
    $('paymentMethod').addEventListener('change', updateReferenceRequired);
    $('recordPaymentForm').addEventListener('submit', submitPayment);
    $('recordPaymentShortcut').addEventListener('click', function () {
      $('recordPaymentCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
      $('paymentOrder').focus();
    });
    $('printSlip').addEventListener('click', function () {
      if (selectedSlip) window.print();
    });
    renderSummary();
    renderOrderChoices();
    updateReferenceRequired();
    selectedSlip = payments.length ? payments[0].id : null;
    renderTransactions();
    renderSlip();
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'record') {
      const order = orderFor(params.get('order'));
      if (order && balanceCents(order) > 0) $('paymentOrder').value = order.id;
      updateBalanceHint();
      $('recordPaymentCard').scrollIntoView({ block: 'center' });
      $('paymentOrder').focus();
    }
  }
  init();
})();