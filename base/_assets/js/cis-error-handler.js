/**
 * CIS Error Handler
 * Global error handling for CIS applications
 */

(function() {
    'use strict';

    // Global error handler
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        console.error('Global Error:', {
            message: msg,
            url: url,
            line: lineNo,
            column: columnNo,
            error: error
        });
        return false; // Let default handler run
    };

    // Promise rejection handler
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled Promise Rejection:', event.reason);
    });

    // Log CIS error handler loaded
    console.log('✅ CIS Error Handler loaded');
})();
