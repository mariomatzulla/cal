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
use TYPO3\CMS\Cal\Model\Pear\Date\Calc;
use TYPO3\CMS\Cal\Controller\DateParser;

/**
 * A concrete model for the calendar.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class EventService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $location;

  var $calnumber = 1;

  var $starttime;

  var $endtime;

  var $internalAdditionWhere = ' AND tx_cal_calendar.nearby = 0';

  var $internalAdditionTable = '';

  public function getCalNumber() {

    return $this->calnumber;
  }

  public function setCalNumber($calnumber) {

    $this->calnumber = $calnumber;
  }

  /**
   * Finds all events within a given range.
   *
   * @return array array of events represented by the model.
   */
  function findAllWithin(&$start_date, &$end_date, $pidList, $eventType = '0,1,2,3', $additionalWhere = '') {
    
    // How to get the events
    // 1st get Calendar specified
    // 2nd get categories specified
    // 3rd get all related events
    // make an array out of the list, so we can handle it better
    $includeRecurring = true;
    if ($this->conf ['view'] == 'ics' || $this->conf ['view'] == 'single_ics') {
      $includeRecurring = false;
    }
    
    $this->setStartAndEndPoint( $start_date, $end_date );
    $dontShowOldEvents = intval ( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['dontShowOldEvents'] ?? 0);
    if ($dontShowOldEvents > 0) {
      $now = new \TYPO3\CMS\Cal\Model\CalDate();
      if ($dontShowOldEvents == 2) {
        $now->setHour( 0 );
        $now->setMinute( 0 );
        $now->setSecond( 0 );
      }
      
      if ($start_date->getTime() <= $now->getTime()) {
        $start_date->copy( $now );
      }
      if ($end_date->getTime() <= $now->getTime()) {
        $end_date->copy( $now );
        $end_date->addSeconds( 86400 );
      }
      $this->starttime->copy( $start_date );
      $this->endtime->copy( $end_date );
    }
    $formattedStarttime = $this->starttime->format( '%Y%m%d' );
    $formattedEndtime = $this->endtime->format( '%Y%m%d' );
    
    $recurringClause = '';
    // only include the recurring clause if we don't use the new recurring model or a view not needing recurring events.
    if ($includeRecurring) {
      // get the uids of recurring events from index
      $select = 'event_uid';
      $table = 'tx_cal_index';
      $where = '(start_datetime >= ' . $this->starttime->format( '%Y%m%d%H%M%S' ) . ' AND start_datetime <= ' . $this->endtime->format( '%Y%m%d%H%M%S' ) . ') OR (start_datetime < ' . $this->starttime->format( '%Y%m%d%H%M%S' ) . ' AND end_datetime > ' . $this->starttime->format( '%Y%m%d%H%M%S' ) . ')  OR (start_datetime < ' . $this->endtime->format( '%Y%m%d%H%M%S' ) . ' AND end_datetime > ' . $this->endtime->format( '%Y%m%d%H%M%S' ) . ')';
      $group = 'event_uid';
      $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $group );
      $tmpUids = array ();
      if ($result) {
        while ( $tmp = $GLOBALS ['TYPO3_DB']->sql_fetch_row( $result ) ) {
          $tmpUids [] = $tmp [0];
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
      }
      if (count( $tmpUids )) {
        $recurringClause = ' OR (tx_cal_event.uid IN (' . implode( ',', $tmpUids ) . ')) ';
      }
    }
    
    $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    $calendarSearchString = $calendarService->getCalendarSearchString( $pidList, true, $this->conf ['calendar'] ? $this->conf ['calendar'] : '' );
    
    // putting everything together
    // Franz: added simple check/include for rdate events at the end of this where clause.
    // But we need to find a way to only include rdate events within the searched timerange
    // - otherwise we'll flood the results after some time. I think we need a mm-table for that!
    $additionalWhere = ' AND tx_cal_event.pid IN (' . $pidList . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' ) . ' AND ((tx_cal_event.start_date>=' . $formattedStarttime . ' AND tx_cal_event.start_date<=' . $formattedEndtime . ') OR (tx_cal_event.end_date<=' . $formattedEndtime . ' AND tx_cal_event.end_date>=' . $formattedStarttime . ') OR (tx_cal_event.end_date>=' . $formattedEndtime . ' AND tx_cal_event.start_date<=' . $formattedStarttime . ')' . $recurringClause . ')' . $additionalWhere;
    // $additionalWhere = $calendarSearchString.' AND tx_cal_event.pid IN ('.$pidList.') '.$this->pageRepository->enableFields('tx_cal_event').' AND ((tx_cal_event.start_date>='.$formattedEndtime.' OR tx_cal_event.end_date>='.$formattedStarttime.')' . $recurringClause . ')'.$additionalWhere;
    
    $additionalWhere = $calendarSearchString . $additionalWhere;
    
    // creating the arrays the user is allowed to see
    $categories = array ();
    
    $categoryService->getCategoryArray( $pidList, $categories );
    
    // creating events
    return $this->getEventsFromTable( $categories [0] [0], $includeRecurring, $additionalWhere, $this->getServiceKey(), true, false, $eventType );
  }

  /**
   * Search for events with an according category.uid
   *
   * @param $categories array
   *          available categories
   * @param $includeRecurring boolean
   *          recurring events should be included
   * @param $categoryIds String
   *          ids to search events for
   * @param $additionalWhere String
   *          string; will be added to the where-clause
   *          
   * @return array array of tx_cal_phpcalendar_model events
   */
  function getEventsFromTable(&$categories, $includeRecurring = false, $additionalWhere = '', $serviceKey = '', $addCategoryWhere = false, $onlyMeetingsWithoutStatus = false, $eventType = '0,1,2,3') {

    $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
    
    $events = array ();
    
    $select = 'tx_cal_calendar.uid AS calendar_uid, ' . 'tx_cal_calendar.owner AS calendar_owner, ' . 'tx_cal_calendar.headerstyle AS calendar_headerstyle, ' . 'tx_cal_calendar.bodystyle AS calendar_bodystyle, ' . 'tx_cal_event.*';
    $table = 'tx_cal_event LEFT JOIN tx_cal_calendar ON tx_cal_calendar.uid = tx_cal_event.calendar_id ';
    if (0 === strpos( $this->conf ['view'], 'search' ) && $languageAspect->getContentId() > 0) {
      $select .= implode( ',tx_cal_event_l18n.', GeneralUtility::trimExplode( ',', $this->conf ['view.'] ['search.'] ['searchEventFieldList'], 1 ) );
      $table .= 'LEFT JOIN tx_cal_event as tx_cal_event_l18n ON tx_cal_event.uid = tx_cal_event_l18n.l18n_parent ';
    }
    $where = '1=1 ' . $additionalWhere;
    $orderBy = ' tx_cal_event.start_date ASC, tx_cal_event.start_time ASC';
    $groupBy = ''; // tx_cal_event.uid';
    
    $allowedEventTypes = GeneralUtility::trimExplode( ',', $eventType, 1 );
    if (! empty( $allowedEventTypes )) {
      $where .= ' AND tx_cal_event.type IN (' . implode( ',', $allowedEventTypes ) . ')';
    }
    
    if (isset($this->conf ['view.'] [$this->conf ['view'] . '.'] ['event.'] ['additionalWhere'])) {
      $where .= ' ' . $this->cObj->cObjGetSingle( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['event.'] ['additionalWhere'], $this->conf ['view.'] [$this->conf ['view'] . '.'] ['event.'] ['additionalWhere.'] );
    }
    
    if ($addCategoryWhere) {
      $categoryService->enhanceEventQuery( $select, $table, $where, $groupBy, $orderBy );
    }
    
    if ($onlyMeetingsWithoutStatus) {
      $table .= ', tx_cal_attendee';
      $where .= ' AND tx_cal_attendee.event_id = tx_cal_event.uid';
    }
    
    if (TYPO3_MODE != 'BE') {
      $where .= $this->getAdditionalWhereForLocalizationAndVersioning( 'tx_cal_event' );
    }
    
    $where .= $this->internalAdditionWhere;
    $table .= $this->internalAdditionTable;
    
    $limit = '';
    
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_event_service', 'eventServiceClass', 'service' );
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'preGetEventsFromTableExec' )) {
        $hookObj->preGetEventsFromTableExec( $this, $select, $table, $where, $groupBy, $orderBy, $limit );
      }
    }
    
    // \TYPO3\CMS\Core\Utility\DebugUtility::debug('SELECT '.$select.' FROM '.$table.' WHERE '.$where.' GROUP BY '.$groupBy.' ORDER BY '.$orderBy);
    
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy, $orderBy, $limit );
    
    $lastday = '';
    $currentday = ' ';
    $first = true;
    $lastUid = '';
    
    $eventOwnerArray = $calendarService->getCalendarOwner();
    
    $resultRows = Array ();
    $lastUid = '';
    
    // fetching all categories attached to all events in the current view
    $categoriesArray = array ();
    // allow all categories, so unset 'allowedCategory' in the 'conf' array
    $categoryService->conf ['view.'] ['allowedCategory'] = false;
    $categoryService->getCategoryArray( $this->conf ['pidList'], $categoriesArray );
    $eventCategories = &$categoriesArray [0] [1];
    $eventUids = array ();
    if ($result) {
      $selectFields = $GLOBALS ['TYPO3_DB']->admin_get_fields( 'tx_cal_event' );
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        /**
         * FIXME property versioningPreview is not public available anymore
        
        if ($GLOBALS ['TSFE']->sys_page->versioningPreview) {
          $interRow = array_intersect_key( $row, $selectFields );
          $GLOBALS ['TSFE']->sys_page->versionOL( 'tx_cal_event', $interRow );
          $GLOBALS ['TSFE']->sys_page->fixVersioningPid( 'tx_cal_event', $interRow );
          $row = array_merge( $row, $interRow );
        }
         */
        
        if ($languageAspect->getContentId()) {
          $row = $GLOBALS ['TSFE']->sys_page->getRecordOverlay( 'tx_cal_event', $row, $languageAspect->getContentId(), $languageAspect->getLegacyOverlayType(), '' );
        }
        if (! $row ['uid']) {
          continue;
        }
        
        // collect event uids for optimized queries. f.e. for exception events etc.
        $eventUids [] = $uid = $row ['uid'];
        
        // prepare category_uid
        $resultRows [$uid] = $row;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    } else {
      // Fehler
    }
    
    static $exceptionEventCache = Array ();
    
    foreach ( $resultRows as $row ) {
      $row ['event_owner'] = &$eventOwnerArray [$row ['calendar_uid']];
      if ($row ['end_date'] == 0) {
        $row ['end_date'] = $row ['start_date'];
      }
      $event = $this->createEvent( $row, false );
      
      if ($this->conf ['view.'] ['showEditableEventsOnly'] == 1 && (! $event->isUserAllowedToEdit() && ! $event->isUserAllowedToDelete())) {
        continue;
      }
      
      if (isset($row ['category_uid']) && $row ['category_uid'] > 0) {
        $categoryArray = $categoryService->getCategoriesForEvent( $row ['uid'] );
        if (is_array( $categoryArray )) {
          foreach ( $categoryArray as $category ) {
            $event->addCategory( $category );
          }
        }
      }
      $events_tmp = array ();
      if (! is_object( $event )) {
        return $events_tmp;
      }
      
      if ($row ['shared_user_cnt'] > 0) {
        $select = 'uid_foreign,tablenames';
        $table = 'tx_cal_event_shared_user_mm';
        $where = 'uid_local = ' . $row ['uid'];
        
        $sharedUserResult = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
        if ($sharedUserResult) {
          while ( $sharedUserRow = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $sharedUserResult ) ) {
            if ($sharedUserRow ['tablenames'] == 'fe_users') {
              $event->addSharedUser( $sharedUserRow ['uid_foreign'] );
            } else if ($sharedUserRow ['tablenames'] == 'fe_groups') {
              $event->addSharedGroup( $sharedUserRow ['uid_foreign'] );
            }
          }
          $GLOBALS ['TYPO3_DB']->sql_free_result( $sharedUserResult );
        }
      }
      
      // get exception events:
      $where = 'AND tx_cal_event.uid = ' . $event->getUid() . ' AND tx_cal_exception_event_mm.tablenames="tx_cal_exception_event_group" ' . $this->pageRepository->enableFields( 'tx_cal_exception_event_group' );
      $orderBy = '';
      $groupBy = '';
      $limit = '';
      $ex_events_group = array ();
      
      if (! $includeRecurring) {
        $tmp_starttime = new \TYPO3\CMS\Cal\Model\CalDate();
        $tmp_starttime->copy( $this->starttime );
        $tmp_endtime = new \TYPO3\CMS\Cal\Model\CalDate();
        $tmp_endtime->copy( $this->endtime );
        $this->starttime->copy( $event->getStart() );
        $this->endtime->copy( new \TYPO3\CMS\Cal\Model\CalDate( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxDate'] ?? '' . '000000' ) );
      }
      
      $result3 = $GLOBALS ['TYPO3_DB']->exec_SELECT_mm_query( 'tx_cal_exception_event_group.*', 'tx_cal_event', 'tx_cal_exception_event_mm', 'tx_cal_exception_event_group', $where, $groupBy, $orderBy, $limit );
      while ( $row3 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result3 ) ) {
        $event->addExceptionGroupId( $row3 ['uid'] );
        $where = 'AND tx_cal_exception_event_group.uid = ' . $row3 ['uid'] . $this->pageRepository->enableFields( 'tx_cal_exception_event' );
        
        $result4 = $GLOBALS ['TYPO3_DB']->exec_SELECT_mm_query( 'tx_cal_exception_event.*', 'tx_cal_exception_event_group', 'tx_cal_exception_event_group_mm', 'tx_cal_exception_event', $where, $groupBy, $orderBy, $limit );
        while ( $row4 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result4 ) ) {
          if ($row4 ['end_date'] == 0) {
            $row4 ['end_date'] = $row4 ['start_date'];
          }
          if (! $exceptionEventCache [$row4 ['uid']]) {
            $ex_event = $this->createEvent( $row4, true );
            $recurringInstances = $this->getRecurringEventsFromIndex( $ex_event );
            $exceptionEventCache [$row4 ['uid']] = $recurringInstances;
            $ex_events_group [] = $recurringInstances;
          } else {
            $ex_events_group [] = $exceptionEventCache [$row4 ['uid']];
          }
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result4 );
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result3 );
      
      $where = 'AND tx_cal_event.uid = ' . $event->getUid() . ' AND tx_cal_exception_event_mm.tablenames="tx_cal_exception_event" ' . $this->pageRepository->enableFields( 'tx_cal_exception_event' );
      $orderBy = ''; // 'tx_cal_exception_event.start_time ASC';
      $groupBy = '';
      $limit = '';
      
      $result2 = $GLOBALS ['TYPO3_DB']->exec_SELECT_mm_query( 'tx_cal_exception_event.*', 'tx_cal_event', 'tx_cal_exception_event_mm', 'tx_cal_exception_event', $where, $groupBy, $orderBy, $limit );
      while ( $row2 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result2 ) ) {
        if (! $exceptionEventCache [$row2 ['uid']]) {
          $event->addExceptionSingleId( $row2 ['uid'] );
          if ($row2 ['end_date'] == 0) {
            $row2 ['end_date'] = $row2 ['start_date'];
          }
          $ex_event = $this->createEvent( $row2, true );
          $recurringInstances = $this->getRecurringEventsFromIndex( $ex_event );
          $exceptionEventCache [$row2 ['uid']] = $recurringInstances;
          $ex_events_group [] = $recurringInstances;
        } else {
          $ex_events_group [] = $exceptionEventCache [$row2 ['uid']];
        }
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result2 );
      
      if (! $includeRecurring) {
        $this->starttime->copy( $tmp_starttime );
        $this->endtime->copy( $tmp_endtime );
        
        $eventStart = $event->getStart();
        $events_tmp [$eventStart->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($eventStart->format( '%H%M' ))] [$event->getUid()] = $event;
        
        $eventStart = $event->getStart();
        foreach ( $ex_events_group as $ex_events ) {
          foreach ( $ex_events as $ex_event_day ) {
            foreach ( $ex_event_day as $ex_event_array ) {
              foreach ( $ex_event_array as $ex_event ) {
                $events_tmp [$eventStart->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($eventStart->format( '%H%M' ))] [$event->getUid()]->addExceptionEvent( $ex_event );
              }
            }
          }
        }
      } else if (is_object( $event )) {
        if (in_array( $event->getFreq(), Array (
            
            'year',
            'month',
            'week',
            'day'
        ) ) || ($event->getRdate() && in_array( $event->getRdateType(), Array (
            
            'date',
            'date_time',
            'period'
        ) ))) {
          $ex_events_dates = Array ();
          foreach ( $ex_events_group as $ex_events ) {
            foreach ( $ex_events as $ex_event_day ) {
              foreach ( $ex_event_day as $ex_event_array ) {
                foreach ( $ex_event_array as $ex_event ) {
                  $ex_events_dates [$ex_event->getStart()->format( '%Y%m%d' )] = 1;
                }
              }
            }
          }
          $events_tmp = $this->getRecurringEventsFromIndex( $event, $ex_events_dates );
        } else {
          $eventStart = $event->getStart();
          $events_tmp [$eventStart->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($eventStart->format( '%H%M' ))] [$event->getUid()] = $event;
        }
      }
      if (! empty( $events )) {
        $this->mergeEvents( $events, $events_tmp );
      } else {
        $events = $events_tmp;
      }
    }
    $categoryIdArray = array();
    if(isset($this->controller->piVars ['category'])){
      $categoryIdArray = GeneralUtility::trimExplode( ',', implode( ',', ( array ) $this->controller->piVars ['category'] ), 1 );
    }
    
    if (isset($this->conf ['view.'] ['categoryMode']) && $this->conf ['view.'] ['categoryMode'] != 1 && $this->conf ['view.'] ['categoryMode'] != 3 && $this->conf ['view.'] ['categoryMode'] != 4 && $addCategoryWhere && ! (($this->conf ['view'] == 'ics' || $this->conf ['view'] == 'search_event') && ! empty( $categoryIdArray ))) {
      $uidCollector = $categoryService->getUidsOfEventsWithCategories();
      
      if (! empty( $uidCollector )) {
        $additionalWhere .= ' AND tx_cal_event.uid NOT IN (' . implode( ',', $uidCollector ) . ')';
      }
      // echo 'neue Runde';
      $eventsWithoutCategory = $this->getEventsFromTable( $categories, $includeRecurring, $additionalWhere, $serviceKey, false, $onlyMeetingsWithoutStatus, $eventType );
      // \TYPO3\CMS\Core\Utility\DebugUtility::debug($events);
      if (! empty( $eventsWithoutCategory )) {
        $this->mergeEvents( $events, $eventsWithoutCategory );
      }
    }
    return $events;
  }

  /**
   * Finds all events.
   *
   * @return array array of events represented by the model.
   */
  function findAll($pidList, $eventType = '0,1,2,3') {
    // How to get the events
    // 1st get Calendar specified
    // 2nd get categories specified
    // 3rd get all related events
    // make an array out of the list, so we can handle it better
    $start_date = new \TYPO3\CMS\Cal\Model\CalDate( '00000001000000' );
    if (isset( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['minDate'] )) {
      $start_date = $this->getTimeParsed( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['minDate'] );
    }
    $start_date->setTZbyId( 'UTC' );
    
    $end_date = $this->getTimeParsed( '+2 years' );
    if (isset( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxDate'] )) {
      $end_date = $this->getTimeParsed( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxDate'] );
    }
    $end_date->setTZbyId( 'UTC' );
    $this->setStartAndEndPoint( $start_date, $end_date );
    
    $this->endtime->setHour( 0 );
    $this->endtime->setMinute( 0 );
    
    $formattedStarttime = $this->starttime->format( '%Y%m%d' );
    $formattedEndtime = $this->endtime->format( '%Y%m%d' );
    
    $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    
    $calendarSearchString = $calendarService->getCalendarSearchString( $pidList, true, $this->conf ['calendar'] ? $this->conf ['calendar'] : '' );
    
    $recurringClause = ' OR (tx_cal_event.start_date<=' . $formattedEndtime . ' AND (tx_cal_event.freq IN ("day", "week", "month", "year") AND (tx_cal_event.until>=' . $formattedStarttime . ' OR tx_cal_event.until=0))) OR (tx_cal_event.rdate AND tx_cal_event.rdate_type IN ("date_time", "date", "period")) ';
    
    // putting everything together
    $additionalWhere = $calendarSearchString . ' AND tx_cal_event.pid IN (' . $pidList . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' ) . ' AND ((tx_cal_event.start_date>=' . $formattedStarttime . ' AND tx_cal_event.start_date<=' . $formattedEndtime . ') OR (tx_cal_event.end_date<=' . $formattedEndtime . ' AND tx_cal_event.end_date>=' . $formattedStarttime . ') OR (tx_cal_event.end_date>=' . $formattedEndtime . ' AND tx_cal_event.start_date<=' . $formattedStarttime . ')' . $recurringClause . ')' . $additionalWhere;
    
    // creating the arrays the user is allowed to see
    
    $categories = array ();
    
    $categoryService->getCategoryArray( $pidList, $categories );
    // creating events
    
    if ($this->conf ['view'] == 'ics' || $this->conf ['view'] == 'single_ics') {
      $includeRecurring = false;
    } else {
      $includeRecurring = true;
    }
    
    // creating events
    if ($pidList) {
      return $this->getEventsFromTable( $categories [0] [0], $includeRecurring, $additionalWhere, $this->getServiceKey(), true, false, $eventType );
    } else {
      return Array ();
    }
  }

  /**
   * Get the time parsed
   *
   * @param string $timeString          
   *
   * @return CalDate
   */
  protected function getTimeParsed($timeString) {

    /** @var DateParser $dp */
    $dp = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Controller\\DateParser' );
    $dp->parse( $timeString, 0, '' );
    return $dp->getDateObjectFromStack();
  }

  function createEvent($row, $isException) {

    return new \TYPO3\CMS\Cal\Model\EventModel( $row, $isException, $this->getServiceKey() );
  }

  /**
   * Finds a single event.
   *
   * @return object event represented by the model.
   */
  function find($uid, $pidList, $showHiddenEvents = false, $showDeletedEvents = false, $getAllInstances = false, $disableCalendarSearchString = false, $disableCategorySearchString = false, $eventType = '0,1,2,3') {

    $uid = intval( $uid );
    if ($getAllInstances) {
      $start_date = new \TYPO3\CMS\Cal\Model\CalDate( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['minDate'] . '000000' );
      $start_date->setTZbyId( 'UTC' );
      $end_date = new \TYPO3\CMS\Cal\Model\CalDate( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxDate'] . '000000' );
      $end_date->setTZbyId( 'UTC' );
      $this->setStartAndEndPoint( $start_date, $end_date );
      
      $this->endtime->setHour( 0 );
      $this->endtime->setMinute( 0 );
    } else {
      $this->starttime = new \TYPO3\CMS\Cal\Model\CalDate();
      if ($this->controller->getDateTimeObject) {
        $this->starttime->copy( $this->controller->getDateTimeObject );
      }
      $this->endtime = new \TYPO3\CMS\Cal\Model\CalDate();
      if ($this->controller->getDateTimeObject) {
        $this->endtime->copy( $this->controller->getDateTimeObject );
      }
      $this->endtime->addSeconds( 86400 );
    }
    
    $categories = array ();
    
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    $categoryService->getCategoryArray( $pidList, $categories );
    
    $calendarSearchString = '';
    if (! $disableCalendarSearchString) {
      $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
      $calendarSearchString = $calendarService->getCalendarSearchString( $pidList, true, $this->conf ['calendar'] ? $this->conf ['calendar'] : '' );
    }
    
    // putting everything together
    
    $additionalWhere = $calendarSearchString . ' AND tx_cal_event.uid=' . $uid;
    if (! $showHiddenEvents) {
      $additionalWhere .= ' AND tx_cal_event.hidden = 0';
    }
    if (! $showDeletedEvents) {
      $additionalWhere .= ' AND tx_cal_event.deleted = 0';
    }
    
    if ($this->conf ['view'] == 'ics' || $this->conf ['view'] == 'single_ics' || $this->conf ['view'] == 'create_event' || $this->conf ['view'] == 'edit_event' || $this->conf ['view'] == 'subscription') {
      $getAllInstances = false;
    }
    
    // In single event view we might have an instance of the recurring event
    if ($this->conf ['view'] == 'event') {
      $getAllInstances = true;
    }
    
    $events = $this->getEventsFromTable( $categories [0] [0], $getAllInstances, $additionalWhere, $this->getServiceKey(), ! $disableCategorySearchString, false, $eventType );
    
    // It is still the single view and we need to get the right instance and not all of them
    if ($this->conf ['view'] == 'event') {
      $getAllInstances = false;
    }
    
    if ($getAllInstances) {
      return $events;
    }
    
    if ($this->conf ['getdate']) {
      foreach ( $events as $date => $time ) {
        foreach ( $time as $eventArray ) {
          foreach ( $eventArray as $event ) {
            $eventStart = $event->getStart();
            $eventEnd = $event->getEnd();
            if ($eventStart->format( '%Y%m%d' ) <= strtotime( $this->conf ['getdate'] ) && $eventEnd->format( '%Y%m%d' ) >= strtotime( $this->conf ['getdate'] ) && $event->getUid() == $uid) {
              return $event;
            }
          }
        }
      }
    }
    if (empty( $events ))
      return;
    if ($this->conf ['getdate'] && isset($events [$this->conf ['getdate']])) {
      $event = array_pop( array_pop( $events [$this->conf ['getdate']] ) );
      return $event;
    } else {
      return array_pop( array_pop( array_pop( $events ) ) );
    }
  }

  function saveEvent($pid) {

    $object = $this->modelObj->createEvent( 'tx_cal_phpicalendar' );
    $object->updateWithPIVars( $this->controller->piVars );
    $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
    
    $crdate = time();
    $insertFields = Array ();
    $insertFields ['pid'] = $pid;
    $insertFields ['tstamp'] = $crdate;
    $insertFields ['crdate'] = $crdate;
    
    if ($languageAspect->getContentId() > 0 && $this->conf ['showRecordsWithoutDefaultTranslation'] == 1 && $this->rightsObj->isAllowedTo( 'create', 'translation' )) {
      $insertFields ['sys_language_uid'] = $languageAspect->getContentId();
    }
    
    // TODO: Check if all values are correct
    $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'event' );
    $this->filterDataToBeSaved( $insertFields, $object );
    
    if ((!isset($insertFields ['calendar_id']) || !$insertFields ['calendar_id']) && $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['calendar_id.'] ['default']) {
      $insertFields ['calendar_id'] = $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['calendar_id.'] ['default'];
    }
    
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    $insertFields ['fe_cruser_id'] = $this->rightsObj->getUserId();
    
    if (isset( $this->controller->piVars ['notify'] ) && is_array( $this->controller->piVars ['notify'] )) {
      $insertFields ['notify_ids'] = implode( ',', $this->controller->piVars ['notify'] );
    } else {
      $insertFields ['notify_ids'] = $this->controller->piVars ['notify_ids'] ?? '';
    }
    if (isset($this->controller->piVars ['exception_ids']) && is_array( $this->controller->piVars ['exception_ids'] )) {
      $insertFields ['exception_ids'] = implode( ',', $this->controller->piVars ['exception_ids'] );
    } else {
      $insertFields ['exception_ids'] = $this->controller->piVars ['exception_ids'] ?? '';
    }
    
    $uid = $this->_saveEvent( $insertFields, $object );
    $object->setUid( $uid );
    
    $this->conf ['category'] = $this->conf ['view.'] ['allowedCategories'];
    $this->conf ['calendar'] = $this->conf ['view.'] ['allowedCalendar'];
    
    $this->unsetPiVars();
    $insertFields ['uid'] = $uid;
    $insertFields ['category'] = $this->controller->piVars ['category_ids'] ?? '';
    $this->_notify( $insertFields );
    if ($object->getSendoutInvitation()) {
      $this->_invite( $object );
    }
    
    $this->_scheduleReminder( $uid );
    
    /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
    $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $GLOBALS ['TSFE']->id );
    $rgc->generateIndexForUid( $uid, 'tx_cal_event' );
    
    // Hook: saveEvent
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_event_service', 'eventServiceClass' );
    \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'saveEvent', $this, $object );
    
    \TYPO3\CMS\Cal\Utility\Functions::clearCache();
    return $this->find( $uid, $pid );
  }

  function _saveEvent(&$eventData, $object) {

    $tempValues = array ();
    $tempValues ['notify_ids'] = $eventData ['notify_ids'] ?? '';
    unset( $eventData ['notify_ids'] );
    $tempValues ['exception_ids'] = $eventData ['exception_ids'] ?? '';
    unset( $eventData ['exception_ids'] );
    $tempValues ['attendee_ids'] = $eventData ['attendee_ids'] ?? '';
    unset( $eventData ['attendee_ids'] );
    
    $eventData['l18n_diffsource'] = '';

    // Creating DB records
    $table = 'tx_cal_event';
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write event record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458130 );
    }
    
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    
    if ($this->rightsObj->isAllowedTo( 'create', 'event', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'image', $eventData, $uid );
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'event', 'attachment' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'attachment', $eventData, $uid );
    }
    
    // creating relation records
    if ($this->rightsObj->isAllowedToCreateEventNotify()) {
      if ($tempValues ['notify_ids'] != '') {
        $user = Array ();
        $group = Array ();
        $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['notify_ids'] ) ), $user, $group );
        foreach ( $user as $u ) {
          $userOffsetArray = GeneralUtility::trimExplode( '_', $u, 1 );
          $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
              
              $userOffsetArray [0]
          ), $uid, 'fe_users', array (
              
              'offset' => isset( $userOffsetArray [1] ) ? $userOffsetArray [1] : $this->conf ['view.'] ['event.'] ['remind.'] ['time'],
              'pid' => $eventData ['pid']
          ) );
        }
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToNotify.'] ['ignore'], 1 );
        foreach ( $group as $g ) {
          $groupOffsetArray = GeneralUtility::trimExplode( '_', $g, 1 );
          if (! in_array( $groupOffsetArray [0], $ignore )) {
            $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
                
                $groupOffsetArray [0]
            ), $uid, 'fe_groups', array (
                
                'offset' => isset( $groupOffsetArray [1] ) ? $groupOffsetArray [1] : $this->conf ['view.'] ['event.'] ['remind.'] ['time'],
                'pid' => $eventData ['pid']
            ) );
          }
        }
      }
    } else if ((isset($this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultUser']) && $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultUser'])
        || (isset($this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultGroup']) && $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultGroup'])) {
      $idArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultUser'], 1 );
      if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToNotify']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToNotify']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $idArray ), $uid, 'fe_users', array (
          
          'offset' => $this->conf ['view.'] ['event.'] ['remind.'] ['time'],
          'pid' => $eventData ['pid']
      ) );
      $idArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['notify.'] ['defaultGroup'], 1 );
      if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToNotify']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToNotify']) {
        $idArray = array_merge( $idArray, $this->rightsObj->getUserGroups() );
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $idArray ), $uid, 'fe_groups', array (
          
          'offset' => $this->conf ['view.'] ['event.'] ['remind.'] ['time'] ?? 0,
          'pid' => $eventData ['pid']
      ) );
        } else if ($this->rightsObj->isLoggedIn() && isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToNotify']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToNotify']) {
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
          
          $this->rightsObj->getUserId()
      ), $uid, 'fe_users', array (
          
          'offset' => $this->conf ['view.'] ['event.'] ['remind.'] ['time'] ?? 0,
          'pid' => $eventData ['pid']
      ) );
    }
    if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['public']) && $this->conf ['rights.'] ['create.'] ['event.'] ['public']) {
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['notifyUsersOnPublicCreate'] ?? '', 1 ), $uid, 'fe_users', array (
          
          'offset' => $this->conf ['view.'] ['event.'] ['remind.'] ['time'] ?? 0,
          'pid' => $eventData ['pid']
      ) );
    }
    if ($this->rightsObj->isAllowedToCreateEventException() && isset($tempValues ['exception_ids']) && $tempValues ['exception_ids'] != '') {
      $user = Array ();
      $group = Array ();
      $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['exception_ids'] ) ), $user, $group );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_exception_event_mm', $user, $uid, 'tx_cal_exception_event' );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_exception_event_group_mm', $group, $uid, 'tx_cal_exception_event_group' );
    }
    
    if ($this->rightsObj->isAllowedToCreateEventShared()) {
      $user = $object->getSharedUsers();
      $group = $object->getSharedGroups();
      if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToShared']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToShared']) {
        $user [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $user ), $uid, 'fe_users' );
      $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToShared.'] ['ignore'] ?? '', 1 );
      $groupArray = array_diff( $group, $ignore );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
    } else {
      $idArray = explode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['shared.'] ['defaultUser'] ?? '' );
      if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToShared']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeUserToShared']) {
        $idArray [] = $this->rightsObj->getUserId();
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $idArray ), $uid, 'fe_users' );
      
      $groupArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['shared.'] ['defaultGroup'] ?? '', 1 );
      if (isset($this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToShared']) && $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToShared']) {
        $idArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToShared.'] ['ignore'] ?? '', 1 );
        $groupArray = array_diff( $idArray, $ignore );
      }
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupArray ), $uid, 'fe_groups' );
    }
    
    $category_mm_relation_table = 'tx_cal_event_category_mm';
    $switchUidLocalForeign = false;
    if ($this->extConf ['categoryService'] == 'sys_category') {
      $category_mm_relation_table = 'sys_category_record_mm';
      $switchUidLocalForeign = true;
    }
    if ($this->rightsObj->isAllowedToCreateEventCategory()) {
      $categoryIds = Array ();
      foreach ( ( array ) $object->getCategories() as $category ) {
        if (is_object( $category )) {
          $categoryIds [] = $category->getUid();
        }
      }
      $this->insertIdsIntoTableWithMMRelation( $category_mm_relation_table, $categoryIds, $uid, '', Array (), $switchUidLocalForeign );
    } else {
      $this->insertIdsIntoTableWithMMRelation( $category_mm_relation_table, array (
          
          $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['category.'] ['default']
      ), $uid, '', Array (), $switchUidLocalForeign );
    }
    
    if ($this->rightsObj->isAllowedTo( 'create', 'event', 'attendee' ) && $object->getEventType() == \TYPO3\CMS\Cal\Model\Model::EVENT_TYPE_MEETING) {
      $attendeeUids = Array ();
      $modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'modelcontroller' );
      $attendeeService = $modelObj->getServiceObjByKey( 'cal_attendee_model', 'attendee', 'tx_cal_attendee' );
      foreach ( $object->getAttendees() as $serviceKey => $attendees ) {
        
        foreach ( $attendees as $attendee ) {
          $attendeeValues = Array (
              
              'pid' => $this->conf ['rights.'] ['create.'] ['attendee.'] ['saveAttendeeToPid'] ? $this->conf ['rights.'] ['create.'] ['attendee.'] ['saveAttendeeToPid'] : $insertFields ['pid'],
              'tstamp' => $insertFields ['tstamp'],
              'crdate' => $insertFields ['crdate']
          );
          $attendeeValues ['event_id'] = $uid;
          $attendeeValues ['fe_user_id'] = $attendee->getFeUserId();
          $attendeeValues ['email'] = $attendee->getEmail();
          $attendeeValues ['attendance'] = $attendee->getAttendance();
          $attendeeValues ['status'] = $attendee->getStatus();
          $attendeeValues ['cruser_id'] = $insertFields ['cruser_id'];
          $attendeeService->_saveAttendee( $attendeeValues );
          $attendeeUids [] = $GLOBALS ['TYPO3_DB']->sql_insert_id();
        }
      }
      $insertFields ['attendee'] = count( $attendeeUids );
    }
    return $uid;
  }

  function updateEvent($uid) {

    $insertFields = array (
        
        'tstamp' => time()
    );
    $tempCategoryConf = $this->conf ['category'];
    
    $event = $this->find( $uid, $this->conf ['pidList'], true, true, false, true, true, '0,1,2,3,4' );
    $event_old = $this->find( $uid, $this->conf ['pidList'], true, true, false, true, true, '0,1,2,3,4' );
    
    $uid = $this->checkUidForLanguageOverlay( $uid, 'tx_cal_event' );
    
    $this->conf ['category'] = $this->conf ['view.'] ['allowedCategories'];
    $this->conf ['calendar'] = $this->conf ['view.'] ['allowedCalendar'];
    
    $event->updateWithPIVars( $this->controller->piVars );
    
    if ($this->conf ['option'] == 'move') {
      if ($this->rightsObj->isAllowedToEditEventDateTime()) {
        if (is_object( $event->getStart() )) {
          $start = $event->getStart();
          $insertFields ['start_date'] = $start->format( '%Y%m%d' );
          $insertFields ['start_time'] = intval( $start->format( '%H' ) ) * 3600 + intval( $start->format( '%M' ) ) * 60;
        }
        if (is_object( $event->getEnd() )) {
          $end = $event->getEnd();
          $insertFields ['end_date'] = $end->format( '%Y%m%d' );
          $insertFields ['end_time'] = intval( $end->format( '%H' ) ) * 3600 + intval( $end->format( '%M' ) ) * 60;
        }
      }
      
      $table = 'tx_cal_event';
      $where = 'uid = ' . $uid;
      $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $insertFields );
    } else {
      $this->searchForAdditionalFieldsToAddFromPostData( $insertFields, 'event', false );
      
      $this->filterDataToBeUpdated( $insertFields, $event );
      
      if (isset( $this->controller->piVars ['notify_ids'] )) {
        $insertFields ['notify_ids'] = strip_tags( $this->controller->piVars ['notify_ids'] );
      } else if (isset($this->controller->piVars ['notify']) && is_array( $this->controller->piVars ['notify'] )) {
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
    }
    
    $this->_notifyOfChanges( $event_old, $insertFields );
    if ($event->getSendoutInvitation()) {
      $this->_invite( $event );
    }
    $this->unsetPiVars();
    
    /** @var \TYPO3\CMS\Cal\Utility\RecurrenceGenerator $rgc */
    $rgc = GeneralUtility::makeInstance( 'TYPO3\\CMS\\Cal\\Utility\\RecurrenceGenerator', $GLOBALS ['TSFE']->id );
    $rgc->generateIndexForUid( $uid, 'tx_cal_event' );
    
    // Hook: updateEvent
    $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_event_service', 'eventServiceClass' );
    \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'updateEvent', $this, $event );
    
    \TYPO3\CMS\Cal\Utility\Functions::clearCache();
    return $event;
  }

  function _updateEvent($uid, $eventData, $object) {

    $tempValues = array ();
    $tempValues ['notify_ids'] = $eventData ['notify_ids'] ?? '';
    $tempValues ['notify_offset'] = $eventData ['notify_offset'] ?? $this->conf ['view.'] ['event.'] ['remind.'] ['time'] ?? 0;
    unset( $eventData ['notify_ids'] );
    $tempValues ['exception_ids'] = $eventData ['exception_ids'] ?? '';
    unset( $eventData ['exception_ids'] );
    $tempValues ['attendee_ids'] = $eventData ['attendee_ids'] ?? '';
    unset( $eventData ['attendee_ids'] );
    
    // Creating DB records
    $table = 'tx_cal_event';
    $where = 'uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $eventData );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write event record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458130 );
    }
    $eventData ['pid'] = $object->row ['pid'];
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'image' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'image', $eventData, $uid );
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'attachment' )) {
      $this->checkOnNewOrDeletableFiles( 'tx_cal_event', 'attachment', $eventData, $uid );
    }
    
    $cal_user_ids = array ();
    $where = ' AND tx_cal_event.uid=' . $uid . ' AND tx_cal_fe_user_category_mm.tablenames="fe_users" ' . $this->pageRepository->enableFields( 'tx_cal_event' );
    $orderBy = '';
    $groupBy = '';
    $limit = '';
    
    if ($this->rightsObj->isAllowedToEditEventCategory()) {
      
      $where = 'uid_local = ' . $uid;
      $category_mm_relation_table = 'tx_cal_event_category_mm';
      $switchUidLocalForeign = false;
      if ($this->extConf ['categoryService'] == 'sys_category') {
        $category_mm_relation_table = 'sys_category_record_mm';
        $switchUidLocalForeign = true;
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( $category_mm_relation_table, $where );
      } else {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( $category_mm_relation_table, $where );
      }
      
      $categoryIds = Array ();
      foreach ( $object->getCategories() as $category ) {
        if (is_object( $category )) {
          $categoryIds [] = $category->getUid();
        }
      }
      $this->insertIdsIntoTableWithMMRelation( $category_mm_relation_table, $categoryIds, $uid, '', Array (), $switchUidLocalForeign );
    }
    
    if ($this->rightsObj->isAllowedToEditEventNotify() && ! is_null( $tempValues ['notify_ids'] )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_fe_user_event_monitor_mm', 'uid_local =' . $uid . ' AND tablenames in ("fe_users","fe_groups")' );
      if ($tempValues ['notify_ids'] != '') {
        $user = Array ();
        $group = Array ();
        $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['notify_ids'] ) ), $user, $group );
        foreach ( $user as $u ) {
          $userOffsetArray = GeneralUtility::trimExplode( '_', $u, 1 );
          $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
              
              $userOffsetArray [0]
          ), $uid, 'fe_users', array (
              
              'offset' => isset( $userOffsetArray [1] ) ? $userOffsetArray [1] : $this->conf ['view.'] ['event.'] ['remind.'] ['time'],
              'pid' => $eventData ['pid']
          ) );
        }
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['create.'] ['event.'] ['addFeGroupToNotify.'] ['ignore'], 1 );
        foreach ( $group as $g ) {
          $groupOffsetArray = GeneralUtility::trimExplode( '_', $g, 1 );
          if (! in_array( $groupOffsetArray [0], $ignore )) {
            $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array (
                
                $groupOffsetArray [0]
            ), $uid, 'fe_groups', array (
                
                'offset' => isset( $groupOffsetArray [1] ) ? $groupOffsetArray [1] : $this->conf ['view.'] ['event.'] ['remind.'] ['time'],
                'pid' => $eventData ['pid']
            ) );
          }
        }
      }
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['notify.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeUserToNotify']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['notify.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeGroupToNotify']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['addFeGroupToNotify.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_fe_user_event_monitor_mm', 'uid_local =' . $uid . ' AND tablenames in ("fe_users","fe_groups")' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $userIdArray ), $uid, 'fe_users', array (
            
            'offset' => $tempValues ['notify_offset'],
            'pid' => $object->row ['pid']
        ) );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_fe_user_event_monitor_mm', array_unique( $groupIdArray ), $uid, 'fe_groups', array (
            
            'offset' => $tempValues ['notify_offset'],
            'pid' => $object->row ['pid']
        ) );
      }
    }
    
    if ($this->rightsObj->isAllowedToEditEventException() && ! is_null( $tempValues ['exception_ids'] )) {
      if ($tempValues ['exception_ids'] != '') {
        $table = 'tx_cal_exception_event_mm';
        $where = 'uid_local = ' . $uid;
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( $table, $where );
        $user = Array ();
        $group = Array ();
        $this->splitUserAndGroupIds( explode( ',', strip_tags( $tempValues ['exception_ids'] ) ), $user, $group );
        $this->insertIdsIntoTableWithMMRelation( $table, $user, $uid, 'tx_cal_exception_event' );
        $this->insertIdsIntoTableWithMMRelation( $table, $group, $uid, 'tx_cal_exception_event_group' );
      }
    }
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'shared' )) {
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event_shared_user_mm', 'uid_local =' . $uid );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $object->getSharedUsers() ), $uid, 'fe_users' );
      $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $object->getSharedGroups() ), $uid, 'fe_groups' );
    } else {
      $userIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['shared.'] ['defaultUser'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeUserToShared']) {
        $userIdArray [] = $this->rightsObj->getUserId();
      }
      
      $groupIdArray = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['shared.'] ['defaultGroup'], 1 );
      if ($this->conf ['rights.'] ['edit.'] ['event.'] ['addFeGroupToShared']) {
        $groupIdArray = $this->rightsObj->getUserGroups();
        $ignore = GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['edit.'] ['event.'] ['addFeGroupToShared.'] ['ignore'], 1 );
        $groupIdArray = array_diff( $groupIdArray, $ignore );
      }
      if (! empty( $userIdArray ) || ! empty( $groupIdArray )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event_shared_user_mm', 'uid_local =' . $uid );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $userIdArray ), $uid, 'fe_users' );
        $this->insertIdsIntoTableWithMMRelation( 'tx_cal_event_shared_user_mm', array_unique( $groupIdArray ), $uid, 'fe_groups' );
      }
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'attendee' ) && $object->getEventType() == \TYPO3\CMS\Cal\Model\Model::EVENT_TYPE_MEETING) {
      
      $modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'modelcontroller' );
      $attendeeServices = $modelObj->findEventAttendees( $uid );
      
      $attendeeIndex = Array ();
      $attendeeServiceKeys = array_keys( $attendeeServices );
      $servKey = 'tx_cal_attendee';
      $oldAttendeeUids = Array (
          
          $servKey => Array ()
      );
      foreach ( $attendeeServiceKeys as $serviceKey ) {
        $attendeeKeys = array_keys( $attendeeServices [$serviceKey] );
        foreach ( $attendeeKeys as $attendeeKey ) {
          $attendeeIndex [$serviceKey . '_' . ($attendeeServices [$serviceKey] [$attendeeKey]->getFeUserId() ? $attendeeServices [$serviceKey] [$attendeeKey]->getFeUserId() : $attendeeServices [$serviceKey] [$attendeeKey]->getEmail())] = &$attendeeServices [$serviceKey] [$attendeeKey];
          $oldAttendeeUids [$serviceKey] [] = $attendeeServices [$serviceKey] [$attendeeKey]->getUid();
        }
      }
      
      $attendeeService = $modelObj->getServiceObjByKey( 'cal_attendee_model', 'attendee', $servKey );
      
      $attendeeUids = Array ();
      $attendees = &$object->getAttendees();
      
      foreach ( $attendees [$servKey] as $attendee ) {
        if (is_object( $attendeeIndex [$serviceKey . '_' . ($attendee->getFeUserId() ? $attendee->getFeUserId() : $attendee->getEmail())] )) {
          // Attendee is already assigned -> updating attendance
          $attendeeValues = Array ();
          $attendeeValues ['attendance'] = $attendee->getAttendance();
          $attendeeValues ['status'] = $attendee->getStatus();
          $attendeeValues ['event_id'] = $attendee->getEventUid();
          $attendeeService->_updateAttendee( $attendee->getUid(), $attendeeValues );
          $attendeeUids [] = $attendee->getUid();
        } else {
          // It's a new attendee -> creating new one
          $crdate = time();
          $attendeeValues = Array (
              
              'pid' => $this->conf ['rights.'] ['create.'] ['attendee.'] ['saveAttendeeToPid'] ? $this->conf ['rights.'] ['create.'] ['attendee.'] ['saveAttendeeToPid'] : $object->row ['pid'],
              'tstamp' => $crdate,
              'crdate' => $crdate
          );
          $attendeeValues ['event_id'] = $uid;
          $attendeeValues ['cruser_id'] = $this->rightsObj->getUserId();
          $attendeeValues ['fe_user_id'] = $attendee->getFeUserId();
          $attendeeValues ['email'] = $attendee->getEmail();
          $attendeeValues ['attendance'] = $attendee->getAttendance();
          $attendeeValues ['status'] = $attendee->getStatus();
          $attendeeService->_saveAttendee( $attendeeValues );
          $attendeeUids [] = $GLOBALS ['TYPO3_DB']->sql_insert_id();
        }
      }
      $uidsToBeDeleted = array_diff( $oldAttendeeUids [$servKey], $attendeeUids );
      if (! empty( $uidsToBeDeleted )) {
        $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_attendee', 'uid in (' . implode( ',', $uidsToBeDeleted ) . ')' );
      }
      $eventData ['attendee'] = count( $attendeeUids );
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
        throw new \RuntimeException( 'Could not delete event record from database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458133 );
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
      $hookObjectsArr = \TYPO3\CMS\Cal\Utility\Functions::getHookObjectsArray( 'tx_cal_event_service', 'eventServiceClass' );
      \TYPO3\CMS\Cal\Utility\Functions::executeHookObjectsFunction( $hookObjectsArr, 'removeEvent', $this, $event );
      \TYPO3\CMS\Cal\Utility\Functions::clearCache();
      $this->unsetPiVars();
    }
  }

  function filterDataToBeSaved(&$insertFields, &$object) {

    $hidden = 0;
    if (isset( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['hidden.'] ['default'] ) && ! $this->rightsObj->isAllowedToEditEventHidden() && ! $this->rightsObj->isAllowedToCreateEventHidden()) {
      $hidden = $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($object->isHidden() && $this->rightsObj->isAllowedToCreateEventHidden()) {
      $hidden = 1;
    }
    $insertFields ['hidden'] = $hidden;
    $insertFields ['type'] = $object->getEventType();
    if ($insertFields ['type'] == null) {
      $insertFields ['type'] = \TYPO3\CMS\Cal\Model\Model::EVENT_TYPE_DEFAULT;
    }
    
    $insertFields ['allday'] = $object->isAllday() ? '1' : '0';
    if (! $this->rightsObj->isAllowedTo( 'create', 'event', 'allday' )) {
      $insertFields ['allday'] = $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['allday.'] ['default'];
    }
    if ($this->rightsObj->isAllowedToCreateEventCalendar()) {
      if ($object->getCalendarUid() != '') {
        $insertFields ['calendar_id'] = $object->getCalendarUid();
      } else if ($this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['calendar.'] ['default']) {
        $insertFields ['calendar_id'] = $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['calendar_id.'] ['default'];
      } else {
        $insertFields ['calendar_id'] = ''; // TODO: Set the calendar_id to some value
      }
    }
    
    if ($this->rightsObj->isAllowedToCreateEventDateTime()) {
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
    if ($this->rightsObj->isAllowedToCreateEventTitle()) {
      $insertFields ['title'] = $object->getTitle();
    }
    
    if ($this->rightsObj->isAllowedToCreateEventOrganizer()) {
      $insertFields ['organizer'] = $object->getOrganizer();
    }
    if (!isset($insertFields ['organizer'])) {
      $insertFields ['organizer'] = '';
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'event', 'cal_organizer' )) {
      $insertFields ['organizer_id'] = $object->getOrganizerId();
    }
    if ($this->rightsObj->isAllowedToCreateEventLocation()) {
      $insertFields ['location'] = $object->getLocation();
    }
    if (!isset($insertFields['location'])) {
      $insertFields ['location'] = '';
    }
    if ($this->rightsObj->isAllowedTo( 'create', 'event', 'cal_location' )) {
      $insertFields ['location_id'] = $object->getLocationId();
    }
    if ($object->getTeaser() != '' && $this->rightsObj->isAllowedToCreateEventTeaser()) {
      $insertFields ['teaser'] = $object->getTeaser();
    }
    if ($object->getDescription() != '' && $this->rightsObj->isAllowedToCreateEventDescription()) {
      $insertFields ['description'] = $object->getDescription();
    }
    if ($this->rightsObj->isAllowedToCreateEventRecurring()) {
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
    
    $eventArray = array (
        
        'calendar_id' => $object->getCalendarUid(),
        'uid' => $object->getUid()
    );
    $insertFields ['icsUid'] = \TYPO3\CMS\Cal\Utility\Functions::getIcsUid( $this->conf, $eventArray );
    
    // Hook initialization:
    $hookObjectsArr = array ();
    if (isset($GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] ) && is_array( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] )) {
      foreach ( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] as $classRef ) {
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
    if (isset( $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['hidden.'] ['default'] ) && ! $this->rightsObj->isAllowedToEditEventHidden() && ! $this->rightsObj->isAllowedToCreateEventHidden()) {
      $hidden = $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['hidden.'] ['default'];
    } else if ($object->isHidden() && $this->rightsObj->isAllowedToEditEventHidden()) {
      $hidden = 1;
    }
    $insertFields ['hidden'] = $hidden;
    
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'type' )) {
      $insertFields ['type'] = $object->getEventType();
    }
    
    $insertFields ['allday'] = $object->isAllday() ? '1' : '0';
    if (! $this->rightsObj->isAllowedTo( 'edit', 'event', 'allday' )) {
      $insertFields ['allday'] = $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['allday.'] ['default'];
    }
    
    if ($this->rightsObj->isAllowedToEditEventCalendar()) {
      if ($object->getCalendarUid() != '') {
        $insertFields ['calendar_id'] = $object->getCalendarUid();
      } else if ($this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['calendar.'] ['default']) {
        $insertFields ['calendar_id'] = $this->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['calendar_id.'] ['default'];
      } else {
        $insertFields ['calendar_id'] = ''; // TODO: Set the calendar_id to some value
      }
    }
    
    if ($this->rightsObj->isAllowedToEditEventDateTime()) {
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
    if ($this->rightsObj->isAllowedToEditEventTitle()) {
      $insertFields ['title'] = $object->getTitle();
    }
    
    if ($this->rightsObj->isAllowedToEditEventOrganizer()) {
      $insertFields ['organizer'] = $object->getOrganizer();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'cal_organizer' )) {
      $insertFields ['organizer_id'] = $object->getOrganizerId();
    }
    if ($this->rightsObj->isAllowedToEditEventLocation()) {
      $insertFields ['location'] = $object->getLocation();
    }
    if ($this->rightsObj->isAllowedTo( 'edit', 'event', 'cal_location' )) {
      $insertFields ['location_id'] = $object->getLocationId();
    }
    if ($object->getTeaser() != '' && $this->rightsObj->isAllowedToEditEventTeaser()) {
      $insertFields ['teaser'] = $object->getTeaser();
    }
    if ($object->getDescription() != '' && $this->rightsObj->isAllowedToEditEventDescription()) {
      $insertFields ['description'] = $object->getDescription();
    }
    if ($this->rightsObj->isAllowedToEditEventRecurring()) {
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
    
    // Hook initialization:
    $hookObjectsArr = array ();
    if (isset( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] ) && is_array( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] )) {
      foreach ( $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/service/class.tx_cal_event_service.php'] ['addAdditionalField'] as $classRef ) {
        $hookObjectsArr [] = & GeneralUtility::getUserObj( $classRef );
      }
    }
    
    foreach ( $hookObjectsArr as $hookObj ) {
      if (method_exists( $hookObj, 'addAdditionalField' )) {
        $hookObj->addAdditionalField( $insertFields, $this );
      }
    }
  }

  function search($pidList = '', $start_date = '', $end_date = '', $searchword = '', $locationIds = '', $organizerIds = '', $eventType = '0,1,2,3') {

    $start_date->subtractSeconds( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['startPointCorrection'] );
    $end_date->addSeconds( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['endPointCorrection'] );
    
    $this->starttime = new \TYPO3\CMS\Cal\Model\CalDate();
    $this->endtime = new \TYPO3\CMS\Cal\Model\CalDate();
    
    $this->starttime->copy( $start_date );
    $this->endtime->copy( $end_date );
    
    $formattedStarttime = $this->starttime->format( '%Y%m%d' );
    $formattedEndtime = $this->endtime->format( '%Y%m%d' );
    
    $additionalSearch = '';
    if ($searchword != '') {
      $additionalSearch = $this->searchWhere( $searchword );
    }
    
    $linkIds = $this->conf ['calendar'] ? $this->conf ['calendar'] : '';
    // Lets see if we shall display the public calendar too
    /*
     * if(!$linkIds || in_array('public',explode(',',$linkIds))){ $includePublic = 1; }else{ $includePublic = 0; }
     */
    
    /**
     * @fixme Always include public events.
     * Do we really want to do this?
     * If so, find a prettier way than hardcoding it.
     */
    $includePublic = 1;
    
    $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    
    $calendarSearchString = $calendarService->getCalendarSearchString( $pidList, $includePublic, $linkIds, $this->conf ['view.'] ['calendar'] ? $this->conf ['view.'] ['calendar'] : '' );
    
    $timeSearchString = ' AND tx_cal_event.pid IN (' . $pidList . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' ) . ' AND (((tx_cal_event.start_date>=' . $formattedStarttime . ' AND tx_cal_event.start_date<=' . $formattedEndtime . ') OR (tx_cal_event.end_date<=' . $formattedEndtime . ' AND tx_cal_event.end_date>=' . $formattedStarttime . ') OR (tx_cal_event.end_date>=' . $formattedEndtime . ' AND tx_cal_event.start_date<=' . $formattedStarttime . ') OR (tx_cal_event.start_date<=' . $formattedEndtime . ' AND (tx_cal_event.freq IN ("day","week","month","year") AND (tx_cal_event.until>=' . $formattedStarttime . ' OR tx_cal_event.until=0)))) OR (tx_cal_event.rdate AND tx_cal_event.rdate_type IN ("date_time","date","period"))) ';
    
    if ($locationIds != '' && $locationIds != '0') {
      $locationSearchString = ' AND tx_cal_event.location_id in (' . $locationIds . ')';
    }
    
    if ($organizerIds != '' && $organizerIds != '0') {
      $organizerSearchString = ' AND tx_cal_event.organizer_id in (' . $organizerIds . ')';
    }
    
    // putting everything together
    $additionalWhere = $calendarSearchString . $timeSearchString . $locationSearchString . $organizerSearchString . $additionalSearch;
    $categories = Array ();
    $categoryService->getCategoryArray( $pidList, $categories );
    return $this->getEventsFromTable( $categories [0] [0], true, $additionalWhere, '', true, false, $eventType );
  }

  /**
   * Generates a search where clause.
   *
   * @param string $sw:          
   * @return string
   */
  function searchWhere($sw) {

    $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
    if (0 === strpos( $this->conf ['view'], 'search' ) && $languageAspect->getContentId() > 0) {
      return $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchEventFieldList'], 'tx_cal_event_l18n' );
    }
    return $this->cObj->searchWhere( $sw, $this->conf ['view.'] ['search.'] ['searchEventFieldList'], 'tx_cal_event' );
  }

  public function setDeviations(&$event) {

    $deviations = Array ();
    $select = '*';
    $table = 'tx_cal_event_deviation';
    // if (TYPO3_MODE === 'BE') {
    $where = 'parentid = ' . $event->getUid() . \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields( 'tx_cal_event_deviation' );
    // } else {
    // $where = 'parentid = ' . $event->getUid();// . $this->pageRepository->enableFields( 'tx_cal_event_deviation' );
    // }
    $deviationResult = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($deviationResult) {
      while ( $deviationRow = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $deviationResult ) ) {
        if ($deviationRow ['deleted']) {
          continue;
        }
        $origStartDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationRow ['orig_start_date'] );
        $origStartDate->addSeconds( $deviationRow ['orig_start_time'] );
        $deviations [$origStartDate->format( '%Y%m%d%H%M%S' )] = $deviationRow;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $deviationResult );
    }
    $event->setDeviationDates( $deviations );
  }

  /**
   * This function looks, if the event is a recurring event
   * and creates the recurrings events for a given time.
   * The starting and ending dates are calculated from the conf
   * array ('gedate' and 'view').
   *
   * @param $event object
   *          this class (tx_cal_model)
   */
  public function recurringEvent($event) {

    $this->setDeviations( $event );
    $eventStart = $event->getStart();
    $eventEnd = $event->getEnd();
    $this->filterFalseCombinations( $event );
    $this->checkRecurringSettings( $event );
    
    $master_array = Array ();
    $until = new \TYPO3\CMS\Cal\Model\CalDate();
    $until->copy( $event->getUntil() );
    $until->addSeconds( 1 );
    $rrule_array = $event->getRecurringRule();
    $count = intval( $event->getCount() );
    
    if ($this->endtime->before( $until )) {
      $until->copy( $this->endtime );
    }
    $byyear = Array ();
    $eventStart = new \TYPO3\CMS\Cal\Model\CalDate();
    $eventStart->copy( $event->getStart() );
    $i = $eventStart->getYear();
    if ($event->getFreq() == 'year') {
      $i = intval( $this->starttime->getYear() ) - (($this->starttime->getYear() - $eventStart->getYear()) % $event->getInterval());
    }
    
    for(; $i < intval( $until->getYear() ) + 1; $i ++) {
      $byyear [] = $i;
    }
    /* If starttime is before or at the same time as the event date, add the event */
    if ($this->starttime->compare( $this->starttime, $eventStart ) != 1 || $event->getFreq() == 'none') {
      if ($event->isAllday()) {
        $master_array [$eventStart->format( '%Y%m%d' )] ['-1'] [$event->getUid()] = $event;
      } else {
        $master_array [$eventStart->format( '%Y%m%d' )] [$eventStart->format( '%H%M' )] [$event->getUid()] = $event;
      }
    }
    
    // new feature for limiting f.e. the listed recurring events in listView
    $maxRecurringEvents = 0;
    if (TYPO3_MODE != 'BE' && isset($this->conf ['view']) && isset($this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxRecurringEvents'])) {
      $maxRecurringEvents = (int) $this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxRecurringEvents'];
    }
    $maxRecurringEvents = ! empty( $maxRecurringEvents ) ? $maxRecurringEvents : $count;
    
    $counter = 1;
    $total = 1;
    
    // if the 'parent' event is still in future, set $added to 1 (true), because we already have one instance of this event
    $added = ( int ) $eventStart->isFuture();
    $nextOccuranceTime = new \TYPO3\CMS\Cal\Model\CalDate();
    $nextOccuranceTime->copy( $event->getStart() );
    $nextOccuranceTime->addSeconds( 86400 );
    
    if ($event->getRdateType() && $event->getRdateType() != 'none') {
      $this->getRecurringDate( $master_array, $event, $added );
    }
    
    switch ($event->getFreq()) {
      case 'day' :
        $this->findDailyWithin( $master_array, $event, $nextOccuranceTime, $until, $event->getByDay(), $count, $counter, $total, $added, $maxRecurringEvents );
        break;
      case 'week' :
      case 'month' :
      case 'year' :
        $bymonth = $event->getByMonth();
        $byday = $event->getByDay();
        $hour = $eventStart->format( '%H' );
        $minute = $eventStart->format( '%M' );
        // 2007, 2008...
        
        foreach ( $byyear as $year ) {
          if ($counter < $count && $until->after( $nextOccuranceTime ) && $added < $maxRecurringEvents) {
            // 1,2,3,4,5,6,7,8,9,10,11,12
            foreach ( $bymonth as $month ) {
              if ($counter < $count && $until->after( $nextOccuranceTime ) && intval( str_pad( $year, 2, '0', STR_PAD_LEFT ) . str_pad( $month, 2, '0', STR_PAD_LEFT ) ) >= intval( $nextOccuranceTime->format( '%Y' ) . $nextOccuranceTime->format( '%m' ) ) && $added < $maxRecurringEvents) {
                $bymonthday = $this->getMonthDaysAccordingly( $event, $month, $year );
                // 1,2,3,4....31
                foreach ( $bymonthday as $day ) {
                  $nextOccuranceTime->setHour( $hour );
                  $nextOccuranceTime->setMinute( $minute );
                  $nextOccuranceTime->setSecond( 0 );
                  $nextOccuranceTime->setDay( $day );
                  $nextOccuranceTime->setMonth( $month );
                  $nextOccuranceTime->setYear( $year );
                  
                  if ($counter < $count && ($until->after( $nextOccuranceTime ) || $until->equals( $nextOccuranceTime )) && $added < $maxRecurringEvents) {
                    $currentUntil = new \TYPO3\CMS\Cal\Model\CalDate();
                    $currentUntil->copy( $nextOccuranceTime );
                    $currentUntil->addSeconds( 86399 );
                    if (intval( $nextOccuranceTime->getMonth() ) == $month && ($eventStart->before( $nextOccuranceTime )) || $eventStart->equals( $nextOccuranceTime )) {
                      $this->findDailyWithin( $master_array, $event, $nextOccuranceTime, $currentUntil, $byday, $count, $counter, $total, $added, $maxRecurringEvents );
                    } else {
                      continue;
                    }
                  } else {
                    return $master_array;
                  }
                }
              }
            }
          } else {
            return $master_array;
          }
        }
        break; // switch-case break
    }
    return $master_array;
  }

  function getRecurringEventsFromIndex($event, $ex_event_dates = Array()) {

    $maxRecurringEvents = intval($this->conf ['view.'] [$this->conf ['view'] . '.'] ['maxRecurringEvents'] ?? 0);
    $maxRecurringEvents = ! empty( $maxRecurringEvents ) ? $maxRecurringEvents : 99999;
    
    $master_array = Array ();
    $startDate = $event->getStart();
    $dontShowOldEvents = ( integer ) $this->conf ['view.'] [$this->conf ['view'] . '.'] ['dontShowOldEvents'];
    if ($dontShowOldEvents > 0) {
      $now = new \TYPO3\CMS\Cal\Model\CalDate();
      if ($dontShowOldEvents == 2) {
        $now->setHour( 0 );
        $now->setMinute( 0 );
        $now->setSecond( 0 );
      }
      
      if ($startDate->getTime() > $now->getTime() && ! $ex_event_dates [$startDate->format( '%Y%m%d' )]) {
        $master_array [$startDate->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($startDate->format( '%H%M' ))] [$event->getUid()] = &$event;
      }
    } else if (! isset($ex_event_dates [$startDate->format( '%Y%m%d' )]) && (! $event->getStart()->after( $this->endtime )) && (! $event->getEnd()->before( $this->starttime ))) {
      $master_array [$startDate->format( '%Y%m%d' )] [$event->isAllday() ? '-1' : ($startDate->format( '%H%M' ))] [$event->getUid()] = &$event;
    }
    
    $added = 0;
    // if the 'parent' event is still in future, set $added to 1, because we already have one instance of this event
    $now = new \TYPO3\CMS\Cal\Model\CalDate();
    if (intval( $now->format( '%Y%m%d%H%M%S' ) ) < intval( $event->getStart()->format( '%Y%m%d%H%M%S' ) )) {
      $added = 1;
    }
    $select = '*';
    $table = 'tx_cal_index';
    $where = 'event_uid = ' . $event->getUid() . ' AND start_datetime >= ' . $this->starttime->format( '%Y%m%d%H%M%S' ) . ' AND start_datetime <= ' . $this->endtime->format( '%Y%m%d%H%M%S' ) . ' AND tablename = "' . ($event->getType() == 'tx_cal_phpicalendar' ? ($event->isException ? 'tx_cal_exception_event' : 'tx_cal_event') : $event->getType()) . '"';
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, '', 'start_datetime' );
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        if ($added < $maxRecurringEvents) {
          $nextOccuranceTime = new \TYPO3\CMS\Cal\Model\CalDate( $row ['start_datetime'] );
          $nextOccuranceEndTime = new \TYPO3\CMS\Cal\Model\CalDate( $row ['end_datetime'] );
          $new_event = null;
          if ($row ['event_deviation_uid'] > 0) {
            $result2 = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( '*', 'tx_cal_event_deviation', 'uid=' . $row ['event_deviation_uid'] . $this->pageRepository->enableFields( 'tx_cal_event_deviation' ) );
            if ($result2) {
              while ( $row2 = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result2 ) ) {
                $new_event = new \TYPO3\CMS\Cal\Model\EventRecDeviationModel( $event, $row2, $nextOccuranceTime, $nextOccuranceEndTime );
              }
            }
          } else {
            $new_event = new \TYPO3\CMS\Cal\Model\EventRecModel( $event, $nextOccuranceTime, $nextOccuranceEndTime );
          }
          if (! isset($ex_event_dates [$new_event->getStart()->format( '%Y%m%d' )])) {
            if ($new_event->isAllday()) {
              $master_array [$nextOccuranceTime->format( '%Y%m%d' )] ['-1'] [$event->getUid()] = $new_event;
            } else {
              $master_array [$nextOccuranceTime->format( '%Y%m%d' )] [$nextOccuranceTime->format( '%H%M' )] [$event->getUid()] = $new_event;
            }
            $added ++;
          }
        }
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    return $master_array;
  }

  function getRecurringDate(&$master_array, &$event, &$addedCount) {

    switch ($event->getRdateType()) {
      case 'date' :
        foreach ( $event->getRdateValues() as $rdateValue ) {
          preg_match( '/(^[0-9]{4})([0-9]{2})([0-9]{2})/', $rdateValue, $dateArray );
          $new_event = $event->cloneEvent();
          $start = &$new_event->getStart();
          $end = &$new_event->getEnd();
          $diff = $end->getTime() - $start->getTime();
          $start->setDay( $dateArray [3] );
          $start->setMonth( $dateArray [2] );
          $start->setYear( $dateArray [1] );
          $new_event->setStart( $start );
          $new_event->setEnd( $start );
          $end = $new_event->getEnd();
          $end->addSeconds( $diff );
          $new_event->setEnd( $end );
          if ($end->after( $this->starttime ) && $start->before( $this->endtime )) {
            $table = 'tx_cal_index';
            $eventData = Array (
                
                'start_datetime' => $start->format( '%Y%m%d' ) . $start->format( '%H%M%S' ),
                'end_datetime' => $end->format( '%Y%m%d' ) . $end->format( '%H%M%S' ),
                'event_uid' => $event->getUid(),
                'tablename' => $event->isException ? 'tx_cal_exception_event' : 'tx_cal_event'
            );
            $this->updateEventDataWithDeviations( $event, $eventData );
            $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
            if (FALSE === $result) {
              throw new \RuntimeException( 'Could not write event index record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458131 );
            }
            $addedCount ++;
          }
        }
        break;
      case 'period' :
        foreach ( $event->getRdateValues() as $rdateValue ) {
          preg_match( '/([0-9]{4})(-?([0-9]{2})((-?[0-9]{2})(T([0-9]{2}):?([0-9]{2})(:?([0-9]{2})(\.([0-9]+))?)?(Z|(([-+])([0-9]{2}):([0-9]{2})))?)?)?)?/', $rdateValue, $dateArray );
          preg_match( '/\/P((\d+)Y)?((\d+)M)?((\d+)W)?((\d+)D)?T((\d+)H)?((\d+)M)?((\d+)S)?/', $rdateValue, $durationArray );
          $new_event = $event->cloneEvent();
          $start = &$new_event->getStart();
          $end = &$new_event->getStart();
          $diff = 0;
          $start->setDay( $dateArray [5] );
          $start->setMonth( $dateArray [3] );
          $start->setYear( $dateArray [1] );
          $start->setHour( $dateArray [7] );
          $start->setMinute( $dateArray [8] );
          $start->setSecond( $dateArray [10] );
          $new_event->setStart( $start );
          $new_event->setEnd( $start );
          $end = $new_event->getEnd();
          if ($durationArray [2]) {
            // Year
            $end->setYear( $end->getYear() + intval( $durationArray [2] ) );
          }
          if ($durationArray [4]) {
            // Month
            $end->setMonth( $end->getMonth() + intval( $durationArray [4] ) );
          }
          if ($durationArray [6]) {
            // Week
            $diff += intval( $durationArray [6] ) * 60 * 60 * 24 * 7;
          }
          if ($durationArray [8]) {
            // Day
            $diff += intval( $durationArray [8] ) * 60 * 60 * 24;
          }
          if ($durationArray [10]) {
            // Hour
            $diff += intval( $durationArray [10] ) * 60 * 60;
          }
          if ($durationArray [12]) {
            // Minute
            $diff += intval( $durationArray [12] ) * 60;
          }
          if ($durationArray [14]) {
            // Second
            $diff += intval( $durationArray [14] );
          }
          
          $end->addSeconds( $diff );
          $new_event->setEnd( $end );
          
          if ($end->after( $this->starttime ) && $start->before( $this->endtime )) {
            $table = 'tx_cal_index';
            $eventData = Array (
                
                'start_datetime' => $start->format( '%Y%m%d' ) . $start->format( '%H%M%S' ),
                'end_datetime' => $end->format( '%Y%m%d' ) . $end->format( '%H%M%S' ),
                'event_uid' => $event->getUid(),
                'tablename' => $event->isException ? 'tx_cal_exception_event' : 'tx_cal_event'
            );
            $this->updateEventDataWithDeviations( $event, $eventData );
            $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
            if (FALSE === $result) {
              throw new \RuntimeException( 'Could not write event index record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458132 );
            }
            $addedCount ++;
          }
        }
        break;
      default :
        foreach ( $event->getRdateValues() as $rdateValue ) {
          preg_match( '/([0-9]{4})(-?([0-9]{2})((-?[0-9]{2})(T([0-9]{2}):?([0-9]{2})(:?([0-9]{2})(\.([0-9]+))?)?(Z|(([-+])([0-9]{2}):([0-9]{2})))?)?)?)?/', $rdateValue, $dateArray );
          $new_event = $event->cloneEvent();
          $start = &$new_event->getStart();
          $end = &$new_event->getEnd();
          $diff = $end->getTime() - $start->getTime();
          $start->setDay( $dateArray [5] );
          $start->setMonth( $dateArray [3] );
          $start->setYear( $dateArray [1] );
          $start->setHour( $dateArray [7] );
          $start->setMinute( $dateArray [8] );
          $start->setSecond( $dateArray [10] );
          $new_event->setStart( $start );
          $new_event->setEnd( $start );
          $end = $new_event->getEnd();
          $end->addSeconds( $diff );
          $new_event->setEnd( $end );
          if ($end->after( $this->starttime ) && $start->before( $this->endtime )) {
            $table = 'tx_cal_index';
            $eventData = Array (
                
                'start_datetime' => $start->format( '%Y%m%d%H%M%S' ),
                'end_datetime' => $end->format( '%Y%m%d%H%M%S' ),
                'event_uid' => $event->getUid(),
                'tablename' => $event->isException ? 'tx_cal_exception_event' : 'tx_cal_event'
            );
            $this->updateEventDataWithDeviations( $event, $eventData );
            $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
            if (FALSE === $result) {
              throw new \RuntimeException( 'Could not write event index record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458133 );
            }
            $addedCount ++;
          }
        }
        break;
    }
  }

  /**
   * This function merges an array of events with another array of events.
   * The structure is: [date][time][event]
   *
   * @param $events array
   *          the events should be added into
   * @param $events_tmp array
   *          is supposed to be merged
   */
  function mergeEvents(&$events, &$events_tmp) {

    $dates = array_keys( $events_tmp );
    foreach ( $dates as $event_date ) {
      $eventsThatDay = &$events_tmp [$event_date];
      if (array_key_exists( $event_date, $events )) {
        $times = array_keys( $eventsThatDay );
        foreach ( $times as $event_time ) {
          $eventsThatTime = &$eventsThatDay [$event_time];
          $eventIDs = array_keys( $eventsThatTime );
          foreach ( $eventIDs as $key ) {
            $events [$event_date] [$event_time] [$key] = &$eventsThatTime [$key];
          }
          /*
           * if(array_key_exists($event_time,$events[$event_date])){ $events[$event_date][$event_time] = array_merge($events[$event_date][$event_time],$eventsThatTime); } else { $events[$event_date][$event_time] = $eventsThatTime; }
           */
        }
      } else {
        $events [$event_date] = &$eventsThatDay;
      }
    }
  }

  /**
   * This function removes an array of events from another array of events.
   * The structure is: [date][time][event]
   *
   * @param $events array
   *          the events should be deleted from
   * @param $events_tmp array
   *          is supposed to be deleted
   */
  function removeEvents(&$events_tmp, &$ex_events) {

    foreach ( $events_tmp as $event_tmp_key => $event_tmp ) {
      if (array_key_exists( $event_tmp_key, $ex_events ) == 1) {
        array_splice( $events_tmp [$event_tmp_key], 0 );
      }
    }
  }

  /**
   * This function returns an array of weekdays (english)
   */
  function getDaysOfWeekShort() {

    return array (
        
        'Sun',
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat'
    );
  }

  function saveExceptionEvent($pid) {

    $crdate = time();
    $insertFields = array (
        
        'pid' => $pid,
        'tstamp' => $crdate,
        'crdate' => $crdate
    );
    // TODO: Check if all values are correct
    
    if ($this->controller->piVars ['exception_start_day'] != '') {
      $insertFields ['start_date'] = strip_tags( $this->controller->piVars ['exception_start_day'] );
    } else {
      return;
    }
    if ($this->controller->piVars ['exception_end_day'] != '') {
      $insertFields ['end_date'] = strip_tags( $this->controller->piVars ['exception_end_day'] );
    }
    
    if ($this->controller->piVars ['exception_title'] != '') {
      $insertFields ['title'] = strip_tags( $this->controller->piVars ['exception_title'] );
    }
    
    // Creating DB records
    $insertFields ['cruser_id'] = $this->rightsObj->getUserId();
    $insertFields ['fe_cruser_id'] = $this->rightsObj->getUserId();
    if ($insertFields ['title'] == '') {
      $insertFields ['title'] = strip_tags( $this->controller->piVars ['exception_start_day'] ) . ' exception';
    }
    $table = 'tx_cal_exception_event';
    
    $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $insertFields );
    if (FALSE === $result) {
      throw new \RuntimeException( 'Could not write exception event record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458134 );
    }
    $uid = $GLOBALS ['TYPO3_DB']->sql_insert_id();
    
    $this->insertIdsIntoTableWithMMRelation( 'tx_cal_exception_event_mm', array (
        
        $uid
    ), intval( $this->controller->piVars ['event_uid'] ), 'tx_cal_exception_event' );
    $this->unsetPiVars();
    \TYPO3\CMS\Cal\Utility\Functions::clearCache();
  }

  function unsetPiVars() {

    unset( $this->controller->piVars ['hidden'] );
    unset( $this->controller->piVars ['_TRANSFORM_description'] );
    unset( $this->controller->piVars ['uid'] );
    unset( $this->controller->piVars ['calendar_id'] );
    unset( $this->controller->piVars ['calendar'] );
    unset( $this->controller->piVars ['switch_calendar'] );
    unset( $this->controller->piVars ['type'] );
    unset( $this->controller->piVars ['allday'] );
    unset( $this->controller->piVars ['startdate'] );
    unset( $this->controller->piVars ['starttime'] );
    unset( $this->controller->piVars ['startminutes'] );
    unset( $this->controller->piVars ['enddate'] );
    unset( $this->controller->piVars ['endtime'] );
    unset( $this->controller->piVars ['endminutes'] );
    unset( $this->controller->piVars ['gettime'] );
    unset( $this->controller->piVars ['title'] );
    unset( $this->controller->piVars ['organizer'] );
    unset( $this->controller->piVars ['organizer_id'] );
    unset( $this->controller->piVars ['location'] );
    unset( $this->controller->piVars ['location_id'] );
    unset( $this->controller->piVars ['description'] );
    unset( $this->controller->piVars ['frequency_id'] );
    unset( $this->controller->piVars ['by_day'] );
    unset( $this->controller->piVars ['by_monthday'] );
    unset( $this->controller->piVars ['by_month'] );
    unset( $this->controller->piVars ['until'] );
    unset( $this->controller->piVars ['count'] );
    unset( $this->controller->piVars ['interval'] );
    unset( $this->controller->piVars ['category'] );
    unset( $this->controller->piVars ['category_ids'] );
    unset( $this->controller->piVars ['category_display_ids'] );
    unset( $this->controller->piVars ['user_ids'] );
    unset( $this->controller->piVars ['group_ids'] );
    unset( $this->controller->piVars ['single_exception_ids'] );
    unset( $this->controller->piVars ['group_exception_ids'] );
    unset( $this->controller->piVars ['gettime'] );
    unset( $this->controller->piVars ['notify'] );
    unset( $this->controller->piVars ['notify_ids'] );
    unset( $this->controller->piVars ['teaser'] );
    unset( $this->controller->piVars ['image'] );
    unset( $this->controller->piVars ['image_caption'] );
    unset( $this->controller->piVars ['image_title'] );
    unset( $this->controller->piVars ['image_alt'] );
    unset( $this->controller->piVars ['image_old'] );
    unset( $this->controller->piVars ['remove_image'] );
    unset( $this->controller->piVars ['cal_location'] );
    unset( $this->controller->piVars ['cal_organizer'] );
    unset( $this->controller->piVars ['attachment'] );
    unset( $this->controller->piVars ['attachment_caption'] );
  }

  function checkRecurringSettings(&$event) {

    $this->checkFrequency( $event );
    if ($event->getFreq() == 'none') {
      return;
    }
    $this->checkInterval( $event );
    $this->checkByMonth( $event );
    $this->checkByWeekno( $event );
    $this->checkByYearday( $event );
    $this->checkByMonthday( $event );
    $this->checkByDay( $event );
    $this->checkByHour( $event );
    $this->checkByMinute( $event );
    $this->checkBySecond( $event );
    $this->checkBySetpos( $event );
    $this->checkCount( $event );
    $this->checkUntil( $event );
    $this->checkWkst( $event );
  }

  function filterFalseCombinations(&$event) {

    switch ($event->getFreq()) {
      case '' :
      case 'none' :
        break;
      case 'day' :
        $event->setByMonth( '' );
        $event->setByWeekNo( '' );
        $event->setByYearDay( '' );
        $event->setByMonthDay( '' );
        $event->setByDay( '' );
        break;
      case 'week' :
        $event->setByMonth( '' );
        $event->setByWeekNo( '' );
        $event->setByYearDay( '' );
        $event->setByMonthDay( '' );
        break;
      case 'month' :
        $event->setByMonth( '' );
        $event->setByWeekNo( '' );
        $event->setByYearDay( '' );
        break;
      case 'year' :
        if (count( $event->getByMonth() ) > 0) {
          $event->setByWeekNo( '' );
          $event->setByYearDay( '' );
        } else if (count( $event->getByWeekNo() ) > 0) {
          $event->setByYearDay( '' );
        } else if (count( $event->getByYearDay() ) > 0) {
          $event->setByMonthDay( '' );
        } else if (count( $event->getByMonthDay() ) > 0) {
          $event->setByDay( '' );
        }
        break;
    }
  }

  function checkFrequency(&$event) {

    $allowedValues = array (
        
        'second',
        'minute',
        'hour',
        'day',
        'week',
        'month',
        'year'
    );
    if (! in_array( $event->getFreq(), $allowedValues )) {
      $event->setFreq( 'none' );
    }
  }

  function checkInterval(&$event) {

    if (! $event->getInterval() || $event->getInterval() < 1) {
      $event->setInterval( 1 );
    }
  }

  function checkCount(&$event) {

    if (! $event->getCount() || $event->getCount() < 1) {
      $event->setCount( 9999999 );
    }
  }

  function checkUntil(&$event) {

    if (! $event->row ['until']) {
      $event->setUntil( $this->endtime );
    }
  }

  function checkBySecond(&$event) {

    if (intval( $event->getBySecond() ) < 0 || intval( $event->getBySecond() ) > 59) {
      $eventStart = $event->getStart();
      $event->setBySecond( $eventStart->getSecond() );
    }
  }

  function checkByMinute(&$event) {

    if (intval( $event->getByMinute() ) < 0 || intval( $event->getByMinute() ) > 59) {
      $eventStart = $event->getStart();
      $event->setByMinute( $eventStart->getMinute() );
    }
  }

  function checkByHour(&$event) {

    if (intval( $event->getByHour() ) < 0 || intval( $event->getByHour() ) > 23) {
      $eventStart = $event->getStart();
      $event->setByHour( $eventStart->getHour() );
    }
  }

  function checkByDay(&$event) {

    $byday_arr = array ();
    $allowedValues = array ();
    $allowedWeekdayValues = array (
        
        'SU',
        'MO',
        'TU',
        'WE',
        'TH',
        'FR',
        'SA'
    );
    // example: -2TU -> 2nd last Tuesday
    // +1TU -> 1st Tuesday
    // WE,FR -> Wednesday and Friday
    $byDayArray = $event->getByDay();
    if ($event->getFreq() == 'day') {
      $event->setByDay( 'all' );
      return;
    }
    for($i = 0; $i < count( $byDayArray ); $i ++) {
      $byDayArray [$i] = strtoupper( $byDayArray [$i] );
      if (preg_match( '/([-\+]{0,1})?([0-9]{1})?([A-Z]{2})/', $byDayArray [$i], $byDaySplit )) {
        if (! in_array( $byDaySplit [3], $allowedWeekdayValues )) {
          continue;
        } else if (! ($byDaySplit [2] > 0 && ($event->getFreq() == 'month' || $event->getFreq() == 'year'))) {
          // n-th values are not allowed for monthly and yearly
          unset( $byDaySplit [1] );
          unset( $byDaySplit [2] );
        }
        unset( $byDaySplit [0] );
        $allowedValues [] = implode( '', $byDaySplit );
      } else {
        // the current byday setting is not valid
      }
    }
    if (count( $allowedValues ) == 0) {
      if ($event->getFreq() == 'week') {
        $eventStart = $event->getStart();
        $allowedValues = array (
            
            $allowedWeekdayValues [$eventStart->getDayOfWeek()]
        );
      } else {
        $allowedValues = array (
            
            'all'
        );
      }
    }
    $event->setByDay( implode( ',', $allowedValues ) );
  }

  function checkByMonth(&$event) {

    $byMonth = $event->getByMonth();
    if (! is_array( $byMonth ) || count( $byMonth ) == 0) {
      if ($event->getFreq() == 'year') {
        $eventStart = $event->getStart();
        $event->setByMonth( $eventStart->getMonth() );
      } else {
        $event->setByMonth( 'all' );
      }
      return;
    }
    $allowedValues = array ();
    foreach ( $byMonth as $month ) {
      if ($month > 0 && $month < 13) {
        $allowedValues [] = $month;
      }
    }
    sort( array_unique( $allowedValues ) );
    $event->setByMonth( implode( ',', $allowedValues ) );
  }

  function checkByMonthday(&$event) {

    /* If there's not a monthday set, pick a default value */
    if (count( $event->getByMonthDay() ) == 0) {
      
      /**
       * If there's no day of the week either, assume that we only want
       * to recur on the event start day.
       * If there is a day of the
       * week, assume that we want to recur anytime that day of the week
       * occurs.
       */
      if (count( $event->getByDay() ) == 0 && $event->getFreq() != 'week') {
        $eventStart = $event->getStart();
        $event->setByMonthDay( $eventStart->getDay() );
      } else {
        $event->setByMonthDay( 'all' );
      }
    } else {
      $event->setByMonthDay( implode( ',', array_filter( $event->getByMonthDay(), function ($value) {
        $value = intval( $value );
        if ($value < - 12 || $value > 12 || $value == 0) {
          return false;
        }
        return true;
      } ) ) );
    }
  }

  function checkByYearday(&$event) {

    if (count( $event->getByYearDay() ) == 0) {
      // nothing
    } else {
      $event->setByYearDay( implode( ',', array_filter( $event->getByYearDay(), function ($value) {
        $value = intval( $value );
        if ($value < - 31 || $value > 31 || $value == 0) {
          return false;
        }
        return true;
      } ) ) );
    }
  }

  function checkByWeekno(&$event) {

    if ($event->getFreq() == 'yearly') {
      $event->setByWeekNo( implode( ',', array_filter( $event->getByWeekNo(), function ($value) {
        $value = intval( $value );
        if ($value < - 53 || $value > 53 || $value == 0) {
          return false;
        }
        return true;
      } ) ) );
    } else {
      $event->setByWeekNo( '' );
    }
  }

  function checkWkst(&$event) {

    $allowedWeekdayValues = array (
        
        'MO',
        'TU',
        'WE',
        'TH',
        'FR',
        'SA',
        'SU'
    );
    $wkst = strtoupper( $event->getWkst() );
    if (! in_array( $wkst, $allowedWeekdayValues )) {
      $wkst = '';
    }
    $event->setWkst( $wkst );
  }

  function checkBySetpos(&$event) {

    $event->setBySetpos( intval( $event->getBySetpos() ) );
  }

  function findDailyWithin(&$master_array, $event, $startRange, $endRange, $weekdays, $maxCount, &$currentCount, &$totalCount, &$addedCount, &$maxRecurringEvents) {

    $nextOccuranceTime = $startRange;
    while ( $currentCount < $maxCount && ($nextOccuranceTime->before( $endRange ) || $nextOccuranceTime->equals( $endRange )) && $addedCount < $maxRecurringEvents ) {
      if (! $nextOccuranceTime->equals( $event->getStart() )) {
        if (($totalCount % $event->getInterval()) == 0) {
          $nextOccuranceEndTime = new \TYPO3\CMS\Cal\Model\CalDate();
          $nextOccuranceEndTime->copy( $nextOccuranceTime );
          $nextOccuranceEndTime->addSeconds( $event->getLengthInSeconds() );
          if ($this->starttime->before( $nextOccuranceEndTime ) || $this->starttime->equals( $nextOccuranceTime )) {
            $table = 'tx_cal_index';
            $eventData = Array (
                
                'start_datetime' => $nextOccuranceTime->format( '%Y%m%d' ) . $nextOccuranceTime->format( '%H%M%S' ),
                'end_datetime' => $nextOccuranceEndTime->format( '%Y%m%d' ) . $nextOccuranceEndTime->format( '%H%M%S' ),
                'event_uid' => $event->getUid(),
                'tablename' => $event->getType() == 'tx_cal_phpicalendar' ? ($event->isException ? 'tx_cal_exception_event' : 'tx_cal_event') : $event->getType()
            );
            $this->updateEventDataWithDeviations( $event, $eventData );
            $result = $GLOBALS ['TYPO3_DB']->exec_INSERTquery( $table, $eventData );
            if (FALSE === $result) {
              throw new \RuntimeException( 'Could not write event index record to database: ' . $GLOBALS ['TYPO3_DB']->sql_error(), 1431458135 );
            }
            $addedCount ++;
          }
          $currentCount ++;
        }
        $totalCount ++;
      }
      $nextOccuranceTime->addSeconds( 86400 );
    }
  }

  private function updateEventDataWithDeviations($event, &$eventData) {

    $deviationDates = $event->getDeviationDates();
    if (array_key_exists( $eventData ['start_datetime'], $deviationDates )) {
      $startDate = null;
      if ($deviationDates [$eventData ['start_datetime']] ['start_date']) {
        $startDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationDates [$eventData ['start_datetime']] ['start_date'] );
      } else {
        $startDate = new \TYPO3\CMS\Cal\Model\CalDate();
        $startDate->copy( $nextOccuranceTime );
      }
      $endDate = null;
      if ($deviationDates [$eventData ['start_datetime']] ['end_date']) {
        $endDate = new \TYPO3\CMS\Cal\Model\CalDate( $deviationDates [$eventData ['start_datetime']] ['end_date'] );
      } else {
        $endDate = new \TYPO3\CMS\Cal\Model\CalDate();
        $endDate->copy( $nextOccuranceEndTime );
      }
      
      if (! $deviationDates [$eventData ['start_datetime']] ['allday']) {
        $startDate->addSeconds( $deviationDates [$eventData ['start_datetime']] ['start_time'] );
        $endDate->addSeconds( $deviationDates [$eventData ['start_datetime']] ['end_time'] );
      }
      
      $eventData ['event_deviation_uid'] = $deviationDates [$eventData ['start_datetime']] ['uid'];
      $eventData ['start_datetime'] = $startDate->format( '%Y%m%d' ) . $startDate->format( '%H%M%S' );
      $eventData ['end_datetime'] = $endDate->format( '%Y%m%d' ) . $endDate->format( '%H%M%S' );
    }
  }

  function getMonthDaysAccordingly(&$event, $month, $year) {

    $byDayArray = $event->getByDay();
    $byMonthDays = $event->getByMonthDay();
    $resultDays = array ();
    if (count( $byDayArray ) == 0) {
      $resultDays = array (
          
          1,
          2,
          3,
          4,
          5,
          6,
          7,
          8,
          9,
          10,
          11,
          12,
          13,
          14,
          15,
          16,
          17,
          18,
          19,
          20,
          21,
          22,
          23,
          24,
          25,
          26,
          27,
          28,
          29,
          30,
          31
      );
      return $resultDays;
    }
    for($i = 0; $i < count( $byDayArray ); $i ++) {
      if (preg_match( '/([-\+]{0,1})?([0-9]{1})?([A-Z]{2})/', $byDayArray [$i], $byDaySplit )) {
        $dayOfWeekday = \TYPO3\CMS\Cal\Controller\Calendar::two2threeCharDays( $byDaySplit [3], false );
        $monthStartTime = new \TYPO3\CMS\Cal\Model\CalDate( $year . '-' . sprintf( "%02d", $month ) . '-01 00:00:00' );
        $monthStartTime->setTZbyId( 'UTC' );
        $monthEndTime = \TYPO3\CMS\Cal\Controller\Calendar::calculateEndMonthTime( $monthStartTime );
        if ($byDaySplit [2] > 0) {
          if ($byDaySplit [1] == '-') {
            $monthTime = new \TYPO3\CMS\Cal\Model\CalDate( Calc::prevDayOfWeek( $dayOfWeekday, $monthEndTime->getDay(), $monthEndTime->getMonth(), $monthEndTime->getYear(), '%Y%m%d', true ) );
            $monthTime->setTZbyId( 'UTC' );
            $monthTime->subtractSeconds( ($byDaySplit [2] - 1) * 604800 );
          } else {
            $monthTime = new \TYPO3\CMS\Cal\Model\CalDate( Calc::nextDayOfWeek( $dayOfWeekday, $monthStartTime->getDay(), $monthStartTime->getMonth(), $monthStartTime->getYear(), '%Y%m%d', true ) );
            $monthTime->setTZbyId( 'UTC' );
            $monthTime->addSeconds( ($byDaySplit [2] - 1) * 604800 );
          }
          if (($monthTime->getMonth() == $month) && in_array( $monthTime->getDay(), $byMonthDays )) {
            $resultDays [] = $monthTime->getDay();
          }
        } else {
          $monthTime = new \TYPO3\CMS\Cal\Model\CalDate( Calc::prevDayOfWeek( $dayOfWeekday, $monthStartTime->getDay(), $monthStartTime->getMonth(), $monthStartTime->getYear(), '%Y%m%d', true ) );
          $monthTime->setTZbyId( 'UTC' );
          if ($monthTime->before( $monthStartTime )) {
            $monthTime->addSeconds( 604800 );
          }
          while ( $monthTime->before( $monthEndTime ) ) {
            $resultDays [] = $monthTime->getDay();
            $monthTime->addSeconds( 604800 );
          }
        }
      }
    }
    
    $resultDays = array_intersect( $resultDays, $event->getByMonthDay() );
    sort( $resultDays );
    
    return $resultDays;
  }

  function createTranslation($uid, $overlay) {

    $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
    $languageFlag = $languageAspect->getContentId();
    // resetting the language to find the default translation!
    $languageAspect->setContentId(0);
    $event = $this->find( $uid, $this->conf ['pidList'] );
    $languageAspect->setContentId($languageFlag);
    $table = 'tx_cal_event';
    $select = $table . '.*';
    $where = $table . '.uid = ' . $uid;
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        unset( $row ['uid'] );
        $crdate = time();
        $row ['tstamp'] = $crdate;
        $row ['crdate'] = $crdate;
        $row ['l18n_parent'] = $uid;
        $row ['sys_language_uid'] = $overlay;
        $this->_saveEvent( $row, $event );
        return;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
  }

  function setStartAndEndPoint(&$start_date, &$end_date) {

    $start_date->subtractSeconds( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['startPointCorrection'] ?? 0 );
    $end_date->addSeconds( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['endPointCorrection'] ?? 0 );
    
    $this->starttime = new \TYPO3\CMS\Cal\Model\CalDate();
    $this->endtime = new \TYPO3\CMS\Cal\Model\CalDate();
    
    $this->starttime->copy( $start_date );
    $this->endtime->copy( $end_date );
    
    if ($this->endtime->equals( $this->starttime )) {
      $this->endtime->addSeconds( 86400 );
    }
  }

  function findMeetingEventsWithEmptyStatus($pidList) {

    if ($this->rightsObj->isLoggedIn()) {
      
      $start_date = new \TYPO3\CMS\Cal\Model\CalDate();
      $start_date->setTZById( 'UTC' );
      $end_date = new \TYPO3\CMS\Cal\Model\CalDate();
      $end_date->copy( $start_date );
      $end_date->addSeconds( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['event.'] ['meeting.'] ['lookingAhead'] );
      $this->setStartAndEndPoint( $start_date, $end_date );
      
      $formattedStarttime = $this->starttime->format( '%Y%m%d' );
      $formattedEndtime = $this->endtime->format( '%Y%m%d' );
      
      $calendarService = &$this->modelObj->getServiceObjByKey( 'cal_calendar_model', 'calendar', 'tx_cal_calendar' );
      $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
      $calendarSearchString = $calendarService->getCalendarSearchString( $pidList, true, $this->conf ['calendar'] ? $this->conf ['calendar'] : '' );
      
      // putting everything together
      $additionalWhere = $calendarSearchString . ' AND tx_cal_event.pid IN (' . $pidList . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' ) . ' AND ((tx_cal_event.start_date>=' . $formattedStarttime . ' AND tx_cal_event.start_date<=' . $formattedEndtime . ') OR (tx_cal_event.end_date<=' . $formattedEndtime . ' AND tx_cal_event.end_date>=' . $formattedStarttime . ') OR (tx_cal_event.end_date>=' . $formattedEndtime . ' AND tx_cal_event.start_date<=' . $formattedStarttime . ') OR (tx_cal_event.start_date<=' . $formattedEndtime . ' AND (tx_cal_event.freq IN ("day","week","month","year") AND tx_cal_event.until>=' . $formattedStarttime . ')))';
      $additionalWhere .= ' AND tx_cal_attendee.status IN ("0","NEEDS-ACTION") AND tx_cal_attendee.attendance <> "CHAIR" AND tx_cal_event.type = 3 AND tx_cal_attendee.fe_user_id = ' . $this->rightsObj->getUserId();
      
      // creating the arrays the user is allowed to see
      $categories = array ();
      
      $categoryService->getCategoryArray( $pidList, $categories );
      $includeRecurring = true;
      if ($this->conf ['view'] == 'ics' || $this->conf ['view'] == 'single_ics') {
        $includeRecurring = false;
      }
      // creating events
      return $this->getEventsFromTable( $categories [0] [0], $includeRecurring, $additionalWhere, $this->getServiceKey(), true, true, '3' );
    }
  }

  function updateAttendees($eventUid) {

    $select = 'tx_cal_event.*';
    $table = 'tx_cal_event';
    $where = 'uid=' . $eventUid;
    $eventRow = Array ();
    $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy );
    if ($result) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
        $eventRow = $row;
        break;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
    }
    $eventObject = $this->createEvent( $eventRow, false );
    
    $modelObj = &tx_cal_registry::Registry( 'basic', 'modelcontroller' );
    $attendeeRecordsArray = $modelObj->findEventAttendees( $eventObject->getUid() );
    
    if (! empty( $attendeeRecordsArray ['tx_cal_attendee'] )) {
      $attendeeRecords = $attendeeRecordsArray ['tx_cal_attendee'];
      // update related event record in attendee calendar
      
      $updatedCalendar = Array (
          
          0
      );
      // attendees have changed, we need to go through every one of them :(
      foreach ( $attendeeRecords as $attendee ) {
        // Check if attendee is a fe-user and has a private calendar defined
        $select = 'tx_cal_calendar.uid, tx_cal_calendar.pid, tx_cal_event.uid AS event_id';
        $table = 'fe_users, tx_cal_calendar, tx_cal_event';
        $where = 'fe_users.uid = ' . $attendee->getFeUserId() . ' AND tx_cal_calendar.uid NOT IN (' . $eventObject->getCalendarUid() . ') AND fe_users.tx_cal_calendar=tx_cal_calendar.uid AND tx_cal_calendar.uid = tx_cal_event.calendar_id AND tx_cal_event.ref_event_id = ' . $eventObject->getUid() . ' AND fe_users.disable=0 AND fe_users.deleted=0 AND tx_cal_calendar.hidden=0 AND tx_cal_calendar.deleted=0';
        $groupBy = 'tx_cal_calendar.uid';
        $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy );
        if ($result) {
          while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
            // found private calendar of attendee
            $eventService = & \TYPO3\CMS\Cal\Utility\Functions::getEventService();
            $eventData = $eventRow;
            $uid = $eventData ['uid'];
            $this->cleanEventData( $eventData );
            $eventData ['pid'] = $row ['pid'];
            $crdate = time();
            $eventData ['crdate'] = $crdate;
            $eventData ['tstamp'] = $crdate;
            $eventData ['calendar_id'] = $row ['uid'];
            $eventData ['ref_event_id'] = $eventObject->getUid();
            $eventData ['attendee_ids'] = implode( ',', array_keys( $attendeeRecords ) );
            $eventService->conf ['rights.'] ['edit.'] ['event.'] ['fields.'] ['attendee.'] ['public'] = 1;
            $eventService->_updateEvent( $row ['event_id'], $eventData, $eventObject );
            $updatedCalendar [] = $row ['uid'];
          }
          $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
        }
      }
      $updatedCalendar [] = $eventObject->getCalendarUid();
      foreach ( $attendeeRecords as $attendee ) {
        // Check if attendee is a fe-user and has a private calendar defined
        $select = 'tx_cal_calendar.uid, tx_cal_calendar.pid';
        $table = 'fe_users, tx_cal_calendar';
        $where = 'tx_cal_calendar.uid NOT IN (' . implode( ',', $updatedCalendar ) . ') AND fe_users.uid = ' . $attendee->getFeUserId() . ' AND fe_users.tx_cal_calendar=tx_cal_calendar.uid AND fe_users.disable=0 AND fe_users.deleted=0 AND tx_cal_calendar.hidden=0 AND tx_cal_calendar.deleted=0';
        $groupBy = 'tx_cal_calendar.uid';
        $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where, $groupBy );
        if ($result) {
          while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
            // found private calendar of attendee
            $eventService = & \TYPO3\CMS\Cal\Utility\Functions::getEventService();
            $eventData = $eventRow;
            $this->cleanEventData( $eventData );
            $eventData ['pid'] = $row ['pid'];
            $crdate = time();
            $eventData ['crdate'] = $crdate;
            $eventData ['tstamp'] = $crdate;
            $eventData ['calendar_id'] = $row ['uid'];
            $eventData ['ref_event_id'] = $eventObject->getUid();
            $eventData ['attendee_ids'] = implode( ',', array_keys( $attendeeRecords ) );
            $eventService->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['attendee.'] ['public'] = 1;
            $eventService->_saveEvent( $eventData, $eventObject );
          }
          $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
        }
      }
    } else {
      // Lets delete events with a ref_event_id to this event, b/c there are no attendees anymore
      
      // But first we have to find the events
      $select = 'tx_cal_event.uid';
      $table = 'tx_cal_event';
      $where = 'ref_event_id=' . $eventObject->getUid();
      $rememberUids = Array (
          
          0
      );
      $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
      if ($result) {
        while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
          $rememberUids [] = $row ['uid'];
        }
        $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
      }
      // Now lets delete the mm relations to the attendees
      $where = 'uid IN (' . implode( ',', $rememberUids ) . ')';
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_attendee', $where );
      
      // Now delete the events
      $where = 'ref_event_id=' . $eventObject->getUid();
      $GLOBALS ['TYPO3_DB']->exec_DELETEquery( 'tx_cal_event', $where );
    }
  }

  function cleanEventData(&$eventData) {

    unset( $eventData ['uid'] );
    unset( $eventData ['deleted'] );
    unset( $eventData ['hidden'] );
    unset( $eventData ['categories'] );
    unset( $eventData ['category_id'] );
    unset( $eventData ['category_string'] );
    unset( $eventData ['category_headerstyle'] );
    unset( $eventData ['category_bodystyle'] );
    unset( $eventData ['exception_single_ids'] );
    unset( $eventData ['exceptionGroupIds'] );
    unset( $eventData ['event_owner'] );
  }

  function findAllWithAdditionalWhere($where = '') {

    $categoryService = &$this->modelObj->getServiceObjByKey( 'cal_category_model', 'category', $this->extConf ['categoryService'] );
    // putting everything together
    //
    // Franz: added simple check/include for rdate events at the end of this where clause.
    // But we need to find a way to only include rdate events within the searched timerange
    // - otherwise we'll flood the results after some time. I think we need a mm-table for that!
    $additionalWhere = $where . ' AND tx_cal_event.pid IN (' . $this->conf ['pidList'] . ') ' . $this->pageRepository->enableFields( 'tx_cal_event' );
    $additionalWhere .= ' AND tx_cal_calendar.nearby = 0';
    // creating the arrays the user is allowed to see
    $categories = array ();
    
    $categoryService->getCategoryArray( $this->conf ['pidList'], $categories );
    // creating events
    return $this->getEventsFromTable( $categories [0] [0], false, $additionalWhere, $this->getServiceKey(), true, false, '' );
  }
}
?>
