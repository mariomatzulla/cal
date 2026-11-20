<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'cal-calendar-standard',
            1 => 'cal-calendar-exturl',
            2 => 'cal-calendar-ics',
        ],
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'languageField' => 'sys_language_uid',
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_domain_model_calendar.svg',
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
            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
                'eval' => 'unique',
                'required' => true,
            ],
        ],
        'owner' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.owner',
            'config' => [
                'type' => 'group',
                'MM' => 'tx_cal_calendar_user_group_mm',
                'size' => 4,
                'minitems' => 0,
                'autoSizeMax' => 25,
                'maxitems' => 500,
                'allowed' => 'fe_users,fe_groups',
                'default' => 0,
            ],
        ],
        'timezone' => [
            'exclude' => true,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.timezone',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '(UTC-12:00) Bakerinsel, Howlandinsel', 'value' => 'Etc/GMT+12'],
                    ['label' => '(UTC-11:00) Pago Pago, Midway', 'value' => 'Pacific/Pago_Pago'],
                    ['label' => '(UTC-10:00) Honolulu, Hawaii', 'value' => 'Pacific/Honolulu'],
                    ['label' => '(UTC-09:00) Anchorage, Alaska', 'value' => 'America/Anchorage'],
                    ['label' => '(UTC-08:00) Los Angeles, Vancouver, Tijuana', 'value' => 'America/Los_Angeles'],
                    ['label' => '(UTC-07:00) Denver, Phoenix, Calgary', 'value' => 'America/Denver'],
                    ['label' => '(UTC-06:00) Chicago, Mexiko-Stadt, Winnipeg', 'value' => 'America/Chicago'],
                    ['label' => '(UTC-05:00) New York, Toronto, Bogotá', 'value' => 'America/New_York'],
                    ['label' => '(UTC-04:00) Santiago, Halifx, La Paz', 'value' => 'America/Santiago'],
                    ['label' => '(UTC-03:30) St. John’s (Neufundland)', 'value' => 'America/St_Johns'],
                    ['label' => '(UTC-03:00) Buenos Aires, São Paulo', 'value' => 'America/Argentina/Buenos_Aires'],
                    ['label' => '(UTC-02:00) Südgeorgien', 'value' => 'Atlantic/South_Georgia'],
                    ['label' => '(UTC-01:00) Azoren, Kap Verde', 'value' => 'Atlantic/Azores'],
                    ['label' => '(UTC±00:00) UTC / GMT – ohne Sommerzeit', 'value' => 'UTC'],
                    ['label' => '(UTC+00:00) London, Dublin, Lissabon – mit Sommerzeit', 'value' => 'Europe/London'],
                    ['label' => '(UTC+01:00) Berlin, Paris, Rom, Madrid, Wien, Zürich', 'value' => 'Europe/Berlin'],
                    ['label' => '(UTC+02:00) Athen, Kairo, Helsinki, Jerusalem', 'value' => 'Europe/Athens'],
                    ['label' => '(UTC+03:00) Moskau, Istanbul, Riad, Nairobi', 'value' => 'Europe/Moscow'],
                    ['label' => '(UTC+03:30) Teheran', 'value' => 'Asia/Tehran'],
                    ['label' => '(UTC+04:00) Dubai, Baku, Eriwan', 'value' => 'Asia/Dubai'],
                    ['label' => '(UTC+04:30) Kabul', 'value' => 'Asia/Kabul'],
                    ['label' => '(UTC+05:00) Karatschi, Taschkent', 'value' => 'Asia/Karachi'],
                    ['label' => '(UTC+05:30) Neu-Delhi, Mumbai, Kolkata', 'value' => 'Asia/Kolkata'],
                    ['label' => '(UTC+05:45) Kathmandu', 'value' => 'Asia/Kathmandu'],
                    ['label' => '(UTC+06:00) Dhaka, Almaty', 'value' => 'Asia/Dhaka'],
                    ['label' => '(UTC+06:30) Yangon (Rangun)', 'value' => 'Asia/Yangon'],
                    ['label' => '(UTC+07:00) Bangkok, Jakarta, Hanoi', 'value' => 'Asia/Bangkok'],
                    ['label' => '(UTC+08:00) Singapur, Peking, Hongkong, Perth', 'value' => 'Asia/Singapore'],
                    ['label' => '(UTC+08:45) Eucla', 'value' => 'Australia/Eucla'],
                    ['label' => '(UTC+09:00) Tokio, Seoul', 'value' => 'Asia/Tokyo'],
                    ['label' => '(UTC+09:30) Adelaide, Darwin', 'value' => 'Australia/Adelaide'],
                    ['label' => '(UTC+10:00) Sydney, Melbourne, Brisbane', 'value' => 'Australia/Sydney'],
                    ['label' => '(UTC+10:30) Lord-Howe-Insel', 'value' => 'Australia/Lord_Howe'],
                    ['label' => '(UTC+11:00) Salomonen, Neukaledonien', 'value' => 'Pacific/Guadalcanal'],
                    ['label' => '(UTC+12:00) Auckland, Fiji', 'value' => 'Pacific/Auckland'],
                    ['label' => '(UTC+12:45) Chatham-Inseln', 'value' => 'Pacific/Chatham'],
                    ['label' => '(UTC+13:00) Nukuʻalofa (Tonga), Apia (Samoa)', 'value' => 'Pacific/Tongatapu'],
                    ['label' => '(UTC+14:00) Kiritimati (Line Islands)', 'value' => 'Pacific/Kiritimati'],
                ],
                'default' => 'Europe/Berlin',
            ],
        ],
        'activate_fnb' => [
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.activate_fnb',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'fnb_user_cnt' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.fb_users_groups',
            'displayCond' => 'FIELD:activate_fnb:=:1',
            'config' => [
                'type' => 'group',
                'MM' => 'tx_cal_calendar_fnb_user_group_mm',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 100,
                'allowed' => 'fe_users,fe_groups',
            ],
        ],
        'nearby' => [
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.nearby',
            'config' => [
                'type' => 'check',
            ],
        ],
        'type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.type',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.type.I.0',
                        'value' => 0,
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.type.I.1',
                        'value' => 1,
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.type.I.2',
                        'value' => 2,
                    ],
                ],
                'default' => 0,
            ],
        ],

        'ext_url' => [
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.ext_url',
            'config' => [
                'type' => 'text',
                'default' => '',
            ],
        ],

        'ext_url_notes' => [
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.ext_url_notes',
            'config' => [
                'type' => 'text',
                'default' => '',
            ],
        ],

        'ics_file' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.ics_file',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.ics_file.description',
            'config' => [
                'type' => 'file',
                'allowed' => 'ics',
                'maxitems' => 10,
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.ics_file.add',
                    'fileUploadAllowed' => true,
                ],
            ],
        ],

        'refresh' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.refresh',
            'config' => [
                'type' => 'input',
                'size' => 6,
                'max' => 4,
                'eval' => 'num',
                'default' => 60,
            ],
        ],
        'schedulerId' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.schedulerId',
            'config' => [
                'type' => 'input',
                'size' => 5,
                'readOnly' => 1,
                'default' => 0,
            ],
        ],

        'md5' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'headerstyle' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_category.headerstyle',
            'config' => [
                'type' => 'input',
                'default' => '',
            ],
        ],
        'bodystyle' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_category.bodystyle',
            'config' => [
                'type' => 'input',
                'default' => '',
            ],
        ],
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' => [
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table' => 'tx_cal_domain_model_calendar',
                'foreign_table_where' => 'AND tx_cal_domain_model_calendar.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
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
            'showitem' => 'type,title, --palette--;;1,timezone,owner,headerstyle,bodystyle,activate_fnb,fnb_user_cnt,nearby',
        ],
        '1' => [
            'showitem' => 'type,title, --palette--;;1,timezone,owner,headerstyle,bodystyle,activate_fnb,fnb_user_cnt,nearby,ext_url,ext_url_notes,refresh,schedulerId',
        ],
        '2' => [
            'showitem' => 'type,title, --palette--;;1,timezone,owner,headerstyle,bodystyle,activate_fnb,fnb_user_cnt,nearby,ics_file,refresh,schedulerId',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => 'hidden,l10n_parent,sys_language_uid,t3ver_label',
        ],
    ],
];
