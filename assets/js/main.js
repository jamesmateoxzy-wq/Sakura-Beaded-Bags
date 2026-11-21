/* =========================================================
   Sakura Beaded Bags - shared JavaScript (loaded on every page
   that uses includes/footer.php)

   Gives you:
   - profile dropdown + mobile sidebar
   - Sakura.openModal('id') / Sakura.closeModal('id')
   - Sakura.toast('message')
   - Sakura.peso(1200) -> "₱1,200.00"
   ========================================================= */
window.Sakura = (function () {
  // ----- money format -----
  function peso(n, decimals) {
    var d = decimals === undefined ? 2 : decimals;
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  // ----- toast -----
  function toast(message) {
    var wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    var el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = '<svg class="icon" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>';
    el.appendChild(document.createTextNode(message));
    wrap.appendChild(el);
    setTimeout(function () { el.remove(); }, 3200);
  }

  // ----- modals -----
  var openStack = [];
  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('is-open');
    m.classList.toggle('is-top', openStack.length > 0); // stacked modal sits above
    openStack.push(id);
        document.body.classList.add('modal-open');
    var first = m.querySelector('input:not([readonly]), select, textarea');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }
  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('is-open', 'is-top');
    openStack = openStack.filter(function (x) { return x !== id; });
        if (!openStack.length) document.body.classList.remove('modal-open');
  }

  document.addEventListener('click', function (e) {
    var openBtn = e.target.closest('[data-open-modal]');
    if (openBtn) { openModal(openBtn.getAttribute('data-open-modal')); return; }
    var closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn) { closeModal(closeBtn.getAttribute('data-close-modal')); return; }
    if (e.target.classList && e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openStack.length) closeModal(openStack[openStack.length - 1]);
  });

  // ----- profile dropdown -----
  var profileBtn = document.getElementById('profileBtn');
  var profileMenu = document.getElementById('profileMenu');
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = profileMenu.classList.toggle('is-open');
      profileBtn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', function () {
      profileMenu.classList.remove('is-open');
      profileBtn.setAttribute('aria-expanded', 'false');
    });
  }

  // ----- mobile sidebar -----
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var menuBtn = document.getElementById('menuBtn');
  function toggleSidebar(force) {
    if (!sidebar) return;
    var open = sidebar.classList.toggle('is-open', force);
    if (backdrop) backdrop.classList.toggle('is-open', open);
  }
  if (menuBtn) menuBtn.addEventListener('click', function () { toggleSidebar(); });
  if (backdrop) backdrop.addEventListener('click', function () { toggleSidebar(false); });

  return { peso: peso, toast: toast, openModal: openModal, closeModal: closeModal };
})();

(function clearLegacyDemoData() {
  var operationsPage = document.getElementById('operationsPage');
  var paymentData = window.SAKURA_PAYMENT_DATA;
  var isDemo = (operationsPage && operationsPage.dataset.demo === '1') ||
    (paymentData && paymentData.demo === true);
  if (!isDemo) return;

  var resetKey = 'sakura-demo-data-cleared-v2';
  try {
    if (sessionStorage.getItem(resetKey) === '1') return;
    sessionStorage.clear();
    sessionStorage.setItem(resetKey, '1');
  } catch (error) {
    console.error('Could not clear previous demo records from this browser session.', error);
    window.Sakura.toast('Could not clear previous demo records from this browser session.');
  }
})();
