<?php
/**
 * CIS Classic Theme - Complete UI Component Demo
 * Shows all buttons, modals, alerts, toasts, and action bar features
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize theme
require_once __DIR__ . '/../theme.php';
$theme = new CISClassicTheme();

// Set page title
$theme->setTitle('UI Component Showcase - CIS Classic');

// Set page subtitle
$theme->setPageSubtitle('Complete UI Component Library');

// Add breadcrumbs
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Examples', '/examples/');
$theme->addBreadcrumb('UI Showcase');

// Add header buttons
$theme->addHeaderButton('Documentation', '#docs', 'primary', 'fas fa-book');
$theme->addHeaderButton('GitHub', '#github', 'secondary', 'fas fa-code-branch');

// Show timestamps
$theme->showTimestamps(true);

// Render page start
?>
<!DOCTYPE html>
<html lang="en">
<?php $theme->render('html-head'); ?>

<style>
/* Alternative Button Color Palette */
.btn-teal {
  background-color: #17a2b8;
  border-color: #17a2b8;
  color: white;
}
.btn-teal:hover {
  background-color: #138496;
  border-color: #117a8b;
  color: white;
}
.btn-outline-teal {
  border-color: #17a2b8;
  color: #17a2b8;
}
.btn-outline-teal:hover {
  background-color: #17a2b8;
  border-color: #17a2b8;
  color: white;
}

.btn-orange {
  background-color: #fd7e14;
  border-color: #fd7e14;
  color: white;
}
.btn-orange:hover {
  background-color: #e8590c;
  border-color: #dc5309;
  color: white;
}
.btn-outline-orange {
  border-color: #fd7e14;
  color: #fd7e14;
}
.btn-outline-orange:hover {
  background-color: #fd7e14;
  border-color: #fd7e14;
  color: white;
}

.btn-pink {
  background-color: #e83e8c;
  border-color: #e83e8c;
  color: white;
}
.btn-pink:hover {
  background-color: #d91a72;
  border-color: #ce176d;
  color: white;
}
.btn-outline-pink {
  border-color: #e83e8c;
  color: #e83e8c;
}
.btn-outline-pink:hover {
  background-color: #e83e8c;
  border-color: #e83e8c;
  color: white;
}

.btn-indigo {
  background-color: #6610f2;
  border-color: #6610f2;
  color: white;
}
.btn-indigo:hover {
  background-color: #560bd0;
  border-color: #510bc4;
  color: white;
}
.btn-outline-indigo {
  border-color: #6610f2;
  color: #6610f2;
}
.btn-outline-indigo:hover {
  background-color: #6610f2;
  border-color: #6610f2;
  color: white;
}

.btn-mint {
  background-color: #20c997;
  border-color: #20c997;
  color: white;
}
.btn-mint:hover {
  background-color: #1ba87e;
  border-color: #199d76;
  color: white;
}
.btn-outline-mint {
  border-color: #20c997;
  color: #20c997;
}
.btn-outline-mint:hover {
  background-color: #20c997;
  border-color: #20c997;
  color: white;
}

.btn-gray {
  background-color: #6c757d;
  border-color: #6c757d;
  color: white;
}
.btn-gray:hover {
  background-color: #5a6268;
  border-color: #545b62;
  color: white;
}
.btn-outline-gray {
  border-color: #6c757d;
  color: #6c757d;
}
.btn-outline-gray:hover {
  background-color: #6c757d;
  border-color: #6c757d;
  color: white;
}
</style>

<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

      <div class="container-fluid mt-4">

        <!-- Action Bar Demo -->
        <div class="card mb-4">
          <div class="card-header bg-dark text-white">
            <strong><i class="fas fa-crown"></i> Action Bar Features</strong>
            <small class="ml-2">Currently active above</small>
          </div>
          <div class="card-body">
            <div class="alert alert-success">
              <strong><i class="fas fa-check-circle"></i> Action Bar Active!</strong>
              Look at the white bar above - you should see:
              <ul class="mb-0 mt-2">
                <li><strong>Page Subtitle:</strong> "Complete UI Component Library"</li>
                <li><strong>Breadcrumbs:</strong> Home > Examples > UI Showcase</li>
                <li><strong>Buttons:</strong> Documentation and GitHub buttons on the right</li>
                <li><strong>Timestamp:</strong> Current date/time on far right</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Color Palette Reference -->
        <div class="card mb-4">
          <div class="card-header bg-gradient-dark text-white">
            <strong><i class="fas fa-swatchbook"></i> Complete Color Palette Reference</strong>
            <small class="ml-2">All available colors at a glance</small>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <h6>Standard Colors</h6>
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #007bff; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Primary</strong> - #007bff <code>.btn-primary</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #6c757d; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Secondary</strong> - #6c757d <code>.btn-secondary</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #28a745; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Success</strong> - #28a745 <code>.btn-success</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #dc3545; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Danger</strong> - #dc3545 <code>.btn-danger</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #ffc107; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Warning</strong> - #ffc107 <code>.btn-warning</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #17a2b8; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Info</strong> - #17a2b8 <code>.btn-info</code></div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <h6>Alternative Colors</h6>
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #17a2b8; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Teal</strong> - #17a2b8 <code>.btn-teal</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #fd7e14; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Orange</strong> - #fd7e14 <code>.btn-orange</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #e83e8c; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Pink</strong> - #e83e8c <code>.btn-pink</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #6610f2; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Indigo</strong> - #6610f2 <code>.btn-indigo</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #20c997; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Mint</strong> - #20c997 <code>.btn-mint</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #6f42c1; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Purple</strong> - #6f42c1 <code>.btn-purple</code></div>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div style="width: 40px; height: 40px; background-color: #8dc63f; border-radius: 4px; margin-right: 10px;"></div>
                    <div><strong>Lime</strong> - #8dc63f <code>.btn-lime</code></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="alert alert-info mt-3">
              <i class="fas fa-info-circle"></i> <strong>Pro Tip:</strong> All colors support <code>.btn-outline-*</code> variants for hollow/ghost button styles.
            </div>
          </div>
        </div>

        <!-- Button Colors Demo -->
        <div class="card mb-4">
          <div class="card-header bg-primary text-white">
            <strong><i class="fas fa-palette"></i> Button Colors</strong>
            <small class="ml-2">All available button styles</small>
          </div>
          <div class="card-body">

            <h5>Standard Buttons</h5>
            <div class="mb-4">
              <button class="btn btn-primary mr-2 mb-2">Primary</button>
              <button class="btn btn-secondary mr-2 mb-2">Secondary</button>
              <button class="btn btn-success mr-2 mb-2">Success</button>
              <button class="btn btn-danger mr-2 mb-2">Danger</button>
              <button class="btn btn-warning mr-2 mb-2">Warning</button>
              <button class="btn btn-info mr-2 mb-2">Info</button>
              <button class="btn btn-light mr-2 mb-2">Light</button>
              <button class="btn btn-dark mr-2 mb-2">Dark</button>
            </div>

            <h5>Custom Brand Buttons</h5>
            <div class="mb-4">
              <button class="btn btn-purple mr-2 mb-2"><i class="fas fa-star"></i> Purple</button>
              <button class="btn btn-lime mr-2 mb-2"><i class="fas fa-leaf"></i> Lime</button>
            </div>

            <h5>Alternative Color Palette</h5>
            <div class="mb-4">
              <button class="btn btn-teal mr-2 mb-2">
                <i class="fas fa-droplet"></i> Teal
              </button>
              <button class="btn btn-orange mr-2 mb-2">
                <i class="fas fa-fire"></i> Orange
              </button>
              <button class="btn btn-pink mr-2 mb-2">
                <i class="fas fa-heart"></i> Pink
              </button>
              <button class="btn btn-indigo mr-2 mb-2">
                <i class="fas fa-gem"></i> Indigo
              </button>
              <button class="btn btn-mint mr-2 mb-2">
                <i class="fas fa-seedling"></i> Mint
              </button>
              <button class="btn btn-gray mr-2 mb-2">
                <i class="fas fa-circle"></i> Gray
              </button>
            </div>

            <h5>Alternative Outline Variants</h5>
            <div class="mb-4">
              <button class="btn btn-outline-teal mr-2 mb-2">Teal</button>
              <button class="btn btn-outline-orange mr-2 mb-2">Orange</button>
              <button class="btn btn-outline-pink mr-2 mb-2">Pink</button>
              <button class="btn btn-outline-indigo mr-2 mb-2">Indigo</button>
              <button class="btn btn-outline-mint mr-2 mb-2">Mint</button>
            </div>

            <h5>Outline Buttons</h5>
            <div class="mb-4">
              <button class="btn btn-outline-primary mr-2 mb-2">Primary</button>
              <button class="btn btn-outline-secondary mr-2 mb-2">Secondary</button>
              <button class="btn btn-outline-success mr-2 mb-2">Success</button>
              <button class="btn btn-outline-danger mr-2 mb-2">Danger</button>
              <button class="btn btn-outline-warning mr-2 mb-2">Warning</button>
              <button class="btn btn-outline-info mr-2 mb-2">Info</button>
            </div>

            <h5>Button Sizes</h5>
            <div class="mb-4">
              <button class="btn btn-primary btn-lg mr-2 mb-2">Large</button>
              <button class="btn btn-primary mr-2 mb-2">Default</button>
              <button class="btn btn-primary btn-sm mr-2 mb-2">Small</button>
            </div>

            <h5>Alternative Colors in Action</h5>
            <div class="mb-4">
              <button class="btn btn-teal btn-sm mr-2 mb-2">
                <i class="fas fa-sync"></i> Refresh
              </button>
              <button class="btn btn-orange btn-sm mr-2 mb-2">
                <i class="fas fa-exclamation-circle"></i> Alert
              </button>
              <button class="btn btn-pink btn-sm mr-2 mb-2">
                <i class="fas fa-heart"></i> Favorite
              </button>
              <button class="btn btn-indigo btn-sm mr-2 mb-2">
                <i class="fas fa-crown"></i> Premium
              </button>
              <button class="btn btn-mint btn-sm mr-2 mb-2">
                <i class="fas fa-check-double"></i> Verified
              </button>
            </div>

            <h5>Buttons with Icons</h5>
            <div class="mb-4">
              <button class="btn btn-success mr-2 mb-2"><i class="fas fa-plus"></i> Create</button>
              <button class="btn btn-primary mr-2 mb-2"><i class="fas fa-edit"></i> Edit</button>
              <button class="btn btn-danger mr-2 mb-2"><i class="fas fa-trash"></i> Delete</button>
              <button class="btn btn-secondary mr-2 mb-2"><i class="fas fa-download"></i> Export</button>
              <button class="btn btn-info mr-2 mb-2"><i class="fas fa-cog"></i> Settings</button>
            </div>

            <h5>Button Groups</h5>
            <div class="mb-4">
              <div class="btn-group" role="group">
                <button class="btn btn-primary"><i class="fas fa-align-left"></i></button>
                <button class="btn btn-primary"><i class="fas fa-align-center"></i></button>
                <button class="btn btn-primary"><i class="fas fa-align-right"></i></button>
              </div>
            </div>

            <h5>Block Buttons</h5>
            <div class="mb-4">
              <button class="btn btn-primary btn-block mb-2">Block Level Button</button>
              <button class="btn btn-success btn-block">Full Width Success</button>
            </div>

          </div>
        </div>

        <!-- Toast Notifications Demo -->
        <div class="card mb-4">
          <div class="card-header bg-success text-white">
            <strong><i class="fas fa-bell"></i> Toast Notifications</strong>
            <small class="ml-2">Professional notification system</small>
          </div>
          <div class="card-body">
            <p>Click buttons below to see different toast notification styles:</p>

            <button class="btn btn-success mr-2 mb-2" onclick="showToast('success')">
              <i class="fas fa-check-circle"></i> Success Toast
            </button>
            <button class="btn btn-danger mr-2 mb-2" onclick="showToast('error')">
              <i class="fas fa-times-circle"></i> Error Toast
            </button>
            <button class="btn btn-warning mr-2 mb-2" onclick="showToast('warning')">
              <i class="fas fa-exclamation-triangle"></i> Warning Toast
            </button>
            <button class="btn btn-info mr-2 mb-2" onclick="showToast('info')">
              <i class="fas fa-info-circle"></i> Info Toast
            </button>

            <hr>
            <h6>Toast with Details</h6>
            <button class="btn btn-primary" onclick="showDetailedToast()">
              <i class="fas fa-clipboard-list"></i> Show Detailed Toast
            </button>
          </div>
        </div>

        <!-- Modal Dialogs Demo -->
        <div class="card mb-4">
          <div class="card-header bg-info text-white">
            <strong><i class="fas fa-window-maximize"></i> Modal Dialogs</strong>
            <small class="ml-2">Standard Bootstrap modals</small>
          </div>
          <div class="card-body">
            <p>Click buttons below to open different modal dialog styles:</p>

            <button class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#simpleModal">
              <i class="fas fa-window-restore"></i> Simple Modal
            </button>
            <button class="btn btn-success mr-2 mb-2" data-toggle="modal" data-target="#formModal">
              <i class="fas fa-edit"></i> Form Modal
            </button>
            <button class="btn btn-danger mr-2 mb-2" data-toggle="modal" data-target="#confirmModal">
              <i class="fas fa-exclamation-triangle"></i> Confirm Dialog
            </button>
            <button class="btn btn-info mr-2 mb-2" data-toggle="modal" data-target="#largeModal">
              <i class="fas fa-expand"></i> Large Modal
            </button>
          </div>
        </div>

        <!-- Alert Boxes Demo -->
        <div class="card mb-4">
          <div class="card-header bg-warning text-dark">
            <strong><i class="fas fa-exclamation-circle"></i> Alert Messages</strong>
            <small class="ml-2">Static alert components</small>
          </div>
          <div class="card-body">

            <div class="alert alert-success" role="alert">
              <strong><i class="fas fa-check-circle"></i> Success!</strong> This is a success alert—check it out!
            </div>

            <div class="alert alert-danger" role="alert">
              <strong><i class="fas fa-times-circle"></i> Error!</strong> This is a danger alert—something went wrong!
            </div>

            <div class="alert alert-warning" role="alert">
              <strong><i class="fas fa-exclamation-triangle"></i> Warning!</strong> This is a warning alert—proceed with caution!
            </div>

            <div class="alert alert-info" role="alert">
              <strong><i class="fas fa-info-circle"></i> Info!</strong> This is an info alert—here's some information!
            </div>

            <div class="alert alert-primary" role="alert">
              <strong><i class="fas fa-star"></i> Primary!</strong> This is a primary alert—primary action needed!
            </div>

            <div class="alert alert-secondary" role="alert">
              <strong><i class="fas fa-book"></i> Secondary!</strong> This is a secondary alert—additional information!
            </div>

            <h6 class="mt-4">Dismissible Alerts</h6>

            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <strong>Success!</strong> You can dismiss this alert by clicking the × button.
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="alert alert-info alert-dismissible fade show" role="alert">
              <strong>Pro Tip:</strong> All dismissible alerts work the same way across the system!
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

          </div>
        </div>

        <!-- Cards Demo -->
        <div class="card mb-4">
          <div class="card-header bg-secondary text-white">
            <strong><i class="fas fa-th-large"></i> Card Components</strong>
            <small class="ml-2">Content containers</small>
          </div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-4 mb-3">
                <div class="card border-primary">
                  <div class="card-header bg-primary text-white">
                    <strong>Primary Card</strong>
                  </div>
                  <div class="card-body">
                    <p class="card-text">This is a primary styled card with header.</p>
                    <button class="btn btn-primary btn-sm">Action</button>
                  </div>
                </div>
              </div>

              <div class="col-md-4 mb-3">
                <div class="card border-success">
                  <div class="card-header bg-success text-white">
                    <strong>Success Card</strong>
                  </div>
                  <div class="card-body">
                    <p class="card-text">This is a success styled card with header.</p>
                    <button class="btn btn-success btn-sm">Action</button>
                  </div>
                </div>
              </div>

              <div class="col-md-4 mb-3">
                <div class="card border-danger">
                  <div class="card-header bg-danger text-white">
                    <strong>Danger Card</strong>
                  </div>
                  <div class="card-body">
                    <p class="card-text">This is a danger styled card with header.</p>
                    <button class="btn btn-danger btn-sm">Action</button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Badges Demo -->
        <div class="card mb-4">
          <div class="card-header bg-dark text-white">
            <strong><i class="fas fa-tag"></i> Badges & Labels</strong>
            <small class="ml-2">Status indicators</small>
          </div>
          <div class="card-body">

            <h6>Standard Badges</h6>
            <p>
              <span class="badge badge-primary mr-1">Primary</span>
              <span class="badge badge-secondary mr-1">Secondary</span>
              <span class="badge badge-success mr-1">Success</span>
              <span class="badge badge-danger mr-1">Danger</span>
              <span class="badge badge-warning mr-1">Warning</span>
              <span class="badge badge-info mr-1">Info</span>
              <span class="badge badge-light text-dark mr-1">Light</span>
              <span class="badge badge-dark mr-1">Dark</span>
            </p>

            <h6>Alternative Color Badges</h6>
            <p>
              <span class="badge mr-1" style="background-color: #17a2b8;">Teal</span>
              <span class="badge mr-1" style="background-color: #fd7e14;">Orange</span>
              <span class="badge mr-1" style="background-color: #e83e8c;">Pink</span>
              <span class="badge mr-1" style="background-color: #6610f2;">Indigo</span>
              <span class="badge mr-1" style="background-color: #20c997;">Mint</span>
              <span class="badge mr-1" style="background-color: #6c757d;">Gray</span>
            </p>

            <h6 class="mt-3">Pill Badges</h6>
            <p>
              <span class="badge badge-pill badge-primary mr-1">Primary</span>
              <span class="badge badge-pill badge-secondary mr-1">Secondary</span>
              <span class="badge badge-pill badge-success mr-1">Success</span>
              <span class="badge badge-pill badge-danger mr-1">Danger</span>
              <span class="badge badge-pill badge-warning mr-1">Warning</span>
              <span class="badge badge-pill badge-info mr-1">Info</span>
            </p>

            <h6 class="mt-3">Badges in Context</h6>
            <p>
              <button class="btn btn-primary">
                Notifications <span class="badge badge-light">42</span>
              </button>
              <button class="btn btn-success ml-2">
                Messages <span class="badge badge-light">7</span>
              </button>
              <button class="btn btn-danger ml-2">
                Errors <span class="badge badge-light">3</span>
              </button>
            </p>

          </div>
        </div>

        <!-- Progress Bars Demo -->
        <div class="card mb-4">
          <div class="card-header bg-primary text-white">
            <strong><i class="fas fa-tasks"></i> Progress Indicators</strong>
            <small class="ml-2">Loading and progress states</small>
          </div>
          <div class="card-body">

            <h6>Standard Progress</h6>
            <div class="progress mb-3">
              <div class="progress-bar" role="progressbar" style="width: 25%">25%</div>
            </div>

            <h6>Colored Progress</h6>
            <div class="progress mb-2">
              <div class="progress-bar bg-success" style="width: 40%">40%</div>
            </div>
            <div class="progress mb-2">
              <div class="progress-bar bg-info" style="width: 60%">60%</div>
            </div>
            <div class="progress mb-2">
              <div class="progress-bar bg-warning" style="width: 80%">80%</div>
            </div>
            <div class="progress mb-3">
              <div class="progress-bar bg-danger" style="width: 100%">100%</div>
            </div>

            <h6>Striped & Animated</h6>
            <div class="progress mb-2">
              <div class="progress-bar progress-bar-striped" style="width: 50%">50%</div>
            </div>
            <div class="progress mb-3">
              <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%">Loading...</div>
            </div>

          </div>
        </div>

        <!-- Code Examples -->
        <div class="card mb-4">
          <div class="card-header bg-secondary text-white">
            <strong><i class="fas fa-code"></i> Quick Copy Examples</strong>
            <small class="ml-2">Ready to use code snippets</small>
          </div>
          <div class="card-body">

            <h6>Toast Notification</h6>
            <pre><code>CIS.ErrorHandler.toast('Success!', 'success', 'Operation completed successfully');</code></pre>

            <h6>Action Bar Setup</h6>
            <pre><code>$theme->setPageSubtitle('My Page Title');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('My Section');
$theme->addHeaderButton('New Item', '/create', 'primary', 'fas fa-plus');
$theme->showTimestamps(true);</code></pre>

            <h6>Alert Box</h6>
            <pre><code>&lt;div class="alert alert-success" role="alert"&gt;
  &lt;strong&gt;Success!&lt;/strong&gt; Your message here.
&lt;/div&gt;</code></pre>

            <h6>Alternative Color Buttons</h6>
            <pre><code>&lt;!-- Solid buttons --&gt;
&lt;button class="btn btn-teal"&gt;Teal Button&lt;/button&gt;
&lt;button class="btn btn-orange"&gt;Orange Button&lt;/button&gt;
&lt;button class="btn btn-pink"&gt;Pink Button&lt;/button&gt;
&lt;button class="btn btn-indigo"&gt;Indigo Button&lt;/button&gt;
&lt;button class="btn btn-mint"&gt;Mint Button&lt;/button&gt;
&lt;button class="btn btn-gray"&gt;Gray Button&lt;/button&gt;

&lt;!-- Outline variants --&gt;
&lt;button class="btn btn-outline-teal"&gt;Teal Outline&lt;/button&gt;
&lt;button class="btn btn-outline-orange"&gt;Orange Outline&lt;/button&gt;
&lt;button class="btn btn-outline-pink"&gt;Pink Outline&lt;/button&gt;</code></pre>

          </div>
        </div>

      </div>

  <!-- Modal Dialogs -->

  <!-- Simple Modal -->
  <div class="modal fade" id="simpleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-window-restore"></i> Simple Modal</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>This is a simple modal dialog with standard content.</p>
          <p>You can put any content here - text, forms, images, etc.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Form Modal -->
  <div class="modal fade" id="formModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-edit"></i> Form Modal</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form>
            <div class="form-group">
              <label>Name</label>
              <input type="text" class="form-control" placeholder="Enter name">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" class="form-control" placeholder="Enter email">
            </div>
            <div class="form-group">
              <label>Message</label>
              <textarea class="form-control" rows="3" placeholder="Enter message"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success">Submit</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirm Modal -->
  <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Action</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p><strong>Are you sure you want to delete this item?</strong></p>
          <p>This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Large Modal -->
  <div class="modal fade" id="largeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title"><i class="fas fa-expand"></i> Large Modal</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <h5>This is a large modal</h5>
          <p>Perfect for displaying more content, tables, or complex forms.</p>
          <table class="table table-sm">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>1</td>
                <td>Item One</td>
                <td><span class="badge badge-success">Active</span></td>
                <td><button class="btn btn-sm btn-primary">Edit</button></td>
              </tr>
              <tr>
                <td>2</td>
                <td>Item Two</td>
                <td><span class="badge badge-warning">Pending</span></td>
                <td><button class="btn btn-sm btn-primary">Edit</button></td>
              </tr>
              <tr>
                <td>3</td>
                <td>Item Three</td>
                <td><span class="badge badge-danger">Inactive</span></td>
                <td><button class="btn btn-sm btn-primary">Edit</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Demo Scripts -->
  <script>
  function showToast(type) {
    const messages = {
      success: {
        title: 'Success!',
        message: 'Operation completed successfully',
        details: 'Everything is working as expected'
      },
      error: {
        title: 'Error!',
        message: 'Something went wrong',
        details: 'Please check your input and try again'
      },
      warning: {
        title: 'Warning!',
        message: 'Please review this action',
        details: 'This might have unintended consequences'
      },
      info: {
        title: 'Information',
        message: 'Here is some helpful information',
        details: 'This notification is for your reference'
      }
    };

    const data = messages[type];

    if (typeof CIS !== 'undefined' && CIS.ErrorHandler) {
      CIS.ErrorHandler.toast(data.title, type, data.message);
    } else {
      alert(data.title + '\n' + data.message);
    }
  }

  function showDetailedToast() {
    if (typeof CIS !== 'undefined' && CIS.ErrorHandler) {
      CIS.ErrorHandler.toast(
        'Detailed Information',
        'info',
        'This toast includes additional context and details that can help users understand what happened.'
      );
    } else {
      alert('Detailed toast notification');
    }
  }
  </script>

    </main>
  </div>

  <?php $theme->render('footer'); ?>
