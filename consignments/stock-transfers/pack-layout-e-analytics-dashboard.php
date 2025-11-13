<?php
/**
 * Transfer Packing - Layout E (Analytics Dashboard)
 * Goal: Provide rich real-time analytics & visual KPIs while packing.
 * Characteristics:
 *  - 3-column adaptive grid: (Products | Analytics | Operations)
 *  - Live charts placeholders (RPS, error rate, packing velocity).
 *  - KPI tiles for progress, under/over counts, box utilization.
 */
// Corrected theme include path (was _templates)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';
$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Analytics Mode');
$theme->setPageSubtitle('Real-time KPIs & operational insights');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);
$theme->addHeaderButton('Finish', 'javascript:finishPacking()', 'success', 'fa-check');
$theme->addHeaderButton('AI', 'javascript:openAIAdvisor()', 'secondary', 'fa-robot');
$theme->addHeaderButton('Refresh Analytics', 'javascript:refreshAnalytics()', 'info', 'fa-sync');
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>
<style>
.layout-e-grid { display:grid; grid-template-columns: 1fr 380px 300px; gap:16px; max-width:1600px; margin:0 auto; padding:12px; }
@media (max-width:1500px){ .layout-e-grid { grid-template-columns: 1fr 330px 260px; } }
@media (max-width:1200px){ .layout-e-grid { grid-template-columns: 1fr 320px; } .ops-panel-e { grid-column: span 2; } }
@media (max-width:900px){ .layout-e-grid { grid-template-columns: 1fr; } .analytics-panel-e, .ops-panel-e { order:2; } }
.panel-e { background:#fff; border:1px solid #e1e4e8; border-radius:6px; padding:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
.products-panel-e { display:flex; flex-direction:column; }
.products-header-e { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.product-row-e { display:grid; grid-template-columns: 50px 1fr 70px 70px 60px; gap:10px; padding:8px; border:1px solid #e1e4e8; border-radius:4px; margin-bottom:6px; background:#fff; }
.product-row-e img { width:46px; height:46px; object-fit:cover; border-radius:4px; }
.product-row-e .sku { font-size:11px; text-transform:uppercase; color:#6a737d; }
.qty-input-e { width:60px; padding:4px 6px; font-size:13px; text-align:center; border:2px solid #d1d5db; border-radius:4px; }
.kpi-grid-e { display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom:12px; }
.kpi-tile-e { background:#f6f8fa; border:1px solid #e1e4e8; padding:10px; border-radius:4px; text-align:center; }
.kpi-tile-e h4 { font-size:13px; margin:0 0 6px; font-weight:600; }
.kpi-tile-e .val { font-size:22px; font-weight:700; color:#0366d6; }
.chart-placeholder { height:140px; background:repeating-linear-gradient(45deg,#f6f8fa,#f6f8fa 10px,#e1e4e8 10px,#e1e4e8 20px); border:1px dashed #d1d5db; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:12px; color:#6a737d; }
.ops-panel-e button { width:100%; margin-bottom:8px; font-size:12px; }
.status-pill-e { font-size:10px; font-weight:600; padding:3px 6px; border-radius:12px; background:#e1e4e8; color:#333; }
</style>
<div class="layout-e-grid">
  <div class="panel-e products-panel-e">
    <div class="products-header-e">
      <h5 style="margin:0;font-weight:600;">Products</h5>
      <input type="text" id="filterProducts" placeholder="Filter..." style="width:160px;" />
    </div>
    <div id="productsListE"></div>
  </div>
  <div class="panel-e analytics-panel-e">
    <div class="kpi-grid-e" id="kpiGrid">
      <div class="kpi-tile-e"><h4>Packed</h4><div class="val" id="kpiPacked">0</div></div>
      <div class="kpi-tile-e"><h4>Remaining</h4><div class="val" id="kpiRemaining">0</div></div>
      <div class="kpi-tile-e"><h4>% Complete</h4><div class="val" id="kpiPercent">0%</div></div>
    </div>
    <div class="chart-placeholder" id="chartVelocity">Packing Velocity (Chart)</div>
    <div class="chart-placeholder" id="chartErrors" style="margin-top:12px;">Errors / Exceptions</div>
    <div class="chart-placeholder" id="chartThroughput" style="margin-top:12px;">Throughput (RPS)</div>
  </div>
  <div class="panel-e ops-panel-e">
    <button class="btn btn-outline-secondary btn-sm" onclick="saveDraft()">Save Draft</button>
    <button class="btn btn-outline-info btn-sm" onclick="generateLabels()">Generate Labels</button>
    <button class="btn btn-outline-warning btn-sm" onclick="openBoxPlanner()">Box Planner</button>
    <button class="btn btn-outline-purple btn-sm" onclick="openAIAdvisor()">AI Advisor</button>
    <button class="btn btn-success btn-sm" onclick="finishPacking()">Pack & Finish</button>
  </div>
</div>
<script>
const apiUrlE='/modules/consignments/api.php';
let productsE=[];
function loadProductsE(){
  productsE=[{id:201, sku:'VAPKIT', name:'Vape Starter Kit', expected:12, packed:7, img:'https://via.placeholder.com/46'},{id:202, sku:'COILX', name:'Advanced Coils', expected:30, packed:10, img:'https://via.placeholder.com/46'},{id:203, sku:'JUICE1', name:'Premium Juice 60ml', expected:20, packed:0, img:'https://via.placeholder.com/46'}];
  renderProductsE(); updateKPIs();
}
function renderProductsE(){
  const list=document.getElementById('productsListE');
  list.innerHTML=productsE.map(p=>`<div class=\"product-row-e\" data-item-id=\"${p.id}\">\n    <img src=\"${p.img}\" alt=\"img\" />\n    <div><div>${p.name}</div><div class=\"sku\">${p.sku}</div></div>\n    <div>Exp: ${p.expected}</div>\n    <div>Packed: <span class=\"packed-val\">${p.packed}</span></div>\n    <div><input type=\"number\" class=\"qty-input-e\" value=\"${p.packed}\" min=\"0\" /></div>\n  </div>`).join('');
  list.querySelectorAll('.qty-input-e').forEach(inp=>{
    inp.addEventListener('change',()=>{ const row=inp.closest('.product-row-e'); const qty=parseInt(inp.value,10); commitQtyE(row,qty); });
  });
}
function commitQtyE(row,qty){
  const itemId=row.dataset.itemId;
  row.querySelector('.packed-val').textContent=qty;
  fetch(apiUrlE,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_item_qty',data:{item_id:parseInt(itemId,10),packed_qty:parseInt(qty,10)}})})
    .then(r=>r.json()).then(r=>{ /* handle */ }).catch(err=>console.error(err));
  updateKPIs();
}
function updateKPIs(){
  const totalExpected=productsE.reduce((a,p)=>a+p.expected,0);
  const totalPacked=[...document.querySelectorAll('.product-row-e .packed-val')].reduce((a,el)=>a+parseInt(el.textContent,10),0);
  document.getElementById('kpiPacked').textContent=totalPacked;
  document.getElementById('kpiRemaining').textContent=totalExpected-totalPacked;
  const pct=Math.round((totalPacked/totalExpected)*100);
  document.getElementById('kpiPercent').textContent=pct+'%';
}
function filterProductsE(){
  const term=document.getElementById('filterProducts').value.toLowerCase();
  document.querySelectorAll('.product-row-e').forEach(r=>{
    const match=r.textContent.toLowerCase().includes(term); r.style.display=match?'grid':'none';
  });
}
document.getElementById('filterProducts').addEventListener('input', filterProductsE);
function saveDraft(){ alert('Draft saved (placeholder)'); }
function generateLabels(){ alert('Labels generation placeholder'); }
function openBoxPlanner(){ alert('Box planner placeholder'); }
function openAIAdvisor(){ alert('AI Advisor placeholder'); }
function finishPacking(){ alert('Finished (placeholder)'); }
function refreshAnalytics(){ alert('Analytics refresh placeholder'); }
loadProductsE();
</script>
<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
