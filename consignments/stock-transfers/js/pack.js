/**
 * Pack Pro JS v2
 * - Auto-validate colours & KPIs
 * - Hover preview above cursor
 * - 600px power search (multi-select, block zero-stock)
 * - Autosave (session) + Save Draft to transfers.draft_data
 * - Freight console 2-way sync; manual mode rules
 * - One-button PACKED → upload
 * - Insights: call freight_insights.php and render side boxes
 * - History placeholders when empty
 */
(function () {
  'use strict';

  const BOOT = window.PACKPRO_BOOT || {};
  const TID  = BOOT.transferId;
  const OUTLET_FROM = BOOT.outletFrom;
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const $ = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>Array.from(r.querySelectorAll(s));
  const debounce = (fn,ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
  const toast = (state,text)=>{ let el=$('.auto-save-toast'); if(!el){el=document.createElement('div');el.className='auto-save-toast';el.innerHTML='<span class="dot"></span><span class="txt"></span>';document.body.appendChild(el);} el.querySelector('.dot').style.background=(state==='saving')?'#ffc107':(state==='saved')?'#28a745':'#6b7a90'; el.querySelector('.txt').textContent=text|| (state==='saving'?'Saving…':state==='saved'?'Saved':'Idle'); if(state!=='saving') setTimeout(()=>el.remove(),2000); };
  async function jpost(url, data) {
    const res = await fetch(url, { method:'POST', headers:{'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest', ...(CSRF?{'X-CSRF-Token':CSRF}:{})}, body: JSON.stringify(data) });
    const j = await res.json().catch(()=> ({}));
    if (!res.ok || j.success===false) throw new Error(j.error||j.message||(`HTTP ${res.status}`));
    return j;
  }

  // --- KPI + row colours ---
  function recalcRow(tr){
    const inp=$('.counted',tr);
    const planned=parseInt(inp?.dataset.planned||'0',10)||0;
    const counted=parseInt(inp?.value||'0',10)||0;
    tr.classList.remove('row-ok','row-under','row-over','row-zero');
    tr.classList.add(counted===0?'row-zero':(counted===planned?'row-ok':(counted<planned?'row-under':'row-over')));
    return {planned,counted};
  }
  function recalcAll(){
    let planned=0, counted=0;
    $$('#transferTable tbody tr').forEach(tr=>{ const s=recalcRow(tr); planned+=s.planned; counted+=s.counted; });
    const pct = Math.min(100, Math.round(((planned>0?counted:0)*100)/(planned||1)));
    $('#kpiPct').textContent = pct;
    const bar=$('.progress-bar'); if(bar){bar.style.width=pct+'%'; bar.setAttribute('aria-valuenow',String(pct));}
    refreshInsightsDebounced();
  }

  // --- Hover preview (above) ---
  let pv=null;
  function showPreview(src,x,y){ if(!src) return; if(!pv){ pv=document.createElement('div'); pv.className='img-preview'; pv.innerHTML='<img alt="preview">'; document.body.appendChild(pv);} $('img',pv).src=src; pv.style.left=x+'px'; pv.style.top=(y-12)+'px'; pv.style.display='block'; }
  function hidePreview(){ if(pv) pv.style.display='none'; }
  document.addEventListener('mousemove', e=>{
    const cell = e.target.closest('td.td-img');
    if(cell && cell.closest('tr')?.dataset?.fullimg) showPreview(cell.closest('tr').dataset.fullimg, e.pageX, e.pageY);
    else hidePreview();
  });

  // --- Collect/Autosave ---
  function collectState(){
    const items={}; $$('#transferTable tbody tr').forEach(tr=>{ const pid=tr.dataset.productId; const v=parseInt($('.counted',tr)?.value||'0',10)||0; if(pid) items[pid]=v; });
    return { transfer_id:TID, notes:$('#internalNotes')?.value||'', items, freight:getFreightState() };
  }
  let dirty=false; const markDirty=()=>{ dirty=true; };
  async function saveSession(){ if(!dirty) return; toast('saving','Saving…'); try{ await jpost('/modules/consignments/api/transfer_ui_state.php',{action:'save', state:collectState()}); dirty=false; toast('saved','Saved'); }catch(e){ toast('idle','Save failed'); } }
  setInterval(saveSession, 12000);
  document.addEventListener('input',e=>{
    if(e.target.classList?.contains('counted')){ recalcRow(e.target.closest('tr')); recalcAll(); markDirty(); }
    if(e.target.id==='internalNotes') markDirty();
  });
  $('#btnSaveDraft')?.addEventListener('click', async ()=>{ try{ await jpost('/modules/consignments/api/transfer_ui_state.php',{action:'save_draft', state:collectState()}); toast('saved','Draft saved'); }catch(e){ alert('Draft save failed: '+(e?.message||e)); }});
  document.addEventListener('keydown', e=>{ if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='s'){ e.preventDefault(); saveSession(); }});

  // --- Search (multi-select; block zero-stock) ---
  const searchBox=$('#productSearch'), results=$('#productResults');
  const doSearch = async (q)=>{
    if(!q || q.length<2){ results.classList.add('d-none'); results.innerHTML=''; return; }
    const j = await jpost('/modules/consignments/api/search_products.php',{q, outlet_id:OUTLET_FROM, limit:30});
    const list=j.results||[];
    results.innerHTML = (!list.length)
      ? '<div class="dropdown-item text-muted">No matches</div>'
      : list.map(r=>{
          const disabled=(r.stock|0)<=0?'disabled':'';
          return `<a href="#" class="dropdown-item item ${disabled}" data-product-id="${r.product_id}" data-stock="${r.stock||0}">
            ${r.thumb?`<img src="${r.thumb}" alt="">`:`<span class="badge badge-light">No img</span>`}
            <div class="flex-fill ml-2">
              <div><strong>${r.name||'(Unnamed)'}</strong></div>
              <div class="small text-muted">SKU: ${r.sku||'-'} • Stock: ${r.stock||0}</div>
            </div>
            <div><input type="checkbox" class="sel" ${disabled?'disabled':''}></div>
          </a>`;
        }).join('') + `
        <div class="px-2 py-2 border-top d-flex justify-content-between align-items-center">
          <div class="small text-muted">Select multiple items</div>
          <button class="btn btn-sm btn-primary" id="addSelected">Add selected</button>
        </div>`;
    results.classList.remove('d-none');
  };
  const debSearch = debounce(v=>doSearch(v.trim()), 180);
  if(searchBox){
    searchBox.addEventListener('input',e=>debSearch(e.target.value));
    document.addEventListener('click',e=>{ if(!results.contains(e.target) && e.target!==searchBox) results.classList.add('d-none'); });
    results.addEventListener('click', async e=>{
      const row=e.target.closest('.dropdown-item.item'); if(!row) return;
      e.preventDefault();
      if(e.target.id==='addSelected') return;
      const chk=row.querySelector('.sel'); if(chk && !chk.disabled) chk.checked=!chk.checked;
    });
    results.addEventListener('click', async e=>{
      if(e.target.id!=='addSelected') return;
      const chosen=$$('.dropdown-item.item', results).filter(x=>x.querySelector('.sel')?.checked);
      if(!chosen.length) return;
      try {
        for(const el of chosen){
          const pid=el.dataset.productId; const stock=parseInt(el.dataset.stock||'0',10);
          if(stock<=0) continue; // blocked
          await jpost('/modules/consignments/api/add_product_to_transfer.php',{transfer_id:TID, product_id:pid, qty:1, mode:'increment'});
          injectRowIfMissing(pid, el);
        }
        results.classList.add('d-none'); searchBox.value=''; recalcAll(); markDirty();
      } catch(err){ alert('Add failed: '+(err?.message||err)); }
    });
  }
  function injectRowIfMissing(pid, el){
    const tbody=$('#transferTable tbody');
    if(tbody.querySelector(`tr[data-product-id="${pid}"]`)) return;
    const name = el.querySelector('strong')?.textContent || '(Unnamed)';
    const sku  = (el.querySelector('.small')?.textContent||'').match(/SKU:\s([^•]+)/)?.[1]?.trim() || '';
    const stock= el.dataset.stock||'0';
    const thumb= el.querySelector('img')?.src || '';
    const idx  = tbody.querySelectorAll('tr').length + 1;
    const tr=document.createElement('tr');
    tr.className='row-zero'; tr.dataset.productId=pid; tr.dataset.thumb=thumb; tr.dataset.fullimg=thumb;
    tr.innerHTML = `
      <td class="text-center td-line">${idx}</td>
      <td class="text-center td-img">${thumb?`<img class="thumb" src="${thumb}" alt="">`:'<span class="noimg">—</span>'}</td>
      <td class="td-name text-left"><div class="prod">${name}</div><div class="sku-mono">${sku||'-'}</div></td>
      <td class="text-center td-qty"><span class="text-muted">${stock}</span></td>
      <td class="text-center td-qty"><span class="badge badge-plan">1</span></td>
      <td class="text-center td-qty"><input type="number" class="form-control form-control-sm counted" min="0" step="1" value="0" data-planned="1"></td>`;
    tbody.appendChild(tr);
  }

  // --- Freight console ---
  const trackingList=$('#trackingList'), boxCountEl=$('#boxCount'), boxInput=$('#boxInput'), boxWarn=$('#boxWarn');
  const currentTracking=()=> $$('#trackingList li').map(li=>li.dataset.num);
  const setBoxCount=n=>{ boxCountEl.textContent=String(n); if(boxInput) boxInput.value=String(n); $('#kpiBoxes').textContent=String(n); };
  const renderCount=()=> setBoxCount(currentTracking().length);
  $('#addTracking')?.addEventListener('click', ()=>{
    const inp=$('#tracking_input'); const v=(inp?.value||'').trim(); if(!v) return;
    const li=document.createElement('li'); li.className='list-group-item'; li.dataset.num=v;
    li.innerHTML=`<span class="text-mono">${v}</span><button class="btn-remove" aria-label="remove" title="Remove">&times;</button>`;
    trackingList.appendChild(li); inp.value=''; renderCount(); markDirty(); refreshInsightsDebounced();
  });
  trackingList?.addEventListener('click', e=>{
    if(e.target.classList?.contains('btn-remove')){ e.preventDefault(); e.stopPropagation(); e.target.closest('li')?.remove(); renderCount(); markDirty(); refreshInsightsDebounced(); }
  });
  boxInput?.addEventListener('input', ()=>{
    const desired=Math.max(0, parseInt(boxInput.value||'0',10)||0);
    const have=currentTracking().length;
    if(desired < have){ boxWarn.classList.remove('d-none'); boxInput.value=String(have); }
    else { boxWarn.classList.add('d-none'); }
  });
  $('#btnResetFreight')?.addEventListener('click', ()=>{ trackingList.innerHTML=''; renderCount(); $('#freight_courier').value=''; markDirty(); refreshInsightsDebounced(); });
  $('#btnSaveFreight')?.addEventListener('click', async ()=>{
    const tab=$('#freightTabs .nav-link.active'); const mode=(tab?.getAttribute('href')||'#mode_manual').replace('#mode_','');
    const nums=currentTracking(); const courier=$('#freight_courier')?.value||'';
    const payload={transfer_id:TID, mode, courier_name:courier, tracking_numbers:nums};
    if(mode==='pickup') payload.boxes=parseInt($('#pickup_boxes')?.value||'0',10)||0;
    if(mode==='dropoff') payload.boxes=parseInt($('#dropoff_boxes')?.value||'0',10)||0;
    try { await jpost('/modules/consignments/api/manual_freight.php', payload); alert('Freight saved'); }
    catch(e){ alert('Freight error: '+(e?.message||e)); }
  });
  function getFreightState(){
    const tab=$('#freightTabs .nav-link.active'); const mode=(tab?.getAttribute('href')||'#mode_manual').replace('#mode_','');
    return { mode, courier: $('#freight_courier')?.value||'', tracking: currentTracking() };
  }

  // --- History ---
  async function loadHistory(){
    try {
      const j = await jpost('/modules/consignments/api/get_transfer_history.php', { transfer_id:TID, limit:50 });
      let list = j.items || [];
      const wrap=$('#historyList');
      if (!list.length && j.placeholders) list = j.placeholders; // server provided starter events
      wrap.innerHTML = list.map(row=>{
        const initials=(row.actor_display||row.actor_type||'user').split(/\s+/).map(s=>s[0]).join('').slice(0,2).toUpperCase();
        const when=(row.created_at||'').replace('T',' ').slice(0,16);
        return `<div class="history-item ${row.kind||''}">
          <div class="avatar">${initials}</div>
          <div class="flex-fill">
            <div class="meta"><span class="name">${row.actor_display||row.actor_type}</span> • ${when}</div>
            <div class="text">${row.text||''}</div>
          </div>
        </div>`;
      }).join('');
    } catch(e){ console.error(e); }
  }
  loadHistory();
  $('#btnAddComment')?.addEventListener('click', async ()=>{
    const input=$('#commentInput'); const note=(input?.value||'').trim(); if(!note) return;
    try { await jpost('/modules/consignments/api/add_transfer_note.php',{transfer_id:TID, note_text:note}); input.value=''; loadHistory(); }
    catch(e){ alert('Failed to add note: '+(e?.message||e)); }
  });

  // --- Packed & Ready → upload ---
  function openProgress(){ try{ $('#uploadProgressModal') && window.jQuery && jQuery('#uploadProgressModal').modal({backdrop:'static',keyboard:false}); }catch(e){} }
  function onSSE(m){
    try { const op=$('#uploadOperation'), bar=$('#uploadProgressBar'), log=$('#uploadLog');
      if(m.message){ const li=document.createElement('li'); li.textContent=m.message; log.prepend(li); }
      if(typeof m.progress_percentage==='number' && bar) bar.style.width=m.progress_percentage+'%';
      if(op && m.status) op.textContent=m.status;
      if(m.status==='completed') setTimeout(()=>location.reload(), 600);
    } catch(e){}
  }
  $('#btnPackedReady')?.addEventListener('click', async ()=>{
    try {
      await jpost('/modules/consignments/api/transfer_ui_state.php',{action:'save', state:collectState()});
      await jpost('/modules/consignments/api/transfer_ui_state.php',{action:'set_state', transfer_id:TID, state:'PACKAGED'});
      openProgress();
      const itemsObj=collectState().items;
      const items=Object.entries(itemsObj).map(([product_id, counted_qty])=>({product_id, counted_qty}));
      const contract=await jpost('/modules/consignments/api/api.php',{action:'submit_transfer', transfer_id:TID, items, notes:$('#internalNotes')?.value||''});
      const es=new EventSource(contract.progress_url);
      es.addEventListener('progress', e=>{ try{ onSSE(JSON.parse(e.data)); }catch(_){} });
      es.onerror=()=>{};
      const fd=new FormData(); fd.append('transfer_id', String(TID)); fd.append('session_id', contract.upload_session_id);
      await fetch(contract.upload_url, {method:'POST', body:fd});
    } catch(e){ alert('Operation failed: '+(e?.message||e)); }
  });
  $('#actCancel')?.addEventListener('click', async e=>{
    e.preventDefault(); if(!confirm('Cancel this transfer?')) return;
    try { await jpost('/modules/consignments/api/transfer_ui_state.php',{action:'set_state', transfer_id:TID, state:'CANCELLED'}); location.reload(); }
    catch(err){ alert('Cancel failed: '+(err?.message||err)); }
  });

  // --- Insights ---
  const refreshInsights = async ()=>{
    try {
      const lines=[]; $$('#transferTable tbody tr').forEach(tr=>{ const pid=tr.dataset.productId; const q=parseInt($('.counted',tr)?.value||'0',10)||0; const p=parseInt($('.counted',tr)?.dataset.planned||'0',10)||0; lines.push([pid, q>0?q:p]); });
      const js = await jpost('/modules/consignments/api/freight_insights.php', { transfer_id:TID, lines });
      const el=$('#freightInsights');
      if (!js || !js.success) { el.innerHTML = `<div class="text-danger">${(js&&js.error)||'Freight insights unavailable'}</div>`; return; }
      const i = js.insights||{};
      const warn = js.warnings||[];
      el.innerHTML = `
        <div class="mb-2"><strong>Total weight:</strong> ${(i.total_weight_g||0)} g (${((i.total_weight_g||0)/1000).toFixed(2)} kg)</div>
        <div class="mb-2"><strong>Container pick:</strong> ${(i.pick && i.pick.container_name) ? i.pick.container_name : (i.pick && i.pick.container_code) ? i.pick.container_code : '—'}
          ${i.pick && i.pick.utilization_pct ? ` · util ${i.pick.utilization_pct}%` : ''}</div>
        <div class="mb-2"><strong>DB cost (est):</strong> ${i.db_cost ? '$'+i.db_cost.toFixed(2) : '—'}</div>
        <div class="mb-2"><strong>Coverage:</strong> P=${i.coverage?.P||0} · C=${i.coverage?.C||0} · D=${i.coverage?.D||0}</div>
        ${warn.length? `<div class="alert alert-warning py-1">${warn.map(w=>`<div>• ${w}</div>`).join('')}</div>`:''}
      `;
    } catch(e){
      $('#freightInsights').innerHTML = `<div class="text-danger">Freight insights error: ${e?.message||e}</div>`;
    }
  };
  const refreshInsightsDebounced = debounce(refreshInsights, 400);
  refreshInsights();

  // init
  recalcAll();
})();
