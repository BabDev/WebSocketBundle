<?php declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP82Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_opening_tag' => false,
        'fopen_flags' => false,
        'linebreak_after_opening_tag' => false,
        'no_superfluous_phpdoc_tags' => ['remove_inheritdoc' => true],
        'nullable_type_declaration_for_default_null_value' => ['use_nullable_type_declaration' => true],
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'single_line_empty_body' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__.'/src')
            ->in(__DIR__.'/tests')
    )
;
