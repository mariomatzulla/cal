<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\TCA\Labels;

defined('TYPO3') or die();

$lll = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

/*
 * Subscriptions ("monitoring") of an event, edited inline in tx_cal_domain_model_event.monitor_cnt.
 * The record type is the subscriber table (fe_users, fe_groups, tx_cal_domain_model_unknown_users),
 * uid_foreign holds the uid of the subscriber.
 */
$subscriberField = static fn(string $table): array => [
    'uid_foreign' => [
        'config' => [
            'allowed' => $table,
        ],
    ],
];

return [
    'ctrl' => [
        'title' => $lll . 'tx_cal_fe_user_event.monitor',
        'label' => 'uid_foreign',
        'label_userFunc' => Labels::class . '->getMonitoringRecordLabel',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'type' => 'tablenames',
        'sortby' => 'sorting',
        'versioningWS' => true,
        'hideTable' => true,
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_fe_user_event_monitor_mm.svg',
    ],
    'columns' => [
        'uid_local' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'tablenames' => [
            'label' => $lll . 'tx_cal_fe_user_event.fe_user',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:fe_users', 'value' => 'fe_users'],
                    ['label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:fe_groups', 'value' => 'fe_groups'],
                    ['label' => $lll . 'tx_cal_unknown_users', 'value' => 'tx_cal_domain_model_unknown_users'],
                ],
                'default' => 'fe_users',
            ],
        ],
        'uid_foreign' => [
            'label' => $lll . 'tx_cal_fe_user_event.fe_user',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'offset' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_fe_user_event.offset',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'schedulerId' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
    'types' => [
        'fe_users' => [
            'showitem' => 'tablenames, uid_foreign, offset',
            'columnsOverrides' => $subscriberField('fe_users'),
        ],
        'fe_groups' => [
            'showitem' => 'tablenames, uid_foreign, offset',
            'columnsOverrides' => $subscriberField('fe_groups'),
        ],
        'tx_cal_domain_model_unknown_users' => [
            'showitem' => 'tablenames, uid_foreign, offset',
            'columnsOverrides' => $subscriberField('tx_cal_domain_model_unknown_users'),
        ],
    ],
];
