// Basic drag & drop photo uploader for Store Reports
document.addEventListener('DOMContentLoaded', () => {
  const dropZone = document.querySelector('[data-sr-dropzone]');
  if (!dropZone) return;
  const reportId = dropZone.getAttribute('data-report-id');
  const list = document.querySelector('[data-sr-upload-list]');

  function uploadFile(file) {
    const formData = new FormData();
    formData.append('report_id', reportId);
    formData.append('file', file);
    formData.append('csrf_token', window.SR_CSRF || '');
    const li = document.createElement('li');
    li.textContent = file.name + ' - uploading...';
    list.appendChild(li);
    fetch('?action=api:upload-image', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          li.textContent = file.name + ' ✔ (ID ' + json.image_id + ')';
        } else {
          li.textContent = file.name + ' ✖ ' + json.error;
        }
      })
      .catch(e => { li.textContent = file.name + ' ✖ ' + e.message; });
  }

  function handleFiles(files) {
    Array.from(files).forEach(f => uploadFile(f));
  }

  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('hover'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('hover'));
  dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('hover'); handleFiles(e.dataTransfer.files); });
  const input = dropZone.querySelector('input[type=file]');
  if (input) { input.addEventListener('change', e => handleFiles(e.target.files)); }
});
