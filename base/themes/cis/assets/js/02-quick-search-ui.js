// 02-quick-search-ui.js - Dropdown UI + fetch backend
(function(){
  function debounce(fn, wait){ var t; return function(){ var c=this,a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(c,a); }, wait); }; }
  function qs(sel,root){ return (root||document).querySelector(sel); }
  function qsa(sel,root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function init(container){
    var input=qs('[data-qps-input]',container);
    var btn=qs('[data-qps-btn]',container);
    var dd=qs('[data-qps-dropdown]',container);
    if(!input||!btn||!dd) return;

    function clear(){ dd.innerHTML=''; dd.classList.add('d-none'); container.classList.remove('open'); }
    function open(){ dd.classList.remove('d-none'); container.classList.add('open'); }

    function render(items, q){
      if(!items||!items.length){ dd.innerHTML='<div class="qps-empty">No matches for \u201C'+escapeHtml(q)+'\u201D</div>'; open(); return; }
      dd.innerHTML=items.map(function(it,idx){
        var name=escapeHtml(it.name||'');
        var sku=escapeHtml(it.sku||'');
        var stock=typeof it.total_stock!=='undefined'?(''+it.total_stock):'';
        return '<div class="qps-item" role="option" data-index="'+idx+'" data-id="'+(it.product_id||'')+'">'
              + '<div><div>'+name+'</div><div class="qps-meta">SKU: '+sku+'</div></div>'
              + '<div class="qps-meta">'+stock+'</div>'
              + '</div>';
      }).join('');
      open();
    }

    function selectIndex(i){ var items=qsa('.qps-item',dd); items.forEach(function(el){ el.classList.remove('active'); }); if(items[i]){ items[i].classList.add('active'); items[i].scrollIntoView({block:'nearest'}); return items[i]; } return null; }

    function currentIndex(){ var items=qsa('.qps-item',dd); for(var i=0;i<items.length;i++){ if(items[i].classList.contains('active')) return i; } return -1; }

    function fetchResults(q){ if(q.length<2){ clear(); return; } var url='/modules/consignments/api/search-products.php?q='+encodeURIComponent(q)+'&limit=10'; fetch(url,{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){ if(j && j.ok){ render(j.items||[], q); } else { dd.innerHTML='<div class="qps-empty">'+escapeHtml((j&&j.message)||'No results')+'</div>'; open(); } })
      .catch(function(){ dd.innerHTML='<div class="qps-empty">Error loading results</div>'; open(); }); }

    var debounced=debounce(function(){ fetchResults(input.value.trim()); }, 300);

    input.addEventListener('input', debounced);
    input.addEventListener('keydown', function(e){
      var items=qsa('.qps-item',dd);
      if(e.key==='ArrowDown'){ e.preventDefault(); var idx=currentIndex(); selectIndex(Math.min(idx+1, items.length-1)); }
      else if(e.key==='ArrowUp'){ e.preventDefault(); var idx=currentIndex(); selectIndex(Math.max(idx-1, 0)); }
      else if(e.key==='Enter'){ var idx=currentIndex(); if(idx<0 && items.length){ idx=0; } var el=selectIndex(idx); if(el){ e.preventDefault(); choose(el); } }
      else if(e.key==='Escape'){ clear(); }
    });

    dd.addEventListener('mousedown', function(e){ var el=e.target.closest('.qps-item'); if(el) { e.preventDefault(); choose(el); } });
    document.addEventListener('click', function(e){ if(!container.contains(e.target)){ clear(); } });

    btn.addEventListener('click', function(){ var q=input.value.trim(); if(q){ fetchResults(q); } else { input.focus(); } });

    function choose(el){ var id=el.getAttribute('data-id'); var label=qsa('div', el)[0]?.textContent||''; console.log('[QPS] choose', id, label); clear(); }
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }

  document.addEventListener('DOMContentLoaded', function(){
    qsa('.quick-product-search').forEach(function(node){ init(node); });
  });
})();
