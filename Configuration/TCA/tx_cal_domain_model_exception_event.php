<?php

declare(strict_types=1);

$tx_cal_domain_model_exception_event = [
    'ctrl' => [
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY start_date DESC',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'versioningWS' => true,
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_deviation.svg',
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
                'required' => true,
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'default' => 0,
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'default' => 0,
            ],
        ],
        'start_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.start_date',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'tx_cal_event' => 'start_date',
                'required' => true,
                'format' => 'date',
            ],
        ],
        'end_date' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],

        'freq' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.freq',
            'onChange' => 'reload',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:frequency.none',
                        'value' => 'none',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:frequency.day',
                        'value' => 'day',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:frequency.week',
                        'value' => 'week',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:frequency.month',
                        'value' => 'month',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:frequency.year',
                        'value' => 'year',
                    ],
                ],
            ],
        ],

        'byday' => [
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:week,month,year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_short',
            'config' => [
                'type' => 'user',
                'renderType' => 'calByDayElement',
                'default' => '',
            ],
        ],

        'bymonthday' => [
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:month,year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonthday_short',
            'config' => [
                'type' => 'user',
                'renderType' => 'calByMonthDayElement',
                'default' => '',
            ],
        ],

        'bymonth' => [
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_short',
            'config' => [
                'type' => 'user',
                'renderType' => 'calByMonthElement',
                'default' => '',
            ],
        ],

        'rdate_type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.rdate_type',
            'onChange' => 'reload',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:rdate_type.none',
                        'value' => 'none',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:rdate_type.date',
                        'value' => 'date',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:rdate_type.date_time',
                        'value' => 'date_time',
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:rdate_type.period',
                        'value' => 'period',
                    ],
                ],
                'default' => 'none',
            ],
        ],

        'rdate' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.rdate',
            'displayCond' => 'FIELD:rdate_type:IN:date_time,date,period',
            'config' => [
                'type' => 'user',
                'renderType' => 'calRDateElement',
                'default' => '',
            ],
        ],

        'until' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.until',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'format' => 'date',
            ],
        ],

        'cnt' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.count',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'eval' => 'num',
                'default' => 0,
            ],
        ],

        'intrval' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_exception_event.interval',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'eval' => 'num',
                'default' => '1',
            ],
        ],
        't3ver_label' => [
            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'none',
                'size' => 27,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'title, --palette--;;1,start_date,end_date, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;3, monitor_cnt',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => 'hidden,t3ver_label',
        ],
        '2' => [
            'showitem' => 'until, cnt, intrval',
            'canNotCollapse' => 1,
        ],
        '3' => [
            'showitem' => 'rdate',
            'canNotCollapse' => 1,
        ],
    ],
];

return $tx_cal_domain_model_exception_event;
