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
 const rows=[...document.querySelectorAll('#orders-table tbody tr')], search=document.getElementById('order-search'), filter=document.getElementById('status-filter'), empty=document.getElementById('order-empty');
 function apply(){let q=(search?.value||'').toLowerCase(), f=filter?.value||'all', n=0;rows.forEach(r=>{let match=r.textContent.toLowerCase().includes(q), status=r.dataset.status; if(f!=='all')match=match&&status===f; r.hidden=!match;if(match)n++});if(empty)empty.hidden=n>0;}
 search?.addEventListener('input',apply);filter?.addEventListener('change',apply);
 document.querySelectorAll('[data-order-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-order-tab]').forEach(x=>x.classList.remove('active'));b.classList.add('active');if(b.dataset.orderTab==='production'){filter.value='In Progress';}else{filter.value='all';}apply();}));
 document.getElementById('new-order-form')?.addEventListener('submit',e=>{e.preventDefault();alert('Demo preview: the order form is ready. Connect this form to your PHP/MySQL order endpoint to save new orders.');document.getElementById('new-order-modal').classList.remove('open');e.target.reset();});
 document.getElementById('save-bom')?.addEventListener('click',()=>alert('Demo preview: BOM changes are not saved to a database yet.'));
 document.querySelector('[data-add-bom]')?.addEventListener('click',()=>{const body=document.querySelector('#bom-table tbody');const tr=document.createElement('tr');tr.innerHTML='<td>New material</td><td>1 pc</td><td>₱0.00</td><td>₱0.00</td>';body.appendChild(tr);});
 document.getElementById('cancel-order')?.addEventListener('click',()=>alert('Demo preview: connect this action to the order cancellation endpoint. Restore any reserved materials on cancellation.'));
 document.getElementById('archive-order')?.addEventListener('click',()=>alert('Demo preview: connect this action to the order archive endpoint.'));
});
