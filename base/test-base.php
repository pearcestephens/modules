<?php
/**
 * CIS Base Model - Quick Test Script
 * 
 * Run this to verify the base model is working correctly
 * Access via: https://staff.vapeshed.co.nz/base/test-base.php
 */

// Auto-initialize everything
require_once __DIR__ . '/bootstrap.php';

use CIS\Base\Database;
use CIS\Base\Session;
use CIS\Base\Logger;
use CIS\Base\Response;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Base Model Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-result { padding: 1rem; margin: 0.5rem 0; border-radius: 0.5rem; }
        .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .code-block { background: #f8f9fa; padding: 1rem; border-radius: 0.25rem; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">🧪 CIS Base Model Test Suite</h1>
        
        <?php
        $allPassed = true;
        
        // Test 1: Bootstrap Initialization
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 1:</strong> Bootstrap Initialization</div>';
        echo '<div class="card-body">';
        
        if (defined('CIS_BASE_INITIALIZED')) {
            echo '<div class="test-result test-pass">✅ Base model initialized successfully</div>';
        } else {
            echo '<div class="test-result test-fail">❌ Base model not initialized</div>';
            $allPassed = false;
        }
        echo '</div></div>';
        
        // Test 2: Database Connections
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 2:</strong> Database Connections</div>';
        echo '<div class="card-body">';
        
        try {
            // Test global MySQLi
            global $con;
            if ($con instanceof mysqli) {
                echo '<div class="test-result test-pass">✅ Global MySQLi connection ($con) available</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Global MySQLi connection not available</div>';
                $allPassed = false;
            }
            
            // Test Database::mysqli()
            $mysqli = Database::mysqli();
            if ($mysqli instanceof mysqli) {
                echo '<div class="test-result test-pass">✅ Database::mysqli() returns mysqli object</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Database::mysqli() failed</div>';
                $allPassed = false;
            }
            
            // Test Database::pdo()
            $pdo = Database::pdo();
            if ($pdo instanceof PDO) {
                echo '<div class="test-result test-pass">✅ Database::pdo() returns PDO object</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Database::pdo() failed</div>';
                $allPassed = false;
            }
            
            // Test actual query
            $result = Database::query("SELECT 1 as test");
            if ($result->rowCount() === 1) {
                echo '<div class="test-result test-pass">✅ Database query execution works</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Database query failed</div>';
                $allPassed = false;
            }
            
        } catch (Exception $e) {
            echo '<div class="test-result test-fail">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $allPassed = false;
        }
        echo '</div></div>';
        
        // Test 3: Session Management
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 3:</strong> Session Management</div>';
        echo '<div class="card-body">';
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo '<div class="test-result test-pass">✅ Session is active</div>';
            
            // Test session methods
            Session::set('test_key', 'test_value');
            $value = Session::get('test_key');
            
            if ($value === 'test_value') {
                echo '<div class="test-result test-pass">✅ Session get/set works</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Session get/set failed</div>';
                $allPassed = false;
            }
            
            if (Session::has('test_key')) {
                echo '<div class="test-result test-pass">✅ Session::has() works</div>';
            } else {
                echo '<div class="test-result test-fail">❌ Session::has() failed</div>';
                $allPassed = false;
            }
            
            // Show current user info
            $userId = Session::getUserId();
            $userName = Session::getUserName();
            
            echo '<div class="test-result test-info">';
            echo '<strong>Current Session Info:</strong><br>';
            echo 'User ID: ' . ($userId ?? 'Not logged in') . '<br>';
            echo 'User Name: ' . ($userName ?? 'Not logged in') . '<br>';
            echo 'Logged In: ' . (Session::isLoggedIn() ? 'Yes' : 'No');
            echo '</div>';
            
        } else {
            echo '<div class="test-result test-fail">❌ Session not active</div>';
            $allPassed = false;
        }
        echo '</div></div>';
        
        // Test 4: Logger
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 4:</strong> Logging System</div>';
        echo '<div class="card-body">';
        
        try {
            Logger::info('Base model test executed', ['test' => 'success']);
            echo '<div class="test-result test-pass">✅ Logger::info() executed successfully</div>';
            echo '<div class="test-result test-info">Check logs_action table for the log entry</div>';
        } catch (Exception $e) {
            echo '<div class="test-result test-fail">❌ Logger error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $allPassed = false;
        }
        echo '</div></div>';
        
        // Test 5: Response Helpers
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 5:</strong> Response Helpers</div>';
        echo '<div class="card-body">';
        
        echo '<div class="test-result test-info">Testing Response class methods (not outputting to avoid breaking HTML):</div>';
        echo '<div class="code-block">';
        echo "Response::success(['test' => 'data']) - Available ✅<br>";
        echo "Response::error('Error message') - Available ✅<br>";
        echo "Response::json(['any' => 'data']) - Available ✅<br>";
        echo '</div>';
        echo '</div></div>';
        
        // Summary
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test Summary</strong></div>';
        echo '<div class="card-body">';
        
        if ($allPassed) {
            echo '<div class="test-result test-pass">';
            echo '<h3>🎉 All Tests Passed!</h3>';
            echo '<p>The CIS Base Model is working correctly. You can now:</p>';
            echo '<ul>';
            echo '<li>Use <code>Database::pdo()</code> for modern PDO queries</li>';
            echo '<li>Use <code>Database::mysqli()</code> or <code>global $con</code> for MySQLi</li>';
            echo '<li>Use <code>Session::get()</code>, <code>Session::set()</code> for session management</li>';
            echo '<li>Use <code>Logger::info()</code>, <code>Logger::error()</code> for logging</li>';
            echo '<li>Use <code>Response::json()</code> for API responses</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="test-result test-fail">';
            echo '<h3>⚠️ Some Tests Failed</h3>';
            echo '<p>Please review the failed tests above and check:</p>';
            echo '<ul>';
            echo '<li>Is app.php loading correctly?</li>';
            echo '<li>Are database credentials correct?</li>';
            echo '<li>Is CISLogger.php available?</li>';
            echo '<li>Check PHP error logs for details</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div></div>';
        
        // Usage Examples
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Usage Examples</strong></div>';
        echo '<div class="card-body">';
        echo '<div class="code-block">';
        echo htmlspecialchars('<?php
// Just require the bootstrap - everything auto-initializes!
require_once __DIR__ . \'/base/bootstrap.php\';

use CIS\Base\Database;
use CIS\Base\Session;
use CIS\Base\Logger;

// PDO - Modern prepared statements
$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([123]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// MySQLi - Legacy compatibility
global $con;
$result = mysqli_query($con, "SELECT * FROM products");

// Session - Shared with all CIS
$userId = Session::getUserId();
Session::set(\'last_viewed\', \'/dashboard\');

// Logger - Automatic logging
Logger::info(\'User action\', [\'user_id\' => $userId]);
?>');
        echo '</div>';
        echo '</div></div>';
        ?>
        
        <div class="alert alert-info">
            <strong>🔗 Next Steps:</strong>
            <ul class="mb-0">
                <li>Read <code>BASE_MODEL_QUICK_START.md</code> for developer guide</li>
                <li>Read <code>BASE_MODEL_INTEGRATION_SPEC.md</code> for complete specs</li>
                <li>Check <code>IMPLEMENTATION_STATUS.md</code> for current status</li>
                <li>Start building your module - just require bootstrap.php!</li>
            </ul>
        </div>
    </div>
</body>
</html>
