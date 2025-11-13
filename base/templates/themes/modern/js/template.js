$(document).ready(function() {
    // Sidebar toggle (desktop collapse)
    $('#sidebarToggle').on('click', function() {
        if (window.innerWidth > 768) {
            $('body').toggleClass('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', $('body').hasClass('sidebar-collapsed'));
        } else {
            $('body').toggleClass('sidebar-open');
        }
    });

    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        $('body').addClass('sidebar-collapsed');
    }

    // Mobile overlay click
    $('#sidebarOverlay').on('click', function() {
        $('body').removeClass('sidebar-open');
    });

    // Submenu toggle
    $('.nav-link[data-toggle="submenu"]').on('click', function(e) {
        e.preventDefault();
        const $item = $(this).closest('.nav-item');

        // Close other submenus
        $('.nav-item.open').not($item).removeClass('open');

        // Toggle current
        $item.toggleClass('open');
    });

    // Set active link
    const currentPath = window.location.pathname;
    $('.nav-link, .nav-submenu a').each(function() {
        const href = $(this).attr('href');
        if (href && href !== '#' && currentPath.includes(href)) {
            $(this).addClass('active');
            $(this).closest('.nav-item').addClass('open');
        }
    });

    // Global search keyboard shortcut (Ctrl+K)
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            $('#globalSearch').focus();
        }
    });

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Toastr default config
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 3000
    };

    // Right hover sidebar behavior (compact)
    const $rightbar = $('#cisRightbar');
    const $hoverRight = $('#hoverZoneRight');
    const $hoverLeft = $('#hoverZoneLeft');
    const $body = $('body');

    let rightbarVisible = false;
    let rightbarHideTimer = null;

    function showRightbar() {
        if (!$rightbar.length) return;
        $rightbar.addClass('active').attr('aria-hidden', 'false');
        rightbarVisible = true;
    }

    function hideRightbar() {
        if (!$rightbar.length) return;
        $rightbar.removeClass('active').attr('aria-hidden', 'true');
        rightbarVisible = false;
    }

    // Hover to show rightbar (no layout shift)
    $hoverRight.on('mouseenter', function() {
        if ($body.hasClass('compact')) return; // disabled in compact mode
        showRightbar();
    });

    // Keep visible while mouse inside rightbar
    $rightbar.on('mouseenter', function() {
        if (rightbarHideTimer) { clearTimeout(rightbarHideTimer); rightbarHideTimer = null; }
    });

    // Hide when leaving rightbar towards center
    $rightbar.on('mouseleave', function(e) {
        // If moving towards the right edge again, keep it (user circling)
        rightbarHideTimer = setTimeout(hideRightbar, 200);
    });

    // Close button
    $('#rightbarClose').on('click', function() { hideRightbar(); });

    // Left main hover when minimized vertically (interpretation: when collapsed)
    $hoverLeft.on('mouseenter', function() {
        // Temporary peek the left sidebar only if collapsed
        if ($body.hasClass('sidebar-collapsed')) {
            $body.addClass('sidebar-peek');
        }
    });
    $hoverLeft.on('mouseleave', function() {
        $body.removeClass('sidebar-peek');
    });
});
