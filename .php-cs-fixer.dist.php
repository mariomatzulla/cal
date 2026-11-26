<?php

declare(strict_types=1);

/*
 * PHP-CS-Fixer configuration following the TYPO3 Coding Guidelines.
 * Requires the dev dependency "typo3/coding-standards":
 *   composer install && composer cs:fix
 */

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__)
    ->exclude(['.Build', 'Documentation-GENERATED-temp', 'Resources', 'node_modules'])
    ->notName('ext_emconf.php');

return $config;
