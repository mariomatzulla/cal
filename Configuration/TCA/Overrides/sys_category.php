<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Color of the events of this category in the calendar (cal)
ExtensionManagementUtility::addTCAcolumns('sys_category', [
    'tx_cal_color' => [
        'exclude' => true,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color',
        'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.description',
        'onChange' => 'reload',
        'config' => [
            'type' => 'color',
            'default' => '',
            'valuePicker' => [
                'items' => [
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.blue', 'value' => '#668cd9'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.green', 'value' => '#4cb052'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.orange', 'value' => '#f2a640'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.red', 'value' => '#d96666'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.pink', 'value' => '#e67399'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.yellow', 'value' => '#e0c240'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.brown', 'value' => '#b08b59'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color.grey', 'value' => '#999999'],
                ],
            ],
        ],
    ],
    'tx_cal_color_priority' => [
        'exclude' => true,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color_priority',
        'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.tx_cal_color_priority.description',
        'displayCond' => 'FIELD:tx_cal_color:REQ:true',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'range' => [
                'lower' => 0,
                'upper' => 999,
            ],
            'default' => 0,
        ],
    ],
]);

$GLOBALS['TCA']['sys_category']['palettes']['tx_cal_color'] = [
    'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:sys_category.palette.tx_cal_color',
    'showitem' => 'tx_cal_color, tx_cal_color_priority',
];
ExtensionManagementUtility::addToAllTCAtypes('sys_category', '--palette--;;tx_cal_color', '', 'after:title');
