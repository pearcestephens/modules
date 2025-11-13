<?php
/**
 * Transfer Packing - Layout D (Compact Scan-Focused)
 * Goal: Maximise speed for barcode scanning & rapid qty adjustments.
 * Characteristics:
 *  - Single column list with sticky scan input bar on top.
 *  - Minimal chrome, 11px headers, 13px body font.
 *  - Real-time pulse feedback on successful updates.
 *  - Inline status chips (OK / UNDER / OVER) with color-coded left bars.
 */

// Corrected theme include path (was _templates)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';
$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Compact Scan Mode');
$theme->setPageSubtitle('Fast barcode scanning & inline qty updates');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);
$theme->addHeaderButton('Finish', 'javascript:finishPacking()', 'success', 'fa-check');
$theme->addHeaderButton('AI', 'javascript:openAIAdvisor()', 'secondary', 'fa-robot');

$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>
<style>
.layout-d-container { max-width: 1500px; margin:0 auto; padding:12px; }
.scan-bar-d { position:sticky; top:10px; z-index:50; background:#fff; border:1px solid #e1e4e8; padding:10px 12px; border-radius:6px; display:flex; gap:10px; box-shadow:0 2px 4px rgba(0,0,0,0.06); }
.scan-bar-d input { flex:1; padding:8px 10px; font-size:14px; border:2px solid #d1d5db; border-radius:4px; }
.scan-bar-d input:focus { outline:none; border-color:#0366d6; }
.scan-bar-d .mode-toggle { display:flex; gap:6px; }
.scan-bar-d button { font-size:12px; padding:6px 10px; }
.products-d { margin-top:14px; }
.product-row-d { display:grid; grid-template-columns: 52px 1fr 120px 90px 70px 90px; gap:10px; align-items:center; padding:8px 10px; background:#fff; border:1px solid #e1e4e8; margin-bottom:6px; border-left:5px solid transparent; transition:background .15s, border-color .3s; }
.product-row-d:hover { background:#f6f8fa; }
.product-row-d.ok { border-left-color:#28a745; }
.product-row-d.under { border-left-color:#ffc107; }
.product-row-d.over { border-left-color:#dc3545; }
.product-row-d.zero { border-left-color:#6a737d; }
.product-row-d img { width:48px; height:48px; object-fit:cover; border-radius:4px; }
.product-row-d .sku { font-size:11px; text-transform:uppercase; color:#6a737d; }
.qty-edit-d { width:70px; padding:4px 6px; font-size:13px; text-align:center; border:2px solid #d1d5db; border-radius:4px; }
.qty-edit-d:focus { outline:none; border-color:#0366d6; }
.status-chip-d { font-size:11px; font-weight:600; padding:4px 6px; border-radius:4px; }
.status-chip-d.ok { background:#e6f4ea; color:#276749; }
.status-chip-d.under { background:#fff8e1; color:#8d6e00; }
.status-chip-d.over { background:#ffebee; color:#b71c1c; }
.status-chip-d.zero { background:#e1e4e8; color:#444; }
.update-pulse { animation:pulse .9s ease-out; }
@keyframes pulse { 0% { box-shadow:0 0 0 0 rgba(3,102,214,0.6);} 70% { box-shadow:0 0 0 8px rgba(3,102,214,0);} 100% { box-shadow:0 0 0 0 rgba(3,102,214,0);} }

.footer-actions-d { position:sticky; bottom:0; background:#fff; padding:10px 12px; border-top:1px solid #e1e4e8; display:flex; gap:10px; justify-content:flex-end; }
.footer-actions-d button { font-size:12px; }
</style>
<div class="layout-d-container">
  <div class="scan-bar-d">
    <input type="text" id="scanInput" placeholder="Scan barcode or type SKU..." autocomplete="off" />
    <div class="mode-toggle">
      <button class="btn btn-outline-secondary btn-sm" data-mode="increment" title="Increment Mode">+1</button>
      <button class="btn btn-outline-secondary btn-sm" data-mode="direct" title="Direct Entry Mode">Direct</button>
    </div>
    <button class="btn btn-primary btn-sm" onclick="focusScan()"><i class="fa fa-barcode"></i> Focus</button>
  </div>
  <div class="products-d" id="productsList"></div>
  <div class="footer-actions-d">
    <button class="btn btn-outline-secondary btn-sm" onclick="saveDraft()">Save Draft</button>
    <button class="btn btn-success btn-sm" onclick="finishPacking()">Pack & Finish</button>
  </div>
</div>
<script>
const apiUrl = '/modules/consignments/api.php';
let mode = 'increment';
const productsEl = document.getElementById('productsList');
function focusScan(){ document.getElementById('scanInput').focus(); }

document.querySelectorAll('.scan-bar-d [data-mode]').forEach(btn=>{
  btn.addEventListener('click',()=>{ mode = btn.dataset.mode; document.querySelectorAll('.scan-bar-d [data-mode]').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); });
});

document.getElementById('scanInput').addEventListener('keydown',e=>{
  if(e.key==='Enter'){ const code = e.target.value.trim(); if(code){ handleScan(code); e.target.value=''; } }
});

function loadProducts(){
  // Placeholder: would fetch transfer items
  const sample=[
    {id:101, sku:'XROS3KIT', name:'Vaporesso XROS 3 Kit', expected:10, packed:0, img:'https://via.placeholder.com/48'},
    {id:102, sku:'NORDPODS', name:'SMOK Nord Pods', expected:15, packed:3, img:'https://via.placeholder.com/48'},
    {id:103, sku:'CALICOILS', name:'Caliburn Coils', expected:5, packed:5, img:'https://via.placeholder.com/48'}
  ];
  productsEl.innerHTML = sample.map(renderRow).join('');
}
function renderRow(p){
  const status = p.packed===0?'zero':(p.packed===p.expected?'ok':(p.packed<p.expected?'under':'over'));
  return `<div class="product-row-d ${status}" data-item-id="${p.id}">
    <img src="${p.img}" alt="img" />
    <div><div class="name">${p.name}</div><div class="sku">${p.sku}</div></div>
    <div class="expected">Exp: ${p.expected}</div>
    <div class="packed">Packed: <span class="packed-val">${p.packed}</span></div>
    <div><input class="qty-edit-d" type="number" min="0" value="${p.packed}" /></div>
    <div><span class="status-chip-d ${status}">${status.toUpperCase()}</span></div>
  </div>`;
}
function handleScan(code){
  const row=[...document.querySelectorAll('.product-row-d')].find(r=>r.querySelector('.sku').textContent===code);
  if(!row){ flashNotFound(code); return; }
  const input=row.querySelector('.qty-edit-d');
  let val=parseInt(input.value||'0',10);
  if(mode==='increment'){ val+=1; } else { val = parseInt(code.split(':')[1]||val,10); }
  input.value=val; commitQty(row,val);
}
function flashNotFound(code){
  const bar=document.getElementById('scanInput');
  bar.classList.add('is-invalid');
  setTimeout(()=>bar.classList.remove('is-invalid'),600);
}
function commitQty(row,newQty){
  const itemId=row.dataset.itemId;
  row.querySelector('.packed-val').textContent=newQty;
  row.classList.add('update-pulse');
  setTimeout(()=>row.classList.remove('update-pulse'),900);
  // POST to API
  fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_item_qty',data:{item_id:parseInt(itemId,10),packed_qty:parseInt(newQty,10)}})})
    .then(r=>r.json()).then(resp=>{ /* handle resp */ }).catch(err=>console.error(err));
  updateStatusChip(row,newQty);
}
function updateStatusChip(row,qty){
  const expected=parseInt(row.querySelector('.expected').textContent.replace(/[^0-9]/g,''),10);
  const chip=row.querySelector('.status-chip-d');
  row.classList.remove('ok','under','over','zero');
  let status='';
  if(qty===0){ status='zero'; }
  else if(qty===expected){ status='ok'; }
  else if(qty<expected){ status='under'; }
  else { status='over'; }
  row.classList.add(status); chip.textContent=status.toUpperCase(); chip.className='status-chip-d '+status;
}
function saveDraft(){ alert('Draft saved (placeholder).'); }
function finishPacking(){ alert('Packing finished (placeholder).'); }
function openAIAdvisor(){ alert('AI Advisor (placeholder).'); }
loadProducts();
</script>
<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
