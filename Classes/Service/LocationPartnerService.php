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

/**
 * Base model for the calendar organizer.
 * Provides basic model functionality that other
 * models can use or override by extending the class.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class LocationPartnerService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $extensionIsNotLoaded = false;

  var $keyId = 'tx_partner_main';

  public function __construct() {

    parent::__construct();
    $this->confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    $useLocationStructure = ($this->confArr ['useLocationStructure'] ? $this->confArr ['useLocationStructure'] : 'tx_cal_organizer');
    if ($useLocationStructure != 'tx_partner_main') {
      $this->extensionIsNotLoaded = true;
      return;
    }
    require_once (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath( 'partner' ) . 'api/class.tx_partner_main.php');
  }

  /**
   * Looks for an location with a given uid on a certain pid-list
   *
   * @param array $conf
   *          array
   * @param integer $uid
   *          to search for
   * @param string $pidList
   *          to search in
   * @return object tx_cal_location_partner object
   */
  function find($uid, $pidList) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    $locationArray = $this->getLocationFromTable( $pidList, ' AND ' . $this->keyId . '.uid=' . $uid );
    return $locationArray [0];
  }

  /**
   * Looks for an organizer with a given uid on a certain pid-list
   *
   * @param string $pidList
   *          to search in
   * @return array tx_cal_organizer_partner object array
   */
  function findAll($pidList) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    return $this->getLocationFromTable( $pidList );
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
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( $this->keyId );
    if ($pidList != '') {
      $additionalWhere .= ' AND ' . $this->keyId . '.pid IN (' . $pidList . ')';
    }
    $additionalWhere .= $this->getAdditionalWhereForLocalizationAndVersioning( $this->keyId );
    $select = 'uid';
    $table = $this->keyId;
    $where = ' l18n_parent = 0 ' . $additionalWhere . $this->pageRepository->enableFields( $this->keyId );
    $groupBy = '';
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( $this->keyId );
    $limit = '';
    
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_location_partner_service', 'locationServiceClass', 'service' );
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'preGetLocationFromTableExec' )) {
        $hookObj->preGetLocationFromTableExec( $this, $select, $table, $where, $groupBy, $orderBy, $limit );
      }
    }
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy, $orderBy, $limit );
    
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        $locations [] = new \TYPO3\CMS\Cal\Model\LocationPartner( $row ['uid'], $pidList );
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return $locations;
  }

  /**
   * Search for location
   *
   * @param string $pidList
   *          to search in
   * @param string $searchword
   *          term
   * @return array containing the location objects
   */
  function search($pidList = '', $searchword) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    return $this->getLocationFromTable( $pidList, $this->searchWhere( $searchword ) );
  }

  /**
   * Generates a search where clause.
   *
   * @param string $sw:          
   * @return string
   */
  function searchWhere($sw) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    $where = $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchLocationFieldList'], $this->keyId );
    return $where;
  }

  function updateLocation($uid) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    $insertFields = array (
        
        'tstamp' => time()
    );
    // TODO: Check if all values are correct
    
    $this->retrievePostData( $insertFields );
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tx_partner_main' );
    // Creating DB records
    $table = 'tx_partner_main';
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function removeLocation($uid) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    if ($this->rightsObj->isAllowedToDeleteLocation()) {
      $updateFields = array (
          
          'tstamp' => time(),
          'deleted' => 1
      );
      $table = 'tx_partner_main';
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $updateFields );
    }
  }

  function retrievePostData(&$insertFields) {

    if (! $this->isAllowedService())
      return;
    if ($this->extensionIsNotLoaded) {
      return;
    }
    $hidden = 0;
    if ($this->controller->piVars ['hidden'] == 'true' && ($this->rightsObj->isAllowedToEditLocationHidden() || $this->rightsObj->isAllowedToCreateLocationHidden()))
      $hidden = 1;
    $insertFields ['hidden'] = $hidden;
    
    if ($this->rightsObj->isAllowedToEditLocationName() || $this->rightsObj->isAllowedToCreateLocationName()) {
      $insertFields ['name'] = strip_tags( $this->controller->piVars ['name'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationDescription() || $this->rightsObj->isAllowedToCreateLocationDescription()) {
      $insertFields ['title'] = Functions::removeBadHTML( $this->controller->piVars ['description'], $this->conf );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationStreet() || $this->rightsObj->isAllowedToCreateLocationStreet()) {
      $insertFields ['address'] = strip_tags( $this->controller->piVars ['street'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationZip() || $this->rightsObj->isAllowedToCreateLocationZip()) {
      $insertFields ['zip'] = strip_tags( $this->controller->piVars ['zip'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCity() || $this->rightsObj->isAllowedToCreateLocationCity()) {
      $insertFields ['city'] = strip_tags( $this->controller->piVars ['city'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCountryZone() || $this->rightsObj->isAllowedToCreateLocationCountryZone()) {
      $insertFields ['countryzone'] = strip_tags( $this->controller->piVars ['countryzone'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationCountry() || $this->rightsObj->isAllowedToCreateLocationCountry()) {
      $inserFields ['country'] = strip_tags( $this->controller->piVars ['country'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationPhone() || $this->rightsObj->isAllowedToCreateLocationPhone()) {
      $insertFields ['phone'] = strip_tags( $this->controller->piVars ['phone'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationEmail() || $this->rightsObj->isAllowedToCreateLocationEmail()) {
      $insertFields ['email'] = strip_tags( $this->controller->piVars ['email'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationImage() || $this->rightsObj->isAllowedToCreateLocationImage()) {
      $insertFields ['image'] = strip_tags( $this->controller->piVars ['image'] );
    }
    
    if ($this->rightsObj->isAllowedToEditLocationLink() || $this->rightsObj->isAllowedToCreateLocationLink()) {
      $insertFields ['www'] = strip_tags( $this->controller->piVars ['link'] );
    }
  }

  function saveLocation($pid) {

    if ($this->extensionIsNotLoaded) {
      return;
    }
    $crdate = time();
    $insertFields = array (
        
        'pid' => $pid,
        'tstamp' => $crdate,
        'crdate' => $crdate
    );
    // TODO: Check if all values are correct
    
    $hidden = 0;
    if ($this->controller->piVars ['hidden'] == 'true')
      $hidden = 1;
    $insertFields ['hidden'] = $hidden;
    if ($this->controller->piVars ['name'] != '') {
      $insertFields ['name'] = strip_tags( $this->controller->piVars ['name'] );
    }
    if ($this->controller->piVars ['description'] != '') {
      $insertFields ['title'] = Functions::removeBadHTML( $this->controller->piVars ['description'], $this->conf );
    }
    if ($this->controller->piVars ['street'] != '') {
      $insertFields ['address'] = strip_tags( $this->controller->piVars ['street'] );
    }
    if ($this->controller->piVars ['zip'] != '') {
      $insertFields ['zip'] = strip_tags( $this->controller->piVars ['zip'] );
    }
    if ($this->controller->piVars ['city'] != '') {
      $insertFields ['city'] = strip_tags( $this->controller->piVars ['city'] );
    }
    if ($this->controller->piVars ['countryzone'] != '') {
      $insertFields ['countryzone'] = strip_tags( $this->controller->piVars ['countryzone'] );
    }
    if ($this->controller->piVars ['country'] != '') {
      $insertFields ['country'] = strip_tags( $this->controller->piVars ['country'] );
    }
    if ($this->controller->piVars ['phone'] != '') {
      $insertFields ['phone'] = strip_tags( $this->controller->piVars ['phone'] );
    }
    if ($this->controller->piVars ['email'] != '') {
      $insertFields ['email'] = strip_tags( $this->controller->piVars ['email'] );
    }
    if ($this->controller->piVars ['image'] != '') {
      $insertFields ['image'] = strip_tags( $this->controller->piVars ['image'] );
    }
    if ($this->controller->piVars ['link'] != '') {
      $insertFields ['www'] = strip_tags( $this->controller->piVars ['link'] );
    }
    
    // Creating DB records
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    $uid = $this->_saveLocation( $insertFields );
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function _saveLocation(&$insertFields) {

    $table = 'tx_partner_main';
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458152 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
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

    $table = 'tx_partner_main';
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
}

?>