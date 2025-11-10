<?php
/**
 * CIS Button Design Lab (Preview)
 * - Presents multiple button size/color sets for selection
 * - Scoped to .cis-button-lab to avoid global impact
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/CISTemplate.php';

$template = new CISTemplate();
$template->setTitle('Button Design Lab');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Button Design Lab', 'url' => '/modules/consignments/?route=buttons-preview', 'active' => true]
]);

$template->startContent();
?>

<style>
/***** Scoped preview styles (no global leakage) *****/
.cis-button-lab { padding: 24px; }
.cis-button-lab h2 { margin-bottom: 1rem; }
.cis-button-lab .set { margin-bottom: 2rem; }
.cis-button-lab .card { border:1px solid #e5e7eb; border-radius:12px; }
.cis-button-lab .card-body { padding: 20px; }
.cis-button-lab .btn-grid { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
.cis-button-lab .btn { border:none; border-radius:10px; font-weight:700; letter-spacing:0.2px; cursor:pointer; }
.cis-button-lab .btn.sm { padding:6px 10px; font-size:12px; }
.cis-button-lab .btn.md { padding:10px 14px; font-size:14px; }
.cis-button-lab .btn.lg { padding:12px 18px; font-size:16px; }
.cis-button-lab .btn .bi { margin-right:6px; }

/* Set A — Neutral modern */
.cis-button-lab .set-a .primary { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; }
.cis-button-lab .set-a .secondary { background:#e5e7eb; color:#111827; }
.cis-button-lab .set-a .success { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
.cis-button-lab .set-a .danger { background:linear-gradient(135deg,#ef4444,#b91c1c); color:#fff; }
.cis-button-lab .set-a .warning { background:linear-gradient(135deg,#f59e0b,#b45309); color:#fff; }
.cis-button-lab .set-a .info { background:linear-gradient(135deg,#0ea5e9,#0369a1); color:#fff; }

/* Set B — Teal brand leaning */
.cis-button-lab .set-b .primary { background:linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; }
.cis-button-lab .set-b .secondary { background:#cbd5e1; color:#0f172a; }
.cis-button-lab .set-b .success { background:linear-gradient(135deg,#16a34a,#15803d); color:#fff; }
.cis-button-lab .set-b .danger { background:linear-gradient(135deg,#dc2626,#991b1b); color:#fff; }
.cis-button-lab .set-b .warning { background:linear-gradient(135deg,#f59e0b,#a16207); color:#fff; }
.cis-button-lab .set-b .info { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }

/* Set C — Subtle flat */
.cis-button-lab .set-c .primary { background:#3b82f6; color:#fff; }
.cis-button-lab .set-c .secondary { background:#e2e8f0; color:#111827; }
.cis-button-lab .set-c .success { background:#22c55e; color:#083344; }
.cis-button-lab .set-c .danger { background:#ef4444; color:#fff; }
.cis-button-lab .set-c .warning { background:#f59e0b; color:#111827; }
.cis-button-lab .set-c .info { background:#06b6d4; color:#083344; }

/* Hover/elevation */
.cis-button-lab .btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.12); }
.cis-button-lab .btn:active { transform:translateY(0); box-shadow:0 2px 6px rgba(0,0,0,.10); }
</style>

<div class="cis-button-lab">
  <h2>Button Design Lab</h2>
  <p class="text-muted">Preview candidate button palettes and sizes. This page is isolated and safe to review before we promote to global CSS.</p>

  <?php
  $sets = [
    'set-a' => 'Set A — Neutral modern',
    'set-b' => 'Set B — Teal brand leaning',
    'set-c' => 'Set C — Subtle flat',
  ];
  $sizes = ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'];
  $kinds = ['primary','secondary','success','danger','warning','info'];
  foreach ($sets as $key => $label): ?>
    <div class="card set <?php echo $key; ?>">
      <div class="card-body">
        <h5 class="mb-3"><?php echo htmlspecialchars($label); ?></h5>
        <?php foreach ($sizes as $sizeKey => $sizeLabel): ?>
          <div class="mb-2 fw-bold"><?php echo htmlspecialchars($sizeLabel); ?></div>
          <div class="btn-grid mb-3">
            <?php foreach ($kinds as $kind): ?>
              <button type="button" class="btn <?php echo $sizeKey; ?> <?php echo $kind; ?>">
                <i class="bi bi-lightning-charge"></i>
                <?php echo ucfirst($kind); ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="alert alert-info">
    Next step: pick a set/size baseline and I will prepare a global PR to update CoreUI variables and/or add `assets/css/cis-core.css` overrides (minimal, token-driven), plus ensure Transfer Manager uses the chosen sizes with extra padding.
  </div>
</div>

<?php
$template->endContent();
$template->render();
