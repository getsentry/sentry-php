<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP71Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
        ],
        'declare_strict_types' => true,
        'yoda_style' => true,
        'self_accessor' => false,
        'phpdoc_no_useless_inheritdoc' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_align' => [
            'tags' => ['param', 'return', 'throws', 'type', 'var'],
        ],
        'phpdoc_line_span' => [
            'const' => 'multi',
            'method' => 'multi',
            'property' => 'multi',
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude([
                'tests/resources',
                'tests/phpt',
                'tests/Fixtures',
            ])
    )
;
