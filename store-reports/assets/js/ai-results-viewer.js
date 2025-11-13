// Poll analyzed images and display AI scores summary
document.addEventListener('DOMContentLoaded', () => {
  const el = document.querySelector('[data-sr-ai-summary]');
  if (!el) return;
  const reportId = el.getAttribute('data-report-id');
  function fetchReport() {
    fetch('?action=api:get-report&report_id=' + encodeURIComponent(reportId))
      .then(r => r.json())
      .then(json => {
        if (!json.success) return;
        const scores = json.computed_scores;
        el.innerHTML = '<strong>Scores:</strong> AI ' + (scores.ai_score ?? 'N/A') + ' | Checklist ' + (scores.checklist_score ?? 'N/A') + ' | Overall ' + (scores.overall ?? 'N/A');
      });
  }
  fetchReport();
  setInterval(fetchReport, 5000);
});
