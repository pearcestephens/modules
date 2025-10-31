<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a0000 0%, #330000 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 0, 0, 0.3);
            border-radius: 16px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(255, 0, 0, 0.2);
        }
        .error-icon {
            font-size: 64px;
            color: #ff4444;
            margin-bottom: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
        h1 {
            color: #ff4444;
            font-size: 48px;
            margin-bottom: 10px;
            text-align: center;
            font-weight: 700;
        }
        .subtitle {
            color: #ffaaaa;
            font-size: 18px;
            margin-bottom: 30px;
            text-align: center;
        }
        .error-message {
            background: rgba(0, 0, 0, 0.3);
            border-left: 4px solid #ff4444;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            color: #ffdddd;
        }
        .error-id {
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #aaa;
            text-align: center;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #ff4444;
            color: #fff;
        }
        .btn-primary:hover {
            background: #ff6666;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.3);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .debug-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: <?= \CIS\Base\ErrorHandler::isDevMode() ? 'block' : 'none' ?>;
        }
        .debug-title {
            color: #ffaaaa;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        pre {
            background: rgba(0, 0, 0, 0.5);
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.6;
            color: #ddd;
        }
        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #00cc66;
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 204, 102, 0.3);
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .copy-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">💥</div>
        <h1>500</h1>
        <div class="subtitle">Internal Server Error</div>
        
        <div class="error-message">
            <?= htmlspecialchars($e->getMessage()) ?>
        </div>
        
        <div class="error-id">
            Error ID: <?= htmlspecialchars($errorId) ?><br>
            <?= htmlspecialchars($timestamp) ?>
        </div>
        
        <div class="actions">
            <button class="btn btn-primary" onclick="copyDebugInfo()">
                📋 Copy Debug Info
            </button>
            <a href="/" class="btn btn-secondary">
                🏠 Go Home
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← Go Back
            </a>
        </div>
        
        <?php if (\CIS\Base\ErrorHandler::isDevMode()): ?>
        <div class="debug-section">
            <div class="debug-title">🐛 Debug Information (Development Mode)</div>
            <pre><?= htmlspecialchars($e->getFile()) ?>:<?= $e->getLine() ?></pre>
            <pre><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="copy-notification" id="copyNotification">
        ✓ Debug info copied to clipboard!
    </div>
    
    <script>
        const debugInfo = <?= $debugInfo ?>;
        
        function copyDebugInfo() {
            const text = JSON.stringify(debugInfo, null, 2);
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification();
                }).catch(err => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                showCopyNotification();
            } catch (err) {
                alert('Failed to copy. Please copy manually.');
            }
            
            document.body.removeChild(textarea);
        }
        
        function showCopyNotification() {
            const notification = document.getElementById('copyNotification');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
