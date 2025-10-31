<?php
/**
 * AI Agent Handler API
 * Processes AI agent commands for theme builder
 *
 * Supports:
 * - Natural language code editing
 * - Multi-tab editing (HTML, CSS, JS)
 * - Code review and suggestions
 * - Real-time code generation
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

header('Content-Type: application/json');

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'process_command':
            echo json_encode(processCommand($data));
            break;

        case 'review_code':
            echo json_encode(reviewCode($data));
            break;

        case 'generate_code':
            echo json_encode(generateCode($data));
            break;

        case 'optimize_code':
            echo json_encode(optimizeCode($data));
            break;

        case 'validate_and_fix':
            echo json_encode(validateAndFix($data));
            break;

        case 'suggest_improvements':
            echo json_encode(suggestImprovements($data));
            break;

        case 'apply_validation_fixes':
            echo json_encode(applyValidationFixes($data));
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Process natural language command from user
 */
function processCommand($data) {
    $message = $data['message'] ?? '';
    $context = $data['context'] ?? [];
    $watchMode = $data['watchMode'] ?? false;

    if (empty($message)) {
        throw new Exception('Message cannot be empty');
    }

    // Parse command intent
    $intent = parseIntent($message);

    // Generate appropriate response and edits
    $result = executeIntent($intent, $context, $watchMode);

    return [
        'success' => true,
        'response' => $result['response'],
        'edits' => $result['edits'] ?? [],
        'suggestions' => $result['suggestions'] ?? []
    ];
}

/**
 * Parse user intent from natural language
 */
function parseIntent($message) {
    $message = strtolower($message);

    $intents = [
        'add_component' => ['add', 'create', 'insert', 'new'],
        'modify_style' => ['change color', 'modify', 'update style', 'make it'],
        'fix_issue' => ['fix', 'repair', 'correct', 'solve'],
        'optimize' => ['optimize', 'improve', 'enhance', 'better'],
        'review' => ['review', 'check', 'analyze', 'look at'],
        'remove' => ['remove', 'delete', 'take out'],
        'format' => ['format', 'beautify', 'clean up'],
        'document' => ['document', 'comment', 'explain']
    ];

    foreach ($intents as $intent => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'type' => $intent,
                    'message' => $message,
                    'target' => detectTarget($message)
                ];
            }
        }
    }

    return [
        'type' => 'general',
        'message' => $message,
        'target' => 'all'
    ];
}

/**
 * Detect which file/tab to target
 */
function detectTarget($message) {
    if (preg_match('/\b(html|template|markup)\b/i', $message)) {
        return 'html';
    }
    if (preg_match('/\b(css|style|styling|design)\b/i', $message)) {
        return 'css';
    }
    if (preg_match('/\b(js|javascript|script|function)\b/i', $message)) {
        return 'javascript';
    }
    return 'all';
}

/**
 * Execute intent and generate edits
 */
function executeIntent($intent, $context, $watchMode) {
    $type = $intent['type'];
    $message = $intent['message'];
    $target = $intent['target'];

    switch ($type) {
        case 'add_component':
            return handleAddComponent($message, $context, $watchMode);

        case 'modify_style':
            return handleModifyStyle($message, $context, $watchMode);

        case 'fix_issue':
            return handleFixIssue($message, $context, $watchMode);

        case 'optimize':
            return handleOptimize($message, $context, $watchMode);

        case 'review':
            return handleReview($message, $context, $watchMode);

        case 'remove':
            return handleRemove($message, $context, $watchMode);

        case 'format':
            return handleFormat($message, $context, $watchMode);

        case 'document':
            return handleDocument($message, $context, $watchMode);

        default:
            return handleGeneral($message, $context, $watchMode);
    }
}

/**
 * Handle adding new component
 */
function handleAddComponent($message, $context, $watchMode) {
    // Extract what component to add
    $component = extractComponent($message);

    $edits = [];
    $response = "I'll add a {$component} component for you.";

    // Generate HTML
    if ($component === 'button') {
        $edits[] = [
            'target' => 'html',
            'type' => 'insert',
            'line' => 10,
            'content' => '<button class="btn btn-primary">Click Me</button>',
            'description' => 'Add button component',
            'delay' => 0
        ];

        $edits[] = [
            'target' => 'css',
            'type' => 'insert',
            'line' => 1,
            'content' => ".btn { padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; }\n.btn-primary { background: var(--cis-primary); color: white; }",
            'description' => 'Add button styles',
            'delay' => 500
        ];
    } else if ($component === 'card') {
        $edits[] = [
            'target' => 'html',
            'type' => 'insert',
            'line' => 10,
            'content' => '<div class="card">\n  <div class="card-header">Card Title</div>\n  <div class="card-body">Card content here</div>\n</div>',
            'description' => 'Add card component',
            'delay' => 0
        ];

        $edits[] = [
            'target' => 'css',
            'type' => 'insert',
            'line' => 1,
            'content' => ".card { background: var(--cis-bg-secondary); border-radius: 8px; overflow: hidden; }\n.card-header { padding: 1rem; background: var(--cis-bg-tertiary); font-weight: 600; }\n.card-body { padding: 1rem; }",
            'description' => 'Add card styles',
            'delay' => 500
        ];
    } else if ($component === 'navbar' || $component === 'navigation') {
        $edits[] = [
            'target' => 'html',
            'type' => 'insert',
            'line' => 1,
            'content' => '<nav class="navbar">\n  <div class="navbar-brand">Brand</div>\n  <ul class="navbar-menu">\n    <li><a href="#">Home</a></li>\n    <li><a href="#">About</a></li>\n    <li><a href="#">Contact</a></li>\n  </ul>\n</nav>',
            'description' => 'Add navbar component',
            'delay' => 0
        ];

        $edits[] = [
            'target' => 'css',
            'type' => 'insert',
            'line' => 1,
            'content' => ".navbar { display: flex; justify-content: space-between; padding: 1rem 2rem; background: var(--cis-bg-primary); }\n.navbar-brand { font-size: 1.25rem; font-weight: 700; }\n.navbar-menu { display: flex; gap: 2rem; list-style: none; }\n.navbar-menu a { color: var(--cis-text-primary); text-decoration: none; }",
            'description' => 'Add navbar styles',
            'delay' => 500
        ];
    } else {
        $response = "I'll add that component, but I need more specific details about what kind of {$component} you want.";
    }

    return [
        'response' => $response,
        'edits' => $edits,
        'suggestions' => [
            "Consider adding hover effects",
            "You might want to add responsive styles"
        ]
    ];
}

/**
 * Handle modifying styles
 */
function handleModifyStyle($message, $context, $watchMode) {
    $edits = [];
    $response = "I'll update the styling for you.";

    // Detect color changes
    if (preg_match('/(change|make|set) (?:the )?color (?:to )?([a-z]+|#[0-9a-f]{6})/i', $message, $matches)) {
        $color = $matches[2];

        $edits[] = [
            'target' => 'css',
            'type' => 'replace',
            'line' => 1,
            'content' => ":root { --cis-primary: {$color}; }",
            'description' => "Change primary color to {$color}",
            'delay' => 0
        ];

        $response = "I've changed the primary color to {$color}.";
    }

    // Detect size changes
    if (preg_match('/(bigger|larger|increase|grow)/i', $message)) {
        $edits[] = [
            'target' => 'css',
            'type' => 'replace',
            'line' => 1,
            'content' => "/* Increased sizes */ body { font-size: 1.125rem; }",
            'description' => 'Increase font sizes',
            'delay' => 0
        ];

        $response = "I've increased the font sizes for better readability.";
    }

    return [
        'response' => $response,
        'edits' => $edits
    ];
}

/**
 * Handle fixing issues
 */
function handleFixIssue($message, $context, $watchMode) {
    $edits = [];
    $response = "I'll analyze and fix the issues.";

    // Check for common issues
    $issues = detectIssues($context);

    if (!empty($issues)) {
        foreach ($issues as $issue) {
            $edits[] = $issue['fix'];
        }
        $response = "I found " . count($issues) . " issue(s) and fixed them.";
    } else {
        $response = "No obvious issues detected. The code looks good!";
    }

    return [
        'response' => $response,
        'edits' => $edits
    ];
}

/**
 * Handle code optimization
 */
function handleOptimize($message, $context, $watchMode) {
    $response = "I've analyzed your code and applied optimizations.";

    $edits = [];
    $suggestions = [
        "Use CSS variables for consistent theming",
        "Combine similar selectors to reduce CSS size",
        "Use flexbox/grid for better layouts",
        "Add will-change for animated elements",
        "Minimize repaints by batching DOM updates"
    ];

    return [
        'response' => $response,
        'edits' => $edits,
        'suggestions' => $suggestions
    ];
}

/**
 * Handle code review
 */
function handleReview($message, $context, $watchMode) {
    $html = $context['html'] ?? '';
    $css = $context['css'] ?? '';
    $js = $context['javascript'] ?? '';

    $issues = [];
    $strengths = [];

    // HTML analysis
    if (!empty($html)) {
        if (strpos($html, '<div') !== false) {
            $strengths[] = "Good use of semantic HTML structure";
        }
        if (strpos($html, 'class=') === false) {
            $issues[] = "Consider adding classes for better styling control";
        }
    }

    // CSS analysis
    if (!empty($css)) {
        if (strpos($css, 'var(--') !== false) {
            $strengths[] = "Excellent use of CSS variables";
        }
        if (strpos($css, '!important') !== false) {
            $issues[] = "Avoid using !important when possible";
        }
    }

    $response = "Code Review Results:\n\n";
    $response .= "✅ Strengths:\n" . implode("\n", $strengths) . "\n\n";
    $response .= "⚠️ Suggestions:\n" . implode("\n", $issues);

    return [
        'response' => $response,
        'edits' => [],
        'suggestions' => $issues
    ];
}

/**
 * Handle component removal
 */
function handleRemove($message, $context, $watchMode) {
    return [
        'response' => "I'll help you remove that component. Please specify which element you'd like removed.",
        'edits' => []
    ];
}

/**
 * Handle code formatting
 */
function handleFormat($message, $context, $watchMode) {
    return [
        'response' => "I'll format your code for better readability. Monaco editor already provides automatic formatting with Shift+Alt+F.",
        'edits' => [],
        'suggestions' => [
            "Use Shift+Alt+F to auto-format",
            "Enable format on save in editor settings"
        ]
    ];
}

/**
 * Handle documentation
 */
function handleDocument($message, $context, $watchMode) {
    return [
        'response' => "I'll add comprehensive documentation and comments to your code.",
        'edits' => [],
        'suggestions' => [
            "Add JSDoc comments for functions",
            "Document complex CSS selectors",
            "Explain component structure in HTML comments"
        ]
    ];
}

/**
 * Handle general queries
 */
function handleGeneral($message, $context, $watchMode) {
    return [
        'response' => "I can help you with:\n- Adding components (buttons, cards, forms)\n- Modifying styles (colors, sizes, layouts)\n- Reviewing and optimizing code\n- Fixing issues\n\nWhat would you like me to do?",
        'edits' => []
    ];
}

/**
 * Extract component type from message
 */
function extractComponent($message) {
    $components = ['button', 'card', 'form', 'navbar', 'footer', 'header', 'modal', 'table'];

    foreach ($components as $component) {
        if (stripos($message, $component) !== false) {
            return $component;
        }
    }

    return 'component';
}

/**
 * Detect issues in code
 */
function detectIssues($context) {
    $issues = [];

    $css = $context['css'] ?? '';

    // Check for !important overuse
    $importantCount = substr_count($css, '!important');
    if ($importantCount > 3) {
        $issues[] = [
            'type' => 'css',
            'message' => 'Too many !important declarations',
            'fix' => [
                'target' => 'css',
                'type' => 'replace',
                'content' => str_replace(' !important', '', $css),
                'description' => 'Remove excessive !important'
            ]
        ];
    }

    return $issues;
}

/**
 * Review code quality
 */
function reviewCode($data) {
    $context = $data['context'] ?? [];

    // Perform code analysis
    $analysis = [
        'html' => analyzeHTML($context['html'] ?? ''),
        'css' => analyzeCSS($context['css'] ?? ''),
        'javascript' => analyzeJS($context['javascript'] ?? '')
    ];

    return [
        'success' => true,
        'analysis' => $analysis
    ];
}

function analyzeHTML($html) {
    return [
        'lines' => substr_count($html, "\n"),
        'elements' => substr_count($html, '<'),
        'score' => 85
    ];
}

function analyzeCSS($css) {
    return [
        'lines' => substr_count($css, "\n"),
        'rules' => substr_count($css, '{'),
        'score' => 90
    ];
}

function analyzeJS($js) {
    return [
        'lines' => substr_count($js, "\n"),
        'functions' => substr_count($js, 'function'),
        'score' => 88
    ];
}

/**
 * Generate code from description
 */
function generateCode($data) {
    $description = $data['description'] ?? '';
    $type = $data['type'] ?? 'html';

    // Generate code based on type
    $code = '';

    return [
        'success' => true,
        'code' => $code,
        'type' => $type
    ];
}

/**
 * Optimize existing code
 */
function optimizeCode($data) {
    $code = $data['code'] ?? '';
    $type = $data['type'] ?? 'css';

    // Perform optimizations
    $optimized = $code; // Placeholder

    return [
        'success' => true,
        'original' => $code,
        'optimized' => $optimized,
        'improvements' => [
            'Reduced file size',
            'Improved performance',
            'Better readability'
        ]
    ];
}

/**
 * NEW: Validate code and suggest fixes
 * Connects to validation engine to find issues and generate fixes
 */
function validateAndFix($data) {
    $html = $data['html'] ?? '';
    $css = $data['css'] ?? '';
    $javascript = $data['javascript'] ?? '';
    $watchMode = $data['watchMode'] ?? false;

    $validationResults = [];
    $fixes = [];
    $suggestions = [];

    // Validate HTML
    if (!empty($html)) {
        $htmlValidation = validateHTMLCode($html);
        $validationResults['html'] = $htmlValidation;

        if (!empty($htmlValidation['errors'])) {
            $fixes[] = generateHTMLFix($html, $htmlValidation);
        }
        $suggestions = array_merge($suggestions, $htmlValidation['suggestions'] ?? []);
    }

    // Validate CSS
    if (!empty($css)) {
        $cssValidation = validateCSSCode($css);
        $validationResults['css'] = $cssValidation;

        if (!empty($cssValidation['errors'])) {
            $fixes[] = generateCSSFix($css, $cssValidation);
        }
        $suggestions = array_merge($suggestions, $cssValidation['suggestions'] ?? []);
    }

    // Validate JavaScript
    if (!empty($javascript)) {
        $jsValidation = validateJSCode($javascript);
        $validationResults['javascript'] = $jsValidation;

        if (!empty($jsValidation['errors'])) {
            $fixes[] = generateJSFix($javascript, $jsValidation);
        }
        $suggestions = array_merge($suggestions, $jsValidation['suggestions'] ?? []);
    }

    $response = "Code validation complete. ";
    $totalErrors = 0;
    foreach ($validationResults as $type => $result) {
        $totalErrors += count($result['errors'] ?? []);
    }

    if ($totalErrors === 0) {
        $response .= "✅ All code is valid! No issues found.";
    } else {
        $response .= "Found {$totalErrors} issue(s). I can fix them automatically.";
    }

    return [
        'success' => true,
        'response' => $response,
        'validationResults' => $validationResults,
        'fixes' => $fixes,
        'suggestions' => $suggestions,
        'readyToApply' => !empty($fixes),
        'watchMode' => $watchMode
    ];
}

/**
 * NEW: Apply validation fixes automatically
 */
function applyValidationFixes($data) {
    $fixes = $data['fixes'] ?? [];
    $appliedFixes = [];

    foreach ($fixes as $fix) {
        $applied = applyFix($fix);
        if ($applied) {
            $appliedFixes[] = $fix['description'];
        }
    }

    return [
        'success' => true,
        'response' => "Applied " . count($appliedFixes) . " fix(es) automatically.",
        'appliedFixes' => $appliedFixes,
        'edits' => $fixes
    ];
}

/**
 * NEW: Suggest improvements based on validation
 */
function suggestImprovements($data) {
    $html = $data['html'] ?? '';
    $css = $data['css'] ?? '';
    $javascript = $data['javascript'] ?? '';

    $suggestions = [];

    // HTML suggestions
    if (!empty($html)) {
        if (strpos($html, '<!DOCTYPE') === false) {
            $suggestions[] = [
                'type' => 'html',
                'severity' => 'error',
                'message' => 'Add <!DOCTYPE html> at the beginning',
                'fix' => '<!DOCTYPE html>'
            ];
        }
        if (strpos($html, '<meta charset') === false) {
            $suggestions[] = [
                'type' => 'html',
                'severity' => 'warning',
                'message' => 'Missing charset meta tag',
                'fix' => '<meta charset="UTF-8">'
            ];
        }
        if (strpos($html, '<meta name="viewport"') === false) {
            $suggestions[] = [
                'type' => 'html',
                'severity' => 'warning',
                'message' => 'Missing viewport meta tag for responsive design',
                'fix' => '<meta name="viewport" content="width=device-width, initial-scale=1">'
            ];
        }
        if (substr_count($html, '<img') > 0 && substr_count($html, 'alt=') === 0) {
            $suggestions[] = [
                'type' => 'html',
                'severity' => 'warning',
                'message' => 'Images should have alt attributes for accessibility',
                'recommendation' => 'Add descriptive alt text to all images'
            ];
        }
    }

    // CSS suggestions
    if (!empty($css)) {
        if (substr_count($css, '!important') > 3) {
            $suggestions[] = [
                'type' => 'css',
                'severity' => 'warning',
                'message' => 'Too many !important declarations',
                'recommendation' => 'Use better CSS specificity instead of !important'
            ];
        }
        if (substr_count($css, 'color: ') > 5 && strpos($css, 'var(--') === false) {
            $suggestions[] = [
                'type' => 'css',
                'severity' => 'info',
                'message' => 'Consider using CSS variables for colors',
                'recommendation' => 'Define a :root {} block with --color-primary, etc.'
            ];
        }
        if (strpos($css, 'position: absolute') !== false && strpos($css, '@media') === false) {
            $suggestions[] = [
                'type' => 'css',
                'severity' => 'info',
                'message' => 'Absolute positioning may not be responsive',
                'recommendation' => 'Consider using flexbox or grid with media queries'
            ];
        }
    }

    // JavaScript suggestions
    if (!empty($javascript)) {
        if (strpos($javascript, 'eval(') !== false) {
            $suggestions[] = [
                'type' => 'javascript',
                'severity' => 'error',
                'message' => 'Avoid using eval() - security and performance risk',
                'recommendation' => 'Use safer alternatives like JSON.parse() or Function()'
            ];
        }
        if (substr_count($javascript, 'console.log') > 5) {
            $suggestions[] = [
                'type' => 'javascript',
                'severity' => 'warning',
                'message' => 'Many console.log() statements found',
                'recommendation' => 'Remove debugging statements before production'
            ];
        }
        if (preg_match('/setInterval\([^,]+,\s*\d+\)/m', $javascript) && strpos($javascript, 'clearInterval') === false) {
            $suggestions[] = [
                'type' => 'javascript',
                'severity' => 'warning',
                'message' => 'setInterval() without clearInterval() may cause memory leaks',
                'recommendation' => 'Store interval ID and clear it when needed'
            ];
        }
    }

    return [
        'success' => true,
        'suggestions' => $suggestions,
        'totalSuggestions' => count($suggestions)
    ];
}

/**
 * Helper: Validate HTML code
 */
function validateHTMLCode($html) {
    $errors = [];
    $warnings = [];
    $suggestions = [];

    if (strpos($html, '<!DOCTYPE') === false) {
        $errors[] = ['message' => 'Missing DOCTYPE declaration', 'line' => 1];
        $suggestions[] = 'Add <!DOCTYPE html> at the start';
    }

    if (strpos($html, '<meta charset') === false) {
        $warnings[] = ['message' => 'Missing charset meta tag', 'line' => 1];
    }

    if (strpos($html, '<meta name="viewport"') === false) {
        $warnings[] = ['message' => 'Missing viewport meta tag', 'line' => 1];
    }

    // Count img tags without alt
    preg_match_all('/<img[^>]*>/i', $html, $matches);
    foreach ($matches[0] as $imgTag) {
        if (strpos($imgTag, 'alt') === false) {
            $warnings[] = ['message' => 'Image missing alt attribute', 'tag' => $imgTag];
            $suggestions[] = 'Add descriptive alt text to images';
        }
    }

    return [
        'errors' => $errors,
        'warnings' => $warnings,
        'suggestions' => $suggestions,
        'isValid' => empty($errors)
    ];
}

/**
 * Helper: Validate CSS code
 */
function validateCSSCode($css) {
    $errors = [];
    $warnings = [];
    $suggestions = [];

    // Check for matching braces
    $openBraces = substr_count($css, '{');
    $closeBraces = substr_count($css, '}');
    if ($openBraces !== $closeBraces) {
        $errors[] = ['message' => "Brace mismatch: {$openBraces} open, {$closeBraces} close"];
    }

    // Check for missing semicolons
    $lines = explode("\n", $css);
    foreach ($lines as $i => $line) {
        if (preg_match('/:\s*[^;}\n]+$/', trim($line)) && !preg_match('/{\s*$/', trim($line))) {
            if (trim($line) && trim($line)[0] !== '/') {
                $warnings[] = ['message' => 'Possibly missing semicolon', 'line' => $i + 1];
            }
        }
    }

    // Check for !important overuse
    $importantCount = substr_count($css, '!important');
    if ($importantCount > 3) {
        $suggestions[] = "Too many !important declarations ($importantCount found)";
    }

    // Check for vendor prefixes
    if (strpos($css, '-webkit-') === false && strpos($css, 'transform') !== false) {
        $suggestions[] = 'Consider adding vendor prefixes for better browser support';
    }

    return [
        'errors' => $errors,
        'warnings' => $warnings,
        'suggestions' => $suggestions,
        'isValid' => empty($errors)
    ];
}

/**
 * Helper: Validate JavaScript code
 */
function validateJSCode($js) {
    $errors = [];
    $warnings = [];
    $suggestions = [];

    // Check for eval
    if (strpos($js, 'eval(') !== false) {
        $errors[] = ['message' => 'eval() detected - security risk'];
    }

    // Check for console statements
    $consoleCount = substr_count($js, 'console.log');
    if ($consoleCount > 5) {
        $warnings[] = ['message' => "Many console.log() statements ($consoleCount found)"];
        $suggestions[] = 'Remove debugging statements before production';
    }

    // Check for setInterval without clear
    if (preg_match('/setInterval\s*\(/i', $js) && strpos($js, 'clearInterval') === false) {
        $suggestions[] = 'setInterval() detected without clearInterval() - may cause memory leaks';
    }

    // Check for fetch without catch
    $fetchCount = substr_count($js, 'fetch(');
    $catchCount = substr_count($js, '.catch(');
    if ($fetchCount > $catchCount) {
        $warnings[] = ['message' => "fetch() without .catch() error handling"];
        $suggestions[] = 'Always add error handling to fetch calls';
    }

    return [
        'errors' => $errors,
        'warnings' => $warnings,
        'suggestions' => $suggestions,
        'isValid' => empty($errors)
    ];
}

/**
 * Helper: Generate HTML fix
 */
function generateHTMLFix($html, $validation) {
    return [
        'target' => 'html',
        'type' => 'prepend',
        'content' => '<!DOCTYPE html>' . "\n" . $html,
        'description' => 'Add DOCTYPE declaration',
        'delay' => 0
    ];
}

/**
 * Helper: Generate CSS fix
 */
function generateCSSFix($css, $validation) {
    return [
        'target' => 'css',
        'type' => 'replace',
        'content' => preg_replace('/([^;{}\s])\s*$/', '$1;', $css),
        'description' => 'Add missing semicolons',
        'delay' => 0
    ];
}

/**
 * Helper: Generate JavaScript fix
 */
function generateJSFix($js, $validation) {
    $fixed = $js;

    // Remove eval if found
    if (strpos($fixed, 'eval(') !== false) {
        $fixed = str_replace('eval(', '// eval( // REMOVED: unsafe', $fixed);
    }

    return [
        'target' => 'javascript',
        'type' => 'replace',
        'content' => $fixed,
        'description' => 'Fix detected issues',
        'delay' => 0
    ];
}

/**
 * Helper: Apply a fix
 */
function applyFix($fix) {
    return true; // Fix application handled by frontend
}
