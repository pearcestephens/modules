<?php
/**
 * CIS Classic Theme - Page Subtitle Demo
 *
 * This example demonstrates how to use the page subtitle feature
 * in the action bar.
 */

// Initialize theme
require_once __DIR__ . '/../theme.php';
$theme = new \CIS\Theme\CISClassic();

// Set page title
$theme->setPageTitle('Subtitle Demo - CIS Classic Theme');

// Set page subtitle (appears in action bar)
$theme->setPageSubtitle('Inventory Management Dashboard');

// Optional: Add breadcrumbs
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Dashboard', '/dashboard.php');
$theme->addBreadcrumb('Inventory');

// Optional: Add header buttons
$theme->addHeaderButton('Export Data', '#', 'primary', 'fas fa-download');
$theme->addHeaderButton('Settings', '#', 'secondary', 'fas fa-cog');

// Optional: Show timestamps
$theme->showTimestamps(true);

// Render page
?>
<!DOCTYPE html>
<html lang="en">
<?php $theme->renderHead(); ?>
<body class="<?php echo $theme->getPageData('body_class'); ?>">

  <?php $theme->renderHeader(); ?>

  <div class="app-body">
    <?php $theme->renderSidebar(); ?>

    <main class="main">
      <div class="container-fluid mt-4">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <strong>Page Subtitle Feature</strong>
                <small>Action Bar Enhancement</small>
              </div>
              <div class="card-body">
                <h5>How to Use Page Subtitles</h5>

                <p>The page subtitle appears prominently in the action bar (below the main header), providing clear context about the current page or section.</p>

                <h6 class="mt-4">Basic Usage:</h6>
                <pre><code class="php">// Set the page subtitle
$theme->setPageSubtitle('Inventory Management Dashboard');</code></pre>

                <h6 class="mt-4">Combined with Other Features:</h6>
                <pre><code class="php">// Set subtitle
$theme->setPageSubtitle('Order Processing');

// Add breadcrumbs (appear after subtitle)
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Orders', '/orders.php');
$theme->addBreadcrumb('Processing');

// Add action buttons (appear on right)
$theme->addHeaderButton('New Order', '/orders/new.php', 'primary', 'fas fa-plus');
$theme->addHeaderButton('Export', '/orders/export.php', 'secondary', 'fas fa-download');

// Show timestamp (far right)
$theme->showTimestamps(true);</code></pre>

                <h6 class="mt-4">Layout Order (left to right):</h6>
                <ol>
                  <li><strong>Page Subtitle</strong> - Bold, prominent text</li>
                  <li><strong>Breadcrumbs</strong> - Navigation path (if set)</li>
                  <li><strong>Action Buttons</strong> - Quick actions (if set)</li>
                  <li><strong>Timestamp</strong> - Current date/time (if enabled)</li>
                </ol>

                <h6 class="mt-4">Best Practices:</h6>
                <ul>
                  <li>Keep subtitles concise (2-5 words)</li>
                  <li>Use title case: "Inventory Management" not "inventory management"</li>
                  <li>Make it descriptive: "Customer Orders" better than "Orders"</li>
                  <li>Consider the full action bar: subtitle + breadcrumbs + buttons</li>
                </ul>

                <h6 class="mt-4">Examples:</h6>
                <table class="table table-sm table-bordered mt-2">
                  <thead>
                    <tr>
                      <th>Page Type</th>
                      <th>Good Subtitle</th>
                      <th>Why It Works</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Dashboard</td>
                      <td>Sales Dashboard</td>
                      <td>Specific about what data is shown</td>
                    </tr>
                    <tr>
                      <td>List Page</td>
                      <td>Active Consignments</td>
                      <td>Indicates filter/status</td>
                    </tr>
                    <tr>
                      <td>Detail Page</td>
                      <td>Consignment #CS-12345</td>
                      <td>Shows specific record ID</td>
                    </tr>
                    <tr>
                      <td>Form Page</td>
                      <td>Create New Transfer</td>
                      <td>Clear action being performed</td>
                    </tr>
                    <tr>
                      <td>Report Page</td>
                      <td>Monthly Sales Report</td>
                      <td>Describes report type and timeframe</td>
                    </tr>
                  </tbody>
                </table>

                <h6 class="mt-4">Try Different Subtitles:</h6>
                <div class="btn-group" role="group">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateSubtitle('Sales Dashboard')">Sales Dashboard</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateSubtitle('Inventory Overview')">Inventory Overview</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateSubtitle('Order Processing')">Order Processing</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateSubtitle('Customer Management')">Customer Management</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateSubtitle('')">Clear Subtitle</button>
                </div>

                <script>
                function updateSubtitle(text) {
                  // In a real application, this would make an AJAX call
                  // For demo purposes, we'll just reload with a parameter
                  const url = new URL(window.location.href);
                  url.searchParams.set('subtitle', text);
                  window.location.href = url.toString();
                }
                </script>

              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

  </div>

  <?php $theme->renderFooter(); ?>

</body>
</html>
