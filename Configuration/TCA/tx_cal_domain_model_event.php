<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\TCA\ItemsProcFunc;
use MarioMatzulla\Cal\Backend\TCA\Labels;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cal');

$sPid = '###CURRENT_PID###'; // storage pid????

return [
    'ctrl' => [
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY start_date DESC, start_time DESC',
        'delete' => 'deleted',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'languageField' => 'sys_language_uid',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'cal-eventtype-standard',
            0 => 'cal-eventtype-standard',
            1 => 'cal-eventtype-intlnk',
            2 => 'cal-eventtype-exturl',
        ],
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_event.svg',
        'label_userFunc' => Labels::class . '->getEventRecordLabel',
        // Not shown (label_userFunc builds the label), but the record list selects these fields,
        // so the label function does not need a database query per record
        'label_alt' => 'start_date,start_time,allday',
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
        'crdate' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'slug' => [
            'exclude' => true,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal.slug',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal.slug.description',
            'config' => [
                'type' => 'slug',
                'generatorOptions' => [
                    'fields' => ['title'],
                    'replacements' => [
                        '/' => '-',
                    ],
                ],
                'fallbackCharacter' => '-',
                // unique in the table: the route enhancer resolves the slug in the whole table, records outside of a site would get duplicates with uniqueInSite
                'eval' => 'unique',
                'default' => '',
            ],
        ],
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.title',
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
        'calendar_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.calendar',
            'onChange' => 'reload',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'itemsProcFunc' => ItemsProcFunc::class . '->getRecords',
                'itemsProcFunc_config' => [
                    'table' => 'tx_cal_domain_model_calendar',
                    'orderBy' => 'tx_cal_domain_model_calendar.title',
                ],
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
                    'editPopup' => [
                        'disabled' => '',
                        'options' => [
                            'windowOpenParameters' => 'height=500,width=660,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_calendar.edit',
                        ],
                    ],
                ],
            ],
        ],
        'start_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start_date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'required' => true,
                'default' => 0,
            ],
        ],
        'start_time' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => [
                'type' => 'datetime',
                'format' => 'time',
                'default' => 0,
            ],
        ],
        'end_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end_date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'required' => true,
                'default' => 0,
            ],
        ],
        'end_time' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => [
                'type' => 'datetime',
                'format' => 'time',
                'default' => 0,
            ],
        ],
        'allday' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.allday',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'organizer' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
            ],
        ],
        'organizer_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_id',
            'config' => [
                'type' => 'group',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
                'allowed' => 'tx_cal_domain_model_organizer',
                'fieldControl' => [
                    'addRecord' => [
                        'disabled' => '',
                        'options' => [
                            'pid' => $sPid,
                            'setValue' => 'set',
                            'table' => 'tx_cal_domain_model_organizer',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_organizer.createNew',
                        ],
                    ],
                    'editPopup' => [
                        'disabled' => '',
                        'options' => [
                            'windowOpenParameters' => 'height=600,width=525,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_organizer.edit',
                        ],
                    ],
                ],
            ],
        ],
        'organizer_pid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_pid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => '0',
                'default' => 0,
            ],
        ],
        'organizer_link' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_link',
            'config' => [
                'type' => 'link',
                'size' => 25,
            ],
        ],
        'location' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
            ],
        ],
        'location_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_id',
            'config' => [
                'type' => 'group',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
                'allowed' => 'tx_cal_domain_model_location',
                'fieldControl' => [
                    'addRecord' => [
                        'disabled' => '',
                        'options' => [
                            'pid' => $sPid,
                            'setValue' => 'set',
                            'table' => 'tx_cal_domain_model_location',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.createNew',
                        ],
                    ],
                    'editPopup' => [
                        'disabled' => '',
                        'options' => [
                            'windowOpenParameters' => 'height=600,width=525,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.edit',
                        ],
                    ],
                ],
            ],
        ],
        'location_pid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_pid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => '0',
                'default' => 0,
            ],
        ],
        'location_link' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_link',
            'config' => [
                'type' => 'link',
                'size' => 25,
                'default' => 0,
            ],
        ],
        'teaser' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.teaser',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 6,
                'softref' => 'rtehtmlarea_images,typolink_tag,images,email[subst],url',
                'enableRichtext' => true,
                'fieldControl' => [
                    'fullScreenRichtext' => [
                        'disabled' => '',
                        'options' => [
                            'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext.W.RTE',
                        ],
                    ],
                ],
            ],
        ],
        'description' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 6,
                'softref' => 'rtehtmlarea_images,typolink_tag,images,email[subst],url',
                'enableRichtext' => true,
                'fieldControl' => [
                    'fullScreenRichtext' => [
                        'disabled' => '',
                        'options' => [
                            'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext.W.RTE',
                        ],
                    ],
                ],
            ],
        ],
        'freq' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.freq',
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
        'status' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status.none', 'value' => ''],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status.confirmed', 'value' => 'CONFIRMED'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status.tentative', 'value' => 'TENTATIVE'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status.cancelled', 'value' => 'CANCELLED'],
                ],
                'default' => '',
            ],
        ],
        'classification' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.classification',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.classification.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.classification.public', 'value' => ''],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.classification.private', 'value' => 'PRIVATE'],
                    ['label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.classification.confidential', 'value' => 'CONFIDENTIAL'],
                ],
                'default' => '',
            ],
        ],
        'rrule' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.rrule',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.rrule.description',
            'displayCond' => 'FIELD:rrule:REQ:true',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'size' => 50,
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
        'until' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.until',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'format' => 'date',
            ],
        ],
        'cnt' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.count',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'eval' => 'num',
            ],
        ],
        'intrval' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.interval',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'eval' => 'num',
                'default' => '1',
            ],
        ],
        'rdate_type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.rdate_type',
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
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.rdate',
            'displayCond' => 'FIELD:rdate_type:IN:date_time,date,period',
            'config' => [
                'type' => 'user',
                'renderType' => 'calRDateElement',
                'default' => '',
            ],
        ],
        'deviation' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.deviation',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_cal_domain_model_eventdeviation',
                'foreign_field' => 'parentid',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'useSortable' => true,
                ],
                'maxitems' => 10,
            ],
        ],
        'exception_cnt' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.exception',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_cal_domain_model_exception_event,tx_cal_domain_model_exception_event_group',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 100,
                'MM' => 'tx_cal_domain_model_exception_event_mm',
            ],
        ],

        /* new */
        'type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.type',
            'config' => [
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => [
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.type.I.0',
                        'value' => 0,
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.type.I.1',
                        'value' => 1,
                    ],
                    [
                        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.type.I.2',
                        'value' => 2,
                    ],
                ],
                'default' => 0,
            ],
        ],

        'ext_url' => [
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.external',
            'config' => [
                'type' => 'link',
                'size' => 40,
                'default' => '',
                'required' => true,
            ],
        ],

        'page' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.shortcut_page',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => '0',
                'required' => true,
            ],
        ],
        /* new */
        'image' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.images',
            'config' => [
                'type' => 'file',
                'maxitems' => 6,
                'allowed' => 'common-image-types',
            ],
        ],

        'attachment' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media',
            'config' => [
                'type' => 'file',
                'maxitems' => 6,
                'allowed' => 'common-media-types',
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
                'foreign_table' => 'tx_cal_domain_model_event',
                'foreign_table_where' => 'AND tx_cal_domain_model_event.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
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
        'categories' => [
            'config' => [
                'type' => 'category',
            ],
        ],
        'isTemp' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.isTemp',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.isTemp.description',
            'displayCond' => 'FIELD:isTemp:=:1',
            'config' => [
                'type' => 'check',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'caldav_data' => [
            'exclude' => 1,
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.general_sheet,type, title, slug, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end;6,calendar_id,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status_palette;8,categories,' . ($configuration['useTeaser'] ? 'teaser,' : '') . 'description,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.recurrence_sheet, freq, rrule, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_sheet,' . ($configuration['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_sheet,' . ($configuration['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.files_sheet,image,attachment',
        ],
        '1' => [
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.general_sheet,type, page,title, slug, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end;6,calendar_id,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status_palette;8,categories,' . ($configuration['useTeaser'] ? 'teaser,' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.recurrence_sheet, freq, rrule, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_sheet,' . ($configuration['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_sheet,' . ($configuration['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . '',
        ],
        '2' => [
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.general_sheet,type, ext_url,title, slug, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end;6,calendar_id,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.status_palette;8,categories,' . ($configuration['useTeaser'] ? 'teaser,' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.recurrence_sheet, freq, rrule, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_sheet,' . ($configuration['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_sheet,' . ($configuration['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . '',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => 'hidden,isTemp,l10n_parent,sys_language_uid,t3ver_label',
            'canNotCollapse' => 1,
        ],
        '2' => [
            'showitem' => 'until, cnt, intrval',
            'canNotCollapse' => 1,
        ],
        '5' => [
            'showitem' => 'allday,--linebreak--,start_date,start_time',
            'canNotCollapse' => 1,
        ],
        '6' => [
            'showitem' => 'end_date,end_time',
            'canNotCollapse' => 1,
        ],
        '7' => [
            'showitem' => 'rdate',
            'canNotCollapse' => 1,
        ],
        '8' => [
            'showitem' => 'status, classification',
        ],
    ],
];
