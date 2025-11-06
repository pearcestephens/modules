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
});
