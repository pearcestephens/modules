/*
 * File: public/assets/js/app.js
 * Purpose: Minimal interactive helpers for CIS admin dashboards.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-02
 */

(function () {
    'use strict';

    const Toast = window.bootstrap ? window.bootstrap.Toast : null;

    /**
     * Automatically wires up data-bs-toast targets for inline notifications.
     */
    function initToasts() {
        if (!Toast) {
            return;
        }

        document.querySelectorAll('.toast[data-auto-show="true"]').forEach((element) => {
            const toast = Toast.getOrCreateInstance(element);
            toast.show();
        });
    }

    /**
     * Toggle support for sidebar collapse on smaller screens.
     */
    function initSidebarToggle() {
        const toggle = document.querySelector('[data-action="toggle-sidebar"]');
        const sidebar = document.querySelector('.vs-sidebar');

        if (!toggle || !sidebar) {
            return;
        }

        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('is-collapsed');
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        initToasts();
        initSidebarToggle();
    });
})();
