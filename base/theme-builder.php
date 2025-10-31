<?php
/**
 * CIS Theme Builder - Visual Color Customization Tool
 * 
 * Allows visual selection of all CIS theme colors and generates
 * CSS variables automatically for download/implementation
 * 
 * @package CIS\ThemeBuilder
 * @version 1.0.0
 */

// No bootstrap needed - standalone tool
$pageTitle = "CIS Theme Builder - Customize Colors";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/assets/css/cis-core.css">
    
    <!-- Theme Builder CSS -->
    <style>
                :root {
            /* Current CIS Colors - Matching your live system */
            --cis-primary: #007bff;
            --cis-primary-hover: #0056b3;
            --cis-primary-light: #e3f2fd;
            --cis-secondary: #6c757d;
            --cis-success: #28a745;
            --cis-danger: #dc3545;
            --cis-warning: #ffc107;
            --cis-info: #17a2b8;
            --cis-dark: #343a40;
            --cis-light: #f8f9fa;
            --cis-sidebar-bg: #495057;
            --cis-header-bg: #ffffff;
            --cis-card-bg: #ffffff;
            --cis-border-color: #dee2e6;
            --cis-sidebar-text: #ffffff;
            --cis-sidebar-active: #007bff;
        }
        
        .theme-builder {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: 100vh;
            gap: 0;
        }
        
        .controls-panel {
            background: var(--cis-light);
            border-right: 2px solid var(--cis-border-color);
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        .preview-panel {
            background: var(--cis-gray-100);
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        .color-group {
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .color-group h3 {
            margin: 0 0 1rem 0;
            color: var(--cis-dark);
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .color-input {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .color-input label {
            flex: 1;
            font-size: 0.9rem;
            color: var(--cis-gray-700);
        }
        
        .color-picker {
            width: 50px;
            height: 35px;
            border: 1px solid var(--cis-border-color);
            border-radius: 4px;
            cursor: pointer;
        }
        
        .hex-input {
            width: 80px;
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--cis-border-color);
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        .preview-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .preview-section h3 {
            margin: 0 0 1rem 0;
            color: var(--cis-dark);
        }
        
        .mock-header {
            background: var(--cis-header-bg);
            border: 1px solid var(--cis-border-color);
            padding: 1rem 1.5rem;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header-brand img {
            width: 28px;
            height: 28px;
        }
        
        .header-title {
            font-weight: 600;
            color: var(--cis-dark);
            font-size: 1.1rem;
        }
        
        .mock-sidebar {
            background: var(--cis-sidebar-bg);
            color: var(--cis-sidebar-text, white);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo img {
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }
        
        .sidebar-brand {
            font-weight: bold;
            font-size: 0.95rem;
            color: white;
        }
        
        .mock-nav-item {
            padding: 0.6rem 1rem;
            border-radius: 4px;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .mock-nav-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .mock-nav-item.active {
            background: var(--cis-sidebar-active);
            color: white;
            font-weight: 500;
        }
        
        .nav-section-header {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin: 1.5rem 0 0.75rem 0;
            letter-spacing: 0.5px;
        }
        
        .buttons-demo {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .cards-demo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .demo-card {
            background: var(--cis-card-bg);
            border: 1px solid var(--cis-border-color);
            border-radius: 6px;
            padding: 1rem;
        }
        
        .export-section {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 2px solid var(--cis-border-color);
            padding: 1rem;
            margin: -1.5rem -1.5rem 0 -1.5rem;
        }
        
        .css-output {
            background: #f8f9fa;
            border: 1px solid var(--cis-border-color);
            border-radius: 4px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre;
            margin: 1rem 0;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        
        .alert-info {
            background: var(--cis-info-light, #d1ecf1);
            border-color: var(--cis-info);
            color: #0c5460;
        }
        
        @media (max-width: 768px) {
            .theme-builder {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }
            
            .controls-panel {
                max-height: 40vh;
            }
        }
    </style>
</head>
<body>
    <div class="theme-builder">
        <!-- Controls Panel -->
        <div class="controls-panel">
            <h2>🎨 CIS Theme Builder</h2>
            <p class="text-muted">Customize your CIS colors and generate CSS instantly.</p>
            
            <!-- Preset Themes -->
            <div class="color-group">
                <h3>🎯 Quick Presets</h3>
                <p style="font-size: 0.85rem; color: var(--cis-secondary); margin-bottom: 0.75rem;">Start with a preset, then customize:</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <button class="btn btn-sm" onclick="loadPreset('current-cis')" style="background: #4472C4; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; font-size: 0.8rem;">Current CIS</button>
                    <button class="btn btn-sm" onclick="loadPreset('fresh-modern')" style="background: #2563eb; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; font-size: 0.8rem;">Fresh Modern</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <button class="btn btn-sm" onclick="loadPreset('cis-refined')" style="background: #3b82f6; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; font-size: 0.8rem;">CIS Refined</button>
                    <button class="btn btn-sm" onclick="loadPreset('minimal-clean')" style="background: #000000; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; font-size: 0.8rem;">Minimal Clean</button>
                </div>
                <button class="btn btn-sm" onclick="loadPreset('dark-professional')" style="background: #1f2937; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; font-size: 0.8rem; width: 100%;">Dark Professional</button>
            </div>
            
            <!-- Primary Colors -->
            <div class="color-group">
                <h3>🔵 Primary Colors</h3>
                <div class="color-input">
                    <label>Primary Color:</label>
                    <input type="color" class="color-picker" id="primary" value="#4472C4" data-var="--cis-primary">
                    <input type="text" class="hex-input" id="primary-hex" value="#4472C4">
                </div>
                <div class="color-input">
                    <label>Primary Hover:</label>
                    <input type="color" class="color-picker" id="primary-hover" value="#365899" data-var="--cis-primary-hover">
                    <input type="text" class="hex-input" id="primary-hover-hex" value="#365899">
                </div>
                <div class="color-input">
                    <label>Primary Light:</label>
                    <input type="color" class="color-picker" id="primary-light" value="#E8F1FF" data-var="--cis-primary-light">
                    <input type="text" class="hex-input" id="primary-light-hex" value="#E8F1FF">
                </div>
            </div>

            <!-- Status Colors -->
            <div class="color-group">
                <h3>🚦 Status Colors</h3>
                <div class="color-input">
                    <label>Success:</label>
                    <input type="color" class="color-picker" id="success" value="#28a745" data-var="--cis-success">
                    <input type="text" class="hex-input" id="success-hex" value="#28a745">
                </div>
                <div class="color-input">
                    <label>Danger:</label>
                    <input type="color" class="color-picker" id="danger" value="#dc3545" data-var="--cis-danger">
                    <input type="text" class="hex-input" id="danger-hex" value="#dc3545">
                </div>
                <div class="color-input">
                    <label>Warning:</label>
                    <input type="color" class="color-picker" id="warning" value="#ffc107" data-var="--cis-warning">
                    <input type="text" class="hex-input" id="warning-hex" value="#ffc107">
                </div>
                <div class="color-input">
                    <label>Info:</label>
                    <input type="color" class="color-picker" id="info" value="#17a2b8" data-var="--cis-info">
                    <input type="text" class="hex-input" id="info-hex" value="#17a2b8">
                </div>
            </div>

            <!-- Layout Colors -->
            <div class="color-group">
                <h3>🏗️ Layout Colors</h3>
                <div class="color-input">
                    <label>Sidebar Background:</label>
                    <input type="color" class="color-picker" id="sidebar-bg" value="#f8f9fa" data-var="--cis-sidebar-bg">
                    <input type="text" class="hex-input" id="sidebar-bg-hex" value="#f8f9fa">
                </div>
                <div class="color-input">
                    <label>Header Background:</label>
                    <input type="color" class="color-picker" id="header-bg" value="#4472C4" data-var="--cis-header-bg">
                    <input type="text" class="hex-input" id="header-bg-hex" value="#4472C4">
                </div>
                <div class="color-input">
                    <label>Card Background:</label>
                    <input type="color" class="color-picker" id="card-bg" value="#ffffff" data-var="--cis-card-bg">
                    <input type="text" class="hex-input" id="card-bg-hex" value="#ffffff">
                </div>
                <div class="color-input">
                    <label>Border Color:</label>
                    <input type="color" class="color-picker" id="border-color" value="#e3e6f0" data-var="--cis-border-color">
                    <input type="text" class="hex-input" id="border-color-hex" value="#e3e6f0">
                </div>
            </div>

            <!-- Neutral Colors -->
            <div class="color-group">
                <h3>⚫ Neutral Colors</h3>
                <div class="color-input">
                    <label>Dark Text:</label>
                    <input type="color" class="color-picker" id="dark" value="#343a40" data-var="--cis-dark">
                    <input type="text" class="hex-input" id="dark-hex" value="#343a40">
                </div>
                <div class="color-input">
                    <label>Light Background:</label>
                    <input type="color" class="color-picker" id="light" value="#f8f9fa" data-var="--cis-light">
                    <input type="text" class="hex-input" id="light-hex" value="#f8f9fa">
                </div>
                <div class="color-input">
                    <label>Secondary:</label>
                    <input type="color" class="color-picker" id="secondary" value="#6c757d" data-var="--cis-secondary">
                    <input type="text" class="hex-input" id="secondary-hex" value="#6c757d">
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <h3>📤 Export Theme</h3>
                <button class="btn btn-primary" onclick="generateCSS()">Generate CSS</button>
                <button class="btn btn-success" onclick="downloadCSS()">Download CSS File</button>
                <button class="btn btn-secondary" onclick="copyCSS()">Copy to Clipboard</button>
                
                <div class="css-output" id="css-output">
                    /* Your generated CSS will appear here */
                </div>
            </div>
        </div>

        <!-- Preview Panel -->
        <div class="preview-panel">
            <div class="alert alert-info">
                <strong>Live Preview:</strong> Changes are applied instantly. Adjust colors on the left to see real-time updates.
            </div>

            <!-- Header Preview -->
            <div class="preview-section">
                <h3>Header Preview</h3>
                <div class="mock-header">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="The Vape Shed" style="height: 30px;">
                        <strong>CIS Dashboard</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <!-- Notifications Bell -->
                        <div style="position: relative; cursor: pointer;">
                            <span style="font-size: 1.2rem;">🔔</span>
                            <span style="position: absolute; top: -5px; right: -5px; background: var(--cis-danger); color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">3</span>
                        </div>
                        <!-- Messages Icon -->
                        <div style="position: relative; cursor: pointer;">
                            <span style="font-size: 1.2rem;">💬</span>
                            <span style="position: absolute; top: -5px; right: -5px; background: var(--cis-primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">5</span>
                        </div>
                        <button class="btn btn-sm" style="background: var(--cis-primary); color: white; border: none; padding: 0.25rem 0.75rem; border-radius: 4px;">
                            Admin User ▼
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar Preview -->
            <div class="preview-section">
                <h3>Sidebar Preview</h3>
                <div class="mock-sidebar">
                    <div style="font-weight: bold; margin-bottom: 1rem; color: white;">🏠 CIS Navigation</div>
                    <div class="mock-nav-item active">📊 Dashboard</div>
                    <div class="mock-nav-item">📦 Inventory</div>
                    <div class="mock-nav-item">🛒 Purchase Orders</div>
                    <div class="mock-nav-item">🚚 Suppliers</div>
                    <div class="mock-nav-item">📈 Reports</div>
                </div>
            </div>

            <!-- Buttons Preview -->
            <div class="preview-section">
                <h3>Buttons Preview</h3>
                <div class="buttons-demo">
                    <button class="btn" style="background: var(--cis-primary); color: white; border: 1px solid var(--cis-primary); padding: 0.5rem 1rem; border-radius: 4px;">Primary</button>
                    <button class="btn" style="background: var(--cis-success); color: white; border: 1px solid var(--cis-success); padding: 0.5rem 1rem; border-radius: 4px;">Success</button>
                    <button class="btn" style="background: var(--cis-danger); color: white; border: 1px solid var(--cis-danger); padding: 0.5rem 1rem; border-radius: 4px;">Danger</button>
                    <button class="btn" style="background: var(--cis-warning); color: black; border: 1px solid var(--cis-warning); padding: 0.5rem 1rem; border-radius: 4px;">Warning</button>
                    <button class="btn" style="background: var(--cis-info); color: white; border: 1px solid var(--cis-info); padding: 0.5rem 1rem; border-radius: 4px;">Info</button>
                    <button class="btn" style="background: transparent; color: var(--cis-primary); border: 1px solid var(--cis-primary); padding: 0.5rem 1rem; border-radius: 4px;">Outline</button>
                </div>
            </div>

            <!-- Cards Preview -->
            <div class="preview-section">
                <h3>Cards & Components Preview</h3>
                <div class="cards-demo">
                    <div class="demo-card">
                        <h4 style="color: var(--cis-primary); margin: 0 0 0.5rem 0;">Sales Today</h4>
                        <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: var(--cis-dark);">$12,345</p>
                        <p style="color: var(--cis-success); margin: 0.25rem 0 0 0; font-size: 0.9rem;">↗ +15% from yesterday</p>
                    </div>
                    <div class="demo-card">
                        <h4 style="color: var(--cis-dark); margin: 0 0 0.5rem 0;">Inventory Status</h4>
                        <p style="margin: 0.5rem 0; color: var(--cis-dark);">• 1,234 Products in stock</p>
                        <p style="margin: 0.5rem 0; color: var(--cis-warning);">• 45 Low stock warnings</p>
                        <p style="margin: 0.5rem 0; color: var(--cis-danger);">• 3 Out of stock items</p>
                    </div>
                    <div class="demo-card">
                        <h4 style="color: var(--cis-info); margin: 0 0 0.5rem 0;">Recent Activity</h4>
                        <div style="padding: 0.5rem; background: var(--cis-light); border-radius: 4px; margin: 0.5rem 0; border-left: 3px solid var(--cis-primary);">
                            New purchase order created
                        </div>
                        <div style="padding: 0.5rem; background: var(--cis-light); border-radius: 4px; margin: 0.5rem 0; border-left: 3px solid var(--cis-success);">
                            Transfer completed successfully
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forms Preview -->
            <div class="preview-section">
                <h3>Forms Preview</h3>
                <div style="max-width: 400px;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--cis-dark); font-weight: 500;">Product Name</label>
                        <input type="text" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--cis-border-color); border-radius: 4px;" placeholder="Enter product name">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--cis-dark); font-weight: 500;">Category</label>
                        <select style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--cis-border-color); border-radius: 4px;">
                            <option>Select category...</option>
                            <option>Devices</option>
                            <option>E-liquids</option>
                            <option>Accessories</option>
                        </select>
                    </div>
                    <button style="background: var(--cis-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer;">Save Product</button>
                </div>
            </div>

            <!-- Notifications & Messages Preview -->
            <div class="preview-section">
                <h3>Notifications & Messages System</h3>
                
                <!-- Facebook-Style Chat Bar -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--cis-dark); margin: 0 0 0.75rem 0; font-size: 1rem;">Facebook-Style Bottom Chat Bar</h4>
                    <div style="position: relative; background: var(--cis-light); border: 1px solid var(--cis-border-color); border-radius: 6px; padding: 1rem; min-height: 400px;">
                        <!-- Main content area placeholder -->
                        <div style="text-align: center; padding: 2rem; color: var(--cis-secondary);">
                            <p>Main content area - chat opens from bottom</p>
                        </div>
                        
                        <!-- Bottom Chat Bar (Fixed to bottom) -->
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: white; border-top: 2px solid var(--cis-border-color); display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; height: 50px;">
                            <!-- Online Users List -->
                            <div style="display: flex; gap: 0.5rem; flex: 1; overflow-x: auto;">
                                <!-- User 1 - Active Chat -->
                                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: var(--cis-primary-light); border-radius: 20px; cursor: pointer; white-space: nowrap;">
                                    <div style="position: relative;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--cis-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">JD</div>
                                        <span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: var(--cis-success); border: 2px solid white; border-radius: 50%;"></span>
                                    </div>
                                    <span style="font-size: 0.85rem; font-weight: 500;">John Doe</span>
                                    <span style="background: var(--cis-danger); color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">2</span>
                                </div>
                                
                                <!-- User 2 - Minimized -->
                                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: var(--cis-light); border-radius: 20px; cursor: pointer; white-space: nowrap;">
                                    <div style="position: relative;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--cis-success); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">SM</div>
                                        <span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: var(--cis-success); border: 2px solid white; border-radius: 50%;"></span>
                                    </div>
                                    <span style="font-size: 0.85rem;">Sarah M.</span>
                                </div>
                                
                                <!-- User 3 - Away -->
                                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: var(--cis-light); border-radius: 20px; cursor: pointer; white-space: nowrap;">
                                    <div style="position: relative;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--cis-info); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">PT</div>
                                        <span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: var(--cis-warning); border: 2px solid white; border-radius: 50%;"></span>
                                    </div>
                                    <span style="font-size: 0.85rem;">Paul T.</span>
                                </div>
                            </div>
                            
                            <!-- Chat Toggle Button -->
                            <button style="background: var(--cis-primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">
                                💬 Chat (3 online)
                            </button>
                        </div>
                        
                        <!-- Open Chat Window (positioned above bar) -->
                        <div style="position: absolute; bottom: 50px; right: 20px; width: 300px; background: white; border: 1px solid var(--cis-border-color); border-radius: 8px 8px 0 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">
                            <!-- Chat Header -->
                            <div style="background: var(--cis-primary); color: white; padding: 0.75rem 1rem; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">JD</div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem;">John Doe</div>
                                        <div style="font-size: 0.75rem; opacity: 0.9;">● Active now</div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button style="background: none; border: none; color: white; cursor: pointer; font-size: 1rem;">−</button>
                                    <button style="background: none; border: none; color: white; cursor: pointer; font-size: 1rem;">×</button>
                                </div>
                            </div>
                            
                            <!-- Chat Messages -->
                            <div style="height: 300px; overflow-y: auto; padding: 1rem; background: var(--cis-light);">
                                <!-- Message from other user -->
                                <div style="margin-bottom: 1rem;">
                                    <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--cis-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.7rem; flex-shrink: 0;">JD</div>
                                        <div>
                                            <div style="background: white; padding: 0.5rem 0.75rem; border-radius: 12px; max-width: 200px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                                <div style="font-size: 0.85rem;">Hey, can you check that transfer?</div>
                                            </div>
                                            <div style="font-size: 0.7rem; color: var(--cis-secondary); margin-top: 0.25rem;">2:34 PM</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Message from current user -->
                                <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
                                    <div>
                                        <div style="background: var(--cis-primary); color: white; padding: 0.5rem 0.75rem; border-radius: 12px; max-width: 200px;">
                                            <div style="font-size: 0.85rem;">Sure, looking at it now</div>
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--cis-secondary); margin-top: 0.25rem; text-align: right;">2:35 PM</div>
                                    </div>
                                </div>
                                
                                <!-- Another message from other user -->
                                <div style="margin-bottom: 1rem;">
                                    <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--cis-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.7rem; flex-shrink: 0;">JD</div>
                                        <div>
                                            <div style="background: white; padding: 0.5rem 0.75rem; border-radius: 12px; max-width: 200px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                                <div style="font-size: 0.85rem;">Thanks! 👍</div>
                                            </div>
                                            <div style="font-size: 0.7rem; color: var(--cis-secondary); margin-top: 0.25rem;">2:35 PM</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Chat Input -->
                            <div style="border-top: 1px solid var(--cis-border-color); padding: 0.75rem;">
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" placeholder="Type a message..." style="flex: 1; padding: 0.5rem 0.75rem; border: 1px solid var(--cis-border-color); border-radius: 20px; font-size: 0.85rem;">
                                    <button style="background: var(--cis-primary); color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                        ➤
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Dropdown -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--cis-dark); margin: 0 0 0.75rem 0; font-size: 1rem;">Notifications Panel</h4>
                    <div style="background: var(--cis-card-bg); border: 1px solid var(--cis-border-color); border-radius: 6px; max-width: 350px;">
                        <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); display: flex; justify-content: space-between; align-items: center;">
                            <strong style="color: var(--cis-dark);">Notifications</strong>
                            <span style="color: var(--cis-primary); font-size: 0.85rem; cursor: pointer;">Mark all read</span>
                        </div>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer; background: var(--cis-primary-light);">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="color: var(--cis-primary); font-size: 1.2rem;">📦</div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500; color: var(--cis-dark); font-size: 0.9rem;">New transfer received</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.8rem;">Hamilton sent 45 items</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.75rem; margin-top: 0.25rem;">2 minutes ago</div>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer;">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="color: var(--cis-success); font-size: 1.2rem;">✅</div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500; color: var(--cis-dark); font-size: 0.9rem;">Purchase order approved</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.8rem;">PO #12345 approved by manager</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.75rem; margin-top: 0.25rem;">15 minutes ago</div>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer;">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="color: var(--cis-warning); font-size: 1.2rem;">⚠️</div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500; color: var(--cis-dark); font-size: 0.9rem;">Low stock alert</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.8rem;">15 products below minimum threshold</div>
                                        <div style="color: var(--cis-secondary); font-size: 0.75rem; margin-top: 0.25rem;">1 hour ago</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 0.5rem 1rem; text-align: center; border-top: 1px solid var(--cis-border-color);">
                            <a href="#" style="color: var(--cis-primary); text-decoration: none; font-size: 0.85rem;">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- Messages Dropdown -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--cis-dark); margin: 0 0 0.75rem 0; font-size: 1rem;">Messages Panel</h4>
                    <div style="background: var(--cis-card-bg); border: 1px solid var(--cis-border-color); border-radius: 6px; max-width: 350px;">
                        <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); display: flex; justify-content: space-between; align-items: center;">
                            <strong style="color: var(--cis-dark);">Messages</strong>
                            <span style="color: var(--cis-primary); font-size: 0.85rem; cursor: pointer;">New message</span>
                        </div>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer; background: var(--cis-primary-light);">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--cis-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">JD</div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="font-weight: 600; color: var(--cis-dark); font-size: 0.9rem;">John Doe</div>
                                            <div style="color: var(--cis-secondary); font-size: 0.75rem;">5m</div>
                                        </div>
                                        <div style="color: var(--cis-dark); font-size: 0.85rem; margin-top: 0.25rem;">Can you check the transfer I just sent?</div>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer;">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--cis-success); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">SM</div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="font-weight: 500; color: var(--cis-dark); font-size: 0.9rem;">Sarah Manager</div>
                                            <div style="color: var(--cis-secondary); font-size: 0.75rem;">1h</div>
                                        </div>
                                        <div style="color: var(--cis-secondary); font-size: 0.85rem; margin-top: 0.25rem;">Great work on the stocktake today!</div>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--cis-border-color); cursor: pointer;">
                                <div style="display: flex; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--cis-info); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">IT</div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="font-weight: 500; color: var(--cis-dark); font-size: 0.9rem;">IT Support</div>
                                            <div style="color: var(--cis-secondary); font-size: 0.75rem;">2h</div>
                                        </div>
                                        <div style="color: var(--cis-secondary); font-size: 0.85rem; margin-top: 0.25rem;">Your ticket has been resolved</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 0.5rem 1rem; text-align: center; border-top: 1px solid var(--cis-border-color);">
                            <a href="#" style="color: var(--cis-primary); text-decoration: none; font-size: 0.85rem;">View all messages</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Preview -->
            <div class="preview-section">
                <h3>Footer Preview</h3>
                <div style="background: var(--cis-sidebar-bg); color: white; padding: 2rem 1.5rem; border-radius: 6px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 1.5rem;">
                        <div>
                            <h4 style="margin: 0 0 0.75rem 0; font-size: 1rem;">Quick Links</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Dashboard</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Inventory</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Reports</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Settings</a>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 0.75rem 0; font-size: 1rem;">Support</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Help Center</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Documentation</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Contact IT</a>
                                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem;">Report Issue</a>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 0.75rem 0; font-size: 1rem;">System Info</h4>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.7);">
                                <div style="margin-bottom: 0.25rem;">Version: 2.0.0</div>
                                <div style="margin-bottom: 0.25rem;">Last Updated: Oct 28, 2025</div>
                                <div style="margin-bottom: 0.25rem;">Server Status: <span style="color: var(--cis-success);">●</span> Online</div>
                            </div>
                        </div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; text-align: center; color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                        © 2025 Ecigdis Limited - The Vape Shed. All rights reserved. | CIS Central Information System
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Builder JavaScript
        let currentTheme = {};

        // Initialize theme builder
        document.addEventListener('DOMContentLoaded', function() {
            // Set up color picker event listeners
            document.querySelectorAll('.color-picker').forEach(picker => {
                picker.addEventListener('input', updateColor);
                picker.addEventListener('change', updateColor);
                
                // Initialize with default values
                updateColor({ target: picker });
            });

            // Set up hex input event listeners
            document.querySelectorAll('.hex-input').forEach(input => {
                input.addEventListener('input', updateFromHex);
                input.addEventListener('change', updateFromHex);
            });

            // Generate initial CSS
            generateCSS();
        });

        function updateColor(event) {
            const picker = event.target;
            const color = picker.value;
            const varName = picker.dataset.var;
            const hexInput = document.getElementById(picker.id + '-hex');
            
            // Update hex input
            if (hexInput) {
                hexInput.value = color;
            }
            
            // Update CSS variable
            document.documentElement.style.setProperty(varName, color);
            
            // Store in current theme
            currentTheme[varName] = color;
            
            // Auto-generate hover colors for primary
            if (picker.id === 'primary') {
                const hoverColor = darkenColor(color, 20);
                const lightColor = lightenColor(color, 40);
                
                document.getElementById('primary-hover').value = hoverColor;
                document.getElementById('primary-hover-hex').value = hoverColor;
                document.getElementById('primary-light').value = lightColor;
                document.getElementById('primary-light-hex').value = lightColor;
                
                document.documentElement.style.setProperty('--cis-primary-hover', hoverColor);
                document.documentElement.style.setProperty('--cis-primary-light', lightColor);
                
                currentTheme['--cis-primary-hover'] = hoverColor;
                currentTheme['--cis-primary-light'] = lightColor;
            }
        }

        function updateFromHex(event) {
            const input = event.target;
            const color = input.value;
            const pickerId = input.id.replace('-hex', '');
            const picker = document.getElementById(pickerId);
            
            if (picker && isValidHex(color)) {
                picker.value = color;
                updateColor({ target: picker });
            }
        }

        function isValidHex(hex) {
            return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(hex);
        }

        function darkenColor(hex, percent) {
            const num = parseInt(hex.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) - amt;
            const G = (num >> 8 & 0x00FF) - amt;
            const B = (num & 0x0000FF) - amt;
            return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        }

        function lightenColor(hex, percent) {
            const num = parseInt(hex.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const G = (num >> 8 & 0x00FF) + amt;
            const B = (num & 0x0000FF) + amt;
            return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        }

        function generateCSS() {
            let css = `/**
 * CIS Custom Theme - Generated by Theme Builder
 * Generated on: ${new Date().toLocaleString()}
 * 
 * Instructions:
 * 1. Save this as /assets/css/cis-custom-theme.css
 * 2. Include after cis-core.css in your templates:
 *    <link rel="stylesheet" href="/assets/css/cis-core.css">
 *    <link rel="stylesheet" href="/assets/css/cis-custom-theme.css">
 */

:root {`;

            // Add all theme variables
            Object.keys(currentTheme).sort().forEach(varName => {
                css += `\n    ${varName}: ${currentTheme[varName]};`;
            });

            css += `
}

/* Auto-generated hover states */
.btn-primary:hover {
    background-color: var(--cis-primary-hover) !important;
    border-color: var(--cis-primary-hover) !important;
}

.btn-success:hover {
    filter: brightness(0.9);
}

.btn-danger:hover {
    filter: brightness(0.9);
}

.btn-warning:hover {
    filter: brightness(0.9);
}

.btn-info:hover {
    filter: brightness(0.9);
}

/* Sidebar navigation active state */
.nav-link.active,
.mock-nav-item.active {
    background-color: var(--cis-primary) !important;
    color: white !important;
}

/* Focus states */
.form-control:focus {
    border-color: var(--cis-primary);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
}`;

            document.getElementById('css-output').textContent = css;
            return css;
        }

        function downloadCSS() {
            const css = generateCSS();
            const blob = new Blob([css], { type: 'text/css' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'cis-custom-theme.css';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            alert('✅ Theme downloaded! Upload to /assets/css/ and include in your templates.');
        }

        function copyCSS() {
            const css = generateCSS();
            navigator.clipboard.writeText(css).then(() => {
                alert('✅ CSS copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = css;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ CSS copied to clipboard!');
            });
        }

        // Preset themes based on CIS login screen analysis
        function loadPreset(presetName) {
            const presets = {
                'current-cis': {
                    '--cis-primary': '#4472C4',        // Current CIS blue from login
                    '--cis-primary-hover': '#365899',   // Darker blue for hover
                    '--cis-primary-light': '#E8F1FF',   // Light blue backgrounds
                    '--cis-success': '#28a745',
                    '--cis-danger': '#dc3545',
                    '--cis-warning': '#ffc107',
                    '--cis-info': '#17a2b8',
                    '--cis-sidebar-bg': '#f8f9fa',      // Light sidebar like login
                    '--cis-header-bg': '#4472C4',       // Blue header from login
                    '--cis-card-bg': '#ffffff',
                    '--cis-border-color': '#e3e6f0',    // Softer borders
                    '--cis-dark': '#2c3e50',
                    '--cis-light': '#f8f9fa',
                    '--cis-secondary': '#6c757d'
                },
                'fresh-modern': {
                    '--cis-primary': '#2563eb',         // Modern blue (Tailwind blue-600)
                    '--cis-primary-hover': '#1d4ed8',   // blue-700
                    '--cis-primary-light': '#dbeafe',   // blue-100
                    '--cis-success': '#059669',         // Modern green
                    '--cis-danger': '#dc2626',          // Modern red
                    '--cis-warning': '#d97706',         // Modern amber
                    '--cis-info': '#0891b2',            // Modern cyan
                    '--cis-sidebar-bg': '#f9fafb',      // gray-50
                    '--cis-header-bg': '#ffffff',       // Clean white header
                    '--cis-card-bg': '#ffffff',
                    '--cis-border-color': '#e5e7eb',    // gray-200
                    '--cis-dark': '#111827',            // gray-900
                    '--cis-light': '#f9fafb',           // gray-50
                    '--cis-secondary': '#6b7280'        // gray-500
                },
                'cis-refined': {
                    '--cis-primary': '#3b82f6',         // Refined blue (less intense)
                    '--cis-primary-hover': '#2563eb',
                    '--cis-primary-light': '#eff6ff',
                    '--cis-success': '#10b981',         // Refined green
                    '--cis-danger': '#ef4444',          // Refined red
                    '--cis-warning': '#f59e0b',         // Refined amber
                    '--cis-info': '#06b6d4',            // Refined cyan
                    '--cis-sidebar-bg': '#fefefe',      // Almost white sidebar
                    '--cis-header-bg': '#3b82f6',       // Blue header but softer
                    '--cis-card-bg': '#ffffff',
                    '--cis-border-color': '#f3f4f6',    // Very light borders
                    '--cis-dark': '#1f2937',
                    '--cis-light': '#fefefe',
                    '--cis-secondary': '#64748b'
                },
                'dark-professional': {
                    '--cis-primary': '#60a5fa',         // Light blue for dark theme
                    '--cis-primary-hover': '#3b82f6',
                    '--cis-primary-light': '#1e3a8a',
                    '--cis-success': '#34d399',
                    '--cis-danger': '#f87171',
                    '--cis-warning': '#fbbf24',
                    '--cis-info': '#22d3ee',
                    '--cis-sidebar-bg': '#1f2937',      // Dark gray
                    '--cis-header-bg': '#111827',       // Very dark header
                    '--cis-card-bg': '#374151',         // Dark cards
                    '--cis-border-color': '#4b5563',    // Dark borders
                    '--cis-dark': '#f9fafb',            // Light text on dark
                    '--cis-light': '#1f2937',
                    '--cis-secondary': '#9ca3af'
                },
                'minimal-clean': {
                    '--cis-primary': '#000000',         // Pure black primary
                    '--cis-primary-hover': '#374151',   
                    '--cis-primary-light': '#f3f4f6',
                    '--cis-success': '#047857',         // Deep green
                    '--cis-danger': '#b91c1c',          // Deep red
                    '--cis-warning': '#b45309',         // Deep orange
                    '--cis-info': '#0e7490',            // Deep teal
                    '--cis-sidebar-bg': '#ffffff',      
                    '--cis-header-bg': '#ffffff',       
                    '--cis-card-bg': '#ffffff',
                    '--cis-border-color': '#e5e7eb',    
                    '--cis-dark': '#000000',
                    '--cis-light': '#ffffff',
                    '--cis-secondary': '#6b7280'
                }
            };

            if (presets[presetName]) {
                currentTheme = { ...presets[presetName] };
                
                // Update all inputs
                Object.keys(currentTheme).forEach(varName => {
                    const picker = document.querySelector(`[data-var="${varName}"]`);
                    if (picker) {
                        picker.value = currentTheme[varName];
                        const hexInput = document.getElementById(picker.id + '-hex');
                        if (hexInput) {
                            hexInput.value = currentTheme[varName];
                        }
                    }
                    
                    // Apply to CSS
                    document.documentElement.style.setProperty(varName, currentTheme[varName]);
                });
                
                generateCSS();
            }
        }
    </script>
</body>
</html>
<?php
// This would be included in a layout template normally
// For now it's standalone
?>