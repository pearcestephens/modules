<?php
/**
 * CIS Classic Theme - Demo Page
 *
 * This demonstrates how to use the CIS Classic theme in your module.
 * Copy this pattern to your own pages.
 *
 * @package Base\Templates\Themes\CISClassic
 * @version 1.0.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For demo purposes, simulate a logged-in user
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include the theme class
require_once __DIR__ . '/theme.php';

// Create theme instance
$theme = new CISClassicTheme();

// Set page configuration
$theme->setTitle('CIS Classic Theme Demo - The Vape Shed');
$theme->setCurrentPage('demo');

// Add custom CSS for this page only
$theme->addHeadContent('
<style>
    .demo-section {
        background: white;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .demo-section h2 {
        color: #20a8d8;
        margin-bottom: 15px;
        border-bottom: 2px solid #f0f3f5;
        padding-bottom: 10px;
    }
    .code-example {
        background: #2d3e50;
        color: #fff;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        font-family: "Courier New", monospace;
        font-size: 13px;
        margin: 10px 0;
    }
    .feature-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .feature-card {
        background: #f8f9fa;
        padding: 15px;
        border-left: 4px solid #20a8d8;
        border-radius: 4px;
    }
    .feature-card i {
        color: #20a8d8;
        font-size: 24px;
        margin-bottom: 10px;
    }
</style>
');

// Render page header
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<!-- PAGE CONTENT STARTS HERE -->
<div class="container-fluid">

    <!-- Page Header -->
    <div class="demo-section">
        <h1><i class="fas fa-palette"></i> CIS Classic Theme Demo</h1>
        <p class="lead">This is the rebuilt CIS template system with better structure and cleaner includes.</p>
        <p>Same look and feel, better code architecture. Any module can inherit from this base theme.</p>
    </div>

    <!-- Features -->
    <div class="demo-section">
        <h2><i class="fas fa-star"></i> Theme Features</h2>
        <div class="feature-list">
            <div class="feature-card">
                <i class="fas fa-palette"></i>
                <h5>Original Look & Feel</h5>
                <p>Identical styling to original CIS template - no visual changes</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-code"></i>
                <h5>Clean Code</h5>
                <p>Modular components, better organized, easier to maintain</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-database"></i>
                <h5>Database-Driven</h5>
                <p>Dynamic navigation from permissions and navigation tables</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h5>Secure</h5>
                <p>CSRF protection, permission checks, session management</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-mobile-alt"></i>
                <h5>Responsive</h5>
                <p>Mobile-friendly sidebar toggle and grid layout</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-plug"></i>
                <h5>Easy Integration</h5>
                <p>Simple API, works with any module, minimal setup</p>
            </div>
        </div>
    </div>

    <!-- Usage Example -->
    <div class="demo-section">
        <h2><i class="fas fa-code"></i> Basic Usage Example</h2>
        <p>Here's how to use the theme in your module pages:</p>

        <div class="code-example">
&lt;?php<br>
// Include the theme class<br>
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';<br>
<br>
// Create theme instance<br>
$theme = new CISClassicTheme();<br>
<br>
// Set page title<br>
$theme->setTitle('My Page - The Vape Shed');<br>
<br>
// Set current page for active menu<br>
$theme->setCurrentPage('mypage');<br>
<br>
// Render the page<br>
$theme->render('html-head');<br>
$theme->render('header');<br>
$theme->render('sidebar');<br>
$theme->render('main-start');<br>
?&gt;<br>
<br>
&lt;!-- YOUR CONTENT HERE --&gt;<br>
&lt;div class="container-fluid"&gt;<br>
&nbsp;&nbsp;&lt;h1&gt;My Page&lt;/h1&gt;<br>
&nbsp;&nbsp;&lt;p&gt;Your content...&lt;/p&gt;<br>
&lt;/div&gt;<br>
<br>
&lt;?php<br>
$theme->render('footer');<br>
?&gt;
        </div>
    </div>

    <!-- Component Overview -->
    <div class="demo-section">
        <h2><i class="fas fa-puzzle-piece"></i> Theme Components</h2>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Component</th>
                    <th>File</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>html-head</code></td>
                    <td>components/html-head.php</td>
                    <td>HTML head with all CSS/JS, page title, meta tags</td>
                </tr>
                <tr>
                    <td><code>header</code></td>
                    <td>components/header.php</td>
                    <td>Top navbar with logo, notifications, user menu</td>
                </tr>
                <tr>
                    <td><code>sidebar</code></td>
                    <td>components/sidebar.php</td>
                    <td>Dynamic navigation menu (database-driven)</td>
                </tr>
                <tr>
                    <td><code>main-start</code></td>
                    <td>components/main-start.php</td>
                    <td>Opens main content area</td>
                </tr>
                <tr>
                    <td><code>footer</code></td>
                    <td>components/footer.php</td>
                    <td>Footer with scripts, closes all tags</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Theme Methods -->
    <div class="demo-section">
        <h2><i class="fas fa-wrench"></i> Available Methods</h2>
        <table class="table table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>setTitle()</code></td>
                    <td>string $title</td>
                    <td>Set page title (shown in browser tab)</td>
                </tr>
                <tr>
                    <td><code>setBodyClass()</code></td>
                    <td>string $class</td>
                    <td>Set body CSS class (for sidebar state, etc)</td>
                </tr>
                <tr>
                    <td><code>setCurrentPage()</code></td>
                    <td>string $page</td>
                    <td>Set current page (for active menu highlighting)</td>
                </tr>
                <tr>
                    <td><code>addHeadContent()</code></td>
                    <td>string $content</td>
                    <td>Add custom CSS/JS to &lt;head&gt;</td>
                </tr>
                <tr>
                    <td><code>render()</code></td>
                    <td>string $component, array $data</td>
                    <td>Render a theme component</td>
                </tr>
                <tr>
                    <td><code>getConfig()</code></td>
                    <td>string $key, mixed $default</td>
                    <td>Get configuration value</td>
                </tr>
                <tr>
                    <td><code>getUserData()</code></td>
                    <td>string $key, mixed $default</td>
                    <td>Get user data from session</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Benefits -->
    <div class="demo-section">
        <h2><i class="fas fa-check-circle"></i> Why Use This Theme?</h2>
        <div class="row">
            <div class="col-md-6">
                <h4>✅ For Developers</h4>
                <ul>
                    <li>Clean, maintainable code structure</li>
                    <li>Easy to extend and customize</li>
                    <li>Consistent across all modules</li>
                    <li>Well-documented with examples</li>
                    <li>No need to copy/paste template includes</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>✅ For Users</h4>
                <ul>
                    <li>Familiar CIS interface - no learning curve</li>
                    <li>Same look and feel everywhere</li>
                    <li>Fast page loads (optimized assets)</li>
                    <li>Works on mobile and desktop</li>
                    <li>Reliable and tested</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="demo-section">
        <h2><i class="fas fa-rocket"></i> Next Steps</h2>
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Ready to use this theme?</h5>
            <p>Follow these steps:</p>
            <ol>
                <li>Copy the basic usage example above</li>
                <li>Paste it into your module page</li>
                <li>Customize the title and content</li>
                <li>Test it - it should work immediately!</li>
            </ol>
            <p class="mb-0">
                <strong>Need help?</strong> Check the
                <a href="README.md" target="_blank">full documentation</a> or
                <a href="/submit_ticket.php">submit a ticket</a>.
            </p>
        </div>
    </div>

</div>
<!-- PAGE CONTENT ENDS HERE -->

<?php
// Render page footer
$theme->render('footer');
?>
