<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CIS Search Design System - Component Gallery</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

  <!-- CIS Design System -->
  <link rel="stylesheet" href="/assets/css/cis-core.css">
  <link rel="stylesheet" href="/assets/css/cis-enhanced-design-system.css">
  <link rel="stylesheet" href="/assets/css/search/cis-search-design-system.css">

  <style>
    body {
      background: var(--cis-gray-50);
      padding: 40px 0;
    }

    .demo-section {
      margin-bottom: 60px;
    }

    .demo-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      color: var(--cis-gray-900);
    }

    .demo-subtitle {
      color: var(--cis-gray-600);
      margin-bottom: 2rem;
    }

    .component-showcase {
      background: white;
      border: 2px solid var(--cis-gray-200);
      border-radius: 12px;
      padding: 40px;
      margin-bottom: 20px;
    }

    .code-block {
      background: var(--cis-gray-900);
      color: var(--cis-gray-100);
      padding: 20px;
      border-radius: 8px;
      font-family: 'Consolas', monospace;
      font-size: 0.875rem;
      overflow-x: auto;
      margin-top: 20px;
    }

    .token-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .token-card {
      background: white;
      border: 1px solid var(--cis-gray-300);
      border-radius: 8px;
      padding: 1rem;
    }

    .token-name {
      font-family: 'Consolas', monospace;
      font-size: 0.75rem;
      color: var(--cis-gray-600);
      margin-bottom: 0.5rem;
    }

    .token-value {
      font-weight: 600;
      color: var(--cis-gray-900);
    }

    .color-swatch {
      width: 100%;
      height: 60px;
      border-radius: 6px;
      margin-bottom: 0.5rem;
      border: 1px solid var(--cis-gray-300);
    }
  </style>
</head>
<body>

<div class="container" style="max-width: 1200px;">

  <!-- Header -->
  <div class="text-center mb-5">
    <h1 class="display-4 fw-bold mb-3">
      <i class="bi bi-search text-primary"></i>
      CIS Search Design System
    </h1>
    <p class="lead text-muted">Component Gallery & Documentation</p>
    <p class="text-muted">
      Organization: <strong>Ecigdis Limited</strong> | Unit: <strong>CIS</strong> | Project: <strong>CIS Staff Portal Intelligence Hub</strong>
    </p>
  </div>

  <!-- Design Tokens -->
  <div class="demo-section">
    <h2 class="demo-title">
      <i class="bi bi-palette-fill text-primary"></i> Design Tokens
    </h2>
    <p class="demo-subtitle">Centralized design decisions as CSS custom properties</p>

    <div class="component-showcase">
      <h3 class="h5 mb-3">Spacing (4px base unit)</h3>
      <div class="token-grid">
        <div class="token-card">
          <div style="background: var(--search-primary); height: var(--search-space-xs); border-radius: 2px;"></div>
          <div class="token-name">--search-space-xs</div>
          <div class="token-value">4px</div>
        </div>
        <div class="token-card">
          <div style="background: var(--search-primary); height: var(--search-space-sm); border-radius: 2px;"></div>
          <div class="token-name">--search-space-sm</div>
          <div class="token-value">8px</div>
        </div>
        <div class="token-card">
          <div style="background: var(--search-primary); height: var(--search-space-md); border-radius: 2px;"></div>
          <div class="token-name">--search-space-md</div>
          <div class="token-value">12px</div>
        </div>
        <div class="token-card">
          <div style="background: var(--search-primary); height: var(--search-space-lg); border-radius: 2px;"></div>
          <div class="token-name">--search-space-lg</div>
          <div class="token-value">16px</div>
        </div>
      </div>
    </div>

    <div class="component-showcase">
      <h3 class="h5 mb-3">Border Radius (6px base)</h3>
      <div class="token-grid">
        <div class="token-card">
          <div style="background: var(--search-primary); height: 40px; border-radius: var(--search-radius-1);"></div>
          <div class="token-name">--search-radius-1</div>
          <div class="token-value">6px</div>
        </div>
        <div class="token-card">
          <div style="background: var(--search-primary); height: 40px; border-radius: var(--search-radius-2);"></div>
          <div class="token-name">--search-radius-2</div>
          <div class="token-value">10px</div>
        </div>
        <div class="token-card">
          <div style="background: var(--search-primary); height: 40px; border-radius: var(--search-radius-3);"></div>
          <div class="token-name">--search-radius-3</div>
          <div class="token-value">14px</div>
        </div>
      </div>
    </div>

    <div class="component-showcase">
      <h3 class="h5 mb-3">Colors</h3>
      <div class="token-grid">
        <div class="token-card">
          <div class="color-swatch" style="background: var(--search-primary);"></div>
          <div class="token-name">--search-primary</div>
          <div class="token-value">#1e40af</div>
        </div>
        <div class="token-card">
          <div class="color-swatch" style="background: var(--search-primary-hover);"></div>
          <div class="token-name">--search-primary-hover</div>
          <div class="token-value">#3b82f6</div>
        </div>
        <div class="token-card">
          <div class="color-swatch" style="background: var(--search-border); border: 2px solid var(--cis-gray-400);"></div>
          <div class="token-name">--search-border</div>
          <div class="token-value">#d1d5db</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Component 1: Smart Search -->
  <div class="demo-section">
    <h2 class="demo-title">
      <i class="bi bi-search text-primary"></i> 1. Smart Search
    </h2>
    <p class="demo-subtitle">Primary search input with icon, clear button, and loader</p>

    <div class="component-showcase">
      <h3 class="h6 text-muted mb-3">Default Size</h3>
      <div class="cis-smart-search" style="max-width: 500px;">
        <div class="cis-smart-search__wrapper">
          <div class="cis-smart-search__icon">
            <i class="bi bi-search"></i>
          </div>
          <input
            type="text"
            class="cis-smart-search__input"
            placeholder="Search transfers, outlets, suppliers..."
            aria-label="Search CIS"
          >
          <button class="cis-smart-search__clear" aria-label="Clear search">
            <i class="bi bi-x-circle"></i>
          </button>
          <div class="cis-smart-search__loader">
            <div class="cis-search-spinner"></div>
          </div>
        </div>
      </div>

      <div class="code-block">
&lt;div class="cis-smart-search"&gt;
  &lt;div class="cis-smart-search__wrapper"&gt;
    &lt;div class="cis-smart-search__icon"&gt;
      &lt;i class="bi bi-search"&gt;&lt;/i&gt;
    &lt;/div&gt;
    &lt;input type="text" class="cis-smart-search__input" placeholder="Search..."&gt;
    &lt;button class="cis-smart-search__clear"&gt;&lt;i class="bi bi-x-circle"&gt;&lt;/i&gt;&lt;/button&gt;
    &lt;div class="cis-smart-search__loader"&gt;
      &lt;div class="cis-search-spinner"&gt;&lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;
      </div>
    </div>

    <div class="component-showcase">
      <h3 class="h6 text-muted mb-3">Loading State</h3>
      <div class="cis-smart-search cis-smart-search--loading" style="max-width: 500px;">
        <div class="cis-smart-search__wrapper">
          <div class="cis-smart-search__icon">
            <i class="bi bi-search"></i>
          </div>
          <input
            type="text"
            class="cis-smart-search__input"
            placeholder="Searching..."
            value="SMOK Nord"
          >
          <button class="cis-smart-search__clear">
            <i class="bi bi-x-circle"></i>
          </button>
          <div class="cis-smart-search__loader">
            <div class="cis-search-spinner"></div>
          </div>
        </div>
      </div>

      <div class="code-block">
&lt;!-- Add .cis-smart-search--loading class to show spinner --&gt;
&lt;div class="cis-smart-search cis-smart-search--loading"&gt;
  ...
&lt;/div&gt;
      </div>
    </div>
  </div>

  <!-- Component 2: Search Dropdown -->
  <div class="demo-section">
    <h2 class="demo-title">
      <i class="bi bi-list-ul text-primary"></i> 2. Search Dropdown
    </h2>
    <p class="demo-subtitle">Results container with keyboard navigation</p>

    <div class="component-showcase">
      <div style="max-width: 600px; position: relative;">
        <div class="cis-smart-search">
          <div class="cis-smart-search__wrapper">
            <div class="cis-smart-search__icon">
              <i class="bi bi-search"></i>
            </div>
            <input
              type="text"
              class="cis-smart-search__input"
              placeholder="Search products..."
              value="SMOK"
            >
            <button class="cis-smart-search__clear">
              <i class="bi bi-x-circle"></i>
            </button>
          </div>
        </div>

        <div class="cis-search-dropdown cis-search-dropdown--visible">
          <div class="cis-search-dropdown__header">
            Search Results (3 found)
          </div>
          <ul class="cis-search-dropdown__list">
            <li class="cis-search-dropdown__item cis-search-dropdown__item--selected">
              <div class="cis-search-dropdown__item-icon">
                <i class="bi bi-box-seam"></i>
              </div>
              <div class="cis-search-dropdown__item-content">
                <h4 class="cis-search-dropdown__item-title">SMOK Nord 4 Kit</h4>
                <p class="cis-search-dropdown__item-subtitle">SKU: SMOK-NORD4 | Stock: 145 | $39.99</p>
              </div>
              <div class="cis-search-dropdown__item-meta">
                <span class="cis-search-dropdown__item-badge bg-success text-white">In Stock</span>
              </div>
            </li>
            <li class="cis-search-dropdown__item">
              <div class="cis-search-dropdown__item-icon">
                <i class="bi bi-box-seam"></i>
              </div>
              <div class="cis-search-dropdown__item-content">
                <h4 class="cis-search-dropdown__item-title">SMOK RPM 2 Coils (5pk)</h4>
                <p class="cis-search-dropdown__item-subtitle">SKU: SMOK-RPM2-COILS | Stock: 8 | $19.99</p>
              </div>
              <div class="cis-search-dropdown__item-meta">
                <span class="cis-search-dropdown__item-badge bg-warning text-dark">Low Stock</span>
              </div>
            </li>
            <li class="cis-search-dropdown__item">
              <div class="cis-search-dropdown__item-icon">
                <i class="bi bi-box-seam"></i>
              </div>
              <div class="cis-search-dropdown__item-content">
                <h4 class="cis-search-dropdown__item-title">SMOK TFV16 Tank</h4>
                <p class="cis-search-dropdown__item-subtitle">SKU: SMOK-TFV16 | Stock: 0 | $49.99</p>
              </div>
              <div class="cis-search-dropdown__item-meta">
                <span class="cis-search-dropdown__item-badge bg-danger text-white">Out of Stock</span>
              </div>
            </li>
          </ul>
          <div class="cis-search-dropdown__footer">
            <kbd>↑</kbd> <kbd>↓</kbd> to navigate • <kbd>Enter</kbd> to select • <kbd>Esc</kbd> to close
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Component 3: Loading States -->
  <div class="demo-section">
    <h2 class="demo-title">
      <i class="bi bi-hourglass-split text-primary"></i> 3. Loading States
    </h2>
    <p class="demo-subtitle">Spinners and skeleton loaders</p>

    <div class="component-showcase">
      <h3 class="h6 text-muted mb-3">Spinner</h3>
      <div class="cis-search-spinner"></div>

      <div class="code-block mt-3">
&lt;div class="cis-search-spinner"&gt;&lt;/div&gt;
      </div>
    </div>

    <div class="component-showcase">
      <h3 class="h6 text-muted mb-3">Skeleton Loader</h3>
      <div class="cis-search-skeleton" style="height: 56px; margin-bottom: 8px; max-width: 600px;"></div>
      <div class="cis-search-skeleton" style="height: 56px; margin-bottom: 8px; max-width: 600px;"></div>
      <div class="cis-search-skeleton" style="height: 56px; max-width: 600px;"></div>

      <div class="code-block mt-3">
&lt;div class="cis-search-skeleton" style="height: 56px;"&gt;&lt;/div&gt;
      </div>
    </div>
  </div>

  <!-- Component 4: Keyboard Hints -->
  <div class="demo-section">
    <h2 class="demo-title">
      <i class="bi bi-keyboard text-primary"></i> 4. Keyboard Hints
    </h2>
    <p class="demo-subtitle">Pro tips and keyboard shortcuts</p>

    <div class="component-showcase">
      <div class="cis-search-kbd-hint">
        <i class="bi bi-lightbulb"></i> Pro tip: Press <kbd>/</kbd> to quick search
      </div>

      <div class="code-block mt-3">
&lt;div class="cis-search-kbd-hint"&gt;
  &lt;i class="bi bi-lightbulb"&gt;&lt;/i&gt; Pro tip: Press &lt;kbd&gt;/&lt;/kbd&gt; to quick search
&lt;/div&gt;
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="text-center mt-5 pt-5 border-top">
    <p class="text-muted">
      <i class="bi bi-github"></i> <a href="#" class="text-decoration-none">View on GitHub</a> |
      <i class="bi bi-book"></i> <a href="/assets/css/search/README.md" class="text-decoration-none">Full Documentation</a>
    </p>
    <p class="small text-muted">
      Built with ❤️ by the CSS Design Superhero for Ecigdis Limited
    </p>
  </div>

</div>

<!-- CIS Search System JS -->
<script src="/assets/js/search/cis-search-system.js"></script>

<script>
// Demo enhancements
document.addEventListener('DOMContentLoaded', () => {
  // Add click handler to show loading state demo
  const demoInputs = document.querySelectorAll('.cis-smart-search__input');
  demoInputs.forEach(input => {
    input.addEventListener('focus', () => {
      console.log('Search input focused - CIS Search System active');
    });
  });
});
</script>

</body>
</html>
