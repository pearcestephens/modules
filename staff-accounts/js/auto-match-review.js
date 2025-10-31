/**
 * Auto-Match Review Interface JavaScript
 * 
 * Handles loading, displaying, and processing auto-match suggestions
 * 
 * @package CIS\StaffAccounts\JS
 * @version 1.0.0
 */

// Global state
let autoMatchSuggestions = [];
let selectedSuggestions = [];
let bulkMode = false;

/**
 * Initialize the auto-match review interface
 */
function initAutoMatchReview() {
    console.log('Initializing Auto-Match Review interface...');
    
    // Load initial suggestions
    loadAutoMatchSuggestions();
    
    // Set up event listeners
    setupEventListeners();
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // Bulk mode toggle
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            toggleBulkMode();
        }
    });
    
    // Suggestion card selection
    $(document).on('change', '.suggestion-checkbox', function() {
        updateBulkSelection();
    });
}

/**
 * Load auto-match suggestions from API
 */
async function loadAutoMatchSuggestions() {
    try {
        showLoadingState();
        
        const minConfidence = $('#confidence-filter').val() || 0.6;
        const limit = 50;
        
        const response = await fetch(`api/auto-match-suggestions.php?min_confidence=${minConfidence}&limit=${limit}`);
        const data = await response.json();
        
        if (data.success) {
            autoMatchSuggestions = data.data;
            displaySuggestions(autoMatchSuggestions);
            updateSuggestionsCount(autoMatchSuggestions.length);
        } else {
            throw new Error(data.error || 'Failed to load suggestions');
        }
        
    } catch (error) {
        console.error('Error loading auto-match suggestions:', error);
        showErrorState('Failed to load auto-match suggestions: ' + error.message);
    }
}

/**
 * Display suggestions in the interface
 */
function displaySuggestions(suggestions) {
    const container = $('#suggestions-container');
    const template = document.getElementById('suggestion-card-template');
    
    // Hide loading state
    hideLoadingState();
    
    if (suggestions.length === 0) {
        showNoSuggestionsState();
        return;
    }
    
    // Clear container
    container.empty();
    
    // Create cards for each suggestion
    suggestions.forEach(suggestion => {
        const card = createSuggestionCard(suggestion, template);
        container.append(card);
    });
    
    // Show container
    container.show();
    $('#no-suggestions').hide();
    
    // Add animations
    $('.suggestion-card').hide().each(function(index) {
        $(this).delay(index * 100).fadeIn(300);
    });
}

/**
 * Create a suggestion card from template
 */
function createSuggestionCard(suggestion, template) {
    const card = $(template.content.cloneNode(true));
    
    // Set data attributes
    card.find('.suggestion-card').attr('data-mapping-id', suggestion.mapping_id);
    
    // Fill employee details
    card.find('.employee-name').text(suggestion.employee_name);
    card.find('.employee-email').text(suggestion.employee_email || 'No email');
    card.find('.employee-name-detail').text(suggestion.employee_name);
    card.find('.employee-email-detail').text(suggestion.employee_email || 'No email');
    card.find('.employee-id-detail').text(suggestion.xero_employee_id);
    
    // Fill customer details
    card.find('.customer-name-detail').text(suggestion.customer_name);
    card.find('.customer-email-detail').text(suggestion.customer_email || 'No email');
    card.find('.customer-code-detail').text(suggestion.customer_code || 'No code');
    card.find('.customer-phone-detail').text(suggestion.customer_phone || 'No phone');
    
    // Confidence badge
    const confidence = parseFloat(suggestion.mapping_confidence);
    const confidencePercent = Math.round(confidence * 100);
    const confidenceLevel = suggestion.confidence_level;
    
    card.find('.confidence-badge')
        .text(confidenceLevel.level)
        .removeClass('badge-secondary badge-success badge-info badge-warning badge-danger')
        .addClass(`badge-${confidenceLevel.color}`);
    
    card.find('.confidence-percentage').text(`${confidencePercent}%`);
    
    // Progress bar
    const progressBar = card.find('.confidence-progress');
    progressBar.removeClass('bg-secondary bg-success bg-info bg-warning bg-danger')
             .addClass(`bg-${confidenceLevel.color}`)
             .css('width', `${confidencePercent}%`);
    
    // Blocked amount
    const blockedAmount = parseFloat(suggestion.blocked_amount || 0);
    card.find('.blocked-amount').text(`$${blockedAmount.toFixed(2)}`);
    card.find('.deduction-count').text(`${suggestion.deduction_count} deductions`);
    
    // Match reasoning
    displayMatchReasoning(card, suggestion.match_details);
    
    // Risk factors
    if (suggestion.risk_factors && suggestion.risk_factors.length > 0) {
        displayRiskFactors(card, suggestion.risk_factors);
    }
    
    // Add selection checkbox for bulk mode
    const checkbox = $(`
        <div class="selection-checkbox" style="display: none;">
            <input type="checkbox" class="suggestion-checkbox" value="${suggestion.mapping_id}">
        </div>
    `);
    card.find('.card-header').append(checkbox);
    
    return card;
}

/**
 * Display match reasoning details
 */
function displayMatchReasoning(card, matchDetails) {
    const reasoningContainer = card.find('.match-reasoning');
    
    if (!matchDetails || Object.keys(matchDetails).length === 0) {
        reasoningContainer.html('<em class="text-muted">No detailed reasoning available</em>');
        return;
    }
    
    let html = '<div class="row">';
    
    // Name similarity
    if (matchDetails.name_similarity) {
        html += `
            <div class="col-md-6 mb-2">
                <small class="text-muted">Name Similarity:</small><br>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar bg-info" style="width: ${matchDetails.name_similarity * 100}%">
                        ${Math.round(matchDetails.name_similarity * 100)}%
                    </div>
                </div>
            </div>
        `;
    }
    
    // Email match
    if (matchDetails.email_match !== undefined) {
        const emailIcon = matchDetails.email_match ? 'check text-success' : 'times text-danger';
        const emailText = matchDetails.email_match ? 'Emails Match' : 'Emails Different';
        html += `
            <div class="col-md-6 mb-2">
                <small class="text-muted">Email Match:</small><br>
                <i class="fas fa-${emailIcon}"></i> ${emailText}
            </div>
        `;
    }
    
    // Match factors
    if (matchDetails.factors && Array.isArray(matchDetails.factors)) {
        html += '<div class="col-12 mt-2"><small class="text-muted">Match Factors:</small><ul class="list-unstyled mt-1">';
        matchDetails.factors.forEach(factor => {
            html += `<li><i class="fas fa-check-circle text-success"></i> ${factor}</li>`;
        });
        html += '</ul></div>';
    }
    
    html += '</div>';
    reasoningContainer.html(html);
}

/**
 * Display risk factors
 */
function displayRiskFactors(card, riskFactors) {
    const riskContainer = card.find('.risk-factors');
    const riskList = card.find('.risk-list');
    
    let html = '';
    riskFactors.forEach(risk => {
        const alertClass = risk.level === 'warning' ? 'alert-warning' : 'alert-info';
        html += `
            <div class="alert ${alertClass}">
                <i class="fas fa-exclamation-triangle"></i>
                ${risk.message}
            </div>
        `;
    });
    
    riskList.html(html);
    riskContainer.show();
}

/**
 * Approve a suggestion
 */
async function approveSuggestion(button) {
    const card = $(button).closest('.suggestion-card');
    const mappingId = parseInt(card.attr('data-mapping-id'));
    const notes = card.find('.approval-notes').val().trim();
    
    try {
        // Show processing state
        card.addClass('processing');
        $(button).html('<i class="fas fa-spinner fa-spin"></i> Approving...');
        
        const response = await fetch('api/auto-match-suggestions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'approve',
                mapping_id: mappingId,
                user: 'current_user', // Replace with actual user
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mark as approved
            card.removeClass('processing').addClass('approved');
            card.find('.action-buttons').html(`
                <span class="badge badge-success">
                    <i class="fas fa-check"></i> Approved
                </span>
            `);
            
            showSuccessMessage('Auto-match approved successfully');
            
            // Remove from suggestions array
            autoMatchSuggestions = autoMatchSuggestions.filter(s => s.mapping_id !== mappingId);
            updateSuggestionsCount(autoMatchSuggestions.length);
            
        } else {
            throw new Error(data.message || 'Approval failed');
        }
        
    } catch (error) {
        console.error('Error approving suggestion:', error);
        card.removeClass('processing');
        $(button).html('<i class="fas fa-check"></i> Approve');
        showErrorMessage('Failed to approve: ' + error.message);
    }
}

/**
 * Reject a suggestion
 */
async function rejectSuggestion(button) {
    const card = $(button).closest('.suggestion-card');
    const mappingId = parseInt(card.attr('data-mapping-id'));
    
    // Show rejection reason selector
    card.find('.approval-notes').hide();
    card.find('.rejection-reason').show().focus();
    card.find('.approve-btn').hide();
    card.find('.reject-btn').html('<i class="fas fa-times"></i> Confirm Reject');
    card.find('.cancel-btn').show();
    
    // Update onclick to confirm rejection
    $(button).attr('onclick', `confirmRejectSuggestion(this)`);
}

/**
 * Confirm rejection with reason
 */
async function confirmRejectSuggestion(button) {
    const card = $(button).closest('.suggestion-card');
    const mappingId = parseInt(card.attr('data-mapping-id'));
    const reason = card.find('.rejection-reason').val();
    
    if (!reason) {
        alert('Please select a rejection reason');
        return;
    }
    
    try {
        // Show processing state
        card.addClass('processing');
        $(button).html('<i class="fas fa-spinner fa-spin"></i> Rejecting...');
        
        const response = await fetch('api/auto-match-suggestions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reject',
                mapping_id: mappingId,
                user: 'current_user', // Replace with actual user
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mark as rejected
            card.removeClass('processing').addClass('rejected');
            card.find('.action-buttons').html(`
                <span class="badge badge-danger">
                    <i class="fas fa-times"></i> Rejected
                </span>
            `);
            
            showSuccessMessage('Auto-match rejected successfully');
            
            // Remove from suggestions array
            autoMatchSuggestions = autoMatchSuggestions.filter(s => s.mapping_id !== mappingId);
            updateSuggestionsCount(autoMatchSuggestions.length);
            
        } else {
            throw new Error(data.message || 'Rejection failed');
        }
        
    } catch (error) {
        console.error('Error rejecting suggestion:', error);
        card.removeClass('processing');
        cancelAction(button);
        showErrorMessage('Failed to reject: ' + error.message);
    }
}

/**
 * Cancel action and restore buttons
 */
function cancelAction(button) {
    const card = $(button).closest('.suggestion-card');
    
    // Restore original state
    card.find('.approval-notes').show().val('');
    card.find('.rejection-reason').hide().val('');
    card.find('.approve-btn').show();
    card.find('.reject-btn').html('<i class="fas fa-times"></i> Reject').attr('onclick', 'rejectSuggestion(this)');
    card.find('.cancel-btn').hide();
}

/**
 * Toggle bulk selection mode
 */
function toggleBulkMode() {
    bulkMode = !bulkMode;
    
    if (bulkMode) {
        $('body').addClass('bulk-selection-active');
        $('.selection-checkbox').show();
        showInfoMessage('Bulk mode enabled. Select suggestions and use "Bulk Approve" button.');
    } else {
        $('body').removeClass('bulk-selection-active');
        $('.selection-checkbox').hide();
        $('.suggestion-checkbox').prop('checked', false);
        selectedSuggestions = [];
        updateBulkApproveButton();
    }
}

/**
 * Update bulk selection state
 */
function updateBulkSelection() {
    selectedSuggestions = $('.suggestion-checkbox:checked').map(function() {
        return parseInt($(this).val());
    }).get();
    
    updateBulkApproveButton();
}

/**
 * Update bulk approve button state
 */
function updateBulkApproveButton() {
    const bulkBtn = $('#bulk-approve-btn');
    
    if (selectedSuggestions.length > 0) {
        bulkBtn.prop('disabled', false)
               .html(`<i class="fas fa-check-double"></i> Bulk Approve (${selectedSuggestions.length})`);
    } else {
        bulkBtn.prop('disabled', true)
               .html('<i class="fas fa-check-double"></i> Bulk Approve');
    }
}

/**
 * Show bulk approve modal
 */
function showBulkApproveModal() {
    if (selectedSuggestions.length === 0) {
        alert('Please select suggestions to approve');
        return;
    }
    
    $('#bulk-count').text(selectedSuggestions.length);
    
    // Build preview
    let previewHtml = '';
    selectedSuggestions.forEach(mappingId => {
        const suggestion = autoMatchSuggestions.find(s => s.mapping_id === mappingId);
        if (suggestion) {
            const confidence = Math.round(suggestion.mapping_confidence * 100);
            previewHtml += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong>${suggestion.employee_name}</strong> → ${suggestion.customer_name}
                    </div>
                    <div>
                        <span class="badge badge-${suggestion.confidence_level.color}">${confidence}%</span>
                        <span class="text-muted ml-2">$${parseFloat(suggestion.blocked_amount || 0).toFixed(2)}</span>
                    </div>
                </div>
            `;
        }
    });
    
    $('#bulk-preview').html(previewHtml);
    $('#bulk-approve-modal').modal('show');
}

/**
 * Confirm bulk approve
 */
async function confirmBulkApprove() {
    const notes = $('#bulk-notes').val().trim();
    
    try {
        $('#bulk-approve-modal').modal('hide');
        showInfoMessage('Processing bulk approval...');
        
        const response = await fetch('api/auto-match-suggestions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_approve',
                mapping_ids: selectedSuggestions,
                user: 'current_user', // Replace with actual user
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccessMessage(`Bulk approval completed: ${data.success_count}/${data.total_count} successful`);
            
            // Refresh suggestions
            await loadAutoMatchSuggestions();
            
            // Exit bulk mode
            toggleBulkMode();
            
        } else {
            throw new Error(data.message || 'Bulk approval failed');
        }
        
    } catch (error) {
        console.error('Error in bulk approval:', error);
        showErrorMessage('Bulk approval failed: ' + error.message);
    }
}

/**
 * Apply filters and reload suggestions
 */
function applyFilters() {
    loadAutoMatchSuggestions();
}

/**
 * Refresh suggestions
 */
function refreshSuggestions() {
    loadAutoMatchSuggestions();
}

/**
 * Update suggestions count badge
 */
function updateSuggestionsCount(count) {
    $('#suggestions-count-badge').text(count + ' pending');
}

/**
 * Show loading state
 */
function showLoadingState() {
    $('#loading-suggestions').show();
    $('#suggestions-container').hide();
    $('#no-suggestions').hide();
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    $('#loading-suggestions').hide();
}

/**
 * Show no suggestions state
 */
function showNoSuggestionsState() {
    $('#no-suggestions').show();
    $('#suggestions-container').hide();
    updateSuggestionsCount(0);
}

/**
 * Show error state
 */
function showErrorState(message) {
    hideLoadingState();
    showErrorMessage(message);
}

/**
 * Show success message
 */
function showSuccessMessage(message) {
    // Use toast notification or alert
    console.log('SUCCESS:', message);
    // Implementation depends on your notification system
}

/**
 * Show error message
 */
function showErrorMessage(message) {
    // Use toast notification or alert
    console.error('ERROR:', message);
    alert('Error: ' + message); // Replace with better notification
}

/**
 * Show info message
 */
function showInfoMessage(message) {
    // Use toast notification or alert
    console.log('INFO:', message);
    // Implementation depends on your notification system
}

// Initialize when document is ready
$(document).ready(function() {
    if ($('#auto-match-review-section').length > 0) {
        initAutoMatchReview();
    }
});