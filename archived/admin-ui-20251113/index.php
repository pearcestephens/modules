<?php
/**
 * CIS Admin UI - Component Showcase
 * Professional, high-quality design system showcase
 *
 * @package CIS\Modules\AdminUI
 * @version 2.0.0
 * @date October 2025
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Session management
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CIS Component Showcase - Professional UI Design System</title>

    <!-- Bootstrap 4.6 (Grid & Components) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" integrity="sha512-rt/SrQ4UNIaGfDyEXZtNcgyVt0/wOILiqWVAdQp/sqLpVzOJE8CIrGT8z3dAT5rPe86qXCzwaQdc3ggcgW8sha==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVJkEZSMUkrQ6usKu8zTSf2oj7kiy0mo50g5A3hLUp/0JA44l+NnRrKjPVke+o8xehpqpKWLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- CIS Professional Brand System -->
    <link rel="stylesheet" href="/modules/admin-ui/css/cis-brand.css">

    <style>
        /* Additional page-specific styles */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeInUp 0.3s ease-out;
        }
    </style>
</head>
<body>

    <!-- HERO SECTION -->
    <div class="cis-hero">
        <div class="cis-hero-content cis-container">
            <h1><i class="fas fa-palette"></i> CIS Component Showcase</h1>
            <p class="lead">Professional, high-quality UI design system for modern web applications</p>

            <div class="cis-hero-actions">
                <a href="template-showcase.php" class="cis-btn cis-btn-warning cis-btn-lg">
                    <i class="fas fa-fire"></i> Template Showcase
                </a>
                <a href="theme-builder.php" class="cis-btn cis-btn-light cis-btn-lg">
                    <i class="fas fa-magic"></i> 🎨 Ultimate Theme Builder
                </a>
                <a href="#documentation" class="cis-btn cis-btn-outline cis-btn-lg">
                    <i class="fas fa-book"></i> Documentation
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="cis-container">
        <div class="cis-nav-container">

            <!-- SIDEBAR NAVIGATION -->
            <div class="cis-nav-sidebar">
                <div class="cis-nav-pills">
                    <button class="cis-nav-link active" onclick="showTab('buttons')">
                        <i class="fas fa-hand-pointer"></i> Buttons
                    </button>
                    <button class="cis-nav-link" onclick="showTab('forms')">
                        <i class="fas fa-edit"></i> Forms
                    </button>
                    <button class="cis-nav-link" onclick="showTab('tables')">
                        <i class="fas fa-table"></i> Tables
                    </button>
                    <button class="cis-nav-link" onclick="showTab('cards')">
                        <i class="fas fa-id-card"></i> Cards
                    </button>
                    <button class="cis-nav-link" onclick="showTab('alerts')">
                        <i class="fas fa-exclamation-triangle"></i> Alerts
                    </button>
                    <button class="cis-nav-link" onclick="showTab('badges')">
                        <i class="fas fa-tag"></i> Badges
                    </button>
                    <button class="cis-nav-link" onclick="showTab('typography')">
                        <i class="fas fa-font"></i> Typography
                    </button>
                </div>
            </div>

            <!-- TAB CONTENT -->
            <div class="cis-nav-content">

                <!-- BUTTONS TAB -->
                <div id="buttons" class="tab-content active">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-hand-pointer"></i> Buttons
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <!-- Primary Buttons -->
                            <div class="cis-component-group">
                                <div class="cis-component-label">Primary Actions</div>
                                <div class="cis-component-demo">
                                    <button class="cis-btn cis-btn-primary mr-2 mb-2">
                                        <i class="fas fa-check"></i> Primary Action
                                    </button>
                                    <button class="cis-btn cis-btn-secondary mr-2 mb-2">
                                        <i class="fas fa-info-circle"></i> Secondary Action
                                    </button>
                                    <button class="cis-btn cis-btn-success mr-2 mb-2">
                                        <i class="fas fa-check-circle"></i> Success Action
                                    </button>
                                    <button class="cis-btn cis-btn-warning mr-2 mb-2">
                                        <i class="fas fa-exclamation-triangle"></i> Warning Action
                                    </button>
                                </div>
                                <div class="cis-code-block">&lt;button class="cis-btn cis-btn-primary"&gt;&lt;i class="fas fa-check"&gt;&lt;/i&gt; Primary Action&lt;/button&gt;</div>
                            </div>

                            <!-- Button Sizes -->
                            <div class="cis-component-group">
                                <div class="cis-component-label">Button Sizes</div>
                                <div class="cis-component-demo">
                                    <button class="cis-btn cis-btn-primary cis-btn-lg mr-2 mb-2">Large Button</button>
                                    <button class="cis-btn cis-btn-primary mr-2 mb-2">Default Button</button>
                                    <button class="cis-btn cis-btn-primary cis-btn-sm mr-2 mb-2">Small Button</button>
                                </div>
                                <div class="cis-code-block">&lt;button class="cis-btn cis-btn-primary cis-btn-lg"&gt;Large Button&lt;/button&gt;</div>
                            </div>

                            <!-- Light & Outline Buttons -->
                            <div class="cis-component-group">
                                <div class="cis-component-label">Light & Outline Variants</div>
                                <div class="cis-component-demo" style="background: var(--gradient-hero); padding: 2rem; border-radius: var(--radius-lg);">
                                    <button class="cis-btn cis-btn-light mr-2 mb-2">
                                        <i class="fas fa-lightbulb"></i> Light Button
                                    </button>
                                    <button class="cis-btn cis-btn-outline mr-2 mb-2">
                                        <i class="fas fa-arrow-right"></i> Outline Button
                                    </button>
                                </div>
                                <div class="cis-code-block">&lt;button class="cis-btn cis-btn-light"&gt;Light Button&lt;/button&gt;</div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- FORMS TAB -->
                <div id="forms" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-edit"></i> Form Elements
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Text Inputs</div>
                                <div class="cis-component-demo">
                                    <div class="cis-form-group">
                                        <label class="cis-form-label">Full Name</label>
                                        <input type="text" class="cis-form-control" placeholder="Enter your full name">
                                    </div>
                                    <div class="cis-form-group">
                                        <label class="cis-form-label">Email Address</label>
                                        <input type="email" class="cis-form-control" placeholder="your.email@example.com">
                                    </div>
                                    <div class="cis-form-group">
                                        <label class="cis-form-label">Message</label>
                                        <textarea class="cis-form-control" rows="4" placeholder="Type your message here..."></textarea>
                                    </div>
                                </div>
                                <div class="cis-code-block">&lt;input type="text" class="cis-form-control" placeholder="Enter text"&gt;</div>
                            </div>

                            <div class="cis-component-group">
                                <div class="cis-component-label">Select & Checkboxes</div>
                                <div class="cis-component-demo">
                                    <div class="cis-form-group">
                                        <label class="cis-form-label">Select Option</label>
                                        <select class="cis-form-control">
                                            <option>Option 1</option>
                                            <option>Option 2</option>
                                            <option>Option 3</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- TABLES TAB -->
                <div id="tables" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-table"></i> Data Tables
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Standard Table</div>
                                <div class="cis-component-demo">
                                    <table class="table table-hover">
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
                                                <td>John Doe</td>
                                                <td><span class="cis-badge cis-badge-success">Active</span></td>
                                                <td>
                                                    <button class="cis-btn cis-btn-primary cis-btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>Jane Smith</td>
                                                <td><span class="cis-badge cis-badge-warning">Pending</span></td>
                                                <td>
                                                    <button class="cis-btn cis-btn-primary cis-btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Mike Johnson</td>
                                                <td><span class="cis-badge cis-badge-danger">Inactive</span></td>
                                                <td>
                                                    <button class="cis-btn cis-btn-primary cis-btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- CARDS TAB -->
                <div id="cards" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-id-card"></i> Cards & Containers
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Card Layouts</div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="cis-card">
                                            <div class="cis-card-header">
                                                <h3 class="cis-card-title" style="font-size: 1.25rem;">
                                                    <i class="fas fa-star"></i> Card Title
                                                </h3>
                                            </div>
                                            <div class="cis-card-body">
                                                <p>This is a beautiful card component with gradient accents and smooth shadows.</p>
                                                <button class="cis-btn cis-btn-primary cis-btn-sm">
                                                    <i class="fas fa-arrow-right"></i> Learn More
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="cis-card">
                                            <div class="cis-card-header">
                                                <h3 class="cis-card-title" style="font-size: 1.25rem;">
                                                    <i class="fas fa-chart-line"></i> Analytics
                                                </h3>
                                            </div>
                                            <div class="cis-card-body">
                                                <p>Track your metrics with beautiful data visualization components.</p>
                                                <button class="cis-btn cis-btn-secondary cis-btn-sm">
                                                    <i class="fas fa-chart-bar"></i> View Stats
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="cis-card">
                                            <div class="cis-card-header">
                                                <h3 class="cis-card-title" style="font-size: 1.25rem;">
                                                    <i class="fas fa-cog"></i> Settings
                                                </h3>
                                            </div>
                                            <div class="cis-card-body">
                                                <p>Configure your application with powerful, intuitive controls.</p>
                                                <button class="cis-btn cis-btn-success cis-btn-sm">
                                                    <i class="fas fa-sliders-h"></i> Configure
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ALERTS TAB -->
                <div id="alerts" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-exclamation-triangle"></i> Alerts & Messages
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Alert Types</div>
                                <div class="cis-component-demo">
                                    <div class="cis-alert cis-alert-success">
                                        <div class="cis-alert-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="cis-alert-content">
                                            <strong>Success!</strong> Your changes have been saved successfully.
                                        </div>
                                    </div>

                                    <div class="cis-alert cis-alert-warning">
                                        <div class="cis-alert-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="cis-alert-content">
                                            <strong>Warning:</strong> Please review the following information before proceeding.
                                        </div>
                                    </div>

                                    <div class="cis-alert cis-alert-danger">
                                        <div class="cis-alert-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="cis-alert-content">
                                            <strong>Error:</strong> Something went wrong. Please try again.
                                        </div>
                                    </div>

                                    <div class="cis-alert cis-alert-info">
                                        <div class="cis-alert-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="cis-alert-content">
                                            <strong>Info:</strong> Here's some helpful information for you.
                                        </div>
                                    </div>
                                </div>
                                <div class="cis-code-block">&lt;div class="cis-alert cis-alert-success"&gt;...&lt;/div&gt;</div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- BADGES TAB -->
                <div id="badges" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-tag"></i> Badges & Labels
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Status Badges</div>
                                <div class="cis-component-demo">
                                    <span class="cis-badge cis-badge-primary mr-2 mb-2">
                                        <i class="fas fa-star"></i> Primary
                                    </span>
                                    <span class="cis-badge cis-badge-success mr-2 mb-2">
                                        <i class="fas fa-check"></i> Success
                                    </span>
                                    <span class="cis-badge cis-badge-warning mr-2 mb-2">
                                        <i class="fas fa-exclamation"></i> Warning
                                    </span>
                                    <span class="cis-badge cis-badge-danger mr-2 mb-2">
                                        <i class="fas fa-times"></i> Danger
                                    </span>
                                </div>
                                <div class="cis-code-block">&lt;span class="cis-badge cis-badge-primary"&gt;Primary&lt;/span&gt;</div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- TYPOGRAPHY TAB -->
                <div id="typography" class="tab-content">
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h2 class="cis-card-title">
                                <i class="fas fa-font"></i> Typography
                            </h2>
                        </div>
                        <div class="cis-card-body">

                            <div class="cis-component-group">
                                <div class="cis-component-label">Headings</div>
                                <div class="cis-component-demo">
                                    <h1>Heading 1 - Large Display</h1>
                                    <h2>Heading 2 - Section Title</h2>
                                    <h3>Heading 3 - Subsection</h3>
                                    <h4>Heading 4 - Card Title</h4>
                                    <p class="lead">This is a lead paragraph with larger text for emphasis and better readability.</p>
                                    <p>This is regular body text that demonstrates the default paragraph styling with proper line height and spacing for optimal readability.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- FOOTER SPACING -->
    <div style="height: 4rem;"></div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js" integrity="sha512-igl8WEUuas9k5dtnhKqyyld6TzzRjvMqLC79jkgT3z02FvJyHAuUtyemm/P/jYSne1xwFI06ezQxEwweaiV7VA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        // Tab switching functionality
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active state from all nav links
            document.querySelectorAll('.cis-nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabId).classList.add('active');

            // Add active state to clicked nav link
            event.target.classList.add('active');

            // Smooth scroll to top of content
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Add smooth animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.cis-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('cis-animate-in');
            });
        });
    </script>

</body>
</html>
