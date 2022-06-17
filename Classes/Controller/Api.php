<?php

namespace TYPO3\CMS\Cal\Controller;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * API for calendar base (cal)
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class Api {

  var $cObj;

  var $rightsObj;

  var $modelObj;

  var $viewObj;

  var $controller;

  var $conf;

  var $prefixId = 'tx_cal_controller';

  var $unsetTSFEOnDestruct = false;

  /**
   * Example:
   * require_once ('class.tx_cal_api.php');
   * $calAPI = new Api($this->cObj, &$conf);
   * $event = $calAPI->findEvent('2','tx_cal_phpicalendar');
   */
  function tx_cal_api_with(&$cObj, &$conf) {

    $this->cObj = &$cObj;
    $this->conf = &$conf;
    
    $this->conf ['useInternalCaching'] = 1;
    $this->conf ['cachingEngine'] = 'cachingFramework';
    $this->conf ['writeCachingInfoToDevlog'] = 0;
    
    $this->controller = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\Controller' );
    $this->controller->cObj = &$this->cObj;
    $this->controller->conf = &$this->conf;
    
    $this->controller->setWeekStartDay();
    
    $this->controller->cleanPiVarParam( $this->piVars );
    $this->controller->clearPiVarParams();
    $this->controller->getParamsFromSession();
    $this->controller->initCaching();
    $this->controller->initConfigs();
    
    $this->rightsObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'rightscontroller' );
    $this->rightsObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstanceService( 'cal_rights_model', 'rights' );
    $this->rightsObj->setDefaultSaveToPage();
    
    $this->modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'modelcontroller' );
    $this->modelObj = new \TYPO3\CMS\Cal\Controller\ModelController();
    
    $this->viewObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'viewcontroller' );
    $this->viewObj = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\ViewController' );
    
    /*
     * $this->rightsObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry('basic','rightscontroller'); $this->modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry('basic','modelcontroller'); $this->viewObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry('basic','viewcontroller');
     */
    return $this;
  }

  public function tx_cal_api_without($pid) {

    $GLOBALS ['TT'] = new \TYPO3\CMS\Core\TimeTracker\TimeTracker();
    
    // ***********************************
    // Creating a fake $TSFE object
    // ***********************************
    $this->unsetTSFEOnDestruct = true;
    
    $uriInterface = GeneralUtility::makeInstance( \TYPO3\CMS\Core\Http\Uri::class, '' );
    $siteLanguage = new \TYPO3\CMS\Core\Site\Entity\SiteLanguage( 0, '', $uriInterface, [ ] );
    $site = new \TYPO3\CMS\Core\Site\Entity\NullSite();
    
    $pageArguments = new \TYPO3\CMS\Core\Routing\PageArguments( $pid, 10, [ ] );
    
    $GLOBALS ['TSFE'] = GeneralUtility::makeInstance( \TYPO3\CMS\Cal\Controller\Tsfe::class, \TYPO3\CMS\Core\Core\Environment::getContext(), $site, $siteLanguage, $pageArguments );
    
    // we need to get the plugin setup to create correct source URLs
    $template = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\TypoScript\ExtendedTemplateService::class );
    
    // Do not log time-performance information
    $template->tt_track = 0;
    $rootline = \TYPO3\CMS\Backend\Utility\BackendUtility::BEgetRootLine( $pid );
    
    $template->start( $rootline ); // This generates the constants/config + hierarchy info for the template.
    $conf = $template->setup ['plugin.'] ['tx_cal_controller.'];
    
    $sys_page = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Domain\Repository\PageRepository::class );
    
    $cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class );
    
    $GLOBALS ['TSFE']->cObjectDepthCounter = 100;
    $GLOBALS ['TSFE']->cObj = $cObj;
    $GLOBALS ['TSFE']->sys_page = $sys_page;
    
    // get the calendar plugin record where starting pages value is the same as the pid
    $fields = '*';
    $tables = 'tt_content';
    $where = 'tt_content.list_type="cal_controller" AND tt_content.deleted=0 AND pid=' . $pid;
    
    list ( $tt_content_row ) = $GLOBALS ['TYPO3_DB']->exec_SELECTgetRows( $fields, $tables, $where );
    
    // if starting point didn't return any records, look for general records
    // storage page.
    if (! $tt_content_row) {
      $tables = 'tt_content LEFT JOIN pages ON tt_content.pid = pages.uid';
      $where = 'tt_content.list_type="cal_controller" AND tt_content.deleted=0 AND tt_content.pid=' . $pid;
      list ( $tt_content_row ) = $GLOBALS ['TYPO3_DB']->exec_SELECTgetRows( $fields, $tables, $where );
    }
    
    if ($tt_content_row ['pages']) {
      // $conf['pages'] = $tt_content_row['pages'];
      $cObj->data = $tt_content_row;
    }
    return $this->tx_cal_api_with( $cObj, $conf );
  }

  /**
   * Destructor to clean up when we're done with the API object.
   *
   * @return void
   */
  function __destruct() {
    // If we created our own TSFE object earlier, get rid of it so that we don't interfere with other scripts.
    if ($this->unsetTSFEOnDestruct) {
      unset( $GLOBALS ['TSFE'] );
    }
  }

  function findEvent($uid, $type, $pidList = '') {

    return $this->modelObj->findEvent( $uid, $type, $pidList );
  }

  function saveEvent($uid, $type, $pidList = '') {

    return $this->modelObj->saveEvent( $uid, $type, $pidList );
  }

  function removeEvent($uid, $type) {

    return $this->modelObj->removeEvent( $uid, $type );
  }

  function saveExceptionEvent($uid, $type, $pidList = '') {

    return $this->modelObj->saveExceptionEvent( $uid, $type, $pidList );
  }

  function findLocation($uid, $type, $pidList = '') {

    return $this->modelObj->findLocation( $uid, $type, $pidList );
  }

  function findAllLocations($type = '', $pidList = '') {

    return $this->modelObj->findAllLocations( $type, $pidList );
  }

  function saveLocation($uid, $type, $pidList = '') {

    return $this->modelObj->saveLocation( $uid, $type, $pidList );
  }

  function removeLocation($uid, $type) {

    return $this->modelObj->removeLocation( $uid, $type );
  }

  function findOrganizer($uid, $type, $pidList = '') {

    return $this->modelObj->findOrganizer( $uid, $type, $pidList );
  }

  function findCalendar($uid, $type, $pidList = '') {

    return $this->modelObj->findCalendar( $uid, $type, $pidList );
  }

  function findAllCalendar($type = '', $pidList = '') {

    return $this->modelObj->findAllCalendar( $type, $pidList );
  }

  function findAllOrganizer($type = '', $pidList = '') {

    return $this->modelObj->findAllOrganizer( $type, $pidList );
  }

  function saveOrganizer($uid, $type, $pidList = '') {

    return $this->modelObj->saveOrganizer( $uid, $type, $pidList );
  }

  function removeOrganizer($uid, $type) {

    return $this->modelObj->removeOrganizer( $uid, $type );
  }

  function saveCalendar($uid, $type, $pidList = '') {

    return $this->modelObj->saveCalendar( $uid, $type, $pidList );
  }

  function removeCalendar($uid, $type) {

    return $this->modelObj->removeCalendar( $uid, $type );
  }

  function saveCategory($uid, $type, $pidList = '') {

    return $this->modelObj->saveCategory( $uid, $type, $pidList );
  }

  function removeCategory($uid, $type) {

    return $this->modelObj->removeCategory( $uid, $type );
  }

  function findEventsWithin($startTimestamp, $endTimestamp, $type = '', $pidList = '') {

    return $this->modelObj->findAllWithin( 'cal_event_model', $startTimestamp, $endTimestamp, $type, 'event', $pidList );
  }

  function findEventsForDay($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForDay( $timestamp, $type, $pidList );
  }

  function findEventsForWeek($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForWeek( $timestamp, $type, $pidList );
  }

  function findEventsForMonth($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForMonth( $timestamp, $type, $pidList );
  }

  function findEventsForYear($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForYear( $timestamp, $type, $pidList );
  }

  function findEventsForList($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForList( $timestamp, $type, $pidList );
  }

  function findCategoriesForList($type = '', $pidList = '') {

    return $this->modelObj->findCategoriesForList( $type, $pidList );
  }

  function findEventsForIcs($timestamp, $type = '', $pidList = '') {

    return $this->modelObj->findEventsForIcs( $timestamp, $type, $pidList );
  }

  function searchEvents($type = '', $pidList = '') {

    return $this->modelObj->searchEvents( $type, $pidList );
  }

  function searchLocation($type = '', $pidList = '') {

    return $this->modelObj->searchLocation( $type, $pidList );
  }

  function searchOrganizer($type = '', $pidList = '') {

    return $this->modelObj->searchOrganizer( $type, $pidList );
  }

  function drawIcs($master_array, $getdate, $sendHeaders = true) {

    return $this->viewObj->drawIcs( $master_array, $getdate, $sendHeaders );
  }

  /*
   * !brief process the Typoscript array to final output @param string The Typoscrypt Object to process @param string The content between the tags to be merged with the TS Objected @return string Processed ooutput of the TS Note: Part of the code is taken from tsobj written by Jean-David Gadina (macmade@gadlab.net)
   */
  function __processTSObject($tsObjPath, $tag_content) {
    // Check for a non empty value
    if ($tsObjPath) {
      
      // Get complete TS template
      $tsObj = & $this->__TSTemplate->setup;
      
      // Get TS object hierarchy in template
      $tmplPath = explode( '.', $tsObjPath );
      // Process TS object hierarchy
      $error = 0;
      for($i = 0; $i < count( $tmplPath ); $i ++) {
        
        // Try to get content type
        $cType = $tsObj [$tmplPath [$i]];
        
        // Try to get TS object configuration array
        $tsNewObj = $tsObj [$tmplPath [$i] . '.'];
        
        // Merge Configuration found in the tags with typoscript config
        if (count( $tag_content )) {
          $tsNewObj = $this->array_merge_recursive2( $tsNewObj, $tag_content [$tsObjPath . '.'] );
        }
        
        // Check object
        if (! $cType && ! $tsNewObj) {
          // Object doesn't exist
          $error = 1;
          break;
        }
      }
      
      // DEBUG ONLY - Show TS object
      // \TYPO3\CMS\Core\Utility\GeneralUtility::debug($cType, 'CONTENT TYPE');
      // \TYPO3\CMS\Core\Utility\GeneralUtility::debug($tsObj, 'TS CONFIGURATION');
      
      // Check object and content type
      if ($error) {
        
        // Object not found
        return '<strong>Not Found</strong> (' . $tsObjPath . ')';
      } elseif ($this->cTypes [$cType]) {
        // Render Object
        $code = $this->__local_cObj->cObjGetSingle( $cType, $tsNewObj );
      } else {
        
        // Invalid content type
        return '<strong>errors.invalid</strong> (' . $cType . ')';
      }
      
      // Return object
      return $code;
    }
  }

  /**
   * Returns current PageRenderer
   *
   * @return PageRenderer
   */
  protected function getPageRenderer() {

    return GeneralUtility::makeInstance( 'TYPO3\\CMS\\Core\\Page\\PageRenderer' );
  }

  /**
   * Sets backPath of PageRenderer back to null (for Backend)
   * Fixes backpath in for backend.
   * See forge #69319
   *
   * @return void
   */
  protected function cleanUpPageRendererBackPath() {

    $this->getPageRenderer()->setBackPath( null );
  }
}

?>