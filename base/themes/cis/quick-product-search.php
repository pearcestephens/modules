<?php

/**
 * Quick Product Search (Stub)
 * Lightweight client-side filter placeholder; later wired to backend API.
 */

?>
<form class="form-inline quick-product-search" onsubmit="return false;" role="search" aria-label="Quick product search">
  <div class="input-group input-group-sm w-100">
    <input type="text" class="form-control" placeholder="Search products" aria-label="Search products" data-qps-input>
    <div class="input-group-append">
      <button class="btn btn-outline-secondary" type="button" data-qps-btn>
        <i class="fas fa-search"></i>
      </button>
    </div>
  </div>
  <div class="qps-dropdown d-none" data-qps-dropdown role="listbox" aria-label="Search results"></div>
</form>
<?php /* Behavior is now in /modules/base/themes/cis/assets/js/01-quick-search.js */ ?>
