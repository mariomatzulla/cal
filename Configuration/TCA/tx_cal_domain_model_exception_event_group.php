<?php

declare(strict_types=1);

$tx_cal_domain_model_exception_event_group =  [

    'ctrl' =>  [

        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event_group',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_exception_event_group.svg',
        'enablecolumns' =>  [

            'disabled' => 'hidden',
        ],
        'versioningWS' => true,
    ],
    'columns' =>  [

        'hidden' =>  [

            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' =>  [

                'type' => 'check',
                'default' => '0',
            ],
        ],
        'title' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event_group.title',
            'config' =>  [

                'type' => 'input',
                'size' => '30',
                'max' => '128',
                'required' => true,
            ],
        ],
        'exception_event_cnt' =>  [

            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event_group.exception_event_cnt',
            'config' =>  [

                'type' => 'group',
                'allowed' => 'tx_cal_domain_model_exception_event',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 100,
                'MM' => 'tx_cal_domain_model_exception_event_group_mm',
            ],
        ],
        't3ver_label' =>  [

            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.versionLabel',
            'config' =>  [

                'type' => 'none',
                'size' => 27,
            ],
        ],
    ],
    'types' =>  [

        '0' =>  [

            'showitem' => 'title, --palette--;;1,color,exception_event_cnt',
        ],
    ],
    'palettes' =>  [

        '1' =>  [

            'showitem' => 'hidden,t3ver_label',
        ],
    ],
];

return $tx_cal_domain_model_exception_event_group;
