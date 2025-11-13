/**
 * UI Components
 *
 * Interactive component behaviors
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.Components = {

        init: function() {
            this.initSearch();
            this.initModals();
            this.initTooltips();
            this.initPopovers();
            this.initDropdowns();
            console.log('✅ Components initialized');
        },

        /**
         * Global search functionality
         */
        initSearch: function() {
            const searchInput = document.getElementById('global-search');
            if (!searchInput) return;

            const debouncedSearch = VapeUltra.Core.debounce((query) => {
                if (query.length < 2) return;

                VapeUltra.API.get('/search', { q: query })
                    .then(results => {
                        this.showSearchResults(results);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }, 300);

            searchInput.addEventListener('input', (e) => {
                debouncedSearch(e.target.value);
            });
        },

        showSearchResults: function(results) {
            // TODO: Implement search results dropdown
            console.log('Search results:', results);
        },

        /**
         * Modal management
         */
        initModals: function() {
            document.addEventListener('click', (e) => {
                // Close modal on overlay click
                if (e.target.classList.contains('modal-overlay')) {
                    this.closeModal(e.target);
                }

                // Close modal on close button click
                if (e.target.classList.contains('modal-close')) {
                    const modal = e.target.closest('.modal-overlay');
                    if (modal) this.closeModal(modal);
                }
            });

            // Close on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const activeModal = document.querySelector('.modal-overlay.active');
                    if (activeModal) this.closeModal(activeModal);
                }
            });
        },

        openModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        closeModal: function(modal) {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        },

        /**
         * Tooltips (Bootstrap)
         */
        initTooltips: function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => {
                new bootstrap.Tooltip(el);
            });
        },

        /**
         * Popovers (Bootstrap)
         */
        initPopovers: function() {
            const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(el => {
                new bootstrap.Popover(el);
            });
        },

        /**
         * Dropdown enhancements
         */
        initDropdowns: function() {
            // Bootstrap handles this, but we can add custom behavior
            document.addEventListener('show.bs.dropdown', (e) => {
                console.log('Dropdown opened:', e.target);
            });
        }
    };

    VapeUltra.Core.registerModule('Components', VapeUltra.Components);

})();
