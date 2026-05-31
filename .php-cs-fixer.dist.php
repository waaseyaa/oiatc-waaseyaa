<?php

declare(strict_types=1);

// Mirrors waaseyaa/framework's .php-cs-fixer.dist.php, scoped to this app's
// src/ and tests/. Keep the rule set in sync with the framework so app code
// matches the conventions the framework is authored in.
$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    // php-cs-fixer 3.x has no PHP 8.5 support flag yet; the app targets 8.5.
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PHP8x5Migration' => true,
        'declare_strict_types' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
        'no_unused_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache');
