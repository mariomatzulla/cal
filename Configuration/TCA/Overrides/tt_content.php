<?php
if (! defined( 'TYPO3_MODE' )) {
  die( 'Access denied.' );
}
$_EXTKEY = $GLOBALS ['_EXTKEY'] = 'cal';
$extensionName = \TYPO3\CMS\Core\Utility\GeneralUtility::underscoredToUpperCamelCase( $_EXTKEY );
$pluginSignature = strtolower( $extensionName ) . '_controller';

/**
 * *************
 * Plugin
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin( Array (
    
    'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tt_content.list_type',
    $_EXTKEY . '_controller'
), 'list_type', $_EXTKEY );

$GLOBALS ['TCA'] ['tt_content'] ['types'] ['list'] ['subtypes_excludelist'] [$pluginSignature] = 'layout,select_key';
$GLOBALS ['TCA'] ['tt_content'] ['types'] ['list'] ['subtypes_addlist'] [$pluginSignature] = 'pi_flexform';

$extConf = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
if ($extConf ['categoryService'] == 'sys_category') {
  \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue( $pluginSignature, 'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/flexform_cal_sys_category.xml' );
} else {
  \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue( $pluginSignature, 'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/flexform_cal.xml' );
}

/**
 * *************
 * Default TypoScript
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/ts/', 'Classic CSS-based template' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/ts_standard/', 'Standard CSS-based template' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/ajax/', 'AJAX-based template (Experimental!)' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/css/', 'Classic CSS styles' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/css_standard/', 'Standard CSS styles' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/rss_feed/', 'News-feed (RSS,RDF,ATOM)' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/ics/', 'ICS Export' );
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile( $_EXTKEY, 'Configuration/TypoScript/fe-editing/', 'Fe-Editing' );

