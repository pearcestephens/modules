<?php
/**
 * 🎨 QUICK THEME GALLERY
 *
 * Fast way to view all available themes/templates in the CIS system
 *
 * Usage: Open this file in browser
 * URL: https://staff.vapeshed.co.nz/modules/consignments/THEME_GALLERY.php
 */

session_start();

// Simulate logged-in user for demo
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$basePath = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/templates/themes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 CIS Theme Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero {
            text-align: center;
            color: white;
            padding: 40px 20px;
        }
        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 10px;
        }
        .hero p {
            font-size: 1.2rem;
            opacity: 0.95;
        }
        .theme-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .theme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        .theme-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        .theme-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.8rem;
            color: white;
        }
        .theme-title {
            flex: 1;
        }
        .theme-title h2 {
            margin: 0;
            font-size: 1.8rem;
            color: #2d3748;
        }
        .theme-title .badge {
            font-size: 0.75rem;
            margin-left: 10px;
        }
        .theme-description {
            color: #718096;
            margin-bottom: 20px;
            font-size: 1.05rem;
        }
        .demo-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .demo-btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .demo-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .demo-btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .demo-btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        .demo-btn-secondary:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
            color: #2d3748;
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f7fafc;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .feature-item i {
            color: #667eea;
            font-size: 1.2rem;
        }
        .quick-links {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .quick-links h3 {
            color: #2d3748;
            margin-bottom: 20px;
        }
        .link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .link-item {
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s ease;
        }
        .link-item:hover {
            transform: translateX(5px);
            color: white;
        }
        .link-item i {
            font-size: 1.5rem;
        }
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-production {
            background: #48bb78;
            color: white;
        }
        .status-beta {
            background: #ed8936;
            color: white;
        }
        .status-legacy {
            background: #a0aec0;
            color: white;
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1>🎨 CIS Theme Gallery</h1>
        <p>Browse and test all available themes for the Central Information System</p>
    </div>

    <div class="container" style="max-width: 1200px;">

        <!-- CIS Classic Theme -->
        <div class="theme-card" style="position: relative;">
            <span class="status-badge status-production">
                <i class="fas fa-check-circle"></i> PRODUCTION
            </span>
            <div class="theme-header">
                <div class="theme-icon">
                    <i class="fas fa-desktop"></i>
                </div>
                <div class="theme-title">
                    <h2>CIS Classic Theme</h2>
                    <span class="badge bg-primary">Recommended</span>
                    <span class="badge bg-success">Active</span>
                </div>
            </div>
            <div class="theme-description">
                Professional, clean interface designed for The Vape Shed CIS. Features responsive sidebar navigation,
                customizable header with action buttons, breadcrumbs, and a polished Bootstrap 5-based design system.
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="fas fa-bars"></i>
                    <span>Sidebar Navigation</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Fully Responsive</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-paint-brush"></i>
                    <span>Bootstrap 5</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-bolt"></i>
                    <span>Action Buttons</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-route"></i>
                    <span>Breadcrumbs</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>CSRF Protected</span>
                </div>
            </div>

            <div class="demo-buttons mt-4">
                <a href="/modules/base/templates/themes/cis-classic/demo.php" target="_blank" class="demo-btn demo-btn-primary">
                    <i class="fas fa-eye"></i> View Live Demo
                </a>
                <a href="/modules/base/templates/themes/cis-classic/examples/demo-simple.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-code"></i> Simple Example
                </a>
                <a href="/modules/base/templates/themes/cis-classic/examples/ui-showcase.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-palette"></i> UI Showcase
                </a>
                <a href="/modules/base/templates/themes/cis-classic/examples/subtitle-demo.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-heading"></i> Subtitle Demo
                </a>
            </div>
        </div>

        <!-- Legacy Theme -->
        <div class="theme-card" style="position: relative;">
            <span class="status-badge status-legacy">
                <i class="fas fa-archive"></i> LEGACY
            </span>
            <div class="theme-header">
                <div class="theme-icon" style="background: #a0aec0;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="theme-title">
                    <h2>Legacy Theme</h2>
                    <span class="badge bg-secondary">Old Style</span>
                </div>
            </div>
            <div class="theme-description">
                Original CIS theme system. Simple templates for dashboard, cards, tables, and blank layouts.
                Minimal styling, maintained for backward compatibility with older modules.
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="fas fa-table"></i>
                    <span>Table Layout</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-id-card"></i>
                    <span>Card Layout</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-file"></i>
                    <span>Blank Layout</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Layout</span>
                </div>
            </div>

            <div class="demo-buttons mt-4">
                <a href="/modules/base/templates/themes/legacy/dashboard.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-eye"></i> Dashboard
                </a>
                <a href="/modules/base/templates/themes/legacy/card.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-eye"></i> Card
                </a>
                <a href="/modules/base/templates/themes/legacy/table.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-eye"></i> Table
                </a>
                <a href="/modules/base/templates/themes/legacy/blank.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-eye"></i> Blank
                </a>
            </div>
        </div>

        <!-- Modern Theme -->
        <div class="theme-card" style="position: relative;">
            <span class="status-badge status-beta">
                <i class="fas fa-flask"></i> BETA
            </span>
            <div class="theme-header">
                <div class="theme-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="theme-title">
                    <h2>Modern Theme</h2>
                    <span class="badge bg-warning">In Development</span>
                </div>
            </div>
            <div class="theme-description">
                Next-generation CIS theme with modern UI components, animations, and enhanced interactivity.
                Features modular components, dark mode support, and advanced dashboard layouts.
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="fas fa-moon"></i>
                    <span>Dark Mode</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-puzzle-piece"></i>
                    <span>Modular Components</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-magic"></i>
                    <span>Animations</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Advanced Dashboard</span>
                </div>
            </div>

            <div class="demo-buttons mt-4">
                <a href="/modules/base/templates/themes/modern/layouts/dashboard.php" target="_blank" class="demo-btn demo-btn-secondary">
                    <i class="fas fa-eye"></i> Dashboard
                </a>
                <span class="text-muted" style="padding: 12px;">
                    <i class="fas fa-info-circle"></i> More examples coming soon...
                </span>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="quick-links">
            <h3><i class="fas fa-link"></i> Related Resources</h3>
            <div class="link-grid">
                <a href="/modules/consignments/stock-transfers/pack-layout-a-v2-PRODUCTION.php?transfer_id=1" class="link-item">
                    <i class="fas fa-box"></i>
                    <div>
                        <div style="font-weight: 600;">Packing Layout A</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Desktop Version</div>
                    </div>
                </a>
                <a href="/modules/consignments/stock-transfers/pack-layout-b-v2-PRODUCTION.php?transfer_id=1" class="link-item">
                    <i class="fas fa-tablet-alt"></i>
                    <div>
                        <div style="font-weight: 600;">Packing Layout B</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Tablet Version</div>
                    </div>
                </a>
                <a href="/modules/consignments/stock-transfers/pack-layout-c-v2-PRODUCTION.php?transfer_id=1" class="link-item">
                    <i class="fas fa-mobile-alt"></i>
                    <div>
                        <div style="font-weight: 600;">Packing Layout C</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Mobile Version</div>
                    </div>
                </a>
                <a href="/modules/consignments/themes-demo/interface.html" class="link-item">
                    <i class="fas fa-shapes"></i>
                    <div>
                        <div style="font-weight: 600;">Interface Demo</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">HTML Mockup</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Documentation -->
        <div class="quick-links">
            <h3><i class="fas fa-book"></i> Documentation</h3>
            <div class="link-grid">
                <a href="/modules/base/templates/themes/README.md" class="link-item" style="background: #4299e1;">
                    <i class="fas fa-readme"></i>
                    <div>
                        <div style="font-weight: 600;">Theme README</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Getting Started</div>
                    </div>
                </a>
                <a href="/modules/consignments/stock-transfers/README_PRODUCTION_LAYOUTS.md" class="link-item" style="background: #48bb78;">
                    <i class="fas fa-file-code"></i>
                    <div>
                        <div style="font-weight: 600;">Layouts Documentation</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Packing System</div>
                    </div>
                </a>
                <a href="/modules/consignments/stock-transfers/DEPLOYMENT_GUIDE.md" class="link-item" style="background: #ed8936;">
                    <i class="fas fa-rocket"></i>
                    <div>
                        <div style="font-weight: 600;">Deployment Guide</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">5-Minute Setup</div>
                    </div>
                </a>
                <a href="/modules/consignments/stock-transfers/QA_CHECKLIST.md" class="link-item" style="background: #9f7aea;">
                    <i class="fas fa-tasks"></i>
                    <div>
                        <div style="font-weight: 600;">QA Checklist</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">Testing Guide</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 40px 20px; color: white;">
            <p style="font-size: 1.1rem; margin-bottom: 10px;">
                <i class="fas fa-code"></i> Built for The Vape Shed CIS
            </p>
            <p style="opacity: 0.8; font-size: 0.95rem;">
                Powered by Bootstrap 5 • Font Awesome • PHP 8.1+
            </p>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
