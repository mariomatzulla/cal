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

/**
 * A concrete model for the calendar.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class TodoService extends \TYPO3\CMS\Cal\Service\EventService {

  public function __construct() {

    parent::__construct();
  }

  /**
   * Finds all todos within a given range.
   *
   * @return array array of events represented by the model.
   */
  function findAllWithin(&$start_date, &$end_date, $pidList, $eventType = '4', $additionalWhere = '') {

    return parent::findAllWithin( $start_date, $end_date, $pidList, '4', $additionalWhere );
  }

  /**
   * Finds all events.
   *
   * @return array array of todos represented by the model.
   */
  function findAll($pidList, $eventType = '4') {

    return parent::findAll( $pidList, '4' );
  }

  function createEvent($row, $isException) {

    return new \TYPO3\CMS\Cal\Model\TodoModel( $row, $this->getServiceKey() );
  }

  /**
   * Finds a single event.
   *
   * @return object todo represented by the model.
   */
  function find($uid, $pidList, $showHiddenEvents = false, $showDeletedEvents = false, $getAllInstances = false, $disableCalendarSearchString = false, $disableCategorySearchString = false, $eventType = '4') {

    return parent::find( $uid, $pidList, $showHiddenEvents, $showDeletedEvents, $getAllInstances, $disableCalendarSearchString, $disableCategorySearchString, '4' );
  }

  function findCurrentTodos($disableCalendarSearchString = false, $disableCategorySearchString = false) {

    $confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    $this->starttime = new \TYPO3\CMS\Cal\Model\CalDate( $confArr ['recurrenceStart'] );
    $this->endtime = new \TYPO3\CMS\Cal\Model\CalDate( $confArr ['recurrenceEnd'] );
    $categories = &$this->modelObj->findAllCategories( 'cal_category_model', '', $this->conf ['pidList'] );
    $categories = array ();
    
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', 'tx_cal_category' );
    $categoryService->getCategoryArray( $this->conf ['pidList'], $categories );
    
    $calendarSearchString = '';
    if (! $disableCalendarSearchString) {
      $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
      $calendarSearchString = $calendarService->getCalendarSearchString( $this->conf ['pidList'], true, $this->conf ['calendar'] ? $this->conf ['calendar'] : '' );
    }
    
    // putting everything together
    $additionalWhere = $calendarSearchString . ' AND tx_cal_event.completed < 100 AND tx_cal_event.pid IN (' . $this->conf ['pidList'] . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' );
    $getAllInstances = true;
    $eventType = \TYPO3\CMS\Cal\Model\Model::EVENT_TYPE_TODO;
    
    return $this->getEventsFromTable( $categories [0] [0], $getAllInstances, $additionalWhere, $this->getServiceKey(), ! $disableCategorySearchString, false, $eventType );
  }

  function saveEvent($pid) {

    $object = $this->modelObj->createEvent( 'tx_cal_todo' );
    $object->updateWithPIVars( $this->controller->piVars );
    
    $crdate = time();
    $insertFields = Array ();
    $insertFields ['pid'] = $pid;
    $insertFields ['tstamp'] = $crdate;
    $insertFields ['crdate'] = $crdate;
    
    if ($GLOBALS ['TSFE']->sys_language_content > 0 && $this->conf ['showRecordsWithoutDefaultTranslation'] == 1 && $this->rightsObj->isAllowedTo( 'create', 'translation' )) {
      $insertFields ['sys_language_uid'] = $GLOBALS ['TSFE']->sys_language_content;
    }
    
    // TODO: Check if all values are correct
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'event' );
    $this->filterDataToBeSaved( $insertFields, $object );
    
    if (! $insertFields ['calendar_id'] && $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['calendar_id.'] ['default']) {
      $insertFields ['calendar_id'] = $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['calendar_id.'] ['default'];
    }
    
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    $insertFields ['fe_cruser_id'] = $this->rightsObj->getUserId();
    
    if (is_array( $this->controller->piVars ['notify'] )) {
      $insertFields ['notify_ids'] = implode( ',', $this->controller->piVars ['notify'] );
    } else {
      $insertFields ['notify_ids'] = $this->controller->piVars ['notify_ids'];
    }
    if (is_array( $this->controller->piVars ['exception_ids'] )) {
      $insertFields ['exception_ids'] = implode( ',', $this->controller->piVars ['exception_ids'] );
    } else {
      $insertFields ['exception_ids'] = $this->controller->piVars ['exception_ids'];
    }
    
    $uid = $this->_saveEvent( $insertFields, $object );
    
    $this->conf ['category'] = $this->conf ['view.'] ['allowedCategories'];
    $this->conf ['calendar'] = $this->conf ['view.'] ['allowedCalendar'];
    
    $this->unsetPiVars();
    $insertFields ['uid'] = $uid;
    $insertFields ['category'] = $this->controller->piVars ['category_ids'];
    $this->_notify( $insertFields );
    if ($object->getSendoutInvitation()) {
      $object->setUid( $uid );
      $this->_invite( $object );
    }
    $this->_scheduleReminder( $uid );
    
    /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
    $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $GLOBALS ['TSFE']->id );
    $rgc->generateIndexForUid( $uid, 'tx_cal_event' );
    
    // Hook: saveEvent
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_todo_service', 'todoServiceClass' );
    \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'saveTodo', $this, $object );
    
    \TYPO3\CMS\Cal\Utility\Functions::clearCache();
    return $this->find( $uid, $pid );
  }

  function _saveEvent(&$eventData, $object) {

    $tempValues = array ();
    $tempValues ['notify_ids'] = $eventData ['notify_ids'];
    unset( $eventData ['notify_ids'] );
    $tempValues ['exception_ids'] = $eventData ['exception_ids'];
    unset( $eventData ['exception_ids'] );
    $tempValues ['attendee_ids'] = $eventData ['attendee_ids'];
    unset( $eventData ['attendee_ids'] );
    
    // Creating DB records
    $table = 'tx_cal_event';
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458159 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'image', $eventData );
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'attachment' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'attachment', $eventData );
    }
    
    // creating relation records
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'notify' )) {
      if ($tempValues ['notify_ids'] != '') {
        $user = Array ();
        $group = Array ();
        $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['notify_ids'] ) ), $user, $group );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', $user, $uid, 'fe_users' );
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['addFeGroupToNotify.'] ['ignore'], 1 );
        $groupArray = array_diff( $group, $ignore );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
      }
    } else if ($this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['notify.'] ['defaultUser'] || $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['notify.'] ['defaultGroup']) {
      $idArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToNotify']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $idArray ), $uid, 'fe_users' );
      $idArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['notify.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['create.'] ['todo.'] ['addFeGroupToNotify']) {
        $idArray = array_merge( $idArray, $this->rightsObj->getUserGroups() );
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $idArray ), $uid, 'fe_groups' );
    } else if ($this->rightsObj->isLoggedIn() && $this->conf ['rights.'] ['create.'] ['todo.'] ['addFeUserToNotify']) {
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
          
          $this->rightsObj->getUserId()
      ), $uid, 'fe_users' );
    }
    if ($this->conf ['rights.'] ['create.'] ['todo.'] ['public']) {
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['notifyUsersOnPublicCreate'], 1 ), $uid, 'fe_users' );
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'shared' )) {
      $user = $object->getSharedUsers();
      $group = $object->getSharedGroups();
      if ($this->conf ['rights.'] ['create.'] ['todo.'] ['addFeUserToShared']) {
        $user [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $user ), $uid, 'fe_users' );
      $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['addFeGroupToShared.'] ['ignore'], 1 );
      $groupArray = array_diff( $group, $ignore );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
    } else {
      $idArray = explode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['shared.'] ['defaultUser'] );
      if ($this->conf ['rights.'] ['create.'] ['todo.'] ['addFeUserToShared']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $idArray ), $uid, 'fe_users' );
      
      $groupArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['create.'] ['todo.'] ['addFeGroupToShared']) {
        $idArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['todo.'] ['addFeGroupToShared.'] ['ignore'], 1 );
        $groupArray = array_diff( $idArray, $ignore );
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'category' )) {
      $categoryIds = Array ();
      foreach ( ( array ) $object->getCategories() as $category ) {
        if (is_object( $category )) {
          $categoryIds [] = $category->getUid();
        }
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_category_mm', $categoryIds, $uid, '' );
    } else {
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_category_mm', array (
          
          $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['category.'] ['default']
      ), $uid, '' );
    }
    
    return $uid;
  }

  function updateEvent($uid) {

    $insertFields = array (
        
        'tstamp' => time()
    );
    
    $event = $this->find( $uid, $this->conf ['pidList'], true, true, false, false, false, '0,1,2,3,4' );
    $event_old = $this->find( $uid, $this->conf ['pidList'], true, true, false, false, false, '0,1,2,3,4' );
    // $event = new tx_cal_phpicalendar_model(null, false, '');
    $this->conf ['category'] = $this->conf ['view.'] ['allowedCategories'];
    $this->conf ['calendar'] = $this->conf ['view.'] ['allowedCalendar'];
    
    $event->updateWithPIVars( $this->controller->piVars );
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'event', false );
    
    $this->filterDataToBeUpdated( $insertFields, $event );
    
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tx_cal_event' );
    
    if (isset( $this->controller->piVars ['notify_ids'] )) {
      $insertFields ['notify_ids'] = strip_tags( $this->controller->piVars ['notify_ids'] );
    } else if (is_array( $this->controller->piVars ['notify'] )) {
      $insertFields ['notify_ids'] = strip_tags( implode( ',', $this->controller->piVars ['notify'] ) );
    }
    if (isset( $this->controller->piVars ['exception_ids'] )) {
      if (is_array( $this->controller->piVars ['exception_ids'] )) {
        $insertFields ['exception_ids'] = strip_tags( implode( ',', $this->controller->piVars ['exception_ids'] ) );
      } else {
        $insertFields ['exception_ids'] = strip_tags( $this->controller->piVars ['exception_ids'] );
      }
    }
    
    $this->_updateEvent( $uid, $insertFields, $event );
    
    $this->_notifyOfChanges( $event_old, $insertFields );
    if ($event->getSendoutInvitation()) {
      $this->_invite( $event );
    }
    $this->unsetPiVars();
    
    /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
    $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $GLOBALS ['TSFE']->id );
    $rgc->generateIndexForUid( $uid, 'tx_cal_event' );
    
    // Hook: updateEvent
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_todo_service', 'todoServiceClass' );
    \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'updateTodo', $this, $event );
    
    \TYPO3\CMS\Cal\Utility\Functions::clearCache();
    return $event;
  }

  function _updateEvent($uid, $eventData, $object) {

    $tempValues = array ();
    $tempValues ['notify_ids'] = $eventData ['notify_ids'];
    unset( $eventData ['notify_ids'] );
    $tempValues ['exception_ids'] = $eventData ['exception_ids'];
    unset( $eventData ['exception_ids'] );
    $tempValues ['attendee_ids'] = $eventData ['attendee_ids'];
    unset( $eventData ['attendee_ids'] );
    
    // Creating DB records
    $table = 'tx_cal_event';
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $eventData );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write todo record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1453825432 );
    }
    
    $eventData ['pid'] = $object->row ['pid'];
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'image', $eventData );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'attachment' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'attachment', $eventData );
    }
    
    $where = ' AND tx_cal_event.uid=' . $uid . ' AND tx_cal_fe_user_category_mm.tablenames="fe_users" ' . $this->pageRepository->enableFields( 'tx_cal_event' );
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'category' )) {
      $categoryIds = Array ();
      foreach ( $object->getCategories() as $category ) {
        if (is_object( $category )) {
          $categoryIds [] = $category->getUid();
        }
      }
      $table = 'tx_cal_event_category_mm';
      $where = 'uid_local = ' . $uid;
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( $table, $where );
      $this->insertIdsIntoTableWithMMRelation( $table, $categoryIds, $uid, '' );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'notify' ) && ! is_null( $tempValues ['notify_ids'] )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_fe_user_event_monitor_mm', 'uid_local =' . $uid . ' AND tablenames in ("fe_users","fe_groups")' );
      if ($tempValues ['notify_ids'] != '') {
        $user = Array ();
        $group = Array ();
        $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['notify_ids'] ) ), $user, $group );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', $user, $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', $group, $uid, 'fe_groups' );
      }
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['notify.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeUserToNotify']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['notify.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['todo.'] ['addFeGroupToNotify']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['addFeGroupToNotify.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_fe_user_event_monitor_mm', 'uid_local =' . $uid . ' AND tablenames in ("fe_users","fe_groups")' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $userIdArray ), $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $groupIdArray ), $uid, 'fe_groups' );
      }
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'shared' )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event_shared_user_mm', 'uid_local =' . $uid );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $object->getSharedUsers() ), $uid, 'fe_users' );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $object->getSharedGroups() ), $uid, 'fe_groups' );
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['shared.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['todo.'] ['addFeUserToShared']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeGroupToShared']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['todo.'] ['addFeGroupToShared.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event_shared_user_mm', 'uid_local =' . $uid );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $userIdArray ), $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupIdArray ), $uid, 'fe_groups' );
      }
    }
  }

  function removeEvent($uid) {

    $event = $this->find( $uid, $this->conf ['pidList'], true, true );
    if (is_object( $event ) && $event->isUserAllowedToDelete()) {
      $config = $this->conf ['calendar'];
      $this->conf ['calendar'] = intval( $this->controller->piVars ['calendar_id'] );
      $event = $this->find( $uid, $this->conf ['pidList'], true, true );
      $this->conf ['calendar'] = $config;
      
      $updateFields = array (
          
          'tstamp' => time(),
          'deleted' => 1
      );
      $table = 'tx_cal_event';
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $updateFields );
      if (FALSE === $result) {
        throw new \RuntimeException( 'Could not write todo record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1453825617 );
      }
      
      $fields = $event->getValuesAsArray();
      $fields ['deleted'] = 1;
      $fields ['tstamp'] = $updateFields ['tstamp'];
      $this->_notify( $fields );
      $this->stopReminder( $uid );
      
      /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
      $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator' );
      $rgc->cleanIndexTableOfUid( $uid, $table );
      
      // Hook: removeEvent
      $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_todo_service', 'todoServiceClass' );
      \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'removeTodo', $this, $event );
      \TYPO3\CMS\Cal\Utility\Functions::clearCache();
      $this->unsetPiVars();
    }
  }

  function filterDataToBeSaved(&$insertFields, &$object) {

    $hidden = 0;
    if (isset( $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['hidden.'] ['default'] ) && ! $this->rightsObj->isAllowedTo( 'edit', 'todo', 'hidden' ) && ! $this->rightsObj->isAllowedTo( 'create', 'todo', 'hidden' )) {
      $hidden = $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($object->isHidden() && $this->rightsObj->isAllowedTo( 'create', 'todo', 'hidden' )) {
      $hidden = 1;
    }
    $insertFields ['hidden'] = $hidden;
    $insertFields ['type'] = $object->getEventType();
    
    $insertFields ['allday'] = $object->isAllday() ? '1' : '0';
    if (! $this->rightsObj->isAllowedTo( 'create', 'todo', 'allday' )) {
      $insertFields ['allday'] = $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['allday.'] ['default'];
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'calendar' )) {
      if ($object->getCalendarUid() != '') {
        $insertFields ['calendar_id'] = $object->getCalendarUid();
      } else if ($this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['calendar.'] ['default']) {
        $insertFields ['calendar_id'] = $this->conf ['rights.'] ['create.'] ['todo.'] ['fields.'] ['calendar_id.'] ['default'];
      } else {
        $insertFields ['calendar_id'] = ''; // TODO: Set the calendar_id to some value
      }
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'starttime' ) || $this->rightsObj->isAllowedTo( 'create', 'todo', 'startdate' )) {
      if (is_object( $object->getStart() )) {
        $start = $object->getStart();
        $insertFields ['start_date'] = $start->format( '%Y%m%d' );
        $insertFields ['start_time'] = intval( $start->format( '%H' ) ) * 3600 + intval( $start->format( '%M' ) ) * 60;
      } else {
        return;
      }
      if (is_object( $object->getEnd() )) {
        $end = $object->getEnd();
        $insertFields ['end_date'] = $end->format( '%Y%m%d' );
        $insertFields ['end_time'] = intval( $end->format( '%H' ) ) * 3600 + intval( $end->format( '%M' ) ) * 60;
      } else {
        return;
      }
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'title' )) {
      $insertFields ['title'] = $object->getTitle();
      ;
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'organizer' )) {
      $insertFields ['organizer'] = $object->getOrganizer();
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'cal_organizer' )) {
      $insertFields ['organizer_id'] = $object->getOrganizerId();
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'location' )) {
      $insertFields ['location'] = $object->getLocation();
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'cal_location' )) {
      $insertFields ['location_id'] = $object->getLocationId();
    }
    if ($object->getDescription() != '' && $this->rightsObj->isAllowedTo( 'create', 'todo', 'description' )) {
      $insertFields ['description'] = $object->getDescription();
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'recurring' )) {
      $insertFields ['freq'] = $object->getFreq();
      $insertFields ['byday'] = strtolower( implode( ',', $object->getByDay() ) );
      $insertFields ['bymonthday'] = implode( ',', $object->getByMonthDay() );
      $insertFields ['bymonth'] = implode( ',', $object->getByMonth() );
      $until = $object->getUntil();
      if (is_object( $until )) {
        $insertFields ['until'] = $until->format( '%Y%m%d' );
      }
      $insertFields ['cnt'] = $object->getCount();
      $insertFields ['intrval'] = $object->getInterval();
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'status' )) {
      $insertFields ['status'] = $object->getStatus();
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'priority' )) {
      $insertFields ['priority'] = $object->getPriority();
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'todo', 'completed' )) {
      $insertFields ['completed'] = $object->getCompleted();
    }
    
    // Hook initialization:
    $hookObjectsArr = array ();
    if (is_array( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_todo_service.php'] ['addAdditionalField'] )) {
      foreach ( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_todo_service.php'] ['addAdditionalField'] as $classRef ) {
        $hookObjectsArr [] = & GeneralUtility::getUserObj( $classRef );
      }
    }
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'addAdditionalField' )) {
        $hookObj->addAdditionalField( $insertFields, $this );
      }
    }
  }

  function filterDataToBeUpdated(&$insertFields, &$object) {

    $hidden = 0;
    if (isset( $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['hidden.'] ['default'] ) && ! $this->rightsObj->isAllowedTo( 'edit', 'todo', 'hidden' ) && ! $this->rightsObj->isAllowedTo( 'create', 'todo', 'hidden' )) {
      $hidden = $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($object->isHidden() && $this->rightsObj->isAllowedToEditEventHidden()) {
      $hidden = 1;
    }
    $insertFields ['hidden'] = $hidden;
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'type' )) {
      $insertFields ['type'] = $object->getEventType();
    }
    
    $insertFields ['allday'] = $object->isAllday() ? '1' : '0';
    if (! $this->rightsObj->isAllowedTo( 'edit', 'todo', 'allday' )) {
      $insertFields ['allday'] = $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['allday.'] ['default'];
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'calendar' )) {
      if ($object->getCalendarUid() != '') {
        $insertFields ['calendar_id'] = $object->getCalendarUid();
      } else if ($this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['calendar.'] ['default']) {
        $insertFields ['calendar_id'] = $this->conf ['rights.'] ['edit.'] ['todo.'] ['fields.'] ['calendar_id.'] ['default'];
      } else {
        $insertFields ['calendar_id'] = ''; // TODO: Set the calendar_id to some value
      }
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'starttime' ) || $this->rightsObj->isAllowedTo( 'edit', 'todo', 'startday' )) {
      if (is_object( $object->getStart() )) {
        $start = $object->getStart();
        $insertFields ['start_date'] = $start->format( '%Y%m%d' );
        $insertFields ['start_time'] = intval( $start->format( '%H' ) ) * 3600 + intval( $start->format( '%M' ) ) * 60;
      } else {
        return;
      }
      if (is_object( $object->getEnd() )) {
        $end = $object->getEnd();
        $insertFields ['end_date'] = $end->format( '%Y%m%d' );
        $insertFields ['end_time'] = intval( $end->format( '%H' ) ) * 3600 + intval( $end->format( '%M' ) ) * 60;
      } else {
        return;
      }
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'title' )) {
      $insertFields ['title'] = $object->getTitle();
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'organizer' )) {
      $insertFields ['organizer'] = $object->getOrganizer();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'cal_organizer' )) {
      $insertFields ['organizer_id'] = $object->getOrganizerId();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'location' )) {
      $insertFields ['location'] = $object->getLocation();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'cal_location' )) {
      $insertFields ['location_id'] = $object->getLocationId();
    }
    if ($object->getDescription() != '' && $this->rightsObj->isAllowedTo( 'edit', 'todo', 'description' )) {
      $insertFields ['description'] = $object->getDescription();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'recurring' )) {
      $insertFields ['freq'] = $object->getFreq();
      $insertFields ['byday'] = strtolower( implode( ',', $object->getByDay() ) );
      $insertFields ['bymonthday'] = implode( ',', $object->getByMonthDay() );
      $insertFields ['bymonth'] = implode( ',', $object->getByMonth() );
      $until = $object->getUntil();
      $insertFields ['until'] = $until->format( '%Y%m%d' );
      $insertFields ['cnt'] = $object->getCount();
      $insertFields ['intrval'] = $object->getInterval();
      $insertFields ['rdate_type'] = $object->getRdateType();
      $insertFields ['rdate'] = $object->getRdate();
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'status' )) {
      $insertFields ['status'] = $object->getStatus();
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'priority' )) {
      $insertFields ['priority'] = $object->getPriority();
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'todo', 'completed' )) {
      $insertFields ['completed'] = $object->getCompleted();
    }
    
    // Hook initialization:
    $hookObjectsArr = array ();
    if (is_array( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_todo_service.php'] ['addAdditionalField'] )) {
      foreach ( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_todo_service.php'] ['addAdditionalField'] as $classRef ) {
        $hookObjectsArr [] = & GeneralUtility::getUserObj( $classRef );
      }
    }
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'addAdditionalField' )) {
        $hookObj->addAdditionalField( $insertFields, $this );
      }
    }
  }

  function search($pidList = '', $start_date, $end_date, $searchword, $locationIds = '', $organizerIds = '', $eventType = '0,1,2,3') {

    return parent::search( $pidList, $start_date, $end_date, $searchword, $locationIds, $organizerIds, '4' );
  }

  function getRecurringEventsFromIndex($event, $ex_event_dates = Array()) {

    $master_array = Array ();
    $startDate = $event->getStart();
    $master_array [$startDate->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($startDate->format( '%H%M' ))] [$event->getUid()] = &$event;
    $select = '*';
    $table = 'tx_cal_index';
    $where = 'event_uid = ' . $event->getUid() . ' AND start_datetime >= ' . $this->starttime->format( '%Y%m%d%H%M%S' ) . ' AND start_datetime <= ' . $this->endtime->format( '%Y%m%d%H%M%S' );
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        $nextOccuranceTime = new \TYPO3\CMS\Cal\Model\CalDate( $row ['start_datetime'] );
        $nextOccuranceEndTime = new \TYPO3\CMS\Cal\Model\CalDate( $row ['end_datetime'] );
        $new_event = new \TYPO3\CMS\Cal\Model\TodoRecModel( $event, $nextOccuranceTime, $nextOccuranceEndTime );
        if ($new_event->isAllday()) {
          $master_array [$nextOccuranceTime->format( '%Y%m%d' )] ['-1'] [$event->getUid()] = $new_event;
        } else {
          $master_array [$nextOccuranceTime->format( '%Y%m%d' )] [$nextOccuranceTime->format( '%H%M' )] [$event->getUid()] = $new_event;
        }
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return $master_array;
  }

  function unsetPiVars() {

    parent::unsetPivars();
    unset( $this->controller->piVars ['priority'] );
    unset( $this->controller->piVars ['completed'] );
    unset( $this->controller->piVars ['status'] );
  }
}

?>