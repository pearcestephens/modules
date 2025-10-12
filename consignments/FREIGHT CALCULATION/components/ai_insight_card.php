<?php
/**
 * AI Insight Display Component
 * 
 * Shows AI-generated transfer insights in a styled card
 * 
 * Required: $txId (transfer ID)
 */

if (!isset($txId)) {
    return;
}
?>

<style>
.ai-insight-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    padding: 12px 14px;
    margin: 12px 0 0 0;
    color: #fff;
    position: relative;
    overflow: hidden;
}

.ai-insight-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="rgba(255,255,255,0.03)"/></svg>');
    opacity: 0.1;
    pointer-events: none;
}

.ai-insight-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1;
}

.ai-insight-header i {
    font-size: 0.85rem;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.ai-badge {
    background: rgba(255, 255, 255, 0.25);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.6rem;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.ai-badge.high-priority {
    background: rgba(237, 137, 54, 0.9);
    border-color: rgba(237, 137, 54, 1);
    animation: blink 1.5s ease-in-out infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.ai-insight-text {
    font-size: 0.75rem;
    line-height: 1.5;
    margin-bottom: 8px;
    position: relative;
    z-index: 1;
    white-space: pre-line;
}

.ai-insight-text ul {
    margin: 0;
    padding-left: 18px;
}

.ai-insight-text li {
    margin: 4px 0;
}

.ai-insight-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    opacity: 0.9;
}

.ai-insight-loading .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.ai-insight-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.6rem;
    opacity: 0.8;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 6px;
    margin-top: 6px;
    position: relative;
    z-index: 1;
}

.ai-insight-meta a {
    color: #fff;
    text-decoration: underline;
    cursor: pointer;
    transition: opacity 0.2s;
}

.ai-insight-meta a:hover {
    opacity: 0.8;
}

.ai-insight-error {
    background: rgba(237, 100, 100, 0.2);
    border: 1px solid rgba(237, 100, 100, 0.5);
    padding: 8px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-top: 6px;
}
</style>

<div class="ai-insight-card" id="ai-insight-card-<?= $txId ?>">
    <div class="ai-insight-header">
        <i class="fas fa-robot"></i>
        <span>TRANSFER AI MANAGER</span>
        <span class="ai-badge" id="ai-badge-<?= $txId ?>">Analyzing...</span>
    </div>
    
    <div class="ai-insight-loading" id="ai-loading-<?= $txId ?>">
        <div class="spinner"></div>
        <span>Analyzing transfer data and generating insights...</span>
    </div>
    
    <div class="ai-insight-text" id="ai-text-<?= $txId ?>" style="display: none;"></div>
    
    <div class="ai-insight-meta" id="ai-meta-<?= $txId ?>" style="display: none;">
        <span id="ai-timestamp-<?= $txId ?>"></span>
        <a onclick="refreshAIInsight(<?= $txId ?>)">↻ Refresh</a>
    </div>
    
    <div class="ai-insight-error" id="ai-error-<?= $txId ?>" style="display: none;">
        ⚠️ Failed to generate AI insight. Please try again.
    </div>
</div>

<script>
(function() {
    const transferId = <?= $txId ?>;
    const loadingEl = document.getElementById('ai-loading-' + transferId);
    const textEl = document.getElementById('ai-text-' + transferId);
    const metaEl = document.getElementById('ai-meta-' + transferId);
    const errorEl = document.getElementById('ai-error-' + transferId);
    const badgeEl = document.getElementById('ai-badge-' + transferId);
    const timestampEl = document.getElementById('ai-timestamp-' + transferId);
    
    // Load AI insight on page load
    loadAIInsight(transferId, false);
    
    function loadAIInsight(id, forceRefresh = false) {
        // Show loading
        loadingEl.style.display = 'flex';
        textEl.style.display = 'none';
        metaEl.style.display = 'none';
        errorEl.style.display = 'none';
        badgeEl.textContent = forceRefresh ? 'Regenerating...' : 'Analyzing...';
        
        const url = '/modules/transfers/stock/api/transfer_ai_insight.php';
        const options = forceRefresh ? {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transfer_id: id, refresh: true })
        } : {
            method: 'GET'
        };
        
        const apiUrl = forceRefresh ? url : url + '?transfer_id=' + id;
        
        fetch(apiUrl, options)
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.insight) {
                    displayInsight(data.insight);
                } else {
                    showError();
                }
            })
            .catch(err => {
                console.error('AI Insight Error:', err);
                showError();
            });
    }
    
    function displayInsight(insight) {
        loadingEl.style.display = 'none';
        textEl.style.display = 'block';
        metaEl.style.display = 'flex';
        
        // Update text
        textEl.innerHTML = formatInsightText(insight.text);
        
        // Update badge
        const modelName = insight.model === 'gpt-4o' ? 'GPT-4o' : 
                          insight.model.includes('claude') ? 'Claude 3.5' : 
                          insight.provider.toUpperCase();
        badgeEl.textContent = modelName;
        
        if (insight.priority === 'high') {
            badgeEl.classList.add('high-priority');
        }
        
        // Update timestamp
        const fromCache = insight.from_cache ? ' (cached)' : '';
        const timeAgo = getTimeAgo(insight.generated_at);
        timestampEl.textContent = 'Generated ' + timeAgo + fromCache;
    }
    
    function formatInsightText(text) {
        // Convert bullet points to HTML list if present
        if (text.includes('•') || text.includes('- ')) {
            const lines = text.split('\n').filter(l => l.trim());
            const listItems = lines
                .map(line => line.replace(/^[•\-]\s*/, ''))
                .map(line => '<li>' + escapeHtml(line) + '</li>')
                .join('');
            return '<ul>' + listItems + '</ul>';
        }
        return escapeHtml(text).replace(/\n/g, '<br>');
    }
    
    function showError() {
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
        badgeEl.textContent = 'Error';
    }
    
    function getTimeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' min ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        const days = Math.floor(hours / 24);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Make refresh function globally available
    window.refreshAIInsight = function(id) {
        loadAIInsight(id, true);
    };
})();
</script>
