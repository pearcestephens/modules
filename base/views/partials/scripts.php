<?php
/**
 * Template Partial: Scripts (Footer JS)
 * 
 * Contains all JavaScript libraries, feature scripts, and module-specific JS
 * 
 * Required variables:
 *   - $moduleJS: Array of module-specific JS files (optional)
 * 
 * @package Modules\Base\Views\Layouts
 */

if (!defined('HTTPS_URL')) {
    define('HTTPS_URL', 'https://staff.vapeshed.co.nz/');
}
?>

<!-- Nicotine Checker Feature (modal + script) -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/features/nicotine-checker.php'; ?>

<!-- jQuery already loaded in head -->
<!-- Popper v1 (required by Bootstrap 4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>

<!-- Bootstrap 4.2 JS (jQuery plugins like $(...).tab() work) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.0/dist/js/bootstrap.min.js"></script>

<!-- Pace + Perfect Scrollbar (safe with BS4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js"></script>

<!-- CoreUI v3 (Bootstrap 4-compatible). DO NOT use CoreUI v5 here. -->
<script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js"></script>

<!-- Chart.js v2 (matches CoreUI custom-tooltips plugin) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script src="<?php echo HTTPS_URL; ?>assets/node_modules/@coreui/coreui-plugin-chartjs-custom-tooltips/dist/js/custom-tooltips.min.js"></script>

<!-- Your app JS -->
<script src="<?php echo HTTPS_URL; ?>assets/js/main.js"></script>

<!-- jQuery UI (loads after jQuery; fine with BS4) -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- CIS Sidebar Mobile Enhancement - Load AFTER jQuery UI -->
<script src="<?php echo HTTPS_URL; ?>assets/js/sidebar-mobile-enhance.js?v=20250904c"></script>

<!-- Cash-Up Calculator Feature (all functions) -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/features/cashup-calculator.php'; ?>

<!-- Module-Specific Scripts (if provided) -->
<?php if (!empty($moduleJS)): ?>
    <?php foreach ($moduleJS as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>?v=<?= date('Ymd') ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
