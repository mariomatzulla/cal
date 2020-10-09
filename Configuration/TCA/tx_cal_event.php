<?php
defined( 'TYPO3_MODE' ) or die();

$configuration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );

$sPid = '###CURRENT_PID###'; // storage pid????

$useLocationStructure = $configuration ['useLocationStructure'] ?: 'tx_cal_location';
$useOrganizerStructure = $configuration ['useOrganizerStructure'] ?: 'tx_cal_organizer';

switch ($useLocationStructure) {
  case 'tx_tt_address' :
    $useLocationStructure = 'tt_address';
    break;
}
switch ($useOrganizerStructure) {
  case 'tx_tt_address' :
    $useOrganizerStructure = 'tt_address';
    break;
  case 'tx_feuser' :
    $useOrganizerStructure = 'fe_users';
    break;
}

$tx_cal_event = array (
    
    'ctrl' => array (
        
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'default_sortby' => 'ORDER BY start_date DESC, start_time DESC',
        'delete' => 'deleted',
        'versioningWS' => TRUE,
        'origUid' => 't3_origuid',
        'shadowColumnsForNewPlaceholders' => 'sys_language_uid,l18n_parent',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'languageField' => 'sys_language_uid',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicons' => array (
            
            '1' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_events_intlnk.gif',
            '2' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_events_exturl.gif',
            '3' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_events_meeting.gif',
            '4' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_events_todo.gif'
        ),
        'dividers2tabs' => $configuration ['noTabDividers'] ? FALSE : TRUE,
        'enablecolumns' => array (
            
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ),
        'iconfile' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_events.gif',
        'searchFields' => 'title,organizer,organizer_link,location,location_link,teaser,description,ext_url,image,imagecaption,imagealttext,imagetitletext,attachment,attachmentcaption',
        'label_userFunc' => 'TYPO3\\CMS\\Cal\\Backend\\TCA\\Labels->getEventRecordLabel'
    ),
    'feInterface' => array (
        
        'fe_admin_fieldList' => 'hidden, title, starttime, endtime, start_date, start_time, end_date, end_time, relation_cnt, organizer, organizer_id, organizer_pid, location, location_id, location_pid, description, freq, byday, bymonthday, bymonth, until, count, interval, rdate_type, rdate, notify_cnt'
    ),
    'interface' => array (
        
        'showRecordFieldList' => 'hidden,category_id,title,start_date,start_time,allday,end_date,end_time,organizer,location,description,image,attachment,freq,byday,bymonthday,bymonth,until,count,rdate_type,rdate,end,intrval,exception_cnt, shared_user_cnt,attendee,status,priority,completed'
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
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.title',
            'config' => array (
                
                'type' => 'input',
                'size' => '30',
                'max' => '128',
                'eval' => 'required'
            )
        ),
        'starttime' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
            'config' => array (
                
                'type' => 'input',
                'size' => '12',
                'max' => '20',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => '0',
                'checkbox' => '0'
            )
        ),
        'endtime' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'datetime',
                'default' => '0',
                'checkbox' => '0'
            )
        ),
        'calendar_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.calendar',
            'onChange' => 'reload',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'itemsProcFunc' => 'TYPO3\CMS\Cal\Backend\TCA\ItemsProcFunc->getRecords',
                'itemsProcFunc_config' => array (
                    
                    'table' => 'tx_cal_calendar',
                    'orderBy' => 'tx_cal_calendar.title'
                ),
                'fieldControl' => array (
                    
                    'addRecord' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'pid' => $sPid,
                            'setValue' => 'set',
                            'table' => 'tx_cal_calendar',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_calendar.createNew'
                        )
                    ),
                    'editPopup' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'windowOpenParameters' => 'height=500,width=660,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_calendar.edit'
                        )
                    )
                ),
                'wizards' => array (
                    
                    '_PADDING' => 2,
                    '_VERTICAL' => 1
                )
            )
        ),
        'category_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.category',
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
                'maxitems' => 20,
                'foreign_table' => 'tx_cal_category',
                'MM' => 'tx_cal_event_category_mm',
                
                'fieldControl' => array (
                    
                    'addRecord' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'pid' => $sPid,
                            'setValue' => 'append',
                            'table' => 'tx_cal_category',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.createNew'
                        )
                    ),
                    'editPopup' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'windowOpenParameters' => 'height=500,width=660,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_category.edit'
                        )
                    )
                ),
                
                'wizards' => array (
                    
                    '_PADDING' => 2,
                    '_VERTICAL' => 1
                )
            )
        ),
        'start_date' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start_date',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'required,date',
                'tx_cal_event' => 'start_date'
            )
        ),
        'allday' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.allday',
            'onChange' => 'reload',
            'config' => array (
                
                'type' => 'check',
                'default' => 0
            )
        ),
        'start_time' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'time',
                'default' => '0'
            )
        ),
        'end_date' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end_date',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'required,date',
                'tx_cal_event' => 'end_date'
            )
        ),
        'end_time' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end_time',
            'displayCond' => 'FIELD:allday:!=:1',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'time',
                'default' => '0'
            )
        ),
        'organizer' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer',
            'config' => array (
                
                'type' => 'input',
                'size' => '30',
                'max' => '128'
            )
        ),
        'organizer_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_id',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
                'allowed' => $useOrganizerStructure,
                'fieldControl' => array (
                    
                    'addRecord' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'pid' => $sPid,
                            'setValue' => 'set',
                            'table' => $useOrganizerStructure,
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_organizer.createNew'
                        )
                    ),
                    'editPopup' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'windowOpenParameters' => 'height=600,width=525,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_organizer.edit'
                        )
                    )
                ),
                'wizards' => array (
                    
                    '_PADDING' => 2,
                    '_VERTICAL' => 1
                )
            )
        ),
        'organizer_pid' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_pid',
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
        'organizer_link' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_link',
            'config' => array (
                
                'type' => 'input',
                'size' => '25',
                'max' => '128',
                'checkbox' => '',
                'eval' => 'trim',
                'renderType' => 'inputLink',
                'wizards' => array (
                    
                    '_PADDING' => 2
                )
            )
        ),
        'location' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location',
            'config' => array (
                
                'type' => 'input',
                'size' => '30',
                'max' => '128'
            )
        ),
        'location_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_id',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
                'allowed' => $useLocationStructure,
                'fieldControl' => array (
                    
                    'addRecord' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'pid' => $sPid,
                            'setValue' => 'set',
                            'table' => $useLocationStructure,
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_location.createNew'
                        )
                    ),
                    'editPopup' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'windowOpenParameters' => 'height=600,width=525,status=0,menubar=0,scrollbars=1',
                            'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_location.edit'
                        )
                    )
                ),
                'wizards' => array (
                    
                    '_PADDING' => 2,
                    '_VERTICAL' => 1
                )
            )
        ),
        'location_pid' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_pid',
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
        'location_link' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_link',
            'config' => array (
                
                'type' => 'input',
                'size' => '25',
                'max' => '128',
                'checkbox' => '',
                'eval' => 'trim',
                'renderType' => 'inputLink',
                'wizards' => array (
                    
                    '_PADDING' => 2
                )
            )
        ),
        'teaser' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.teaser',
            'config' => array (
                
                'type' => 'text',
                'cols' => '40',
                'rows' => '6',
                'softref' => 'rtehtmlarea_images,typolink_tag,images,email[subst],url',
                'enableRichtext' => true,
                'fieldControl' => array (
                    
                    'fullScreenRichtext' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext.W.RTE'
                        )
                    )
                ),
                'wizards' => array (
                    
                    '_PADDING' => 4
                )
            )
        ),
        'description' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.description',
            'config' => array (
                
                'type' => 'text',
                'cols' => '40',
                'rows' => '6',
                'softref' => 'rtehtmlarea_images,typolink_tag,images,email[subst],url',
                'enableRichtext' => true,
                'fieldControl' => array (
                    
                    'fullScreenRichtext' => array (
                        
                        'disabled' => '',
                        'options' => array (
                            
                            'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext.W.RTE'
                        )
                    )
                ),
                'wizards' => array (
                    
                    '_PADDING' => 4
                )
            )
        ),
        'freq' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.freq',
            'onChange' => 'reload',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => '1',
                'items' => array (
                    
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:frequency.none',
                        'none'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:frequency.day',
                        'day'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:frequency.week',
                        'week'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:frequency.month',
                        'month'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:frequency.year',
                        'year'
                    )
                )
            )
        ),
        'byday' => array (
            
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:week,month,year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_short',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customByRecurrence'
            )
        ),
        'bymonthday' => array (
            
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:month,year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonthday_short',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customByRecurrence'
            )
        ),
        'bymonth' => array (
            
            'exclude' => 1,
            'displayCond' => 'FIELD:freq:IN:year',
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_short',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customByRecurrence'
            )
        ),
        'until' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.until',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'date'
            )
        ),
        'cnt' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.count',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => array (
                
                'type' => 'input',
                'size' => '4',
                'eval' => 'num',
                'checkbox' => '0'
            )
        ),
        'intrval' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.interval',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => array (
                
                'type' => 'input',
                'size' => '4',
                'eval' => 'num',
                'default' => '1'
            )
        ),
        'rdate_type' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.rdate_type',
            'onChange' => 'reload',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => array (
                    
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:rdate_type.none',
                        'none'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:rdate_type.date',
                        'date'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:rdate_type.date_time',
                        'date_time'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:rdate_type.period',
                        'period'
                    )
                ),
                'default' => 'none'
            )
        ),
        'rdate' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.rdate',
            'displayCond' => 'FIELD:rdate_type:IN:date_time,date,period',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customRdate'
            )
        ),
        'deviation' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.deviation',
            'config' => array (
                
                'type' => 'inline',
                'foreign_table' => 'tx_cal_event_deviation',
                'foreign_field' => 'parentid',
                'foreign_label' => 'title',
                'maxitems' => 10,
                'appearance' => array (
                    
                    'collapseAll' => 1,
                    'expandSingle' => 1,
                    'useSortable' => 1
                )
            )
        ),
        'monitor_cnt' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_fe_user_event.monitor',
            'config' => array (
                
                'type' => 'inline',
                'foreign_table' => 'tx_cal_fe_user_event_monitor_mm',
                'foreign_field' => 'uid_local',
                'appearance' => array (
                    
                    'collapseAll' => 1,
                    'expandSingle' => 1,
                    'useSortable' => 1
                )
            )
        ),
        'exception_cnt' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.exception',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_cal_exception_event,tx_cal_exception_event_group',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 100,
                'MM' => 'tx_cal_exception_event_mm'
            )
        ),
        'fe_cruser_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.fe_cruser_id',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'fe_users',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1
            )
        ),
        'fe_crgroup_id' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.fe_crgroup_id',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'fe_groups',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1
            )
        ),
        
        'shared_user_cnt' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.shared_user',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'fe_users,fe_groups',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 100,
                'MM' => 'tx_cal_event_shared_user_mm'
            )
        ),

			/* new */
			'type' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'size' => 1,
                'items' => array (
                    
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type.I.0',
                        0
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type.I.1',
                        1
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type.I.2',
                        2
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type.I.3',
                        3
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.type.I.4',
                        4
                    )
                ),
                'default' => 0
            )
        ),
        
        'ext_url' => array (
            
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.external',
            'config' => array (
                
                'type' => 'input',
                'size' => '40',
                'max' => '256',
                'eval' => 'required',
                'default' => '',
                'renderType' => 'inputLink',
                'wizards' => array (
                    
                    '_PADDING' => 2
                )
            )
        ),
        
        'page' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.shortcut_page',
            'config' => array (
                
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => '1',
                'minitems' => '0',
                'eval' => 'required'
            )
        ),
			/* new */
			'image' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.images',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig( 'image', array (
                
                'maxitems' => 5,
                'default' => '',
                // Use the imageoverlayPalette instead of the basicoverlayPalette
                'foreign_types' => array (
                    
                    '0' => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    )
                )
            ) )
        ),
        'attachment' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig( 'attachment', array (
                
                'maxitems' => 5,
                'default' => '',
                // Use the imageoverlayPalette instead of the basicoverlayPalette
                'foreign_types' => array (
                    
                    '0' => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_TEXT => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_AUDIO => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_VIDEO => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    ),
                    \TYPO3\CMS\Core\Resource\File::FILETYPE_APPLICATION => array (
                        
                        'showitem' => '
											--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
											--palette--;;filePalette'
                    )
                )
            ) )
        ),
        
        'attendee' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.attendee',
            'config' => array (
                
                'type' => 'inline',
                'default' => '',
                'foreign_table' => 'tx_cal_attendee',
                'foreign_field' => 'event_id',
                'appearance' => array (
                    
                    'collapseAll' => 1,
                    'expandSingle' => 1,
                    'useSortable' => 1
                )
            )
        ),
        'send_invitation' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.send_invitation',
            'config' => array (
                
                'type' => 'check',
                'default' => '0'
            )
        ),
        'status' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_attendee.status',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' => array (
                    
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_attendee.status.0',
                        '0'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_attendee.status.NEEDS-ACTION',
                        'NEEDS-ACTION'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.status.COMPLETED',
                        'COMPLETED'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.status.IN-PROGRESS',
                        'IN-PROGRESS'
                    ),
                    array (
                        
                        'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.status.CANCELLED',
                        'CANCELLED'
                    )
                ),
                'size' => '1',
                'minitems' => 1,
                'maxitems' => 1
            )
        ),
        'priority' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.priority',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' => array (
                    
                    array (
                        
                        0,
                        0
                    ),
                    array (
                        
                        1,
                        1
                    ),
                    array (
                        
                        2,
                        2
                    ),
                    array (
                        
                        3,
                        3
                    ),
                    array (
                        
                        4,
                        4
                    ),
                    array (
                        
                        5,
                        5
                    ),
                    array (
                        
                        6,
                        6
                    ),
                    array (
                        
                        7,
                        7
                    ),
                    array (
                        
                        8,
                        8
                    ),
                    array (
                        
                        9,
                        9
                    )
                ),
                'size' => '1',
                'minitems' => 1,
                'maxitems' => 1
            )
        ),
        'completed' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.completed',
            'config' => array (
                
                'type' => 'input',
                'size' => '3',
                'eval' => 'num',
                'checkbox' => '0'
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
        'l18n_parent' => array (
            
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => array (
                
                'renderType' => 'selectSingle',
                'type' => 'select',
                'items' => array (
                    
                    array (
                        
                        '',
                        0
                    )
                ),
                'foreign_table' => 'tx_cal_event',
                'foreign_table_where' => 'AND tx_cal_event.sys_language_uid IN (-1,0)'
            )
        ),
        'l18n_diffsource' => array (
            
            'config' => array (
                
                'type' => 'passthrough',
                'default' => ''
            )
        ),
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
            
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser,' : '') . 'description,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.files_sheet,image,attachment,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt'
        ),
        '1' => array (
            
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, page,title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser,' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt'
        ),
        '2' => array (
            
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, ext_url,title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser,' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt'
        ),
        '3' => array (
            
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.attendance_sheet,attendee,send_invitation,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link')
        ),
        '4' => array (
            
            'showitem' => '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.due;6,calendar_id,category_id,description,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.todo_sheet, status, priority, completed,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.files_sheet,attachment'
        )
    ),
    'palettes' => array (
        
        '1' => array (
            
            'showitem' => 'hidden,l18n_parent,sys_language_uid,t3ver_label',
            'canNotCollapse' => 1
        ),
        '2' => array (
            
            'showitem' => 'until, cnt, intrval',
            'canNotCollapse' => 1
        ),
        '5' => array (
            
            'showitem' => 'allday,--linebreak--,start_date,start_time',
            'canNotCollapse' => 1
        ),
        '6' => array (
            
            'showitem' => 'end_date,end_time',
            'canNotCollapse' => 1
        ),
        '7' => array (
            
            'showitem' => 'rdate',
            'canNotCollapse' => 1
        )
    )
);

if ($configuration ['categoryService'] == 'sys_category') {
  unset( $tx_cal_event ['columns'] ['category_id'] ['config'] );
  $tx_cal_event ['columns'] ['category_id'] ['config'] = array (
      
      'type' => 'select',
      'renderType' => 'selectTree',
      'treeConfig' => array (
          
          'dataProvider' => 'TYPO3\\CMS\\Cal\\TreeProvider\\DatabaseTreeDataProvider',
          'parentField' => 'parent',
          'appearance' => array (
              
              'showHeader' => TRUE,
              'allowRecursiveMode' => TRUE,
              'expandAll' => TRUE,
              'maxLevels' => 99
          )
      ),
      'MM' => 'sys_category_record_mm',
      'MM_match_fields' => array (
          
          'fieldname' => 'category_id',
          'tablenames' => 'tx_cal_event'
      ),
      'MM_opposite_field' => 'items',
      'foreign_table' => 'sys_category',
      'foreign_table_where' => ' AND (sys_category.sys_language_uid = 0 OR sys_category.l10n_parent = 0) ORDER BY sys_category.sorting',
      'size' => 10,
      'autoSizeMax' => 20,
      'minitems' => 0,
      'maxitems' => 99
  );
}

if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger( TYPO3_version ) < 7000000) {
  $tx_cal_event ['types'] ['0'] ['showitem'] = '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser;;;richtext:rte_transform[flag=rte_enabled|mode=ts_css],' : '') . 'description;;;richtext:rte_transform[flag=rte_enabled|mode=ts_css],--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.files_sheet,image,attachment,--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt';
  $tx_cal_event ['types'] ['1'] ['showitem'] = '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, page,title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser;;;richtext:rte_transform[flag=rte_enabled|mode=ts_css],' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt';
  $tx_cal_event ['types'] ['2'] ['showitem'] = '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.general_sheet,type, ext_url,title, --palette--;;1,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.start;5,--palette--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.end;6,calendar_id,category_id,' . ($configuration ['useTeaser'] ? 'teaser;;;richtext:rte_transform[flag=rte_enabled|mode=ts_css],' : '') . '--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurrence_sheet, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;7, deviation, exception_cnt, --div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.location_sheet,' . ($configuration ['hideLocationTextfield'] ? 'location_id,location_pid,location_link' : 'location,location_id,location_pid,location_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.organizer_sheet,' . ($configuration ['hideOrganizerTextfield'] ? 'organizer_id,organizer_pid,organizer_link' : 'organizer,organizer_id,organizer_pid,organizer_link') . ',--div--;LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.other_sheet, monitor_cnt, shared_user_cnt';
}

if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger( TYPO3_version ) > 8000000) {
  $tx_cal_event ['columns'] ['attachment'] ['config'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig( 'attachment', [ 
      
      'appearance' => [ 
          
          'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media.addFileReference'
      ],
      'default' => ''
  ] );
}

return $tx_cal_event;