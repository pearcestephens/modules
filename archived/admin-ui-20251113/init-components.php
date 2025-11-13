<?php
/**
 * Component Catalog Initializer
 *
 * Seeds the component library with common UI elements
 * Run once to populate initial components
 */

$components = [
    [
        'id' => 'comp_primary_button',
        'name' => 'Primary Button',
        'category' => 'Buttons',
        'description' => 'Main call-to-action button with gradient',
        'html' => '<button class="btn btn-primary"><i class="fas fa-check"></i> Save Changes</button>',
        'css' => '.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }',
        'tags' => ['button', 'cta', 'gradient'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_secondary_button',
        'name' => 'Secondary Button',
        'category' => 'Buttons',
        'description' => 'Secondary action button',
        'html' => '<button class="btn btn-secondary">Cancel</button>',
        'css' => '.btn-secondary { background: #f3f4f6; border: 1px solid #d1d5db; }',
        'tags' => ['button', 'secondary'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_success_alert',
        'name' => 'Success Alert',
        'category' => 'Alerts',
        'description' => 'Success message alert',
        'html' => '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Operation completed successfully!</div>',
        'css' => '.alert-success { background: #d1fae5; border-left: 4px solid #10b981; }',
        'tags' => ['alert', 'success', 'notification'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_danger_alert',
        'name' => 'Danger Alert',
        'category' => 'Alerts',
        'description' => 'Error message alert',
        'html' => '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>',
        'css' => '.alert-danger { background: #fee2e2; border-left: 4px solid #ef4444; }',
        'tags' => ['alert', 'error', 'danger'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_basic_card',
        'name' => 'Basic Card',
        'category' => 'Cards',
        'description' => 'Standard content card with header and body',
        'html' => '<div class="card"><div class="card-header">Card Title</div><div class="card-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore.</div></div>',
        'css' => '.card { background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }',
        'tags' => ['card', 'content', 'container'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_form_input',
        'name' => 'Form Input',
        'category' => 'Forms',
        'description' => 'Standard text input with label',
        'html' => '<div class="form-group"><label class="form-label">Email Address</label><input type="email" class="form-control" placeholder="you@example.com"></div>',
        'css' => '.form-control { border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.75rem 1rem; }',
        'tags' => ['form', 'input', 'text'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_badge_primary',
        'name' => 'Primary Badge',
        'category' => 'Badges',
        'description' => 'Small status badge',
        'html' => '<span class="badge badge-primary">Active</span>',
        'css' => '.badge-primary { background: #667eea; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; }',
        'tags' => ['badge', 'status', 'label'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_badge_success',
        'name' => 'Success Badge',
        'category' => 'Badges',
        'description' => 'Success status badge',
        'html' => '<span class="badge badge-success">Completed</span>',
        'css' => '.badge-success { background: #10b981; color: white; }',
        'tags' => ['badge', 'success'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_data_table',
        'name' => 'Data Table',
        'category' => 'Tables',
        'description' => 'Responsive data table',
        'html' => '<table class="table"><thead><tr><th>Name</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td>John Doe</td><td><span class="badge badge-success">Active</span></td><td><button class="btn btn-sm btn-primary">Edit</button></td></tr></tbody></table>',
        'css' => '.table { width: 100%; border-collapse: collapse; } .table th { background: #f3f4f6; padding: 1rem; }',
        'tags' => ['table', 'data', 'responsive'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_modal_basic',
        'name' => 'Basic Modal',
        'category' => 'Modals',
        'description' => 'Centered modal dialog',
        'html' => '<div class="modal"><div class="modal-header">Confirm Action</div><div class="modal-body">Are you sure you want to proceed?</div><div class="modal-footer"><button class="btn btn-secondary">Cancel</button><button class="btn btn-primary">Confirm</button></div></div>',
        'css' => '.modal { background: white; border-radius: 1rem; padding: 2rem; max-width: 500px; }',
        'tags' => ['modal', 'dialog', 'popup'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_nav_tabs',
        'name' => 'Navigation Tabs',
        'category' => 'Navigation',
        'description' => 'Horizontal tab navigation',
        'html' => '<div class="nav-tabs"><a href="#" class="nav-link active">Dashboard</a><a href="#" class="nav-link">Settings</a><a href="#" class="nav-link">Profile</a></div>',
        'css' => '.nav-tabs { display: flex; gap: 0.5rem; border-bottom: 2px solid #e5e7eb; }',
        'tags' => ['navigation', 'tabs', 'menu'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_breadcrumb',
        'name' => 'Breadcrumb',
        'category' => 'Navigation',
        'description' => 'Breadcrumb navigation trail',
        'html' => '<nav class="breadcrumb"><a href="#">Home</a> / <a href="#">Products</a> / <span>Item</span></nav>',
        'css' => '.breadcrumb { color: #6b7280; } .breadcrumb a { color: #667eea; text-decoration: none; }',
        'tags' => ['breadcrumb', 'navigation'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_spinner',
        'name' => 'Loading Spinner',
        'category' => 'Utilities',
        'description' => 'Animated loading spinner',
        'html' => '<div class="spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>',
        'css' => '.spinner { display: inline-flex; align-items: center; gap: 0.5rem; color: #667eea; }',
        'tags' => ['loading', 'spinner', 'utility'],
        'preview_bg' => '#ffffff'
    ],
    [
        'id' => 'comp_tooltip',
        'name' => 'Tooltip',
        'category' => 'Utilities',
        'description' => 'Hover tooltip',
        'html' => '<button class="btn btn-primary" data-tooltip="Click to save changes">Save</button>',
        'css' => '[data-tooltip]:hover::after { content: attr(data-tooltip); position: absolute; background: #1f2937; color: white; padding: 0.5rem; border-radius: 0.25rem; }',
        'tags' => ['tooltip', 'hover', 'help'],
        'preview_bg' => '#f9fafb'
    ],
    [
        'id' => 'comp_progress_bar',
        'name' => 'Progress Bar',
        'category' => 'Utilities',
        'description' => 'Progress indicator',
        'html' => '<div class="progress"><div class="progress-bar" style="width: 65%;">65%</div></div>',
        'css' => '.progress { background: #e5e7eb; border-radius: 9999px; height: 24px; } .progress-bar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }',
        'tags' => ['progress', 'indicator', 'bar'],
        'preview_bg' => '#ffffff'
    ]
];

// Save components
$componentsDir = __DIR__ . '/components';
if (!is_dir($componentsDir)) {
    mkdir($componentsDir, 0755, true);
}

foreach ($components as $component) {
    $component['updated_at'] = date('Y-m-d H:i:s');
    $component['created_at'] = date('Y-m-d H:i:s');

    $file = $componentsDir . '/' . $component['id'] . '.json';
    file_put_contents($file, json_encode($component, JSON_PRETTY_PRINT));
}

echo "✅ Initialized " . count($components) . " components!\n\n";
echo "Component breakdown:\n";

$categories = [];
foreach ($components as $comp) {
    $cat = $comp['category'];
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
}

foreach ($categories as $cat => $count) {
    echo "  - {$cat}: {$count} components\n";
}

echo "\n✨ Component library ready!\n";
echo "🌐 Open: https://staff.vapeshed.co.nz/modules/admin-ui/css-version-control.php\n";
