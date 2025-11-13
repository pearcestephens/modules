/* Receive Purchase Order Page JS */
(function(){
  function qs(sel){return document.querySelector(sel);}
  function qsa(sel){return Array.from(document.querySelectorAll(sel));}

  var items = [];
  function render(){
    var tbody = qs('#po-items-table tbody');
    if(!tbody) return;
    tbody.innerHTML='';
    items.forEach(function(it, idx){
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>'+it.product_id+'</td>'+
                     '<td>'+it.qty_received+'</td>'+
                     '<td>'+it.qty_damaged+'</td>'+
                     '<td>'+(it.scanned_barcode||'')+'</td>'+
                     '<td>'+(it.notes||'')+'</td>'+
                     '<td><button type="button" class="btn btn-sm btn-outline-danger" data-idx="'+idx+'">Remove</button></td>';
      tbody.appendChild(tr);
    });
  }

  function addItem(){
    var pid = parseInt(qs('#po-product-id').value||'0',10);
    var qty = parseInt(qs('#po-qty').value||'0',10);
    var dmg = parseInt(qs('#po-damaged').value||'0',10);
    var bc  = (qs('#po-barcode').value||'').trim();
    if(!pid || qty<0 || dmg<0){ return; }
    items.push({product_id:pid, qty_received:qty, qty_damaged:dmg, scanned_barcode:bc});
    qs('#po-product-id').value='';
    qs('#po-qty').value='0';
    qs('#po-damaged').value='0';
    qs('#po-barcode').value='';
    render();
  }

  function submitReceive(){
    var poId = parseInt(qs('#po-id').value||'0',10);
    var notes = (qs('#po-notes').value||'').trim();
    if(!poId || items.length===0){ return; }
    qs('#po-status').textContent = 'Submitting...';
    fetch(window.ReceiveConfig.poApiUrl, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ po_id: poId, notes: notes, items: items })
    }).then(function(r){ return r.json(); })
      .then(function(j){
        if(j && j.success){
          qs('#po-status').textContent = j.message || 'Success';
          items = [];
          render();
        } else {
          qs('#po-status').textContent = (j && j.error && j.error.message) || 'Failed';
        }
      }).catch(function(){
        qs('#po-status').textContent = 'Network or server error';
      });
  }

  document.addEventListener('click', function(e){
    if(e.target && e.target.id==='po-add-item') addItem();
    if(e.target && e.target.id==='po-submit') submitReceive();
    if(e.target && e.target.matches('#po-items-table [data-idx]')){
      var idx = parseInt(e.target.getAttribute('data-idx'),10);
      if(!isNaN(idx)) { items.splice(idx,1); render(); }
    }
  });
})();
