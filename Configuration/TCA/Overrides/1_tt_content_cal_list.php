<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

$contentTypeName = ExtensionUtility::registerPlugin(
    'Cal',
    'Controller',
    'LLL:EXT:cal/Resources/Private/Language/locallang_plugin.xlf:plugin.cal_views.title',
    'ext-cal-plugin-cal-views',
    'plugins',
    'LLL:EXT:cal/Resources/Private/Language/locallang_plugin.xlf:plugin.cal_views.description',
    'FILE:EXT:cal/Configuration/FlexForms/flexform_cal.xml',
);
