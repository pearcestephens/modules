<?php
/**
 * Admin UI - Theme System Example
 *
 * Demonstrates how to use the theme system in admin-ui pages.
 * This shows the proper way to create themed pages.
 */

// Load admin-ui bootstrap (includes theme system)
require_once __DIR__ . '/bootstrap.php';

// Start the themed page
theme_page_start('Theme System Demo - Admin UI', 'theme-demo');
?>

<!-- YOUR PAGE CONTENT GOES HERE -->
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-palette"></i> Theme System Demo</h1>
            <p class="lead">Admin UI now supports multiple themes that can be switched without conflicts!</p>
        </div>
    </div>

    <!-- Current Theme Info -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Current Theme</h5>
                </div>
                <div class="card-body">
                    <p><strong>Theme:</strong> <?php echo htmlspecialchars(theme()->getCurrentThemeName() ?? 'Unknown'); ?></p>
                    <p><strong>Class:</strong> <?php echo htmlspecialchars(get_class(currentTheme())); ?></p>
                    <p class="mb-0"><strong>Status:</strong> <span class="badge badge-success">Active</span></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Available Themes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $themes = theme()->getAvailableThemes();
                    $currentTheme = theme()->getCurrentThemeName();
                    ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($themes as $name => $info): ?>
                            <li class="mb-2">
                                <?php if ($name === $currentTheme): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                    <strong><?php echo htmlspecialchars($name); ?></strong>
                                    <span class="badge badge-success ml-2">Active</span>
                                <?php else: ?>
                                    <i class="far fa-circle text-muted"></i>
                                    <?php echo htmlspecialchars($name); ?>
                                    <a href="?switch_theme=<?php echo urlencode($name); ?>" class="btn btn-sm btn-outline-primary ml-2">Switch</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Example -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-code"></i> Usage Example</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 border rounded"><code>&lt;?php
// Load admin-ui bootstrap (includes theme system)
require_once __DIR__ . '/bootstrap.php';

// Start the themed page
theme_page_start('My Page Title', 'my-page-slug');
?&gt;

&lt;!-- Your page content here --&gt;
&lt;div class="container-fluid"&gt;
    &lt;h1&gt;My Page&lt;/h1&gt;
    &lt;p&gt;Content goes here...&lt;/p&gt;
&lt;/div&gt;

&lt;?php
// End the themed page
theme_page_end();
?&gt;</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Features -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-check-double"></i> Theme System Features</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-exchange-alt text-success"></i> Theme Switching</h6>
                            <ul>
                                <li>Switch themes without code conflicts</li>
                                <li>Persists across sessions</li>
                                <li>Instant visual change</li>
                                <li>URL parameter support: <code>?switch_theme=cis-classic</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-cogs text-primary"></i> Easy Integration</h6>
                            <ul>
                                <li>Simple helper functions</li>
                                <li>Automatic theme discovery</li>
                                <li>Component-based rendering</li>
                                <li>Extensible architecture</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Helper Functions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-tools"></i> Helper Functions Available</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Function</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>theme()</code></td>
                                <td>Get theme manager instance</td>
                                <td><code>$manager = theme();</code></td>
                            </tr>
                            <tr>
                                <td><code>currentTheme()</code></td>
                                <td>Get current theme instance</td>
                                <td><code>$theme = currentTheme();</code></td>
                            </tr>
                            <tr>
                                <td><code>theme_page_start()</code></td>
                                <td>Start themed page with header/sidebar</td>
                                <td><code>theme_page_start('Title', 'page-slug');</code></td>
                            </tr>
                            <tr>
                                <td><code>theme_page_end()</code></td>
                                <td>End themed page with footer</td>
                                <td><code>theme_page_end();</code></td>
                            </tr>
                            <tr>
                                <td><code>theme_render()</code></td>
                                <td>Render specific component</td>
                                <td><code>theme_render('header');</code></td>
                            </tr>
                            <tr>
                                <td><code>theme_add_head()</code></td>
                                <td>Add custom CSS/JS to head</td>
                                <td><code>theme_add_head('&lt;style&gt;...&lt;/style&gt;');</code></td>
                            </tr>
                            <tr>
                                <td><code>theme_config()</code></td>
                                <td>Get theme config value</td>
                                <td><code>$url = theme_config('base_url');</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-flask"></i> Test Actions</h5>
                </div>
                <div class="card-body">
                    <p>Try these actions to test the theme system:</p>
                    <div class="btn-group" role="group">
                        <?php foreach ($themes as $name => $info): ?>
                            <a href="?switch_theme=<?php echo urlencode($name); ?>"
                               class="btn <?php echo ($name === $currentTheme) ? 'btn-success' : 'btn-outline-primary'; ?>">
                                <?php echo htmlspecialchars($name); ?>
                                <?php if ($name === $currentTheme): ?>
                                    <i class="fas fa-check ml-1"></i>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
// End the themed page
theme_page_end();
?>
