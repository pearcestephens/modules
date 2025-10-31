<?php
/**
 * Theme Builder - Visual Interface
 * Edit and preview theme changes in real-time
 * 
 * @package CIS\Modules\AdminUI
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';
require_once __DIR__ . '/lib/ThemeGenerator.php';
require_once __DIR__ . '/lib/AIThemeAssistant.php';

$generator = new ThemeGenerator();
$aiAssistant = new AIThemeAssistant();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_theme') {
        $updates = json_decode($_POST['updates'], true);
        $newConfig = $generator->updateConfig($updates);
        echo json_encode(['success' => true, 'config' => $newConfig]);
        exit;
    }
    
    if ($_POST['action'] === 'generate_css') {
        $generator->generatePrimaryTheme();
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'get_changelog') {
        $changelog = $generator->getChangelog();
        echo json_encode(['success' => true, 'changelog' => $changelog]);
        exit;
    }
    
    if ($_POST['action'] === 'ai_message') {
        $message = $_POST['message'] ?? '';
        $currentConfig = require __DIR__ . '/../config/theme-config.php';
        $result = $aiAssistant->processMessage($message, $currentConfig);
        echo json_encode($result);
        exit;
    }
    
    if ($_POST['action'] === 'ai_check') {
        echo json_encode(['available' => $aiAssistant->isAvailable()]);
        exit;
    }
}

// Load current config (from admin-ui/config/ not ../config/)
$config = require __DIR__ . '/config/theme-config.php';
$changelog = $generator->getChangelog();

// Initialize custom CSS if needed
$generator->initializeCustomCSS();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Theme Builder - Admin UI Module</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="_templates/css/theme-generated.css">
    <link rel="stylesheet" href="_templates/css/theme-custom.css">
    <style>
        body { background: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .theme-builder-header { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .color-input-group { margin-bottom: 1.5rem; }
        .color-input-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151; }
        .color-input-wrapper { display: flex; align-items: center; gap: 1rem; }
        .color-input-wrapper input[type="color"] { width: 60px; height: 40px; border: 2px solid #e5e7eb; border-radius: 6px; cursor: pointer; }
        .color-input-wrapper input[type="text"] { flex: 1; font-family: 'Courier New', monospace; }
        .preview-section { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.07); }
        .changelog-item { padding: 1rem; border-left: 3px solid #8B5CF6; background: #f9fafb; margin-bottom: 1rem; border-radius: 6px; }
        .changelog-item .timestamp { font-size: 0.875rem; color: #6b7280; }
        .changelog-item .action { font-weight: 600; color: #8B5CF6; }
        .save-btn { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; padding: 0.75rem 2rem; border-radius: 6px; color: white; font-weight: 600; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3); transition: all 0.2s; }
        .save-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4); }
        .generate-btn { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); }
        .section-header { font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e5e7eb; color: #111827; }
        .preview-component { margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 8px; }
        .version-badge { background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; }
        
        /* AI Assistant Styles */
        .ai-assistant-btn { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; border: none; box-shadow: 0 8px 16px rgba(139, 92, 246, 0.4); cursor: pointer; z-index: 9999; transition: all 0.3s; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .ai-assistant-btn:hover { transform: scale(1.1); box-shadow: 0 12px 24px rgba(139, 92, 246, 0.6); }
        .ai-assistant-btn.pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 8px 16px rgba(139, 92, 246, 0.4); } 50% { box-shadow: 0 8px 24px rgba(139, 92, 246, 0.8); } }
        
        .ai-chat-modal { display: none; position: fixed; bottom: 110px; right: 30px; width: 400px; height: 600px; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); z-index: 9998; flex-direction: column; overflow: hidden; }
        .ai-chat-modal.active { display: flex; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .ai-chat-header { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .ai-chat-header h5 { margin: 0; font-weight: 600; }
        .ai-chat-close { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; }
        .ai-chat-close:hover { opacity: 1; }
        
        .ai-chat-messages { flex: 1; overflow-y: auto; padding: 1rem; background: #f9fafb; }
        .ai-message { margin-bottom: 1rem; display: flex; gap: 0.75rem; }
        .ai-message.user { flex-direction: row-reverse; }
        .ai-message-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.25rem; }
        .ai-message.bot .ai-message-avatar { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; }
        .ai-message.user .ai-message-avatar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .ai-message-content { background: white; padding: 0.75rem 1rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); max-width: 80%; }
        .ai-message.user .ai-message-content { background: #8B5CF6; color: white; }
        .ai-message-time { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
        
        .ai-chat-input { padding: 1rem; background: white; border-top: 1px solid #e5e7eb; display: flex; gap: 0.5rem; }
        .ai-chat-input input { flex: 1; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.75rem; font-size: 0.875rem; }
        .ai-chat-input button { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .ai-chat-input button:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }
        .ai-chat-input button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .ai-typing { display: flex; gap: 4px; padding: 0.5rem 0; }
        .ai-typing span { width: 8px; height: 8px; border-radius: 50%; background: #8B5CF6; animation: typing 1.4s infinite; }
        .ai-typing span:nth-child(2) { animation-delay: 0.2s; }
        .ai-typing span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
        
        .ai-unavailable { text-align: center; padding: 2rem; color: #6b7280; }
        .ai-unavailable i { font-size: 3rem; margin-bottom: 1rem; color: #d1d5db; }
    </style>
</head>
<body>
    <div class="theme-builder-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><i class="fas fa-palette"></i> CIS Theme Builder</h1>
                    <p class="mb-0">Admin UI Module - Programmatic Theme Management</p>
                </div>
                <span class="version-badge">v<?= $config['version'] ?></span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Left Panel - Theme Editor -->
            <div class="col-lg-4">
                <div class="preview-section">
                    <div class="section-header"><i class="fas fa-sliders-h"></i> Theme Configuration</div>
                    
                    <h6 class="font-weight-bold text-muted mb-3">Primary Colors</h6>
                    <div class="color-input-group">
                        <label>Main</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="primary-main" value="<?= $config['primary']['main'] ?>">
                            <input type="text" class="form-control" id="primary-main-text" value="<?= $config['primary']['main'] ?>">
                        </div>
                    </div>
                    
                    <div class="color-input-group">
                        <label>Light</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="primary-light" value="<?= $config['primary']['light'] ?>">
                            <input type="text" class="form-control" id="primary-light-text" value="<?= $config['primary']['light'] ?>">
                        </div>
                    </div>
                    
                    <div class="color-input-group">
                        <label>Dark</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="primary-dark" value="<?= $config['primary']['dark'] ?>">
                            <input type="text" class="form-control" id="primary-dark-text" value="<?= $config['primary']['dark'] ?>">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="font-weight-bold text-muted mb-3">Status Colors</h6>
                    <div class="color-input-group">
                        <label>Success</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="success" value="<?= $config['success'] ?>">
                            <input type="text" class="form-control" id="success-text" value="<?= $config['success'] ?>">
                        </div>
                    </div>
                    
                    <div class="color-input-group">
                        <label>Warning</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="warning" value="<?= $config['warning'] ?>">
                            <input type="text" class="form-control" id="warning-text" value="<?= $config['warning'] ?>">
                        </div>
                    </div>
                    
                    <div class="color-input-group">
                        <label>Danger</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="danger" value="<?= $config['danger'] ?>">
                            <input type="text" class="form-control" id="danger-text" value="<?= $config['danger'] ?>">
                        </div>
                    </div>
                    
                    <div class="color-input-group">
                        <label>Info</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="info" value="<?= $config['info'] ?>">
                            <input type="text" class="form-control" id="info-text" value="<?= $config['info'] ?>">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="font-weight-bold text-muted mb-3">Sidebar</h6>
                    <div class="color-input-group">
                        <label>Background</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="sidebar-bg" value="<?= $config['sidebar']['bg'] ?>">
                            <input type="text" class="form-control" id="sidebar-bg-text" value="<?= $config['sidebar']['bg'] ?>">
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button class="btn save-btn btn-lg btn-block" id="save-theme">
                            <i class="fas fa-save"></i> Save & Generate CSS
                        </button>
                        <button class="btn generate-btn btn-lg btn-block text-white mt-2" id="preview-changes">
                            <i class="fas fa-eye"></i> Preview Changes
                        </button>
                    </div>
                </div>
                
                <!-- Changelog -->
                <div class="preview-section">
                    <div class="section-header"><i class="fas fa-history"></i> Change Log</div>
                    <div id="changelog-container" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($changelog)): ?>
                            <p class="text-muted text-center">No changes yet</p>
                        <?php else: ?>
                            <?php foreach (array_reverse(array_slice($changelog, -10)) as $entry): ?>
                                <div class="changelog-item">
                                    <div class="action"><?= htmlspecialchars($entry['action']) ?></div>
                                    <div><?= htmlspecialchars($entry['description']) ?></div>
                                    <div class="timestamp"><?= htmlspecialchars($entry['timestamp']) ?> - v<?= htmlspecialchars($entry['version']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Live Preview -->
            <div class="col-lg-8">
                <div class="preview-section">
                    <div class="section-header"><i class="fas fa-desktop"></i> Live Preview</div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Buttons</h6>
                        <button class="btn btn-primary mr-2 mb-2"><i class="fas fa-check"></i> Primary Button</button>
                        <button class="btn btn-success mr-2 mb-2"><i class="fas fa-check-circle"></i> Success</button>
                        <button class="btn btn-warning mr-2 mb-2"><i class="fas fa-exclamation-triangle"></i> Warning</button>
                        <button class="btn btn-danger mr-2 mb-2"><i class="fas fa-times"></i> Danger</button>
                        <button class="btn btn-info mr-2 mb-2"><i class="fas fa-info-circle"></i> Info</button>
                    </div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Cards</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <h6 class="font-weight-bold">Card Title</h6>
                                    <p class="mb-0 text-muted">Card with hover effect</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <h6 class="font-weight-bold">Another Card</h6>
                                    <p class="mb-0 text-muted">Hover to see animation</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <h6 class="font-weight-bold">Third Card</h6>
                                    <p class="mb-0 text-muted">Uses CSS variables</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Badges</h6>
                        <span class="badge badge-primary mr-2">Primary</span>
                        <span class="badge badge-success mr-2">Success</span>
                        <span class="badge badge-warning mr-2">Warning</span>
                        <span class="badge badge-danger mr-2">Danger</span>
                        <span class="badge badge-info mr-2">Info</span>
                    </div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Alerts</h6>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> This is a success alert using theme colors!
                        </div>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> This is a warning alert using theme colors!
                        </div>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-times-circle"></i> This is a danger alert using theme colors!
                        </div>
                    </div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Form Elements</h6>
                        <div class="form-group">
                            <label>Text Input</label>
                            <input type="text" class="form-control" placeholder="Enter text...">
                        </div>
                        <div class="form-group">
                            <label>Select Dropdown</label>
                            <select class="form-control">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="preview-component">
                        <h6 class="font-weight-bold mb-3">Table</h6>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Item One</td>
                                    <td><span class="badge badge-success">Active</span></td>
                                    <td><button class="btn btn-sm btn-primary">Edit</button></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Item Two</td>
                                    <td><span class="badge badge-warning">Pending</span></td>
                                    <td><button class="btn btn-sm btn-primary">Edit</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Generated CSS Preview -->
                <div class="preview-section">
                    <div class="section-header"><i class="fas fa-code"></i> Generated CSS</div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> CSS is auto-generated to: <code>/modules/admin-ui/_templates/css/theme-generated.css</code>
                    </div>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Custom overrides: <code>/modules/admin-ui/_templates/css/theme-custom.css</code>
                    </div>
                    <p class="text-muted">The generated CSS uses CSS variables for easy theming. All components reference these variables, so changes propagate automatically.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Assistant Button (only show if available) -->
    <button class="ai-assistant-btn pulse" id="ai-toggle" style="display: none;">
        <i class="fas fa-robot"></i>
    </button>
    
    <!-- AI Chat Modal -->
    <div class="ai-chat-modal" id="ai-chat-modal">
        <div class="ai-chat-header">
            <h5><i class="fas fa-robot"></i> Theme Assistant</h5>
            <button class="ai-chat-close" id="ai-close">×</button>
        </div>
        <div class="ai-chat-messages" id="ai-messages">
            <div class="ai-message bot">
                <div class="ai-message-avatar"><i class="fas fa-robot"></i></div>
                <div>
                    <div class="ai-message-content">
                        Hi! I'm your AI theme assistant. Tell me what you'd like to change, and I'll update the theme in real-time. Try saying things like:
                        <ul class="mb-0 mt-2 pl-3">
                            <li>"Make the primary color blue"</li>
                            <li>"Change sidebar to dark gray"</li>
                            <li>"Use green for success messages"</li>
                        </ul>
                    </div>
                    <div class="ai-message-time">Just now</div>
                </div>
            </div>
        </div>
        <div class="ai-chat-input">
            <input type="text" id="ai-input" placeholder="Type your request...">
            <button id="ai-send"><i class="fas fa-paper-plane"></i></button>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Theme builder initialized');
            
        // Check AI availability on load
        let aiAvailable = false;
        
        (async function checkAI() {
            try {
                const formData = new FormData();
                formData.append('action', 'ai_check');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                aiAvailable = result.available;
                
                if (aiAvailable) {
                    document.getElementById('ai-toggle').style.display = 'flex';
                }
            } catch (error) {
                console.log('AI assistant not available');
            }
        })();
        
        // AI Chat Toggle
        document.getElementById('ai-toggle')?.addEventListener('click', () => {
            document.getElementById('ai-chat-modal').classList.add('active');
            document.getElementById('ai-toggle').classList.remove('pulse');
        });
        
        document.getElementById('ai-close')?.addEventListener('click', () => {
            document.getElementById('ai-chat-modal').classList.remove('active');
        });
        
        // AI Message Handling
        const aiInput = document.getElementById('ai-input');
        const aiSend = document.getElementById('ai-send');
        const aiMessages = document.getElementById('ai-messages');
        
        async function sendAIMessage() {
            const message = aiInput.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage('user', message);
            aiInput.value = '';
            aiSend.disabled = true;
            
            // Show typing indicator
            const typingDiv = document.createElement('div');
            typingDiv.className = 'ai-message bot';
            typingDiv.innerHTML = `
                <div class="ai-message-avatar"><i class="fas fa-robot"></i></div>
                <div class="ai-typing">
                    <span></span><span></span><span></span>
                </div>
            `;
            aiMessages.appendChild(typingDiv);
            aiMessages.scrollTop = aiMessages.scrollHeight;
            
            try {
                const formData = new FormData();
                formData.append('action', 'ai_message');
                formData.append('message', message);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                // Remove typing indicator
                aiMessages.removeChild(typingDiv);
                
                if (result.success && result.updates) {
                    // Apply updates to the theme
                    applyAIUpdates(result.updates);
                    
                    // Show AI response
                    addMessage('bot', result.explanation || 'Changes applied!');
                    
                    // Show preview text
                    if (result.preview_text) {
                        setTimeout(() => {
                            addMessage('bot', `Preview: ${result.preview_text}`);
                        }, 500);
                    }
                } else {
                    // Fallback: Try to parse the message locally
                    const localUpdates = parseMessageLocally(message);
                    if (Object.keys(localUpdates).length > 0) {
                        applyLocalUpdates(localUpdates);
                        addMessage('bot', 'I\'ve updated the theme based on your request. Preview the changes above!');
                    } else {
                        addMessage('bot', 'I couldn\'t understand that request. Try being more specific about colors or components.');
                    }
                }
            } catch (error) {
                aiMessages.removeChild(typingDiv);
                addMessage('bot', 'Sorry, I had trouble processing that. You can manually adjust the colors on the left.');
            }
            
            aiSend.disabled = false;
        }
        
        function addMessage(type, text) {
            const div = document.createElement('div');
            div.className = `ai-message ${type}`;
            const avatar = type === 'bot' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';
            div.innerHTML = `
                <div class="ai-message-avatar">${avatar}</div>
                <div>
                    <div class="ai-message-content">${text}</div>
                    <div class="ai-message-time">${new Date().toLocaleTimeString()}</div>
                </div>
            `;
            aiMessages.appendChild(div);
            aiMessages.scrollTop = aiMessages.scrollHeight;
        }
        
        function parseMessageLocally(message) {
            const updates = {};
            const msg = message.toLowerCase();
            
            // Simple pattern matching
            const colorPatterns = [
                { pattern: /primary.*(#[0-9a-f]{6}|blue|purple|red|green|orange)/i, key: 'primary-main' },
                { pattern: /sidebar.*(#[0-9a-f]{6}|dark|light|gray|black)/i, key: 'sidebar-bg' },
                { pattern: /success.*(#[0-9a-f]{6}|green)/i, key: 'success' },
                { pattern: /warning.*(#[0-9a-f]{6}|orange|yellow)/i, key: 'warning' },
                { pattern: /danger.*(#[0-9a-f]{6}|red)/i, key: 'danger' },
                { pattern: /info.*(#[0-9a-f]{6}|blue)/i, key: 'info' },
            ];
            
            const colorMap = {
                'blue': '#3b82f6',
                'red': '#ef4444',
                'green': '#10b981',
                'purple': '#8B5CF6',
                'orange': '#f59e0b',
                'yellow': '#f59e0b',
                'dark': '#1f2937',
                'light': '#f3f4f6',
                'gray': '#6b7280',
                'black': '#000000',
            };
            
            colorPatterns.forEach(({ pattern, key }) => {
                const match = message.match(pattern);
                if (match) {
                    let color = match[1].toLowerCase();
                    if (colorMap[color]) {
                        color = colorMap[color];
                    }
                    if (/^#[0-9a-f]{6}$/i.test(color)) {
                        updates[key] = color;
                    }
                }
            });
            
            return updates;
        }
        
        function applyLocalUpdates(updates) {
            Object.keys(updates).forEach(key => {
                const input = document.getElementById(key);
                const textInput = document.getElementById(key + '-text');
                if (input) {
                    input.value = updates[key];
                    if (textInput) {
                        textInput.value = updates[key];
                    }
                }
            });
            updatePreview();
        }
        
        function applyAIUpdates(updates) {
            // Apply updates from AI response
            if (updates.primary?.main) {
                document.getElementById('primary-main').value = updates.primary.main;
                document.getElementById('primary-main-text').value = updates.primary.main;
            }
            if (updates.sidebar?.bg) {
                document.getElementById('sidebar-bg').value = updates.sidebar.bg;
                document.getElementById('sidebar-bg-text').value = updates.sidebar.bg;
            }
            if (updates.success) {
                document.getElementById('success').value = updates.success;
                document.getElementById('success-text').value = updates.success;
            }
            if (updates.warning) {
                document.getElementById('warning').value = updates.warning;
                document.getElementById('warning-text').value = updates.warning;
            }
            if (updates.danger) {
                document.getElementById('danger').value = updates.danger;
                document.getElementById('danger-text').value = updates.danger;
            }
            if (updates.info) {
                document.getElementById('info').value = updates.info;
                document.getElementById('info-text').value = updates.info;
            }
            updatePreview();
        } // End applyAIUpdates function
        
        aiSend?.addEventListener('click', sendAIMessage);
        aiInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendAIMessage();
            }
        });
        
        // Sync color pickers with text inputs
        const colorPickers = document.querySelectorAll('input[type="color"]');
        console.log('Found', colorPickers.length, 'color pickers');
        
        colorPickers.forEach(picker => {
            const textInput = document.getElementById(picker.id + '-text');
            console.log('Setting up listeners for:', picker.id, textInput);
            
            picker.addEventListener('input', () => {
                console.log('Color picker changed:', picker.id, '=', picker.value);
                textInput.value = picker.value;
                updatePreview();
            });
            
            textInput.addEventListener('input', () => {
                if (/^#[0-9A-F]{6}$/i.test(textInput.value)) {
                    console.log('Text input changed:', textInput.id, '=', textInput.value);
                    picker.value = textInput.value;
                    updatePreview();
                }
            });
        });
        
        // Preview changes in real-time
        function updatePreview() {
            console.log('updatePreview() called');
            const root = document.documentElement;
            
            const primaryMain = document.getElementById('primary-main').value;
            console.log('Setting --cis-primary to:', primaryMain);
            
            root.style.setProperty('--cis-primary', primaryMain);
            root.style.setProperty('--cis-primary-light', document.getElementById('primary-light').value);
            root.style.setProperty('--cis-primary-dark', document.getElementById('primary-dark').value);
            root.style.setProperty('--cis-success', document.getElementById('success').value);
            root.style.setProperty('--cis-warning', document.getElementById('warning').value);
            root.style.setProperty('--cis-danger', document.getElementById('danger').value);
            root.style.setProperty('--cis-info', document.getElementById('info').value);
            root.style.setProperty('--cis-sidebar-bg', document.getElementById('sidebar-bg').value);
            
            console.log('CSS variables updated');
        }
        
        // Save theme
        document.getElementById('save-theme').addEventListener('click', async () => {
            const updates = {
                primary: {
                    main: document.getElementById('primary-main').value,
                    light: document.getElementById('primary-light').value,
                    dark: document.getElementById('primary-dark').value,
                },
                success: document.getElementById('success').value,
                warning: document.getElementById('warning').value,
                danger: document.getElementById('danger').value,
                info: document.getElementById('info').value,
                sidebar: {
                    bg: document.getElementById('sidebar-bg').value,
                }
            };
            
            const formData = new FormData();
            formData.append('action', 'update_theme');
            formData.append('updates', JSON.stringify(updates));
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Theme saved and CSS generated successfully! Version: ' + result.config.version);
                location.reload();
            }
        }); // End save-theme click handler
        
        }); // End DOMContentLoaded
    
    </script>
    
    <!-- Bootstrap JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
