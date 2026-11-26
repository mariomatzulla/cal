<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\TCA\Labels;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cal');

$sPid = '###CURRENT_PID###'; // storage pid????

return [
    'ctrl' =>  [

        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.deviation',
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
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_deviation.svg',
        'enablecolumns' =>  [

            'disabled' => 'hidden',
        ],
        'hideTable' => (bool)($configuration['hideDeviationRecords'] ?? true),
        'label_userFunc' => Labels::class . '->getDeviationRecordLabel',
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
        'parentid' => [

            'config' => [

                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.title',
            'config' => [

                'type' => 'input',
                'size' => 30,
                'max' => 128,
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
        'orig_start_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.orig_start_date',
            'config' => [

                'type' => 'datetime',
                'size' => 12,
                'required' => true,
                'format' => 'date',
            ],
        ],
        'orig_start_time' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.orig_start_time',
            'config' => [

                'type' => 'datetime',
                'size' => 12,
                'format' => 'time',
            ],
        ],
        'start_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start_date',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'tx_cal_event' => 'start_date',
                'format' => 'date',
            ],
        ],
        'allday' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.allday',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'start_time' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'default' => 0,
                'format' => 'time',
            ],
        ],
        'end_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end_date',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'tx_cal_event' => 'end_date',
                'format' => 'date',
            ],
        ],
        'end_time' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => [
                'type' => 'datetime',
                'size' => 12,
                'default' => 0,
                'format' => 'time',
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
    ],
    'types' => [
        '0' => [
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.general_sheet,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.orig_start;3, title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.end;6,' . ($configuration ['useTeaser'] ? 'teaser,' : '') . 'description, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.files_sheet,image, --palette--;;4,imagecaption,attachment,attachmentcaption',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => 'hidden,l10n_parent,sys_language_uid,t3ver_label',
        ],
        '3' => [
            'showitem' => 'orig_start_date,orig_start_time',
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
    ],
];
