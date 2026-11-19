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
 const search=document.getElementById('inventory-search'),category=document.getElementById('inventory-category'),status=document.getElementById('inventory-status'),rows=[...document.querySelectorAll('#inventory-table tbody tr')],empty=document.getElementById('inventory-empty');
 function filter(){let q=(search.value||'').toLowerCase(),c=category.value,s=status.value,n=0;rows.forEach(r=>{let ok=r.textContent.toLowerCase().includes(q)&&(c==='all'||r.dataset.category===c)&&(s==='all'||r.dataset.status===s);r.hidden=!ok;if(ok)n++});empty.hidden=n>0;}
 search?.addEventListener('input',filter);category?.addEventListener('change',filter);status?.addEventListener('change',filter);
 document.querySelectorAll('[data-adjust]').forEach(button=>button.addEventListener('click',()=>{document.getElementById('adjust-material-name').value=button.dataset.adjust;document.getElementById('adjust-modal').classList.add('open')}));
 document.querySelectorAll('.demo-form').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();alert('Demo preview: form layout is ready. Connect this form to your PHP/MySQL inventory endpoint to save the movement.');form.closest('.modal-backdrop').classList.remove('open');form.reset()}));
});
