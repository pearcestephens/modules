/* Interaction Logger - batches UI events and posts to server
   Usage:
     InteractionLogger.track({ type: 'modal_opened', modal_name: 'receive_modal', page: 'receive', po_id: 123 });
*/

var InteractionLogger = (function(){
    var queue = [];
    var timer = null;
    var endpoint = '/modules/consignments/api/purchase-orders/log-interaction.php';
    var flushInterval = 3000; // flush every 3s or when > 10 events

    function sendBatch() {
        if (queue.length === 0) return;
        var batch = queue.splice(0, queue.length);
        try {
            navigator.sendBeacon
                ? navigator.sendBeacon(endpoint, JSON.stringify({ events: batch }))
                : fetch(endpoint, { method: 'POST', headers: { 'Content-Type':'application/json' }, body: JSON.stringify({ events: batch }) });
        } catch (e) {
            console.warn('InteractionLogger send failed', e);
        }
    }

    function scheduleFlush() {
        if (timer) return;
        timer = setTimeout(function(){
            sendBatch();
            timer = null;
        }, flushInterval);
    }

    return {
        track: function(evt) {
            try {
                queue.push(evt);
                if (queue.length >= 10) {
                    sendBatch();
                } else {
                    scheduleFlush();
                }
            } catch (e) {
                console.warn('InteractionLogger.track error', e);
            }
        },
        flush: sendBatch
    };
})();

// Auto flush on unload
window.addEventListener('beforeunload', function(){ InteractionLogger.flush(); });
