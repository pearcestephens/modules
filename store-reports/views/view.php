<?php
$reportId = sr_int($_GET['report_id'] ?? 0);
?>
<h2>Report #<?= $reportId ?></h2>
<div data-sr-ai-summary data-report-id="<?= $reportId ?>">Loading scores...</div>
<p>
	<a href="?action=upload&report_id=<?= $reportId ?>" class="btn btn-sm btn-primary">Upload Photos</a>
	<button class="btn btn-sm btn-warning" onclick="analyzeAll()">Analyze All Images</button>
	<button class="btn btn-sm btn-success" onclick="submitReport()">Finalize Report</button>
</p>
<div id="sr-messages" class="small"></div>
<script>window.SR_CSRF='<?= csrf_token(); ?>';</script>
<script src="assets/js/ai-results-viewer.js"></script>
<script>
function postForm(action, data){
	data.csrf_token = window.SR_CSRF;
	return fetch('?action=' + action, {method:'POST', body: toFormData(data)}).then(r=>r.json());
}
function toFormData(obj){ const fd=new FormData(); for(const k in obj){ fd.append(k,obj[k]); } return fd; }
function analyzeAll(){ postForm('api:analyze-report',{report_id:<?= $reportId ?>}).then(showMsg); }
function submitReport(){ postForm('api:submit-report',{report_id:<?= $reportId ?>}).then(showMsg); }
function showMsg(json){ document.getElementById('sr-messages').textContent = JSON.stringify(json); }
</script>
