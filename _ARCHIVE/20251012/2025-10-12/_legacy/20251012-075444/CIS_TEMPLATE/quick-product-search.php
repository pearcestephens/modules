<?php
// Timestamp + action buttons
?>
<span style="color:#73818f; font-size:14px; margin-right:20px;"><?php echo date('d/m/Y g:i A'); ?></span>
<div class="btn-group" role="group" aria-label="Actions">
  <a class="btn" style="background:#9c27b0;border-radius:10px;color:#fff;" href="#" data-toggle="modal" data-target="#quickQtyChange" data-requires-vend="1">Quick Product Qty Change</a>
  <a class="btn" style="background:#8bc34a;border-radius:10px;color:#fff;margin-left:20px;" href="#" data-toggle="modal" data-target="#quickFloatCount">Store Cashup Calculator</a>
</div>

<!-- Cashup Modal -->
<div class="modal fade" id="quickFloatCount" tabindex="-1" role="dialog" aria-labelledby="quickFloatCountLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header align-items-center">
        <h5 class="modal-title" id="quickFloatCountLabel">Store Cashup Calculator</h5>
        <div class="ml-auto">
          <button style="display:none" type="button" class="btn btn-sm btn-outline-primary mr-2" id="btnPrefillFromVend">Prefill from Vend</button>

          <button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button>
        </div>
      </div>
      <div class="modal-body">
        <div id="cashupResumeBanner" class="alert alert-warning py-1 px-2 mb-2" style="display:none;">
          Previous cashup draft found. <a href="#" id="btnRestoreCashup">Restore</a> • <a href="#" id="btnDiscardCashup">Discard</a>
        </div>

        <div id="cashupContainer">
          <ul class="nav nav-tabs" id="bankingNav" role="tablist">
            <li class="nav-item"><a class="nav-link active show" id="cashupCalcTab-tab" data-toggle="tab" href="#cashupCalcTab" role="tab">Store Cashup Calculator</a></li>
            <li class="nav-item"><a class="nav-link" id="cashUpnotesTab-tab" data-toggle="tab" href="#cashUpnotesTab" role="tab">Autosaving NotePad</a></li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane active show" id="cashupCalcTab" role="tabpanel">
              <div class="row">
                <div class="col-md-6">
                  <h6 class="mt-2">Step 1 - Total Cash</h6>

                  <div id="cashTotal" class="position-relative">
                    <table class="table table-sm table-bordered table-striped mb-2">
                      <thead>
                        <tr>
                          <td></td>
                          <td>Qty</td>
                          <td>Total</td>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $denoms = [100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
                        foreach ($denoms as $d):
                          $id = str_replace('.', '', (string)($d * 100));
                          $label = ($d >= 1 ? '$' . number_format($d, 2) : (int)($d * 100) . 'c') . ($d >= 1 ? ($d >= 5 ? ' Notes:' : ' Notes:') : ' Coins:');
                        ?>
                          <tr>
                            <td><?php echo $label; ?></td>
                            <td><input data-den="<?php echo number_format($d, 2, '.', ''); ?>" class="cashUpTotalInput form-control form-control-sm" type="number" step="1" min="0" id="denCash<?php echo $id; ?>" style="width:100px"></td>
                            <td><span class="cashUpTotalOutput" id="denCashOut<?php echo $id; ?>"></span></td>
                          </tr>
                        <?php endforeach; ?>
                        <tr>
                          <td>Total Cash:</td>
                          <td><span id="cashUpTotalCash">$0.00</span></td>
                          <td></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="col-md-6">

                  <!-- Actions: Copy + CSV -->
                  <div class="d-flex align-items-center cashup-actions" style=" float: right; ">

                    <button type="button" class="btn btn-sm btn-info" id="btnMakeFloat">Make Float $300</button>

                    <button id="btnCopySummary" class="btn btn-copy mr-2" type="button">
                      <i class="fa fa-clipboard" aria-hidden="true"></i>
                      <span class="label">Copy</span>
                    </button>

                    <button id="btnExportCsv" class="btn btn-copy" type="button">
                      CSV
                    </button>


                  </div>
                  <h6 class="mt-2">Step 2 - Total Banking</h6>
                  <div id="bankingTotal" class="position-relative">
                    <table class="table table-sm table-bordered table-striped mb-2">
                      <thead>
                        <tr>
                          <td></td>
                          <td>Qty</td>
                          <td>Total</td>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($denoms as $d):
                          $id = str_replace('.', '', (string)($d * 100));
                          $label = ($d >= 1 ? '$' . number_format($d, 2) : (int)($d * 100) . 'c') . ($d >= 1 ? ($d >= 5 ? ' Notes:' : ' Notes:') : ' Coins:');
                        ?>
                          <tr>
                            <td><?php echo $label; ?></td>
                            <td><input data-den="<?php echo number_format($d, 2, '.', ''); ?>" class="cashUpBankingInput form-control form-control-sm" type="number" step="1" min="0" id="denBank<?php echo $id; ?>" style="width:100px"></td>
                            <td><span class="cashUpBankingOutput" id="denBankOut<?php echo $id; ?>"></span></td>
                          </tr>
                        <?php endforeach; ?>
                        <tr>
                          <td>Total Banking:</td>
                          <td><span id="cashUpTotalBanking">$0.00</span></td>
                          <td></td>
                        </tr>
                      </tbody>
                    </table>
                    <div id="cashupDiscrepancyHint" class="small text-danger" style="display:none;">Banking exceeds counted total.</div>
                  </div>
                </div>
              </div>

              <div class="row mt-2">
                <div class="col-md-8">
                  <div class="form-group mb-2">
                    <label class="small mb-1">Notes (optional)</label>
                    <textarea class="form-control form-control-sm" id="trackingNotes" rows="2" maxlength="300"></textarea>
                  </div>

                </div>
                <div class="col-md-4">
                  <table class="table table-sm table-bordered">
                    <tbody>
                      <tr>
                        <td>Total Cash</td>
                        <td class="text-right">$<span id="cashUpTotalCashOutput">0.00</span></td>
                      </tr>
                      <tr>
                        <td>Total Banking</td>
                        <td class="text-right">$<span id="cashUpTotalBankingOutput">0.00</span></td>
                      </tr>
                      <tr>
                        <td>Float</td>
                        <td class="text-right">$<span id="cashUpTotalFloatOutput">0.00</span></td>
                      </tr>
                    </tbody>
                  </table>


                </div>
              </div>

              <div class="alert alert-info py-1 px-2 mt-2" id="quick-qty-outlet-hint" style="display:none; font-size:12px;">Set Outlet + Confirm in the Quick Qty modal to tag the cashup correctly.</div>
            </div>

            <div class="tab-pane" id="cashUpnotesTab" role="tabpanel">
              <p>Note: Text here only saves locally on this computer.</p>
              <textarea onkeyup="localStorage.autoSavingPad=this.value;" id="autosavingPad" style="width:100%;height:300px;"></textarea>
            </div>
          </div>
        </div>

        <div id="cashupLoader" class="text-center" style="font-size:18px; display:none;">
          <img src="/assets/img/loader.gif" alt="">
          <p class="mt-2">Saving…</p>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-lg btn-danger" id="btnResetCashup" style="color:#fff;background:#ff9800;">Reset</button>
        <button type="button" class="btn btn-lg btn-danger" data-dismiss="modal" style="color:#fff;background:red;">Close</button>
        <button type="button" class="btn btn-lg btn-info" id="btnSaveCashup" style="color:#fff;background:#8bc34a;">Save &amp; Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Quick Product Qty modal stays as you had it (omitted for brevity if already on page) -->

<style>
  .is-invalid {
    border-color: #dc3545 !important;
  }
</style>

<script>
  (function() {
    'use strict';

    function qs(name) {
      var m = new RegExp('[?&]' + name + '=([^&]*)').exec(location.search);
      return m && m[1] ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
    }
    window.__CLOSURE_CTX__ = {
      closureID: qs('closureID') || '',
      outletID: qs('outletID') || '',
      hasCtx: function() {
        return !!this.closureID;
      }
    };
  })();

  /* ===== Submit calculator totals to closure-reconciliation.php ===== */
  (function() {
    'use strict';

    function text(id) {
      var el = document.getElementById(id);
      return el ? (el.textContent || '').trim() : '';
    }

    function addHidden(form, name, value) {
      var i = document.createElement('input');
      i.type = 'hidden';
      i.name = name;
      i.value = value;
      form.appendChild(i);
    }

    window.submitCashupToClosure = function() {
      var ctx = window.__CLOSURE_CTX__ || {
        hasCtx: function() {
          return false;
        }
      };
      if (!ctx.hasCtx()) {
        alert('This calculator is not opened in a Register Closure context (missing closureID). Open it from the Closure page.');
        return false;
      }
      var remainder = text('cashUpTotalFloatOutput') || '0.00'; // Declared Closing Float
      var banked = text('cashUpTotalBankingOutput') || '0.00'; // Declared Banking
      var notesEl = document.getElementById('trackingNotes');
      var notes = notesEl ? notesEl.value.trim() : '';
      var firstName = (window.USER_FIRST_NAME || '').trim();
      var lastName = (window.USER_LAST_NAME || '').trim();
      var storeCashUpJSON = localStorage.getItem('savedCashUp') || '';

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/closure-reconciliation.php?closureID=' + encodeURIComponent(ctx.closureID) +
        (ctx.outletID ? '&outletID=' + encodeURIComponent(ctx.outletID) : '');

      // closure expects dup confirm fields
      addHidden(form, 'remainderInTill', remainder);
      addHidden(form, 'remainderInTillConfirm', remainder);
      addHidden(form, 'totalCashBanked', banked);
      addHidden(form, 'totalCashBankedConfirm', banked);
      addHidden(form, 'notes', notes);

      // nice-to-have context
      addHidden(form, 'firstName', firstName);
      addHidden(form, 'lastName', lastName);
      addHidden(form, 'bagNumber', ''); // bag captured later
      addHidden(form, 'storeCashUpJSON', storeCashUpJSON);

      document.body.appendChild(form);
      form.submit();
      return true;
    };
  })();

  (function($) {
    'use strict';
    var BRIDGE = '/assets/services/integrations/vend/cashup_bridge.php';

    /* ---------- utils ---------- */
    function n(v) {
      v = parseFloat(String(v).replace(/[^0-9.]/g, ''));
      return isNaN(v) ? 0 : v;
    }

    function round2(x) {
      return Math.round((x + Number.EPSILON) * 100) / 100;
    }

    function sum(selector) {
      var s = 0;
      $(selector).each(function() {
        var den = n($(this).data('den')),
          q = n(this.value);
        s += den * q;
      });
      return +s.toFixed(2);
    }

    function hint(msg) {
      var $el = $('#cashupDiscrepancyHint');
      if (!msg) {
        $el.hide();
        return;
      }
      $el.text(msg).show();
    }

    function outletId() {
      return $('#quick-qty-store-select').val() || '';
    }

    function getTargetFloat(totalCash) {
      // 1) per-outlet override
      var map = (window.CIS_FLOAT_TARGETS || {}),
        oid = outletId();
      if (oid && Number.isFinite(map[oid]) && map[oid] > 0) return map[oid];
      // 2) global override
      if (Number.isFinite(window.CIS_FLOAT_TARGET) && window.CIS_FLOAT_TARGET > 0) return window.CIS_FLOAT_TARGET;
      // 3) read from button text "Make Float $300"
      var txt = ($('#btnMakeFloat').text() || '').match(/([\d,.]+)\s*$/);
      if (txt) {
        var t = n(txt[1]);
        if (t > 0) return t;
      }
      // 4) default
      return 300;
    }

    /* ---------- totals + validation ---------- */
    function updateTotals() {
      var t1 = sum('.cashUpTotalInput'),
        t2 = sum('.cashUpBankingInput');
      $('#cashUpTotalCash,#cashUpTotalCashOutput').text(t1.toFixed(2));
      $('#cashUpTotalBanking,#cashUpTotalBankingOutput').text(t2.toFixed(2));
      $('#cashUpTotalFloatOutput').text((+(t1 - t2).toFixed(2)).toFixed(2));

      var over = t2 > t1 + 0.009;
      $('#cashupDiscrepancyHint').toggle(over && !$('#cashupDiscrepancyHint').text());

      // Step2 qty must not exceed Step1 qty for same denom
      $('.cashUpBankingInput').each(function() {
        var den = $(this).data('den'),
          need = n(this.value),
          have = 0;
        $('.cashUpTotalInput[data-den="' + den + '"]').each(function() {
          have += n(this.value);
        });
        $(this).toggleClass('is-invalid', need > have && need > 0);
      });
    }
    $(document).on('input', '.cashUpTotalInput,.cashUpBankingInput', updateTotals);

    /* ---------- Make Float (bounded, 10c granularity) ---------- */
    function getAvailable() { // Step 1 quantities map: {'100.00': qty, ...}
      var map = {};
      document.querySelectorAll('.cashUpTotalInput').forEach(function(el) {
        var den = el.getAttribute('data-den');
        if (!den) return;
        map[den] = n(el.value);
      });
      return map;
    }

    function setBankingCounts(counts) { // Step 2 fill
      document.querySelectorAll('.cashUpBankingInput').forEach(function(el) {
        var den = el.getAttribute('data-den');
        if (!den) return;
        el.value = counts[den] && counts[den] > 0 ? String(counts[den]) : '';
      });
      // re-fire totals/validation
      var evt = new Event('input', {
        bubbles: true
      });
      document.querySelectorAll('.cashUpBankingInput').forEach(function(el) {
        el.dispatchEvent(evt);
      });
    }

    /* ---------- Bounded “best I can do” Make Float (10c granularity) ---------- */
    function boundedMakeFloat() {
      var totalCash = n(($('#cashUpTotalCashOutput').text() || ''));
      var target = getTargetFloat(totalCash);
      var desired = round2(totalCash - target);

      if (desired <= 0) {
        setBankingCounts({});
        updateTotals();
        hint('');
        return;
      }

      var avail = getAvailable(); // from Step 1
      var denoms = Object.keys(avail).map(parseFloat).sort((a, b) => b - a).map(d => d.toFixed(2));

      var use = {},
        remaining = desired;

      // Pass 1: greedy largest→smallest within availability
      denoms.forEach(function(denStr) {
        var den = parseFloat(denStr);
        var have = avail[denStr] || 0;
        if (have <= 0 || remaining < den - 1e-9) {
          use[denStr] = 0;
          return;
        }
        var want = Math.floor(remaining / den);
        var take = Math.min(have, want);
        use[denStr] = take;
        remaining = round2(remaining - take * den);
      });

      // Pass 2: try to resolve small remainder with smaller coins (≥10c)
      if (remaining > 0.0001) {
        var smallAsc = denoms.slice().reverse();
        smallAsc.forEach(function(denStr) {
          var den = parseFloat(denStr);
          if (den < 0.10) return; // NZ minimum coin 10c
          var can = (avail[denStr] || 0) - (use[denStr] || 0);
          if (can <= 0 || remaining < den) return;
          var want = Math.floor(remaining / den);
          var take = Math.min(can, want);
          if (take > 0) {
            use[denStr] = (use[denStr] || 0) + take;
            remaining = round2(remaining - take * den);
          }
        });
      }

      // Micro improvement: if remainder ≤ 10c, try to add one small coin
      if (remaining > 0.0001 && remaining <= 0.10 + 1e-9) {
        ['0.50', '0.20', '0.10'].some(function(denStr) {
          var can = (avail[denStr] || 0) - (use[denStr] || 0);
          if (can > 0 && parseFloat(denStr) >= remaining - 1e-9) {
            use[denStr] = (use[denStr] || 0) + 1;
            remaining = round2(remaining - parseFloat(denStr));
            return true;
          }
          return false;
        });
      }

      setBankingCounts(use);

      // Final achieved & feedback
      var achieved = 0;
      Object.keys(use).forEach(function(k) {
        achieved = round2(achieved + (use[k] || 0) * parseFloat(k));
      });
      var off = round2(desired - achieved);

      if (Math.abs(off) > 0.049) {
        hint('Could not reach exact $' + desired.toFixed(2) + ' with available notes/coins (off by $' + Math.abs(off).toFixed(2) + '). Adjust a small coin/qty.');
      } else {
        hint('');
      }

      // Clear any invalid flags (we respected availability)
      document.querySelectorAll('#quickFloatCount .is-invalid').forEach(function(el) {
        el.classList.remove('is-invalid');
      });

      updateTotals();
    }

    // Bind Make Float to bounded algorithm (replace any prior binding)
    $('#btnMakeFloat').off('click.makefloat').on('click.makefloat', boundedMakeFloat);

    // (The rest of your handlers—prefill/copy/csv/save/reset/open—are already in your file.)

  })(jQuery);
</script>

<script>
(function($){
  'use strict';
  var BRIDGE = '/assets/services/integrations/vend/cashup_bridge.php';

  function n(v){ v=parseFloat(String(v).replace(/[^0-9.]/g,'')); return isNaN(v)?0:v; }

  $('#btnSaveCashup').off('click.saveCashup').on('click.saveCashup', function(){
    var $btn = $(this);
    if ($btn.prop('disabled')) return;

    var outlet     = $('#quick-qty-store-select').val() || '';
    var outletName = $('#quick-qty-store-select option:selected').text() || '';
    if (!outlet){
      $('#quick-qty-outlet-hint').show(); return;
    }

    // Build payload for bridge
    var cash={}, bank={};
    $('.cashUpTotalInput').each(function(){ var d=$(this).data('den'), q=n(this.value); if(q>0) cash[String(d)]=q; });
    $('.cashUpBankingInput').each(function(){ var d=$(this).data('den'), q=n(this.value); if(q>0) bank[String(d)]=q; });

    var payload = {
      action        : 'store_cashup',
      outlet_id     : outlet,
      outlet_name   : outletName,
      date          : (window.moment ? moment().format('YYYY-MM-DD') : ''),
      total_cash    : n($('#cashUpTotalCashOutput').text()),
      total_banking : n($('#cashUpTotalBankingOutput').text()),
      float_total   : n($('#cashUpTotalFloatOutput').text()),
      notes         : $('#trackingNotes').val(),
      denoms_cash   : cash,
      denoms_banking: bank
    };
    if (window.CIS_CSRF) payload._csrf = window.CIS_CSRF;

    // UI locks
    $btn.prop('disabled', true).text('Saving…');
    $('#cashupLoader').show(); $('#cashupContainer').hide();

    $.ajax({
      url: BRIDGE,
      method: 'POST',
      data: JSON.stringify(payload),
      contentType: 'application/json'
    }).done(function(r){
      localStorage.removeItem('savedCashUp');

      // If opened from a closure page, forward the totals to closure-reconciliation.php
      if (r && r.ok && window.__CLOSURE_CTX__ && __CLOSURE_CTX__.hasCtx()){
        window.submitCashupToClosure();
        return; // navigation will be handled by the closure page
      }

      // Otherwise close modal normally
      setTimeout(function(){
        $('#quickFloatCount').modal('hide');
        $('#cashupLoader').hide(); $('#cashupContainer').show();
        $btn.prop('disabled', false).text('Save & Close');
      }, 300);

    }).fail(function(){
      alert('Failed to save cashup.');
      $('#cashupLoader').hide(); $('#cashupContainer').show();
      $btn.prop('disabled', false).text('Save & Close');
    });
  });
})(jQuery);


$('#btnCopySummary').off('click').on('click', function(){
  var t='Cashup Summary\nTotal Cash: $'+$('#cashUpTotalCashOutput').text()
        +'\nTotal Banking: $'+$('#cashUpTotalBankingOutput').text()
        +'\nFloat: $'+$('#cashUpTotalFloatOutput').text();
  if (navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(t);
  } else {
    var ta=document.createElement('textarea'); ta.value=t; ta.style.position='fixed'; ta.style.left='-9999px';
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); }catch(e){}
    document.body.removeChild(ta);
  }
});


</script>


<style>
  /* =========================
   Store Cashup Modal — Wider, Cap Height, Compact
   ========================= */

  /* Scope vars to THIS modal only (won't affect other modals) */
  #quickFloatCount {
    /* WIDTH: base + delta (0 → 920px, 200 → 1120px, etc.) */
    --cashup-modal-base: 920px;
    --cashup-modal-delta: 200px;
    /* Vertical gutter around dialog (top/bottom space) */
    --cashup-v-gutter: 12px;
  }

  /* Dialog: target width; height may grow OR shrink within viewport */
  #quickFloatCount .modal-dialog {
    width: calc(100vw - 24px);
    max-width: calc(var(--cashup-modal-base) + var(--cashup-modal-delta));
    margin: var(--cashup-v-gutter) auto;
    display: flex;
    /* enable flex layout */
    height: auto;
    max-height: calc(100vh - (var(--cashup-v-gutter) * 2));
    /* cap to viewport, so it can grow/shrink */
  }

  /* Content as a vertical flex column: header/footer natural size; body gets the rest */
  #quickFloatCount .modal-content {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: auto;
    max-height: 100%;
    /* respect dialog cap */
  }

  /* Body: occupies remaining space; only it scrolls when content is taller */
  #quickFloatCount .modal-body {
    flex: 1 1 auto;
    overflow: auto;
    /* scroll only if necessary */
    min-height: 0;
    /* critical for flex overflow */
  }

  /* Header/footer/tabs compact */
  #quickFloatCount .modal-header,
  #quickFloatCount .modal-footer {
    padding: .5rem .75rem;
  }

  #quickFloatCount .modal-title {
    font-size: 1rem;
  }

  #quickFloatCount .nav-tabs .nav-link {
    padding: .35rem .6rem;
    font-size: .85rem;
  }

  /* Tables compact & consistent */
  #quickFloatCount .table {
    table-layout: fixed;
    font-size: .875rem;
    line-height: 1em;
  }

  #quickFloatCount .table td {
    padding: .35rem .4rem;
    vertical-align: middle;
  }

  #quickFloatCount .table thead td {
    font-weight: 600;
    padding-top: .35rem;
    padding-bottom: .35rem;
  }

  /* Qty inputs: compact & right-aligned */

  /* Qty inputs: compact & right-aligned */
  #quickFloatCount input.form-control-sm {
    height: calc(1.2em + 0.3rem);
    max-width: 76px;
    text-align: center;
    font-size: .875rem;
  }


  /* Right-side summary table spacing */
  #quickFloatCount .card table td {
    padding: .35rem .45rem;
  }

  /* Top action buttons: strong contrast */
  #btnMakeFloat,
  #btnPrefillFromVend {
    border-width: 2px !important;
  }

  #btnMakeFloat {
    background: #0d6efd !important;
    color: #fff !important;
    border-color: #0d6efd !important;
  }

  #btnPrefillFromVend {
    background: #495057 !important;
    color: #fff !important;
    border-color: #495057 !important;
  }

  /* Validation: subtle */
  #quickFloatCount .is-invalid {
    border-color: #dc3545 !important;
    box-shadow: none !important;
  }

  /* Remove deposit-slip UI entirely */
  #cashupContainer .attachment-block,
  #cashupContainer label[for="cashupFile"],
  #cashupFile {
    display: none !important;
  }

  /* ===== Cashup action buttons ===== */

  /* Lay out the group cleanly (ditch inline float if possible) */
  .cashup-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    /* modern spacing */
    flex-wrap: wrap;
    /* wrap on small screens */
  }

  /* Uniform height & compact density */
  .cashup-actions .btn {
    line-height: 1.2;
    padding: .42rem .7rem;
    font-size: .875rem;
    border-radius: .375rem;
  }

  /* Primary: Make Float */
  #btnMakeFloat {
    background: #0d6efd !important;
    border: 2px solid #0d6efd !important;
    color: #fff !important;
    box-shadow: none !important;
    transition: background .15s ease, border-color .15s ease, transform .04s ease;
  }

  #btnMakeFloat:hover {
    background: #0b5ed7 !important;
    border-color: #0b5ed7 !important;
  }

  #btnMakeFloat:active {
    transform: translateY(1px);
  }

  #btnMakeFloat:focus {
    outline: 2px solid #0dcaf0;
    outline-offset: 2px;
  }

  /* Secondary pills: Copy + CSV (matching look) */
  .btn-copy {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: #f1f3f5;
    /* light neutral */
    color: #495057;
    border: 1px solid #ced4da;
    border-radius: 999px;
    /* pill */
    padding: .38rem .8rem;
    font-size: .875rem;
    box-shadow: none;
    transition: background .15s ease, border-color .15s ease, color .15s ease, transform .04s ease;
  }

  .btn-copy:hover {
    background: #e9ecef;
    border-color: #c7ced4;
    color: #343a40;
  }

  .btn-copy:active {
    transform: translateY(1px);
  }

  .btn-copy:focus {
    outline: 2px solid #0dcaf0;
    outline-offset: 2px;
  }

  /* “Copied” feedback (toggle this class in JS) */
  .btn-copy.copied {
    background: #198754;
    /* success */
    border-color: #198754;
    color: #fff;
  }

  /* Icon spacing in copy button */
  #btnCopySummary i {
    margin-right: .25rem;
  }

  /* Status text beside buttons */
  #copyStatus {
    min-width: 72px;
    /* keeps layout stable */
  }

  /* Small screens: tighten spacing a bit */
  @media (max-width: 480px) {
    .cashup-actions {
      gap: 6px;
    }

    .cashup-actions .btn {
      padding: .36rem .6rem;
      font-size: .84rem;
    }
  }


  /* Mobile spacing */
  @media (max-width: 992px) {
    #quickFloatCount .row .col-md-6 {
      margin-bottom: 12px;
    }
  }

  /* Extra small-height screens: shave some padding to fit more content before scroll */
  @media (max-height: 760px) {

    #quickFloatCount .modal-header,
    #quickFloatCount .modal-footer {
      padding: .4rem .6rem;
    }

    #quickFloatCount .nav-tabs .nav-link {
      padding: .3rem .5rem;
    }

    #quickFloatCount .table {
      font-size: .84rem;
    }

    #quickFloatCount input.form-control-sm {
      max-width: 70px;
    }
  }
</style>



<script>
  (function() {
    'use strict';

    /* ---------- Helpers ---------- */
    function num(v) {
      v = parseFloat(String(v).replace(/[^0-9.]/g, ''));
      return isNaN(v) ? 0 : v;
    }

    function round2(x) {
      return Math.round((x + Number.EPSILON) * 100) / 100;
    }

    function sum(selector) {
      var s = 0;
      document.querySelectorAll(selector).forEach(function(el) {
        var den = num(el.getAttribute('data-den')),
          qty = num(el.value);
        s += den * qty;
      });
      return round2(s);
    }

    function outletId() {
      var el = document.getElementById('quick-qty-store-select');
      return el && el.value ? el.value : '';
    }

    function getTargetFloat(totalCash) {
      // 1) Per-outlet override
      var map = (window.CIS_FLOAT_TARGETS || {});
      var oid = outletId();
      if (oid && Number.isFinite(map[oid]) && map[oid] > 0) return map[oid];

      // 2) Global override
      if (Number.isFinite(window.CIS_FLOAT_TARGET) && window.CIS_FLOAT_TARGET > 0) return window.CIS_FLOAT_TARGET;

      // 3) From button text (e.g. "Make Float $300")
      var btn = document.getElementById('btnMakeFloat');
      if (btn) {
        var m = (btn.textContent || '').match(/([\d,.]+)\s*$/);
        if (m) {
          var t = num(m[1]);
          if (t > 0) return t;
        }
      }
      // 4) Default
      return 300;
    }

    /* ---------- Availability & writing ---------- */
    function getAvailable() { // Step 1 quantities
      var map = {};
      document.querySelectorAll('.cashUpTotalInput').forEach(function(el) {
        var den = el.getAttribute('data-den');
        if (!den) return;
        map[den] = num(el.value);
      });
      return map; // e.g. {'100.00':22,'50.00':2,...}
    }

    function setBankingCounts(counts) { // Step 2 fill
      document.querySelectorAll('.cashUpBankingInput').forEach(function(el) {
        var den = el.getAttribute('data-den');
        if (!den) return;
        el.value = counts[den] && counts[den] > 0 ? String(counts[den]) : '';
      });
      // re-fire your existing totals logic
      var evt = new Event('input', {
        bubbles: true
      });
      document.querySelectorAll('.cashUpBankingInput').forEach(function(el) {
        el.dispatchEvent(evt);
      });
    }

    /* ---------- Totals/validation ---------- */
    function updateTotals() {
      var t1 = sum('.cashUpTotalInput');
      var t2 = sum('.cashUpBankingInput');
      var fl = round2(t1 - t2);

      var elCash = document.getElementById('cashUpTotalCashOutput');
      var elBank = document.getElementById('cashUpTotalBankingOutput');
      var elFloat = document.getElementById('cashUpTotalFloatOutput');
      if (elCash) elCash.textContent = t1.toFixed(2);
      if (elBank) elBank.textContent = t2.toFixed(2);
      if (elFloat) elFloat.textContent = fl.toFixed(2);

      // per-denom validation: Step2 qty cannot exceed Step1 qty for same denom
      document.querySelectorAll('.cashUpBankingInput').forEach(function(el) {
        var den = el.getAttribute('data-den');
        if (!den) return;
        var need = num(el.value);
        var have = 0;
        document.querySelectorAll('.cashUpTotalInput[data-den="' + den + '"]').forEach(function(e2) {
          have += num(e2.value);
        });
        if (need > have) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
      });
    }

    document.addEventListener('input', function(e) {
      if (e && (e.target.matches('.cashUpTotalInput') || e.target.matches('.cashUpBankingInput'))) {
        updateTotals();
      }
    }, {
      passive: true
    });

    /* ---------- Bounded “best I can do” Make Float (10c granularity) ---------- */
    function boundedMakeFloat() {
      var totalCash = num((document.getElementById('cashUpTotalCashOutput') || {}).textContent || '0');
      var target = getTargetFloat(totalCash);

      // Desired banking value:
      var desiredBank = round2(totalCash - target);
      if (desiredBank <= 0) {
        setBankingCounts({});
        updateTotals();
        hint('');
        return;
      }

      // Availability from Step 1:
      var avail = getAvailable(); // denStr -> qty (e.g. '100.00': 22)
      var denoms = Object.keys(avail).map(parseFloat).sort((a, b) => b - a).map(d => d.toFixed(2));

      // Greedy pass: largest→smallest, never exceed availability
      var use = {},
        remaining = desiredBank;
      denoms.forEach(function(denStr) {
        var den = parseFloat(denStr);
        var have = avail[denStr] || 0;
        var want = Math.floor(remaining / den);
        var take = Math.min(have, want);
        use[denStr] = take;
        remaining = round2(remaining - take * den);
      });

      // Second pass: try to cover the small remainder with smaller coins (10c steps)
      if (remaining > 0.0001) {
        var smallAsc = denoms.slice().reverse(); // smallest→largest now
        smallAsc.forEach(function(denStr) {
          var den = parseFloat(denStr);
          if (den < 0.10) return; // 10c is minimum unit
          var can = (avail[denStr] || 0) - (use[denStr] || 0);
          if (can <= 0 || remaining < den) return;
          var want = Math.floor(remaining / den);
          var take = Math.min(can, want);
          if (take > 0) {
            use[denStr] = (use[denStr] || 0) + take;
            remaining = round2(remaining - take * den);
          }
        });
      }

      // Small local improvement: try adding one smaller coin if it gets us closer (≤ 10c off)
      if (remaining > 0.0001 && remaining <= 0.10 + 1e-9) {
        var tryAdd = ['0.50', '0.20', '0.10']; // big→small
        for (var i = 0; i < tryAdd.length; i++) {
          var denStr = tryAdd[i],
            can = (avail[denStr] || 0) - (use[denStr] || 0);
          if (can > 0 && parseFloat(denStr) >= remaining - 1e-9) {
            use[denStr] = (use[denStr] || 0) + 1;
            remaining = round2(remaining - parseFloat(denStr));
            break;
          }
        }
      }

      // Write Step 2 counts
      setBankingCounts(use);

      // Final achieved and hint text
      var achieved = 0;
      Object.keys(use).forEach(function(k) {
        achieved = round2(achieved + (use[k] || 0) * parseFloat(k));
      });
      var off = round2(desiredBank - achieved);

      if (Math.abs(off) > 0.049) {
        hint('Could not reach exact $' + desiredBank.toFixed(2) + ' with available notes/coins (off by $' + Math.abs(off).toFixed(2) + '). Adjust a small coin/qty.');
      } else {
        hint('');
      }

      // Clear any old invalid flags (we’ve bounded counts)
      document.querySelectorAll('#quickFloatCount .is-invalid').forEach(function(el) {
        el.classList.remove('is-invalid');
      });

      // Totals refresh
      updateTotals();
    }

    function hint(msg) {
      var el = document.getElementById('cashupDiscrepancyHint');
      if (!el) return;
      if (msg) {
        el.textContent = msg;
        el.style.display = '';
      } else {
        el.style.display = 'none';
      }
    }

    /* ---------- Bind ---------- */
    var mfBtn = document.getElementById('btnMakeFloat');
    if (mfBtn) {
      mfBtn.addEventListener('click', boundedMakeFloat);
    }

    // Ensure totals up-to-date when modal opens
    var modal = document.getElementById('quickFloatCount');
    if (modal) {
      modal.addEventListener('shown.bs.modal', function() {
        updateTotals();
        hint('');
      });
    }
  })();
</script>