<?php

namespace TYPO3\CMS\Cal\View;

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
use OutOfBoundsException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 *
 * @author Jeff Segars <jeff@webempoweredchurch.org>
 * @package TYPO3
 * @subpackage cal
 */
class ReminderView extends \TYPO3\CMS\Cal\View\NotificationView {

  public function __construct() {

    parent::__construct();
  }

  function remind(&$event, $eventMonitor) {

    $this->startMailer();
    
    switch ($eventMonitor ['tablenames']) {
      case 'fe_users' :
        $feUserRec = BackendUtility::getRecord( 'fe_users', $eventMonitor ['uid_foreign'] );
        $this->process( $event, $feUserRec ['email'], $eventMonitor ['tablenames'] . '_' . $feUserRec ['uid'] );
        break;
      case 'fe_groups' :
        $subType = 'getGroupsFE';
        $groups = array ();
        $serviceObj = null;
        $serviceObj = GeneralUtility::makeInstanceService( 'auth', $subType );
        if ($serviceObj == null) {
          return;
        }
        
        $serviceObj->getSubGroups( $eventMonitor ['uid_foreign'], '', $groups );
        
        $select = 'DISTINCT fe_users.email';
        $table = 'fe_groups, fe_users';
        $where = 'fe_groups.uid IN (' . implode( ',', $groups ) . ') 
						AND FIND_IN_SET(fe_groups.uid, fe_users.usergroup)
						AND fe_users.email != \'\' 
						AND fe_groups.deleted = 0 
						AND fe_groups.hidden = 0 
						AND fe_users.disable = 0
						AND fe_users.deleted = 0';
        $result2 = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
        while ( $row2 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result2 ) ) {
          $this->process( $event, $row2 ['email'], $eventMonitor ['tablenames'] . '_' . $row2 ['uid'] );
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result2 );
        break;
      case 'tx_cal_unknown_users' :
        $feUserRec = BackendUtility::getRecord( 'tx_cal_unknown_users', $eventMonitor ['uid_foreign'] );
        $this->process( $event, $feUserRec ['email'], $eventMonitor ['tablenames'] . '_' . $feUserRec ['uid'] );
        break;
    }
  }

  function process(&$event, $email, $userId) {

    if ($email != '' && GeneralUtility::validEmail( $email )) {
      $template = $this->conf ['view.'] ['event.'] ['remind.'] [$userId . '.'] ['template'];
      if (! $template) {
        $template = $this->conf ['view.'] ['event.'] ['remind.'] ['all.'] ['template'];
      }
      $titleText = $this->conf ['view.'] ['event.'] ['remind.'] [$userId . '.'] ['emailTitle'];
      if (! $titleText) {
        $titleText = $this->conf ['view.'] ['event.'] ['remind.'] ['all.'] ['emailTitle'];
      }
      $this->sendNotification( $event, $email, $template, $titleText, '' );
    }
  }

  /* @todo Figure out where this should live */
  public function scheduleReminder($calEventUID) {
    
    // Get complete record
    $eventRecord = BackendUtility::getRecord( 'tx_cal_event', $calEventUID );
    
    // get the related monitoring records
    $taskId = null;
    $offset = 0;
    
    $select = '*';
    $table = 'tx_cal_fe_user_event_monitor_mm';
    $where = 'uid_local = ' . $calEventUID;
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
      $taskId = $row ['schedulerId'];
      $offset = $row ['offset'];
      
      // maybe there is a recurring instance
      // get the uids of recurring events from index
      $now = new \TYPO3\CMS\Cal\Model\CalDate();
      $now->setTZbyId( 'UTC' );
      $now->addSeconds( $offset * 60 );
      $startDateTimeObject = new \TYPO3\CMS\Cal\Model\CalDate( $eventRecord ['start_date'] . '000000' );
      $startDateTimeObject->setTZbyId( 'UTC' );
      $startDateTimeObject->addSeconds( $eventRecord ['start_time'] );
      $start_datetime = $startDateTimeObject->format( '%Y%m%d%H%M%S' );
      $select2 = '*';
      $table2 = 'tx_cal_index';
      $where2 = 'start_datetime >= ' . $now->format( '%Y%m%d%H%M%S' ) . ' AND event_uid = ' . $calEventUID;
      $orderby2 = 'start_datetime asc';
      $result2 = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select2, $table2, $where2, $orderby2 );
      if ($result) {
        $tmp = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result2 );
        if (is_array( $tmp )) {
          $start_datetime = $tmp ['start_datetime'];
          $nextOccuranceTime = new \TYPO3\CMS\Cal\Model\CalDate( $tmp ['start_datetime'] );
          $nextOccuranceTime->setTZbyId( 'UTC' );
          $nextOccuranceEndTime = new \TYPO3\CMS\Cal\Model\CalDate( $tmp ['end_datetime'] );
          $nextOccuranceEndTime->setTZbyId( 'UTC' );
          $eventRecord ['start_date'] = $nextOccuranceTime->format( '%Y%m%d' );
          $eventRecord ['start_time'] = $nextOccuranceTime->getHour() * 3600 + $nextOccuranceTime->getMinute() * 60 + $nextOccuranceTime->getSecond();
          $eventRecord ['end_date'] = $nextOccuranceEndTime->format( '%Y%m%d' );
          $eventRecord ['end_time'] = $nextOccuranceEndTime->getHour() * 3600 + $nextOccuranceEndTime->getMinute() * 60 + $nextOccuranceEndTime->getSecond();
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result2 );
      }
      
      if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'scheduler' )) {
        
        $scheduler = new \TYPO3\CMS\Scheduler\Scheduler();
        $date = new \TYPO3\CMS\Cal\Model\CalDate( $start_datetime );
        $date->setTZbyId( 'UTC' );
        $timestamp = $date->getTime();
        $offsetTime = new \TYPO3\CMS\Cal\Model\CalDate();
        $offsetTime->copy( $date );
        $offsetTime->setTZbyId( 'UTC' );
        $offsetTime->addSeconds( - 1 * $offset * 60 );
        if ($taskId > 0) {
          if ($offsetTime->isFuture()) {
            try {
              $task = $scheduler->fetchTask( $taskId );
              $execution = new \TYPO3\CMS\Scheduler\Execution();
              $execution->setStart( $timestamp - ($offset * 60) );
              $execution->setIsNewSingleExecution( true );
              $execution->setMultiple( false );
              $execution->setEnd( time() - 1 );
              $task->setExecution( $execution );
              $task->setDisabled( false );
              $scheduler->saveTask( $task );
            } catch ( OutOfBoundsException $e ) {
              $this->createSchedulerTask( $scheduler, $date, $calEventUID, $timestamp, $offset, $row ['uid'] );
            }
          } else {
            $this->deleteReminder( $calEventUID );
          }
        } else {
          // taskId == 0 -> schedule task
          $this->createSchedulerTask( $scheduler, $date, $calEventUID, $timestamp, $offset, $row ['uid'] );
        }
      }
    }
  }

  function createSchedulerTask(&$scheduler, $date, $calEventUID, $timestamp, $offset, $uid) {

    if ($date->isFuture()) {
      /* Set up the scheduler event */
      $task = new \TYPO3\CMS\Cal\Cron\ReminderScheduler();
      $task->setUID( $calEventUID );
      $taskGroup = BackendUtility::getRecordRaw( 'tx_scheduler_task_group', 'groupName="cal"' );
      if ($taskGroup ['uid']) {
        $task->setTaskGroup( $taskGroup ['uid'] );
      } else {
        $crdate = time();
        $insertFields = Array ();
        $insertFields ['pid'] = 0;
        $insertFields ['tstamp'] = $crdate;
        $insertFields ['crdate'] = $crdate;
        $insertFields ['cruser_id'] = 0;
        $insertFields ['groupName'] = 'cal';
        $insertFields ['description'] = 'Calendar Base';
        $table = 'tx_scheduler_task_group';
        $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
        if (FALSE === $result) {
          throw new \RuntimeException( 'Could not write ' . $table . ' record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458160 );
        }
        $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
        $task->setTaskGroup( $uid );
      }
      $task->setDescription( 'Reminder of a calendar event (id=' . $calEventUID . ')' );
      /* Schedule the event */
      $execution = new \TYPO3\CMS\Scheduler\Execution();
      $execution->setStart( $timestamp - ($offset * 60) );
      $execution->setIsNewSingleExecution( true );
      $execution->setMultiple( false );
      $execution->setEnd( time() - 1 );
      $task->setExecution( $execution );
      $scheduler->addTask( $task );
      $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( 'tx_cal_fe_user_event_monitor_mm', 'uid=' . $uid, Array (
          
          'schedulerId' => $task->getTaskUid()
      ) );
    } else {
    }
  }

  /* @todo Figure out where this should live */
  function deleteReminder($eventUid) {

    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'scheduler' )) {
      $eventRow = BackendUtility::getRecordRaw( 'tx_cal_fe_user_event_monitor_mm', 'uid_local=' . $eventUid );
      $taskId = $eventRow ['schedulerId'];
      if ($taskId > 0) {
        $scheduler = new \TYPO3\CMS\Scheduler\Scheduler();
        try {
          $task = $scheduler->fetchTask( $taskId );
          $scheduler->removeTask( $task );
        } catch ( OutOfBoundsException $e ) {
        }
      }
    } else if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'gabriel' )) {
      $monitoringUID = 'tx_cal_fe_user_event_monitor_mm:' . $eventUid;
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_gabriel', ' crid="' . $eventUid . '"' );
    }
  }

  function deleteReminderForEvent($eventUid) {
    // get the related monitoring records
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( 'uid_local', 'tx_cal_fe_user_event_monitor_mm', 'uid_local = ' . $eventUid );
    while ( $monitorRow = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
      /* Check for existing gabriel events and remove them */
      $this->deleteReminder( $monitorRow ['uid_local'] );
    }
  }
}

?>