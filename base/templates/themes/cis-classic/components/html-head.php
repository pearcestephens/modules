<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">

    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- CoreUI 2.1.16 CSS (Latest 2.x for original look) -->
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@2.1.16/dist/css/coreui.min.css" rel="stylesheet">

    <!-- Bootstrap 4.6.2 (Latest 4.x - CoreUI 2.x compatible) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- JQUERY UI (Latest) -->
    <link href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.min.css" rel="stylesheet">

    <!-- PACE LOADER -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css" rel="stylesheet">

    <!-- FONT AWESOME 6.7.1 (Latest) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" rel="stylesheet">

    <!-- Simple Line Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css" rel="stylesheet">

    <!-- JAVASCRIPT LIBRARIES -->
    <!-- jQuery 3.7.1 (Latest stable) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Bootstrap 4.6.2 Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- CoreUI 2.1.16 JS -->
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@2.1.16/dist/js/coreui.min.js"></script>

    <!-- jQuery UI 1.14.0 (Latest) -->
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.min.js"></script>

    <!-- Moment.js 2.30.1 (Latest) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>

    <!-- Pace Loader -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>

    <!-- CIS BASE JAVASCRIPT LIBRARIES (Load before page scripts) -->
    <script src="/modules/base/_assets/js/cis-error-handler.js?v=<?php echo time(); ?>"></script>
    <script src="/modules/base/_assets/js/cis-core.js?v=<?php echo time(); ?>"></script>

    <!-- MOBILE RESPONSIVE ENHANCEMENTS (Preserves original look) -->
    <style>
        /* Mobile sidebar overlay */
        .sidebar-mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
            display: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        body.sidebar-mobile-show .sidebar-mobile-overlay {
            display: block;
            opacity: 1;
        }

        /* Mobile: sidebar slides in from left */
        @media (max-width: 991px) {
            .sidebar {
                position: fixed !important;
                top: 55px;
                bottom: 0;
                left: 0;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 1020;
                box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            }

            body.sidebar-mobile-show .sidebar {
                transform: translateX(0);
            }

            .app-body {
                margin-left: 0 !important;
            }

            .main {
                padding: 1rem !important;
            }
        }

        /* Ensure sidebar stays visible on desktop */
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0) !important;
            }

            .sidebar-mobile-overlay {
                display: none !important;
            }
        }
            color: #73818f;
        }

        /* SIDEBAR */
        .cis-sidebar {
            position: fixed;
            top: var(--cis-header-height);
            left: 0;
            bottom: 0;
            width: var(--cis-sidebar-width);
            background: var(--cis-sidebar-bg);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1020;
            transition: transform 0.3s ease-in-out, left 0.3s ease-in-out;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }

        .cis-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .cis-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .cis-sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cis-sidebar-nav-item {
            position: relative;
        }

        .cis-sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .cis-sidebar-nav-link:hover {
            background: var(--cis-sidebar-hover);
            color: #fff;
        }

        .cis-sidebar-nav-link.active {
            background: var(--cis-sidebar-hover);
            border-left-color: var(--cis-sidebar-active);
            color: #fff;
        }

        .cis-sidebar-nav-link i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .cis-sidebar-nav-link .badge {
            margin-left: auto;
        }

        /* MAIN CONTENT */
        .cis-main {
            margin-top: var(--cis-header-height);
            margin-left: var(--cis-sidebar-width);
            padding: 1.5rem;
            min-height: calc(100vh - var(--cis-header-height));
            transition: margin-left 0.3s ease-in-out;
        }

        /* SIDEBAR COLLAPSED STATE */
        body.sidebar-collapsed .cis-sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .cis-main {
            margin-left: 0;
        }

        /* MOBILE OVERLAY */
        .cis-sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1010;
            display: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        body.sidebar-mobile-open .cis-sidebar-overlay {
            display: block;
            opacity: 1;
        }

        /* TABLET & MOBILE RESPONSIVE */
        @media (max-width: 991.98px) {
            .cis-header .navbar-toggler {
                display: block;
                order: -1;
                margin-right: 1rem;
            }

            .cis-sidebar {
                transform: translateX(-100%);
            }

            .cis-main {
                margin-left: 0;
            }

            body.sidebar-mobile-open .cis-sidebar {
                transform: translateX(0);
            }
        }

        @media (max-width: 575.98px) {
            :root {
                --cis-sidebar-width: 280px;
            }

            .cis-header {
                padding: 0 0.75rem;
            }

            .cis-main {
                padding: 1rem;
            }

            .cis-sidebar-nav-link {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* FOOTER */
        .cis-footer {
            background: #fff;
            border-top: 1px solid #c8ced3;
            padding: 1rem 1.5rem;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #73818f;
        }

        /* LOADING STATE */
        .pace {
            pointer-events: none;
            user-select: none;
        }

        .pace-inactive {
            display: none;
        }

        .pace .pace-progress {
            background: var(--cis-primary);
            position: fixed;
            z-index: 2000;
            top: 0;
            right: 100%;
            width: 100%;
            height: 2px;
        }
    </style>

    <?php if ($userData && $userData['logged_in']): ?>
        <script>
            var staffID = <?php echo (int)($_SESSION["userID"] ?? 0); ?>;
        </script>
    <?php endif; ?>

    <?php if (isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])): ?>
        <script>
            window.CIS_CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        </script>
    <?php endif; ?>

    <?php if (!empty($extra_head)): ?>
        <?php echo $extra_head; ?>
    <?php endif; ?>

    <!-- Custom Button Colors & Breadcrumb Styling -->
    <style>
        .btn-purple {
            background-color: #a349a4 !important;
            border-color: #a349a4 !important;
            color: #fff !important;
        }
        .btn-purple:hover, .btn-purple:focus {
            background-color: #8a3e8b !important;
            border-color: #8a3e8b !important;
        }
        .btn-lime {
            background-color: #a4c639 !important;
            border-color: #a4c639 !important;
            color: #fff !important;
        }
        .btn-lime:hover, .btn-lime:focus {
            background-color: #8fb030 !important;
            border-color: #8fb030 !important;
        }
        .app-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            content: "/" !important;
        }
    </style>
</head>
