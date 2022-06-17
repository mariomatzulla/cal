<?php

namespace TYPO3\CMS\Cal\Service;

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
use TYPO3\CMS\Core\Context\Context;

/**
 * Base model for the calendar organizer.
 * Provides basic model functionality that other
 * models can use or override by extending the class.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class LocationService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $keyId = 'tx_cal_location';

  public function __construct() {

    parent::__construct();
  }

  /**
   * Looks for an organizer with a given uid on a certain pid-list
   *
   * @param integer $uid
   *          to search for
   * @param string $pidList
   *          to search in
   * @return object tx_cal_organizer object
   */
  function find($uid, $pidList) {

    if (! $this->isAllowedService())
      return;
    $locationArray = $this->getLocationFromTable( $pidList, ' AND tx_cal_location.uid=' . $uid );
    return $locationArray [0];
  }

  /**
   * Looks for an organizer with a given uid on a certain pid-list
   *
   * @param string $pidList
   *          to search in
   * @return array tx_cal_organizer object array
   */
  function findAll($pidList) {

    if (! $this->isAllowedService())
      return;
    return $this->getLocationFromTable( $pidList );
  }

  /**
   * Search for locations
   *
   * @param string $pidList
   *          to search in
   * @param string $searchword
   *          term
   * @return array containing the location objects
   */
  function search($pidList = '', $searchword = '') {

    if (! $this->isAllowedService()) {
      return array ();
    }
    return $this->getLocationFromTable( $pidList, $this->searchWhere( $searchword ) );
  }

  /**
   * Generates the sql query and builds location objects out of the result rows
   *
   * @param string $pidList
   *          to search in
   * @param string $additionalWhere
   *          where clause
   * @return array containing the location objects
   */
  function getLocationFromTable($pidList = '', $additionalWhere = '') {

    $locations = array ();
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( 'tx_cal_location' );
    if ($pidList != '') {
      $additionalWhere .= ' AND tx_cal_location.pid IN (' . $pidList . ')';
    }
    $additionalWhere .= $this->getAdditionalWhereForLocalizationAndVersioning( 'tx_cal_location' );
    $table = 'tx_cal_location';
    $select = '*';
    $where = ' l18n_parent = 0 ' . $additionalWhere . $this->pageRepository->enableFields( 'tx_cal_location' );
    $groupBy = '';
    $limit = '';
    
    $rightsObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'rightscontroller' );
    $feUserUid = $rightsObj->getUserId();
    $feGroupsArray = $rightsObj->getUserGroups();
    
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_location_service', 'locationServiceClass', 'service' );
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'preGetLocationFromTableExec' )) {
        $hookObj->preGetLocationFromTableExec( $this, $select, $table, $where, $groupBy, $orderBy, $limit );
      }
    }
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy, $orderBy, $limit );
    if ($result) {
      $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        
        if ($languageAspect->getContentId()) {
          $row = $GLOBALS ['TSFE']->sys_page->getRecordOverlay( 'tx_cal_location', $row, $languageAspect->getContentId(), $languageAspect->getLegacyOverlayType(), '' );
        }
        /**
         * FIXMY no public property anymore
         if ($GLOBALS ['TSFE']->sys_page->versioningPreview == TRUE) {
          // get workspaces Overlay
          $GLOBALS ['TSFE']->sys_page->versionOL( 'tx_cal_location', $row );
        }*/
        
        $lastLocation = new \TYPO3\CMS\Cal\Model\Location( $row, $pidList );
        
        $select = 'uid_foreign,tablenames';
        $table = 'tx_cal_location_shared_user_mm';
        $where = 'uid_local = ' . $row ['uid'];
        
        $sharedUserResult = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
        if ($sharedUserResult) {
          while ( $sharedUserRow = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $sharedUserResult ) ) {
            if ($sharedUserRow ['tablenames'] == 'fe_users') {
              $lastLocation->addSharedUser( $sharedUserRow ['uid_foreign'] );
            } else if ($sharedUserRow ['tablenames'] == 'fe_groups') {
              $lastLocation->addSharedGroup( $sharedUserRow ['uid_foreign'] );
            }
          }
          $GLOBALS ['TYPO3_DB']->sql_free_result( $sharedUserResult );
        }
        $locations [] = $lastLocation;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return $locations;
  }

  function _addEventLinkToLocation(&$location, $event_uid) {

    return;
    // modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry('basic','modelcontroller');
    $event_s = $this->modelObj->findAllEventInstances( $event_uid, 'tx_cal_phpicalendar', $this->conf ['pidList'], false, false, true );
    if (is_array( $event_s )) {
      foreach ( $event_s as $date => $time ) {
        foreach ( $time as $eventArray ) {
          foreach ( $eventArray as $event ) {
            $location->addEventLink( $event->getStart()->format( '%Y%m%d%H%M' ) . '_' . $event->getType() . '_' . $event->getUid(), $event->renderEventForLocation() );
          }
        }
      }
    } else {
      $location->addEventLink( $event->getStart()->format( '%Y%m%d%H%M' ) . '_' . $event->getType() . '_' . $event->getUid(), $event->renderEventForOrganizer() );
    }
  }

  /**
   * Generates a search where clause.
   *
   * @param string $sw:          
   * @return string
   */
  function searchWhere($sw) {

    if (! $this->isAllowedService()) {
      $where = '';
    } else {
      $where = $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchLocationFieldList'], 'tx_cal_location' );
    }
    return $where;
  }

  function updateLocation($uid) {

    if (! $this->isAllowedService())
      return;
    $insertFields = array (
        
        'tstamp' => time()
    );
    // TODO: Check if all values are correct
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'location', false );
    $this->retrievePostData( $insertFields );
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'location', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_location', 'image', $insertFields, $uid );
    }
    
    $sharedGroups = Array ();
    $sharedUsers = Array ();
    $values = $this->controller->piVars ['shared_ids'];
    if (! is_array( $this->controller->piVars ['shared_ids'] )) {
      $values = GeneralUtility::trimExplode( ',', $this->controller->piVars ['shared_ids'], 1 );
    }
    foreach ( $values as $entry ) {
      preg_match( '/(^[a-z])_([0-9]+)/', $entry, $idname );
      if ($idname [1] == 'u') {
        $sharedUsers [] = $idname [2];
      } else if ($idname [1] == 'g') {
        $sharedGroups [] = $idname [2];
      }
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'location', 'shared' )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_location_shared_user_mm', 'uid_local =' . $uid );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $sharedUsers ), $uid, 'fe_users' );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $sharedGroups ), $uid, 'fe_groups' );
      if (count( $sharedUsers ) > 0 || count( $sharedGroups ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['location.'] ['fields.'] ['shared.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['location.'] ['addFeUserToShared']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['location.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['location.'] ['addFeGroupToShared']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['location.'] ['addFeGroupToShared.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_location_shared_user_mm', 'uid_local =' . $uid );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $userIdArray ), $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $groupIdArray ), $uid, 'fe_groups' );
      }
      if (count( $userIdArray ) > 0 || count( $groupIdArray ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    }
    
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tx_cal_location' );
    // Creating DB records
    $table = 'tx_cal_location';
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
    $this->unsetPiVars();
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function removeLocation($uid) {

    if (! $this->isAllowedService())
      return;
    if ($this->rightsObj->isAllowedToDeleteLocation()) {
      $updateFields = array (
          
          'tstamp' => time(),
          'deleted' => 1
      );
      $table = 'tx_cal_location';
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $updateFields );
    }
    $this->unsetPiVars();
  }

  function retrievePostData(&$insertFields) {

    $hidden = 0;
    if ($this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['hidden.'] ['default'] && ! $this->rightsObj->isAllowedTo( 'create', 'location', 'hidden' ) && ! $this->rightsObj->isAllowedTo( 'create', 'location', 'hidden' )) {
      $hidden = $this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($this->conf ['rights.'] ['edit.'] ['location.'] ['fields.'] ['hidden.'] ['default'] && ! $this->rightsObj->isAllowedTo( 'edit', 'location', 'hidden' ) && ! $this->rightsObj->isAllowedTo( 'create', 'location', 'hidden' )) {
      $hidden = $this->conf ['rights.'] ['location.'] ['event.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($this->controller->piVars ['hidden'] == 'true' && ($this->rightsObj->isAllowedTo( 'edit', 'location', 'hidden' ) || $this->rightsObj->isAllowedTo( 'create', 'location', 'hidden' ))) {
      $hidden = 1;
    }
    $insertFields ['hidden'] = $hidden;
    
    if ($this->rightsObj->isAllowedToEditLocationName() || $this->rightsObj->isAllowedToCreateLocationName()) {
      $insertFields ['name'] = strip_tags( $this->controller->piVars ['name'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationDescription() || $this->rightsObj->isAllowedToCreateLocationDescription()) {
      $insertFields ['description'] = Functions::removeBadHTML( $this->controller->piVars ['description'], $this->conf );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationStreet() || $this->rightsObj->isAllowedToCreateLocationStreet()) {
      $insertFields ['street'] = strip_tags( $this->controller->piVars ['street'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationZip() || $this->rightsObj->isAllowedToCreateLocationZip()) {
      $insertFields ['zip'] = strip_tags( $this->controller->piVars ['zip'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCity() || $this->rightsObj->isAllowedToCreateLocationCity()) {
      $insertFields ['city'] = strip_tags( $this->controller->piVars ['city'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCountryZone() || $this->rightsObj->isAllowedToCreateLocationCountryZone()) {
      $insertFields ['country_zone'] = strip_tags( $this->controller->piVars ['countryzone'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCountry() || $this->rightsObj->isAllowedToCreateLocationCountry()) {
      $insertFields ['country'] = strip_tags( $this->controller->piVars ['country'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationPhone() || $this->rightsObj->isAllowedToCreateLocationPhone()) {
      $insertFields ['phone'] = strip_tags( $this->controller->piVars ['phone'] );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'location', 'fax' ) || $this->rightsObj->isAllowedTo( 'create', 'location', 'fax' )) {
      $insertFields ['fax'] = strip_tags( $this->controller->piVars ['fax'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationEmail() || $this->rightsObj->isAllowedToCreateLocationEmail()) {
      $insertFields ['email'] = strip_tags( $this->controller->piVars ['email'] );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'location', 'link' ) || $this->rightsObj->isAllowedTo( 'create', 'location', 'link' )) {
      $insertFields ['link'] = strip_tags( $this->controller->piVars ['link'] );
    }
    
    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'wec_map' ) && ($insertFields ['street'] != '' || $insertFields ['city'] != '' || $insertFields ['country_zone'] != '' || $insertFields ['zip'] != '' || $insertFields ['country'])) {
      /* Geocode the address */
      $lookupTable = \TYPO3\CMS\Cal\Utility\Functions::makeInstance( 'JBartels\WecMap\Utility\Cache' );
      $latlong = $lookupTable->lookup( $insertFields ['street'], $insertFields ['city'], $insertFields ['country_zone'], $insertFields ['zip'], $insertFields ['country'] );
      $insertFields ['latitude'] = $latlong ['lat'];
      $insertFields ['longitude'] = $latlong ['long'];
    }
  }

  function saveLocation($pid) {

    if (! $this->isAllowedService())
      return;
    $crdate = time();
    $insertFields = array (
        
        'pid' => $pid,
        'tstamp' => $crdate,
        'crdate' => $crdate
    );
    // TODO: Check if all values are correct
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'location' );
    $this->retrievePostData( $insertFields );
    // Creating DB records
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    
    $uid = $this->_saveLocation( $insertFields );
    $this->unsetPiVars();
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function _saveLocation(&$insertFields) {

    $table = 'tx_cal_location';
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458153 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    
    if ($this->rightsObj->isAllowedTo( 'create', 'location', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_location', 'image', $insertFields, $uid );
    }
    
    $sharedGroups = Array ();
    $sharedUsers = Array ();
    $values = $this->controller->piVars ['shared_ids'];
    if (! is_array( $this->controller->piVars ['shared_ids'] )) {
      $values = GeneralUtility::trimExplode( ',', $this->controller->piVars ['shared_ids'], 1 );
    }
    foreach ( $values as $entry ) {
      preg_match( '/(^[a-z])_([0-9]+)/', $entry, $idname );
      if ($idname [1] == 'u') {
        $sharedUsers [] = $idname [2];
      } else if ($idname [1] == 'g') {
        $sharedGroups [] = $idname [2];
      }
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'location', 'shared' )) {
      if ($this->conf ['rights.'] ['create.'] ['location.'] ['addFeUserToShared']) {
        $sharedUsers [] = $this->rightsObj->getUserId();
      }
      if (count( $sharedUsers ) > 0 && $sharedUsers [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $sharedUsers ), $uid, 'fe_users' );
      }
      $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['location.'] ['addFeGroupToShared.'] ['ignore'], 1 );
      $groupArray = array_diff( $sharedGroups, $ignore );
      if (count( $groupArray ) > 0 && $groupArray [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
      }
      if (count( $sharedUsers ) > 0 || count( $groupArray ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    } else {
      $idArray = Array ();
      if ($this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['shared.'] ['defaultUser'] != '') {
        $idArray = explode( ',', $this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['shared.'] ['defaultUser'] );
      }
      if ($this->conf ['rights.'] ['create.'] ['location.'] ['addFeUserToShared']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      
      if (count( $idArray ) > 0 && $idArray [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $idArray ), $uid, 'fe_users' );
      }
      
      $groupArray = Array ();
      if ($this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['shared.'] ['defaultGroup'] != '') {
        $groupArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['location.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
        if ($this->conf ['rights.'] ['create.'] ['location.'] ['addFeGroupToShared']) {
          $idArray = $this->rightsObj->getUserGroups();
          $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['location.'] ['addFeGroupToShared.'] ['ignore'], 1 );
          $groupArray = array_diff( $idArray, $ignore );
        }
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_location_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
      }
      if (count( $idArray ) > 0 || count( $groupArray ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    }
    return $uid;
  }

  function isAllowedService() {

    $this->confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    $useLocationStructure = ($this->confArr ['useLocationStructure'] ? $this->confArr ['useLocationStructure'] : 'tx_cal_location');
    if ($useLocationStructure == $this->keyId) {
      return true;
    }
    return false;
  }

  function createTranslation($uid, $overlay) {

    $table = 'tx_cal_location';
    $select = $table . '.*';
    $where = $table . '.uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($result) {
      $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result );
      if (is_array( $row )) {
        unset( $row ['uid'] );
        $crdate = time();
        $row ['tstamp'] = $crdate;
        $row ['crdate'] = $crdate;
        $row ['l18n_parent'] = $uid;
        $row ['sys_language_uid'] = $overlay;
        $this->_saveLocation( $row );
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return;
  }

  function unsetPiVars() {

    unset( $this->controller->piVars ['hidden'] );
    unset( $this->controller->piVars ['_TRANSFORM_description'] );
    unset( $this->controller->piVars ['uid'] );
    unset( $this->controller->piVars ['type'] );
    unset( $this->controller->piVars ['formCheck'] );
    unset( $this->controller->piVars ['name'] );
    unset( $this->controller->piVars ['description'] );
    unset( $this->controller->piVars ['street'] );
    unset( $this->controller->piVars ['zip'] );
    unset( $this->controller->piVars ['city'] );
    unset( $this->controller->piVars ['country'] );
    unset( $this->controller->piVars ['countryzone'] );
    unset( $this->controller->piVars ['phone'] );
    unset( $this->controller->piVars ['email'] );
    unset( $this->controller->piVars ['link'] );
    unset( $this->controller->piVars ['image'] );
    unset( $this->controller->piVars ['image_caption'] );
    unset( $this->controller->piVars ['image_title'] );
    unset( $this->controller->piVars ['image_alt'] );
  }
}

?>