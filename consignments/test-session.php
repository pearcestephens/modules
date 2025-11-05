<?php
/**
 * Session Debug Test
 * Tests if sessions are working correctly between CIS and modules
 */

echo "<h1>Session Debug Test</h1>";
echo "<hr>";

echo "<h2>1. Testing Main CIS Bootstrap</h2>";
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
echo "✅ Main bootstrap loaded<br>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<h2>2. Session Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>3. Testing Modules Bootstrap</h2>";
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/bootstrap.php';
echo "✅ Modules bootstrap loaded<br>";
echo "Session Status After Modules: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "<br>";
echo "Session Name After Modules: " . session_name() . "<br>";
echo "Session ID After Modules: " . session_id() . "<br>";

echo "<h2>4. Database Connection Test</h2>";
try {
    $pdo = CIS\Base\Database::pdo();
    echo "✅ PDO connection works<br>";

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_outlets");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Database query works - Found " . $result['cnt'] . " outlets<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Auth Check</h2>";
echo "userID (legacy): " . ($_SESSION['userID'] ?? 'NOT SET') . "<br>";
echo "user_id (modules): " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";

if (isset($_SESSION['userID']) || isset($_SESSION['user_id'])) {
    echo "✅ User is logged in<br>";
} else {
    echo "⚠️ No user session found - you may need to log in to CIS first<br>";
}

echo "<h2>6. Cookie Information</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<hr>";
echo "<p><strong>Test Complete!</strong></p>";
echo "<p>If you see 'Forbidden' errors, it's likely because:</p>";
echo "<ul>";
echo "<li>You're not logged into the main CIS system</li>";
echo "<li>Session cookies aren't being set correctly</li>";
echo "<li>There's a file permission issue</li>";
echo "</ul>";
echo "<p><a href='/'>Go to Main CIS Login</a> | <a href='/modules/consignments/'>Try Consignments Module</a></p>";
