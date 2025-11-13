<?php
/**
 * Vape Ultra Theme Configuration
 *
 * Central configuration for the base theme system
 * Modules inherit this and inject their content
 */

return [
    'theme' => [
        'name' => 'Vape Ultra',
        'version' => '1.0.0',
        'author' => 'Ecigdis Limited',
        'description' => 'Maximum feature-rich admin theme for CIS Pro',
    ],

    'assets' => [
        'css' => [
            // === FONTS & ICONS ===
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap',
            'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap', // Code font
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',

            // === CORE FRAMEWORKS ===
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',

            // === UI COMPONENTS ===
            'https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css', // Animations
            'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css', // Scroll animations
            'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css', // Toast notifications
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.10.3/dist/sweetalert2.min.css', // Beautiful alerts
            'https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/dist/tippy.css', // Tooltips
            'https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/themes/light.css',
            'https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/themes/material.css',

            // === DATA VISUALIZATION ===
            'https://cdn.jsdelivr.net/npm/gridjs@6.0.6/dist/theme/mermaid.min.css', // Advanced tables
            'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css', // DataTables
            'https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css',
            'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css',

            // === FORMS & INPUTS ===
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', // Better selects
            'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css', // Date picker
            'https://cdn.jsdelivr.net/npm/dropzone@6.0.0-beta.2/dist/dropzone.min.css', // File uploads
            'https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css', // Rich text editor

            // === CHAT & MESSAGING ===
            'https://cdn.jsdelivr.net/npm/@dmuy/toast@1.0.1/mdtoast.min.css',

            // === CODE EDITORS ===
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/material-darker.min.css',

            // === THEME CSS (Loaded in numbered order) ===
            '/modules/base/templates/vape-ultra/assets/css/variables.css',
            '/modules/base/templates/vape-ultra/assets/css/base.css',
            '/modules/base/templates/vape-ultra/assets/css/layout.css',
            '/modules/base/templates/vape-ultra/assets/css/components.css',
            '/modules/base/templates/vape-ultra/assets/css/utilities.css',
            '/modules/base/templates/vape-ultra/assets/css/animations.css',
        ],

        'js' => [
            // === CORE UTILITIES ===
            'https://code.jquery.com/jquery-3.7.1.min.js',
            'https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js', // Utility functions
            'https://cdn.jsdelivr.net/npm/axios@1.6.2/dist/axios.min.js', // HTTP client
            'https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js', // Date/time (lighter than moment)
            'https://cdn.jsdelivr.net/npm/uuid@9.0.1/dist/umd/uuidv4.min.js', // UUID generation

            // === FRAMEWORK ===
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',

            // === AI & REALTIME ===
            'https://cdn.jsdelivr.net/npm/socket.io-client@4.7.2/dist/socket.io.min.js', // WebSocket
            'https://cdn.jsdelivr.net/npm/reconnecting-websocket@4.4.0/dist/reconnecting-websocket.min.js',
            'https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js', // Markdown parser (for AI responses)
            'https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js', // XSS protection
            'https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/highlight.min.js', // Code highlighting

            // === DATA VISUALIZATION ===
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', // Charts
            'https://cdn.jsdelivr.net/npm/chartjs-adapter-dayjs-4@1.0.4/dist/chartjs-adapter-dayjs-4.esm.min.js',
            'https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js', // Advanced charts
            'https://d3js.org/d3.v7.min.js', // D3.js for custom viz
            'https://cdn.jsdelivr.net/npm/gridjs@6.0.6/dist/gridjs.umd.js', // Advanced tables
            'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
            'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js',
            'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js',
            'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',

            // === UI COMPONENTS ===
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.10.3/dist/sweetalert2.all.min.js', // Alerts
            'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js', // Toasts
            'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js', // Tooltips base
            'https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/dist/tippy-bundle.umd.min.js', // Tooltips
            'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', // Scroll animations
            'https://cdn.jsdelivr.net/npm/progressbar.js@1.1.1/dist/progressbar.min.js', // Progress bars
            'https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js', // Page load progress

            // === FORMS & INPUTS ===
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', // Better selects
            'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', // Date picker
            'https://cdn.jsdelivr.net/npm/dropzone@6.0.0-beta.2/dist/dropzone.min.js', // File uploads
            'https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js', // Input masking
            'https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js', // Input formatting
            'https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js', // Rich text editor

            // === CODE EDITORS ===
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/javascript/javascript.min.js',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/css/css.min.js',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/htmlmixed/htmlmixed.min.js',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/php/php.min.js',
            'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/sql/sql.min.js',

            // === MEDIA & IMAGES ===
            'https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/js/lightbox.min.js', // Image lightbox
            'https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js', // Image gallery
            'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js', // Video player

            // === UTILITIES ===
            'https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js', // Copy to clipboard
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.1/Sortable.min.js', // Drag & drop
            'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js', // Screenshots
            'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js', // PDF generation
            'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js', // QR codes
            'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js', // Barcodes

            // === PERFORMANCE ===
            'https://cdn.jsdelivr.net/npm/localforage@1.10.0/dist/localforage.min.js', // Local storage
            'https://cdn.jsdelivr.net/npm/idb@7.1.1/build/umd.js', // IndexedDB
            'https://cdn.jsdelivr.net/npm/workbox-sw@7.0.0/build/workbox-sw.js', // Service worker

            // === VALIDATION ===
            'https://cdn.jsdelivr.net/npm/validator@13.11.0/validator.min.js', // String validation
            'https://cdn.jsdelivr.net/npm/zxcvbn@4.4.2/dist/zxcvbn.js', // Password strength

            // === AUDIO (for notifications) ===
            'https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js', // Audio library

            // === THEME JS (Loaded in numbered order) ===
            '/modules/base/templates/vape-ultra/assets/js/core.js',
            '/modules/base/templates/vape-ultra/assets/js/components.js',
            '/modules/base/templates/vape-ultra/assets/js/utils.js',
            '/modules/base/templates/vape-ultra/assets/js/api.js',
            '/modules/base/templates/vape-ultra/assets/js/notifications.js',
            '/modules/base/templates/vape-ultra/assets/js/charts.js',
        ],
    ],

    'layout' => [
        'default' => 'main',
        'available' => ['main', 'minimal', 'mobile', 'print'],
    ],

    'features' => [
        'live_updates' => true,
        'notifications' => true,
        'dark_mode' => true,
        'mobile_responsive' => true,
        'pwa_support' => true,
        'offline_mode' => false,
    ],

    'middleware' => [
        'auth' => true,
        'csrf' => true,
        'rate_limit' => true,
        'logging' => true,
        'cache' => true,
        'compression' => true,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'driver' => 'file', // file, redis, memcached
    ],
];
