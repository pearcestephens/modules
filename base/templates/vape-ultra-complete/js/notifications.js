/**
 * Notification System
 *
 * Toast notifications using SweetAlert2
 */

(function() {
    'use strict';

    window.VapeUltra = window.VapeUltra || {};

    VapeUltra.Notifications = {

        /**
         * Show success notification
         */
        success: function(message, title = 'Success') {
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        },

        /**
         * Show error notification
         */
        error: function(message, title = 'Error') {
            Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
        },

        /**
         * Show warning notification
         */
        warning: function(message, title = 'Warning') {
            Swal.fire({
                icon: 'warning',
                title: title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        },

        /**
         * Show info notification
         */
        info: function(message, title = 'Info') {
            Swal.fire({
                icon: 'info',
                title: title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        },

        /**
         * Show confirmation dialog
         */
        confirm: function(message, title = 'Are you sure?', confirmText = 'Yes', cancelText = 'No') {
            return Swal.fire({
                title: title,
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#8a8a8a'
            });
        },

        /**
         * Show loading indicator
         */
        loading: function(message = 'Loading...') {
            Swal.fire({
                title: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },

        /**
         * Close current notification
         */
        close: function() {
            Swal.close();
        }
    };

    console.log('✅ Notification system initialized');

})();
