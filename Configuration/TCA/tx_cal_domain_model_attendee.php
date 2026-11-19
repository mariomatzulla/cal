<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\TCA\Labels;

defined('TYPO3') or die();

$lll = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_cal_attendee',
        'label' => 'email',
        'label_userFunc' => Labels::class . '->getAttendeeRecordLabel',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'versioningWS' => true,
        'hideTable' => true,
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_attendee.svg',
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'event_id' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'fe_user_id' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_attendee.fe_user_id',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'fe_group_id' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_attendee.fe_group_id',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_groups',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'email' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_attendee.email',
            'config' => [
                'type' => 'email',
                'size' => 30,
            ],
        ],
        'attendance' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_attendee.attendance',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $lll . 'tx_cal_attendee.attendance.NON', 'value' => 'NON'],
                    ['label' => $lll . 'tx_cal_attendee.attendance.OPT-PARTICIPANT', 'value' => 'OPT-PARTICIPANT'],
                    ['label' => $lll . 'tx_cal_attendee.attendance.REQ-PARTICIPANT', 'value' => 'REQ-PARTICIPANT'],
                    ['label' => $lll . 'tx_cal_attendee.attendance.CHAIR', 'value' => 'CHAIR'],
                ],
                'default' => 'REQ-PARTICIPANT',
            ],
        ],
        'status' => [
            'exclude' => true,
            'label' => $lll . 'tx_cal_attendee.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $lll . 'tx_cal_attendee.status.0', 'value' => ''],
                    ['label' => $lll . 'tx_cal_attendee.status.NEEDS-ACTION', 'value' => 'NEEDS-ACTION'],
                    ['label' => $lll . 'tx_cal_attendee.status.ACCEPTED', 'value' => 'ACCEPTED'],
                    ['label' => $lll . 'tx_cal_attendee.status.DECLINE', 'value' => 'DECLINE'],
                    ['label' => $lll . 'tx_cal_attendee.status.TENTATIVE', 'value' => 'TENTATIVE'],
                    ['label' => $lll . 'tx_cal_attendee.status.DELEGATED', 'value' => 'DELEGATED'],
                ],
                'default' => '',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'fe_user_id, fe_group_id, email, --palette--;;state, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ],
    ],
    'palettes' => [
        'state' => [
            'showitem' => 'attendance, status',
        ],
    ],
];
