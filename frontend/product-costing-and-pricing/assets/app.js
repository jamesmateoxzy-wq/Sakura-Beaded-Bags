// Shared mobile navigation and modal helpers.
document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('[data-menu-toggle]');
  const sidebar = document.querySelector('.sidebar');
  if (menu && sidebar) menu.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.openModal);
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => button.closest('.modal-backdrop')?.classList.remove('open'));
  });
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', event => {
      if (event.target === backdrop) backdrop.classList.remove('open');
    });
  });
});

document.addEventListener('DOMContentLoaded',()=>{
 const $=id=>document.getElementById(id), money=n=>'₱'+(Number(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
 const rows=()=>[...document.querySelectorAll('#cost-table tbody tr')];
 function calculate(){let materials=0;rows().forEach(r=>{let q=Number(r.querySelector('.mat-qty')?.value)||0,c=Number(r.querySelector('.mat-cost')?.value)||0,s=q*c;materials+=s;r.querySelector('.mat-subtotal').textContent=money(s)});let labor=(Number($('labor-hours').value)||0)*(Number($('labor-rate').value)||0),pack=Number($('packaging').value)||0,cost=materials+labor+pack,markup=Number($('markup').value)||0,sell=cost*(1+markup/100),target=Number($('target-price').value)||sell,profit=target-cost,margin=target>0?profit/target*100:0;$('total-cost').textContent=money(cost);$('selling-price').textContent=money(sell);$('profit').textContent=money(profit);$('margin-note').textContent=`Materials ${money(materials)} + labor ${money(labor)} + packaging ${money(pack)} = ${money(cost)} · Target margin: ${margin.toFixed(1)}%`;}
 document.addEventListener('input',e=>{if(e.target.matches('.mat-qty,.mat-cost,#labor-hours,#labor-rate,#packaging,#markup,#target-price'))calculate()});
 $('cost-table')?.addEventListener('click',e=>{if(e.target.classList.contains('remove-row')){e.target.closest('tr').remove();calculate()}});
 $('add-material')?.addEventListener('click',()=>{let tr=document.createElement('tr');tr.innerHTML='<td><input class="field mat-name" value="New material"></td><td><input class="field mat-qty" type="number" min="0" step="0.01" value="1"></td><td><input class="field mat-cost" type="number" min="0" step="0.01" value="0"></td><td class="mat-subtotal">₱0.00</td><td><button class="remove-row" type="button" aria-label="Remove material">×</button></td>'; $('cost-table').querySelector('tbody').appendChild(tr);calculate()});
 $('target-price')?.addEventListener('input',()=>{if(!$('markup').dataset.touched)calculate()});$('markup')?.addEventListener('input',()=>{$('markup').dataset.touched='1'});
 $('reset-pricing')?.addEventListener('click',()=>{if(confirm('Reset the costing sheet to its sample values?'))location.reload()});
 $('save-pricing')?.addEventListener('click',()=>alert('Demo preview: price calculations are live, but saving requires your PHP/MySQL product-pricing endpoint.'));
 $('add-product-form')?.addEventListener('submit',e=>{e.preventDefault();alert('Demo preview: connect this form to your product create endpoint to save a product.');$('add-product-modal').classList.remove('open');e.target.reset()});
 const cards=[...document.querySelectorAll('.product-card')];function apply(){let q=$('product-search').value.toLowerCase(),c=$('category-filter').value;cards.forEach(card=>card.hidden=!(card.dataset.product.toLowerCase().includes(q)&&(c==='all'||card.dataset.category===c)));}
 $('product-search')?.addEventListener('input',apply);$('category-filter')?.addEventListener('change',apply);
 cards.forEach(card=>card.addEventListener('click',()=>{cards.forEach(c=>c.classList.remove('selected'));card.classList.add('selected');$('sheet-title').textContent=card.dataset.product+' · Costing sheet';$('sheet-subtitle').textContent='SKU: '+card.dataset.sku+' · '+card.dataset.category;$('product-name').value=card.dataset.product;$('product-category').value=card.dataset.category;$('target-price').value=card.dataset.price;calculate()}));
 calculate();
});
