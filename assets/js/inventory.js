/* Sakura Beaded Bags - Inventory Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'inventory') return;
  var $ = function (id) { return document.getElementById(id); };
  var store = window.SakuraStore;
  var data = store.read();
  var materials = data.materials;
  var history = data.inventoryHistory;

  function materialValue(list) {
    return list.reduce((sum, material) => sum + store.cents(material.stock * material.cost), 0) / 100;
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }
  function money(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function stockStatus(material) {
    return material.stock === 0 ? 'Out of Stock' : material.stock <= material.min ? 'Low Stock' : 'In Stock';
  }
  function badge(status) {
    var cls = status === 'In Stock' ? 'is-good' : status === 'Out of Stock' ? 'is-out' : 'is-low';
    return '<span class="inv-stock-badge ' + cls + '">' + esc(status) + '</span>';
  }
  function notify(message) {
    if (window.Sakura && Sakura.toast) Sakura.toast(message);
  }
  function openModal(id) { if (window.Sakura) Sakura.openModal(id); }
  function closeModal(id) { if (window.Sakura) Sakura.closeModal(id); }
  function showError(id, message) {
    var field = $(id);
    field.textContent = message;
    field.hidden = !message;
  }
  function formatDate(value) {
    var parsed = new Date(value + 'T00:00:00');
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('en-US', {month:'short', day:'numeric'});
  }
  var historyExpanded = false;

  function updateMaterialOptions(select, selectedId) {
    var currentId = selectedId || select.value;
    select.innerHTML = materials.map(function (material) {
      return '<option value="' + esc(material.id) + '">' + esc(material.name) + ' · ' + esc(material.id) + '</option>';
    }).join('');
    if (materials.some(function (material) { return material.id === currentId; })) select.value = currentId;
  }
  function updateHistory() {
    var rows = historyExpanded ? history : history.slice(0, 3);
    $('invHistoryRows').innerHTML = rows.map(function (entry) {
      var quantityClass = entry.delta >= 0 ? 'is-positive' : 'is-negative';
      var quantity = (entry.delta > 0 ? '+' : '−') + Math.abs(entry.delta) + ' ' + esc(entry.unit);
      return '<li><div class="inv-history-details"><strong>' + esc(entry.material) +
        '</strong><small>' + esc(formatDate(entry.date)) + ' · ' + esc(entry.activity) +
        '</small></div><span class="inv-history-quantity ' + quantityClass + '">' + quantity + '</span></li>';
    }).join('');
    $('invHistoryToggle').innerHTML = historyExpanded ? 'Show less <span aria-hidden="true">↑</span>' :
      'View all <span aria-hidden="true">→</span>';
  }
  function updateLowStockAlerts() {
    var low = materials.filter(function (material) { return material.stock <= material.min; });
    $('invLowAlerts').innerHTML = low.map(function (material) {
      return '<li>' + esc(material.name) + ' · ' + material.stock + ' / ' + material.min + ' ' + esc(material.unit) + '</li>';
    }).join('');
  }
  function render() {
    var query = $('invSearch').value.trim().toLowerCase();
    var shown = materials.filter(function (material) {
      return !query || (material.id + ' ' + material.name).toLowerCase().includes(query);
    });
    $('invTotal').textContent = materials.length;
    $('invOpenStockIn').disabled = !materials.length;
    $('invOpenCorrection').disabled = !materials.length;
    $('invLow').textContent = materials.filter(function (material) { return material.stock <= material.min; }).length;
    $('invValue').textContent = money(materialValue(materials));
    const month = store.today().slice(0, 7);
    $('invStockInCount').textContent = history.filter(entry => entry.activity === 'Stock-In' &&
      entry.date.startsWith(month)).length;
    $('invRows').innerHTML = shown.map(function (material) {
      return '<tr><td>' + esc(material.id) + '</td><td>' + esc(material.name) + '</td><td>' +
        esc(material.unit) + '</td><td>' + money(material.cost) + '</td><td>' + material.stock +
        '</td><td>' + material.min + '</td><td>' + badge(stockStatus(material)) + '</td></tr>';
    }).join('');
    $('invEmpty').hidden = shown.length > 0;
    updateMaterialOptions($('invStockInMaterial'), $('invStockInMaterial').value);
    updateMaterialOptions($('invAdjustId'), $('invAdjustId').value);
    updateHistory();
    updateLowStockAlerts();
  }
  function getMaterial(id) {
    return materials.find(function (material) { return material.id === id; });
  }
  function updateStockInSummary() {
    var material = getMaterial($('invStockInMaterial').value);
    if (!material) return;
    var quantity = Number($('invStockInQty').value);
    var cost = Number($('invStockInCost').value);
    var current = Number(material.stock);
    var newBalance = Math.round((current + (Number.isFinite(quantity) ? quantity : 0)) * 1000) / 1000;
    var receivedValue = (Number.isFinite(quantity) ? quantity : 0) * (Number.isFinite(cost) ? cost : 0);
    $('invCurrentBalance').textContent = current + ' ' + material.unit;
    $('invStockInUnit').textContent = material.unit;
    $('invStockInMath').textContent = current + ' ' + material.unit + ' + ' +
      (Number.isFinite(quantity) ? quantity : 0) + ' ' + material.unit + ' received';
    $('invStockInNewBalance').textContent = 'New balance: ' + newBalance + ' ' + material.unit;
    $('invStockInValue').textContent = 'Value: ' + money(receivedValue);
  }
  function updateCorrectionBalance() {
    var material = getMaterial($('invAdjustId').value);
    $('invAdjustBalance').textContent = material ? material.stock + ' ' + material.unit : '—';
  }
  function openStockIn() {
    if (!materials.length) { notify('Register a material in Product Costing & Pricing first.'); return; }
    $('invStockInForm').reset();
    showError('invStockInError', '');
    updateMaterialOptions($('invStockInMaterial'), materials[0] && materials[0].id);
    var material = materials[0];
    $('invStockInQty').value = '';
    $('invStockInCost').value = material ? Number(material.cost).toFixed(2) : '0.00';
    $('invStockInDate').value = store.today();
    $('invStockInDate').max = store.today();
    updateStockInSummary();
    openModal('invStockInModal');
  }
  function openCorrection() {
    if (!materials.length) { notify('Register a material in Product Costing & Pricing first.'); return; }
    $('invAdjustForm').reset();
    showError('invAdjustError', '');
    updateMaterialOptions($('invAdjustId'), materials[0] && materials[0].id);
    updateCorrectionBalance();
    openModal('invAdjustModal');
  }

  $('invSearch').addEventListener('input', render);
  $('invHistoryToggle').addEventListener('click', function () {
    if (history.length <= 3) {
      notify('All available inventory history is shown.');
      return;
    }
    historyExpanded = !historyExpanded;
    updateHistory();
  });
  $('invOpenStockIn').addEventListener('click', openStockIn);
  $('invOpenCorrection').addEventListener('click', openCorrection);
  $('invStockInMaterial').addEventListener('change', function () {
    var material = getMaterial(this.value);
    if (material) $('invStockInCost').value = Number(material.cost).toFixed(2);
    updateStockInSummary();
  });
  $('invStockInQty').addEventListener('input', updateStockInSummary);
  $('invStockInModal').addEventListener('click', function (event) {
    var button = event.target.closest('[data-focus]');
    if (button) $(button.getAttribute('data-focus')).focus();
  });
  $('invStockInForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var material = getMaterial($('invStockInMaterial').value);
    var quantity = Number($('invStockInQty').value);
    var date = $('invStockInDate').value;
    if (!material || !Number.isFinite(quantity) || quantity < 0.001 ||
        !store.validDate(date) || date > store.today() || material.stock + quantity > 1000000) {
      showError('invStockInError', 'Enter a valid material, quantity and date. Stock cannot exceed 1,000,000.');
      return;
    }
    var oldStock = material.stock;
    store.movement(data, material, 'Stock-In', quantity, date);
    if (!store.save(data)) {
      material.stock = oldStock;
      history.shift();
      showError('invStockInError', 'Could not save this Stock-In. Your changes were discarded.');
      return;
    }
    closeModal('invStockInModal');
    render();
    notify('Stock-In saved.');
  });
  $('invAdjustId').addEventListener('change', updateCorrectionBalance);
  $('invAdjustForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var material = getMaterial($('invAdjustId').value);
    var quantity = Number($('invAdjustQty').value);
    var direction = $('invDirection').value;
    var delta = direction === 'in' ? quantity : -quantity;
    if (!material || !Number.isFinite(quantity) || quantity < 0.001 ||
        Math.round((material.stock + delta) * 1000) < 0 || material.stock + delta > 1000000) {
      showError('invAdjustError', 'Enter a valid quantity. Stock cannot become negative or exceed 1,000,000.');
      return;
    }
    var oldStock = material.stock;
    store.movement(data, material, 'Correction', delta);
    if (!store.save(data)) {
      material.stock = oldStock;
      history.shift();
      showError('invAdjustError', 'Could not save this correction. Your changes were discarded.');
      return;
    }
    closeModal('invAdjustModal');
    render();
    notify('Stock correction saved.');
  });
  render();
  if (new URLSearchParams(window.location.search).get('action') === 'stockin') openStockIn();
})();