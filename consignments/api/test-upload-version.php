<?php
/**
 * Test which version of simple-upload-direct.php is active
 */

$file = __DIR__ . '/simple-upload-direct.php';
$content = file_get_contents($file);

echo "<h1>Upload File Version Check</h1>";
echo "<p><strong>File:</strong> $file</p>";
echo "<p><strong>Size:</strong> " . filesize($file) . " bytes</p>";
echo "<p><strong>Modified:</strong> " . date('Y-m-d H:i:s', filemtime($file)) . "</p>";

echo "<h2>Critical Checks:</h2>";

// Check for correct Authorization header
if (strpos($content, "'Authorization: Bearer ' . \$VEND_TOKEN") !== false) {
    echo "<p style='color: green;'>✅ Authorization header is CORRECT (using ': Bearer')</p>";
} else if (strpos($content, "'Authorization=' . \$VEND_TOKEN") !== false) {
    echo "<p style='color: red;'>❌ Authorization header is WRONG (using '=')</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Could not find Authorization header</p>";
}

// Check for correct column names
if (strpos($content, 't.outlet_from') !== false || strpos($content, 'src.id = t.outlet_from') !== false) {
    echo "<p style='color: green;'>✅ Using correct column names (outlet_from/outlet_to)</p>";
} else if (strpos($content, 't.source_outlet_id') !== false) {
    echo "<p style='color: red;'>❌ Using WRONG column names (source_outlet_id/destination_outlet_id)</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Could not find outlet columns</p>";
}

// Check for progress tracking
if (strpos($content, 'consignment_upload_progress') !== false) {
    echo "<p style='color: green;'>✅ Progress tracking enabled (writes to consignment_upload_progress)</p>";
} else {
    echo "<p style='color: red;'>❌ NO progress tracking (no database writes)</p>";
}

// Check for SSE support
if (strpos($content, '\$progress(') !== false || strpos($content, 'UPDATE consignment_upload_progress') !== false) {
    echo "<p style='color: green;'>✅ SSE support enabled (updates progress table)</p>";
} else {
    echo "<p style='color: red;'>❌ NO SSE support (no progress updates)</p>";
}

echo "<h2>Recent Lines (190-210):</h2>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
$lines = explode("\n", $content);
echo htmlspecialchars(implode("\n", array_slice($lines, 189, 21)));
echo "</pre>";

echo "<h2>Authorization Header Section (45-55):</h2>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo htmlspecialchars(implode("\n", array_slice($lines, 44, 11)));
echo "</pre>";
