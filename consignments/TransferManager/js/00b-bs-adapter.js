'use strict';
// Bootstrap compatibility adapter: emulate minimal BS5 API when only BS4 (jQuery) is present
(function(){
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap && window.bootstrap.Modal) return;
  if (typeof window.jQuery === 'undefined' || !window.jQuery.fn) return;
  var $ = window.jQuery;

  function getEl(target){
    if (!target) return null;
    if (typeof target === 'string') return document.querySelector(target);
    if (target.nodeType) return target;
    return null;
  }

  // Modal shim using jQuery BS4 modal
  function Modal(target){
    this._el = getEl(target);
    this._$ = this._el ? $(this._el) : null;
  }
  Modal.prototype.show = function(){ if (this._$) { this._$.modal('show'); } };
  Modal.prototype.hide = function(){ if (this._$) { this._$.modal('hide'); } };

  // Toast shim (optional)
  function Toast(target){
    this._el = getEl(target);
    this._$ = this._el ? $(this._el) : null;
  }
  Toast.prototype.show = function(){ if (this._$ && this._$.toast) { this._$.toast('show'); } else if (this._el){ this._el.style.display='block'; } };
  Toast.prototype.hide = function(){ if (this._$ && this._$.toast) { this._$.toast('hide'); } else if (this._el){ this._el.style.display='none'; } };

  window.bootstrap = { Modal: Modal, Toast: Toast };
})();
