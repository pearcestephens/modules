// Global client-side error/fetch handler (development-friendly)
(function(){
  var DEBUG = !!(window.CIS_DEBUG);

  function showToast(title, message, level){
    level = level || 'warning';
    var box = document.createElement('div');
    box.className = 'cis-toast cis-toast-' + level;
    box.innerHTML = '<div class="cis-toast-title">'+escapeHtml(title)+'</div>'+
                    '<div class="cis-toast-body">'+escapeHtml(message)+'</div>';
    document.body.appendChild(box);
    setTimeout(function(){ box.classList.add('show'); }, 10);
    setTimeout(function(){ box.classList.remove('show'); setTimeout(function(){ box.remove(); }, 300); }, 6000);
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }

  window.addEventListener('error', function(e){
    try { showToast('JavaScript Error', (e.message||'Script error') + (DEBUG && e.filename? (' @ '+e.filename+':'+e.lineno):''), 'danger'); } catch(_){}
  });

  window.addEventListener('unhandledrejection', function(e){
    try { showToast('Unhandled Promise Rejection', (e.reason && (e.reason.message||e.reason)) || 'Unknown', 'danger'); } catch(_){}
  });

  // Wrap fetch to surface network/HTTP failures
  var _fetch = window.fetch;
  window.fetch = function(){
    return _fetch.apply(this, arguments).then(function(res){
      if(!res.ok){
        showToast('Request Failed', res.status+' '+res.statusText, 'warning');
      }
      return res;
    }).catch(function(err){
      showToast('Network Error', (err && err.message) || 'Fetch failed', 'danger');
      throw err;
    });
  };
})();
