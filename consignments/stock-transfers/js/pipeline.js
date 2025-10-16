/**
 * Transfer Pipeline Orchestrator (vanilla fetch)
 * Requires:
 *  - A button or hook to call: TransferPipeline.run(transferId, buildPayload)
 *  - buildPayload() must return { transfer_id, items:[{product_id, counted_qty}], notes? }
 */

(function () {
  'use strict';

  async function jsonPost(url, bodyObj) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(bodyObj)
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = j && (j.error || j.message) || ('HTTP ' + res.status);
      throw new Error(msg);
    }
    return j;
  }

  function connectSSE(progressUrl, onEvent) {
    const es = new EventSource(progressUrl);
    es.onmessage = (e) => { /* default event name is 'message', we emit named events; ignore */ };
    es.addEventListener('progress', (e) => {
      try { onEvent(JSON.parse(e.data)); } catch {}
    });
    es.addEventListener('heartbeat', (e) => {});
    es.onerror = () => {};
    return es;
  }

  async function run(transferId, buildPayload) {
    // 1) Submit transfer to server → gets upload contract
    const payload = buildPayload();
    payload.action = 'submit_transfer';
    const contract = await jsonPost('/modules/consignments/api/api.php', payload);

    if (!contract || !contract.success) {
      throw new Error((contract && (contract.error || contract.message)) || 'Submit failed');
    }
    const { upload_session_id, upload_url, progress_url } = contract;

    if (!upload_session_id || !progress_url || !upload_url) {
      throw new Error('Server did not return upload session/URLs');
    }

    // 2) Connect SSE first so the user sees live progress
    const es = connectSSE(progress_url, (m) => {
      // TODO: Surface in your UI; for now, log:
      console.log('[SSE]', m.status, m.message, m.meta || {});
      if (m.status === 'completed' || m.status === 'failed') {
        es.close();
      }
    });

    // 3) Kick off the upload (FormData to allow future file parts if needed)
    const fd = new FormData();
    fd.append('transfer_id', String(transferId));
    fd.append('session_id', upload_session_id);

    const res = await fetch(upload_url, { method: 'POST', body: fd });
    const j = await res.json().catch(() => ({}));
    if (!res.ok || !(j && j.success)) {
      throw new Error((j && (j.error || j.message)) || ('Upload failed HTTP ' + res.status));
    }

    // 4) Success — refresh or toast
    console.log('Upload complete:', j);
    if (window.Toast && Toast.success) {
      Toast.success('Consignment uploaded to Lightspeed');
    } else {
      alert('Consignment uploaded to Lightspeed');
    }
    setTimeout(() => location.reload(), 700);
  }

  window.TransferPipeline = { run };
})();
