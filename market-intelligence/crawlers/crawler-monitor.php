<?php
if (!defined('DASHBOARD_ACCESS')) exit('Direct access not allowed');

/**
 * Web Crawler Monitor Page
 * Integrates with SSE server running on localhost:4000
 */
?>

<style>
    .gradient-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .gradient-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .status-card-purple {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .status-card-blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }
    
    .status-card-green {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        color: white;
    }
    
    .status-card-orange {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: white;
    }
    
    .btn-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }
    
    .btn-gradient-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
        color: white;
    }
    
    .btn-gradient-success {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(67, 233, 123, 0.3);
    }
    
    .btn-gradient-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(67, 233, 123, 0.5);
        color: white;
    }
    
    .btn-gradient-danger {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(240, 147, 251, 0.3);
    }
    
    .btn-gradient-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(240, 147, 251, 0.5);
        color: white;
    }
    
    .btn-gradient-info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
    }
    
    .btn-gradient-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(79, 172, 254, 0.5);
        color: white;
    }
    
    .control-panel {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .form-control, .form-select {
        border-radius: 8px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .event-stream {
        height: 500px;
        overflow-y: auto;
        background: linear-gradient(135deg, #1e1e1e 0%, #2d2d2d 100%);
        color: #d4d4d4;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        padding: 15px;
        border-radius: 12px;
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.3);
    }
    
    .chat-messages {
        height: 400px;
        overflow-y: auto;
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
    }
    
    .chat-message-user {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 15px;
        border-radius: 12px;
        margin-bottom: 10px;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        animation: slideInRight 0.3s ease;
    }
    
    .chat-message-ai {
        background: white;
        color: #1e293b;
        padding: 10px 15px;
        border-radius: 12px;
        margin-bottom: 10px;
        border: 2px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        animation: slideInLeft 0.3s ease;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(67, 233, 123, 0.3);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
    }
    
    .card-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
        opacity: 0.9;
    }
    
    .card-value {
        font-size: 32px;
        font-weight: 700;
        margin: 0;
    }
    
    .input-group-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
    }
    
    /* Event Stream Styles */
    .event-stream-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .event-stream-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .event-stream-header h5 {
        margin: 0;
        font-weight: 600;
    }
    
    .event-stream-header h5 i {
        margin-right: 10px;
        animation: pulse 2s infinite;
    }
    
    .event-stream-body {
        padding: 0;
    }
    
    .event-console {
        height: 500px;
        overflow-y: auto;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        color: #1e293b;
        font-family: 'Courier New', Monaco, monospace;
        font-size: 13px;
        padding: 20px;
    }
    
    .event-console::-webkit-scrollbar {
        width: 8px;
    }
    
    .event-console::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    
    .event-console::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 4px;
    }
    
    .event-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
    }
    
    .event-empty i {
        color: #cbd5e1;
    }
    
    .event-item {
        padding: 8px 12px;
        margin-bottom: 6px;
        border-left: 3px solid;
        border-radius: 4px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }
    
    .event-item:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .event-item.event-info {
        border-left-color: #4facfe;
        background: linear-gradient(90deg, rgba(79, 172, 254, 0.1) 0%, white 20%);
    }
    
    .event-item.event-success {
        border-left-color: #38ef7d;
        background: linear-gradient(90deg, rgba(56, 239, 125, 0.1) 0%, white 20%);
    }
    
    .event-item.event-warning {
        border-left-color: #fee140;
        background: linear-gradient(90deg, rgba(254, 225, 64, 0.1) 0%, white 20%);
    }
    
    .event-item.event-error {
        border-left-color: #f45c43;
        background: linear-gradient(90deg, rgba(244, 92, 67, 0.1) 0%, white 20%);
    }
    
    .event-timestamp {
        color: #667eea;
        font-weight: 600;
        margin-right: 10px;
    }
    
    .event-type {
        color: #64748b;
        font-weight: 600;
        margin-right: 10px;
    }
    
    .event-message {
        color: #334155;
    }
    
    /* Chat Card Styles */
    .chat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        height: 100%;
    }
    
    .chat-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 15px 20px;
    }
    
    .chat-header h5 {
        margin: 0;
        font-weight: 600;
    }
    
    .chat-header h5 i {
        margin-right: 10px;
        animation: pulse 2s infinite;
    }
    
    .chat-body {
        display: flex;
        flex-direction: column;
        height: calc(100% - 54px);
    }
    
    .chat-messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        min-height: 400px;
    }
    
    .chat-messages-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .chat-messages-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    
    .chat-messages-container::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        border-radius: 3px;
    }
    
    .chat-welcome {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
    }
    
    .chat-welcome i {
        color: #cbd5e1;
    }
    
    .chat-message {
        margin-bottom: 12px;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .chat-message-user {
        text-align: right;
    }
    
    .chat-message-user .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 16px;
        border-radius: 18px 18px 4px 18px;
        display: inline-block;
        max-width: 80%;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        word-wrap: break-word;
    }
    
    .chat-message-ai {
        text-align: left;
    }
    
    .chat-message-ai .message-bubble {
        background: white;
        color: #1e293b;
        padding: 12px 16px;
        border-radius: 18px 18px 18px 4px;
        display: inline-block;
        max-width: 80%;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 2px solid #4facfe;
        word-wrap: break-word;
    }
    
    .chat-input-section {
        padding: 20px;
        background: white;
        border-top: 2px solid #e2e8f0;
    }
    
    .chat-input-section .form-label {
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
        font-size: 13px;
    }
    
    .chat-input-section .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    .chat-input-section .form-select:focus {
        border-color: #4facfe;
        box-shadow: 0 0 0 4px rgba(79, 172, 254, 0.1);
        background: white;
    }
    
    .chat-input-section .input-group .form-control {
        border: 2px solid #e2e8f0;
        border-right: none;
        border-radius: 8px 0 0 8px;
        padding: 12px 16px;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    .chat-input-section .input-group .form-control:focus {
        border-color: #4facfe;
        box-shadow: 0 0 0 4px rgba(79, 172, 254, 0.1);
        background: white;
    }
    
    .chat-input-section .input-group .btn-gradient-primary {
        border-radius: 0 8px 8px 0;
        padding: 12px 20px;
    }

</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">
                <i class="fas fa-spider"></i> Web Crawler Monitor
                <span class="status-badge" id="connection-status">Connecting...</span>
            </h1>
            <p class="text-muted">Real-time monitoring of the Puppeteer web crawler with AI chat integration</p>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card gradient-card status-card-purple">
                <div class="card-body">
                    <h5 class="card-title">Crawler Status</h5>
                    <h2 class="card-value" id="crawler-status">Idle</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card gradient-card status-card-blue">
                <div class="card-body">
                    <h5 class="card-title">Memory Usage</h5>
                    <h2 class="card-value" id="memory-usage">0 MB</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card gradient-card status-card-green">
                <div class="card-body">
                    <h5 class="card-title">SSE Clients</h5>
                    <h2 class="card-value" id="sse-clients">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card gradient-card status-card-orange">
                <div class="card-body">
                    <h5 class="card-title">Uptime</h5>
                    <h2 class="card-value" id="uptime">0s</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Video Feed -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0" style="color: white; font-weight: 600;">
                            <i class="fas fa-video"></i> Live Browser Feed
                        </h5>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-light" id="btn-video-play" title="Start Live Feed">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button class="btn btn-sm btn-light" id="btn-video-pause" title="Pause Feed" disabled>
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button class="btn btn-sm btn-light" id="btn-video-snapshot" title="Save Screenshot" disabled>
                                <i class="fas fa-camera"></i> Snapshot
                            </button>
                            <button class="btn btn-sm btn-light" id="btn-video-fullscreen" title="Fullscreen">
                                <i class="fas fa-expand"></i> Fullscreen
                            </button>
                        </div>
                    </div>
                    
                    <div id="video-feed-container" style="position: relative; background: #1e293b; border-radius: 12px; overflow: hidden; min-height: 400px;">
                        <div id="video-placeholder" style="display: flex; align-items: center; justify-content: center; height: 400px; color: #94a3b8;">
                            <div class="text-center">
                                <i class="fas fa-video-slash fa-3x mb-3" style="opacity: 0.5;"></i>
                                <p class="mb-0">Click "Start" to begin live browser feed</p>
                                <small style="color: #64748b;">Latest screenshots will appear here automatically</small>
                            </div>
                        </div>
                        <img id="video-feed-image" src="" alt="Live Browser Feed" 
                             style="display: none; width: 100%; height: auto; border-radius: 12px;">
                        <div id="video-feed-overlay" style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px; display: none;">
                            <i class="fas fa-circle text-danger blink"></i> LIVE
                        </div>
                        <div id="video-feed-timestamp" style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 6px 10px; border-radius: 6px; font-size: 11px; display: none;">
                            Last update: <span id="video-timestamp-text">--:--:--</span>
                        </div>
                    </div>
                    
                    <div class="mt-3" style="display: flex; gap: 10px; align-items: center;">
                        <span style="color: white; font-size: 13px;">
                            <i class="fas fa-info-circle"></i> Auto-refresh: <strong><span id="video-refresh-rate">2</span>s</strong>
                        </span>
                        <input type="range" class="form-range" id="video-refresh-slider" 
                               min="1" max="10" value="2" step="1" 
                               style="width: 150px; accent-color: white;">
                        <span style="color: rgba(255,255,255,0.7); font-size: 12px;" id="video-feed-status">
                            Ready
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Control Panel -->
    <!-- Control Panel -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="control-panel">
                <h5 class="mb-3"><i class="fas fa-sliders-h"></i> Crawler Controls</h5>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Target URL</label>
                        <input type="url" class="form-control" id="target-url" 
                               placeholder="https://example.com" 
                               value="https://gpt.ecigdis.co.nz">
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Actions</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-gradient-success" id="btn-start">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button class="btn btn-gradient-danger" id="btn-stop">
                                <i class="fas fa-stop"></i> Stop
                            </button>
                            <button class="btn btn-gradient-info" id="btn-pause">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button class="btn btn-gradient-primary" id="btn-resume">
                                <i class="fas fa-play-circle"></i> Resume
                            </button>
                            <button class="btn btn-gradient-info" id="btn-screenshot">
                                <i class="fas fa-camera"></i> Screenshot
                            </button>
                            <button class="btn btn-gradient-primary" id="btn-navigate">
                                <i class="fas fa-compass"></i> Navigate
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label">Execute JavaScript</label>
                        <div class="input-group">
                            <textarea class="form-control" id="js-code" rows="2"
                                      placeholder="document.querySelector('h1').textContent"></textarea>
                            <button class="btn btn-gradient-primary" id="btn-evaluate">
                                <i class="fas fa-code"></i> Execute
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Event Stream & Chat -->
    <div class="row">
        <!-- Event Stream -->
        <div class="col-md-8">
            <div class="event-stream-card">
                <div class="event-stream-header">
                    <h5>
                        <i class="fas fa-stream"></i> Live Event Stream
                    </h5>
                    <button class="btn btn-gradient-danger btn-sm" id="btn-clear-events">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
                <div class="event-stream-body">
                    <div id="event-stream" class="event-console">
                        <div class="event-empty">
                            <i class="fas fa-rss fa-3x mb-3"></i>
                            <p>Waiting for events...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Chat -->
        <div class="col-md-4">
            <div class="chat-card">
                <div class="chat-header">
                    <h5>
                        <i class="fas fa-robot"></i> AI Chat Assistant
                    </h5>
                </div>
                <div class="chat-body">
                    <div id="chat-messages" class="chat-messages-container">
                        <div class="chat-welcome">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>Ask AI about the crawler...</p>
                        </div>
                    </div>
                    <div class="chat-input-section">
                        <label class="form-label">AI Provider</label>
                        <select class="form-select mb-3" id="ai-provider">
                            <option value="claude">🤖 Claude (Anthropic)</option>
                            <option value="openai">🧠 GPT-4 (OpenAI)</option>
                        </select>
                        <div class="input-group">
                            <input type="text" class="form-control" id="chat-input" 
                                   placeholder="Type your message...">
                            <button class="btn btn-gradient-primary" id="btn-send-chat">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for SSE Connection -->
<script>
(function() {
    'use strict';
    
    // SSE connection to backend proxy
    let eventSource = null;
    let reconnectAttempts = 0;
    let reconnectTimer = null;
    let isReconnecting = false;
    const maxReconnectAttempts = 10;
    const connectionTimeout = 30000; // 30 seconds
    
    // Connect to SSE via PHP proxy
    function connectSSE() {
        // Prevent overlapping reconnection attempts
        if (isReconnecting) {
            console.log('Already reconnecting, skipping...');
            return;
        }
        
        isReconnecting = true;
        console.log('Connecting to SSE...');
        const proxyUrl = 'api/sse-proxy.php?stream=events';
        
        // Close existing connection first
        if (eventSource) {
            try {
                eventSource.close();
            } catch (e) {
                console.warn('Error closing existing EventSource:', e);
            }
            eventSource = null;
        }
        
        try {
            eventSource = new EventSource(proxyUrl);
        } catch (error) {
            console.error('Failed to create EventSource:', error);
            isReconnecting = false;
            scheduleReconnect();
            return;
        }
        
        // Connection timeout
        const timeoutId = setTimeout(() => {
            if (eventSource && eventSource.readyState === EventSource.CONNECTING) {
                console.error('Connection timeout');
                eventSource.close();
                scheduleReconnect();
            }
        }, connectionTimeout);
        
        eventSource.onopen = function() {
            console.log('SSE connected');
            clearTimeout(timeoutId);
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                statusEl.textContent = 'Connected';
                statusEl.className = 'badge bg-success';
            }
            reconnectAttempts = 0;
            isReconnecting = false;
        };
        
        eventSource.onerror = function(e) {
            console.error('SSE error:', e);
            clearTimeout(timeoutId);
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                statusEl.textContent = 'Disconnected';
                statusEl.className = 'badge bg-danger';
            }
            
            // Close connection safely
            if (eventSource && eventSource.readyState !== EventSource.CLOSED) {
                try {
                    eventSource.close();
                } catch (closeError) {
                    console.warn('Error closing EventSource:', closeError);
                }
            }
            
            isReconnecting = false;
            scheduleReconnect();
        };
        
        // Safe JSON parse wrapper
        const safeParseJSON = (data, eventType) => {
            try {
                return JSON.parse(data);
            } catch (error) {
                console.error(`Failed to parse ${eventType} event:`, error);
                return null;
            }
        };
        
        // Handle different event types with error handling
        eventSource.addEventListener('system:stats', function(e) {
            const data = safeParseJSON(e.data, 'system:stats');
            if (data) updateSystemStats(data);
        });
        
        eventSource.addEventListener('crawler:status', function(e) {
            const data = safeParseJSON(e.data, 'crawler:status');
            if (data) updateCrawlerStatus(data);
        });
        
        eventSource.addEventListener('crawler:event', function(e) {
            const data = safeParseJSON(e.data, 'crawler:event');
            if (data) addEventToStream(data);
        });
        
        eventSource.addEventListener('chat:response', function(e) {
            const data = safeParseJSON(e.data, 'chat:response');
            if (data && data.message) {
                addChatMessage('ai', data.message);
            }
        });
        
        // Listen for screenshot events for video feed
        eventSource.addEventListener('crawler:screenshot', function(e) {
            const data = safeParseJSON(e.data, 'crawler:screenshot');
            if (data && videoFeedActive) {
                updateVideoFeed(data);
            }
        });
    }
    
    // Schedule reconnection with backoff
    function scheduleReconnect() {
        // Clear any existing reconnect timer
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        
        if (reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            const delay = Math.min(3000 * Math.pow(2, reconnectAttempts - 1), 60000);
            console.log(`Scheduling reconnect in ${delay}ms (attempt ${reconnectAttempts}/${maxReconnectAttempts})`);
            
            reconnectTimer = setTimeout(() => {
                reconnectTimer = null;
                connectSSE();
            }, delay);
        } else {
            console.error('Max reconnect attempts reached. Click badge to retry.');
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                statusEl.textContent = 'Failed - Click to Retry';
                statusEl.className = 'badge bg-danger';
                statusEl.style.cursor = 'pointer';
                statusEl.onclick = () => {
                    reconnectAttempts = 0;
                    statusEl.onclick = null;
                    statusEl.style.cursor = '';
                    connectSSE();
                };
            }
        }
    }
    
    // Update system stats
    function updateSystemStats(stats) {
        const memMB = Math.round(stats.memory.heapUsed / 1024 / 1024);
        document.getElementById('memory-usage').textContent = memMB + ' MB';
        document.getElementById('sse-clients').textContent = stats.connections.sse;
        document.getElementById('uptime').textContent = formatUptime(stats.uptime);
    }
    
    // Update crawler status
    function updateCrawlerStatus(status) {
        document.getElementById('crawler-status').textContent = status.status || 'Unknown';
    }
    
    // Add event to stream
    function addEventToStream(event) {
        const stream = document.getElementById('event-stream');
        if (!stream) return;
        
        // Remove empty state if present
        const emptyState = stream.querySelector('.event-empty');
        if (emptyState) {
            emptyState.remove();
        }
        
        const time = new Date().toLocaleTimeString();
        const div = document.createElement('div');
        
        // Determine event class based on type
        let eventClass = 'event-info';
        const eventType = (event.type || '').toLowerCase();
        if (eventType.includes('error') || eventType.includes('fail')) {
            eventClass = 'event-error';
        } else if (eventType.includes('success') || eventType.includes('complete')) {
            eventClass = 'event-success';
        } else if (eventType.includes('warn')) {
            eventClass = 'event-warning';
        }
        
        div.className = `event-item ${eventClass}`;
        div.innerHTML = `
            <span class="event-timestamp">[${time}]</span>
            <span class="event-type">${event.type || 'Unknown'}</span>
            <span class="event-message">${JSON.stringify(event.data || {})}</span>
        `;
        
        stream.appendChild(div);
        stream.scrollTop = stream.scrollHeight;
        
        // Limit to last 100 events
        while (stream.children.length > 100) {
            stream.removeChild(stream.firstChild);
        }
    }
    
    // Add chat message
    function addChatMessage(role, message) {
        const chatDiv = document.getElementById('chat-messages');
        
        // Remove welcome state if present
        const welcomeState = chatDiv.querySelector('.chat-welcome');
        if (welcomeState) {
            welcomeState.remove();
        }
        
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-message chat-message-${role}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = message;
        
        msgDiv.appendChild(bubble);
        chatDiv.appendChild(msgDiv);
        chatDiv.scrollTop = chatDiv.scrollHeight;
        
        // Limit to last 100 messages
        while (chatDiv.children.length > 100) {
            chatDiv.removeChild(chatDiv.firstChild);
        }
    }
    
    // Format uptime
    function formatUptime(ms) {
        const seconds = Math.floor(ms / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        
        if (hours > 0) return hours + 'h ' + (minutes % 60) + 'm';
        if (minutes > 0) return minutes + 'm ' + (seconds % 60) + 's';
        return seconds + 's';
    }
    
    // API call helper
    function apiCall(endpoint, method = 'GET', body = null) {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };
        if (body) options.body = JSON.stringify(body);
        
        return fetch('api/sse-proxy.php?endpoint=' + endpoint, options)
            .then(r => r.json())
            .catch(e => console.error('API call failed:', e));
    }
    
    // Event handlers
    document.getElementById('btn-start').addEventListener('click', () => {
        const url = document.getElementById('target-url').value;
        apiCall('crawler/start', 'POST', { url });
    });
    
    document.getElementById('btn-stop').addEventListener('click', () => {
        apiCall('crawler/stop');
    });
    
    document.getElementById('btn-pause').addEventListener('click', () => {
        apiCall('crawler/pause');
    });
    
    document.getElementById('btn-resume').addEventListener('click', () => {
        apiCall('crawler/resume');
    });
    
    document.getElementById('btn-screenshot').addEventListener('click', () => {
        apiCall('crawler/screenshot');
    });
    
    document.getElementById('btn-navigate').addEventListener('click', () => {
        const url = document.getElementById('target-url').value;
        apiCall('crawler/navigate', 'POST', { url });
    });
    
    document.getElementById('btn-evaluate').addEventListener('click', () => {
        const code = document.getElementById('js-code').value;
        apiCall('crawler/evaluate', 'POST', { code });
    });
    
    document.getElementById('btn-clear-events').addEventListener('click', () => {
        const stream = document.getElementById('event-stream');
        stream.innerHTML = `
            <div class="event-empty">
                <i class="fas fa-rss fa-3x mb-3"></i>
                <p>Events cleared. Waiting for new events...</p>
            </div>
        `;
    });
    
    document.getElementById('btn-send-chat').addEventListener('click', () => {
        const message = document.getElementById('chat-input').value;
        const provider = document.getElementById('ai-provider').value;
        
        if (message.trim()) {
            addChatMessage('user', message);
            document.getElementById('chat-input').value = '';
            
            // Call Intelligence Hub AI chat API
            fetch('../api/ai-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, provider })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const aiMessage = data.response.text || data.response.message || JSON.stringify(data.response);
                    addChatMessage('ai', aiMessage);
                } else {
                    addChatMessage('ai', '❌ Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(e => {
                addChatMessage('ai', '❌ Network error: ' + e.message);
            });
        }
    });
    
    document.getElementById('chat-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('btn-send-chat').click();
        }
    });
    
    // ===========================================
    // LIVE VIDEO FEED FUNCTIONALITY
    // ===========================================
    
    // Variables
    let videoFeedActive = false;
    let videoRefreshInterval = null;
    let videoRefreshRate = 2000; // milliseconds
    let lastScreenshotUrl = null;
    let lastScreenshotTimestamp = 0; // Race condition protection
    let videoFetchRetries = 0;
    const maxVideoRetries = 3;
    
    const videoFeedImage = document.getElementById('video-feed-image');
    const videoPlaceholder = document.getElementById('video-placeholder');
    const videoOverlay = document.getElementById('video-feed-overlay');
    const videoTimestamp = document.getElementById('video-feed-timestamp');
    const videoStatus = document.getElementById('video-feed-status');
    const videoTimestampText = document.getElementById('video-timestamp-text');
    
    // Helper functions
    const startVideoFeed = () => {
        videoFeedActive = true;
        videoPlaceholder.style.display = 'none';
        videoFeedImage.style.display = 'block';
        videoOverlay.style.display = 'block';
        videoTimestamp.style.display = 'block';
        videoStatus.textContent = 'Connecting...';
        
        // Request initial screenshot
        apiCall('crawler/screenshot').then(() => {
            videoStatus.textContent = 'Live';
            startVideoRefresh();
        });
    };
    
    const stopVideoFeed = () => {
        videoFeedActive = false;
        if (videoRefreshInterval) {
            clearInterval(videoRefreshInterval);
            videoRefreshInterval = null;
        }
        videoPlaceholder.style.display = 'flex';
        videoFeedImage.style.display = 'none';
        videoOverlay.style.display = 'none';
        videoTimestamp.style.display = 'none';
        videoStatus.textContent = 'Stopped';
        lastScreenshotUrl = null;
    };
    
    const startVideoRefresh = () => {
        videoRefreshInterval = setInterval(() => {
            fetchLatestScreenshot();
        }, videoRefreshRate);
    };
    
    const fetchLatestScreenshot = () => {
        if (!videoFeedActive) return; // Check if still active
        
        // Get latest screenshot from SSE data
        fetch('api/sse-proxy.php?endpoint=screenshots/latest')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success && data.screenshot) {
                    videoFetchRetries = 0; // Reset retry counter
                    updateVideoFeed(data.screenshot);
                } else {
                    throw new Error('No screenshot data');
                }
            })
            .catch(e => {
                console.error('Failed to fetch screenshot:', e);
                videoFetchRetries++;
                
                if (videoFetchRetries >= maxVideoRetries) {
                    videoStatus.textContent = `Error: ${e.message} (${videoFetchRetries} failures)`;
                    // Don't stop interval, keep trying
                } else {
                    videoStatus.textContent = 'Retrying...';
                }
            });
    };
    
    const updateVideoFeed = (screenshotData) => {
        if (!videoFeedActive) return;
        
        // Race condition protection: Check timestamp
        const dataTimestamp = screenshotData.timestamp || Date.now();
        if (dataTimestamp <= lastScreenshotTimestamp) {
            console.log('Skipping older screenshot');
            return; // Skip older screenshots
        }
        lastScreenshotTimestamp = dataTimestamp;
        
        // Clean up old blob URLs to prevent memory leaks
        if (lastScreenshotUrl && lastScreenshotUrl.startsWith('blob:')) {
            try {
                URL.revokeObjectURL(lastScreenshotUrl);
            } catch (e) {
                console.warn('Failed to revoke blob URL:', e);
            }
        }
        
        // Update image with error handling
        if (screenshotData.url || screenshotData.path) {
            const imageUrl = screenshotData.url || screenshotData.path;
            lastScreenshotUrl = imageUrl;
            
            // Add error handler for image load
            videoFeedImage.onerror = () => {
                console.error('Failed to load image:', imageUrl);
                videoStatus.textContent = 'Image load error';
            };
            
            videoFeedImage.onload = () => {
                videoStatus.textContent = 'Live';
            };
            
            videoFeedImage.src = imageUrl + '?t=' + Date.now(); // Cache bust
        } else if (screenshotData.base64) {
            lastScreenshotUrl = 'data:image/png;base64,' + screenshotData.base64;
            
            // Add error handler for base64 images too
            videoFeedImage.onerror = () => {
                console.error('Failed to load base64 image');
                videoStatus.textContent = 'Image decode error';
            };
            
            videoFeedImage.onload = () => {
                videoStatus.textContent = 'Live';
            };
            
            videoFeedImage.src = lastScreenshotUrl;
        } else {
            console.warn('Screenshot data has no url, path, or base64');
            return;
        }
        
        // Update timestamp
        const now = new Date();
        videoTimestampText.textContent = now.toLocaleTimeString();
        videoStatus.textContent = 'Live';
        
        // Add blink animation to LIVE indicator
        videoOverlay.classList.add('blink');
        setTimeout(() => videoOverlay.classList.remove('blink'), 500);
    };
    
    // Event Listeners
    document.getElementById('btn-video-play').addEventListener('click', function() {
        if (!videoFeedActive) {
            startVideoFeed();
            this.innerHTML = '<i class="fas fa-stop"></i> Stop';
            this.classList.replace('btn-light', 'btn-danger');
            document.getElementById('btn-video-pause').disabled = false;
            document.getElementById('btn-video-snapshot').disabled = false;
        } else {
            stopVideoFeed();
            this.innerHTML = '<i class="fas fa-play"></i> Start';
            this.classList.replace('btn-danger', 'btn-light');
            document.getElementById('btn-video-pause').disabled = true;
            document.getElementById('btn-video-snapshot').disabled = true;
        }
    });
    
    document.getElementById('btn-video-pause').addEventListener('click', function() {
        if (videoRefreshInterval) {
            clearInterval(videoRefreshInterval);
            videoRefreshInterval = null;
            this.innerHTML = '<i class="fas fa-play"></i> Resume';
            videoStatus.textContent = 'Paused';
        } else {
            startVideoRefresh();
            this.innerHTML = '<i class="fas fa-pause"></i> Pause';
            videoStatus.textContent = 'Live';
        }
    });
    
    document.getElementById('btn-video-snapshot').addEventListener('click', function() {
        if (lastScreenshotUrl) {
            const link = document.createElement('a');
            link.href = lastScreenshotUrl;
            link.download = `crawler-snapshot-${Date.now()}.png`;
            link.click();
            videoStatus.textContent = 'Snapshot saved!';
            setTimeout(() => videoStatus.textContent = 'Live', 2000);
        }
    });
    
    document.getElementById('btn-video-fullscreen').addEventListener('click', function() {
        const container = document.getElementById('video-feed-container');
        if (!container) return;
        
        // Try all fullscreen API variants
        const requestFullscreen = container.requestFullscreen ||
                                  container.webkitRequestFullscreen ||
                                  container.mozRequestFullScreen ||
                                  container.msRequestFullscreen;
        
        if (requestFullscreen) {
            requestFullscreen.call(container).catch(err => {
                console.error('Fullscreen request failed:', err);
                videoStatus.textContent = 'Fullscreen not available';
                setTimeout(() => videoStatus.textContent = 'Live', 2000);
            });
        } else {
            console.warn('Fullscreen API not supported');
            videoStatus.textContent = 'Fullscreen not supported';
            setTimeout(() => videoStatus.textContent = 'Live', 2000);
        }
    });
    
    document.getElementById('video-refresh-slider').addEventListener('input', function(e) {
        videoRefreshRate = parseInt(e.target.value) * 1000;
        document.getElementById('video-refresh-rate').textContent = e.target.value;
        
        // Restart interval if active
        if (videoRefreshInterval) {
            clearInterval(videoRefreshInterval);
            startVideoRefresh();
        }
    });
    
    // Listen for screenshot events from SSE
    window.addEventListener('crawler-screenshot', (e) => {
        if (videoFeedActive && e.detail) {
            updateVideoFeed(e.detail);
        }
    });
    
    // Add CSS for blink animation
    const blinkStyle = document.createElement('style');
    blinkStyle.textContent = `
        @keyframes blink-animation {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .blink {
            animation: blink-animation 0.5s ease-in-out;
        }
    `;
    document.head.appendChild(blinkStyle);
    
    // ===========================================
    // END LIVE VIDEO FEED FUNCTIONALITY
    // ===========================================
    
    // Window cleanup - comprehensive resource cleanup
    window.addEventListener('beforeunload', () => {
        console.log('Page unloading, cleaning up resources...');
        
        // Close SSE connection
        if (eventSource) {
            try {
                eventSource.close();
            } catch (e) {
                console.warn('Error closing EventSource:', e);
            }
        }
        
        // Clear video feed interval
        if (videoRefreshInterval) {
            clearInterval(videoRefreshInterval);
            videoRefreshInterval = null;
        }
        
        // Clear reconnect timer
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        
        // Revoke blob URLs to prevent memory leaks
        if (lastScreenshotUrl && lastScreenshotUrl.startsWith('blob:')) {
            try {
                URL.revokeObjectURL(lastScreenshotUrl);
            } catch (e) {
                console.warn('Error revoking blob URL:', e);
            }
        }
    });
    
    // Visibility change handler - pause video when tab hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && videoFeedActive && videoRefreshInterval) {
            // Pause updates when tab is hidden to save resources
            clearInterval(videoRefreshInterval);
            videoRefreshInterval = null;
            console.log('Tab hidden, pausing video feed');
        } else if (!document.hidden && videoFeedActive && !videoRefreshInterval) {
            // Resume when tab visible again
            startVideoRefresh();
            console.log('Tab visible, resuming video feed');
        }
    });
    
    // Start connection
    connectSSE();
})();
</script>
