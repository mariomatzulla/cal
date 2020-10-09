<?php
defined( 'TYPO3_MODE' ) or die();

$tx_cal_exception_event = array (
    
    'ctrl' => array (
        
        'title' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'default_sortby' => 'ORDER BY start_date DESC',
        'delete' => 'deleted',
        'enablecolumns' => array (
            
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ),
        'versioningWS' => TRUE,
        'iconfile' => 'EXT:cal/Resources/Public/icons/icon_tx_cal_exception_event.gif',
        'searchFields' => 'title'
    ),
    'feInterface' => array (
        
        'fe_admin_fieldList' => 'hidden, title, starttime, endtime, start_date, end_date, relation_cnt, freq, byday, bymonthday, bymonth, until, count, interval'
    ),
    'interface' => array (
        
        'showRecordFieldList' => 'hidden,title,start_date,end_date,freq,byday,bymonthday,bymonth,rdate,rdate_type,until,count,end,intrval,ex_freq, ex_byday, ex_bymonthday, ex_bymonth, ex_until'
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
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.title',
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
                'renderType' => 'inputDateTime',
                'size' => '12',
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
        'start_date' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.start_date',
            'config' => array (
                
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => '12',
                'eval' => 'required,date',
                'tx_cal_event' => 'start_date'
            )
        ),
        'end_date' => array (
            
            'config' => array (
                
                'type' => 'passthrough'
            )
        ),
        
        'freq' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.freq',
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
        
        'rdate_type' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.rdate_type',
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
                'default' => 0
            )
        ),
        
        'rdate' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.rdate',
            'displayCond' => 'FIELD:rdate_type:IN:date_time,date,period',
            'config' => array (
                
                'type' => 'user',
                'renderType' => 'customRdate'
            )
        ),
        
        'until' => array (
            
            'exclude' => 1,
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.until',
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
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.count',
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
            'label' => 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_exception_event.interval',
            'displayCond' => 'FIELD:freq:IN:day,week,month,year',
            'config' => array (
                
                'type' => 'input',
                'size' => '4',
                'eval' => 'num',
                'default' => '1'
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
            
            'showitem' => 'title, --palette--;;1,start_date,end_date, freq, --palette--;;2, byday, bymonthday, bymonth, rdate_type, --palette--;;3, monitor_cnt'
        )
    ),
    'palettes' => array (
        
        '1' => array (
            
            'showitem' => 'hidden,t3ver_label'
        ),
        '2' => array (
            
            'showitem' => 'until, cnt, intrval',
            'canNotCollapse' => 1
        ),
        '3' => array (
            
            'showitem' => 'rdate',
            'canNotCollapse' => 1
        )
    )
);

return $tx_cal_exception_event;