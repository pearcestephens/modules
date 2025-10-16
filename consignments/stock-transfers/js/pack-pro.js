/**
 * Pack Pro JS — Iteration 2
 * - Header kept; table prioritized; sidebar ~400px
 * - SKU merged under name; auto-validate row colors; larger planned badge
 * - Search (max 600px) with rich popover; multi-select; block zero-stock
 * - History: better empty state; capped height; message styling
 * - Freight: two-way sync; manual boxes read-only with warning
 * - Primary CTA: Packed & Ready (save → PACKAGED → upload via SSE)
 */
(function () {
  'use strict';

  const BOOT = window.PACKPRO_BOOT || {};
  const TID  = BOOT.transferId;
  const OUTLET_FROM = BOOT.outletFrom;
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // ---------------- Utilities ----------------
  function $(sel, root=document){ return root.querySelector(sel); }
  function $all(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
  function toast(state, text) {
    let el = $('.auto-save-toast');
    if (!el) { el = document.createElement('div'); el.className = 'auto-save-toast'; el.innerHTML = `<span class="dot"></span><span class="txt"></span>`; document.body.appendChild(el); }
    el.querySelector('.dot').style.background = state === 'saving' ? '#ffc107' : (state === 'saved' ? '#28a745' : '#6c757d');
    el.querySelector('.txt').textContent = text || (state === 'saving' ? 'Saving…' : state === 'saved' ? 'Saved' : 'Idle');
    if (state !== 'saving') setTimeout(()=>{ el.remove(); }, 2000);
  }
  async function jpost(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest', ...(CSRF?{'X-CSRF-Token':CSRF}:{})},
      body: JSON.stringify(data)
    });
    const j = await res.json().catch(()=> ({}));
    if (!res.ok || j.success === false) { throw new Error(j.error || j.message || `HTTP ${res.status}`); }
    return j;
  }
  const debounce = (fn, wait)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), wait); }; };

  // ---------------- Row colours & KPIs ----------------
  function recalcRow(tr){
    const input   = $('.counted', tr);
    const planned = parseInt(input?.dataset.planned || '0', 10) || 0;
    const counted = parseInt(input?.value || '0', 10) || 0;
    tr.classList.remove('row-ok','row-under','row-over','row-zero');
    tr.classList.add(counted === 0 ? 'row-zero' : (counted === planned ? 'row-ok' : (counted < planned ? 'row-under' : 'row-over')));
    return { planned, counted };
  }
  function recalcAll(){
    let countedTotal = 0, plannedTotal = 0;
    $all('#transferTable tbody tr').forEach(tr=>{
      const {planned, counted} = recalcRow(tr);
      countedTotal += counted; plannedTotal += planned;
    });
    const pct = Math.min(100, Math.round(((plannedTotal>0?countedTotal:0) * 100) / (plannedTotal||1)));
    $('#kpiPct') && ($('#kpiPct').textContent = pct);
    const bar = $('.progress-bar'); if (bar) { bar.style.width = pct+'%'; bar.setAttribute('aria-valuenow', String(pct)); }
  }

  // Auto-validate on input
  document.addEventListener('input', e=>{
    if (e.target.classList?.contains('counted')) { recalcRow(e.target.closest('tr')); recalcAll(); dirty(); }
    if (e.target.id === 'internalNotes') { dirty(); }
  });

  // ---------------- Hover preview above cursor ----------------
  let previewEl = null;
  function showPreview(src, x, y){
    if (!src) return;
    if (!previewEl) { previewEl = document.createElement('div'); previewEl.className = 'img-preview'; previewEl.innerHTML = '<img alt="preview">'; document.body.appendChild(previewEl); }
    $('img', previewEl).src = src;
    previewEl.style.left = x + 'px';
    previewEl.style.top  = (y - 12) + 'px'; // slightly above cursor
    previewEl.style.display = 'block';
  }
  function hidePreview(){ if (previewEl) previewEl.style.display='none'; }
  document.addEventListener('mousemove', e=>{
    const cell = e.target.closest('td.td-img');
    if (cell && cell.closest('tr')?.dataset?.fullimg) showPreview(cell.closest('tr').dataset.fullimg, e.pageX, e.pageY);
    else hidePreview();
  });

  // ---------------- Collect & Autosave ----------------
  function collectState(){
    const items = {};
    $all('#transferTable tbody tr').forEach(tr=>{
      const pid = tr.dataset.productId;
      const val = parseInt($('.counted', tr)?.value||'0',10)||0;
      if (pid) items[pid] = val;
    });
    return { transfer_id: TID, notes: $('#internalNotes')?.value || '', items, freight: getFreightState() };
  }

  let dirtyFlag = false;
  const dirty = ()=> (dirtyFlag = true);
  async function saveSession(){
    if (!dirtyFlag) return;
    toast('saving','Saving…');
    try { await jpost('/modules/consignments/api/transfer_ui_state.php', { action:'save', state: collectState() }); dirtyFlag = false; toast('saved','Saved'); }
    catch (e) { toast('idle','Save failed'); console.error(e); }
  }
  setInterval(saveSession, 12000);
  $('#btnSaveDraft')?.addEventListener('click', async ()=>{
    try { await jpost('/modules/consignments/api/transfer_ui_state.php', { action:'save_draft', state: collectState() }); toast('saved','Draft saved'); }
    catch (e) { alert('Draft save failed: '+(e?.message||e)); }
  });
  document.addEventListener('keydown', (e)=>{ if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveSession(); }});

  // Initial KPI
  recalcAll();

  // ---------------- Power Search (max 600px; multi-select; block zero-stock) ----------------
  const searchBox = $('#productSearch');
  const results   = $('#productResults');

  async function doSearch(q) {
    if (!q || q.length < 2) { results.classList.add('d-none'); results.innerHTML=''; return; }
    const j = await jpost('/modules/consignments/api/search_products.php', { q, outlet_id: OUTLET_FROM, limit: 24 });
    const list = j.results || [];
    if (!list.length) { results.innerHTML = '<div class="dropdown-item text-muted">No matches</div>'; results.classList.remove('d-none'); return; }
    results.style.minWidth = Math.min(600, searchBox.getBoundingClientRect().width) + 'px';
    results.innerHTML = list.map(r => {
      const disabled = (r.stock|0) <= 0 ? 'disabled' : '';
      return `
        <a href="#" class="dropdown-item item ${disabled}" data-product-id="${r.product_id}" data-stock="${r.stock||0}">
          ${r.thumb ? `<img src="${r.thumb}" alt="" style="width:28px;height:28px;border-radius:4px;object-fit:cover;">` : `<span class="badge badge-light">No img</span>`}
          <div class="flex-fill ml-2">
            <div><strong>${(r.name||'(Unnamed)')}</strong></div>
            <div class="small text-muted"><span style="font-family: 'Roboto Mono', monospace; font-size: .8rem;">${r.sku||'-'}</span> • Stock: ${r.stock||0}</div>
          </div>
          <div><input
