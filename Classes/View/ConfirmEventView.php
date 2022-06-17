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
use TYPO3\CMS\Cal\Utility\Functions;

/**
 * A service which renders a form to confirm the phpicalendar event create/edit.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class ConfirmEventView extends \TYPO3\CMS\Cal\View\FeEditingBaseView {

  var $confArr = array ();

  public function __construct() {

    parent::__construct();
  }

  /**
   * Draws a confirm event form.
   *
   * @param
   *          object The cObject of the mother-class
   * @param
   *          object The rights object.
   * @return string HTML output.
   */
  function drawConfirmEvent() {

    $this->objectString = 'event';
    $this->isConfirm = true;
    unset( $this->controller->piVars ['formCheck'] );
    
    /* @fixme Temporarily reverted to using piVars rather than conf */
    // unset($this->controller->piVars['category']);
    $page = Functions::getContent( $this->conf ['view.'] ['confirm_event.'] ['template'] );
    if ($page == '') {
      return '<h3>calendar: no confirm event template file found:</h3>' . $this->conf ['view.'] ['confirm_event.'] ['template'];
    }
    $this->lastPiVars = $this->controller->piVars;
    
    $this->object = $this->modelObj->createEvent( 'tx_cal_phpicalendar' );
    $this->object->updateWithPIVars( $this->controller->piVars );
    
    $lastViewParams = $this->controller->shortenLastViewAndGetTargetViewParameters();
    
    if ($lastViewParams ['view'] == 'edit_event') {
      $this->isEditMode = true;
    }
    
    $rems = array ();
    $sims = array ();
    $wrapped = array ();
    $sims ['###UID###'] = $this->conf ['uid'];
    $sims ['###TYPE###'] = $this->conf ['type'];
    $sims ['###LASTVIEW###'] = $lastViewParams ['lastview'];
    $sims ['###OPTION###'] = $this->conf ['option'];
    // $sims['###CALENDAR_ID###'] = intval($this->controller->piVars['calendar_id']);
    $sims ['###L_CONFIRM_EVENT###'] = $this->controller->pi_getLL( 'l_confirm_event' );
    $sims ['###L_SAVE###'] = $this->controller->pi_getLL( 'l_save' );
    $sims ['###L_CANCEL###'] = $this->controller->pi_getLL( 'l_cancel' );
    $this->controller->pi_linkTP( '|', array (
        
        'tx_cal_controller[view]' => 'save_event',
        'tx_cal_controller[category]' => null,
        'tx_cal_controller[getdate]' => $this->conf ['getdate']
    ) );
    $sims ['###ACTION_URL###'] = htmlspecialchars( $this->cObj->lastTypoLinkUrl );
    
    $this->getTemplateSubpartMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, array (), $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, array (), array () );
    $sims = array ();
    $rems = array ();
    $wrapped = array ();
    $this->getTemplateSingleMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, array (), $rems, $wrapped );
    ;
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, array (), array () );
    return \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, array (), array () );
  }

  function getTitleMarker(& $template, & $sims, & $rems) {

    $sims ['###TITLE###'] = '';
    if ($this->isAllowed( 'title' )) {
      $sims ['###TITLE###'] = $this->applyStdWrap( $this->object->getTitle(), 'title_stdWrap' );
      $sims ['###TITLE_VALUE###'] = htmlspecialchars( $this->object->getTitle() );
    }
  }

  function getCalendarIdMarker(& $template, & $sims, & $rems) {

    $sims ['###CALENDAR_ID###'] = '';
    if ($this->isAllowed( 'calendar_id' )) {
      $calendar = $this->object->getCalendarObject();
      if (is_object( $calendar )) {
        $sims ['###CALENDAR_ID###'] = $this->applyStdWrap( $calendar->getTitle(), 'calendar_id_stdWrap' );
        $sims ['###CALENDAR_ID_VALUE###'] = htmlspecialchars( $calendar->getUID() );
      }
    }
  }

  function getEventTypeMarker(& $template, & $sims, & $rems) {

    $sims ['###EVENT_TYPE###'] = '';
    if ($this->isAllowed( 'event_type' )) {
      $sims ['###EVENT_TYPE###'] = $this->applyStdWrap( $this->controller->pi_getLL( 'l_event_type_' . $this->object->getEventType() ), 'event_type_stdWrap' );
      $sims ['###EVENT_TYPE_VALUE###'] = intval( $this->object->getEventType() );
    }
  }

  function getCategoryMarker(& $template, & $sims, & $rems) {

    $sims ['###CATEGORY###'] = '';
    $categoryArray = $this->object->getCategories();
    if ($this->isAllowed( 'category' )) {
      if (! empty( $categoryArray )) {
        $temp = $this->templateService->getSubpart( $template, '###FORM_CATEGORY###' );
        $catIds = explode( ',', $piVarCategory );
        $ids = array ();
        $names = array ();
        foreach ( $categoryArray as $category ) {
          if (is_object( $category )) {
            $ids [] = $category->getUid();
            $names [] = $category->getTitle();
          }
        }
        $sims ['###CATEGORY###'] = $this->applyStdWrap( implode( ', ', $names ), 'category_stdWrap' );
        $sims ['###CATEGORY_VALUE###'] = htmlspecialchars( implode( ',', $ids ) );
      } else {
        $sims ['###CATEGORY###'] = $this->applyStdWrap( '', 'category_stdWrap' );
        $sims ['###CATEGORY_VALUE###'] = '-1';
      }
    }
  }

  function getAlldayMarker(& $template, & $sims, & $rems) {

    $sims ['###ALLDAY###'] = '';
    if ($this->isAllowed( 'allday' )) {
      $allday = false;
      $label = $this->controller->pi_getLL( 'l_false' );
      if ($this->object->isAllDay() == '1') {
        $allday = 1;
        $label = $this->controller->pi_getLL( 'l_true' );
      }
      $sims ['###ALLDAY###'] = $this->applyStdWrap( $label, 'allday_stdWrap' );
      $sims ['###ALLDAY_VALUE###'] = htmlspecialchars( $allday ? 1 : 0 );
    }
  }

  function getStartdateMarker(& $template, & $sims, & $rems) {

    $sims ['###STARTDATE###'] = '';
    if ($this->isAllowed( 'startdate' )) {
      $startDate = $this->object->getStart();
      $split = $this->conf ['dateConfig.'] ['splitSymbol'];
      $startDateFormatted = $startDate->format( \TYPO3\CMS\Cal\Utility\Functions::getFormatStringFromConf( $this->conf ) );
      $dateFormatArray = explode( $this->conf ['dateConfig.'] ['splitSymbol'], $startDateFormatted );
      $sims ['###STARTDATE###'] = $this->applyStdWrap( $startDateFormatted, 'startdate_stdWrap' );
      $sims ['###STARTDATE_VALUE###'] = htmlspecialchars( $dateFormatArray [$this->conf ['dateConfig.'] ['yearPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['monthPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['dayPosition']] );
    }
  }

  function getEnddateMarker(& $template, & $sims, & $rems) {

    $sims ['###ENDDATE###'] = '';
    if ($this->isAllowed( 'enddate' )) {
      $endDate = $this->object->getEnd();
      $split = $this->conf ['dateConfig.'] ['splitSymbol'];
      $endDateFormatted = $endDate->format( \TYPO3\CMS\Cal\Utility\Functions::getFormatStringFromConf( $this->conf ) );
      $dateFormatArray = explode( $this->conf ['dateConfig.'] ['splitSymbol'], $endDateFormatted );
      $sims ['###ENDDATE###'] = $this->applyStdWrap( $endDateFormatted, 'enddate_stdWrap' );
      $sims ['###ENDDATE_VALUE###'] = htmlspecialchars( $dateFormatArray [$this->conf ['dateConfig.'] ['yearPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['monthPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['dayPosition']] );
    }
  }

  function getStarttimeMarker(& $template, & $sims, & $rems) {

    $sims ['###STARTTIME###'] = '';
    if ($this->isAllowed( 'starttime' )) {
      $startDate = $this->object->getStart();
      $sims ['###STARTTIME###'] = $this->applyStdWrap( $startDate->format( $this->conf ['view.'] ['event.'] ['event.'] ['timeFormat'] ), 'starttime_stdWrap' );
      $sims ['###STARTTIME_VALUE###'] = htmlspecialchars( $startDate->format( "%H%M" ) );
    }
  }

  function getEndtimeMarker(& $template, & $sims, & $rems) {

    $sims ['###ENDTIME###'] = '';
    if ($this->isAllowed( 'endtime' )) {
      $endDate = $this->object->getEnd();
      $sims ['###ENDTIME###'] = $this->applyStdWrap( $endDate->format( $this->conf ['view.'] ['event.'] ['event.'] ['timeFormat'] ), 'endtime_stdWrap' );
      $sims ['###ENDTIME_VALUE###'] = htmlspecialchars( $endDate->format( "%H%M" ) );
    }
  }

  function getOrganizerMarker(& $template, & $sims, & $rems) {

    $sims ['###ORGANIZER###'] = '';
    if (! $this->extConf ['hideOrganizerTextfield'] && $this->isAllowed( 'organizer' )) {
      $sims ['###ORGANIZER###'] = $this->applyStdWrap( $this->object->getOrganizer(), 'organizer_stdWrap' );
      $sims ['###ORGANIZER_VALUE###'] = htmlspecialchars( $this->object->getOrganizer() );
    }
  }

  function getCalOrganizerMarker(& $template, & $sims, & $rems) {

    $sims ['###CAL_ORGANIZER###'] = '';
    if ($this->isAllowed( 'cal_organizer' )) {
      if ($organizer = $this->object->getOrganizerObject()) {
        $this->initLocalCObject( $organizer->getValuesAsArray() );
        $this->local_cObj->setCurrentVal( $organizer->getName() );
        $value = $this->local_cObj->cObjGetSingle( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['organizerDisplayField'], $this->conf ['view.'] [$this->conf ['view'] . '.'] ['organizerDisplayField.'] );
        $sims ['###CAL_ORGANIZER###'] = $this->applyStdWrap( $value, 'cal_organizer_stdWrap' );
        $sims ['###CAL_ORGANIZER_VALUE###'] = htmlspecialchars( $organizer->getUid() );
      } else {
        $this->initLocalCObject();
        $this->local_cObj->setCurrentVal( '' );
        $value = $this->local_cObj->cObjGetSingle( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['organizerDisplayField'], $this->conf ['view.'] [$this->conf ['view'] . '.'] ['organizerDisplayField.'] );
        $sims ['###CAL_ORGANIZER###'] = $this->applyStdWrap( $value, 'cal_organizer_stdWrap' );
        $sims ['###CAL_ORGANIZER_VALUE###'] = 0;
      }
    }
  }

  function getLocationMarker(& $template, & $sims, & $rems) {

    $sims ['###LOCATION###'] = '';
    if (! $this->extConf ['hideLocationTextfield'] && $this->isAllowed( 'location' )) {
      $sims ['###LOCATION###'] = $this->applyStdWrap( $this->object->getLocation(), 'location_stdWrap' );
      $sims ['###LOCATION_VALUE###'] = htmlspecialchars( $this->object->getLocation() );
    }
  }

  function getCalLocationMarker(& $template, & $sims, & $rems) {

    $sims ['###CAL_LOCATION###'] = '';
    if ($this->isAllowed( 'cal_location' )) {
      if ($location = $this->object->getLocationObject()) {
        $this->initLocalCObject( $location->getValuesAsArray() );
        $this->local_cObj->setCurrentVal( $location->getName() );
        $value = $this->local_cObj->cObjGetSingle( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['locationDisplayField'], $this->conf ['view.'] [$this->conf ['view'] . '.'] ['locationDisplayField.'] );
        $sims ['###CAL_LOCATION###'] = $this->applyStdWrap( $value, 'cal_location_stdWrap' );
        $sims ['###CAL_LOCATION_VALUE###'] = htmlspecialchars( $location->getUid() );
      } else {
        $this->initLocalCObject();
        $this->local_cObj->setCurrentVal( '' );
        $value = $this->local_cObj->cObjGetSingle( $this->conf ['view.'] [$this->conf ['view'] . '.'] ['locationDisplayField'], $this->conf ['view.'] [$this->conf ['view'] . '.'] ['locationDisplayField.'] );
        $sims ['###CAL_LOCATION###'] = $this->applyStdWrap( $value, 'cal_location_stdWrap' );
        $sims ['###CAL_LOCATION_VALUE###'] = 0;
      }
    }
  }

  function getDescriptionMarker(& $template, & $sims, & $rems) {

    $sims ['###DESCRIPTION###'] = '';
    if ($this->isAllowed( 'description' )) {
      $sims ['###DESCRIPTION###'] = $this->applyStdWrap( $this->object->getDescription(), 'description_stdWrap' );
      $sims ['###DESCRIPTION_VALUE###'] = htmlspecialchars( $this->object->getDescription() );
    }
  }

  function getTeaserMarker(& $template, & $sims, & $rems) {

    $sims ['###TEASER###'] = '';
    
    $confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    if ($confArr ['useTeaser'] && $this->isAllowed( 'teaser' )) {
      $sims ['###TEASER###'] = $this->applyStdWrap( $this->object->getTeaser(), 'teaser_stdWrap' );
      $sims ['###TEASER_VALUE###'] = htmlspecialchars( $this->object->getTeaser() );
    }
  }

  function getFrequencyMarker(& $template, & $sims, & $rems) {

    $sims ['###FREQUENCY###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $sims ['###FREQUENCY###'] = $this->applyStdWrap( $this->controller->pi_getLL( 'l_' . $this->object->getFreq() ), 'frequency_stdWrap' );
      $sims ['###FREQUENCY_VALUE###'] = htmlspecialchars( $this->object->getFreq() );
    }
  }

  function getByDayMarker(& $template, & $sims, & $rems) {

    $sims ['###BY_DAY###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $byDayString = implode( ',', $this->object->getByDay() );
      $sims ['###BY_DAY###'] = $this->applyStdWrap( $byDayString, 'byDay_stdWrap' );
      $sims ['###BY_DAY_VALUE###'] = htmlspecialchars( $byDayString );
    }
  }

  function getByMonthDayMarker(& $template, & $sims, & $rems) {

    $sims ['###BY_MONTHDAY###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $byMonthDayString = implode( ',', $this->object->getByMonthDay() );
      $sims ['###BY_MONTHDAY###'] = $this->applyStdWrap( $byMonthDayString, 'byMonthday_stdWrap' );
      $sims ['###BY_MONTHDAY_VALUE###'] = htmlspecialchars( $byMonthDayString );
    }
  }

  function getByMonthMarker(& $template, & $sims, & $rems) {

    $sims ['###BY_MONTH###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $byMonthString = implode( ',', $this->object->getByMonth() );
      $sims ['###BY_MONTH###'] = $this->applyStdWrap( $byMonthString, 'byMonth_stdWrap' );
      $sims ['###BY_MONTH_VALUE###'] = htmlspecialchars( $byMonthString );
    }
  }

  function getUntilMarker(& $template, & $sims, & $rems) {

    $sims ['###UNTIL###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $untilDate = $this->object->getUntil();
      if (is_object( $untilDate )) {
        $untilDateFormatted = '';
        $sims ['###UNTIL_VALUE###'] = '';
        if ($untilDate->getYear() > 0) {
          $split = $this->conf ['dateConfig.'] ['splitSymbol'];
          $untilDateFormatted = $untilDate->format( \TYPO3\CMS\Cal\Utility\Functions::getFormatStringFromConf( $this->conf ) );
          $dateFormatArray = explode( $this->conf ['dateConfig.'] ['splitSymbol'], $untilDateFormatted );
          $sims ['###UNTIL_VALUE###'] = htmlspecialchars( $dateFormatArray [$this->conf ['dateConfig.'] ['yearPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['monthPosition']] . $dateFormatArray [$this->conf ['dateConfig.'] ['dayPosition']] );
        }
        $sims ['###UNTIL###'] = $this->applyStdWrap( $untilDateFormatted, 'until_stdWrap' );
      }
    }
  }

  function getCountMarker(& $template, & $sims, & $rems) {

    $sims ['###COUNT###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $sims ['###COUNT###'] = $this->applyStdWrap( $this->object->getCount(), 'count_stdWrap' );
      $sims ['###COUNT_VALUE###'] = htmlspecialchars( $this->object->getCount() );
    }
  }

  function getIntervalMarker(& $template, & $sims, & $rems) {

    $sims ['###INTERVAL###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $sims ['###INTERVAL###'] = $this->applyStdWrap( $this->object->getInterval(), 'interval_stdWrap' );
      $sims ['###INTERVAL_VALUE###'] = htmlspecialchars( $this->object->getInterval() );
    }
  }

  function getRdateTypeMarker(& $template, & $sims, & $rems) {

    $sims ['###RDATE_TYPE###'] = '';
    if ($this->isAllowed( 'recurring' )) {
      $sims ['###RDATE_TYPE###'] = $this->applyStdWrap( $this->controller->pi_getLL( 'l_' . $this->object->getRdateType() ), 'rdateType_stdWrap' );
      $sims ['###RDATE_TYPE_VALUE###'] = htmlspecialchars( $this->object->getRdateType() );
    }
  }

  function getNotifyMarker(& $template, & $sims, & $rems) {

    $sims ['###NOTIFY###'] = '';
    if ($this->isAllowed( 'notify' ) && isset( $this->controller->piVars ['notify'] ) && is_array( $this->controller->piVars ['notify'] )) {
      $notifydisplaylist = Array ();
      $notifyids = Array ();
      foreach ( $this->controller->piVars ['notify'] as $value ) {
        preg_match( '/(^[a-z])_([0-9]+)_(.*)/', $value, $idname );
        if ($idname [1] == 'u' || $idname [1] == 'g') {
          $offset = $this->controller->piVars [$idname [1] . '_' . $idname [2] . '_notify_offset'] ? $this->controller->piVars [$idname [1] . '_' . $idname [2] . '_notify_offset'] : $this->conf ['view.'] ['event.'] ['remind.'] ['time'];
          $notifyids [] = $idname [1] . '_' . $idname [2] . '_' . $offset;
          $notifydisplaylist [] = $idname [3] . ' (' . $offset . ')';
        }
      }
      $sims ['###NOTIFY###'] = $this->applyStdWrap( implode( ',', $notifydisplaylist ), 'notify_stdWrap' );
      $sims ['###NOTIFY_VALUE###'] = htmlspecialchars( implode( ',', $notifyids ) );
    }
  }

  function getSharedMarker(& $template, & $sims, & $rems) {

    $sims ['###SHARED###'] = '';
    if ($this->isAllowed( 'shared' ) && isset($this->controller->piVars ['shared']) && is_array( $this->controller->piVars ['shared'] )) {
      $shareddisplaylist = Array ();
      $sharedids = Array ();
      foreach ( $this->controller->piVars ['shared'] as $value ) {
        preg_match( '/(^[a-z])_([0-9]+)_(.*)/', $value, $idname );
        if ($idname [1] == 'u' || $idname [1] == 'g') {
          $sharedids [] = $idname [1] . '_' . $idname [2];
          $shareddisplaylist [] = $idname [3];
        }
      }
      $sims ['###SHARED###'] = $this->applyStdWrap( implode( ',', $shareddisplaylist ), 'shared_stdWrap' );
      $sims ['###SHARED_VALUE###'] = htmlspecialchars( implode( ',', $sharedids ) );
    }
  }

  function getExceptionMarker(& $template, & $sims, & $rems) {

    $sims ['###EXCEPTION###'] = '';
    if ($this->isAllowed( 'exception' ) && isset( $this->controller->piVars ['exception_ids'] ) && is_array( $this->controller->piVars ['exception_ids'] )) {
      $exceptiondisplaylist = Array ();
      $exceptionids = Array ();
      foreach ( $this->controller->piVars ['exception_ids'] as $value ) {
        preg_match( '/(^[a-z])_([0-9]+)_(.*)/', $value, $idname );
        if ($idname [1] == 'u' || $idname [1] == 'g') {
          $exceptionids [] = $idname [1] . '_' . $idname [2];
          $exceptiondisplaylist [] = $idname [3];
        }
      }
      $sims ['###EXCEPTION###'] = $this->applyStdWrap( implode( ',', $exceptiondisplaylist ), 'exception_stdWrap' );
      $sims ['###EXCEPTION_VALUE###'] = htmlspecialchars( implode( ',', $exceptionids ) );
    }
  }

  function getAttendeeMarker(& $template, & $sims, & $rems) {

    $sims ['###ATTENDEE###'] = '';
    if ($this->isAllowed( 'attendee' ) && $this->object->getEventType() == \TYPO3\CMS\Cal\Model\Model::EVENT_TYPE_MEETING) {
      $attendee = '';
      $allowedUsers = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode( ',', $this->conf ['rights.'] ['allowedUsers'], 1 );
      $globalAttendeeArray = $this->object->getAttendees();
      $attendeeAttendance = Array ();
      $attendeeDisplayList = Array ();
      $attendeeIds = Array ();
      $options = Array (
          
          'OPT-PARTICIPANT' => $this->controller->pi_getLL( 'l_event_attendee_OPT-PARTICIPANT' ),
          'REQ-PARTICIPANT' => $this->controller->pi_getLL( 'l_event_attendee_REQ-PARTICIPANT' ),
          'CHAIR' => $this->controller->pi_getLL( 'l_event_attendee_CHAIR' )
      );
      foreach ( $globalAttendeeArray as $serviceKey => $attendeeArray ) {
        foreach ( $attendeeArray as $attendeeObject ) {
          if ($attendeeObject->getFeUserId()) {
            $result = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( '*', 'fe_users', 'pid in (' . $this->conf ['pidList'] . ')' . $this->pageRepository->enableFields( 'fe_users' ) . ' AND uid =' . $attendeeObject->getFeUserId() );
            if ($result) {
              while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $result ) ) {
                $attendeeIds [] = 'u_' . $attendeeObject->getFeUserId();
                $attendeeDisplayList [] = $row ['username'] . ' (' . $options [$attendeeObject->getAttendance()] . ')';
              }
              $GLOBALS ['TYPO3_DB']->sql_free_result( $result );
            }
          } else {
            $attendeeIds [] = 'email_' . $attendeeObject->getEmail();
            $attendeeDisplayList [] = $attendeeObject->getEmail() . ' (' . $options [$attendeeObject->getAttendance()] . ')';
          }
          $attendeeAttendance [$attendeeObject->getFeUserId() ? $attendeeObject->getFeUserId() : $attendeeObject->getEmail()] = $attendeeObject->getAttendance();
        }
      }
      $sims ['###ATTENDEE###'] = $this->applyStdWrap( implode( ',', $attendeeDisplayList ), 'attendee_stdWrap' );
      $sims ['###ATTENDEE_VALUE###'] = htmlspecialchars( implode( ',', $attendeeIds ) );
      $sims ['###ATTENDEE_ATTENDANCE_VALUE###'] = htmlspecialchars( implode( ',', $attendeeAttendance ) );
    }
  }

  function getSendoutInvitationMarker(& $template, & $sims, & $rems, $view) {

    $sims ['###SENDOUT_INVITATION###'] = '';
    
    if ($this->isAllowed( 'sendout_invitation' )) {
      $sendoutInvitation = '';
      if ($this->object->getSendoutInvitation()) {
        $value = 1;
        $label = $this->controller->pi_getLL( 'l_true' );
      } else {
        $value = 0;
        $label = $this->controller->pi_getLL( 'l_false' );
      }
      
      $sims ['###SENDOUT_INVITATION###'] = $this->applyStdWrap( $label, 'sendout_invitation_stdWrap' );
      $sims ['###SENDOUT_INVITATION_VALUE###'] = $value;
    }
  }
}

?>