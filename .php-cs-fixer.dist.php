<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        // Keep inline "@var" docblocks: PHPStan relies on them.
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
    ])
    ->setFinder($finder)
;
