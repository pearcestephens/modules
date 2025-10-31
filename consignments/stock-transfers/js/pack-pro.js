/**
 * Pack Pro JS (v4 — MAX)
 * - Robust fetch with retries, backoff, and X-Request-ID
 * - SSE with auto-reconnect, completion/abort guards
 * - Keyboard-first power search (/, ↑/↓, Enter, Esc, Ctrl+Enter to add)
 * - Optimistic product add + de-dupe + highlight + scroll-into-view
 * - Unified Boxes state (manual ⇄ tracking lines, pickup/dropoff, KPI, pill)
 * - Local draft autosave + recover-on-reload (per transfer)
 * - Safer submit (idempotent-ish client guards, double-click proof)
 * - Online/offline awareness; beforeunload guard when dirty
 * - Zero dependencies (vanilla) — jQuery only used to open Bootstrap modal if present
 */
(function () {
  'use strict';

  // ---------------- Boot / Constants ----------------
  const BOOT = window.PACKPRO_BOOT || {};
  const TID  = BOOT.transferId;
  const OUTLET_FROM = BOOT.outletFrom;
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const LS_KEY = `packpro:${TID}:draft`;
  const MAX_RETRIES = 3;
  const BASE_BACKOFF_MS = 300;

  // Endpoints (unchanged contracts)
  const URLS = {
    SAVE_UI: '/modules/consignments/api/transfer_ui_state.php',
    API:     '/modules/consignments/api/api.php',
    SEARCH:  '/modules/consignments/api/search_products.php',
    ADD:     '/modules/consignments/api/add_product_to_transfer.php',
    NOTE:    '/modules/consignments/api/add_transfer_note.php',
    HIST:    '/modules/consignments/api/get_transfer_history.php',
    FREIGHT: '/modules/consignments/api/manual_freight.php'
  };

  // ---------------- Small helpers ----------------
  const $  = (sel, root=document)=> root.querySelector(sel);
  const $$ = (sel, root=document)=> Array.from(root.querySelectorAll(sel));
  const sleep = (ms)=> new Promise(r=> setTimeout(r, ms));
  const nowISO = ()=> new Date().toISOString();

  // RFC4122-ish (not cryptographic; good enough for request correlation)
  function rid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c=>{
      const r = Math.random()*16|0, v = c==='x'? r : (r&0x3|0x8);
      return v.toString(16);
    });
  }

  // Network status UI nudge (subtle)
  function netToast(text) {
    let el = $('.net-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'net-toast';
      el.style.cssText = 'position:fixed;left:50%;top:10px;transform:translateX(-50%);background:#111;color:#fff;padding:6px 10px;border-radius:10px;font-size:12px;opacity:.92;z-index:99999';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(()=> el.style.display='none', 2000);
  }

  // ---------------- fetch with retry/backoff + JSON guard ----------------
  async function jpost(url, data, opts={}) {
    const requestId = opts.requestId || rid();
    let attempt = 0, lastErr;

    while (attempt <= MAX_RETRIES) {
      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Request-ID': requestId,
            ...(CSRF ? { 'X-CSRF-Token': CSRF } : {})
          },
          body: JSON.stringify(data)
        });

        // parse JSON safely
        let j = {};
        try { j = await res.json(); } catch (_) { j = {}; }

        if (!res.ok || j.success === false) {
          // Retry on network-ish / transient HTTPs
          if ([429, 500, 502, 503, 504].includes(res.status) && attempt < MAX_RETRIES) {
            const backoff = BASE_BACKOFF_MS * Math.pow(2, attempt) + Math.random()*120;
            await sleep(backoff); attempt++; continue;
          }
          const msg = j.error || j.message || `HTTP ${res.status}`;
          const err = new Error(msg);
          err.status = res.status; err.payload = j; err.requestId = requestId;
          throw err;
        }
        // success
        return j;
      } catch (e) {
        lastErr = e;
        if (attempt < MAX_RETRIES) {
          const backoff = BASE_BACKOFF_MS * Math.pow(2, attempt) + Math.random()*120;
          await sleep(backoff); attempt++; continue;
        }
        e.requestId = requestId;
        throw e;
      }
    }
    // If we fall through
    lastErr = lastErr || new Error('Unknown network error');
    lastErr.requestId = lastErr.requestId || requestId;
    throw lastErr;
  }

  const debounce = (fn, wait)=> { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), wait); }; };

  // ---------------- Dirty / autosave / beforeunload ----------------
  let dirtyFlag = false;
  function dirty(){ dirtyFlag = true; }
  async function saveSession(){
    if (!dirtyFlag) return;
    try {
      const state = collectState();
      localStorage.setItem(LS_KEY, JSON.stringify({ t: Date.now(), state }));
      await jpost(URLS.SAVE_UI, { action:'save', state });
      dirtyFlag = false;
    } catch (e) {
      console.error('Autosave failed', e);
    }
  }
  setInterval(saveSession, 12000);
  window.addEventListener('beforeunload', (e)=>{
    if (!dirtyFlag) return;
    try { localStorage.setItem(LS_KEY, JSON.stringify({ t: Date.now(), state: collectState() })); } catch(_){}
    e.preventDefault(); e.returnValue = '';
  });

  // Offer to restore a draft (if newer than 48h)
  (function tryRestoreDraft(){
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return;
      const { t, state } = JSON.parse(raw);
      if (!state || (Date.now()-t) > 48*3600*1000) return;
      // only restore counts + notes (freight is live-synced anyway)
      if (state.items) {
        Object.entries(state.items).forEach(([pid, counted])=>{
          const row = document.querySelector(`#transferTable tbody tr[data-product-id="${pid}"]`);
          if (row) {
            const input = row.querySelector('.counted');
            if (input) input.value = String(counted);
          }
        });
      }
      if (state.notes) { const n = $('#internalNotes'); if (n) n.value = state.notes; }
      recalcAll();
      netToast('Recovered unsaved draft');
    } catch (_){}
  })();

  // ---------------- Network status awareness ----------------
  window.addEventListener('online',  ()=> netToast('Back online'));
  window.addEventListener('offline', ()=> netToast('You’re offline'));

  // ---------------- Boxes (single source of truth) ----------------
  const kpiBoxes   = $('#kpiBoxes');
  const boxPill    = $('#boxCount');
  const boxMinus   = $('#boxMinus');
  const boxPlus    = $('#boxPlus');
  const pickupBoxes  = $('#pickup_boxes');
  const dropoffBoxes = $('#dropoff_boxes');

  let BOXES = parseInt(String(BOOT.boxes ?? kpiBoxes?.textContent ?? '0'), 10) || 0;

  function setBoxes(n, source) {
    n = Math.max(0, parseInt(String(n), 10) || 0);
    BOXES = n;
    if (kpiBoxes)  kpiBoxes.textContent = String(n);
    if (boxPill)   boxPill.textContent  = String(n);
    if (pickupBoxes && source !== 'pickup')   pickupBoxes.value  = String(n);
    if (dropoffBoxes && source !== 'dropoff') dropoffBoxes.value = String(n);
    dirty(); // freight is part of UI state
  }
  function currentMode(){
    const active = $('#freightTabs .nav-link.active');
    return active ? (active.getAttribute('href')||'#').replace('#mode_','') : 'manual';
  }

  // Manual → boxes follow tracking count
  const trackingList = $('#trackingList');
  function trackingNumbers(){ return $$('#trackingList li').map(li => li.dataset.num); }
  function renderTrackingCount(){ setBoxes(trackingNumbers().length, 'tracking'); }

  $('#addTracking')?.addEventListener('click', ()=>{
    const inp = $('#tracking_input'); const v = (inp?.value||'').trim(); if (!v) return;
    if (trackingNumbers().includes(v)) { netToast('Already added'); return; }
    const li = document.createElement('li');
    li.className='list-group-item'; li.dataset.num=v;
    li.innerHTML = `<span class="text-mono">${v}</span><button class="btn-remove" aria-label="remove" title="Remove">&times;</button>`;
    trackingList.appendChild(li); inp.value=''; renderTrackingCount();
  });
  trackingList?.addEventListener('click', e=>{
    if (e.target.classList?.contains('btn-remove')) {
      e.preventDefault(); e.target.closest('li')?.remove(); renderTrackingCount();
    }
  });

  // Stepper (disabled in manual mode)
  boxMinus?.addEventListener('click', ()=> {
    if (currentMode() === 'manual') return; // boxes = tracking count
    setBoxes(Math.max(0, BOXES-1), 'stepper');
  });
  boxPlus?.addEventListener('click', ()=> {
    if (currentMode() === 'manual') return;
    setBoxes(BOXES+1, 'stepper');
  });
  pickupBoxes?.addEventListener('input',  ()=> setBoxes(pickupBoxes.value,  'pickup'));
  dropoffBoxes?.addEventListener('input', ()=> setBoxes(dropoffBoxes.value, 'dropoff'));

  // ---------------- Table row states & KPI ----------------
  function recalcRow(tr){
    const input = $('.counted', tr);
    const planned = parseInt(input?.dataset.planned || '0', 10) || 0;
    const counted = parseInt(input?.value || '0', 10) || 0;
    tr.classList.remove('row-ok','row-under','row-over','row-zero');
    tr.classList.add(counted === 0 ? 'row-zero' : (counted === planned ? 'row-ok' : (counted < planned ? 'row-under' : 'row-over')));
    return { planned, counted };
  }
  function recalcAll(){
    let countedTotal = 0, plannedTotal = 0;
    $$('#transferTable tbody tr').forEach(tr=>{
      const {planned, counted} = recalcRow(tr);
      countedTotal += counted; plannedTotal += planned;
    });
    const pct = Math.min(100, Math.round(((plannedTotal>0?countedTotal:0) * 100) / (plannedTotal||1)));
    $('#kpiPct').textContent = String(pct);
    const bar = $('.progress-bar');
    if (bar) { bar.style.width = pct+'%'; bar.setAttribute('aria-valuenow', String(pct)); }
  }
  document.addEventListener('input', e=>{
    if (e.target.classList?.contains('counted')) { recalcRow(e.target.closest('tr')); recalcAll(); dirty(); }
    if (e.target.id === 'internalNotes') { dirty(); }
  });
  recalcAll();

  // Keyboard niceties on number inputs (arrow increments; shift=±10)
  document.addEventListener('keydown', (e)=>{
    const t = e.target;
    if (t && t.classList?.contains('counted')) {
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        const delta = (e.key === 'ArrowUp' ? 1 : -1) * (e.shiftKey ? 10 : 1);
        const v = Math.max(0, (parseInt(t.value||'0',10)||0) + delta);
        t.value = String(v);
        recalcRow(t.closest('tr')); recalcAll(); dirty();
      }
      if (e.key === 'Enter') {
        // move focus to next row input
        const inputs = $$('.counted');
        const idx = inputs.indexOf(t);
        if (idx >= 0 && idx < inputs.length-1) { inputs[idx+1].focus(); inputs[idx+1].select(); }
      }
    }
  });

  // Double-click a row to set counted = planned (fast fill)
  $('#transferTable')?.addEventListener('dblclick', (e)=>{
    const tr = e.target.closest('tr'); if (!tr) return;
    const inp = $('.counted', tr); if (!inp) return;
    const planned = parseInt(inp.dataset.planned||'0',10)||0;
    inp.value = String(planned); recalcRow(tr); recalcAll(); dirty();
  });

  // ---------------- Image hover preview (kept) ----------------
  let previewEl = null;
  function showPreview(src, x, y){
    if (!src) return;
    if (!previewEl) { previewEl = document.createElement('div'); previewEl.className = 'img-preview'; previewEl.innerHTML = '<img alt="preview">'; document.body.appendChild(previewEl); }
    $('img', previewEl).src = src;
    previewEl.style.left = x + 'px';
    previewEl.style.top  = (y - 12) + 'px';
    previewEl.style.display = 'block';
  }
  function hidePreview(){ if (previewEl) previewEl.style.display='none'; }
  document.addEventListener('mousemove', e=>{
    const cell = e.target.closest('td.td-img');
    if (cell && cell.closest('tr')?.dataset?.fullimg) {
      showPreview(cell.closest('tr').dataset.fullimg, e.pageX, e.pageY);
    } else hidePreview();
  });

  // ---------------- Power Search (keyboard-first + cache) ----------------
  const searchBox = $('#productSearch');
  const results   = $('#productResults');
  const cache = new Map(); // q -> [{...}]
  const debSearch = debounce(v => doSearch(v.trim()), 150);

  if (searchBox) {
    // Focus shortcut "/"
    document.addEventListener('keydown', (e)=>{ if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) { e.preventDefault(); searchBox.focus(); } });
    // Typing triggers search
    searchBox.addEventListener('input', e => debSearch(e.target.value));

    // Keyboard nav within dropdown
    searchBox.addEventListener('keydown', (e)=>{
      if (results.classList.contains('d-none')) return;
      const items = $$('.dropdown-item.item', results);
      if (!items.length) return;
      const cur = results.querySelector('.dropdown-item.item.active');
      let idx = cur ? items.indexOf(cur) : -1;

      if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(items.length-1, idx+1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(0, idx-1); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        if (cur) {
          const chk = cur.querySelector('.sel'); if (chk && !chk.disabled) chk.checked = !chk.checked;
        } else {
          // no active — toggle first
          const first = items[0]; first && first.classList.add('active');
        }
      } else if (e.key === 'Escape') {
        results.classList.add('d-none');
      } else if (e.key.toLowerCase() === 'enter' && e.ctrlKey) {
        // Add selected (Ctrl+Enter)
        results.querySelector('#addSelected')?.click();
      }

      items.forEach(el=> el.classList.remove('active'));
      if (idx >= 0 && items[idx]) { items[idx].classList.add('active'); items[idx].scrollIntoView({ block:'nearest' }); }
    });

    // Close dropdown clicking outside
    document.addEventListener('click', e => { if (!results.contains(e.target) && e.target !== searchBox) { results.classList.add('d-none'); }});

    // Mouse interactions
    results.addEventListener('mousemove', (e)=>{
      const row = e.target.closest('.dropdown-item.item'); if (!row) return;
      $$('.dropdown-item.item', results).forEach(el=> el.classList.remove('active'));
      row.classList.add('active');
    });

    // Toggle check / collect selected
    results.addEventListener('click', (e)=>{
      const row = e.target.closest('.dropdown-item.item'); 
      if (!row) return;
      if (e.target.id === 'addSelected') return; // handled below
      e.preventDefault();
      const chk = row.querySelector('.sel'); if (chk && !chk.disabled) chk.checked = !chk.checked;
    });

    // Add selected (with optimistic UI)
    results.addEventListener('click', async (e)=>{
      if (e.target.id !== 'addSelected') return;
      const chosen = $$('.dropdown-item.item', results).filter(x=> x.querySelector('.sel')?.checked);
      if (!chosen.length) return;

      // Disable to prevent double-submission
      e.target.disabled = true;
      try {
        for (const el of chosen) {
          const pid = el.dataset.productId;
          const stock = parseInt(el.dataset.stock||'0',10);
          if (stock <= 0) continue;
          if (document.querySelector(`#transferTable tbody tr[data-product-id="${pid}"]`)) continue; // de-dupe

          // Optimistic row injection
          injectOrRefreshRow(pid, el);
          recalcAll(); dirty();

          // Server call
          await jpost(URLS.ADD, { transfer_id: TID, product_id: pid, qty: 1, mode: 'increment' });
        }
        results.classList.add('d-none'); searchBox.value='';
      } catch (err) {
        alert('Add failed: '+(err?.message||err));
      } finally {
        e.target.disabled = false;
      }
    });
  }

  async function doSearch(q) {
    if (!q || q.length < 2) { results.classList.add('d-none'); results.innerHTML=''; return; }
    if (cache.has(q)) return renderResults(cache.get(q));
    try {
      const j = await jpost(URLS.SEARCH, { q, outlet_id: OUTLET_FROM, limit: 24 });
      const list = j.results || [];
      cache.set(q, list);
      renderResults(list);
    } catch (e) {
      results.innerHTML = `<div class="dropdown-item text-danger">Search error: ${e?.message||e}</div>`;
      results.classList.remove('d-none');
    }
  }
  function renderResults(list){
    if (!list.length) {
      results.innerHTML = '<div class="dropdown-item text-muted">No matches</div>';
      results.classList.remove('d-none'); 
      return;
    }
    results.innerHTML = list.map(r => {
      const disabled = (r.stock|0) <= 0 ? 'disabled' : '';
      return `
        <a href="#" class="dropdown-item item ${disabled}" data-product-id="${r.product_id}" data-stock="${r.stock||0}">
          ${r.thumb ? `<img src="${r.thumb}" alt="" class="res-thumb">` : `<span class="badge badge-light">No img</span>`}
          <div class="flex-fill ml-2">
            <div><strong>${(r.name||'(Unnamed)')}</strong></div>
            <div class="small text-muted">SKU: ${r.sku||'-'} • Stock: ${r.stock||0}</div>
          </div>
          <div>
            <input type="checkbox" class="sel" ${disabled?'disabled':''}>
          </div>
        </a>`;
    }).join('') + `
      <div class="px-2 py-2 border-top d-flex justify-content-between align-items-center">
        <div class="small text-muted">Select multiple items (Ctrl+Enter to add)</div>
        <button class="btn btn-sm btn-primary" id="addSelected">Add selected</button>
      </div>`;
    results.classList.remove('d-none');
  }

  function injectOrRefreshRow(pid, el){
    const tBody = $('#transferTable tbody');
    const existing = tBody.querySelector(`tr[data-product-id="${pid}"]`);
    if (existing) return;

    const name = el.querySelector('strong')?.textContent || '(Unnamed)';
    const sku  = el.querySelector('.small')?.textContent?.match(/SKU:\s([^•]+)/)?.[1]?.trim() || '';
    const stock= el.dataset.stock || '0';
    const thumbEl = el.querySelector('img'); const thumb = thumbEl ? thumbEl.src : '';
    const idx  = tBody.querySelectorAll('tr').length + 1;

    const tr = document.createElement('tr');
    tr.className = 'row-zero new-insert';
    tr.dataset.productId = pid;
    tr.dataset.thumb = thumb;
    tr.dataset.fullimg = thumb;
    tr.innerHTML = `
      <td class="text-center td-line">${idx}</td>
      <td class="text-center td-img">${thumb? `<img class="thumb" src="${thumb}" alt="">` : '<span class="noimg">—</span>'}</td>
      <td class="td-name text-left">
        <div class="prod">${name}</div>
        <div class="sku-mono">${sku||'-'}</div>
      </td>
      <td class="text-center td-qty"><span class="text-muted">${stock}</span></td>
      <td class="text-center td-qty"><span class="badge badge-plan">1</span></td>
      <td class="text-center td-qty">
        <input type="number" class="form-control form-control-sm counted" min="0" step="1" value="0" data-planned="1" inputmode="numeric">
      </td>`;
    tBody.appendChild(tr);
    tr.scrollIntoView({ block: 'nearest' });
    setTimeout(()=> tr.classList.remove('new-insert'), 900);
  }

  // ---------------- History ----------------
  async function loadHistory(){
    try {
      const j = await jpost(URLS.HIST, { transfer_id: TID, limit: 50 });
      const list = j.items || [];
      const wrap = $('#historyList');
      wrap.innerHTML = list.map(row => {
        const initials = (row.actor_display || row.actor_type || 'user').split(/\s+/).map(s=>s[0]).join('').slice(0,2).toUpperCase();
        const when = row.created_at?.replace('T',' ').slice(0,16) || '';
        return `<div class="history-item ${row.kind||''}">
          <div class="avatar">${initials}</div>
          <div class="flex-fill">
            <div class="meta"><span class="name">${row.actor_display || row.actor_type}</span> • ${when}</div>
            <div class="text">${row.text || ''}</div>
          </div>
        </div>`;
      }).join('');
    } catch (e) { console.error(e); }
  }
  loadHistory();

  $('#btnAddComment')?.addEventListener('click', async ()=>{
    const input = $('#commentInput'); const note = (input?.value || '').trim(); if (!note) return;
    try { await jpost(URLS.NOTE, { transfer_id: TID, note_text: note }); input.value=''; loadHistory(); }
    catch (e) { alert('Failed to add note: '+(e?.message||e)); }
  });

  // ---------------- Collect full state ----------------
  function collectState(){
    const items = {};
    $$('#transferTable tbody tr').forEach(tr=>{
      const pid = tr.dataset.productId;
      const val = parseInt($('.counted', tr)?.value||'0',10)||0;
      if (pid) items[pid] = val;
    });
    const mode = currentMode();
    const boxes = (mode === 'manual') ? trackingNumbers().length : BOXES;
    return {
      transfer_id: TID,
      notes: $('#internalNotes')?.value || '',
      items,
      freight: { mode, courier: $('#freight_courier')?.value || '', tracking: trackingNumbers(), boxes }
    };
  }

  // ---------------- Packed & Ready flow (with guards & SSE recon) ----------------
  function openProgress() { try { $('#uploadProgressModal') && window.jQuery && jQuery('#uploadProgressModal').modal({backdrop:'static',keyboard:false}); } catch(e){} }
  function onSSE(m){
    try {
      const op = $('#uploadOperation'), bar = $('#uploadProgressBar'), log = $('#uploadLog');
      if (m.message) { const li = document.createElement('li'); li.textContent = m.message; log.prepend(li); }
      if (typeof m.progress_percentage === 'number' && bar) bar.style.width = m.progress_percentage+'%';
      if (op && m.status) op.textContent = m.status;
      if (m.status === 'completed') setTimeout(()=> location.reloa
