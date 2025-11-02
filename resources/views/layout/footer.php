<?php
/**
 * File: resources/views/layout/footer.php
 * Purpose: Shared closing layout markup and script bundle references.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-02
 * Dependencies: public/assets/js/app.js, Bootstrap 5
 */

declare(strict_types=1);

$assetBase = isset($assetBase) && $assetBase !== ''
    ? rtrim((string)$assetBase, '/')
    : '/modules/public/assets';
?>
        </main>
    </div>
</div>
<footer class="vs-footer border-top bg-white py-3 mt-auto">
    <div class="container-fluid text-center text-muted small">
        &copy; <?= date('Y'); ?> Ecigdis Limited · Internal Use Only
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-tYF5NCz7L+G2kZZgwyXR0YJIUKGwEuITdSb9VjA36TObgGJE0E7E5Wdl66iRS0L" crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>/js/app.js?v=1" defer></script>
</body>
</html>
