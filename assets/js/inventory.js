/* Sakura Beaded Bags - Inventory Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'inventory') return;
  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var storageUnavailable = false;
  var storageFailureReported = false;
  var samples = [
    { id:'MAT-001', name:'Pink Beads', unit:'pcs', cost:2, stock:10, min:50 },
    { id:'MAT-002', name:'Nylon Thread', unit:'roll', cost:40, stock:3, min:5 },
    { id:'MAT-003', name:'Bag Handles', unit:'pcs', cost:55, stock:5, min:10 },
    { id:'MAT-004', name:'Rose Crystal Beads', unit:'pcs', cost:2, stock:600, min:100 },
    { id:'MAT-005', name:'Ivory Pearl Beads', unit:'pcs', cost:3, stock:450, min:100 },
    { id:'MAT-006', name:'Clasps', unit:'pcs', cost:30, stock:4, min:10 },
    { id:'MAT-007', name:'Pink Ribbon', unit:'roll', cost:25, stock:2, min:5 }
  ];
  var sampleHistory = [
    { material:'Rose Crystal Beads', date:'2026-10-28', activity:'Stock-In', delta:200, unit:'pcs' },
    { material:'Rose Crystal Beads', date:'2026-10-28', activity:'ORD-002', delta:-100, unit:'pcs' },
    { material:'Bag Handles', date:'2026-10-27', activity:'Correction', delta:-2, unit:'pcs' }
  ];

  function clone(value) { return JSON.parse(JSON.stringify(value)); }
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
    if (!demo) return { materials:[], history:[], stockInCount:0, valueOffset:0 };
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || '{}');
      if (!stored || typeof stored !== 'object' || Array.isArray(stored)) stored = {};
      var materials = Array.isArray(stored.materials) ? stored.materials : clone(samples);
      return {
        materials:materials,
        history:Array.isArray(stored.inventoryHistory) ? stored.inventoryHistory : clone(sampleHistory),
        stockInCount:Number.isInteger(stored.inventoryStockInCount) ? stored.inventoryStockInCount : 12,
        valueOffset:Number.isFinite(stored.inventoryValueOffset) ? stored.inventoryValueOffset : 8450 - materialValue(materials)
      };
    } catch (error) {
      storageUnavailable = true;
      reportStorageFailure();
      return {
        materials:clone(samples),
        history:clone(sampleHistory),
        stockInCount:12,
        valueOffset:8450 - materialValue(samples)
      };
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
      stored.inventoryValueOffset = valueOffset;
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
  function moneyWhole(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {maximumFractionDigits:0});
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
    var parsed = new Date(value);
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
  var valueOffset = loaded.valueOffset;
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
    $('invTotal').textContent = demo ? '24' : materials.length;
    $('invLow').textContent = materials.filter(function (material) { return material.stock <= material.min; }).length;
    $('invValue').textContent = moneyWhole(materialValue(materials) + valueOffset);
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
    var newBalance = current + (Number.isFinite(quantity) ? quantity : 0);
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
    if (!demo) return;
    $('invStockInForm').reset();
    showError('invStockInError', '');
    updateMaterialOptions($('invStockInMaterial'), materials[0] && materials[0].id);
    var material = materials[0];
    $('invStockInQty').value = '200';
    $('invStockInCost').value = material ? Number(material.cost).toFixed(2) : '0.00';
    $('invStockInDate').value = 'Oct 28, 2026';
    updateStockInSummary();
    openModal('invStockInModal');
  }
  function openCorrection() {
    if (!demo) return;
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
  $('invStockInCost').addEventListener('input', updateStockInSummary);
  $('invStockInModal').addEventListener('click', function (event) {
    var button = event.target.closest('[data-focus]');
    if (button) $(button.getAttribute('data-focus')).focus();
  });
  $('invStockInForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var material = getMaterial($('invStockInMaterial').value);
    var quantity = Number($('invStockInQty').value);
    var cost = Number($('invStockInCost').value);
    var date = inputDate($('invStockInDate').value);
    if (!material || !Number.isInteger(quantity) || quantity < 1 || !Number.isFinite(cost) ||
        cost < 0 || cost > 1000000 || !date || material.stock + quantity > 1000000) {
      showError('invStockInError', 'Enter a valid material, quantity, unit cost and date. Stock cannot exceed 1,000,000.');
      return;
    }
    var oldStock = material.stock;
    var oldCost = material.cost;
    var oldCount = stockInCount;
    material.stock += quantity;
    material.cost = cost;
    stockInCount += 1;
    appendHistory(material, 'Stock-In', quantity, date);
    if (!save()) {
      material.stock = oldStock;
      material.cost = oldCost;
      stockInCount = oldCount;
      history.shift();
      showError('invStockInError', 'Could not save this Stock-In. Your changes were discarded.');
      return;
    }
    closeModal('invStockInModal');
    render();
    notify('Stock-In saved to this demo session.');
  });
  $('invAdjustId').addEventListener('change', updateCorrectionBalance);
  $('invAdjustForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var material = getMaterial($('invAdjustId').value);
    var quantity = Number($('invAdjustQty').value);
    var direction = $('invDirection').value;
    var delta = direction === 'in' ? quantity : -quantity;
    if (!material || !Number.isInteger(quantity) || quantity < 1 ||
        material.stock + delta < 0 || material.stock + delta > 1000000) {
      showError('invAdjustError', 'Enter a valid quantity. Stock cannot become negative or exceed 1,000,000.');
      return;
    }
    var oldStock = material.stock;
    material.stock += delta;
    appendHistory(material, 'Correction', delta, new Date().toISOString().slice(0, 10));
    if (!save()) {
      material.stock = oldStock;
      history.shift();
      showError('invAdjustError', 'Could not save this correction. Your changes were discarded.');
      return;
    }
    closeModal('invAdjustModal');
    render();
    notify('Stock correction saved to this demo session.');
  });
  render();
  if (demo && new URLSearchParams(window.location.search).get('action') === 'stockin') openStockIn();
})();
