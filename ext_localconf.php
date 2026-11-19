<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Backend\Form\RenderType\ByDayElement;
use MarioMatzulla\Cal\Backend\Form\RenderType\ByMonthDayElement;
use MarioMatzulla\Cal\Backend\Form\RenderType\ByMonthElement;
use MarioMatzulla\Cal\Backend\Form\RenderType\RDateElement;
use MarioMatzulla\Cal\Backend\Form\TempEventReadOnlyDataProvider;
use MarioMatzulla\Cal\Controller\ContentController;
use MarioMatzulla\Cal\Hooks\CalendarDataHandlerHook;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

// Frontend plugin (CType "cal_controller"). Since TYPO3 v14 plugins are always content elements.
ExtensionUtility::configurePlugin(
    'Cal',
    'Controller',
    [
        ContentController::class => 'index,list,event,ics,year,month,week,day,location,organizer',
    ],
    // non-cacheable actions
    [
        ContentController::class => '',
    ],
);

// TypoScript is provided by the site set "mariomatzulla/cal" (Configuration/Sets/Cal)
// or, for sites without site sets, by the static template "Calendar Base (cal)".

// Fluid namespace "mmcal" is registered in Configuration/Fluid/Namespaces.php (TYPO3 v14.1+).

// German labels shipped with this extension. The translation server (var/labels) still
// provides an old language pack for "cal", which would otherwise hide the new labels.
foreach (['locallang.xlf', 'locallang_db.xlf', 'locallang_tca.xlf'] as $languageFile) {
    $GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['de']['EXT:cal/Resources/Private/Language/' . $languageFile][]
        = 'EXT:cal/Resources/Private/Language/de.' . $languageFile;
}

// The recurrence index is rebuilt on save (CalendarDataHandlerHook), not when opening the form.
// Imported events of external calendars are read-only
TempEventReadOnlyDataProvider::register();

// DataHandler hooks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['tx_cal_ics'] = CalendarDataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['tx_cal_ics'] = CalendarDataHandlerHook::class;

// FormEngine custom render types
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1542311227] = [
    'nodeName' => 'calRDateElement',
    'priority' => 40,
    'class' => RDateElement::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1542311228] = [
    'nodeName' => 'calByMonthElement',
    'priority' => 40,
    'class' => ByMonthElement::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1542311229] = [
    'nodeName' => 'calByMonthDayElement',
    'priority' => 40,
    'class' => ByMonthDayElement::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1542311230] = [
    'nodeName' => 'calByDayElement',
    'priority' => 40,
    'class' => ByDayElement::class,
];
