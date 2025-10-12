<?php
/**
 * Consignments Module - Auto-Fix Script
 * 
 * Automatically fixes common code quality issues:
 * - Adds missing docblocks
 * - Fixes namespace declarations
 * - Adds XSS protection helpers
 * - Removes trailing whitespace
 * - Standardizes line endings
 * - Fixes indentation
 * 
 * @package Consignments\Tools
 * @author CIS Development Team
 * @date 2025-10-12
 * @version 1.0.0
 */

declare(strict_types=1);

$options = getopt('', ['dry-run', 'fix', 'file:', 'help']);

if (isset($options['help']) || (empty($options['dry-run']) && empty($options['fix']))) {
    echo <<<HELP
Consignments Auto-Fix Script

Usage:
  php auto_fix.php --dry-run              Preview changes without applying
  php auto_fix.php --fix                  Apply all fixes
  php auto_fix.php --fix --file=path.php  Fix specific file only
  php auto_fix.php --help                 Show this help

Fixes Applied:
  ✓ Add missing docblocks
  ✓ Fix namespace declarations (Transfers → Consignments)
  ✓ Add htmlspecialchars() for XSS protection
  ✓ Remove trailing whitespace
  ✓ Standardize line endings (LF)
  ✓ Fix indentation (2 spaces)
  ✓ Remove debug statements

HELP;
    exit(0);
}

$dryRun = isset($options['dry-run']);
$targetFile = $options['file'] ?? null;
$baseDir = dirname(__DIR__);

echo "🔧 Consignments Auto-Fix Script\n";
echo "================================\n\n";
echo "Mode: " . ($dryRun ? "DRY RUN (preview only)" : "FIX (applying changes)") . "\n";
echo "Base: {$baseDir}\n\n";

// Find all PHP files
$files = [];
if ($targetFile) {
    $files[] = $baseDir . '/' . ltrim($targetFile, '/');
} else {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            // Skip vendor, node_modules, .git
            $path = $file->getPathname();
            if (strpos($path, '/vendor/') === false && 
                strpos($path, '/node_modules/') === false &&
                strpos($path, '/.git/') === false) {
                $files[] = $path;
            }
        }
    }
}

$stats = [
    'files_processed' => 0,
    'files_modified' => 0,
    'docblocks_added' => 0,
    'namespaces_fixed' => 0,
    'xss_fixes' => 0,
    'whitespace_fixes' => 0,
    'line_ending_fixes' => 0,
    'indent_fixes' => 0,
    'debug_removed' => 0,
];

foreach ($files as $file) {
    $stats['files_processed']++;
    
    $relativePath = str_replace($baseDir . '/', '', $file);
    $content = file_get_contents($file);
    $originalContent = $content;
    $changes = [];
    
    // Fix 1: Add missing docblock
    if (!preg_match('/^<\?php\s*\/\*\*/', $content)) {
        $filename = basename($file);
        $purpose = guessPurpose($file);
        $package = guessPackage($file);
        
        $docblock = <<<DOCBLOCK
<?php
/**
 * File: {$filename}
 * Purpose: {$purpose}
 * 
 * @package {$package}
 * @author CIS Development Team
 * @date 2025-10-12
 * @version 1.0.0
 */
DOCBLOCK;
        
        // Remove opening <?php tag and add docblock
        $content = preg_replace('/^<\?php\s*/', '', $content, 1);
        $content = $docblock . "\n" . $content;
        $changes[] = "Added docblock";
        $stats['docblocks_added']++;
    }
    
    // Fix 2: Fix namespace declarations (Transfers → Consignments)
    $namespaceFixed = preg_replace_callback(
        '/(namespace|use)\s+Transfers\\\\/',
        function($matches) {
            return $matches[1] . ' Consignments\\';
        },
        $content,
        -1,
        $count
    );
    if ($count > 0) {
        $content = $namespaceFixed;
        $changes[] = "Fixed {$count} namespace declarations";
        $stats['namespaces_fixed'] += $count;
    }
    
    // Fix 3: Add XSS protection (basic - needs manual review)
    // Look for unescaped echo/print in HTML context
    $xssFixed = preg_replace_callback(
        '/<\?=\s*\$(\w+)(\[[^\]]+\])?\s*\?>/',
        function($matches) {
            // Don't escape if already has htmlspecialchars
            if (strpos($matches[0], 'htmlspecialchars') !== false) {
                return $matches[0];
            }
            // Don't escape if it's a known safe function
            $safe = ['Helpers::url', 'url', 'asset_url', 'csrf_token'];
            foreach ($safe as $func) {
                if (strpos($matches[0], $func) !== false) {
                    return $matches[0];
                }
            }
            // Add XSS protection
            $var = $matches[1] . ($matches[2] ?? '');
            return "<?= htmlspecialchars(\${$var} ?? '', ENT_QUOTES, 'UTF-8') ?>";
        },
        $content,
        -1,
        $count
    );
    if ($count > 0) {
        $content = $xssFixed;
        $changes[] = "Added XSS protection to {$count} outputs";
        $stats['xss_fixes'] += $count;
    }
    
    // Fix 4: Remove trailing whitespace
    $lines = explode("\n", $content);
    $whitespaceFixed = false;
    foreach ($lines as $i => $line) {
        $trimmed = rtrim($line);
        if ($trimmed !== $line) {
            $lines[$i] = $trimmed;
            $whitespaceFixed = true;
        }
    }
    if ($whitespaceFixed) {
        $content = implode("\n", $lines);
        $changes[] = "Removed trailing whitespace";
        $stats['whitespace_fixes']++;
    }
    
    // Fix 5: Standardize line endings to LF
    if (strpos($content, "\r\n") !== false) {
        $content = str_replace("\r\n", "\n", $content);
        $changes[] = "Fixed line endings (CRLF → LF)";
        $stats['line_ending_fixes']++;
    }
    
    // Fix 6: Remove debug statements
    $debugPatterns = [
        '/var_dump\([^;]+\);?\s*/',
        '/print_r\([^;]+\);?\s*/',
        '/echo\s+["\']DEBUG:.*?["\'];?\s*/',
        '/console\.log\([^;]+\);?\s*/',
    ];
    foreach ($debugPatterns as $pattern) {
        $debugFixed = preg_replace($pattern, '', $content, -1, $count);
        if ($count > 0) {
            $content = $debugFixed;
            $changes[] = "Removed {$count} debug statements";
            $stats['debug_removed'] += $count;
        }
    }
    
    // Fix 7: Fix indentation (basic - 2 spaces for PHP, preserve HTML)
    // This is complex, so we'll just fix obvious issues
    $lines = explode("\n", $content);
    $indentFixed = false;
    $indent = 0;
    foreach ($lines as $i => $line) {
        $trimmed = ltrim($line);
        
        // Skip empty lines and comments
        if (empty($trimmed) || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        
        // Decrease indent for closing braces/tags
        if (preg_match('/^[}\]\)]/', $trimmed)) {
            $indent = max(0, $indent - 1);
        }
        
        // Apply indent
        $expectedSpaces = $indent * 2;
        $currentSpaces = strlen($line) - strlen($trimmed);
        
        if ($currentSpaces !== $expectedSpaces && !preg_match('/^(<?php|<\?=|<html|<!DOCTYPE)/', $trimmed)) {
            $lines[$i] = str_repeat(' ', $expectedSpaces) . $trimmed;
            $indentFixed = true;
        }
        
        // Increase indent for opening braces/tags
        if (preg_match('/[{\[\(]\s*$/', $trimmed) && !preg_match('/[}\]\)]\s*$/', $trimmed)) {
            $indent++;
        }
    }
    if ($indentFixed) {
        $content = implode("\n", $lines);
        $changes[] = "Fixed indentation";
        $stats['indent_fixes']++;
    }
    
    // Apply changes
    if ($content !== $originalContent) {
        $stats['files_modified']++;
        
        if ($dryRun) {
            echo "📄 {$relativePath}\n";
            foreach ($changes as $change) {
                echo "   ✓ {$change}\n";
            }
            echo "\n";
        } else {
            file_put_contents($file, $content);
            echo "✅ {$relativePath}\n";
            foreach ($changes as $change) {
                echo "   ✓ {$change}\n";
            }
            echo "\n";
        }
    }
}

// Summary
echo "\n";
echo "================================\n";
echo "Summary\n";
echo "================================\n";
echo "Files processed:       {$stats['files_processed']}\n";
echo "Files modified:        {$stats['files_modified']}\n";
echo "Docblocks added:       {$stats['docblocks_added']}\n";
echo "Namespaces fixed:      {$stats['namespaces_fixed']}\n";
echo "XSS protections:       {$stats['xss_fixes']}\n";
echo "Whitespace fixes:      {$stats['whitespace_fixes']}\n";
echo "Line ending fixes:     {$stats['line_ending_fixes']}\n";
echo "Indent fixes:          {$stats['indent_fixes']}\n";
echo "Debug statements:      {$stats['debug_removed']}\n";
echo "\n";

if ($dryRun) {
    echo "ℹ️  This was a DRY RUN. No files were modified.\n";
    echo "   Run with --fix to apply changes.\n";
} else {
    echo "✅ All fixes applied successfully!\n";
    echo "   Review changes and test thoroughly.\n";
}

// Helper functions
function guessPurpose(string $file): string
{
    $basename = basename($file, '.php');
    $dir = basename(dirname($file));
    
    if (strpos($file, '/api/') !== false) {
        return 'API endpoint for ' . str_replace('_', ' ', $basename);
    }
    if (strpos($file, '/controllers/') !== false) {
        return 'Controller for ' . str_replace('Controller', '', $basename);
    }
    if (strpos($file, '/lib/') !== false) {
        return 'Library class for ' . $basename;
    }
    if (strpos($file, '/views/') !== false) {
        return 'View template for ' . $dir . '/' . $basename;
    }
    if (strpos($file, '/components/') !== false) {
        return 'UI component for ' . $dir . '/' . $basename;
    }
    if (strpos($file, '/tools/') !== false) {
        return 'Utility script for ' . $basename;
    }
    
    return 'Module file for ' . $basename;
}

function guessPackage(string $file): string
{
    if (strpos($file, '/api/') !== false) {
        return 'Consignments\\Api';
    }
    if (strpos($file, '/controllers/') !== false) {
        return 'Consignments\\Controllers';
    }
    if (strpos($file, '/lib/') !== false) {
        return 'Consignments\\Lib';
    }
    if (strpos($file, '/views/') !== false) {
        return 'Consignments\\Views';
    }
    if (strpos($file, '/components/') !== false) {
        return 'Consignments\\Components';
    }
    if (strpos($file, '/tools/') !== false) {
        return 'Consignments\\Tools';
    }
    
    return 'Consignments';
}
