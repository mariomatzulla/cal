<?php
defined( 'TYPO3_MODE' ) or die();

$tx_cal_category = array (
    
    'ctrl' => array (
        
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'enablecolumns' => array (
            
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ),
        'versioningWS' => TRUE,
        'origUid' => 't3_origuid',
        'shadowColumnsForNewPlaceholders' => 'sys_language_uid,l18n_parent',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'languageField' => 'sys_language_uid',
        'iconfile' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_category.gif',
        // 'treeParentField' => 'calendar_id',
        'searchFields' => 'title,notification_emails'
    ),
    'feInterface' => array (
        
        'fe_admin_fieldList' => 'hidden, title, starttime, endtime'
    ),
    'interface' => array (
        
        'showRecordFieldList' => 'hidden,title,headerstyle,bodystyle,calendar_id,single_pid,shared_user_allowed,notification_emails,icon'
    ),
    'columns' => array (
        
        'hidden' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => array (
                
                'type' => 'check',
                'default' => '0'
            )
        ),
        'title' => array (
            
            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.title',
            'config' => array (
                
                'type' => 'input',
                'size' => '30',
                'max' => '128',
                'eval' => 'required'
            )
        ),
        'headerstyle' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.headerstyle',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customStyles'
            )
        ),
        'bodystyle' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.bodystyle',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customStyles'
            )
        ),
        'calendar_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.calendar',
            'onChange' => 'reload',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'itemsProcFunc' => 'TYPO3\CMS\Cal\Backend\TCA\ItemsProcFunc->getRecords',
                'itemsProcFunc_config' => array (
                    
                    'table' => 'tx_cal_calendar',
                    'orderBy' => 'tx_cal_calendar.title'
                ),
                'items' => array (
                    
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.none',
                        0
                    )
                ),
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'allowed' => 'tx_cal_calendar'
            )
        ),
        'parent_category' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.parent_category',
            'config' => array (
                
                'type' => 'select',
                'renderType' => 'selectTree',
                'parameterArray' => array (
                    
                    'fieldConf' => array (
                        
                        'config' => array (
                            
                            'renderMode' => 'tree'
                        )
                    )
                ),
                'treeConfig' => array (
                    
                    // 'dataProvider' => 'TYPO3\\CMS\\Cal\\TreeProvider\\DatabaseTreeDataProvider',
                    'parentField' => 'parent_category',
                    'appearance' => array (
                        
                        'showHeader' => TRUE,
                        'expandAll' => TRUE,
                        'maxLevels' => 99
                    )
                ),
                'form_type' => 'user',
                'userFunc' => 'TYPO3\CMS\Cal\TreeProvider\TreeView->displayCategoryTree',
                'treeView' => 1,
                'size' => 20,
                'itemListStyle' => 'height:300px;',
                'minitems' => 0,
                'maxitems' => 2,
                'foreign_table' => 'tx_cal_category',
                'default' => 0
            )
        ),
        'shared_user_allowed' => array (
            
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.shared_user_allowed',
            'config' => array (
                
                'type' => 'check'
            )
        ),
        'single_pid' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.single_pid',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => '1',
                'minitems' => '0',
                'default' => 0
            )
        ),
        'notification_emails' => array (
            
            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.notification_emails',
            'config' => array (
                
                'type' => 'input',
                'size' => '30'
            )
        ),
        'icon' => array (
            
            'exclude' => 0,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.icon',
            'config' => array (
                
                'type' => 'input',
                'size' => '30',
                'max' => '128'
            )
        ),
        'sys_language_uid' => [ 
            
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [ 
                
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [ 
                    
                    [ 
                        
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages',
                        - 1,
                        'flags-multiple'
                    ]
                ],
                'default' => 0
            ]
        ],
        'l18n_parent' => [ 
            
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [ 
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' => [ 
                    
                    [ 
                        
                        '',
                        0
                    ]
                ],
                'foreign_table' => 'tx_cal_category',
                'foreign_table_where' => 'AND tx_cal_category.sys_language_uid IN (-1,0)'
            ]
        ],
        'l18n_diffsource' => [ 
            
            'config' => [ 
                
                'type' => 'passthrough',
                'default' => ''
            ]
        ],
        't3ver_label' => array (
            
            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => array (
                
                'type' => 'none',
                'cols' => 27
            )
        )
    ),
    'types' => array (
        
        '0' => array (
            
            'showitem' => 'type,title, --palette--;;1,calendar_id,parent_category,shared_user_allowed,single_pid,notification_emails,icon'
        )
    ),
    'palettes' => array (
        
        '1' => array (
            
            'showitem' => 'hidden,l18n_parent,sys_language_uid,t3ver_label,headerstyle,bodystyle'
        )
    )
);

return $tx_cal_category;