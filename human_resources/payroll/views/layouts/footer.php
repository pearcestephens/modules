<?php
/**
 * Payroll Module - Footer Layout
 *
 * Standard footer for all payroll views
 * Closes HTML tags and includes JavaScript
 *
 * @package HumanResources\Payroll\Views\Layouts
 */
?>
        </div> <!-- .content-wrapper -->
    </div> <!-- .main-container -->

    <!-- Footer -->
    <footer class="payroll-footer">
        <div class="main-container">
            <div class="footer-content">
                <p class="text-muted mb-0">
                    &copy; <?php echo date('Y'); ?> CIS Payroll System |
                    <a href="/modules/human_resources/payroll/?view=help" class="text-decoration-none">Help</a> |
                    <a href="/modules/human_resources/payroll/?view=changelog" class="text-decoration-none">Changelog</a>
                </p>
                <p class="text-muted small mb-0">
                    Version 1.0.0 | Build: <?php echo date('Y-m-d'); ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (if needed for legacy code) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Payroll Module Global JS -->
    <script src="/modules/human_resources/payroll/assets/js/global.js"></script>

    <!-- Global Toast Helper -->
    <script>
        // Global toast notification helper
        window.showToast = function(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;

            const toastId = 'toast-' + Date.now();
            const bgClass = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            }[type] || 'bg-info';

            const iconClass = {
                'success': 'bi-check-circle',
                'error': 'bi-exclamation-triangle',
                'warning': 'bi-exclamation-circle',
                'info': 'bi-info-circle'
            }[type] || 'bi-info-circle';

            const toastHTML = `
                <div class="toast align-items-center text-white ${bgClass} border-0" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi ${iconClass} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);

            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: type === 'error' ? 5000 : 3000
            });
            toast.show();

            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        };

        // Global loading spinner helper
        window.showLoading = function(show = true) {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) {
                spinner.classList.toggle('active', show);
            }
        };

        // Global AJAX error handler
        window.handleAjaxError = function(xhr, status, error) {
            console.error('AJAX Error:', { xhr, status, error });

            let message = 'An error occurred. Please try again.';

            if (xhr.responseJSON && xhr.responseJSON.error) {
                message = xhr.responseJSON.error;
            } else if (xhr.statusText) {
                message = xhr.statusText;
            }

            showToast(message, 'error');
            showLoading(false);
        };

        // CSRF Token Helper
        window.getCsrfToken = function() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        };

        // Confirm Dialog Helper
        window.confirmAction = function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        };
    </script>

    <!-- Page-specific scripts injected by views -->
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>

    <style>
        .payroll-footer {
            background: white;
            border-top: 1px solid var(--border-color, #e2e8f0);
            padding: 2rem 0;
            margin-top: 3rem;
        }

        .payroll-footer .footer-content {
            text-align: center;
        }

        .payroll-footer a {
            color: #667eea;
        }

        .payroll-footer a:hover {
            color: #764ba2;
        }
    </style>
</body>
</html>
