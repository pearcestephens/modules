<?php
/**
 * Transfer Packing - Layout F (Minimal Mobile-Friendly)
 * Goal: Ultra-clean minimal interface optimized for smaller screens / tablet.
 * Characteristics:
 *  - Single column, collapsible product groups.
 *  - Large touch-friendly inputs & buttons.
 *  - Progressive disclosure (details hidden until expanded).
 */
// Corrected theme include path (was _templates)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';
$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Mobile Mode');
$theme->setPageSubtitle('Minimal interface for tablet operations');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);
$theme->addHeaderButton('Finish', 'javascript:finishPacking()', 'success', 'fa-check');
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>
<style>
.layout-f-container { max-width:1100px; margin:0 auto; padding:10px; }
.group-f { background:#fff; border:1px solid #e1e4e8; border-radius:8px; margin-bottom:12px; }
.group-header-f { padding:14px 16px; font-size:15px; font-weight:600; display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
.group-header-f i { transition: transform .3s; }
.group-header-f.collapsed i { transform:rotate(-90deg); }
.group-body-f { padding:8px 12px; border-top:1px solid #e1e4e8; }
.product-item-f { display:flex; align-items:center; gap:12px; padding:10px 6px; border-bottom:1px solid #f1f4f6; }
.product-item-f:last-child { border-bottom:none; }
.product-item-f img { width:54px; height:54px; object-fit:cover; border-radius:6px; }
.product-item-f .info { flex:1; }
.product-item-f .sku { font-size:11px; text-transform:uppercase; color:#6a737d; }
.qty-input-f { width:86px; font-size:18px; padding:10px 8px; text-align:center; border:2px solid #d1d5db; border-radius:6px; }
.qty-input-f:focus { outline:none; border-color:#0366d6; }
.action-bar-f { position:sticky; bottom:0; background:#fff; padding:10px 12px; display:flex; gap:10px; border-top:1px solid #e1e4e8; }
.action-bar-f button { flex:1; font-size:15px; padding:12px; }
.status-pill-f { font-size:11px; font-weight:600; padding:4px 8px; border-radius:16px; background:#e1e4e8; color:#333; }
@media (max-width:800px){ .product-item-f img { width:44px; height:44px; } .qty-input-f { width:72px; font-size:16px; } }
</style>
<div class="layout-f-container">
  <div id="groupsF"></div>
  <div class="action-bar-f">
    <button class="btn btn-outline-secondary" onclick="saveDraft()">Save Draft</button>
    <button class="btn btn-success" onclick="finishPacking()">Pack & Finish</button>
  </div>
</div>
<script>
const apiUrlF='/modules/consignments/api.php';
function loadGroupsF(){
  const data=[{name:'Starter Kits', items:[{id:301, sku:'KITSTART', name:'Starter Kit Basic', expected:8, packed:2, img:'https://via.placeholder.com/54'},{id:302, sku:'KITPRO', name:'Starter Kit Pro', expected:5, packed:5, img:'https://via.placeholder.com/54'}]},{name:'Consumables', items:[{id:303, sku:'COILX', name:'Advanced Coils', expected:30, packed:10, img:'https://via.placeholder.com/54'},{id:304, sku:'JUICE1', name:'Premium Juice 60ml', expected:20, packed:0, img:'https://via.placeholder.com/54'}]}];
  document.getElementById('groupsF').innerHTML = data.map(renderGroupF).join('');
  bindQtyHandlersF(); bindGroupToggleF();
}
function renderGroupF(g){
  return `<div class=\"group-f\">\n    <div class=\"group-header-f\" data-collapsed=\"false\">${g.name} <i class=\"fa fa-chevron-down\"></i></div>\n    <div class=\"group-body-f\">${g.items.map(i=>renderItemF(i)).join('')}\n    </div>\n  </div>`;
}
function renderItemF(p){
  return `<div class=\"product-item-f\" data-item-id=\"${p.id}\">\n    <img src=\"${p.img}\" alt=\"img\" />\n    <div class=\"info\"><div>${p.name}</div><div class=\"sku\">${p.sku}</div></div>\n    <div>Exp: ${p.expected}</div>\n    <div>Packed: <span class=\"packed-val\">${p.packed}</span></div>\n    <input type=\"number\" class=\"qty-input-f\" value=\"${p.packed}\" min=\"0\" />\n  </div>`;
}
function bindQtyHandlersF(){
  document.querySelectorAll('.qty-input-f').forEach(inp=>{
    inp.addEventListener('change',()=>{ const row=inp.closest('.product-item-f'); const qty=parseInt(inp.value,10); commitQtyF(row,qty); });
  });
}
function commitQtyF(row,qty){
  row.querySelector('.packed-val').textContent=qty;
  const itemId=row.dataset.itemId;
  fetch(apiUrlF,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_item_qty',data:{item_id:parseInt(itemId,10),packed_qty:parseInt(qty,10)}})})
    .then(r=>r.json()).then(r=>{/* handle */}).catch(err=>console.error(err));
}
function bindGroupToggleF(){
  document.querySelectorAll('.group-header-f').forEach(h=>{
    h.addEventListener('click',()=>{
      const collapsed=h.dataset.collapsed==='true';
      h.dataset.collapsed=collapsed?'false':'true';
      h.classList.toggle('collapsed');
      const body=h.nextElementSibling; body.style.display=collapsed?'block':'none';
    });
  });
}
function saveDraft(){ alert('Draft saved (placeholder)'); }
function finishPacking(){ alert('Finished (placeholder)'); }
loadGroupsF();
</script>
<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
