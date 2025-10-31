/**
 * Theme Builder PRO - Monaco Editor Initialization
 * Sets up all three code editors
 * @version 3.0.0
 */

window.ThemeBuilder.initEditors = function() {
    require.config({
        paths: {
            'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs'
        }
    });

    require(['vs/editor/editor.main'], function() {
        const state = window.ThemeBuilder.state;

        // HTML Editor
        state.editors.html = monaco.editor.create(document.getElementById('html-editor'), {
            value: state.currentTheme.html,
            language: 'html',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            formatOnPaste: true,
            formatOnType: true,
            tabSize: 2
        });

        // CSS Editor
        state.editors.css = monaco.editor.create(document.getElementById('css-editor'), {
            value: state.currentTheme.css,
            language: 'css',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            formatOnPaste: true,
            formatOnType: true,
            tabSize: 2
        });

        // JavaScript Editor
        state.editors.js = monaco.editor.create(document.getElementById('js-editor'), {
            value: state.currentTheme.js,
            language: 'javascript',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            formatOnPaste: true,
            formatOnType: true,
            tabSize: 2
        });

        // Setup auto-refresh on content change
        Object.values(state.editors).forEach(editor => {
            if (editor) {
                editor.onDidChangeModelContent(() => {
                    state.unsavedChanges = true;
                    clearTimeout(state.autoRefreshTimeout);
                    state.autoRefreshTimeout = setTimeout(() => {
                        window.ThemeBuilder.refreshPreview();
                    }, window.ThemeBuilder.config.autoRefreshDelay);
                });
            }
        });

        // Initial preview render
        setTimeout(() => window.ThemeBuilder.refreshPreview(), 500);

        console.log('✓ Monaco editors initialized');
    });
};
