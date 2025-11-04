<?php
/**
 * CIS Classic Theme - Simple Demo
 * Test page for page subtitle feature
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize theme
require_once __DIR__ . '/../theme.php';
$theme = new CISClassicTheme();

// Set page title
$theme->setTitle('Action Bar Demo - CIS Classic');

// Set current page
$theme->setCurrentPage('demo');

// Set page subtitle (appears in action bar)
$theme->setPageSubtitle('Action Bar Demo');

// Add breadcrumbs
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Examples', '/examples/');
$theme->addBreadcrumb('Demo');

// Add header buttons
$theme->addHeaderButton('New Item', '#new', 'primary', 'fas fa-plus');
$theme->addHeaderButton('Export', '#export', 'secondary', 'fas fa-download');
$theme->addHeaderButton('Settings', '#settings', 'secondary', 'fas fa-cog');

// Show timestamps
$theme->showTimestamps(true);

// Render page start
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12">

      <div class="card">
        <div class="card-header">
          <strong>Action Bar Demo</strong>
          <small>Page Subtitle Feature</small>
        </div>
        <div class="card-body">

          <div class="alert alert-success">
            <strong>✅ Success!</strong> If you can see this page, the theme is working correctly!
          </div>

          <h5>What You're Seeing:</h5>
          <ul>
            <li><strong>Page Subtitle:</strong> "Action Bar Demo" appears prominently in the action bar</li>
            <li><strong>Breadcrumbs:</strong> Home > Examples > Demo navigation path</li>
            <li><strong>Action Buttons:</strong> Three buttons on the right side</li>
            <li><strong>Timestamp:</strong> Current date/time on far right</li>
          </ul>

          <hr>

          <h5>Code Used:</h5>
          <pre><code class="language-php"><?php echo htmlspecialchars('<?php
$theme = new CISClassicTheme();
$theme->setPageSubtitle(\'Action Bar Demo\');
$theme->addBreadcrumb(\'Home\', \'/\');
$theme->addBreadcrumb(\'Examples\', \'/examples/\');
$theme->addBreadcrumb(\'Demo\');
$theme->addHeaderButton(\'New Item\', \'#new\', \'primary\', \'fas fa-plus\');
$theme->addHeaderButton(\'Export\', \'#export\', \'secondary\', \'fas fa-download\');
$theme->addHeaderButton(\'Settings\', \'#settings\', \'secondary\', \'fas fa-cog\');
$theme->showTimestamps(true);
?>'); ?></code></pre>

          <hr>

          <h5>Try Different Examples:</h5>

          <div class="row mb-3">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header"><strong>Dashboard Example</strong></div>
                <div class="card-body">
                  <pre><code class="language-php"><?php echo htmlspecialchars('$theme->setPageSubtitle(\'Sales Dashboard\');
$theme->showTimestamps(true);'); ?></code></pre>
                  <p><small>Use for: Main dashboards with real-time data</small></p>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card">
                <div class="card-header"><strong>List Page Example</strong></div>
                <div class="card-body">
                  <pre><code class="language-php"><?php echo htmlspecialchars('$theme->setPageSubtitle(\'Active Consignments\');
$theme->addBreadcrumb(\'Home\', \'/\');
$theme->addBreadcrumb(\'Consignments\');
$theme->addHeaderButton(\'New\', \'/new\', \'primary\', \'fas fa-plus\');'); ?></code></pre>
                  <p><small>Use for: List/index pages with create action</small></p>
                </div>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header"><strong>Detail Page Example</strong></div>
                <div class="card-body">
                  <pre><code class="language-php"><?php echo htmlspecialchars('$theme->setPageSubtitle(\'Order #12345\');
$theme->addBreadcrumb(\'Home\', \'/\');
$theme->addBreadcrumb(\'Orders\', \'/orders/\');
$theme->addBreadcrumb(\'#12345\');
$theme->addHeaderButton(\'Edit\', \'/edit?id=12345\', \'primary\', \'fas fa-edit\');'); ?></code></pre>
                  <p><small>Use for: Detail/view pages for specific records</small></p>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card">
                <div class="card-header"><strong>Report Page Example</strong></div>
                <div class="card-body">
                  <pre><code class="language-php"><?php echo htmlspecialchars('$theme->setPageSubtitle(\'Monthly Sales Report\');
$theme->addHeaderButton(\'PDF\', \'/export?pdf\', \'primary\', \'fas fa-file-pdf\');
$theme->addHeaderButton(\'Excel\', \'/export?xlsx\', \'success\', \'fas fa-file-excel\');
$theme->showTimestamps(true);'); ?></code></pre>
                  <p><small>Use for: Reports with export options</small></p>
                </div>
              </div>
            </div>
          </div>

          <hr>

          <h5>Button Colors:</h5>
          <div class="mb-3">
            <button class="btn btn-primary btn-sm">Primary</button>
            <button class="btn btn-secondary btn-sm">Secondary</button>
            <button class="btn btn-success btn-sm">Success</button>
            <button class="btn btn-danger btn-sm">Danger</button>
            <button class="btn btn-warning btn-sm">Warning</button>
            <button class="btn btn-info btn-sm">Info</button>
            <button class="btn btn-purple btn-sm">Purple</button>
            <button class="btn btn-lime btn-sm">Lime</button>
          </div>

          <hr>

          <h5>Common FontAwesome Icons:</h5>
          <div class="mb-3">
            <i class="fas fa-plus"></i> fa-plus (Create/New)<br>
            <i class="fas fa-edit"></i> fa-edit (Edit)<br>
            <i class="fas fa-trash"></i> fa-trash (Delete)<br>
            <i class="fas fa-download"></i> fa-download (Export/Download)<br>
            <i class="fas fa-upload"></i> fa-upload (Import/Upload)<br>
            <i class="fas fa-cog"></i> fa-cog (Settings)<br>
            <i class="fas fa-file-pdf"></i> fa-file-pdf (PDF)<br>
            <i class="fas fa-file-excel"></i> fa-file-excel (Excel)<br>
            <i class="fas fa-search"></i> fa-search (Search)<br>
            <i class="fas fa-filter"></i> fa-filter (Filter)
          </div>

          <hr>

          <div class="alert alert-info">
            <strong>📚 Documentation:</strong> See <code>/modules/base/_templates/themes/cis-classic/QUICK_REFERENCE.md</code> for more examples and best practices.
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<?php
// Render page end
$theme->render('footer');
?>
