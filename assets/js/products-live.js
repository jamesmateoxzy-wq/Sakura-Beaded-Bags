/* Sakura Beaded Bags — Product Costing & Pricing, MySQL-backed implementation.
   Does not use sessionStorage. All writes use the protected PHP endpoint.
   Compatible with the existing admin/products.php markup on main (Nov 21).
*/
(function () {
  'use strict';
  const S = window.Sakura;
  const $ = id => document.getElementById(id);
  const endpoint = 'api/products.php';
  let materials = [], products = [], categories = [], csrf = '', selectedId = null;
  let draft = null, newDraft = null, onAddMaterial = null, pending = false;
  let lastCreatedCategoryId = null;
  let newPhoto = null, photoPreviewUrl = null;
  const MAX_PHOTO_BYTES = 4 * 1024 * 1024;
  const PHOTO_TYPES = ['image/jpeg','image/png','image/webp'];
  const IMG_PATH = '../assets/images/products/';
  function toast(msg) { if (S && S.toast) S.toast(msg); }
  function esc(x) { return String(x == null ? '' : x).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function pesos(n) { return '₱' + Number(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}); }
  function num(v) { const x = Number(v); return Number.isFinite(x) ? x : NaN; }
  function copy(x) { return JSON.parse(JSON.stringify(x)); }
  function setBusy(value) {
    pending = value;
    ['nSave','sSave','openAddProduct','openAddCategory','acSave','amAdd'].forEach(id => { if ($(id)) $(id).disabled = value; });
  }
  async function request(payload, photoFile) {
    const opts = {credentials:'same-origin', cache:'no-store'};
    if (payload) {
      opts.method = 'POST';
      opts.headers = {'X-CSRF-Token':csrf};
      if (photoFile) {
        const form = new FormData();
        form.append('data', JSON.stringify(payload));
        form.append('photo', photoFile, photoFile.name);
        opts.body = form; // The browser adds the multipart boundary.
      } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(payload);
      }
    }
    let res;
    try { res = await fetch(endpoint, opts); }
    catch (_) { throw new Error('Could not reach PHP. Check the local server.'); }
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || !json.ok) throw new Error(json && json.error ? json.error : `Server error ${res.status}`);
    return json;
  }
  function lookup(id) { return materials.find(m => m.id === id); }
  function categoryName(id) { return (categories.find(c => c.id === Number(id)) || {}).name || 'Uncategorized'; }
  function prices(p) {
    const materialTotal = (p.materials || []).reduce((sum, m) => sum + Math.round(Number(m.qty) * Number(lookup(m.id)?.cost ?? m.cost) * 100), 0) / 100;
    const total = Math.round((materialTotal + Number(p.labor || 0) + Number(p.packaging || 0)) * 100) / 100;
    const price = Math.round(total * (1 + Number(p.markup || 0) / 100) * 100) / 100;
    return {materials:materialTotal,cost:total,price,profit:Math.round((price-total)*100)/100,margin:price?100*(price-total)/price:0};
  }
  function thumbnail(p) {
    const imagePath = String(p.image || '');
    const url = imagePath && !imagePath.startsWith('data:')
      ? (imagePath.startsWith('../uploads/product-photos/') || imagePath.startsWith('/') ? imagePath : IMG_PATH + imagePath) : '';
    return '<div class="thumb ' + (url?'':'no-img') + '" data-initial="' + esc((p.name||'?').charAt(0).toUpperCase()) + '">' +
      (url?'<img src="'+esc(url)+'" alt="'+esc(p.name)+'" onerror="this.parentElement.classList.add(\'no-img\')">':'')+'</div>';
  }
  function clearProductPhoto() {
    if (photoPreviewUrl) URL.revokeObjectURL(photoPreviewUrl);
    photoPreviewUrl = null;
    newPhoto = null;
    $('nPhoto').value = '';
    $('nPhotoName').textContent = 'No photo chosen';
    $('nPhotoPreview').replaceChildren();
    $('nPhotoPreview').classList.add('no-img');
  }
  function chooseProductPhoto(file) {
    clearProductPhoto();
    if (!file) return;
    if (!PHOTO_TYPES.includes(file.type)) { toast('Choose a JPG, PNG or WebP photo.'); return; }
    if (file.size < 1 || file.size > MAX_PHOTO_BYTES) {
      toast('Product photos must be non-empty and no larger than 4 MB.'); return;
    }
    newPhoto = file;
    photoPreviewUrl = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = photoPreviewUrl;
    img.alt = 'Selected product photo preview';
    $('nPhotoPreview').appendChild(img);
    $('nPhotoPreview').classList.remove('no-img');
    $('nPhotoName').textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
  }
  function populateCategories() {
    for (const id of ['categoryFilter','sCategory','nCategory']) {
      const el = $(id), prev = el.value;
      el.innerHTML = (id==='categoryFilter'?'<option value="all">All categories</option>':'') +
        categories.map(c=>'<option value="'+c.id+'">'+esc(c.name)+'</option>').join('');
      if ([...el.options].some(o=>o.value===prev)) el.value=prev;
    }
  }
  function listProducts() {
    const query = $('productSearch').value.trim().toLowerCase(), cat = $('categoryFilter').value;
    const list = products.filter(p => (!query||p.name.toLowerCase().includes(query)) && (cat==='all'||Number(cat)===p.categoryId));
    $('productList').innerHTML = list.length ? '' : '<p class="empty">'+(products.length?'No products match your search.':'No products yet.')+'</p>';
    for (const p of list) {
      const card = document.createElement('button'); card.type='button'; card.className='prod-card'+(p.id===selectedId?' is-selected':'');
      card.innerHTML = thumbnail(p)+'<div><h3>'+esc(p.name)+'</h3><div class="cat">'+esc(p.category)+'</div><div class="price">'+pesos(p.sellingPrice)+'</div><div class="go">'+(p.id===selectedId?'Editing price →':'View costing →')+'</div></div>';
      card.addEventListener('click',()=>selectProduct(p.id)); $('productList').appendChild(card);
    }
    $('productCount').textContent=products.length+' active product'+(products.length===1?'':'s')+' · Prices per bag';
  }
  function summary(prefix, p, updateTarget) {
    const calc = prices(p);
    $(prefix+'Cost').textContent=pesos(calc.cost);
    $(prefix+'Price').textContent=pesos(calc.price);
    $(prefix+'Profit').textContent=pesos(calc.profit);
    $(prefix+'Note').textContent=pesos(calc.materials)+' materials + '+pesos(p.labor)+' labor + '+pesos(p.packaging)+' packaging · Profit margin: '+calc.margin.toFixed(1)+'%';
    if (prefix==='s' && updateTarget!==false) $('sTarget').value=calc.price.toFixed(2);
    return calc;
  }
  function renderRows(container, rows, changed) {
    container.innerHTML=rows.length?rows.map((r,i)=>{
      const mat=lookup(r.id); const cost=mat?mat.cost:r.cost; const sub=cost*Number(r.qty);
      return '<div class="mat-row" data-i="'+i+'"><div class="mat-name">'+esc(r.name)+'</div>'+
        '<label class="mat-cell"><span class="mat-label">Quantity</span><div class="qty-wrap"><input class="input" type="number" min="0.01" step="0.01" max="1000000" value="'+esc(r.qty)+'" data-qty aria-label="Quantity of '+esc(r.name)+'"><em>'+esc(r.unit)+'</em></div></label>'+
        '<label class="mat-cell"><span class="mat-label">Unit cost (Inventory)</span><input class="input" type="number" readonly value="'+esc(cost)+'"></label>'+
        '<div class="mat-sub"><span class="mat-label">Subtotal</span><b>'+pesos(sub)+'</b></div><button type="button" class="icon-btn" data-remove aria-label="Remove '+esc(r.name)+'"><svg class="icon" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>';
    }).join(''):'<p class="empty">No materials yet. Click “Add material”.</p>';
    container.oninput=e=>{const row=e.target.closest('.mat-row');if(!row||!e.target.matches('[data-qty]'))return;
      rows[Number(row.dataset.i)].qty=num(e.target.value); row.querySelector('.mat-sub b').textContent=pesos((lookup(rows[Number(row.dataset.i)].id)?.cost||0)*rows[Number(row.dataset.i)].qty);changed();};
    container.onclick=e=>{const btn=e.target.closest('[data-remove]');if(!btn)return;const row=btn.closest('.mat-row');rows.splice(Number(row.dataset.i),1);renderRows(container,rows,changed);changed();};
  }
  function selectProduct(id) {
    const product=products.find(p=>p.id===id); if(!product)return;
    selectedId=id; draft=copy(product);
    $('costSheet').hidden=false; $('emptyState').hidden=true;
    $('sTitle').textContent=draft.name+' · Costing sheet';
    $('sSku').textContent='SKU: '+(draft.sku||'—')+' · '+draft.category;
    $('sName').value=draft.name; $('sCategory').value=draft.categoryId;
    $('sLabor').value=draft.labor; $('sPackaging').value=draft.packaging; $('sMarkup').value=draft.markup;
    renderRows($('sMaterials'),draft.materials,()=>summary('s',draft));
    summary('s',draft); listProducts();
  }
  function addToRecipe(recipe,entry) {
    const found=recipe.find(m=>m.id===entry.id);
    if(found) found.qty=Math.round((Number(found.qty)+Number(entry.qty))*100)/100;
    else recipe.push(entry);
  }
  function currentChoice() { return lookup($('amMaterial').value); }
  function materialAmount() {
    const m=currentChoice(), qty=num($('amQty').value);
    $('amAdd').disabled=pending||!m;
    $('amStockInfo').textContent=m?m.id+' · In stock: '+m.stock+' '+m.unit:'';
    $('amCost').value=m?Number(m.cost).toFixed(2):'0.00';
    $('amAmount').textContent=pesos(m&&Number.isFinite(qty)?qty*m.cost:0);
    const warn=$('amWarning');
    warn.hidden=!m||!(qty>m.stock);
    if(!warn.hidden) warn.textContent='Insufficient stock for production. You can save the recipe, but Stock-In is needed before accepting an order.';
  }
  function addMaterialModal(context,callback) {
    if(!materials.length){toast('Add materials in Inventory Management first.');return;}
    onAddMaterial=callback; $('amContext').textContent=context;
    $('amMaterial').innerHTML=materials.map(m=>'<option value="'+esc(m.id)+'">'+esc(m.name)+' · '+esc(m.stock)+' '+esc(m.unit)+' available</option>').join('');
    $('amQty').value='1'; $('amCost').readOnly=true; materialAmount();
    S.openModal('addMaterialModal');
  }
  function valid(p) {
    if(!p||!p.name||!p.name.trim()||p.name.trim().length>160){toast('Enter a valid product name.');return false;}
    if(!categories.some(c=>c.id===Number(p.categoryId))){toast('Choose a valid category.');return false;}
    if(!Array.isArray(p.materials)||p.materials.length===0){toast('Add at least one recipe material.');return false;}
    for(const field of ['labor','packaging','markup']){
      const val=Number(p[field]); if(!Number.isFinite(val)||val<0||val>(field==='markup'?999.99:1000000)||Math.abs(Math.round(val*100)-val*100)>0.000001){toast('Enter valid labor, packaging and markup amounts (up to 2 decimals).');return false;}
    }
    for(const item of p.materials){
      const n=Number(item.qty);
      if(!lookup(item.id)||!Number.isFinite(n)||n<0.01||n>1000000||Math.abs(n*100-Math.round(n*100))>0.000001){toast('Recipe quantities must be positive with at most 2 decimals.');return false;}
    }
    if(!Number.isFinite(prices(p).price)||prices(p).price<=0||prices(p).price>100000000){toast('Calculated price is outside the allowed range.');return false;}
    return true;
  }
  function payload(p,action) { return {action,id:p.id||undefined,name:p.name.trim(),categoryId:Number(p.categoryId),labor:Number(p.labor),packaging:Number(p.packaging),markup:Number(p.markup),materials:p.materials.map(m=>({id:m.id,qty:Number(m.qty)}))}; }
  async function reloadAndSelect(id) {
    const data=await request(); materials=data.materials||[]; products=data.products||[]; categories=data.categories||[]; csrf=data.csrf||'';
    populateCategories();
    if(id && products.some(p=>p.id===id)) selectProduct(id);
    else if(products.length) selectProduct(products[0].id);
    else {selectedId=null;draft=null;$('costSheet').hidden=true;$('emptyState').hidden=false;listProducts();}
  }
  function setup() {
    $('productSearch').addEventListener('input',listProducts);
    $('categoryFilter').addEventListener('change',listProducts);
    $('nPhoto').addEventListener('change',event=>chooseProductPhoto(event.target.files?.[0]));
    $('openAddCategory').addEventListener('click',()=>{
      if (pending) return;
      $('addCategoryForm').reset();
      $('acError').textContent = '';
      $('acError').hidden = true;
      S.openModal('addCategoryModal');
    });
    $('acName').addEventListener('input',()=>{ $('acError').hidden = true; });
    $('addCategoryForm').addEventListener('submit',async event=>{
      event.preventDefault();
      if (pending) return;
      const name = $('acName').value.trim().replace(/\s+/g,' ');
      const error = $('acError');
      function showError(message) { error.textContent = message; error.hidden = false; }
      if (!name || Array.from(name).length > 100) { showError('Enter a category name (up to 100 characters).'); return; }
      if (name.toLowerCase() === 'all') { showError('This category name is reserved.'); return; }
      if (categories.some(c => c.name.toLocaleLowerCase() === name.toLocaleLowerCase())) {
        showError('This category already exists.'); return;
      }
      setBusy(true);
      try {
        const saved = await request({action:'add_category',name});
        const data = await request();
        categories = data.categories || [];
        csrf = data.csrf || csrf;
        populateCategories();
        lastCreatedCategoryId = Number(saved.id);
        $('nCategory').value = String(lastCreatedCategoryId);
        S.closeModal('addCategoryModal');
        toast('Category saved in MySQL. It is ready for new products!');
      } catch (e) {
        showError(e.message);
      } finally {
        setBusy(false);
      }
    });
    $('sName').addEventListener('input',()=>{if(draft){draft.name=$('sName').value;$('sTitle').textContent=(draft.name||'Product')+' · Costing sheet';}});
    $('sCategory').addEventListener('change',()=>{if(draft){draft.categoryId=Number($('sCategory').value);draft.category=categoryName(draft.categoryId);}});
    for(const type of ['Labor','Packaging','Markup']) $('s'+type).addEventListener('input',()=>{if(draft){draft[type.toLowerCase()]=num($('s'+type).value);summary('s',draft);}});
    $('sTarget').addEventListener('input',()=>{if(!draft)return;
      const cost=prices(draft).cost, target=num($('sTarget').value);
      if(!Number.isFinite(target)||target<cost){$('sTarget').setCustomValidity('Target price must cover the cost.');return;}
      $('sTarget').setCustomValidity(''); draft.markup=cost?Math.max(0,Math.round((target/cost-1)*10000)/100):0;
      $('sMarkup').value=draft.markup;summary('s',draft,false);
    });
    $('sReset').addEventListener('click',()=>{if(selectedId!==null)selectProduct(selectedId);toast('Unsaved changes reset.');});
    $('sAddMaterial').addEventListener('click',()=>{if(!draft)return;addMaterialModal('Adding to '+draft.name+' costing sheet',entry=>{
      addToRecipe(draft.materials,entry);renderRows($('sMaterials'),draft.materials,()=>summary('s',draft));summary('s',draft);});});
    $('amMaterial').addEventListener('change',materialAmount); $('amQty').addEventListener('input',materialAmount);
    $('amAdd').addEventListener('click',()=>{const m=currentChoice(),q=num($('amQty').value);
      if(!m||!Number.isFinite(q)||q<0.01||q>1000000||Math.abs(q*100-Math.round(q*100))>0.000001){toast('Enter a valid quantity (up to 2 decimals).');return;}
      if(onAddMaterial) onAddMaterial({id:m.id,name:m.name,unit:m.unit,qty:q,cost:m.cost});
      S.closeModal('addMaterialModal');toast('Material added to product recipe.');
    });
    $('openAddProduct').addEventListener('click',()=>{
      if(!materials.length){toast('Add materials in Inventory first.');return;}
      const defaultCategoryId = categories.some(c=>c.id===lastCreatedCategoryId) ? lastCreatedCategoryId : (categories[0]?.id||0);
      newDraft={name:'',categoryId:defaultCategoryId,materials:[],labor:0,packaging:0,markup:100};
      $('nName').value='';$('nCategory').value=newDraft.categoryId;$('nSku').value='Assigned when saved';
      $('nLabor').value=0;$('nPackaging').value=0;$('nMarkup').value=100;
      $('nPhoto').disabled=false;clearProductPhoto();
      renderRows($('nMaterials'),newDraft.materials,()=>summary('n',newDraft));summary('n',newDraft);
      S.openModal('addProductModal');
    });
    $('nName').addEventListener('input',()=>{if(newDraft)newDraft.name=$('nName').value;});
    $('nCategory').addEventListener('change',()=>{if(newDraft)newDraft.categoryId=Number($('nCategory').value);});
    for(const type of ['Labor','Packaging','Markup']) $('n'+type).addEventListener('input',()=>{if(newDraft){newDraft[type.toLowerCase()]=num($('n'+type).value);summary('n',newDraft);}});
    $('nAddMaterial').addEventListener('click',()=>{if(!newDraft)return;addMaterialModal('Adding to '+(newDraft.name||'new product')+' recipe',entry=>{
      addToRecipe(newDraft.materials,entry);renderRows($('nMaterials'),newDraft.materials,()=>summary('n',newDraft));summary('n',newDraft);});});
    $('nSave').addEventListener('click',async()=>{if(pending||!valid(newDraft))return;
      setBusy(true);try{const res=await request(payload(newDraft,'add_product'),newPhoto);S.closeModal('addProductModal');clearProductPhoto();await reloadAndSelect(res.id);toast('Product saved to MySQL!');}
      catch(e){toast(e.message);}finally{setBusy(false);}
    });
    $('sSave').addEventListener('click',async()=>{if(pending||!valid(draft))return;
      setBusy(true);try{const res=await request(payload(draft,'save_pricing'));await reloadAndSelect(res.id);toast('Product costing saved to MySQL!');}
      catch(e){toast(e.message);}finally{setBusy(false);}
    });
  }
  setup();
  reloadAndSelect(null).catch(e=>{
    toast('Product database connection failed: '+e.message);
    $('costSheet').hidden=true;$('emptyState').hidden=false;
    $('emptyState').querySelector('p').textContent='Cannot load Products from MySQL: '+e.message;
  });
})();
