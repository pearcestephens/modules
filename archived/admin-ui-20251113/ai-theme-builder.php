<?php
/**
 * AI Agent Theme Builder Demo
 * Shows AI agent integration with live code editing
 *
 * Features:
 * - Real-time AI code editing
 * - Watch mode for live updates
 * - Chat interface with AI
 * - Multi-tab editing (HTML, CSS, JS)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

$pageTitle = 'AI Agent Theme Builder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Monaco Editor -->
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            overflow: hidden;
        }

        /* Top Bar */
        .top-bar {
            height: 60px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 2px solid #8b5cf6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .brand-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .brand-text p {
            font-size: 0.75rem;
            color: #94a3b8;
            margin: 0;
        }

        .top-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-action {
            padding: 0.625rem 1.25rem;
            background: #334155;
            border: none;
            border-radius: 6px;
            color: #f1f5f9;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: #475569;
            transform: translateY(-1px);
        }

        .btn-action.primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .btn-action.primary:hover {
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        /* Main Layout */
        .main-layout {
            display: flex;
            height: calc(100vh - 60px);
        }

        /* Editor Section */
        .editor-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 2px solid #334155;
        }

        /* Tab Bar */
        .tab-bar {
            display: flex;
            background: #1e293b;
            border-bottom: 1px solid #334155;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab:hover {
            color: #f1f5f9;
            background: rgba(139, 92, 246, 0.1);
        }

        .tab.active {
            color: #8b5cf6;
            border-bottom-color: #8b5cf6;
        }

        /* Editor Container */
        .editor-container {
            flex: 1;
            position: relative;
        }

        .monaco-editor-wrapper {
            width: 100%;
            height: 100%;
            display: none;
        }

        .monaco-editor-wrapper.active {
            display: block;
        }

        /* Preview Section */
        .preview-section {
            width: 50%;
            background: #1e293b;
            display: flex;
            flex-direction: column;
        }

        .preview-header {
            padding: 0.75rem 1.5rem;
            background: rgba(139, 92, 246, 0.1);
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-controls {
            display: flex;
            gap: 0.5rem;
        }

        .device-btn {
            width: 32px;
            height: 32px;
            background: transparent;
            border: 1px solid #334155;
            border-radius: 4px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.2s;
        }

        .device-btn:hover {
            background: #334155;
            color: #f1f5f9;
        }

        .device-btn.active {
            background: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
        }

        .preview-frame {
            flex: 1;
            background: white;
            border: none;
            margin: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* AI Instructions Banner */
        .ai-instructions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 420px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            padding: 1rem 2rem;
            z-index: 800;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 -4px 12px rgba(139, 92, 246, 0.3);
        }

        .ai-instructions i {
            font-size: 2rem;
        }

        .ai-instructions-content h4 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
        }

        .ai-instructions-content p {
            font-size: 0.875rem;
            margin: 0;
            opacity: 0.9;
        }

        .btn-close-instructions {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-close-instructions:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="brand-text">
                <h1><?= $pageTitle ?></h1>
                <p>Watch AI edit code in real-time</p>
            </div>
        </div>

        <div class="top-actions">
            <button class="btn-action" onclick="themeSwitcher.togglePanel()">
                <i class="fas fa-palette"></i> Themes
            </button>
            <button class="btn-action primary" onclick="aiAgent.togglePanel()">
                <i class="fas fa-robot"></i> AI Agent
            </button>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Editor Section -->
        <div class="editor-section">
            <!-- Tab Bar -->
            <div class="tab-bar">
                <button class="tab active" data-tab="html" onclick="switchTab('html')">
                    <i class="fab fa-html5"></i> HTML
                </button>
                <button class="tab" data-tab="css" onclick="switchTab('css')">
                    <i class="fab fa-css3-alt"></i> CSS
                </button>
                <button class="tab" data-tab="javascript" onclick="switchTab('javascript')">
                    <i class="fab fa-js"></i> JavaScript
                </button>
            </div>

            <!-- Editor Container -->
            <div class="editor-container">
                <div id="html-editor" class="monaco-editor-wrapper active"></div>
                <div id="css-editor" class="monaco-editor-wrapper"></div>
                <div id="js-editor" class="monaco-editor-wrapper"></div>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="preview-section">
            <div class="preview-header">
                <h3>
                    <i class="fas fa-eye"></i>
                    Live Preview
                </h3>
                <div class="preview-controls">
                    <button class="device-btn active" title="Desktop">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button class="device-btn" title="Tablet">
                        <i class="fas fa-tablet-alt"></i>
                    </button>
                    <button class="device-btn" title="Mobile">
                        <i class="fas fa-mobile-alt"></i>
                    </button>
                </div>
            </div>
            <iframe id="preview-frame" class="preview-frame"></iframe>
        </div>
    </div>

    <!-- AI Instructions Banner -->
    <div class="ai-instructions" id="ai-instructions">
        <i class="fas fa-lightbulb"></i>
        <div class="ai-instructions-content">
            <h4>💡 Try These AI Commands:</h4>
            <p>
                "Add a button component" • "Change the color to blue" • "Review my code" •
                "Make the text bigger" • "Add a navigation bar" • "Optimize the CSS"
            </p>
        </div>
        <button class="btn-close-instructions" onclick="document.getElementById('ai-instructions').style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Monaco Editor -->
    <script>var require = { paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.nls.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.js"></script>

    <!-- Theme Builder JavaScript Modules -->
    <script src="js/theme-builder/01-state.js"></script>
    <script src="js/theme-builder/02-monaco.js"></script>
    <script src="js/theme-builder/03-preview.js"></script>
    <script src="js/theme-builder/04-api.js"></script>
    <script src="js/theme-builder/05-themes.js"></script>
    <script src="js/theme-builder/06-components.js"></script>
    <script src="js/theme-builder/07-ui.js"></script>
    <script src="js/theme-builder/08-keyboard.js"></script>
    <script src="js/theme-builder/09-history.js"></script>
    <script src="js/theme-builder/10-ai-agent.js"></script>
    <script src="_templates/js/11-theme-switcher.js"></script>
    <script src="js/theme-builder/12-ai-agent-integration.js"></script>

    <script>
        // Global editors
        let htmlEditor, cssEditor, jsEditor;

        // Initialize Monaco editors
        require(['vs/editor/editor.main'], function() {
            // HTML Editor
            htmlEditor = monaco.editor.create(document.getElementById('html-editor'), {
                value: `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Theme</title>
</head>
<body>
    <div class="container">
        <h1>Welcome to Theme Builder</h1>
        <p>Tell the AI agent what you want to create!</p>
    </div>
</body>
</html>`,
                language: 'html',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false },
                scrollBeyondLastLine: false
            });

            // CSS Editor
            cssEditor = monaco.editor.create(document.getElementById('css-editor'), {
                value: `/* CSS Variables */
:root {
    --primary: #8b5cf6;
    --secondary: #3b82f6;
    --bg: #0f172a;
    --text: #f1f5f9;
}

body {
    font-family: -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    padding: 2rem;
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

h1 {
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

p {
    font-size: 1.125rem;
    line-height: 1.6;
    color: #94a3b8;
}`,
                language: 'css',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false }
            });

            // JavaScript Editor
            jsEditor = monaco.editor.create(document.getElementById('js-editor'), {
                value: `// JavaScript
console.log('Theme Builder Ready!');

// Add your interactive code here
document.addEventListener('DOMContentLoaded', () => {
    console.log('Page loaded');
});`,
                language: 'javascript',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false }
            });

            // Make editors globally accessible
            window.htmlEditor = htmlEditor;
            window.cssEditor = cssEditor;
            window.jsEditor = jsEditor;

            // Update preview on changes
            htmlEditor.onDidChangeModelContent(() => updatePreview());
            cssEditor.onDidChangeModelContent(() => updatePreview());
            jsEditor.onDidChangeModelContent(() => updatePreview());

            // Initial preview update
            updatePreview();

            console.log('✅ Monaco editors initialized');
        });

        // Switch tabs
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelector(`.tab[data-tab="${tab}"]`).classList.add('active');

            // Update editor visibility
            document.querySelectorAll('.monaco-editor-wrapper').forEach(e => e.classList.remove('active'));
            document.getElementById(`${tab}-editor`).classList.add('active');
        }

        // Update preview
        function updatePreview() {
            const iframe = document.getElementById('preview-frame');
            const html = htmlEditor?.getValue() || '';
            const css = cssEditor?.getValue() || '';
            const js = jsEditor?.getValue() || '';

            const fullHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${css}</style>
                </head>
                <body>
                    ${html}
                    <script>${js}<\/script>
                </body>
                </html>
            `;

            const blob = new Blob([fullHtml], { type: 'text/html' });
            iframe.src = URL.createObjectURL(blob);
        }
    </script>
</body>
</html>
