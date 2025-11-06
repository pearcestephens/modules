<?php
/**
 * AI Settings View
 * Configure AI rules, thresholds, and behaviors
 */

// Get current AI rules
$rulesStmt = $pdo->query("SELECT * FROM payroll_ai_rules ORDER BY priority DESC, rule_type");
$rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get bot config
$configStmt = $pdo->query("SELECT * FROM payroll_bot_config ORDER BY config_key");
$configs = $configStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="ai-settings-view">
    <div class="view-header">
        <h4>⚙️ AI Configuration</h4>
        <p class="text-muted">Configure AI behavior, rules, and thresholds</p>
    </div>

    <!-- Bot Configuration -->
    <div class="settings-section">
        <h5>🤖 Auto-Pilot Configuration</h5>
        <div class="config-grid">
            <?php foreach ($configs as $config): ?>
                <div class="config-item">
                    <label class="form-label">
                        <?php echo ucwords(str_replace('_', ' ', $config['config_key'])); ?>
                    </label>
                    <?php if ($config['config_type'] === 'boolean'): ?>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="config_<?php echo $config['id']; ?>"
                                   <?php echo $config['config_value'] ? 'checked' : ''; ?>
                                   onchange="updateConfig(<?php echo $config['id']; ?>, this.checked)">
                        </div>
                    <?php elseif ($config['config_type'] === 'float'): ?>
                        <input type="number" class="form-control"
                               step="0.01" min="0" max="1"
                               value="<?php echo $config['config_value']; ?>"
                               onchange="updateConfig(<?php echo $config['id']; ?>, this.value)">
                        <small class="text-muted"><?php echo $config['description'] ?? ''; ?></small>
                    <?php elseif ($config['config_type'] === 'integer'): ?>
                        <input type="number" class="form-control"
                               min="0"
                               value="<?php echo $config['config_value']; ?>"
                               onchange="updateConfig(<?php echo $config['id']; ?>, this.value)">
                        <small class="text-muted"><?php echo $config['description'] ?? ''; ?></small>
                    <?php else: ?>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($config['config_value']); ?>"
                               onchange="updateConfig(<?php echo $config['id']; ?>, this.value)">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <hr class="my-4">

    <!-- AI Rules -->
    <div class="settings-section">
        <h5>📐 AI Decision Rules</h5>
        <p class="text-muted mb-3">
            These rules determine how the AI evaluates and processes payroll items.
            Each rule has conditions that must be met for it to trigger.
        </p>

        <div class="rules-list">
            <?php foreach ($rules as $rule): ?>
                <div class="rule-card <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="rule-header">
                        <div class="rule-info">
                            <h6>
                                <?php echo htmlspecialchars($rule['rule_name']); ?>
                                <span class="badge bg-info"><?php echo ucfirst($rule['rule_type']); ?></span>
                            </h6>
                            <p class="text-muted"><?php echo htmlspecialchars($rule['description']); ?></p>
                        </div>
                        <div class="rule-toggle">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="rule_<?php echo $rule['id']; ?>"
                                       <?php echo $rule['is_active'] ? 'checked' : ''; ?>
                                       onchange="toggleRule(<?php echo $rule['id']; ?>, this.checked)">
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="rule-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Priority:</strong>
                                <input type="number" class="form-control form-control-sm"
                                       value="<?php echo $rule['priority']; ?>"
                                       onchange="updateRulePriority(<?php echo $rule['id']; ?>, this.value)">
                            </div>
                            <div class="col-md-3">
                                <strong>Confidence Required:</strong>
                                <input type="number" class="form-control form-control-sm"
                                       step="0.01" min="0" max="1"
                                       value="<?php echo $rule['confidence_required']; ?>"
                                       onchange="updateRuleConfidence(<?php echo $rule['id']; ?>, this.value)">
                            </div>
                            <div class="col-md-6">
                                <strong>Actions:</strong>
                                <div class="action-badges">
                                    <?php if ($rule['auto_approve']): ?>
                                        <span class="badge bg-success">Auto-Approve</span>
                                    <?php endif; ?>
                                    <?php if ($rule['auto_decline']): ?>
                                        <span class="badge bg-danger">Auto-Decline</span>
                                    <?php endif; ?>
                                    <?php if ($rule['require_human_review']): ?>
                                        <span class="badge bg-warning">Require Review</span>
                                    <?php endif; ?>
                                    <?php if ($rule['require_escalation']): ?>
                                        <span class="badge bg-danger">Escalate</span>
                                    <?php endif; ?>
                                    <?php if ($rule['send_notification']): ?>
                                        <span class="badge bg-info">Send Notification</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="rule-conditions mt-3">
                            <strong>Conditions:</strong>
                            <pre class="conditions-json"><?php echo json_encode(json_decode($rule['conditions_json']), JSON_PRETTY_PRINT); ?></pre>
                        </div>

                        <div class="rule-actions mt-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="editRule(<?php echo $rule['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(<?php echo $rule['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="testRule(<?php echo $rule['id']; ?>)">
                                <i class="fas fa-flask"></i> Test Rule
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="add-rule-section mt-3">
            <button class="btn btn-success" onclick="addNewRule()">
                <i class="fas fa-plus"></i> Add New Rule
            </button>
        </div>
    </div>

    <hr class="my-4">

    <!-- AI Performance -->
    <div class="settings-section">
        <h5>📊 AI Performance Metrics</h5>
        <?php
        $accuracyStats = $aiEngine->getAccuracyStats();
        ?>
        <div class="row">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Overall Accuracy</div>
                    <div class="metric-value"><?php echo $accuracyStats['accuracy']; ?>%</div>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Total Decisions</div>
                    <div class="metric-value"><?php echo $accuracyStats['total_decisions']; ?></div>
                    <small class="text-muted">AI decisions made</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Avg Confidence</div>
                    <div class="metric-value"><?php echo $accuracyStats['avg_confidence']; ?>%</div>
                    <small class="text-muted">Decision confidence</small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.config-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.rule-card {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s ease;
}

.rule-card.active {
    border-color: #28a745;
    background: #f8fff9;
}

.rule-card.inactive {
    opacity: 0.6;
    background: #f8f9fa;
}

.rule-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.rule-info h6 {
    margin-bottom: 5px;
}

.conditions-json {
    background: #2d2d2d;
    color: #f8f8f2;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.action-badges {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.metric-card {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.metric-label {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 10px;
}

.metric-value {
    font-size: 32px;
    font-weight: bold;
    color: #28a745;
}
</style>

<script>
function updateConfig(configId, value) {
    fetch('api/update-config.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({config_id: configId, value: value})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Configuration updated', 'success');
        }
    });
}

function toggleRule(ruleId, enabled) {
    fetch('api/toggle-rule.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rule_id: ruleId, enabled: enabled})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Rule ' + (enabled ? 'enabled' : 'disabled'), 'success');
        }
    });
}

function updateRulePriority(ruleId, priority) {
    fetch('api/update-rule.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rule_id: ruleId, priority: priority})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Priority updated', 'success');
        }
    });
}

function updateRuleConfidence(ruleId, confidence) {
    fetch('api/update-rule.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rule_id: ruleId, confidence_required: confidence})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Confidence threshold updated', 'success');
        }
    });
}

function editRule(ruleId) {
    // Open modal to edit rule
    alert('Edit rule #' + ruleId);
}

function deleteRule(ruleId) {
    if (confirm('Are you sure you want to delete this rule?')) {
        fetch('api/delete-rule.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({rule_id: ruleId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function testRule(ruleId) {
    // Open test interface
    alert('Test rule #' + ruleId + ' with sample data');
}

function addNewRule() {
    // Open modal to create new rule
    alert('Add new AI rule');
}

function showToast(message, type) {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
