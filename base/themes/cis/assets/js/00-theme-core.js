// 00-theme-core.js - Apply runtime theme settings
(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var s = (window.CIS_THEME||{}).sidebar||{};
    // Ensure CSS var width respects settings
    if (typeof s.width === 'number' && s.width > 100) {
      document.documentElement.style.setProperty('--cis-sidebar-width', s.width + 'px');
    }
    // If collapsed flag changes at runtime, support toggle
    if (s.hoverExpand === false) {
      // Disable hover expand: override via style class
      document.documentElement.classList.add('cis-sidebar-no-hover-expand');
    }
  });
})();
