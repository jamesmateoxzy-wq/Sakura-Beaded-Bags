/* Shared data for the database-free prototype. */
window.SakuraStore = (function () {
  'use strict';

  const admin = document.body.dataset.adminId;
  const key = 'sakura-operations-v1' + (admin && admin !== 'demo' ? ':' + admin : '');
  // A back/forward cache restore can revive an old page with stale data.
  window.addEventListener('pageshow', event => {
    if (event.persisted) location.reload();
  });

  const collections = ['products', 'materials', 'orders', 'payments', 'inventoryHistory', 'categories'];

  function read() {
    let data;
    try {
      data = JSON.parse(sessionStorage.getItem(key) || '{}');
      if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Invalid store');
    } catch (error) {
      Sakura.toast('The browser session data could not be read.');
      data = {};
    }
    for (const collection of collections) {
      if (!Array.isArray(data[collection])) data[collection] = [];
    }
    if (!data.categories.length) {
      data.categories = ['Signature collection', 'Pink collection', 'Pearl collection'];
    }
    return data;
  }

  function save(data) {
    try {
      sessionStorage.setItem(key, JSON.stringify(data));
      return true;
    } catch (error) {
      Sakura.toast('The browser could not save these changes.');
      return false;
    }
  }

  function today() {
    const date = new Date();
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0')].join('-');
  }

  function validDate(date) {
    return /^\d{4}-\d{2}-\d{2}$/.test(date) &&
      Number.isFinite(Date.parse(date)) && new Date(date).toISOString().slice(0, 10) === date;
  }

  function cents(amount) {
    return Math.round(Number(amount) * 100 + 1e-8);
  }

  function costing(product) {
    const materials = product.materials.reduce((total, item) => total + cents(item.qty * item.cost), 0);
    const cost = materials + cents(product.labor) + cents(product.packaging);
    const price = Math.round(cost * (1 + product.markup / 100));
    const profit = price - cost;
    return {materials: materials / 100, cost: cost / 100, price: price / 100,
      profit: profit / 100, margin: price ? profit / price * 100 : 0};
  }

  function paidCents(payments, orderId) {
    return payments.reduce((total, entry) => total + (entry.orderId === orderId ? cents(entry.amount) : 0), 0);
  }

  function movement(data, material, activity, delta, date = today()) {
    material.stock = Math.round((material.stock + delta) * 1000) / 1000;
    data.inventoryHistory.unshift({material:material.name, unit:material.unit, activity, delta, date});
  }

  function csvCell(value) {
    let contents = String(value == null ? '' : value);
    if (typeof value === 'string' && /^\s*[=+\-@\t\r]/.test(contents)) contents = "'" + contents;
    return '"' + contents.replace(/"/g, '""') + '"';
  }

  return {read, save, today, validDate, cents, costing, paidCents, movement, csvCell};
})();