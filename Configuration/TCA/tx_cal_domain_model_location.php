<?php

declare(strict_types=1);

$tx_cal_domain_model_location =  [

    'ctrl' =>  [

        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY name',
        'delete' => 'deleted',
        'iconfile' => 'EXT:cal/Resources/Public/Icons/tx_cal_location.svg',
        'enablecolumns' =>  [

            'disabled' => 'hidden',
        ],
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'languageField' => 'sys_language_uid',
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
        'slug' => [
            'exclude' => true,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal.slug',
            'description' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal.slug.description',
            'config' => [
                'type' => 'slug',
                'generatorOptions' => [
                    'fields' => ['name'],
                    'replacements' => [
                        '/' => '-',
                    ],
                ],
                'fallbackCharacter' => '-',
                'eval' => 'uniqueInSite',
                'default' => '',
            ],
        ],
        'name' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.name',
            'config' =>  [

                'type' => 'input',
                'size' => '30',
                'max' => '128',
            ],
        ],
        'description' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.description',
            'config' =>  [

                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
                'enableRichtext' => true,
                'fieldControl' =>  [

                    'fullScreenRichtext' =>  [

                        'disabled' => '',
                        'options' =>  [

                            'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext.W.RTE',
                        ],
                    ],
                ],
            ],
        ],
        'street' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.street',
            'config' =>  [

                'type' => 'input',
                'size' => '30',
                'max' => '128',
            ],
        ],
        'zip' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.zip',
            'config' =>  [

                'type' => 'input',
                'size' => '15',
                'max' => '15',
            ],
        ],
        'city' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.city',
            'config' =>  [

                'type' => 'input',
                'size' => '30',
                'max' => '128',
            ],
        ],
        'phone' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.phone',
            'config' =>  [

                'type' => 'input',
                'size' => '15',
                'max' => '24',
            ],
        ],
        'fax' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.fax',
            'config' =>  [

                'type' => 'input',
                'size' => '15',
                'max' => '24',
            ],
        ],
        'email' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.email',
            'config' =>  [

                'type' => 'input',
                'size' => '30',
                'max' => '64',
                'eval' => 'lower',
            ],
        ],
        'image' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.images',
            'config' => [
                'type' => 'file',
                'maxitems' => 5,
                'allowed' => 'common-image-types',
            ],
        ],
        'link' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.link',
            'config' =>  [

                'type' => 'link',
                'size' => '25',
            ],
        ],
        'latitude' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.latitude',
            'config' =>  [

                'type' => 'input',
                'readOnly' => 1,
            ],
        ],
        'longitude' =>  [

            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.longitude',
            'config' =>  [

                'type' => 'input',
                'readOnly' => 1,
            ],
        ],
        'sys_language_uid' => [

            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' =>  [

            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' =>  [

                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' =>  [

                    [

                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table' => 'tx_cal_domain_model_location',
                'foreign_table_where' => 'AND tx_cal_domain_model_location.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' =>  [

            'config' =>  [

                'type' => 'passthrough',
                'default' => '',
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

            'showitem' => 'name, slug, --palette--;;1, description, street, city, country, country_zone, zip, latitude, longitude, phone, fax, email, image, link',
        ],
    ],
    'palettes' =>  [

        '1' =>  [

            'showitem' => 'hidden,l10n_parent,sys_language_uid,t3ver_label',
        ],
    ],
];

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
    $tx_cal_domain_model_location ['columns'] ['country_zone'] =  [

        'exclude' => 1,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.countryzone',
        'config' =>  [

            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' =>  [

                [

                    'label' => '',
                    'value' => 0,
                ],
            ],
            'foreign_table' => 'static_country_zones',
            'foreign_table_where' => 'ORDER BY static_country_zones.zn_name_en',
            'itemsProcFunc' => 'SJBR\\StaticInfoTables\\Hook\\Backend\\Form\\ElementRenderingHelper->translateCountryZonesSelector',
            'size' => 1,
            'minitems' => 0,
            'maxitems' => 1,
        ],
    ];
    $tx_cal_domain_model_location ['columns'] ['country'] =  [

        'exclude' => 1,
        'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_location.country',
        'config' =>  [

            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' =>  [

                [

                    'label' => '',
                    'value' => 0,
                ],
            ],
            'foreign_table' => 'static_countries',
            'foreign_table_where' => 'ORDER BY static_countries.cn_short_en',
            'itemsProcFunc' => 'SJBR\\StaticInfoTables\\Hook\\Backend\\Form\\ElementRenderingHelper->translateCountriesSelector',
            'size' => 1,
            'minitems' => 0,
            'maxitems' => 1,
        ],
    ];
    $tx_cal_domain_model_location ['columns'] ['country_zone'] ['config'] ['itemsProcFunc'] = 'SJBR\\StaticInfoTables\\Hook\\Backend\\Form\\FormDataProvider\\TcaSelectItemsProcessor->translateCountryZonesSelector';
    $tx_cal_domain_model_location ['columns'] ['country'] ['config'] ['itemsProcFunc'] = 'SJBR\\StaticInfoTables\\Hook\\Backend\\Form\\FormDataProvider\\TcaSelectItemsProcessor->translateCountriesSelector';
}

return $tx_cal_domain_model_location;
