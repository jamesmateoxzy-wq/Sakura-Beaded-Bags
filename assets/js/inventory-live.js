/* SBB Inventory live MySQL front-end (replaces inventory.js on admin/inventory.php).
   Keeps the existing HTML/CSS/modals. Requires admin/api/inventory.php. */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'inventory') return;
  var $ = function (id) { return document.getElementById(id); };
  var apiUrl = 'api/inventory.php';
  var csrf = '';
  var ready = false;
  var busy = false;
  var materials = [];
  var history = [];
  var stockInCount = 0;
  var expanded = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function money(v) { return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function moneyWhole(v) { return '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 }); }
  function notify(s) { if (window.Sakura && Sakura.toast) Sakura.toast(s); }
  function open(id) { if (window.Sakura) Sakura.openModal(id); }
  function close(id) { if (window.Sakura) Sakura.closeModal(id); }
  function err(id, msg) { $(id).textContent = msg || ''; $(id).hidden = !msg; }
  function status(m) { return Number(m.stock) === 0 ? 'Out of Stock' : Number(m.stock) <= Number(m.min) ? 'Low Stock' : 'In Stock'; }
  function badge(s) { return '<span class="inv-stock-badge ' + (s === 'In Stock' ? 'is-good' : s === 'Out of Stock' ? 'is-out' : 'is-low') + '">' + esc(s) + '</span>'; }
  function formattedDate(date) {
    var d = new Date(String(date) + 'T00:00:00');
    return Number.isNaN(d.getTime()) ? date : d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
  }
  function inputDate(value) {
    var d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }
  function toggleButtons() {
    $('invOpenCreate').disabled = !ready || busy;
    $('invOpenStockIn').disabled = !ready || busy || materials.length === 0;
    $('invOpenCorrection').disabled = !ready || busy || materials.length === 0;
  }
  async function request(body) {
    var opts = { credentials:'same-origin', cache:'no-store' };
    if (body) {
      opts.method = 'POST';
      opts.headers = { 'Content-Type':'application/json', 'X-CSRF-Token': csrf };
      opts.body = JSON.stringify(body);
    }
    var response = await fetch(apiUrl, opts);
    var data;
    try { data = await response.json(); }
    catch (e) { throw new Error('Unexpected server response. Check that the PHP server is running and sign in again if needed.'); }
    if (!response.ok || !data.ok) throw new Error(data.error || 'Server error ' + response.status);
    return data;
  }
  async function reload() {
    var data = await request();
    csrf = data.csrf || '';
    materials = Array.isArray(data.materials) ? data.materials : [];
    history = Array.isArray(data.history) ? data.history : [];
    stockInCount = Number(data.stockInCount || 0);
    ready = true;
    render();
  }
  async function write(formId, errorId, data, modalId, success) {
    if (busy) return;
    busy = true;
    toggleButtons();
    var submit = $(formId).querySelector('[type=submit]');
    if (submit) submit.disabled = true;
    err(errorId, '');
    try {
      await request(data);
      await reload();
      close(modalId);
      notify(success);
    } catch (e) {
      err(errorId, e.message);
      // A slow/rejected reload must not create duplicate submissions automatically.
    } finally {
      busy = false;
      toggleButtons();
      if (submit) submit.disabled = false;
    }
  }
  function material(id) { return materials.find(function(m) { return m.id === id; }); }
  function options(select, selected) {
    var prior = selected || select.value;
    select.innerHTML = materials.length ? materials.map(function(m) {
      return '<option value="' + esc(m.id) + '">' + esc(m.name) + ' · ' + esc(m.id) + '</option>';
    }).join('') : '<option value="">No registered materials available</option>';
    if (materials.some(function(m) { return m.id === prior; })) select.value = prior;
  }
  function render() {
    var q = $('invSearch').value.trim().toLowerCase();
    var shown = materials.filter(function(m) { return !q || (m.id + ' ' + m.name).toLowerCase().includes(q); });
    $('invTotal').textContent = materials.length;
    $('invValue').textContent = moneyWhole(materials.reduce(function(a,m) {return a + m.stock*m.cost;},0));
    $('invLow').textContent = materials.filter(function(m) {return m.stock <= m.min;}).length;
    $('invStockInCount').textContent = stockInCount;
    $('invRows').innerHTML = shown.map(function(m) {
      return '<tr><td>' + esc(m.id) + '</td><td>' + esc(m.name) + '</td><td>' + esc(m.unit) +
        '</td><td>' + money(m.cost) + '</td><td>' + esc(m.stock) + '</td><td>' + esc(m.min) +
        '</td><td>' + badge(status(m)) + '</td><td><button type="button" class="btn btn-outline btn-sm" data-remove-material="' +
        esc(m.id) + '"' + (busy ? ' disabled' : '') + '>Remove</button></td></tr>';
    }).join('');
    $('invEmpty').hidden = shown.length !== 0;
    options($('invStockInMaterial'));
    options($('invAdjustId'));
    var h = expanded ? history : history.slice(0,3);
    $('invHistoryRows').innerHTML = h.map(function(v) {
      var delta = Number(v.delta);
      return '<li><div class="inv-history-details"><strong>' + esc(v.material) +
        '</strong><small>' + esc(formattedDate(v.date)) + ' · ' + esc(v.activity) +
        '</small></div><span class="inv-history-quantity ' + (delta >= 0 ? 'is-positive' : 'is-negative') + '">' +
        (delta > 0 ? '+' : delta < 0 ? '−' : '') + esc(Math.abs(delta)) + ' ' + esc(v.unit) + '</span></li>';
    }).join('');
    $('invHistoryEmpty').hidden = history.length !== 0;
    $('invHistoryRows').hidden = history.length === 0;
    $('invHistoryToggle').hidden = history.length <= 3;
    $('invHistoryToggle').innerHTML = expanded ? 'Show less <span aria-hidden="true">↑</span>' : 'View all <span aria-hidden="true">→</span>';
    var low = materials.filter(function(m) { return m.stock <= m.min; });
    $('invLowAlerts').innerHTML = low.map(function(m) {return '<li>' + esc(m.name) + ' · ' + esc(m.stock) + ' / ' + esc(m.min) + ' ' + esc(m.unit) + '</li>';}).join('');
    $('invAlertsEmpty').hidden = low.length !== 0;
    $('invLowAlerts').hidden = low.length === 0;
    toggleButtons();
  }
  function updateStockInSummary() {
    var m = material($('invStockInMaterial').value);
    if (!m) {
      $('invCurrentBalance').textContent = '0';
      $('invStockInUnit').textContent = 'pcs';
      $('invStockInMath').textContent = 'No registered materials';
      $('invStockInNewBalance').textContent = 'New balance: —';
      $('invStockInValue').textContent = 'Value: ₱0.00';
      return;
    }
    var qty = Number($('invStockInQty').value);
    var cost = Number($('invStockInCost').value);
    $('invCurrentBalance').textContent = m.stock + ' ' + m.unit;
    $('invStockInUnit').textContent = m.unit;
    $('invStockInMath').textContent = m.stock + ' ' + m.unit + ' + ' + qty + ' ' + m.unit + ' received';
    $('invStockInNewBalance').textContent = 'New balance: ' + (m.stock + qty) + ' ' + m.unit;
    $('invStockInValue').textContent = 'Value: ' + money(qty*cost);
  }
  function updateCorrectionBalance() {
    var m = material($('invAdjustId').value);
    $('invAdjustBalance').textContent = m ? m.stock + ' ' + m.unit : '—';
  }
  $('invSearch').addEventListener('input',render);
  $('invRows').addEventListener('click',async function(e) {
    var button=e.target.closest('[data-remove-material]');
    if(!button||busy)return;
    var selected=material(button.dataset.removeMaterial);
    if(!selected)return;
    if(!window.confirm('Remove '+selected.name+' from active inventory? Product recipes, order history and inventory movements will be preserved.'))return;
    busy=true;toggleButtons();
    var removed=false;
    try {
      var result=await request({action:'remove_material',id:selected.id});
      removed=true;
      await reload();
      notify(result.message||'Material removed from active inventory.');
    } catch(error) {
      notify(removed?'Material was removed, but the list could not refresh: '+error.message:'Could not remove material: '+error.message);
    } finally {
      busy=false;render();toggleButtons();
    }
  });
  $('invHistoryToggle').addEventListener('click',function() {expanded=!expanded; render();});
  $('invOpenCreate').addEventListener('click',function() {if(!ready)return; $('invCreateForm').reset(); err('invCreateError',''); open('invCreateModal');});
  $('invCreateForm').addEventListener('submit',function(e) {
    e.preventDefault();
    var name=$('invCreateName').value.trim(), unit=$('invCreateUnit').value;
    var cost=$('invCreateCost').value, stock=$('invCreateStock').value, min=$('invCreateMin').value;
    if (!name || name.length > 80 || !/^\d+(\.\d{1,2})?$/.test(cost) ||
        !/^(0|[1-9]\d*)$/.test(stock) || !/^(0|[1-9]\d*)$/.test(min) ||
        !['pcs','roll','pack','meter','set'].includes(unit)) {
      err('invCreateError','Enter a valid material name, cost, whole-number quantity and reorder level.'); return;
    }
    write('invCreateForm','invCreateError',{action:'add_material',name:name,unit:unit,cost:cost,stock:stock,min:min},'invCreateModal','Material saved to MySQL.');
  });
  $('invOpenStockIn').addEventListener('click',function() {
    if (!ready || !materials.length) return;
    $('invStockInForm').reset();err('invStockInError','');options($('invStockInMaterial'), materials[0].id);
    $('invStockInCost').value = Number(materials[0].cost).toFixed(2);
    $('invStockInDate').value = new Date().toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
    updateStockInSummary();open('invStockInModal');
  });
  $('invStockInMaterial').addEventListener('change',function() {
    var m=material(this.value);if(m)$('invStockInCost').value=Number(m.cost).toFixed(2);
    updateStockInSummary();
  });
  $('invStockInQty').addEventListener('input',updateStockInSummary);
  $('invStockInCost').addEventListener('input',updateStockInSummary);
  $('invStockInForm').addEventListener('submit',function(e) {
    e.preventDefault();
    var id=$('invStockInMaterial').value, quantity=$('invStockInQty').value, cost=$('invStockInCost').value;
    var date=inputDate($('invStockInDate').value);
    if (!material(id)|| !/^[1-9]\d*$/.test(quantity)|| !/^\d+(\.\d{1,2})?$/.test(cost)||!date) {
      err('invStockInError','Choose a material and enter a valid quantity, cost and date.');return;
    }
    write('invStockInForm','invStockInError',{action:'stock_in',id:id,quantity:quantity,cost:cost,date:date},'invStockInModal','Stock-In saved to MySQL.');
  });
  $('invOpenCorrection').addEventListener('click',function() {
    if(!ready||!materials.length)return;
    $('invAdjustForm').reset();err('invAdjustError','');options($('invAdjustId'),materials[0].id);
    updateCorrectionBalance();open('invAdjustModal');
  });
  $('invAdjustId').addEventListener('change',updateCorrectionBalance);
  $('invAdjustForm').addEventListener('submit',function(e) {
    e.preventDefault();
    var id=$('invAdjustId').value, quantity=$('invAdjustQty').value, direction=$('invDirection').value;
    if (!material(id)|| !/^[1-9]\d*$/.test(quantity)|| !['in','out'].includes(direction)) {
      err('invAdjustError','Choose a valid material, quantity and correction direction.');return;
    }
    write('invAdjustForm','invAdjustError',{action:'correction',id:id,quantity:quantity,direction:direction},'invAdjustModal','Correction saved to MySQL.');
  });
  // Start with no actions available until the authorized API request succeeds.
  toggleButtons();
  reload().catch(function(e) {
    ready = false;toggleButtons();
    $('invEmpty').hidden = false;
    $('invEmpty').textContent = 'Could not load inventory from MySQL: ' + e.message;
    notify('Inventory database connection failed.');
  });
})();
