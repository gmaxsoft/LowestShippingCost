<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/lowestshipping')
    ->exclude('vendor')
    ->name('*.php')
    ->notPath('var');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        'blank_line_after_opening_tag' => false,
        'declare_strict_types' => true,
        'function_declaration' => [
            'closure_fn_spacing' => 'one',
            'closure_function_spacing' => 'one',
        ],
        'new_with_parentheses' => [
            'anonymous_class' => true,
            'named_class' => true,
        ],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_line_span' => ['const' => 'single', 'method' => 'multi', 'property' => 'single'],
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => ['order' => ['param', 'return', 'throws']],
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'phpdoc_var_annotation_correct_order' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],
    ])
    ->setFinder($finder);
