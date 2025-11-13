<?php $reportId = sr_int($_GET['report_id'] ?? 0); ?>
<h3>Upload Photos for Report #<?= $reportId ?></h3>
<div data-sr-dropzone data-report-id="<?= $reportId ?>" class="sr-dropzone border p-3 mb-3">
	<p>Drag & drop images here or select:</p>
	<input type="file" multiple accept="image/*" />
</div>
<ul data-sr-upload-list class="list-unstyled small"></ul>
<script>window.SR_CSRF='<?= csrf_token(); ?>';</script>
<script src="assets/js/photo-uploader.js"></script>
