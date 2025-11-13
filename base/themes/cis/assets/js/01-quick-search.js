// 01-quick-search.js - Debounced quick product search
(function(){
  function debounce(fn, wait){
    var t; return function(){
      var ctx=this, args=arguments;
      clearTimeout(t);
      t=setTimeout(function(){ fn.apply(ctx,args); }, wait);
    };
  }

  document.addEventListener('DOMContentLoaded',function(){
    var input=document.querySelector('[data-qps-input]');
    var btn=document.querySelector('[data-qps-btn]');
    if(!input||!btn) return;

    function performSearch(term){
      if(!term) return;
      console.log('[QPS] search:', term);
      btn.classList.add('disabled');
      setTimeout(function(){btn.classList.remove('disabled');},300);
    }

    var debounced=debounce(function(){ performSearch(input.value.trim()); }, 300);

    btn.addEventListener('click', function(){ performSearch(input.value.trim()); });
    input.addEventListener('input', debounced);
    input.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); performSearch(input.value.trim()); } });
  });
})();
