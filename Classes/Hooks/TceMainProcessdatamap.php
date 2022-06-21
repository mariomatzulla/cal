<?php

namespace TYPO3\CMS\Cal\Hooks;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;

//define( 'ICALENDAR_PATH', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath( 'cal' ) . 'Classes/Model/ICalendar.php' );

/**
 * This hook extends the tcemain class.
 * It catches changes on tx_cal_event
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class TceMainProcessdatamap {

  public static function processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, &$tce) {

    /* If we have an existing calendar event */
    if ($table == 'tx_cal_event' && count( $fieldArray ) > 1) {
      if ($fieldArray ['start_date']) {
        $fieldArray ['start_date'] = self::convertBackendDateToYMD( $fieldArray ['start_date'] );
      }
      
      if ($fieldArray ['end_date']) {
        $fieldArray ['end_date'] = self::convertBackendDateToYMD( $fieldArray ['end_date'] );
      }
      
      /* If the end date is blank or earlier than the start date */
      if ($fieldArray ['end_date'] < $fieldArray ['start_date']) {
        $fieldArray ['end_date'] = $fieldArray ['start_date'];
      }
      
      if (isset($fieldArray ['until'])) {
        $fieldArray ['until'] = self::convertBackendDateToYMD( $fieldArray ['until'] );
      }
      
      if ($status != 'new') {
        $event = BackendUtility::getRecord( 'tx_cal_event', $id );
        
        // Do to our JS, these values get recalculated each time, but they may not have changed!
        if ($event ['start_date'] == $fieldArray ['start_date']) {
          unset( $fieldArray ['start_date'] );
        }
        if ($event ['end_date'] == $fieldArray ['end_date']) {
          unset( $fieldArray ['end_date'] );
        }
        if (isset($event ['until']) && isset($fieldArray ['until']) && $event ['until'] == $fieldArray ['until']) {
          unset( $fieldArray ['until'] );
        }
        /* If we're in a workspace, don't notify anyone about the event */
        if ($event ['pid'] > 0 && count( $fieldArray ) > 1 && ! $GLOBALS ['BE_USER']->workspace) {
          if (isset($fieldArray ['calendar_id']) && isset($event ['calendar_id']) && $event ['calendar_id'] != $fieldArray ['calendar_id']) {
            $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event_category_mm', 'uid_local=' . intval( $id ) );
          }
          
          /* Check Page TSConfig for a preview page that we should use */
          $pageIDForPlugin = self::getPageIDForPlugin( $event ['pid'] );
          
          $page = BackendUtility::getRecord( 'pages', intval( $pageIDForPlugin ), 'doktype' );
          
          if ($page ['doktype'] != 254) {
            /* Notify of changes to existing event */
            $tx_cal_api = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\Api' );
            $tx_cal_api = &$tx_cal_api->tx_cal_api_without( $pageIDForPlugin );
            
            $fieldArray ['icsUid'] = \TYPO3\CMS\Cal\Utility\Functions::getIcsUid( $tx_cal_api->conf, $event );
            
            $notificationService = \TYPO3\CMS\Cal\Utility\Functions::getNotificationService();
            if (is_object( $notificationService )) {
              $oldPath = &$notificationService->conf ['view.'] ['event.'] ['eventModelTemplate'];
              $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath( 'cal' );
              
              $oldPath = str_replace( 'EXT:cal/', $extPath, $oldPath );
              // $oldPath = str_replace(PATH_site, '', $oldPath);
              $tx_cal_api->conf ['view.'] ['event.'] ['eventModelTemplate'] = $oldPath;
              /**
               * FIXME
               * $oldBackPath = $GLOBALS ['TSFE']->tmpl->getFileName_backPath;
               * $GLOBALS ['TSFE']->tmpl->getFileName_backPath = '';
               * $fileInfo = GeneralUtility::split_fileref ($oldPath);
               * $GLOBALS ['TSFE']->tmpl->allowedPaths [] = $fileInfo ['path'];
               */
              if(!$notificationService->controller){
                $notificationService->controller = $tx_cal_api->controller;
              }
              $notificationService->controller->getDateTimeObject = new \TYPO3\CMS\Cal\Model\CalDate( $event ['start_date'] . '000000' );
              $notificationService->notifyOfChanges( $event, $fieldArray );
              if (isset($fieldArray ['send_invitation']) && $fieldArray ['send_invitation']) {
                $notificationService->invite( $event );
                $fieldArray ['send_invitation'] = 0;
              }
            }
          /**
           * FIXME
           * $GLOBALS ['TSFE']->tmpl->getFileName_backPath = $oldBackPath;
           */
          }
        }
      }
    }
    
    if ($table == 'tx_cal_exception_event' && count( $fieldArray ) > 1) {
      
      if ($fieldArray ['start_date']) {
        $fieldArray ['start_date'] = self::convertBackendDateToYMD( $fieldArray ['start_date'] );
      }
      
      if ($fieldArray ['end_date']) {
        $fieldArray ['end_date'] = self::convertBackendDateToYMD( $fieldArray ['end_date'] );
      }
      
      /* If the end date is blank or earlier than the start date */
      if ($fieldArray ['end_date'] < $fieldArray ['start_date']) {
        $fieldArray ['end_date'] = $fieldArray ['start_date'];
      }
      
      if ($fieldArray ['until']) {
        $fieldArray ['until'] = self::convertBackendDateToYMD( $fieldArray ['until'] );
      }
    }
    
    /* If we're working with a calendar and an ICS file or URL has been posted, try to import it */
    if ($table == 'tx_cal_calendar') {
      $calendar = BackendUtility::getRecord( 'tx_cal_calendar', $id );
      
      /** @var \TYPO3\CMS\Cal\Service\ICalendarService $service */
      $service = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Service\\ICalendarService' );
      
      if (isset($calendar ['type']) && ($calendar ['type'] == 1 or $calendar ['type'] == 2)) {
        self::processICS( $calendar, $fieldArray, $service );
      }
    }
    
    if ($table == 'tx_cal_fe_user_event_monitor_mm') {
      $values = explode( '_', $fieldArray ['uid_foreign'] );
      $fieldArray ['uid_foreign'] = array_pop( $values );
      $fieldArray ['tablenames'] = implode( '_', $values );
    }
    
    if ($table == 'tx_cal_location' && count( $fieldArray ) > 0 && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'wec_map' )) {
      $location = BackendUtility::getRecord( 'tx_cal_location', $id );
      if (is_array( $location )) {
        $location = array_merge( $location, $fieldArray );
      } else {
        $location = $fieldArray;
      }
      
      /* Geocode the address */
      $lookupTable = \TYPO3\CMS\Cal\Utility\Functions::makeInstance( 'JBartels\WecMap\Utility\Cache' );
      $latlong = $lookupTable->lookup( $location ['street'], $location ['city'], $location ['state'], $location ['zip'], $location ['country'] );
      $fieldArray ['latitude'] = $latlong ['lat'];
      $fieldArray ['longitude'] = $latlong ['long'];
    }
    
    if ($table == 'tx_cal_event_deviation') {
      $fieldArray ['imagecaption'] = '';
      $fieldArray ['imagealttext'] = '';
      $fieldArray ['imagetitletext'] = '';
      $fieldArray ['attachment'] = '';
      $fieldArray ['attachmentcaption'] = '';
    }
  }

  public static function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, &$tcemain) {

    /* If we have a new calendar event */
    if (($table == 'tx_cal_event' || $table == 'tx_cal_exception_event') && count( $fieldArray ) > 1) {
      $event = BackendUtility::getRecord( $table, $status == 'new' ? $tcemain->substNEWwithIDs [$id] : $id );
      
//       $calendar = BackendUtility::getRecord( 'tx_cal_calendar', $event ['calendar_id'] );
//       $insertFields = Array (
//           'tstamp' => $calendar['tstamp'] + 1,
//           );
//       $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( 'tx_cal_calendar', 'uid='.$calendar['uid'], $insertFields );
      
      /* If we're in a workspace, don't notify anyone about the event */
      if ($event ['pid'] > 0 && ! $GLOBALS ['BE_USER']->workspace) {
        /* Check Page TSConfig for a preview page that we should use */
        
        $pageIDForPlugin = self::getPageIDForPlugin( $event ['pid'] );
        $page = BackendUtility::getRecord( 'pages', intval( $pageIDForPlugin ), 'doktype' );
        
        if ($page ['doktype'] != 254) {
          $tx_cal_api = new \TYPO3\CMS\Cal\Controller\Api();
          $tx_cal_api = &$tx_cal_api->tx_cal_api_without( $pageIDForPlugin );
          
          if (isset($event ['event_type']) && isset($event ['ref_event_id']) && $event ['event_type'] == 3 && ! $event ['ref_event_id']) {
            $modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'modelcontroller' );
            $modelObj->updateEventAttendees( $event ['uid'], 'tx_cal_phpicalendar' );
          }
          
          if ($table == 'tx_cal_event' && ($status == 'new' || (isset($fieldArray ['send_invitation']) && $fieldArray ['send_invitation']))) {
            /* Notify of new event */
            $notificationService = & \TYPO3\CMS\Cal\Utility\Functions::getNotificationService();
            
            $oldPath = &$notificationService->conf ['view.'] ['event.'] ['eventModelTemplate'];
            
            $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath( 'cal' );
            
            $oldPath = str_replace( 'EXT:cal/', $extPath, $oldPath );
            // $oldPath = str_replace(PATH_site, '', $oldPath);
            $tx_cal_api->conf ['view.'] ['event.'] ['eventModelTemplate'] = $oldPath;
            /**
             * FIXME
             * $oldBackPath = $GLOBALS ['TSFE']->tmpl->getFileName_backPath;
             * $GLOBALS ['TSFE']->tmpl->getFileName_backPath = '';
             * $fileInfo = GeneralUtility::split_fileref( $oldPath );
             * $GLOBALS ['TSFE']->tmpl->allowedPaths [] = $fileInfo ['path'];
             */
            $notificationService->controller->getDateTimeObject = new \TYPO3\CMS\Cal\Model\CalDate( $event ['start_date'] . '000000' );
            
            if ($status == 'new') {
              $notificationService->notify( $event );
            }
            if ($fieldArray ['send_invitation']) {
              $notificationService->invite( $fieldArray );
              $fieldArray ['send_invitation'] = 0;
            }
            
            // FIXME $GLOBALS ['TSFE']->tmpl->getFileName_backPath = $oldBackPath;
          }
          /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
          $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $pageIDForPlugin );
          $rgc->generateIndexForUid( $event ['uid'], $table );
          
          if ($table == 'tx_cal_event' && $tx_cal_api->conf ['view.'] ['event.'] ['remind']) {
            /* Schedule reminders for new and changed events */
            $reminderService = &\TYPO3\CMS\Cal\Utility\Functions::getReminderService();
            $reminderService->scheduleReminder( $event ['uid'] );
          }
        }
      }
    }
    
    // t3ver_stage is always in the $fieldArray
    if ($table == 'tx_cal_event_deviation' && count( $fieldArray ) > 0) {
      $deviationRow = BackendUtility::getRecord( 'tx_cal_event_deviation', $id );
      if (is_array( $deviationRow )) {
        $startDate = null;
        if ($deviationRow ['start_date']) {
          $startDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationRow ['start_date'] );
        } else {
          $startDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationRow ['orig_start_date'] );
        }
        $endDate = null;
        if ($deviationRow ['end_date']) {
          $endDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationRow ['end_date'] );
        } else {
          $endDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationRow ['orig_end_date'] );
        }
        
        if (! $deviationRow ['allday']) {
          if ($deviationRow ['start_time']) {
            $startDate->addSeconds( $deviationRow ['start_time'] );
          }
          if ($deviationRow ['end_time']) {
            $endDate->addSeconds( $deviationRow ['end_time'] );
          }
        }
        
        $table = 'tx_cal_index';
        $where = 'event_deviation_uid = ' . $id;
        $insertFields = Array (
            
            'start_datetime' => $startDate->format( '%Y%m%d' ) . $startDate->format( '%H%M%S' ),
            'end_datetime' => $endDate->format( '%Y%m%d' ) . $endDate->format( '%H%M%S' )
        );
        $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
      }
    }
    if ($table == 'pages' && $status == 'new') {
      $GLOBALS ['BE_USER']->setAndSaveSessionData( 'cal_itemsProcFunc', array () );
    }
  }

  public static function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, &$tce) {

    /**
     * Demo code for using TCE to do custom validation of form elements.
     * The record is still
     * saved but a bad combination of start date and end date will generate an error message.
     */
    
    /*
     * if($table == 'tx_cal_event') { $startTimestamp = $incomingFieldArray['start_date'] + $incomingFieldArray['start_time']; $endTimestamp = $incomingFieldArray['end_date'] + $incomingFieldArray['end_time']; if ($startTimestamp > $endTimestamp) { $tce->log('tx_cal_event', 2, $id, 0, 1, "Event end (".BackendUtility::datetime($endTimestamp).") is earlier than event start (".BackendUtility::datetime($startTimestamp).").", 1); } }
     */
    
    /* preview events on eventViewPid on "save and preview" calls. but only if it's a regular event and the user is in live workspace */
    if ($table == 'tx_cal_event' && isset( $GLOBALS ['_POST'] ['_savedokview_x'] ) && ! $incomingFieldArray ['type'] && ! $GLOBALS ['BE_USER']->workspace) {
      $pagesTSConfig = BackendUtility::getPagesTSconfig( $GLOBALS ['_POST'] ['popViewId'] );
      if ($pagesTSConfig ['options.'] ['tx_cal_controller.'] ['eventViewPid']) {
        $GLOBALS ['_POST'] ['popViewId_addParams'] = ($incomingFieldArray ['sys_language_uid'] > 0 ? '&L=' . $incomingFieldArray ['sys_language_uid'] : '') . '&no_cache=1&tx_cal_controller[view]=event&tx_cal_controller[type]=tx_cal_phpicalendar&tx_cal_controller[uid]=' . $id;
        $GLOBALS ['_POST'] ['popViewId'] = $pagesTSConfig ['options.'] ['tx_cal_controller.'] ['eventViewPid'];
      }
    }
    
    if ($table == 'tx_cal_event' || $table == "tx_cal_exeption_event") {
      $event = BackendUtility::getRecord( $table, $id );
      if (intval( $event ['start_date'] ) == 0) {
        return;
      }
      
      /**
       * If we have an event, check if a start and end time have been sent.
       * If both are 0, then its an all day event.
       */
      if (array_key_exists( 'start_time', $incomingFieldArray ) && array_key_exists( 'end_time', $incomingFieldArray ) && $incomingFieldArray ['start_time'] == 0 && $incomingFieldArray ['end_time'] == 0) {
        $incomingFieldArray ['allday'] = 1;
      }
      
      /**
       * If the recurring frequency has changed and recurrence rules are not
       * already set, preset a reasonable value based on event start date/time.
       *
       * @todo Default date calculations do not take any timezone information into account.
       */
      if ($incomingFieldArray ['freq'] != $event ['freq']) {
        $date = self::convertBackendDateToPear( $incomingFieldArray ['start_date'] );
        $date->addSeconds( $incomingFieldArray ['start_time'] );
        $dayArray = self::getWeekdayOccurrence( $date );
        
        /* If we're on the 4th occurrence or later, let's assume we want the last occurrence */
        if ($dayArray [0] >= 4) {
          $dayArray [0] = - 1;
        }
        
        switch ($incomingFieldArray ['freq']) {
          case 'week': /* Default Value = Day of the week when event starts. */
						if (! $incomingFieldArray ['byday'] && ! $event ['byday']) {
              $incomingFieldArray ['byday'] = strtolower( $date->getDayName( true, 2 ) );
            }
            break;
          case 'month': /* Default Value = Day of the week and weekday occurrence when event starts */
						if (! $incomingFieldArray ['byday'] && ! $event ['byday']) {
              $incomingFieldArray ['byday'] = $dayArray [0] . strtolower( substr( $dayArray [1], 0, 2 ) );
            }
            break;
          case 'year': /* Default Value = Day of the month and month when event starts */
						if (! $incomingFieldArray ['bymonthday'] && ! $event ['bymonthday']) {
              $incomingFieldArray ['bymonthday'] = $date->getDay();
            }
            
            if (! $incomingFieldArray ['bymonth'] && ! $event ['bymonth']) {
              $incomingFieldArray ['bymonth'] = $date->getMonth();
            }
            break;
        }
      }
      
      /* Check Page TSConfig for a preview page that we should use */
      $pageIDForPlugin = self::getPageIDForPlugin( $event ['pid'] );
      $page = BackendUtility::getRecord( 'pages', intval( $pageIDForPlugin ), 'doktype' );
      
      if ($page ['doktype'] != 254) {
        /* Notify of changes to existing event */
        $tx_cal_api = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\Api' );
        $tx_cal_api = &$tx_cal_api->tx_cal_api_without( $pageIDForPlugin );
        
        $incomingFieldArray ['icsUid'] = \TYPO3\CMS\Cal\Utility\Functions::getIcsUid( $tx_cal_api->conf, $event );
      }
    }
    
    /* because of irre we have to transform the dates here */
    if ($table == 'tx_cal_event_deviation') {
      if ($incomingFieldArray ['start_date']) {
        $incomingFieldArray ['start_date'] = str_replace( '-', '', str_replace( 'T00:00:00Z', '', $incomingFieldArray ['start_date'] ) );
      } else {
      }
      
      if ($incomingFieldArray ['end_date']) {
        $incomingFieldArray ['end_date'] = str_replace( '-', '', str_replace( 'T00:00:00Z', '', $incomingFieldArray ['end_date'] ) );
      } else {
      }
      $incomingFieldArray ['imagecaption'] = '';
      $incomingFieldArray ['imagealttext'] = '';
      $incomingFieldArray ['imagetitletext'] = '';
      $incomingFieldArray ['attachment'] = '';
      $incomingFieldArray ['attachmentcaption'] = '';
    }
    
    if ($table == 'tx_cal_category' && array_key_exists( 'calendar_id', $incomingFieldArray ) && ! strstr( $id, 'NEW' )) {
      $category = BackendUtility::getRecord( 'tx_cal_category', $id );
      if ($incomingFieldArray ['calendar_id'] != $category ['calendar_id']) {
        $incomingFieldArray ['parent_category'] = 0;
      }
    }
    
    /* If an existing calendar is updated */
    if ($table == 'tx_cal_calendar' && array_key_exists( 'type', $incomingFieldArray ) && ! strstr( $id, 'NEW' )) {
      /* Get the calendar info from the db */
      $calendar = BackendUtility::getRecord( 'tx_cal_calendar', $id );
      
      $service = new \TYPO3\CMS\Cal\Service\ICalendarService();
      
      // Here we have to check if the calendar belongs to the type
      // problem with case 2 & 3 -> what to do with events of type database? delete them without warning? keep them and assign them to a default category?
      switch ($incomingFieldArray ['type']) {
        case 0: /* Standard */
					/* Delete any temporary events previously associated with this calendar */
					if ($calendar ['type'] != 0) {
            $service->deleteTemporaryEvents( $id );
            $service->deleteSchedulerTask( $id );
            $calendar ['schedulerId'] = 0;
            
            /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
            $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator' );
            $rgc->cleanIndexTableOfCalendarUid( $id );
          }
          break;
        case 1 : /* External URL or ICS file */
        case 2: /* ICS File */
					self::processICS( $calendar, $incomingFieldArray, $service );
          break;
      }
    }
    
    if ($table == 'tx_cal_exception_event_group' && ! strstr( $id, 'NEW' )) {
      $exceptionEvent = BackendUtility::getRecord( 'tx_cal_exception_event_group', $id );
      
      /* If we're in a workspace, don't notify anyone about the event */
      if ($exceptionEvent ['pid'] > 0 && ! $GLOBALS ['BE_USER']->workspace) {
        
        $pageIDForPlugin = self::getPageIDForPlugin( $exceptionEvent ['pid'] );
        $page = BackendUtility::getRecord( 'pages', intval( $pageIDForPlugin ), "doktype" );
        
        if ($page ['doktype'] != 254) {
          $tx_cal_api = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\Api' );
          $tx_cal_api = $tx_cal_api->tx_cal_api_without( $pageIDForPlugin );
          /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
          $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator' );
          $rgc->cleanIndexTableOfExceptionGroupUid( $id );
        }
      }
    }
    
    if ($table == 'tx_cal_attendee') {
      $incomingFieldArray ['fe_user_id'] = str_replace( Array (
          
          ',',
          'fe_users_'
      ), Array (
          
          '',
          ''
      ), $incomingFieldArray ['fe_user_id'] );
      $incomingFieldArray ['fe_group_id'] = str_replace( [ 
          
          ',',
          'fe_groups_'
      ], [ 
          
          '',
          ''
      ], $incomingFieldArray ['fe_group_id'] );
      if ($incomingFieldArray ['fe_group_id'] > 0) {
        $subType = 'getGroupsFE';
        $groups = [ 
            
            0
        ];
        $serviceObj = null;
        $serviceObj = GeneralUtility::makeInstanceService( 'auth', $subType );
        if ($serviceObj == null) {
          return;
        }
        
        $serviceObj->getSubGroups( $incomingFieldArray ['fe_group_id'], '', $groups );
        unset( $incomingFieldArray ['fe_group_id'] );
        
        $select = 'DISTINCT fe_users.*';
        $table = 'fe_groups, fe_users';
        $where = 'fe_groups.uid IN (' . implode( ',', $groups ) . ') 
						AND FIND_IN_SET(fe_groups.uid, fe_users.usergroup)
						AND fe_users.email != \'\' 
						AND fe_groups.deleted = 0 
						AND fe_groups.hidden = 0 
						AND fe_users.disable = 0
						AND fe_users.deleted = 0';
        $result2 = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
        $attendeeUids = Array ();
        while ( $row2 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result2 ) ) {
          $incomingFieldArray ['fe_user_id'] = $row2 ['fe_users.uid'];
          $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( 'tx_cal_attendee', $incomingFieldArray );
          if (FALSE === $result) {
            throw new \RuntimeException( 'Could not write attendee record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458136 );
          }
          $attendeeUids [] = $GLOBALS ['TYPO3_DB']->sql_insert_id();
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result2 );
        // $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_cal_event', $incomingFieldArray);
        
        foreach ( $tce->datamap ['tx_cal_event'] as $eventUid => $eventArray ) {
          $eventArray ['attendee'] = array_unique( array_merge( GeneralUtility::trimExplode( ',', $eventArray ['attendee'], 1 ), $attendeeUids ) );
        }
      }
      unset( $incomingFieldArray ['fe_group_id'] );
    }
  }

  /**
   *
   * @param
   *          pid
   */
  private static function getPageIDForPlugin($pid) {

    /* Check Page TSConfig for a preview page that we should use */
    $pageTSConf = BackendUtility::getPagesTSconfig( $pid );
    if (isset($pageTSConf ['options.'] ['tx_cal_controller.'] ['pageIDForPlugin'])) {
      $pageIDForPlugin = $pageTSConf ['options.'] ['tx_cal_controller.'] ['pageIDForPlugin'];
    } else {
      $pageIDForPlugin = $pid;
    }
    return $pageIDForPlugin;
  }

  /**
   *
   * @param array $calendar          
   * @param array $fieldArray          
   * @param \TYPO3\CMS\Cal\Service\ICalendarService $service          
   *
   */
  public static function processICS($calendar, &$fieldArray, &$service) {

    if ($fieldArray ['ics_file'] or $fieldArray ['ext_url']) {
      if ($fieldArray ['ics_file']) {
        $url = GeneralUtility::getFileAbsFileName( 'uploads/tx_cal/ics/' . $fieldArray ['ics_file'] );
      } elseif ($fieldArray ['ext_url']) {
        $fieldArray ['ext_url'] = trim( $fieldArray ['ext_url'] );
        $url = $fieldArray ['ext_url'];
      }
      
      $newMD5 = $service->updateEvents( $calendar ['uid'], $calendar ['pid'], $url, $calendar ['md5'], $calendar ['cruser_id'] );
      
      if ($newMD5) {
        
        $fieldArray ['md5'] = $newMD5;
        $pageIDForPlugin = self::getPageIDForPlugin( $calendar ['pid'] );
        $page = BackendUtility::getRecord( 'pages', intval( $pageIDForPlugin ), "doktype" );
        
        if ($page ['doktype'] != 254) {
          /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
          $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $pageIDForPlugin );
          $rgc->generateIndexForCalendarUid( $calendar ['uid'] );
        }
      }
      
      $service->scheduleUpdates( $fieldArray ['refresh'], $calendar ['uid'] );
    }
  }

  public static function getWeekdayOccurrence($date) {

    return array (
        
        ceil( $date->getDay() / 7 ),
        $date->getDayName()
    );
  }

  /**
   * Converts a date from the backend (m-d-Y or d-m-Y) into a PEAR Date object.
   *
   * @param
   *          string The date to convert.
   * @return object date object.
   */
  public static function convertBackendDateToPear($dateString) {

    $ymdString = self::convertBackendDateToYMD( $dateString );
    return new \TYPO3\CMS\Cal\Model\CalDate( $ymdString . '000000' );
  }

  /**
   * Converts a date from the backend (m-d-Y or d-m-Y or in TYPO3 v.
   * >= 4.3 timestamp) into the Ymd format.
   *
   * @param
   *          string The date to convert.
   * @return string date in Ymd format.
   */
  public static function convertBackendDateToYMD($dateString) {

    $date = new \TYPO3\CMS\Cal\Model\CalDate( $dateString );
    $date->setTZbyId( 'UTC' );
    return $date->format( '%Y%m%d' );
  }
}

?>
