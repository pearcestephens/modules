<?php
// Test if BaseController loads correctly

require_once __DIR__ . '/controllers/BaseController.php';

echo "BaseController namespace test:\n\n";

$reflection = new ReflectionClass('HumanResources\Payroll\Controllers\BaseController');
echo "✓ BaseController found!\n";
echo "Namespace: " . $reflection->getNamespaceName() . "\n";
echo "File: " . $reflection->getFileName() . "\n";

echo "\n✅ BaseController loads correctly with correct namespace!\n";
