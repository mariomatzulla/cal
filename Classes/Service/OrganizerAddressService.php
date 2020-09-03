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
class OrganizerAddressService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $keyId = 'tx_tt_address';

  var $tableId = 'tt_address';

  /**
   * Looks for an organizer with a given uid on a certain pid-list
   *
   * @param array $conf
   *          array
   * @param integer $uid
   *          to search for
   * @param string $pidList
   *          to search in
   * @return object tx_cal_organizer_partner object
   */
  function find($uid, $pidList) {

    if (! $this->isAllowedService())
      return;
    $organizerArray = $this->getOrganizerFromTable( $pidList, ' AND ' . $this->tableId . '.uid=' . $uid );
    return $organizerArray [0];
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
    return $this->getOrganizerFromTable( $pidList );
  }

  /**
   * Search for organizer
   *
   * @param string $pidList
   *          to search in
   * @param string $searchword
   *          term
   * @return array containing the organizer objects
   */
  function search($pidList = '', $searchword) {

    if (! $this->isAllowedService())
      return;
    return $this->getOrganizerFromTable( $pidList, $this->searchWhere( $searchword ) );
  }

  /**
   * Generates the sql query and builds organizer objects out of the result rows
   *
   * @param string $pidList
   *          to search in
   * @param string $additionalWhere
   *          where clause
   * @return array containing the organizer objects
   */
  function getOrganizerFromTable($pidList = '', $additionalWhere = '') {

    $organizers = array ();
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( $this->tableId );
    if ($pidList != '') {
      $additionalWhere .= ' AND ' . $this->tableId . '.pid IN (' . $pidList . ')';
    }
    $additionalWhere .= $this->getAdditionalWhereForLocalizationAndVersioning( $this->tableId );
    $select = '*';
    $table = $this->tableId;
    $where = 'tx_cal_controller_isorganizer = 1 AND l18n_parent = 0 ' . $additionalWhere . $this->pageRepository->enableFields( $this->tableId );
    $groupBy = '';
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( $this->tableId );
    $limit = '';
    
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_organizer_address_service', 'organizerServiceClass', 'service' );
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'preGetOrganizerFromTableExec' )) {
        $hookObj->preGetLocationFromTableExec( $this, $select, $table, $where, $groupBy, $orderBy, $limit );
      }
    }
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy, $orderBy, $limit );
    
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        $organizers [] = new \TYPO3\CMS\Cal\Model\OrganizerAddress( $row, $pidList );
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return $organizers;
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
    $where = $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchOrganizerFieldList'], 'tt_address' );
    return $where;
  }

  function updateOrganizer($uid) {

    $insertFields = array (
        
        'tstamp' => time()
    );
    // TODO: Check if all values are correct
    
    $this->retrievePostData( $insertFields );
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tt_address' );
    // Creating DB records
    $table = $this->tableId;
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function removeOrganizer($uid) {

    if (! $this->isAllowedService())
      return;
    if ($this->rightsObj->isAllowedToDeleteOrganizer()) {
      $updateFields = array (
          
          'tstamp' => time(),
          'deleted' => 1
      );
      $table = $this->tableId;
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $updateFields );
    }
  }

  function retrievePostData(&$insertFields) {

    if (! $this->isAllowedService())
      return;
    $hidden = 0;
    if ($this->controller->piVars ['hidden'] == 'true' && ($this->rightsObj->isAllowedToEditOrganizerHidden() || $this->rightsObj->isAllowedToCreateOrganizerHidden()))
      $hidden = 1;
    $insertFields ['hidden'] = $hidden;
    
    if ($this->rightsObj->isAllowedToEditOrganizerName() || $this->rightsObj->isAllowedToCreateOrganizerName()) {
      $insertFields ['name'] = strip_tags( $this->controller->piVars ['name'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerDescription() || $this->rightsObj->isAllowedToCreateOrganizerDescription()) {
      $insertFields ['description'] = Functions::removeBadHTML( $this->controller->piVars ['description'], $this->conf );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerStreet() || $this->rightsObj->isAllowedToCreateOrganizerStreet()) {
      $insertFields ['address'] = strip_tags( $this->controller->piVars ['street'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerZip() || $this->rightsObj->isAllowedToCreateOrganizerZip()) {
      $insertFields ['zip'] = strip_tags( $this->controller->piVars ['zip'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerCity() || $this->rightsObj->isAllowedToCreateOrganizerCity()) {
      $insertFields ['city'] = strip_tags( $this->controller->piVars ['city'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerPhone() || $this->rightsObj->isAllowedToCreateOrganizerPhone()) {
      $insertFields ['phone'] = strip_tags( $this->controller->piVars ['phone'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerEmail() || $this->rightsObj->isAllowedToCreateOrganizerEmail()) {
      $insertFields ['email'] = strip_tags( $this->controller->piVars ['email'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerImage() || $this->rightsObj->isAllowedToCreateOrganizerImage()) {
      $insertFields ['image'] = strip_tags( $this->controller->piVars ['image'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerLink() || $this->rightsObj->isAllowedToCreateOrganizerLink()) {
      $insertFields ['www'] = strip_tags( $this->controller->piVars ['link'] );
    }
  }

  function saveOrganizer($pid) {

    if (! $this->isAllowedService())
      return;
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
      $insertFields ['description'] = Functions::removeBadHTML( $this->controller->piVars ['description'] );
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
    $uid = $this->_saveOrganizer( $insertFields );
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function _saveOrganizer(&$insertFields) {

    $table = $this->tableId;
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458154 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    return $uid;
  }

  function isAllowedService() {

    $this->confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    $useOrganizerStructure = ($this->confArr ['useOrganizerStructure'] ? $this->confArr ['useOrganizerStructure'] : 'tx_cal_organizer');
    if ($useOrganizerStructure == $this->keyId) {
      return true;
    }
    return false;
  }

  function createTranslation($uid, $overlay) {

    $table = $this->tableId;
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
        $this->_saveOrganizer( $row );
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return;
  }
}

?>