<?php

declare(strict_types=1);

defined('TYPO3') or die();

$lll = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_cal_unknown_users',
        'label' => 'email',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'email',
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_unknown_users.svg',
    ],
    'columns' => [
        'email' => [
            'label' => $lll . 'tx_cal_unknown_users.email',
            'config' => [
                'type' => 'email',
                'size' => 30,
                'required' => true,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'email',
        ],
    ],
];
