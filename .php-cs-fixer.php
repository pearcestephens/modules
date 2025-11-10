<?php
/**
 * PHP CS Fixer Configuration - PSR-12 + Enterprise Standards
 *
 * Automated code style fixing for CIS Modules
 * Standards: PSR-12, Symfony, PHPUnit
 *
 * Usage:
 *   vendor/bin/php-cs-fixer fix
 *   vendor/bin/php-cs-fixer fix --dry-run --diff
 *   make cs-fix
 *   make cs-check
 *
 * @see https://cs.symfony.com/
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/shared/services/crawler/src',
        __DIR__ . '/shared/services/product-intelligence/src',
        __DIR__ . '/app',
        __DIR__ . '/base/src',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'vendor',
        'node_modules',
        'cache',
        'storage',
        'temp',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        // PSR Standards
        '@PSR12' => true,
        '@PSR12:risky' => true,

        // Symfony Standards
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // PHPUnit
        '@PHPUnit84Migration:risky' => true,

        // Array notation
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

        // Binary operators
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '=' => 'align_single_space_minimal',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],
        'operator_linebreak' => ['only_booleans' => true, 'position' => 'beginning'],

        // Casting
        'cast_spaces' => ['space' => 'single'],
        'modernize_types_casting' => true,
        'no_short_bool_cast' => true,
        'short_scalar_cast' => true,

        // Classes
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'no_blank_lines_after_class_opening' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],
        'self_accessor' => true,
        'single_class_element_per_statement' => true,
        'visibility_required' => ['elements' => ['property', 'method', 'const']],

        // Comments
        'comment_to_phpdoc' => true,
        'multiline_comment_opening_closing' => true,
        'no_empty_comment' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Control structures
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'simplified_if_return' => true,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

        // Declarations
        'declare_strict_types' => true,

        // Doctrine
        // '@DoctrineAnnotation' => true,

        // Functions
        'function_declaration' => ['closure_function_spacing' => 'one'],
        'function_typehint_space' => true,
        'lambda_not_used_import' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'no_spaces_after_function_name' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'single_line_throw' => false,

        // Imports
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,

        // Language constructs
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'declare_equal_normalize' => ['space' => 'none'],
        'explicit_indirect_variable' => true,
        'explicit_string_variable' => true,
        'no_alternative_syntax' => true,

        // Namespaces
        'blank_line_after_namespace' => true,
        'no_leading_namespace_whitespace' => true,

        // Operators
        'increment_style' => ['style' => 'post'],
        'logical_operators' => true,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'standardize_increment' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_null_coalescing' => true,
        'unary_operator_spaces' => true,

        // PHPDoc
        'align_multiline_comment' => ['comment_type' => 'phpdocs_like'],
        'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],
        'phpdoc_no_access' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => true,
        'phpdoc_tag_type' => ['tags' => ['inheritdoc' => 'inline']],
        'phpdoc_to_comment' => false,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'alpha'],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,

        // Return
        'no_useless_return' => true,
        'return_assignment' => true,
        'simplified_null_return' => false,

        // Semicolons
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => true,
        'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],

        // Strict
        'strict_comparison' => true,
        'strict_param' => true,

        // Strings
        'escape_implicit_backslashes' => true,
        'explicit_string_variable' => true,
        'heredoc_to_nowdoc' => true,
        'no_trailing_whitespace_in_string' => true,
        'simple_to_complex_string_variable' => true,
        'single_quote' => true,

        // Whitespace
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'compact_nullable_typehint' => true,
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'continue',
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],
        'no_spaces_around_offset' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
