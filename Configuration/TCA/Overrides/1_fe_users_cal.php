<?php

declare(strict_types=1);

defined('TYPO3') or die();

$sPid = '###CURRENT_PID###'; // storage pid????

// Define the TCA for the access control calendar selector.
$tempColumns = [
    'tx_cal_calendar' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar_private',
        'config' => [
            'type' => 'group',
            'allowed' => 'tx_cal_domain_model_calendar',
            'minitems' => 0,
            'maxitems' => 99,
            'fieldControl' => [
                'addRecord' => [
                    'disabled' => '',
                    'options' => [
                        'pid' => $sPid,
                        'setValue' => 'set',
                        'table' => 'tx_cal_domain_model_calendar',
                        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.createNew',
                    ],
                ],
            ],
        ],
    ],
    'tx_cal_calendar_subscription' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar_subscription',
        'config' => [
            'type' => 'group',
            'allowed' => 'tx_cal_domain_model_calendar',
            'minitems' => 0,
            'maxitems' => 99,
            'fieldControl' => [
                'addRecord' => [
                    'disabled' => '',
                    'options' => [
                        'pid' => $sPid,
                        'setValue' => 'set',
                        'table' => 'tx_cal_domain_model_calendar',
                        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.createNew',
                    ],
                ],
            ],
        ],
    ],
];

// Add the calendar selector for backend users.
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'tx_cal_calendar,tx_cal_calendar_subscription',
);
