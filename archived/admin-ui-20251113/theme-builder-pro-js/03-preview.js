/**
 * Theme Builder PRO - Live Preview System
 * 1:1 rendering of HTML/CSS/JS in iframe
 * @version 3.0.0
 */

window.ThemeBuilder.refreshPreview = function() {
    const state = window.ThemeBuilder.state;
    const iframe = document.getElementById('preview-frame');

    if (!iframe) {
        console.error('Preview iframe not found');
        return;
    }

    const html = state.editors.html ? state.editors.html.getValue() : state.currentTheme.html;
    const css = state.editors.css ? state.editors.css.getValue() : state.currentTheme.css;
    const js = state.editors.js ? state.editors.js.getValue() : state.currentTheme.js;

    // Build complete HTML document with Bootstrap and custom code
    const fullHTML = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Preview</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>${css}</style>
</head>
<body>
    ${html}

    <!-- jQuery (if needed) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>

    <!-- Custom JS -->
    <script>
        ${js}
    <\/script>
</body>
</html>`;

    // Inject into iframe for 1:1 preview
    iframe.srcdoc = fullHTML;

    console.log('✓ Preview refreshed');
};

// Device preview switching
window.ThemeBuilder.setDeviceMode = function(device) {
    const container = document.getElementById('preview-container');
    const buttons = document.querySelectorAll('.device-btn');

    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-device="${device}"]`).classList.add('active');

    container.className = 'preview-container ' + device;
    window.ThemeBuilder.state.currentDevice = device;

    console.log('✓ Device mode:', device);
};
