<?php
/**
 * CIS Admin UI - Enhanced Main Dashboard
 * Integrated theme system, version tracking, AI configuration, and feature showcase
 *
 * @package CIS\Modules\AdminUI
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Get theme preference
$theme = $_GET['theme'] ?? $_SESSION['admin_theme'] ?? 'vscode-dark';
$_SESSION['admin_theme'] = $theme;

// Ensure session and CSRF token
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CIS Admin Panel v<?php echo ADMIN_UI_VERSION; ?> - <?php echo htmlspecialchars(getTheme($theme)['name'] ?? $theme); ?></title>

    <!-- Bootstrap CSS (for grid and components) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/bootstrap.min.css" integrity="sha512-rt/SrQ4UNIaGfDyEXZtNcgyVt0/wOILiqWVAdQp/sqLpVzOJE8CIrGT8z3dAT5rPe86qXCzwaQdc3ggcgW8sha==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVJkEZSMUkrQ6usKu8zTSf2oj7kiy0mo50g5A3hLUp/0JA44l+NnRrKjPVke+o8xehpqpKWLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Professional Admin UI Styles -->
    <link rel="stylesheet" href="/modules/admin-ui/css/admin-ui-styles.css">
    <link rel="stylesheet" href="/modules/admin-ui/_templates/css/theme-custom.css">

    <!-- Dark Theme Override for Admin Dashboard -->
    <style>
        body {
            background-color: #1e1e1e !important;
            color: #d4d4d4 !important;
        }
        .showcase-header {
            background: linear-gradient(135deg, #1e1e1e 0%, #252526 100%) !important;
            border-bottom: 2px solid #007acc;
        }
        .showcase-section {
            background: #252526 !important;
            border: 1px solid #3e3e42 !important;
            color: #d4d4d4 !important;
        }
        .section-title {
            color: #d4d4d4 !important;
            border-bottom-color: #3e3e42 !important;
        }
        .component-label {
            color: #858585 !important;
        }
        .code-block {
            background: #1f2937 !important;
            color: #10b981 !important;
            border: 1px solid #3e3e42 !important;
        }
        .nav-pills .nav-link {
            color: #d4d4d4 !important;
        }
        .nav-pills .nav-link.active {
            background-color: #007acc !important;
        }
        .btn-primary {
            background-color: #007acc !important;
            border-color: #007acc !important;
        }
        .btn-primary:hover {
            background-color: #0099cc !important;
            border-color: #0099cc !important;
        }
        table {
            color: #d4d4d4 !important;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            background-color: #1f2937 !important;
            color: #d4d4d4 !important;
            border: 1px solid #3e3e42 !important;
        }
    </style>

    <!-- Meta & Favicon -->
    <meta name="theme-color" content="#1e1e1e">
    <meta name="description" content="CIS Admin Panel - Manage themes, AI agents, and system configuration">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75'>⚙️</text></svg>">
</head>
<body>

    <!-- Main Content (No Template Wrapping) -->
    <div class="dashboard-main">

        <div class="showcase-header">
            <div class="container-fluid">
                <h1 class="display-4 mb-3"><i class="fas fa-palette"></i> CIS Admin UI Component Showcase</h1>
                <p class="lead mb-0">Complete reference for all UI components, styles, and patterns</p>
                <div class="mt-4">
                    <a href="template-showcase.php" class="btn btn-warning btn-lg mr-2"><i class="fas fa-layer-group"></i> 🔥 Template Showcase (NEW!)</a>
                    <a href="theme-builder.php" class="btn btn-light btn-lg mr-2"><i class="fas fa-paint-brush"></i> Theme Builder</a>
                    <a href="#documentation" class="btn btn-outline-light btn-lg"><i class="fas fa-book"></i> Documentation</a>
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                    <button id="aiAgentBtn" class="btn btn-dark btn-lg ml-2"><i class="fas fa-robot"></i> AI Agent</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="container-fluid">

            <!-- Navigation Tabs -->
            <ul class="nav nav-pills nav-pills-custom mb-4">
                <li class="nav-item"><a class="nav-link active" href="#buttons" data-toggle="pill">Buttons</a></li>
                <li class="nav-item"><a class="nav-link" href="#forms" data-toggle="pill">Forms</a></li>
                <li class="nav-item"><a class="nav-link" href="#tables" data-toggle="pill">Tables</a></li>
                <li class="nav-item"><a class="nav-link" href="#cards" data-toggle="pill">Cards</a></li>
                <li class="nav-item"><a class="nav-link" href="#alerts" data-toggle="pill">Alerts</a></li>
                <li class="nav-item"><a class="nav-link" href="#modals" data-toggle="pill">Modals</a></li>
                <li class="nav-item"><a class="nav-link" href="#layout" data-toggle="pill">Layout</a></li>
            </ul>

            <div class="tab-content">

                <!-- BUTTONS TAB -->
                <div class="tab-pane fade show active" id="buttons">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-hand-pointer"></i> Buttons</div>

                        <div class="component-group">
                            <div class="component-label">Standard Buttons</div>
                            <button class="btn btn-primary mr-2 mb-2"><i class="fas fa-check"></i> Primary</button>
                            <button class="btn btn-secondary mr-2 mb-2">Secondary</button>
                            <button class="btn btn-success mr-2 mb-2"><i class="fas fa-check-circle"></i> Success</button>
                            <button class="btn btn-warning mr-2 mb-2"><i class="fas fa-exclamation-triangle"></i> Warning</button>
                            <button class="btn btn-danger mr-2 mb-2"><i class="fas fa-times"></i> Danger</button>
                            <button class="btn btn-info mr-2 mb-2"><i class="fas fa-info-circle"></i> Info</button>
                            <button class="btn btn-light mr-2 mb-2">Light</button>
                            <button class="btn btn-dark mr-2 mb-2">Dark</button>
                            <div class="code-block">&lt;button class="btn btn-primary"&gt;&lt;i class="fas fa-check"&gt;&lt;/i&gt; Primary&lt;/button&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Button Sizes</div>
                            <button class="btn btn-primary btn-lg mr-2 mb-2">Large Button</button>
                            <button class="btn btn-primary mr-2 mb-2">Default Button</button>
                            <button class="btn btn-primary btn-sm mr-2 mb-2">Small Button</button>
                            <div class="code-block">&lt;button class="btn btn-primary btn-lg"&gt;Large&lt;/button&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Outline Buttons</div>
                            <button class="btn btn-outline-primary mr-2 mb-2">Primary</button>
                            <button class="btn btn-outline-success mr-2 mb-2">Success</button>
                            <button class="btn btn-outline-warning mr-2 mb-2">Warning</button>
                            <button class="btn btn-outline-danger mr-2 mb-2">Danger</button>
                            <button class="btn btn-outline-info mr-2 mb-2">Info</button>
                            <div class="code-block">&lt;button class="btn btn-outline-primary"&gt;Primary&lt;/button&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Button Groups</div>
                            <div class="btn-group mr-2 mb-2" role="group">
                                <button class="btn btn-primary">Left</button>
                                <button class="btn btn-primary">Middle</button>
                                <button class="btn btn-primary">Right</button>
                            </div>
                            <div class="btn-group mb-2" role="group">
                                <button class="btn btn-outline-secondary"><i class="fas fa-align-left"></i></button>
                                <button class="btn btn-outline-secondary"><i class="fas fa-align-center"></i></button>
                                <button class="btn btn-outline-secondary"><i class="fas fa-align-right"></i></button>
                            </div>
                            <div class="code-block">&lt;div class="btn-group"&gt;&lt;button class="btn btn-primary"&gt;Left&lt;/button&gt;...&lt;/div&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Icon Buttons</div>
                            <button class="btn btn-primary btn-icon mr-2 mb-2"><i class="fas fa-heart"></i></button>
                            <button class="btn btn-success btn-icon mr-2 mb-2"><i class="fas fa-download"></i></button>
                            <button class="btn btn-danger btn-icon mr-2 mb-2"><i class="fas fa-trash"></i></button>
                            <button class="btn btn-info btn-icon mr-2 mb-2"><i class="fas fa-search"></i></button>
                            <div class="code-block">&lt;button class="btn btn-primary btn-icon"&gt;&lt;i class="fas fa-heart"&gt;&lt;/i&gt;&lt;/button&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Loading Buttons</div>
                            <button class="btn btn-primary mr-2 mb-2" disabled>
                                <span class="spinner-border spinner-border-sm mr-2"></span>Loading...
                            </button>
                            <button class="btn btn-success mr-2 mb-2" disabled>
                                <span class="spinner-grow spinner-grow-sm mr-2"></span>Processing...
                            </button>
                            <div class="code-block">&lt;button class="btn btn-primary" disabled&gt;&lt;span class="spinner-border spinner-border-sm"&gt;&lt;/span&gt; Loading...&lt;/button&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- FORMS TAB -->
                <div class="tab-pane fade" id="forms">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-edit"></i> Form Elements</div>

                        <div class="component-group">
                            <div class="component-label">Text Inputs</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Standard Input</label>
                                        <input type="text" class="form-control" placeholder="Enter text...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Input with Icon</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            </div>
                                            <input type="text" class="form-control" placeholder="Username">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="code-block">&lt;input type="text" class="form-control" placeholder="Enter text..."&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Select Dropdowns</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Standard Select</label>
                                        <select class="form-control">
                                            <option>Option 1</option>
                                            <option>Option 2</option>
                                            <option>Option 3</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Multiple Select</label>
                                        <select class="form-control" multiple>
                                            <option>Option 1</option>
                                            <option>Option 2</option>
                                            <option>Option 3</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="code-block">&lt;select class="form-control"&gt;&lt;option&gt;Option 1&lt;/option&gt;&lt;/select&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Checkboxes & Radio Buttons</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="check1" checked>
                                        <label class="form-check-label" for="check1">Checkbox checked</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="check2">
                                        <label class="form-check-label" for="check2">Checkbox unchecked</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="check3" disabled>
                                        <label class="form-check-label" for="check3">Checkbox disabled</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="radio" id="radio1" checked>
                                        <label class="form-check-label" for="radio1">Radio selected</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="radio" id="radio2">
                                        <label class="form-check-label" for="radio2">Radio unselected</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="radio2" id="radio3" disabled>
                                        <label class="form-check-label" for="radio3">Radio disabled</label>
                                    </div>
                                </div>
                            </div>
                            <div class="code-block">&lt;div class="form-check"&gt;&lt;input class="form-check-input" type="checkbox"&gt;&lt;label class="form-check-label"&gt;Label&lt;/label&gt;&lt;/div&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Text Area</div>
                            <textarea class="form-control" rows="4" placeholder="Enter multiple lines of text..."></textarea>
                            <div class="code-block">&lt;textarea class="form-control" rows="4"&gt;&lt;/textarea&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Form Validation</div>
                            <div class="row">
                                <div class="col-md-4">
                                    <input type="text" class="form-control is-valid" value="Valid input">
                                    <div class="valid-feedback">Looks good!</div>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control is-invalid" value="Invalid input">
                                    <div class="invalid-feedback">Please fix this.</div>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" placeholder="Normal input">
                                </div>
                            </div>
                            <div class="code-block">&lt;input class="form-control is-valid"&gt;&lt;div class="valid-feedback"&gt;Looks good!&lt;/div&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- TABLES TAB -->
                <div class="tab-pane fade" id="tables">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-table"></i> Tables</div>

                        <div class="component-group">
                            <div class="component-label">Standard Table</div>
                            <table class="table">
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
                                        <td><span class="badge badge-success">Active</span></td>
                                        <td><button class="btn btn-sm btn-primary">Edit</button></td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td>Jane Smith</td>
                                        <td><span class="badge badge-warning">Pending</span></td>
                                        <td><button class="btn btn-sm btn-primary">Edit</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="code-block">&lt;table class="table"&gt;&lt;thead&gt;&lt;tr&gt;&lt;th&gt;...&lt;/th&gt;&lt;/tr&gt;&lt;/thead&gt;&lt;/table&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Striped & Hover Table</div>
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#1001</td>
                                        <td>Alice Johnson</td>
                                        <td>$149.99</td>
                                        <td><span class="badge badge-success">Completed</span></td>
                                    </tr>
                                    <tr>
                                        <td>#1002</td>
                                        <td>Bob Wilson</td>
                                        <td>$89.50</td>
                                        <td><span class="badge badge-warning">Processing</span></td>
                                    </tr>
                                    <tr>
                                        <td>#1003</td>
                                        <td>Carol Martinez</td>
                                        <td>$249.99</td>
                                        <td><span class="badge badge-danger">Cancelled</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="code-block">&lt;table class="table table-striped table-hover"&gt;...&lt;/table&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Responsive Table</div>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Column 1</th>
                                            <th>Column 2</th>
                                            <th>Column 3</th>
                                            <th>Column 4</th>
                                            <th>Column 5</th>
                                            <th>Column 6</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Data 1</td>
                                            <td>Data 2</td>
                                            <td>Data 3</td>
                                            <td>Data 4</td>
                                            <td>Data 5</td>
                                            <td>Data 6</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="code-block">&lt;div class="table-responsive"&gt;&lt;table class="table table-bordered"&gt;...&lt;/table&gt;&lt;/div&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- CARDS TAB -->
                <div class="tab-pane fade" id="cards">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-clone"></i> Cards</div>

                        <div class="component-group">
                            <div class="component-label">Basic Cards</div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Card Title</h5>
                                            <p class="card-text">Some quick example text to build on the card title.</p>
                                            <button class="btn btn-primary">Action</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">Featured</div>
                                        <div class="card-body">
                                            <h5 class="card-title">With Header</h5>
                                            <p class="card-text">Card with a header section.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">With Footer</h5>
                                            <p class="card-text">Card with a footer section.</p>
                                        </div>
                                        <div class="card-footer text-muted">2 days ago</div>
                                    </div>
                                </div>
                            </div>
                            <div class="code-block">&lt;div class="card"&gt;&lt;div class="card-body"&gt;&lt;h5 class="card-title"&gt;Title&lt;/h5&gt;&lt;/div&gt;&lt;/div&gt;</div>
                        </div>

                        <div class="component-group mt-4">
                            <div class="component-label">Colored Cards</div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card text-white bg-primary">
                                        <div class="card-body">
                                            <h6 class="card-title">Primary</h6>
                                            <p class="card-text mb-0">Primary colored card</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card text-white bg-success">
                                        <div class="card-body">
                                            <h6 class="card-title">Success</h6>
                                            <p class="card-text mb-0">Success colored card</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card text-white bg-warning">
                                        <div class="card-body">
                                            <h6 class="card-title">Warning</h6>
                                            <p class="card-text mb-0">Warning colored card</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card text-white bg-danger">
                                        <div class="card-body">
                                            <h6 class="card-title">Danger</h6>
                                            <p class="card-text mb-0">Danger colored card</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="code-block">&lt;div class="card text-white bg-primary"&gt;&lt;div class="card-body"&gt;...&lt;/div&gt;&lt;/div&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- ALERTS TAB -->
                <div class="tab-pane fade" id="alerts">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-exclamation-circle"></i> Alerts & Badges</div>

                        <div class="component-group">
                            <div class="component-label">Alerts</div>
                            <div class="alert alert-primary" role="alert">
                                <i class="fas fa-info-circle"></i> This is a primary alert—check it out!
                            </div>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> This is a success alert—check it out!
                            </div>
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> This is a warning alert—check it out!
                            </div>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-times-circle"></i> This is a danger alert—check it out!
                            </div>
                            <div class="code-block">&lt;div class="alert alert-success"&gt;&lt;i class="fas fa-check-circle"&gt;&lt;/i&gt; Success message&lt;/div&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Dismissible Alerts</div>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>Well done!</strong> You successfully read this important alert message.
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="code-block">&lt;div class="alert alert-success alert-dismissible fade show"&gt;...&lt;button class="close" data-dismiss="alert"&gt;&times;&lt;/button&gt;&lt;/div&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Badges</div>
                            <h5>
                                <span class="badge badge-primary mr-2">Primary</span>
                                <span class="badge badge-secondary mr-2">Secondary</span>
                                <span class="badge badge-success mr-2">Success</span>
                                <span class="badge badge-warning mr-2">Warning</span>
                                <span class="badge badge-danger mr-2">Danger</span>
                                <span class="badge badge-info mr-2">Info</span>
                            </h5>
                            <div class="code-block">&lt;span class="badge badge-primary"&gt;Primary&lt;/span&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Pill Badges</div>
                            <h5>
                                <span class="badge badge-pill badge-primary mr-2">Primary</span>
                                <span class="badge badge-pill badge-success mr-2">Success</span>
                                <span class="badge badge-pill badge-warning mr-2">Warning</span>
                                <span class="badge badge-pill badge-danger mr-2">Danger</span>
                            </h5>
                            <div class="code-block">&lt;span class="badge badge-pill badge-primary"&gt;Primary&lt;/span&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- MODALS TAB -->
                <div class="tab-pane fade" id="modals">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-window-maximize"></i> Modals & Dropdowns</div>

                        <div class="component-group">
                            <div class="component-label">Modal Triggers</div>
                            <button class="btn btn-primary mr-2" data-toggle="modal" data-target="#exampleModal">Launch Modal</button>
                            <button class="btn btn-success mr-2" data-toggle="modal" data-target="#largeModal">Large Modal</button>
                            <button class="btn btn-warning" data-toggle="modal" data-target="#smallModal">Small Modal</button>
                            <div class="code-block">&lt;button data-toggle="modal" data-target="#exampleModal"&gt;Launch Modal&lt;/button&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Dropdowns</div>
                            <div class="dropdown d-inline-block mr-2">
                                <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                    Dropdown button
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#">Action</a>
                                    <a class="dropdown-item" href="#">Another action</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">Separated link</a>
                                </div>
                            </div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                                    Split button
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#">Action</a>
                                    <a class="dropdown-item" href="#">Another action</a>
                                </div>
                            </div>
                            <div class="code-block">&lt;div class="dropdown"&gt;&lt;button class="btn dropdown-toggle" data-toggle="dropdown"&gt;...&lt;/button&gt;&lt;div class="dropdown-menu"&gt;...&lt;/div&gt;&lt;/div&gt;</div>
                        </div>
                    </div>
                </div>

                <!-- LAYOUT TAB -->
                <div class="tab-pane fade" id="layout">
                    <div class="showcase-section">
                        <div class="section-title"><i class="fas fa-th-large"></i> Layout & Grid</div>

                        <div class="component-group">
                            <div class="component-label">Grid System</div>
                            <div class="row mb-3">
                                <div class="col-md-4"><div class="p-3 bg-primary text-white text-center">col-md-4</div></div>
                                <div class="col-md-4"><div class="p-3 bg-success text-white text-center">col-md-4</div></div>
                                <div class="col-md-4"><div class="p-3 bg-warning text-white text-center">col-md-4</div></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6"><div class="p-3 bg-info text-white text-center">col-md-6</div></div>
                                <div class="col-md-6"><div class="p-3 bg-danger text-white text-center">col-md-6</div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-12"><div class="p-3 bg-dark text-white text-center">col-md-12</div></div>
                            </div>
                            <div class="code-block">&lt;div class="row"&gt;&lt;div class="col-md-4"&gt;...&lt;/div&gt;&lt;/div&gt;</div>
                        </div>

                        <div class="component-group">
                            <div class="component-label">Spacing Utilities</div>
                            <div class="bg-light p-3 mb-2">Padding: p-3</div>
                            <div class="bg-light m-3">Margin: m-3</div>
                            <div class="bg-light mt-4 mb-4">Margin Top & Bottom: mt-4 mb-4</div>
                            <div class="code-block">&lt;div class="p-3"&gt;Padding&lt;/div&gt; &lt;div class="m-3"&gt;Margin&lt;/div&gt;</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Example Modals -->
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modal Title</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>This is a standard modal dialog.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="largeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Large Modal</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>This is a large modal dialog.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="smallModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Small Modal</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>This is a small modal.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
        <?php if (!empty($_SESSION['is_admin'])): ?>
        <!-- AI Agent Modal -->
        <div class="modal fade" id="aiAgentModal" tabindex="-1" role="dialog" aria-labelledby="aiAgentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="aiAgentModalLabel">AI Agent - Template Edit</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Template Path (relative to webroot)</label>
                            <input id="ai_template_path" class="form-control" placeholder="modules/some-module/views/template.php">
                        </div>
                        <div class="form-group">
                            <label>Instructions (what to change)</label>
                            <textarea id="ai_instructions" class="form-control" rows="4" placeholder="Describe the edit you want"></textarea>
                        </div>
                        <div id="ai_preview" style="max-height:300px;overflow:auto;background:#f8f9fa;padding:10px;border-radius:6px;display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="aiPreviewBtn" class="btn btn-secondary">Preview</button>
                        <button type="button" id="aiApplyBtn" class="btn btn-primary">Apply (Create Backup)</button>
                        <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(function(){
                $('#aiAgentBtn').on('click', function(){
                        $('#aiAgentModal').modal('show');
                        $('#ai_preview').hide().text('');
                });

                function callAi(action, onSuccess, onError){
                        const payload = {
                                action: action,
                                template_path: $('#ai_template_path').val(),
                                instructions: $('#ai_instructions').val(),
                                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        };

                        $.ajax({
                                url: '/modules/admin-ui/api/ai-agent.php',
                                method: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify(payload),
                                success: function(resp){ if(onSuccess) onSuccess(resp); },
                                error: function(xhr){ if(onError) onError(xhr); }
                        });
                }

                $('#aiPreviewBtn').on('click', function(){
                        callAi('preview', function(resp){
                                if(resp.success){
                                        $('#ai_preview').show().text(resp.preview);
                                } else {
                                        alert('Preview error: ' + (resp.error||'unknown'));
                                }
                        }, function(){ alert('Preview failed'); });
                });

                $('#aiApplyBtn').on('click', function(){
                        if(!confirm('This will create a backup and apply the edit. Are you sure?')) return;
                        callAi('apply', function(resp){
                                if(resp.success){
                                        alert('Applied. Backup: ' + (resp.backup||'none'));
                                        location.reload();
                                } else {
                                        alert('Apply error: ' + (resp.error||'unknown'));
                                }
                        }, function(){ alert('Apply failed'); });
                });
        });
        </script>
        <?php endif; ?>

    <!-- jQuery for Bootstrap compatibility -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVeS0HstYNMCa2jsSMsQ0FAvQkS5L+g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/bootstrap.bundle.min.js" integrity="sha512-kBY+YSgNG3rfPYa50Wrcrbirfc3D0GaKRWaX9SEzYpe3xJ5NMnSiHQAM3BAM8elutG264+ku2ZwXvOMHXjPMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Admin UI JavaScript -->
    <script src="/modules/admin-ui/js/theme-switcher.js"></script>
    <script src="/modules/admin-ui/js/ai-config-panel.js"></script>
    <script src="/modules/admin-ui/js/main-ui.js"></script>

</body>
</html>
