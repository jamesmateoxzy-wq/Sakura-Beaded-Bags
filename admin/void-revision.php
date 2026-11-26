<div class="modal-overlay" id="voidPaymentModal" role="dialog" aria-modal="true" aria-labelledby="voidHeading">
  <section class="modal pay-form-card pay-void-card" aria-labelledby="voidHeading">
    <div class="modal-head">
      <h2 id="voidHeading">Void Payment</h2>
      <button type="button" class="icon-btn" data-close-modal="voidPaymentModal" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form id="voidPaymentForm" class="modal-body" novalidate>
      <div class="pay-void-alert"><?= icon('alert') ?><span>This will remove the remaining payment amount from sales and recalculate the order balance.</span></div>
      <div class="pay-void-summary">
        <div><span>Payment</span><strong id="voidPaymentCode">—</strong></div>
        <div><span>Customer · Order</span><strong id="voidCustomerOrder">—</strong></div>
        <div><span>Amount removed from sales</span><strong id="voidNetAmount">₱0.00</strong></div>
        <div><span>Already refunded</span><strong id="voidRefundedAmount">₱0.00</strong></div>
      </div>
      <p class="pay-void-note">Any refund already recorded will remain in the payment history.</p>
      <p id="voidFormError" class="pay-error" role="alert" hidden></p>
      <div class="pay-form-actions">
        <button type="button" class="btn btn-outline" data-close-modal="voidPaymentModal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="confirmVoidBtn">Confirm Void</button>
      </div>
    </form>
  </section>
</div>
<style>
  .pay-void-card{max-width:560px}
  .pay-void-card .modal-head{align-items:center}
  .pay-void-card .modal-head h2{color:var(--ink)}
  .pay-void-alert{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;padding:12px 14px;border:1px solid #efd4b6;border-radius:10px;background:#fffaf0;color:#775b41;font-size:13px}
  .pay-void-alert .icon{width:17px;height:17px;margin-top:1px;color:#b47a3c}
  .pay-void-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px;border-radius:12px;background:var(--pink-50)}
  .pay-void-summary div{min-width:0}
  .pay-void-summary span{display:block;color:var(--muted);font-size:12px}
  .pay-void-summary strong{display:block;margin-top:3px;overflow-wrap:anywhere;font-size:13px;font-weight:600}
  .pay-void-note{margin-top:11px;color:var(--muted);font-size:12px}
  #voidPaymentModal .pay-form-actions{justify-content:flex-end}
  #voidPaymentModal .pay-form-actions .btn{min-width:112px}
  #confirmVoidBtn:disabled{opacity:.54;cursor:not-allowed}
  @media(max-width:560px){.pay-void-summary{gap:9px;padding:12px}.pay-void-summary strong{font-size:12px}}
</style>
<script>
(function () {
  'use strict';
  const $ = id => document.getElementById(id);
  const form = $('voidPaymentForm');
  if (!form) return;
  const money = cents => '₱' + (cents / 100).toLocaleString('en-PH', {
    minimumFractionDigits:2, maximumFractionDigits:2
  });
  let csrf = '';
  let payment = null;
  let busy = false;

  function setError(message) {
    $('voidFormError').textContent = message || '';
    $('voidFormError').hidden = !message;
  }
  window.addEventListener('sbb:csrf-token', event => {
    csrf = event.detail || '';
    $('confirmVoidBtn').disabled = busy || !csrf || !payment;
  });
  window.SakuraVoid = {
    open(record) {
      if (!record || record.paymentStatus !== 'Successful') return;
      payment = record;
      setError('');
      $('voidPaymentCode').textContent = record.id || '—';
      $('voidCustomerOrder').textContent = (record.customer || 'Customer') + ' · ' + (record.orderId || 'Order');
      $('voidNetAmount').textContent = money(Math.round(Number(record.amount || 0) * 100));
      $('voidRefundedAmount').textContent = money(Math.round(Number(record.refundedAmount || 0) * 100));
      $('confirmVoidBtn').disabled = busy || !csrf;
      Sakura.openModal('voidPaymentModal');
    }
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    if (!payment || !csrf) {
      setError('Security token is not ready. Refresh the page and try again.');
      return;
    }
    busy = true;
    $('confirmVoidBtn').disabled = true;
    setError('');
    try {
      const response = await fetch('api/payments.php', {
        method:'POST',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-Token':csrf},
        body:JSON.stringify({
          action:'update_payment_status',
          paymentId:payment.rawId,
          status:'Cancelled'
        })
      });
      let data;
      try { data = await response.json(); } catch (_) { throw new Error('Server did not return JSON. Check the PHP server terminal.'); }
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not void this payment.');
      Sakura.closeModal('voidPaymentModal');
      if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast(data.message || 'Payment voided successfully.');
      window.dispatchEvent(new Event('sbb:payments-refresh'));
    } catch (error) {
      setError(error.message || 'Could not void this payment.');
      if (window.Sakura && typeof Sakura.toast === 'function') Sakura.toast('Payment could not be voided.');
    } finally {
      busy = false;
      $('confirmVoidBtn').disabled = !csrf || !payment;
    }
  });
})();
</script>