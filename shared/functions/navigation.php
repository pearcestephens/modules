<?php
/**
 * Navigation Menu Functions
 * 
 * Provides navigation menu structure for CIS template system.
 * Used by sidemenu.php in assets/template/
 * 
 * @package CIS\Shared\Functions
 * @version 1.0.0
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
}

/**
 * Get navigation menu structure for current user
 * 
 * Returns collection of navigation category objects that match the database structure.
 * Each object has: id, title, active, sort_order
 * Compatible with sidemenu.php which expects objects, not arrays.
 * 
 * @return array Collection of navigation objects
 */
if (!function_exists('getNavigationMenus')) {
function getNavigationMenus() {
    // Return objects that match the database structure expected by sidemenu.php
    // sidemenu.php looks for: $c->id, $c->title, $c->itemsArray
    
    $categories = [];
    
    // Staff Accounts category
    $cat1 = new stdClass();
    $cat1->id = 1;
    $cat1->title = 'Staff Accounts';
    $cat1->active = 1;
    $cat1->sort_order = 1;
    $categories[] = $cat1;
    
    // Consignments category
    $cat2 = new stdClass();
    $cat2->id = 2;
    $cat2->title = 'Consignments';
    $cat2->active = 1;
    $cat2->sort_order = 2;
    $categories[] = $cat2;
    
    // Testing Tools category
    $cat3 = new stdClass();
    $cat3->id = 3;
    $cat3->title = 'Testing Tools';
    $cat3->active = 1;
    $cat3->sort_order = 3;
    $categories[] = $cat3;
    
    // System category
    $cat4 = new stdClass();
    $cat4->id = 4;
    $cat4->title = 'System';
    $cat4->active = 1;
    $cat4->sort_order = 4;
    $categories[] = $cat4;
    
    return $categories;
}
}

/**
 * Get current user permissions (fallback for testing)
 * 
 * Returns permission items for current user.
 * Each object has: permission_id, name, filename, navigation_id, show_in_sidemenu
 * 
 * This is a FALLBACK for when the main permissions.php function isn't loaded.
 * In production, assets/functions/permissions.php provides the real function.
 * 
 * @param int $userID User ID
 * @return array Collection of permission objects
 */
if (!function_exists('getCurrentUserPermissions')) {
function getCurrentUserPermissions($userID) {
    // For testing bypass user (999999), return mock permissions
    if ($userID === 999999 || isset($_SESSION['testing_bypass'])) {
        $permissions = [];
        
        // Staff Accounts permissions
        $perm1 = new stdClass();
        $perm1->permission_id = 1;
        $perm1->name = 'Employee Mapping';
        $perm1->filename = '/modules/staff-accounts/index.php';
        $perm1->navigation_id = 1;
        $perm1->show_in_sidemenu = 1;
        $permissions[] = $perm1;
        
        $perm2 = new stdClass();
        $perm2->permission_id = 2;
        $perm2->name = 'UI/UX Testing';
        $perm2->filename = '/modules/staff-accounts/ui-ux-test-suite.php';
        $perm2->navigation_id = 1;
        $perm2->show_in_sidemenu = 1;
        $permissions[] = $perm2;
        
        // Consignments permissions
        $perm3 = new stdClass();
        $perm3->permission_id = 3;
        $perm3->name = 'Stock Transfers';
        $perm3->filename = '/modules/consignments/stock-transfers/';
        $perm3->navigation_id = 2;
        $perm3->show_in_sidemenu = 1;
        $permissions[] = $perm3;
        
        $perm4 = new stdClass();
        $perm4->permission_id = 4;
        $perm4->name = 'Purchase Orders';
        $perm4->filename = '/modules/consignments/purchase-orders/';
        $perm4->navigation_id = 2;
        $perm4->show_in_sidemenu = 1;
        $permissions[] = $perm4;
        
        // Testing Tools permissions
        $perm5 = new stdClass();
        $perm5->permission_id = 5;
        $perm5->name = 'Comprehensive Tests';
        $perm5->filename = '/modules/staff-accounts/comprehensive-test-suite.php';
        $perm5->navigation_id = 3;
        $perm5->show_in_sidemenu = 1;
        $permissions[] = $perm5;
        
        $perm6 = new stdClass();
        $perm6->permission_id = 6;
        $perm6->name = 'API Validator';
        $perm6->filename = '/modules/staff-accounts/api-endpoint-validator.php';
        $perm6->navigation_id = 3;
        $perm6->show_in_sidemenu = 1;
        $permissions[] = $perm6;
        
        return $permissions;
    }
    
    // For real users, return empty (the real function from permissions.php will be used)
    return [];
}
}

/**
 * Render navigation menu HTML
 * 
 * Converts menu array into Bootstrap 4 sidebar navigation HTML.
 * Handles nested sub-menus and active states.
 * 
 * @param array $menus Menu structure from getNavigationMenus()
 * @return string HTML navigation markup
 */
if (!function_exists('renderNavigationMenu')) {
function renderNavigationMenu($menus) {
    if (empty($menus)) {
        return '<p class="text-muted p-3">No menu items available</p>';
    }
    
    $html = '<ul class="nav">';
    
    foreach ($menus as $menu) {
        $activeClass = !empty($menu['active']) ? 'active' : '';
        $hasChildren = !empty($menu['children']);
        
        if ($hasChildren) {
            // Parent menu with children
            $html .= '<li class="nav-item nav-dropdown ' . $activeClass . '">';
            $html .= '<a class="nav-link nav-dropdown-toggle" href="#">';
            if (!empty($menu['icon'])) {
                $html .= '<i class="' . $menu['icon'] . '"></i> ';
            }
            $html .= htmlspecialchars($menu['name']);
            $html .= '</a>';
            
            // Render children
            $html .= '<ul class="nav-dropdown-items">';
            foreach ($menu['children'] as $child) {
                $childActiveClass = !empty($child['active']) ? 'active' : '';
                $html .= '<li class="nav-item ' . $childActiveClass . '">';
                $html .= '<a class="nav-link" href="' . htmlspecialchars($child['url']) . '">';
                $html .= htmlspecialchars($child['name']);
                $html .= '</a>';
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Simple menu item
            $html .= '<li class="nav-item ' . $activeClass . '">';
            $html .= '<a class="nav-link" href="' . htmlspecialchars($menu['url']) . '">';
            if (!empty($menu['icon'])) {
                $html .= '<i class="' . $menu['icon'] . '"></i> ';
            }
            $html .= htmlspecialchars($menu['name']);
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ul>';
    
    return $html;
}
}

/**
 * Get breadcrumb trail for current page
 * 
 * Generates breadcrumb navigation based on URL path.
 * 
 * @return array Breadcrumb items [['name' => '...', 'url' => '...'], ...]
 */
if (!function_exists('getBreadcrumbs')) {
function getBreadcrumbs() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = array_filter(explode('/', $path));
    
    $breadcrumbs = [
        ['name' => 'Home', 'url' => '/']
    ];
    
    $currentPath = '';
    foreach ($segments as $segment) {
        $currentPath .= '/' . $segment;
        $name = ucwords(str_replace(['-', '_'], ' ', $segment));
        
        // Remove .php extension from display
        $name = str_replace('.php', '', $name);
        
        $breadcrumbs[] = [
            'name' => $name,
            'url' => $currentPath
        ];
    }
    
    return $breadcrumbs;
}
}

/**
 * Render breadcrumb HTML
 * 
 * @param array $breadcrumbs Breadcrumb items
 * @return string HTML breadcrumb markup
 */
if (!function_exists('renderBreadcrumbs')) {
function renderBreadcrumbs($breadcrumbs) {
    if (empty($breadcrumbs)) {
        return '';
    }
    
    $html = '<ol class="breadcrumb">';
    
    $lastIndex = count($breadcrumbs) - 1;
    foreach ($breadcrumbs as $index => $crumb) {
        if ($index === $lastIndex) {
            // Last item - active, no link
            $html .= '<li class="breadcrumb-item active">' . htmlspecialchars($crumb['name']) . '</li>';
        } else {
            // Link item
            $html .= '<li class="breadcrumb-item">';
            $html .= '<a href="' . htmlspecialchars($crumb['url']) . '">' . htmlspecialchars($crumb['name']) . '</a>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ol>';
    
    return $html;
}
}
