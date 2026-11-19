<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\Modul\IcsGeneratorController;
use MarioMatzulla\Cal\Backend\Modul\IndexController;

return [
    'cal_indexer_module' => [
        'parent' => 'system',
        'access' => 'systemMaintainer',
        'path' => '/module/system/cal-indexer-module',
        'iconIdentifier' => 'tx_cal-indexer-module',
        'labels' => 'LLL:EXT:cal/Resources/Private/Language/locallang_indexer.xlf',
        'extensionName' => 'cal',
        'controllerActions' => [
            IndexController::class => [
                'index',
                'generate',
                'result',
            ],
        ],
    ],
    'cal_ics_generator_module' => [
        'parent' => 'system',
        'access' => 'systemMaintainer',
        'path' => '/module/system/cal-ics-generator-module',
        'iconIdentifier' => 'tx-cal-ics-generator-icon',
        'labels' => 'LLL:EXT:cal/Resources/Private/Language/locallang_ics_generator.xlf',
        'extensionName' => 'cal',
        'controllerActions' => [
            IcsGeneratorController::class => [
                'index',
                'generate',
                'check',
            ],
        ],
    ],
];
