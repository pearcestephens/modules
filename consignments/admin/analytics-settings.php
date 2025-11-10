<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Settings Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .preset-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .preset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .preset-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .complexity-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
        }
        .setting-group {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }
        .setting-row {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .setting-row:last-child {
            border-bottom: none;
        }
        .level-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .level-global { background-color: #6c757d; }
        .level-outlet { background-color: #0d6efd; }
        .level-user { background-color: #198754; }
        .level-transfer_override { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h1><i class="bi bi-sliders"></i> Analytics Settings Manager</h1>
            <p class="text-muted">Customize every feature from VERY BASIC to EXPERT mode</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" onclick="window.location.href='barcode-management.php'">
                <i class="bi bi-arrow-left"></i> Back to Barcode Management
            </button>
        </div>
    </div>

    <div class="row">

        <!-- Left Column: Settings Level Selector -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-layers"></i> Settings Level
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action active" data-level="user" onclick="selectLevel('user', event)">
                        <i class="bi bi-person-fill"></i> My Settings
                        <br><small class="text-muted">Personal preferences</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-level="outlet" onclick="selectLevel('outlet', event)">
                        <i class="bi bi-shop"></i> Outlet Settings
                        <br><small class="text-muted">Store-wide defaults</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-level="global" onclick="selectLevel('global', event)">
                        <i class="bi bi-globe"></i> Global Settings
                        <br><small class="text-muted">Company defaults</small>
                    </a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightning-fill"></i> Quick Actions
                </div>
                <div class="card-body">
                    <button class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="showPresets()">
                        <i class="bi bi-stars"></i> Apply Preset
                    </button>
                    <button class="btn btn-sm btn-outline-danger w-100 mb-2" onclick="resetToDefaults()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                    </button>
                    <button class="btn btn-sm btn-outline-success w-100" onclick="exportSettings()">
                        <i class="bi bi-download"></i> Export Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Column: Settings Display -->
        <div class="col-md-9">

            <!-- Complexity Presets (Hidden initially) -->
            <div id="presetsSection" class="card mb-4" style="display: none;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-stars"></i> Complexity Presets</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="presetsContainer">
                        <!-- Presets loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Current Settings -->
            <div class="card">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-gear-fill"></i>
                        <span id="settingsTitle">My Settings</span>
                    </h5>
                    <div>
                        <span class="level-indicator level-global"></span> Global
                        <span class="level-indicator level-outlet ms-2"></span> Outlet
                        <span class="level-indicator level-user ms-2"></span> User
                        <span class="level-indicator level-transfer_override ms-2"></span> Override
                    </div>
                </div>
                <div class="card-body">

                    <!-- Loading Spinner -->
                    <div id="loadingSpinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading settings...</p>
                    </div>

                    <!-- Settings Content -->
                    <div id="settingsContent" style="display: none;">
                        <!-- Settings loaded dynamically -->
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- Apply Preset Modal -->
<div class="modal fade" id="presetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stars"></i> Apply Complexity Preset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="modalPresetsContainer">
                    <!-- Presets -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applySelectedPreset()">
                    <i class="bi bi-check-circle"></i> Apply Preset
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentLevel = 'user';
let currentUserId = <?php echo $_SESSION['userID'] ?? 1; ?>;
let currentOutletId = '<?php echo $_SESSION['outlet_id'] ?? 'OUTLET001'; ?>';
let selectedPreset = null;
let allPresets = [];

// =====================================================
// Initialize
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    loadPresets();
});

// =====================================================
// Select Settings Level
// =====================================================
function selectLevel(level, event) {
    if (event) event.preventDefault();

    currentLevel = level;

    // Update active state
    document.querySelectorAll('[data-level]').forEach(el => {
        el.classList.remove('active');
    });
    event.target.closest('[data-level]').classList.add('active');

    // Update title
    const titles = {
        'user': 'My Settings',
        'outlet': 'Outlet Settings',
        'global': 'Global Settings'
    };
    document.getElementById('settingsTitle').textContent = titles[level];

    loadSettings();
}

// =====================================================
// Load Settings
// =====================================================
async function loadSettings() {
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('settingsContent').style.display = 'none';

    try {
        let url = '';
        if (currentLevel === 'user') {
            url = `api/analytics_settings.php?action=get_settings&user_id=${currentUserId}`;
        } else if (currentLevel === 'outlet') {
            url = `api/analytics_settings.php?action=get_outlet_settings&outlet_id=${currentOutletId}`;
        } else {
            url = `api/analytics_settings.php?action=get_global_settings`;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            renderSettings(data.settings);
        } else {
            alert('Error loading settings: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load settings');
    }

    document.getElementById('loadingSpinner').style.display = 'none';
    document.getElementById('settingsContent').style.display = 'block';
}

// =====================================================
// Render Settings
// =====================================================
function renderSettings(settings) {
    const container = document.getElementById('settingsContent');
    container.innerHTML = '';

    const categoryIcons = {
        'fraud_detection': 'shield-fill-check',
        'performance_tracking': 'speedometer2',
        'gamification': 'trophy-fill',
        'leaderboards': 'bar-chart-fill',
        'photo_requirements': 'camera-fill',
        'notifications': 'bell-fill',
        'reviews': 'star-fill',
        'ui_features': 'palette-fill'
    };

    const categoryNames = {
        'fraud_detection': 'Fraud Detection & Security',
        'performance_tracking': 'Performance Tracking',
        'gamification': 'Gamification & Achievements',
        'leaderboards': 'Leaderboards',
        'photo_requirements': 'Photo Requirements',
        'notifications': 'Notifications',
        'reviews': 'Transfer Reviews',
        'ui_features': 'UI Features'
    };

    Object.keys(settings).forEach(category => {
        const categorySettings = settings[category];

        const groupDiv = document.createElement('div');
        groupDiv.className = 'setting-group';
        groupDiv.innerHTML = `
            <h5>
                <i class="bi bi-${categoryIcons[category] || 'gear-fill'}"></i>
                ${categoryNames[category] || category}
            </h5>
        `;

        Object.keys(categorySettings).forEach(key => {
            const setting = categorySettings[key];
            const value = setting.value || setting;
            const source = setting.source || 'unknown';
            const description = setting.description || '';

            const rowDiv = document.createElement('div');
            rowDiv.className = 'setting-row d-flex justify-content-between align-items-center';

            const isBoolean = value === 'true' || value === 'false' || value === true || value === false;
            const boolValue = value === 'true' || value === true;

            rowDiv.innerHTML = `
                <div class="flex-grow-1">
                    <span class="level-indicator level-${source}"></span>
                    <strong>${formatKey(key)}</strong>
                    ${description ? `<br><small class="text-muted">${description}</small>` : ''}
                </div>
                <div class="d-flex align-items-center gap-2">
                    ${isBoolean ? `
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   ${boolValue ? 'checked' : ''}
                                   onchange="toggleSetting('${category}', '${key}', this.checked)">
                        </div>
                    ` : `
                        <input type="text" class="form-control form-control-sm"
                               style="width: 150px;" value="${value}"
                               onchange="updateSetting('${category}', '${key}', this.value)">
                    `}
                    <span class="badge bg-secondary">${source}</span>
                </div>
            `;

            groupDiv.appendChild(rowDiv);
        });

        container.appendChild(groupDiv);
    });
}

// =====================================================
// Format Setting Key
// =====================================================
function formatKey(key) {
    return key.replace(/_/g, ' ')
              .replace(/\b\w/g, l => l.toUpperCase());
}

// =====================================================
// Toggle Setting
// =====================================================
async function toggleSetting(category, key, enabled) {
    try {
        const data = {
            action: 'toggle_feature',
            level: currentLevel,
            id: currentLevel === 'user' ? currentUserId : currentOutletId,
            category: category,
            setting_key: key,
            enabled: enabled
        };

        const response = await fetch('api/analytics_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Setting updated successfully', 'success');
        } else {
            alert('Error: ' + result.error);
            // Reload to revert
            loadSettings();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update setting');
        loadSettings();
    }
}

// =====================================================
// Update Setting
// =====================================================
async function updateSetting(category, key, value) {
    try {
        const action = currentLevel === 'user' ? 'update_user_preference' :
                      currentLevel === 'outlet' ? 'update_outlet_setting' :
                      'update_global_setting';

        const data = {
            action: action,
            category: category,
            setting_key: key,
            setting_value: value
        };

        if (currentLevel === 'user') {
            data.user_id = currentUserId;
        } else if (currentLevel === 'outlet') {
            data.outlet_id = currentOutletId;
        }

        const response = await fetch('api/analytics_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Setting updated', 'success');
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update setting');
    }
}

// =====================================================
// Load Presets
// =====================================================
async function loadPresets() {
    try {
        const response = await fetch('api/analytics_settings.php?action=get_presets');
        const data = await response.json();

        if (data.success) {
            allPresets = data.presets;
            renderPresets();
        }
    } catch (error) {
        console.error('Error loading presets:', error);
    }
}

// =====================================================
// Render Presets
// =====================================================
function renderPresets() {
    const container = document.getElementById('modalPresetsContainer');
    container.innerHTML = '';

    const levelColors = {
        'very_basic': 'success',
        'basic': 'info',
        'intermediate': 'primary',
        'advanced': 'warning',
        'very_advanced': 'danger',
        'expert': 'dark'
    };

    allPresets.forEach(preset => {
        const color = levelColors[preset.preset_level] || 'secondary';

        const col = document.createElement('div');
        col.className = 'col-md-6';
        col.innerHTML = `
            <div class="preset-card card h-100" onclick="selectPreset('${preset.preset_name}', event)">
                <div class="card-body">
                    <h5 class="card-title">
                        ${preset.preset_name}
                        <span class="badge bg-${color} complexity-badge float-end">
                            ${preset.preset_level.replace('_', ' ').toUpperCase()}
                        </span>
                    </h5>
                    <p class="card-text text-muted">${preset.description}</p>
                </div>
            </div>
        `;
        container.appendChild(col);
    });
}

// =====================================================
// Show Presets
// =====================================================
function showPresets() {
    renderPresets();
    const modal = new bootstrap.Modal(document.getElementById('presetModal'));
    modal.show();
}

// =====================================================
// Select Preset
// =====================================================
function selectPreset(presetName, event) {
    selectedPreset = presetName;

    // Remove selected class from all
    document.querySelectorAll('.preset-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Add to clicked
    event.currentTarget.classList.add('selected');
}

// =====================================================
// Apply Selected Preset
// =====================================================
async function applySelectedPreset() {
    if (!selectedPreset) {
        alert('Please select a preset first');
        return;
    }

    if (!confirm(`Apply "${selectedPreset}" preset? This will override current settings.`)) {
        return;
    }

    try {
        const action = currentLevel === 'user' ? 'apply_preset_to_user' : 'apply_preset_to_outlet';

        const data = {
            action: action,
            preset_name: selectedPreset
        };

        if (currentLevel === 'user') {
            data.user_id = currentUserId;
        } else if (currentLevel === 'outlet') {
            data.outlet_id = currentOutletId;
            data.updated_by = currentUserId;
        }

        const response = await fetch('api/analytics_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast(`Preset "${selectedPreset}" applied successfully!`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('presetModal')).hide();
            setTimeout(() => loadSettings(), 500);
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to apply preset');
    }
}

// =====================================================
// Reset to Defaults
// =====================================================
async function resetToDefaults() {
    if (!confirm('Reset all settings to defaults? This cannot be undone.')) {
        return;
    }

    try {
        const data = {
            action: 'reset_to_defaults',
            level: currentLevel,
            id: currentLevel === 'user' ? currentUserId : currentOutletId
        };

        const response = await fetch('api/analytics_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Settings reset to defaults', 'success');
            setTimeout(() => loadSettings(), 500);
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to reset settings');
    }
}

// =====================================================
// Export Settings
// =====================================================
function exportSettings() {
    // Create JSON export
    const settingsData = {
        level: currentLevel,
        exported_at: new Date().toISOString(),
        settings: document.getElementById('settingsContent').textContent
    };

    const blob = new Blob([JSON.stringify(settingsData, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `analytics-settings-${currentLevel}-${Date.now()}.json`;
    a.click();

    showToast('Settings exported', 'success');
}

// =====================================================
// Toast Notification
// =====================================================
function showToast(message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0 position-fixed top-0 end-0 m-3" role="alert" style="z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = toastHtml;
    document.body.appendChild(div);

    const toast = new bootstrap.Toast(div.querySelector('.toast'));
    toast.show();

    setTimeout(() => div.remove(), 5000);
}
</script>

</body>
</html>
