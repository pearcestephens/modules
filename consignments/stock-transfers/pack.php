<?php
declare(strict_types=1);
/**
 * Minimal demo page to trigger the pipeline
 * - Replace/merge into your real page as needed.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
$transferId = (int)($_GET['transfer_id'] ?? 0);
if ($transferId <= 0) { http_response_code(400); echo "transfer_id required"; exit; }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Pack Transfer #<?= htmlspecialchars((string)$transferId) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h1 class="h3 mb-3">Pack & Upload Transfer <span class="text-muted">#<?= htmlspecialchars((string)$transferId) ?></span></h1>

    <div class="alert alert-info">This is a minimal demo. Wire into your real UI as needed.</div>

    <div class="mb-3">
      <button id="btnUpload" class="btn btn-primary">Upload to Lightspeed</button>
    </div>

    <pre id="log" class="bg-light p-3 rounded" style="min-height:160px; white-space:pre-wrap;"></pre>
  </div>

  <script src="/modules/consignments/stock-transfers/js/pipeline.js"></script>
  <script>
    const logEl = document.getElementById('log');
    function log(msg) { logEl.textContent += msg + "\n"; }

    // Fake builder — replace with your form data collector
    function buildPayload() {
      // Example: collect counted items from your page
      // Here we just build a small example payload
      return {
        transfer_id: <?= (int)$transferId ?>,
        items: [
          // { product_id: "internal-prod-id-1", counted_qty: 2 },
          // { product_id: "internal-prod-id-2", counted_qty: 1 }
        ],
        notes: "Packing via demo page"
      };
    }

    document.getElementById('btnUpload').addEventListener('click', async () => {
      log('Submitting transfer…');
      try {
        await window.TransferPipeline.run(<?= (int)$transferId ?>, buildPayload);
      } catch (e) {
        log('Error: ' + (e && e.message ? e.message : e));
        alert('Error: ' + (e && e.message ? e.message : e));
      }
    });
  </script>
</body>
</html>
