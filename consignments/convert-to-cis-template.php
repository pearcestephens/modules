<?php
/**
 * CIS Template Conversion Script
 * Converts all consignments views to use the standard CIS template
 */

echo "=== CIS Template Conversion Script ===\n\n";

$views = array(
    'transfer-manager' => array('title' => 'Transfer Manager', 'icon' => 'fa-arrow-left-right'),
    'control-panel' => array('title' => 'Control Panel', 'icon' => 'fa-gauge'),
    'purchase-orders' => array('title' => 'Purchase Orders', 'icon' => 'fa-cart-shopping'),
    'stock-transfers' => array('title' => 'Stock Transfers', 'icon' => 'fa-box'),
    'receiving' => array('title' => 'Receiving', 'icon' => 'fa-inbox'),
    'freight' => array('title' => 'Freight Management', 'icon' => 'fa-truck'),
    'queue-status' => array('title' => 'Queue Status', 'icon' => 'fa-tasks'),
    'admin-controls' => array('title' => 'Admin Controls', 'icon' => 'fa-cog'),
    'ai-insights' => array('title' => 'AI Insights', 'icon' => 'fa-brain')
);

$converted = 0;
$failed = 0;
$skipped = 0;

foreach ($views as $filename => $data) {
    $filepath = __DIR__ . "/views/" . $filename . ".php";

    if (!file_exists($filepath)) {
        echo "❌ SKIP: " . $filename . ".php (file not found)\n";
        $skipped++;
        continue;
    }

    // Backup original file
    $backupPath = $filepath . ".backup." . date('Y-m-d-His');
    if (!copy($filepath, $backupPath)) {
        echo "❌ FAIL: " . $filename . ".php (backup failed)\n";
        $failed++;
        continue;
    }

    $content = file_get_contents($filepath);

    // Check if already converted
    if (strpos($content, 'CISTemplate') !== false) {
        echo "⏭️  SKIP: " . $filename . ".php (already converted)\n";
        $skipped++;
        continue;
    }

    $title = $data['title'];
    $icon = $data['icon'];

    // Build new content
    $newContent = "<?php\n";
    $newContent .= "/**\n * Consignments Module - " . $title . "\n * \n * @package CIS\\Consignments\n * @version 3.0.0\n */\n\n";
    $newContent .= "declare(strict_types=1);\n\n";
    $newContent .= "// Load CIS Template\nrequire_once __DIR__ . '/../lib/CISTemplate.php';\n\n";
    $newContent .= "// Initialize template\n";
    $newContent .= "\$template = new CISTemplate();\n";
    $newContent .= "\$template->setTitle('" . $title . "');\n";
    $newContent .= "\$template->setBreadcrumbs([\n";
    $newContent .= "    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],\n";
    $newContent .= "    ['label' => 'Consignments', 'url' => '/modules/consignments/'],\n";
    $newContent .= "    ['label' => '" . $title . "', 'url' => '/modules/consignments/?route=" . $filename . "', 'active' => true]\n";
    $newContent .= "]);\n\n";
    $newContent .= "// Start content capture\n\$template->startContent();\n?>\n\n";

    // Remove PHP opening tag if present
    $content = preg_replace('/^<\?php\s*/', '', $content);

    // Wrap content
    $newContent .= "<div class=\"container-fluid\">\n";
    $newContent .= "    <div class=\"card mb-4\">\n";
    $newContent .= "        <div class=\"card-body\">\n";
    $newContent .= "            <h2 class=\"mb-0\"><i class=\"fas " . $icon . " mr-2\"></i>" . $title . "</h2>\n";
    $newContent .= "        </div>\n";
    $newContent .= "    </div>\n\n";
    $newContent .= $content;
    $newContent .= "\n</div>\n\n";

    $newContent .= "<?php\n// End content capture and render\n\$template->endContent();\n\$template->render();\n";

    // Write new file
    if (file_put_contents($filepath, $newContent)) {
        echo "✅ SUCCESS: " . $filename . ".php (converted and backed up)\n";
        $converted++;
    } else {
        echo "❌ FAIL: " . $filename . ".php (write failed)\n";
        $failed++;
        copy($backupPath, $filepath);
    }
}

echo "\n=== Conversion Summary ===\n";
echo "✅ Converted: " . $converted . "\n";
echo "⏭️  Skipped: " . $skipped . "\n";
echo "❌ Failed: " . $failed . "\n";
echo "\nBackup files created in views/ directory with .backup.* extension\n";
echo "\n✅ Done!\n";
