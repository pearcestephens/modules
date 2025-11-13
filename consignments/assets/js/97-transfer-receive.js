/* Stock Transfer Receive - Rich interactions */
(function(){
  var cfg = window.TransferReceiveConfig || {};
  var state = window.TransferReceiveState || { transferId: 0, items: [] };

  function el(sel){ return document.querySelector(sel); }
  function els(sel){ return Array.from(document.querySelectorAll(sel)); }

  function setBadge(id, type, text){
    var n = document.getElementById('status-'+id);
    if(!n) return;
    n.className = 'badge ' + (type==='complete' ? 'badge-success' : type==='partial' ? 'badge-info' : 'badge-warning');
    n.textContent = text;
  }

  function recomputeSummary(){
    var total = state.items.length;
    var receivedAny = 0, completed = 0;
    state.items.forEach(function(it){
      var input = document.getElementById('received-'+it.id);
      if(!input) return;
      var r = parseInt(input.value||'0',10);
      if(r>0) receivedAny++;
      if(r >= (parseInt(it.count||'0',10) || parseInt(input.max||'0',10))) completed++;
    });
    var btn = document.getElementById('tr-complete');
    if(btn) btn.disabled = receivedAny === 0;
    var sr = document.getElementById('sum-received'); if(sr) sr.textContent = receivedAny;
    var sc = document.getElementById('sum-complete'); if(sc) sc.textContent = completed;
  }

  function sync(id){
    var row = document.getElementById('item-'+id);
    if(!row) return false;
    var exp = parseInt(row.getAttribute('data-expected')||'0',10);
    var input = document.getElementById('received-'+id);
    var val = Math.max(0, Math.min(exp, parseInt(input.value||'0',10)));
    input.value = String(val);
    if(val === 0){ setBadge(id,'pending','Pending'); }
    else if(val < exp){ setBadge(id,'partial','Partial'); }
    else { setBadge(id,'complete','Complete'); }
    recomputeSummary();
    return false;
  }

  function inc(id, delta){
    var input = document.getElementById('received-'+id);
    if(!input) return false;
    var max = parseInt(input.max||'0',10);
    var cur = parseInt(input.value||'0',10);
    var next = Math.max(0, Math.min(max, cur + (delta|0)));
    if(next !== cur){ input.value = String(next); sync(id); }
    return false;
  }

  function flashFeedback(type, msg){
    var box = el('#tr-barcode-feedback');
    if(!box) return;
    box.className = 'alert ' + (type==='ok' ? 'alert-success' : 'alert-danger');
    box.textContent = msg;
    box.style.display = 'block';
    setTimeout(function(){ box.style.display='none'; }, 2500);
  }

  function scanSubmit(){
    var inp = el('#tr-barcode-scan');
    if(!inp) return;
    var code = (inp.value||'').trim();
    if(!code) return;
    var row = els('#tr-items-table tbody tr').find(function(r){ return (r.getAttribute('data-sku')||'') === code; });
    if(!row){ flashFeedback('err','Item not found in this transfer'); inp.value=''; return; }
    var id = parseInt(row.getAttribute('data-item-id')||'0',10);
    var input = document.getElementById('received-'+id);
    if(!input){ flashFeedback('err','Row missing'); return; }
    var before = parseInt(input.value||'0',10);
    inc(id, 1);
    var after = parseInt(input.value||'0',10);
    if(after>before){ flashFeedback('ok','Scanned: '+code+' ('+after+'/'+input.max+')'); row.scrollIntoView({behavior:'smooth', block:'center'}); }
    else { flashFeedback('err','Already fully received'); }
    inp.value='';
  }

  function buildPayload(){
    var items = [];
    state.items.forEach(function(it){
      var input = document.getElementById('received-'+it.id);
      if(!input) return;
      var rec = parseInt(input.value||'0',10);
      if(rec>0){
        items.push({ product_id: it.product_id || it.id, expected: parseInt(it.count||input.max||'0',10), received: rec });
      }
    });
    return items;
  }

  function submitReceive(){
    var items = buildPayload();
    var status = el('#tr-status'); if(status) status.textContent = 'Submitting...';
    var notes = (el('#transfer-notes') && el('#transfer-notes').value || '').trim();
    fetch(cfg.apiUrl, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ transfer_id: state.transferId, notes: notes, items: items })
    }).then(function(r){ return r.json(); })
      .then(function(j){
        if(j && j.success){ if(status) status.textContent = j.message || 'Received'; }
        else { if(status) status.textContent = (j && j.error && j.error.message) || 'Failed'; }
      })
      .catch(function(){ if(status) status.textContent = 'Network error'; });
  }

  // Public API for inline handlers
  window.TR = {
    sync: sync,
    inc: inc,
    note: function(id){ var n = prompt('Notes for item #'+id+':'); return false; },
    damage: function(id){ var n = prompt('Describe damage for item #'+id+':'); return false; }
  };

  document.addEventListener('DOMContentLoaded', function(){
    var scan = el('#tr-barcode-scan');
    if(scan){ scan.addEventListener('keypress', function(e){ if(e.key==='Enter'){ e.preventDefault(); scanSubmit(); } }); }
    var btn = el('#tr-submit'); if(btn){ btn.addEventListener('click', submitReceive); }
    recomputeSummary();
  });
})();
