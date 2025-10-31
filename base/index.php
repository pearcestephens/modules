<?php
/**
 * CIS Modern Template System - Index/Links Page
 * 
 * Quick access to all new components and demos
 * 
 * @package CIS\Base
 * @version 2.0.0
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Modern Template System</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 3rem;
            max-width: 800px;
            width: 100%;
        }
        
        h1 {
            color: #8B5CF6;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .status {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        
        .links-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .link-card {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
        }
        
        .link-card:hover {
            border-color: #8B5CF6;
            background: #EDE9FE;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }
        
        .link-card-title {
            color: #343a40;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .link-card-icon {
            font-size: 1.5rem;
        }
        
        .link-card-desc {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .link-card-badge {
            display: inline-block;
            background: #8B5CF6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .features {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .features h3 {
            color: #343a40;
            margin-bottom: 1rem;
        }
        
        .features ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
        }
        
        .features li {
            color: #495057;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .features li:before {
            content: "✅";
            position: absolute;
            left: 0;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #dee2e6;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 2rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .features ul {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <h1>
            🎨 CIS Modern Template System
        </h1>
        
        <p class="subtitle">
            Complete two-tier header, purple theme, Facebook-style chat, and theme builder
        </p>
        
        <span class="status">✅ Production Ready v2.0</span>
        
        <div class="links-grid">
            
            <!-- Main Demo -->
            <a href="dashboard-demo.php" class="link-card">
                <div class="link-card-title">
                    <span class="link-card-icon">🏠</span>
                    Complete Dashboard Demo
                    <span class="link-card-badge">MAIN</span>
                </div>
                <div class="link-card-desc">
                    Full working template with two-tier header, purple buttons, stats cards, tables, activity feed, and all components integrated together.
                </div>
            </a>
            
            <!-- Theme Builder -->
            <a href="theme-builder.php" class="link-card">
                <div class="link-card-title">
                    <span class="link-card-icon">🎨</span>
                    Theme Builder Tool
                </div>
                <div class="link-card-desc">
                    Visual color picker for all CIS elements. See live preview and generate CSS variables. Customize your entire color scheme.
                </div>
            </a>
            
            <!-- Documentation -->
            <a href="TEMPLATE_README.md" class="link-card">
                <div class="link-card-title">
                    <span class="link-card-icon">📖</span>
                    Full Documentation
                </div>
                <div class="link-card-desc">
                    Complete guide on how to use components, configuration options, customization, and integration examples.
                </div>
            </a>
            
        </div>
        
        <div class="features">
            <h3>✨ What's Included</h3>
            <ul>
                <li>Two-tier header with logo & search</li>
                <li>Purple accent colors (#8B5CF6)</li>
                <li>Notification badges (13)</li>
                <li>Messages icon with count</li>
                <li>Center search bar</li>
                <li>Purple quick action buttons</li>
                <li>Breadcrumbs navigation</li>
                <li>Dark gray sidebar</li>
                <li>Facebook-style chat bar</li>
                <li>Stats dashboard cards</li>
                <li>Activity feed</li>
                <li>Data tables</li>
                <li>Footer component</li>
                <li>Responsive design</li>
                <li>Theme customization tool</li>
            </ul>
        </div>
        
        <div class="footer">
            <strong>Version 2.0.0</strong> • October 2024 • The Vape Shed CIS
            <br>
            <small>Modern template system matching current CIS design with fresh upgrades</small>
        </div>
        
    </div>
</body>
</html>
