/* Sakura Beaded Bags - Inventory Management */
(function () {
  'use strict';
  var root = document.getElementById('operationsPage');
  if (!root || root.dataset.module !== 'inventory') return;
  var demo = root.dataset.demo === '1';
  var $ = function (id) { return document.getElementById(id); };
  var key = 'sakura-operations-v1';
  var samples = [
    { id:'MAT-001', name:'Pink Beads', unit:'pcs', cost:2, stock:10, min:50 },
    { id:'MAT-002', name:'Nylon Thread', unit:'roll', cost:40, stock:3, min:5 },
    { id:'MAT-003', name:'Bag Handles', unit:'pcs', cost:55, stock:5, min:10 },
    { id:'MAT-004', name:'Rose Crystal Beads', unit:'pcs', cost:2, stock:600, min:100 },
    { id:'MAT-005', name:'Ivory Pearl Beads', unit:'pcs', cost:3, stock:450, min:100 },
    { id:'MAT-006', name:'Clasps', unit:'pcs', cost:30, stock:4, min:10 },
    { id:'MAT-007', name:'Pink Ribbon', unit:'roll', cost:25, stock:2, min:5 }
  ];
  function load() {
    if (!demo) return [];
    try {
      var stored = JSON.parse(sessionStorage.getItem(key));
      if (stored && Array.isArray(stored.materials)) return stored.materials;
    } catch (_) {}
    return JSON.parse(JSON.stringify(samples));
  }
  function save() {
    try {
      var stored = JSON.parse(sessionStorage.getItem(key) || '{}');
      stored.materials = materials;
      sessionStorage.setItem(key, JSON.stringify(stored));
    } catch (_) {}
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
    var cls = status === 'In Stock' ? 'badge-green' : status === 'Low Stock' ? 'badge-amber' : 'badge-pink';
    return '<span class="badge ' + cls + '">' + esc(status) + '</span>';
  }
  function notify(message) {
    if (window.Sakura && Sakura.toast) Sakura.toast(message);
  }
  function openModal(id) { if (window.Sakura) Sakura.openModal(id); }
  function closeModal(id) { if (window.Sakura) Sakura.closeModal(id); }
  var materials = load();

  function render() {
    var query = $('invSearch').value.trim().toLowerCase();
    var filter = $('invFilter').value;
    var shown = materials.filter(function (material) {
      var status = stockStatus(material);
      return (filter === 'all' || (filter === 'low' && material.stock <= material.min) ||
        (filter === 'out' && material.stock === 0) || (filter === 'good' && status === 'In Stock')) &&
        (!query || (material.id + ' ' + material.name).toLowerCase().includes(query));
    });
    $('invTotal').textContent = materials.length;
    $('invLow').textContent = materials.filter(function (m) { return m.stock <= m.min; }).length;
    $('invOut').textContent = materials.filter(function (m) { return m.stock === 0; }).length;
    $('invValue').textContent = money(materials.reduce(function (sum, m) { return sum + m.stock * m.cost; }, 0));
    $('invRows').innerHTML = shown.map(function (material) {
      return '<tr><td class="strong">' + esc(material.id) + '</td><td>' + esc(material.name) +
        '</td><td>' + esc(material.unit) + '</td><td>' + money(material.cost) + '</td><td>' + material.stock +
        '</td><td>' + material.min + '</td><td>' + money(material.stock * material.cost) + '</td><td>' +
        badge(stockStatus(material)) + '</td><td><button type="button" class="btn btn-outline btn-sm" data-adjust="' +
        esc(material.id) + '"' + (demo ? '' : ' disabled') + '>Adjust Stock</button></td></tr>';
    }).join('');
    $('invEmpty').hidden = shown.length > 0;
    $('invFoot').textContent = 'Showing ' + shown.length + ' of ' + materials.length + ' materials';
  }
  $('invSearch').addEventListener('input', render);
  $('invFilter').addEventListener('change', render);
  $('invExport').addEventListener('click', function () {
    var rows = [['Material ID','Material','Unit','Unit Cost PHP','Stock','Reorder At','Status']]
      .concat(materials.map(function (m) { return [m.id,m.name,m.unit,m.cost,m.stock,m.min,stockStatus(m)]; }));
    var csv = '\ufeff' + rows.map(function (row) {
      return row.map(function (value) { return '"' + String(value).replace(/"/g,'""') + '"'; }).join(',');
    }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    var link = document.createElement('a');
    link.href = url; link.download = 'sakura-inventory-demo.csv'; document.body.appendChild(link); link.click(); link.remove();
  });
  $('invNew').addEventListener('click', function () {
    if (!demo) return;
    $('invCreateForm').reset();
    openModal('invCreateModal');
  });
  $('invCreateForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var name = $('invName').value.trim();
    var cost = Number($('invCost').value);
    var qty = Number($('invQty').value);
    var min = Number($('invMin').value);
    if (!demo || !name || materials.some(function (m) { return m.name.toLowerCase() === name.toLowerCase(); })) return;
    var max = materials.reduce(function (value, material) {
      return Math.max(value, Number(material.id.split('-')[1]) || 0);
    }, 0);
    materials.push({id:'MAT-' + String(max + 1).padStart(3,'0'), name:name, unit:$('invUnit').value,
      cost:cost, stock:qty, min:min});
    save(); closeModal('invCreateModal'); render(); notify('Demo material added.');
  });
  $('invRows').addEventListener('click', function (event) {
    var button = event.target.closest('[data-adjust]');
    if (!button || !demo) return;
    var material = materials.find(function (item) { return item.id === button.dataset.adjust; });
    if (!material) return;
    $('invAdjustId').value = material.id;
    $('invAdjustDescription').textContent = material.name + ' · ' + material.stock + ' ' + material.unit + ' on hand';
    $('invAdjustForm').reset();
    openModal('invAdjustModal');
  });
  $('invAdjustForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var material = materials.find(function (item) { return item.id === $('invAdjustId').value; });
    var qty = Number($('invAdjustQty').value);
    if (!demo || !material || qty < 1) return;
    var next = material.stock + ($('invDirection').value === 'in' ? qty : -qty);
    if (next < 0) return;
    material.stock = next;
    save(); closeModal('invAdjustModal'); render(); notify('Demo stock adjusted.');
  });
  render();
})();