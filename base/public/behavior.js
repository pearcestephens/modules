// Client-side behavior capture (non-intrusive, security/audit)
(function(){
  var DEBUG = !!window.CIS_DEBUG;
  var BUF = [];
  var MAX_BUF = 40;
  var FLUSH_MS = 5000;
  var lastFlush = Date.now();

  function nowMs(){ return Date.now(); }
  function page(){ return location.pathname + location.search; }

  function push(evt){
    try{
      BUF.push(evt);
      if (BUF.length >= MAX_BUF || (Date.now() - lastFlush) > FLUSH_MS) flush();
    }catch(e){ if(DEBUG) console.warn('behavior push err', e); }
  }

  function flush(){
    if (!BUF.length) return;
    var payload = BUF.slice(0); BUF.length = 0; lastFlush = Date.now();
    navigator.sendBeacon && navigator.sendBeacon('/modules/base/public/behavior.php', new Blob([JSON.stringify(payload)], {type:'application/json'}))
      || fetch('/modules/base/public/behavior.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials:'same-origin'})
         .catch(function(e){ if(DEBUG) console.warn('behavior flush err', e); });
  }

  function clickHandler(e){
    var t = e.target;
    var tag = (t && t.tagName || 'UNKNOWN').toLowerCase();
    var id = t && t.id || null;
    var cls = t && t.className || null;
    var role = t && t.getAttribute && t.getAttribute('role') || null;
    push({type:'click', t:nowMs(), pg:page(), tag:tag, id:id, cls:cls, role:role, x:e.clientX, y:e.clientY});
  }

  var scrollTimer = null;
  function scrollHandler(){
    if (scrollTimer) return; // throttle
    scrollTimer = setTimeout(function(){ scrollTimer=null; push({type:'scroll', t:nowMs(), pg:page(), y:window.scrollY, h:document.documentElement.scrollHeight}); }, 1000);
  }

  function visHandler(){ push({type:'visibility', t:nowMs(), pg:page(), v:document.visibilityState}); }

  function perf(){
    if (performance && performance.timing){
      var t = performance.timing;
      push({type:'perf', t:nowMs(), pg:page(),
        dns:t.domainLookupEnd - t.domainLookupStart,
        tcp:t.connectEnd - t.connectStart,
        ttfb:t.responseStart - t.requestStart,
        dom: t.domContentLoadedEventEnd - t.responseEnd,
        load: t.loadEventEnd - t.navigationStart});
    }
  }

  function suspicious(){
    // Basic signals
    var w = window;
    // DevTools detection heuristic
    var devtools = false;
    var threshold = 160;
    var widthThreshold = Math.abs(w.outerWidth - w.innerWidth) > threshold;
    var heightThreshold = Math.abs(w.outerHeight - w.innerHeight) > threshold;
    devtools = widthThreshold || heightThreshold;
    if (devtools) push({type:'suspicious', subtype:'devtools', t:nowMs(), pg:page()});
  }

  document.addEventListener('click', clickHandler, true);
  document.addEventListener('scroll', scrollHandler, {passive:true});
  document.addEventListener('visibilitychange', visHandler);
  window.addEventListener('beforeunload', flush);
  window.addEventListener('pagehide', flush);

  // Initial signals
  perf();
  suspicious();

  // Periodic flush
  setInterval(flush, 4000);
})();
