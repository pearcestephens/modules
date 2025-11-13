<?php
/**
 * Theme Builder PRO v3.0.0
 * Full-featured theme editor with AI integration
 */

// Backend API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        switch ($action) {
            case 'save_theme':
                $themeData = json_decode($_POST['theme_data'], true);
                $themeId = $themeData['id'] ?? 'theme_' . time();
                $filePath = __DIR__ . '/themes/' . $themeId . '.json';
                
                if (!is_dir(__DIR__ . '/themes')) {
                    mkdir(__DIR__ . '/themes', 0755, true);
                }
                
                file_put_contents($filePath, json_encode($themeData, JSON_PRETTY_PRINT));
                $response = ['success' => true, 'theme_id' => $themeId];
                break;
                
            case 'load_theme':
                $themeId = $_POST['theme_id'];
                $filePath = __DIR__ . '/themes/' . $themeId . '.json';
                
                if (file_exists($filePath)) {
                    $theme = json_decode(file_get_contents($filePath), true);
                    $response = ['success' => true, 'data' => $theme];
                } else {
                    $response = ['success' => false, 'error' => 'Theme not found'];
                }
                break;
                
            case 'list_themes':
                $themes = [];
                if (is_dir(__DIR__ . '/themes')) {
                    foreach (glob(__DIR__ . '/themes/*.json') as $file) {
                        $theme = json_decode(file_get_contents($file), true);
                        $themes[] = [
                            'id' => $theme['id'],
                            'name' => $theme['name'],
                            'version' => $theme['version'] ?? '1.0.0',
                            'modified' => date('Y-m-d H:i:s', filemtime($file))
                        ];
                    }
                }
                $response = ['success' => true, 'data' => $themes];
                break;
                
            case 'save_component':
                $componentData = json_decode($_POST['component_data'], true);
                $componentId = $componentData['id'] ?? 'comp_' . time();
                $filePath = __DIR__ . '/_templates/components/' . $componentId . '.json';
                
                if (!is_dir(__DIR__ . '/_templates/components')) {
                    mkdir(__DIR__ . '/_templates/components', 0755, true);
                }
                
                file_put_contents($filePath, json_encode($componentData, JSON_PRETTY_PRINT));
                $response = ['success' => true, 'component_id' => $componentId];
                break;
                
            case 'load_component':
                $componentId = $_POST['component_id'];
                $filePath = __DIR__ . '/_templates/components/' . $componentId . '.json';
                
                if (file_exists($filePath)) {
                    $component = json_decode(file_get_contents($filePath), true);
                    $response = ['success' => true, 'data' => $component];
                } else {
                    $response = ['success' => false, 'error' => 'Component not found'];
                }
                break;
                
            case 'list_components':
                $components = [];
                if (is_dir(__DIR__ . '/_templates/components')) {
                    foreach (glob(__DIR__ . '/_templates/components/*.json') as $file) {
                        $component = json_decode(file_get_contents($file), true);
                        $components[] = [
                            'id' => $component['id'],
                            'name' => $component['name'],
                            'type' => $component['type'] ?? 'custom',
                            'created' => date('Y-m-d H:i:s', filectime($file))
                        ];
                    }
                }
                $response = ['success' => true, 'data' => $components];
                break;
                
            case 'delete_component':
                $componentId = $_POST['component_id'];
                $filePath = __DIR__ . '/_templates/components/' . $componentId . '.json';
                
                if (file_exists($filePath)) {
                    unlink($filePath);
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'error' => 'Component not found'];
                }
                break;
                
            case 'ai_analyze':
                // Placeholder for AI integration
                $response = [
                    'success' => true,
                    'data' => [
                        'suggestions' => [
                            'Consider using semantic HTML5 elements',
                            'Add meta viewport for mobile responsiveness',
                            'Use CSS variables for consistent theming'
                        ],
                        'warnings' => [
                            'Missing alt text on images',
                            'Consider adding ARIA labels for accessibility'
                        ],
                        'score' => 85
                    ]
                ];
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Unknown action'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Builder PRO v3.0.0</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#10b981;--primary-dark:#059669;--secondary:#3b82f6;--accent:#f59e0b;--danger:#ef4444;--bg-primary:#0f172a;--bg-secondary:#1e293b;--bg-tertiary:#334155;--text-primary:#f1f5f9;--text-secondary:#94a3b8}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg-primary);color:var(--text-primary);overflow:hidden;height:100vh}
        .top-bar{height:56px;background:var(--bg-secondary);border-bottom:1px solid var(--bg-tertiary);display:flex;align-items:center;padding:0 1.5rem;gap:1rem}
        .top-bar h1{font-size:1.25rem;font-weight:600;color:var(--primary);margin:0}
        .top-bar .btn{padding:0.5rem 1rem;border-radius:6px;font-weight:500;border:none;cursor:pointer;transition:all 0.2s}
        .btn-primary{background:var(--primary);color:white}
        .btn-primary:hover{background:var(--primary-dark)}
        .btn-secondary{background:var(--secondary);color:white}
        .btn-outline{background:transparent;color:var(--text-secondary);border:1px solid var(--bg-tertiary)}
        .btn-outline:hover{background:var(--bg-tertiary);color:var(--text-primary)}
        .main-container{display:flex;height:calc(100vh - 56px)}
        .sidebar{width:280px;background:var(--bg-secondary);border-right:1px solid var(--bg-tertiary);display:flex;flex-direction:column}
        .sidebar-tabs{display:flex;border-bottom:1px solid var(--bg-tertiary)}
        .sidebar-tab{flex:1;padding:1rem;background:transparent;border:none;color:var(--text-secondary);cursor:pointer;font-weight:500;transition:all 0.2s;border-bottom:2px solid transparent}
        .sidebar-tab:hover{color:var(--text-primary);background:var(--bg-primary)}
        .sidebar-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
        .tab-content{flex:1;overflow-y:auto;padding:1rem}
        .tab-content:not(:first-child){display:none}
        .item-card{background:var(--bg-tertiary);padding:0.75rem;border-radius:6px;margin-bottom:0.5rem;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:0.75rem}
        .item-card:hover{background:var(--bg-primary);transform:translateX(2px)}
        .item-icon{width:36px;height:36px;background:var(--primary);border-radius:6px;display:flex;align-items:center;justify-content:center;color:white}
        .item-info{flex:1}
        .item-name{font-weight:600;color:var(--text-primary);margin-bottom:0.25rem}
        .item-meta{font-size:0.75rem;color:var(--text-secondary)}
        .editor-section{flex:1;display:flex;flex-direction:column;background:var(--bg-primary)}
        .editor-tabs{display:flex;background:var(--bg-secondary);border-bottom:1px solid var(--bg-tertiary);padding:0 1rem}
        .editor-tab{padding:0.75rem 1.5rem;background:transparent;border:none;color:var(--text-secondary);cursor:pointer;font-weight:500;transition:all 0.2s;border-bottom:2px solid transparent}
        .editor-tab:hover{color:var(--text-primary)}
        .editor-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
        .editors-container{flex:1;position:relative}
        .editor-wrapper{position:absolute;top:0;left:0;right:0;bottom:0;display:none}
        .editor-wrapper.active{display:block}
        .preview-section{width:50%;background:var(--bg-secondary);border-left:1px solid var(--bg-tertiary);display:flex;flex-direction:column}
        .preview-toolbar{display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1rem;background:var(--bg-tertiary);border-bottom:1px solid var(--bg-primary)}
        .device-btn{padding:0.5rem 0.75rem;background:transparent;border:1px solid var(--bg-primary);color:var(--text-secondary);border-radius:6px;cursor:pointer;transition:all 0.2s}
        .device-btn:hover{background:var(--bg-primary);color:var(--text-primary)}
        .device-btn.active{background:var(--primary);color:white;border-color:var(--primary)}
        .preview-container{flex:1;padding:1rem;overflow:auto;background:#ffffff}
        .preview-container iframe{width:100%;height:100%;border:1px solid #dee2e6;border-radius:4px;background:white}
        .preview-container.tablet iframe{width:768px;height:1024px;margin:0 auto;display:block}
        .preview-container.mobile iframe{width:375px;height:667px;margin:0 auto;display:block}
        .fab-menu{position:fixed;bottom:24px;right:24px;z-index:9999}
        .fab-main{width:56px;height:56px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.3);transition:all 0.3s}
        .fab-main:hover{transform:scale(1.1)}
        .fab-menu.open .fab-main{transform:rotate(45deg)}
        .fab-actions{position:absolute;bottom:70px;right:0;display:none;flex-direction:column;gap:0.75rem}
        .fab-menu.open .fab-actions{display:flex}
        .fab-action{width:48px;height:48px;background:var(--secondary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.3);transition:all 0.2s;opacity:0;animation:fadeInUp 0.3s forwards}
        .fab-action:nth-child(1){animation-delay:0.05s}
        .fab-action:nth-child(2){animation-delay:0.1s}
        .fab-action:nth-child(3){animation-delay:0.15s}
        .fab-action:nth-child(4){animation-delay:0.2s}
        .fab-action:hover{transform:scale(1.1)}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:var(--bg-secondary);border:1px solid var(--bg-tertiary);border-radius:8px;padding:2rem;max-width:500px;width:90%}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
        .modal-header h3{color:var(--primary);margin:0}
        .form-group{margin-bottom:1rem}
        .form-group label{display:block;margin-bottom:0.5rem;color:var(--text-primary);font-weight:500}
        .form-control{width:100%;padding:0.5rem;background:var(--bg-tertiary);border:1px solid var(--bg-primary);border-radius:6px;color:var(--text-primary)}
        textarea.form-control{resize:vertical;min-height:100px}
        .modal-footer{display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1.5rem}
        .w-100{width:100%}
        .mb-3{margin-bottom:1rem}
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="/modules/admin-ui/" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        <h1><i class="fas fa-paint-brush"></i> Theme Builder PRO</h1>
        <div style="flex:1;"></div>
        <button class="btn btn-outline" onclick="window.ThemeBuilder.themes.create()"><i class="fas fa-plus"></i> New</button>
        <button class="btn btn-secondary" onclick="window.ThemeBuilder.themes.save()"><i class="fas fa-save"></i> Save</button>
        <button class="btn btn-primary" onclick="window.ThemeBuilder.themes.export()"><i class="fas fa-download"></i> Export</button>
        <button class="btn btn-outline" onclick="window.ThemeBuilder.keyboard.showShortcutsHelp()"><i class="fas fa-keyboard"></i></button>
    </div>
    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-tabs">
                <button class="sidebar-tab active" data-tab="components"><i class="fas fa-puzzle-piece"></i> Components</button>
                <button class="sidebar-tab" data-tab="themes"><i class="fas fa-palette"></i> Themes</button>
            </div>
            <div class="tab-content" data-content="components">
                <button class="btn btn-primary w-100 mb-3" onclick="window.ThemeBuilder.components.showCreateModal()"><i class="fas fa-plus"></i> New Component</button>
                <div id="components-list"></div>
            </div>
            <div class="tab-content" data-content="themes">
                <button class="btn btn-primary w-100 mb-3" onclick="window.ThemeBuilder.themes.import()"><i class="fas fa-upload"></i> Import</button>
                <div id="themes-list"></div>
            </div>
        </div>
        <div class="editor-section">
            <div class="editor-tabs">
                <button class="editor-tab active" data-editor="html"><i class="fab fa-html5"></i> HTML</button>
                <button class="editor-tab" data-editor="css"><i class="fab fa-css3-alt"></i> CSS</button>
                <button class="editor-tab" data-editor="js"><i class="fab fa-js"></i> JavaScript</button>
            </div>
            <div class="editors-container">
                <div class="editor-wrapper active" id="html-editor"></div>
                <div class="editor-wrapper" id="css-editor"></div>
                <div class="editor-wrapper" id="js-editor"></div>
            </div>
        </div>
        <div class="preview-section">
            <div class="preview-toolbar">
                <button class="device-btn active" data-device="desktop"><i class="fas fa-desktop"></i></button>
                <button class="device-btn" data-device="tablet"><i class="fas fa-tablet-alt"></i></button>
                <button class="device-btn" data-device="mobile"><i class="fas fa-mobile-alt"></i></button>
                <div style="flex:1;"></div>
                <button class="device-btn" onclick="window.ThemeBuilder.refreshPreview()"><i class="fas fa-sync"></i></button>
            </div>
            <div class="preview-container desktop" id="preview-container">
                <iframe class="preview-frame" id="preview-frame"></iframe>
            </div>
        </div>
    </div>
    <div class="fab-menu" id="fab-menu">
        <div class="fab-actions">
            <div class="fab-action" onclick="window.ThemeBuilder.history.showTimeline()"><i class="fas fa-history"></i></div>
            <div class="fab-action" onclick="window.ThemeBuilder.ai.toggleChat()"><i class="fas fa-robot"></i></div>
            <div class="fab-action" onclick="window.ThemeBuilder.ai.analyzeCode('all')"><i class="fas fa-search"></i></div>
            <div class="fab-action" onclick="window.ThemeBuilder.themes.save()"><i class="fas fa-save"></i></div>
        </div>
        <div class="fab-main" onclick="window.ThemeBuilder.ui.toggleFab()"><i class="fas fa-plus"></i></div>
    </div>
    <div class="modal" id="new-component-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-puzzle-piece"></i> New Component</h3>
                <button class="btn btn-outline" onclick="window.ThemeBuilder.ui.closeModal('new-component-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form id="new-component-form" onsubmit="event.preventDefault();window.ThemeBuilder.components.save(new FormData(this));">
                <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><option value="button">Button</option><option value="card">Card</option><option value="form">Form</option><option value="navbar">Navbar</option><option value="footer">Footer</option><option value="custom">Custom</option></select></div>
                <div class="form-group"><label>HTML</label><textarea name="html" class="form-control" required></textarea></div>
                <div class="form-group"><label>CSS (optional)</label><textarea name="css" class="form-control"></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="window.ThemeBuilder.ui.closeModal('new-component-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>var require={paths:{'vs':'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs'}};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.nls.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.js"></script>
    <script src="theme-builder-pro-js/01-state.js"></script>
    <script src="theme-builder-pro-js/02-editors.js"></script>
    <script src="theme-builder-pro-js/03-preview.js"></script>
    <script src="theme-builder-pro-js/04-api.js"></script>
    <script src="theme-builder-pro-js/05-components.js"></script>
    <script src="theme-builder-pro-js/06-themes.js"></script>
    <script src="theme-builder-pro-js/07-ui.js"></script>
    <script src="theme-builder-pro-js/08-keyboard.js"></script>
    <script src="theme-builder-pro-js/09-history.js"></script>
    <script src="theme-builder-pro-js/10-ai-agent.js"></script>
    <script>
        $(document).ready(function(){
            console.log('Initializing Theme Builder PRO v3.0.0...');
            setTimeout(function(){
                window.ThemeBuilder.initEditors();
                window.ThemeBuilder.ui.init();
                window.ThemeBuilder.keyboard.init();
                window.ThemeBuilder.history.init();
                window.ThemeBuilder.ai.init();
                window.ThemeBuilder.components.loadList();
                window.ThemeBuilder.themes.loadList();
                console.log('✅ Theme Builder PRO ready!');
                window.ThemeBuilder.ui.showNotification('Welcome to Theme Builder PRO!','success');
            },500);
        });
    </script>
</body>
</html>
