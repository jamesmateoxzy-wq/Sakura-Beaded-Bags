/* Sakura Beaded Bags - Inventory Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'inventory') return;
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var storageUnavailable = false;
  var storageFailureReported = false;
  function materialValue(list) {
    return list.reduce(function (sum, material) {
      return sum + Number(material.stock || 0) * Number(material.cost || 0);
    }, 0);
  }
  function reportStorageFailure() {
    if (storageFailureReported) return;
    storageFailureReported = true;
    notify('Could not read or save demo inventory changes in this browser session.');
  }
  function load() {
    if (!demo) return { materials:[], history:[], stockInCount:0 };
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || '{}');
      if (!stored || typeof stored !== 'object' || Array.isArray(stored)) stored = {};
      return {
        materials:Array.isArray(stored.materials) ? stored.materials : [],
        history:Array.isArray(stored.inventoryHistory) ? stored.inventoryHistory : [],
        stockInCount:Number.isInteger(stored.inventoryStockInCount) ? stored.inventoryStockInCount : 0
      };
    } catch (error) {
      storageUnavailable = true;
      reportStorageFailure();
      return { materials:[], history:[], stockInCount:0 };
    }
  }
  function save() {
    if (!demo || storageUnavailable) return false;
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || '{}');
      if (!stored || typeof stored !== 'object' || Array.isArray(stored)) stored = {};
      stored.materials = materials;
      stored.inventoryHistory = history;
      stored.inventoryStockInCount = stockInCount;
      sessionStorage.setItem(key, JSON.stringify(stored));
      return true;
    } catch (error) {
      storageUnavailable = true;
      reportStorageFailure();
      return false;
    }
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
  function inputDate(value) {
    var parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '';
    return parsed.getFullYear() + '-' + String(parsed.getMonth() + 1).padStart(2, '0') + '-' +
      String(parsed.getDate()).padStart(2, '0');
  }
  function appendHistory(material, activity, delta, date) {
    history.unshift({
      material:material.name,
      date:date,
      activity:activity,
      delta:delta,
      unit:material.unit
    });
  }

  var loaded = load();
  var materials = loaded.materials;
  var history = loaded.history;
  var stockInCount = loaded.stockInCount;
  var historyExpanded = false;

  function updateMaterialOptions(select, selectedId) {
    var currentId = selectedId || select.value;
    select.innerHTML = materials.map(function (material) {
      return '<option value="' + esc(material.id) + '">' + esc(material.name) + ' · ' + esc(material.id) + '</option>';
    }).join('');
    if (!materials.length) {
      select.innerHTML = '<option value="">No registered materials available</option>';
      return;
    }
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
    $('invHistoryEmpty').hidden = history.length > 0;
    $('invHistoryRows').hidden = history.length === 0;
    $('invHistoryToggle').hidden = history.length <= 3;
    $('invHistoryToggle').innerHTML = historyExpanded ? 'Show less <span aria-hidden="true">↑</span>' :
      'View all <span aria-hidden="true">→</span>';
  }
  function updateLowStockAlerts() {
    var low = materials.filter(function (material) { return material.stock <= material.min; });
    $('invLowAlerts').innerHTML = low.map(function (material) {
      return '<li>' + esc(material.name) + ' · ' + material.stock + ' / ' + material.min + ' ' + esc(material.unit) + '</li>';
    }).join('');
    $('invAlertsEmpty').hidden = low.length > 0;
    $('invLowAlerts').hidden = low.length === 0;
  }
  function render() {
    var query = $('invSearch').value.trim().toLowerCase();
    var shown = materials.filter(function (material) {
      return !query || (material.id + ' ' + material.name).toLowerCase().includes(query);
    });
    $('invTotal').textContent = materials.length;
    $('invLow').textContent = materials.filter(function (material) { return material.stock <= material.min; }).length;
    $('invValue').textContent = moneyWhole(materialValue(materials));
    $('invStockInCount').textContent = stockInCount;
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
    $('invOpenStockIn').disabled = !demo;
    $('invOpenCorrection').disabled = !demo || materials.length === 0;
  }
  function getMaterial(id) {
    return materials.find(function (material) { return material.id === id; });
  }
  function updateStockInSummary() {
    var material = getMaterial($('invStockInMaterial').value);
    if (!material) {
      $('invCurrentBalance').textContent = '0';
      $('invStockInUnit').textContent = 'pcs';
      $('invStockInMath').textContent = 'No registered materials';
      $('invStockInNewBalance').textContent = 'New balance: —';
      $('invStockInValue').textContent = 'Value: ₱0.00';
      return;
    }
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
    $('invStockInQty').value = '1';
    $('invStockInCost').value = material ? Number(material.cost).toFixed(2) : '0.00';
    $('invStockInDate').value = new Date().toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
    updateStockInSummary();
    openModal('invStockInModal');
  }
  function openCorrection() {
    if (!demo) return;
    if (!materials.length) {
      notify('Add a material before recording a stock correction.');
      return;
    }
    $('invAdjustForm').reset();
    showError('invAdjustError', '');
    updateMaterialOptions($('invAdjustId'), materials[0] && materials[0].id);
    updateCorrectionBalance();
    openModal('invAdjustModal');
  }

  $('invSearch').addEventListener('input', render);
  $('invOpenCreate').addEventListener('click', function () {
    if (!demo) return;
    $('invCreateForm').reset();
    showError('invCreateError', '');
    openModal('invCreateModal');
  });
  $('invCreateForm').addEventListener('submit', function (event) {
    event.preventDefault();
    if (!demo) return;
    var name = $('invCreateName').value.trim();
    var unit = $('invCreateUnit').value;
    var cost = Number($('invCreateCost').value);
    var stock = Number($('invCreateStock').value);
    var min = Number($('invCreateMin').value);
    if (!name || name.length > 80 || materials.some(function (material) {
      return material.name.toLowerCase() === name.toLowerCase();
    }) || ['pcs','roll','pack','meter','set'].indexOf(unit) === -1 ||
        !Number.isFinite(cost) || cost < 0 || cost > 1000000 ||
        !Number.isInteger(stock) || stock < 0 || stock > 1000000 ||
        !Number.isInteger(min) || min < 0 || min > 1000000) {
      showError('invCreateError', 'Enter a unique material name, unit cost, quantity and reorder level within the allowed limits.');
      return;
    }
    var nextNumber = materials.reduce(function (max, material) {
      return Math.max(max, Number(String(material.id).replace(/^MAT-/, '')) || 0);
    }, 0) + 1;
    var material = {
      id:'MAT-' + String(nextNumber).padStart(3, '0'),
      name:name,
      unit:unit,
      cost:cost,
      stock:stock,
      min:min
    };
    materials.push(material);
    if (stock > 0) appendHistory(material, 'Opening balance', stock, new Date().toISOString().slice(0, 10));
    if (!save()) {
      materials.pop();
      if (stock > 0) history.shift();
      showError('invCreateError', 'Could not save this material. Your changes were discarded.');
      return;
    }
    closeModal('invCreateModal');
    render();
    notify(material.name + ' added to inventory.');
  });
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