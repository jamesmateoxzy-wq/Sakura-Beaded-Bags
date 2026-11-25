/* V2 reports: booked order cohort and payment-date cash flow are kept separate. */
(function () {
  'use strict';
  const $ = id => document.getElementById(id);
  if (!$('operationsPage') || $('operationsPage').dataset.module !== 'reports') return;
  const esc = x => String(x ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const cents = x => Math.round(Number(x || 0)*100);
  const money = x => '₱'+(x/100).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  const pct = (x,y) => y ? (100*x/y).toFixed(1)+'%' : '—';
  const sum = (a,fn) => a.reduce((n,x)=>n+fn(x),0);
  let data, sections=[], metrics=[], periodLabel='';
  const today = new Date();
  const iso = d => d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
  $('repMonth').value=iso(today).slice(0,7);
  $('repFrom').value=iso(new Date(today.getFullYear(),today.getMonth(),1));
  $('repTo').value=iso(today);
  function groupKey(date) {
    const mode=$('repGranularity').value;
    if(mode==='daily') return date;
    if(mode==='yearly') return date.slice(0,4);
    if(mode==='weekly') {
      const d=new Date(date+'T00:00:00Z');
      d.setUTCDate(d.getUTCDate()-((d.getUTCDay()+6)%7));
      return d.toISOString().slice(0,10);
    }
    return date.slice(0,7);
  }
  function filter() {
    const mode=$('repPeriod').value;
    for(const [id,show] of [['repMonthWrap',mode==='month'],['repYearWrap',mode==='year'],['repFromWrap',mode==='range'],['repToWrap',mode==='range']]) $(id).hidden=!show;
    if(mode==='month') {const m=$('repMonth').value; if(!m) throw Error('Choose a month.'); periodLabel=m;return x=>x.startsWith(m);}
    if(mode==='year') {const y=$('repYear').value; periodLabel=y;return x=>x.startsWith(y);}
    if(mode==='range') {
      const a=$('repFrom').value,b=$('repTo').value;
      if(!a||!b||a>b) throw Error('Select a valid start and end date.');
      periodLabel=a+' to '+b;return x=>x>=a&&x<=b;
    }
    periodLabel='All dates';return ()=>true;
  }
  function table(title,desc,head,rows,chart=false) {
    sections.push({title,desc,head,rows});
    let chartHtml='';
    if(chart&&rows.length){
      const max=Math.max(1,...rows.map(r=>r[1]));
      chartHtml='<div class="v2-sales-bars">'+rows.map(r=>'<div><span>'+esc(r[0])+'</span><div class="v2-track"><i style="width:'+Math.max(0,Math.min(100,r[1]/max*100))+'%"></i></div><strong>'+esc(money(r[1]))+'</strong></div>').join('')+'</div>';
    }
    const displayRows=rows.map(r=>r.map((v,i)=>chart&&i===1?money(v):v));
    return '<section class="card v2-report-section"><h2>'+esc(title)+'</h2><p>'+esc(desc)+'</p>'+chartHtml+'<div class="v2-table-scroll"><table class="rep-analysis-table"><thead><tr>'+head.map(h=>'<th>'+esc(h)+'</th>').join('')+'</tr></thead><tbody>'+displayRows.map(r=>'<tr>'+r.map(v=>'<td>'+esc(v)+'</td>').join('')+'</tr>').join('')+'</tbody></table></div>'+(rows.length?'':'<p>No records for this period.</p>')+'</section>';
  }
  function render() {
    if(!data)return;
    $('repExport').disabled=true;$('repPrint').disabled=true;
    try {
      const match=filter(), orders=data.orders.filter(o=>match(o.date)), ids=new Set(orders.map(o=>String(o.id)));
      const items=data.items.filter(i=>ids.has(String(i.orderId))), payments=data.payments.filter(p=>match(p.date)), expenses=data.expenses.filter(e=>match(e.date));
      const sales=sum(orders,o=>cents(o.total)), costs=sum(items,i=>Number(i.quantity)*cents(i.cost));
      const known=items.filter(i=>i.materialCost!==null&&i.laborCost!==null&&i.otherCost!==null);
      const unknown=items.length-known.length;
      const material=sum(known,i=>Number(i.quantity)*cents(i.materialCost)), labor=sum(known,i=>Number(i.quantity)*cents(i.laborCost)), other=sum(known,i=>Number(i.quantity)*cents(i.otherCost));
      const unclassified=costs-material-labor-other, gross=sales-costs, overhead=sum(expenses,e=>cents(e.amount));
      const cash=sum(payments,p=>cents(p.amount)), outstanding=sum(orders,o=>Math.max(0,cents(o.balance)));
      const status={Pending:0,Downpayment:0,'Fully Paid':0};
      orders.forEach(o=>{const paid=cents(o.received),total=cents(o.total);status[paid<=0?'Pending':paid>=total?'Fully Paid':'Downpayment']++;});
      const timeline=new Map();
      orders.forEach(o=>{const k=groupKey(o.date);timeline.set(k,(timeline.get(k)||0)+cents(o.total));});
      const customers=new Map(), products=new Map(), first=new Map();
      for(const o of data.orders){const key=String(o.customerId);if(!first.has(key)||first.get(key)>o.date)first.set(key,o.date);}
      for(const o of orders){const k=String(o.customerId),r=customers.get(k)||{name:o.customer,orders:0,sales:0};r.orders++;r.sales+=cents(o.total);customers.set(k,r);}
      for(const i of items){const k=String(i.productId),r=products.get(k)||{name:i.product,qty:0,sales:0,cost:0};r.qty+=Number(i.quantity);r.sales+=Number(i.quantity)*cents(i.price);r.cost+=Number(i.quantity)*cents(i.cost);products.set(k,r);}
      $('repRevenue').textContent=money(sales);$('repMaterial').textContent=money(material)+(unknown?'*':'');$('repLabor').textContent=money(labor)+(unknown?'*':'');$('repProfit').textContent=money(gross);
      $('repStatus').textContent=periodLabel+' · '+orders.length+' orders'+(unknown?' · * '+unknown+' older order line(s) have unknown cost components; displayed component totals are known amounts only.':'');
      sections=[];metrics=[['Total booked sales',money(sales)],['Known material expenses',money(material)],['Known labor expenses',money(labor)],['Estimated gross profit',money(gross)],['Unknown historical cost lines',unknown]];
      const series=[...timeline].sort((a,b)=>a[0].localeCompare(b[0]));
      const topCustomers=[...customers].sort((a,b)=>b[1].sales-a[1].sales);
      const productRows=[...products.values()];
      const topProducts=[...productRows].sort((a,b)=>b.qty-a.qty);
      const profitable=[...productRows].sort((a,b)=>(b.sales-b.cost)-(a.sales-a.cost));
      const html=[
        table('1. Sales Revenue Analysis','Booked order value by '+$('repGranularity').value+' period; weeks start Monday.',['Period','Sales'],series,true),
        table('2. Material & Labor Expense Analysis','Saved per-unit cost components multiplied by order quantities. Unknown older components are not guessed.',['Cost component','Amount'],[['Materials (known)',money(material)],['Labor (known)',money(labor)],['Packaging and other (known)',money(other)],['Unclassified historical cost',money(unclassified)],['Total estimated production cost',money(costs)]]),
        table('3. Profitability Analysis','Gross profit uses booked sales less estimated production costs. Other operating expenses are separate.',['Metric','Value'],[['Booked sales',money(sales)],['Production costs',money(costs)],['Estimated gross profit',money(gross)],['Gross profit margin',pct(gross,sales)],['Recorded operating expenses',money(overhead)],['Estimated profit after expenses',money(gross-overhead)]]),
        table('4. Payment Breakdown','Current payment status of non-cancelled orders created in the selected period.',['Payment status','Orders'],Object.entries(status)),
        table('5. Customer Insights','Customer records ranked by booked order value. New customer records: '+[...customers.keys()].filter(k=>match(first.get(k))).length+'. Existing system creates a customer record per order; repeat-customer retention is not reliable until customer reuse is implemented.',['Customer','Orders','Booked sales','Contribution'],topCustomers.map(([k,r])=>[r.name+' (CUST-'+k+')',r.orders,money(r.sales),pct(r.sales,sales)])),
        table('6. Product Insights — Best-selling','Ranked by quantity ordered; current product names are displayed.',['Product','Quantity','Booked sales','Estimated gross profit'],topProducts.map(r=>[r.name,r.qty,money(r.sales),money(r.sales-r.cost)])),
        table('6b. Product Insights — Most profitable','Ranked by estimated gross profit.',['Product','Estimated gross profit','Margin'],profitable.map(r=>[r.name,money(r.sales-r.cost),pct(r.sales-r.cost,r.sales)])),
        table('7. Cash-Flow Tracking','Successful receipts by payment date; outstanding is the current balance of selected orders, not an as-of historical balance. Refunded/cancelled payments are excluded.',['Metric','Amount'],[['Cash-in',money(cash)],['Outstanding balances',money(outstanding)],['Other recorded expenses',money(overhead)]])
      ];
      $('repSections').innerHTML=html.join('');
      $('repExport').disabled=false;$('repPrint').disabled=false;
    }catch(e){$('repStatus').textContent=e.message;$('repSections').innerHTML='';['repRevenue','repMaterial','repLabor','repProfit'].forEach(id=>$(id).textContent='—');}
  }
  function csvCell(v){let s=String(v??'');if(/^[\s]*[=+\-@\t\r]/.test(s))s="'"+s;return '"'+s.replace(/"/g,'""')+'"';}
  $('repExport').addEventListener('click',()=>{
    const rows=[['Sakura Beaded Bags — Financial Report'],['Period',periodLabel],...metrics];
    for(const t of sections){rows.push([], [t.title],[t.desc],t.head,...t.rows.map(r=>t.title.startsWith('1.')?[r[0],money(r[1])]:r));}
    const url=URL.createObjectURL(new Blob(['\ufeff'+rows.map(r=>r.map(csvCell).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8'}));
    const a=document.createElement('a');a.href=url;a.download='sakura-financial-report.csv';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),500);
  });
  $('repPrint').addEventListener('click',()=>window.print());
  ['repPeriod','repMonth','repYear','repFrom','repTo','repGranularity'].forEach(id=>$(id).addEventListener('change',render));
  fetch('api/reports.php',{credentials:'same-origin',cache:'no-store'}).then(async r=>{const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Report unavailable');return d;}).then(d=>{
    data=d;
    const years=[...new Set([String(today.getFullYear()),...d.orders.concat(d.payments,d.expenses).map(x=>x.date.slice(0,4))])].sort().reverse();
    $('repYear').innerHTML=years.map(y=>'<option>'+esc(y)+'</option>').join('');
    render();
  }).catch(e=>{$('repStatus').textContent='Could not load reports: '+e.message;});
})();
