<?php
/**
 * Order History Component
 * 
 * Displays transfer history with:
 * - User input field for adding comments/notes
 * - Full transfer history with timestamps
 * - Staff first/last names from users table
 * - System messages (userID 18)
 * - Auto-fills remaining container height
 * 
 * Expected variables:
 * - $transferId: Current transfer ID
 * - $notes: Array of notes (preloaded)
 */

$transferId = $transferId ?? $_GET['transfer'] ?? 0;
$notes = $notes ?? [];
$currentUserId = (int)($_SESSION['userID'] ?? $_SESSION['user']['id'] ?? 0);
?>

<!-- Order History Section -->
<div class="card shadow-sm mt-4" id="order-history-section">
    <div class="card-header bg-gradient-dark text-white d-flex align-items-center justify-content-between">
        <div class="h5 mb-0">
            <i class="fa fa-history mr-2"></i>Order History
        </div>
        <span class="badge badge-light" id="history-count"><?= count($notes) ?></span>
    </div>
    
    <div class="card-body p-0" style="display: flex; flex-direction: column; height: 500px;">
        
        <!-- History Timeline (scrollable) -->
        <div class="flex-grow-1 overflow-auto p-3" id="history-timeline" style="flex: 1; min-height: 0;">
            <?php if (empty($notes)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-inbox fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No history yet. Add the first note below.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notes as $note): 
                    $isSystem = (int)$note['user_id'] === 18;
                    $staffName = $note['staff_name'] ?? 'Unknown';
                    $timestamp = date('M j, Y g:i A', strtotime($note['created_at']));
                    $noteType = $note['note_type'] ?? 'general';
                    $isInternal = (int)($note['is_internal'] ?? 0) === 1;
                    
                    // Icon based on note type
                    $icon = 'fa-comment';
                    $iconColor = '#6c757d';
                    if ($noteType === 'system') {
                        $icon = 'fa-cog';
                        $iconColor = '#007bff';
                    } elseif ($noteType === 'status_change') {
                        $icon = 'fa-exchange-alt';
                        $iconColor = '#28a745';
                    } elseif ($noteType === 'shipping') {
                        $icon = 'fa-truck';
                        $iconColor = '#17a2b8';
                    } elseif ($noteType === 'warning') {
                        $icon = 'fa-exclamation-triangle';
                        $iconColor = '#ffc107';
                    }
                ?>
                <div class="history-entry mb-3 <?= $isSystem ? 'system-entry' : '' ?>" data-note-id="<?= $note['id'] ?>">
                    <div class="d-flex align-items-start">
                        <!-- Timeline Icon -->
                        <div class="timeline-icon mr-3" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?= $iconColor ?>15; flex-shrink: 0;">
                            <i class="fa <?= $icon ?>" style="color: <?= $iconColor ?>; font-size: 14px;"></i>
                        </div>
                        
                        <!-- Entry Content -->
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div>
                                    <strong class="<?= $isSystem ? 'text-primary' : '' ?>">
                                        <?= $isSystem ? '<i class="fa fa-robot mr-1"></i>System' : htmlspecialchars($staffName, ENT_QUOTES) ?>
                                    </strong>
                                    <?php if ($isInternal): ?>
                                        <span class="badge badge-warning badge-sm ml-1" title="Internal note - not visible to customer">
                                            <i class="fa fa-lock"></i> Internal
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= $timestamp ?></small>
                            </div>
                            <div class="note-text" style="color: #495057; line-height: 1.5;">
                                <?= nl2br(htmlspecialchars($note['note_text'], ENT_QUOTES)) ?>
                            </div>
                        </div>
                    </div>
                    <hr class="mt-3 mb-0" style="border-color: #e9ecef;">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Input Section (fixed at bottom) -->
        <div class="border-top bg-light p-3" style="flex-shrink: 0;">
            <form id="add-note-form" class="mb-0">
                <div class="form-group mb-2">
                    <label for="note-input" class="small font-weight-bold mb-1">Add Note:</label>
                    <textarea 
                        id="note-input" 
                        class="form-control" 
                        rows="2" 
                        placeholder="Type your comment or note here..."
                        style="resize: none;"
                    ></textarea>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="note-internal">
                        <label class="custom-control-label small" for="note-internal">
                            <i class="fa fa-lock mr-1"></i>Internal Note
                        </label>
                    </div>
                    <div>
                        <select id="note-type" class="form-control form-control-sm d-inline-block mr-2" style="width: auto;">
                            <option value="general">General</option>
                            <option value="status_change">Status Change</option>
                            <option value="shipping">Shipping</option>
                            <option value="warning">Warning</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" id="add-note-btn">
                            <i class="fa fa-plus mr-1"></i>Add Note
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
    </div>
</div>

<style>
/* Order History Custom Styles */
#order-history-section .card-header {
    background: linear-gradient(90deg, #0b132b, #1c2541);
}

#history-timeline::-webkit-scrollbar {
    width: 8px;
}

#history-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#history-timeline::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

#history-timeline::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

.history-entry.system-entry {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #007bff;
}

.history-entry:last-child hr {
    display: none;
}

#add-note-form .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
</style>

<script>
(function() {
    const transferId = <?= (int)$transferId ?>;
    const form = document.getElementById('add-note-form');
    const textarea = document.getElementById('note-input');
    const submitBtn = document.getElementById('add-note-btn');
    const timeline = document.getElementById('history-timeline');
    const historyCount = document.getElementById('history-count');
    
    // Scroll to bottom on load
    if (timeline) {
        timeline.scrollTop = timeline.scrollHeight;
    }
    
    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const noteText = textarea.value.trim();
        if (!noteText) {
            alert('Please enter a note before submitting.');
            return;
        }
        
        const noteType = document.getElementById('note-type').value;
        const isInternal = document.getElementById('note-internal').checked;
        
        // Disable form
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i>Adding...';
        
        try {
            const response = await fetch('/modules/transfers/stock/api/notes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    transfer_id: transferId,
                    note_text: noteText,
                    note_type: noteType,
                    is_internal: isInternal ? 1 : 0
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reload page to show new note
                window.location.reload();
            } else {
                alert('Failed to add note: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error adding note:', error);
            alert('Network error adding note. Please try again.');
        } finally {
            // Re-enable form
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa fa-plus mr-1"></i>Add Note';
        }
    });
    
    // Auto-resize textarea
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
})();
</script>
