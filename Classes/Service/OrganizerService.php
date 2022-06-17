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
class OrganizerService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $keyId = 'tx_cal_organizer';

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
    $organizerArray = $this->getOrganizerFromTable( $pidList, ' AND tx_cal_organizer.uid=' . $uid );
    return $organizerArray [0];
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
    return $this->getOrganizerFromTable( $pidList );
  }

  /**
   * Search for organizer
   *
   * @param string $pidList
   *          to search in
   * @param string $searchword
   *          to search for
   * @return array containing the organizer objects
   */
  function search($pidList = '', $searchword = '') {

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
    $orderBy = \TYPO3\CMS\Cal\Utility\Functions::getOrderBy( 'tx_cal_organizer' );
    if ($pidList != '') {
      $additionalWhere .= ' AND tx_cal_organizer.pid IN (' . $pidList . ')';
    }
    $additionalWhere .= $this->getAdditionalWhereForLocalizationAndVersioning( 'tx_cal_organizer' );
    $table = 'tx_cal_organizer';
    $select = '*';
    $where = ' l18n_parent = 0 ' . $additionalWhere . $this->pageRepository->enableFields( 'tx_cal_organizer' );
    $groupBy = '';
    $limit = '';
    
    $rightsObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'rightscontroller' );
    $feUserUid = $rightsObj->getUserId();
    $feGroupsArray = $rightsObj->getUserGroups();
    
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_organizer_service', 'organizerServiceClass', 'service' );
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'preGetOrganizerFromTableExec' )) {
        $hookObj->preGetOrganizerFromTableExec( $this, $select, $table, $where, $groupBy, $orderBy, $limit );
      }
    }
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy, $orderBy, $limit );
    if ($result) {
      $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        
        if ($languageAspect->getContentId()) {
          $row = $GLOBALS ['TSFE']->sys_page->getRecordOverlay( 'tx_cal_organizer', $row, $languageAspect->getContentId(), $languageAspect->getLegacyOverlayType(), '' );
        }
        /**
         * FIXME no public property anymore
         if ($GLOBALS ['TSFE']->sys_page->versioningPreview == TRUE) {
          // get workspaces Overlay
          $GLOBALS ['TSFE']->sys_page->versionOL( 'tx_cal_organizer', $row );
        }*/
        
        $lastOrganizer = new \TYPO3\CMS\Cal\Model\Organizer( $row, $pidList );
        
        $select = 'uid_foreign,tablenames';
        $table = 'tx_cal_organizer_shared_user_mm';
        $where = 'uid_local = ' . $row ['uid'];
        
        $sharedUserResult = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
        if ($sharedUserResult) {
          while ( $sharedUserRow = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $sharedUserResult ) ) {
            if ($sharedUserRow ['tablenames'] == 'fe_users') {
              $lastOrganizer->addSharedUser( $sharedUserRow ['uid_foreign'] );
            } else if ($sharedUserRow ['tablenames'] == 'fe_groups') {
              $lastOrganizer->addSharedGroup( $sharedUserRow ['uid_foreign'] );
            }
          }
          $GLOBALS ['TYPO3_DB']->sql_free_result( $sharedUserResult );
        }
        $organizers [] = $lastOrganizer;
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
    $where = $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchOrganizerFieldList'], 'tx_cal_organizer' );
    return $where;
  }

  function updateOrganizer($uid) {

    if (! $this->isAllowedService())
      return;
    $insertFields = array (
        
        'tstamp' => time()
    );
    // TODO: Check if all values are correct
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'organizer', false );
    $this->retrievePostData( $insertFields );
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_organizer', 'image', $insertFields, $uid );
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
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'shared' )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_organizer_shared_user_mm', 'uid_local =' . $uid );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $sharedUsers ), $uid, 'fe_users' );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $sharedGroups ), $uid, 'fe_groups' );
      if (count( $sharedUsers ) > 0 || count( $sharedGroups ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['organizer.'] ['addFeUserToShared']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['organizer.'] ['addFeGroupToShared']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['organizer.'] ['addFeGroupToShared.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_organizer_shared_user_mm', 'uid_local =' . $uid );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $userIdArray ), $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $groupIdArray ), $uid, 'fe_groups' );
      }
      if (count( $userIdArray ) > 0 || count( $groupIdArray ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    }
    
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tx_cal_organizer' );
    // Creating DB records
    $table = 'tx_cal_organizer';
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
    $this->unsetPiVars();
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function removeOrganizer($uid) {

    if (! $this->isAllowedService())
      return;
    if ($this->rightsObj->isAllowedToDeleteOrganizer()) {
      $updateFields = Array (
          
          'tstamp' => time(),
          'deleted' => 1
      );
      $table = 'tx_cal_organizer';
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $updateFields );
    }
    $this->unsetPiVars();
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
      $insertFields ['street'] = strip_tags( $this->controller->piVars ['street'] );
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
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'fax' ) || $this->rightsObj->isAllowedTo( 'create', 'organizer', 'fax' )) {
      $insertFields ['fax'] = strip_tags( $this->controller->piVars ['fax'] );
    }
    
    if ($this->rightsObj->isAllowedToEditOrganizerEmail() || $this->rightsObj->isAllowedToCreateOrganizerEmail()) {
      $insertFields ['email'] = strip_tags( $this->controller->piVars ['email'] );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'link' ) || $this->rightsObj->isAllowedTo( 'create', 'organizer', 'link' )) {
      $insertFields ['link'] = strip_tags( $this->controller->piVars ['link'] );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'countryZone' ) || $this->rightsObj->isAllowedTo( 'create', 'organizer', 'countryZone' )) {
      $insertFields ['country_zone'] = strip_tags( $this->controller->piVars ['countryzone'] );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'organizer', 'country' ) || $this->rightsObj->isAllowedTo( 'create', 'organizer', 'country' )) {
      $insertFields ['country'] = strip_tags( $this->controller->piVars ['country'] );
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
    
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'organizer' );
    $this->retrievePostData( $insertFields );
    
    // Creating DB records
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    $uid = $this->_saveOrganizer( $insertFields );
    
    if ($this->rightsObj->isAllowedTo( 'create', 'organizer', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_organizer', 'image', $insertFields, $uid );
    }
    
    $this->unsetPiVars();
    return $this->find( $uid, $this->conf ['pidList'] );
  }

  function _saveOrganizer(&$insertFields) {

    $table = 'tx_cal_organizer';
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458157 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    
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
    
    if ($this->rightsObj->isAllowedTo( 'create', 'organizer', 'shared' )) {
      if ($this->conf ['rights.'] ['create.'] ['organizer.'] ['addFeUserToShared']) {
        $sharedUsers [] = $this->rightsObj->getUserId();
      }
      if (count( $sharedUsers ) > 0 && $sharedUsers [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $sharedUsers ), $uid, 'fe_users' );
      }
      $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['organizer.'] ['addFeGroupToShared.'] ['ignore'], 1 );
      $groupArray = array_diff( $sharedGroups, $ignore );
      if (count( $groupArray ) > 0 && $groupArray [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
      }
      if (count( $sharedUsers ) > 0 || count( $groupArray ) > 0) {
        $insertFields ['shared_user_cnt'] = 1;
      } else {
        $insertFields ['shared_user_cnt'] = 0;
      }
    } else {
      $idArray = Array ();
      if ($this->conf ['rights.'] ['create.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultUser'] != '') {
        $idArray = explode( ',', $this->conf ['rights.'] ['create.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultUser'] );
      }
      if ($this->conf ['rights.'] ['create.'] ['organizer.'] ['addFeUserToShared']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      
      if (count( $idArray ) > 0 && $idArray [0] != 0) {
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $idArray ), $uid, 'fe_users' );
      }
      
      $groupArray = Array ();
      if ($this->conf ['rights.'] ['create.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultGroup'] != '') {
        $groupArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['organizer.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
        if ($this->conf ['rights.'] ['create.'] ['organizer.'] ['addFeGroupToShared']) {
          $idArray = $this->rightsObj->getUserGroups();
          $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['organizer.'] ['addFeGroupToShared.'] ['ignore'], 1 );
          $groupArray = array_diff( $idArray, $ignore );
        }
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_organizer_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
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
    $useOrganizerStructure = ($this->confArr ['useOrganizerStructure'] ? $this->confArr ['useOrganizerStructure'] : 'tx_cal_organizer');
    if ($useOrganizerStructure == $this->keyId) {
      return true;
    }
    return false;
  }

  function createTranslation($uid, $overlay) {

    $table = 'tx_cal_organizer';
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