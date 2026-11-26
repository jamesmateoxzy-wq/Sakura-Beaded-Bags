<div class="modal-overlay" id="refundPaymentModal" role="dialog" aria-modal="true" aria-labelledby="refundHeading">
  <section class="modal modal-lg pay-form-card pay-refund-card" aria-labelledby="refundHeading">
    <div class="modal-head">
      <h2 id="refundHeading">Refund Payment</h2>
      <button type="button" class="icon-btn" data-close-modal="refundPaymentModal" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form id="refundPaymentForm" class="modal-body" novalidate>
      <p class="pay-refund-intro">Choose the refund percentage. The refund is calculated from this payment's original amount.</p>
      <div class="pay-refund-summary">
        <div><span>Payment</span><strong id="refundPaymentCode">—</strong></div>
        <div><span>Customer · Order</span><strong id="refundCustomerOrder">—</strong></div>
        <div><span>Original payment</span><strong id="refundOriginalAmount">₱0.00</strong></div>
        <div><span>Already refunded</span><strong id="refundAlreadyAmount">₱0.00</strong></div>
      </div>
      <div class="field pay-refund-percent-field">
        <label for="refundPercent">Refund percentage <span class="req">*</span></label>
        <div class="pay-refund-percent-input">
          <input class="input" id="refundPercent" name="refundPercent" type="number" min="0.01" max="100" step="0.01" value="100" required inputmode="decimal">
          <span>%</span>
        </div>
        <small>Maximum refundable balance: <strong id="refundRemainingAmount">₱0.00</strong></small>
      </div>
      <div class="pay-refund-preview" aria-live="polite">
        <span>Refund amount</span><strong id="refundPreviewAmount">₱0.00</strong>
      </div>
      <p id="refundFormError" class="pay-error" role="alert" hidden></p>
      <div class="pay-form-actions">
        <button type="button" class="btn btn-outline" data-close-modal="refundPaymentModal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitRefundBtn">Confirm Refund</button>
      </div>
    </form>
  </section>
</div>
<style>
  .pay-refund-card{max-width:560px}
  .pay-refund-intro{margin:0 0 14px;color:var(--muted);font-size:13px}
  .pay-refund-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px;border-radius:12px;background:var(--pink-50)}
  .pay-refund-summary div{min-width:0}
  .pay-refund-summary span,.pay-refund-percent-field small{display:block;color:var(--muted);font-size:12px}
  .pay-refund-summary strong{display:block;margin-top:3px;overflow-wrap:anywhere;font-size:13px;font-weight:600}
  .pay-refund-percent-field{margin:16px 0 10px}
  .pay-refund-percent-input{position:relative}
  .pay-refund-percent-input input{padding-right:44px}
  .pay-refund-percent-input>span{position:absolute;top:50%;right:14px;transform:translateY(-50%);color:var(--muted)}
  .pay-refund-percent-field small{margin-top:5px}
  .pay-refund-percent-field small strong{color:var(--ink);font-weight:600}
  .pay-refund-preview{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;border:1px solid var(--line);border-radius:10px}
  .pay-refund-preview span{color:var(--muted);font-size:13px}
  .pay-refund-preview strong{color:var(--pink-600);font-size:20px;font-weight:600}
  #refundPaymentModal .pay-form-actions{justify-content:flex-end}
  #refundPaymentModal .pay-form-actions .btn{min-width:112px}
  @media(max-width:560px){.pay-refund-summary{gap:9px;padding:12px}.pay-refund-summary strong{font-size:12px}}
</style>
<script>
(function () {
  'use strict';
  const $ = id => document.getElementById(id);
  const form = $('refundPaymentForm');
  if (!form) return;
  const money = cents => '₱' + (cents / 100).toLocaleString('en-PH', {
    minimumFractionDigits:2, maximumFractionDigits:2
  });
  let csrf = '';
  let payment = null;
  let requestKey = '';
  let busy = false;

  function setError(message) {
    $('refundFormError').textContent = message || '';
    $('refundFormError').hidden = !message;
  }
  function percentHundredths() {
    const value = $('refundPercent').value.trim();
    if (!/^(?:\d{1,3})(?:\.\d{1,2})?$/.test(value)) return null;
    const percent = Math.round(Number(value) * 100);
    return percent >= 1 && percent <= 10000 ? percent : null;
  }
  function refundCents() {
    const percent = percentHundredths();
    const original = payment ? Math.round(Number(payment.originalAmount || payment.amount || 0) * 100) : 0;
    return percent === null ? 0 : Math.round(original * percent / 10000);
  }
  function renderPreview() {
    const amount = refundCents();
    $('refundPreviewAmount').textContent = money(amount);
    const remaining = payment ? Math.max(0, Math.round(
      (Number(payment.originalAmount || payment.amount || 0) - Number(payment.refundedAmount || 0)) * 100
    )) : 0;
    const submit = $('submitRefundBtn');
    submit.disabled = busy || !csrf || !payment || amount < 1 || amount > remaining;
  }
  function makeRequestKey() {
    if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') return '';
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' +
      hex.slice(16, 20) + '-' + hex.slice(20);
  }

  window.addEventListener('sbb:csrf-token', event => {
    csrf = event.detail || '';
    renderPreview();
  });
  window.SakuraRefund = {
    open(record) {
      if (!record || record.paymentStatus !== 'Successful') return;
      payment = record;
      requestKey = '';
      setError('');
      $('refundPaymentCode').textContent = record.id || '—';
      $('refundCustomerOrder').textContent = (record.customer || 'Customer') + ' · ' + (record.orderId || 'Order');
      const original = Math.round(Number(record.originalAmount || record.amount || 0) * 100);
      const refunded = Math.min(original, Math.round(Number(record.refundedAmount || 0) * 100));
      const remaining = Math.max(0, original - refunded);
      $('refundOriginalAmount').textContent = money(original);
      $('refundAlreadyAmount').textContent = money(refunded);
      $('refundRemainingAmount').textContent = money(remaining);
      const maxPercent = original ? Math.floor(remaining * 10000 / original) / 100 : 0;
      $('refundPercent').value = String(Math.min(100, maxPercent));
      $('refundPercent').max = String(maxPercent);
      renderPreview();
      Sakura.openModal('refundPaymentModal');
      $('refundPercent').focus();
    }
  };

  $('refundPercent').addEventListener('input', () => {
    setError('');
    renderPreview();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    const percent = percentHundredths();
    const amount = refundCents();
    const original = payment ? Math.round(Number(payment.originalAmount || payment.amount || 0) * 100) : 0;
    const refunded = payment ? Math.round(Number(payment.refundedAmount || 0) * 100) : 0;
    if (!payment || !percent) return setError('Enter a refund percentage from 0.01% to 100%.');
    if (!csrf) return setError('Security token is not ready. Refresh the page and try again.');
    if (amount < 1) return setError('The selected percentage is less than the minimum refund of ₱0.01.');
    if (amount > original - refunded) return setError('Refund exceeds the remaining refundable balance of ' + money(original - refunded) + '.');
    if (!requestKey) requestKey = makeRequestKey();
    if (!requestKey) return setError('This browser cannot create a secure refund request. Update the browser and retry.');

    busy = true;
    $('submitRefundBtn').disabled = true;
    setError('');
    try {
      const response = await fetch('api/payments.php', {
        method:'POST',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-Token':csrf},
        body:JSON.stringify({
          action:'record_refund',
          paymentId:payment.rawId,
          refundPercent:(percent / 100).toFixed(2),
          requestKey
        })
      });
      let data;
      try { data = await response.json(); } catch (_) { throw new Error('Server did not return JSON. Check the PHP server terminal.'); }
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not save this refund.');
      requestKey = '';
      Sakura.closeModal('refundPaymentModal');
      if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast(data.message || 'Refund recorded successfully.');
      window.dispatchEvent(new Event('sbb:payments-refresh'));
    } catch (error) {
      setError(error.message || 'Could not save this refund.');
      if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast('Refund could not be recorded.');
    } finally {
      busy = false;
      renderPreview();
    }
  });
})();
</script>