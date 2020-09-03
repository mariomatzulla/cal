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

/**
 * Front controller for the calendar base.
 * Takes requests from the main
 * controller and starts rendering in the appropriate calendar view by
 * utilizing TYPO3 services.
 *
 * @author Jeff Segars <jeff@webempoweredchurch.org>
 * @package TYPO3
 * @subpackage cal
 */
class ViewController extends \TYPO3\CMS\Cal\Controller\BaseController {

  function ViewController() {

    $this->BaseController();
  }

  /**
   * Draws the day view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawDay(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'day', '_day' );
    
    $content = $viewObj->drawDay( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the week view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawWeek(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'week', '_week' );
    $content = $viewObj->drawWeek( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the month view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawMonth(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'month', '_month' );
    $content = $viewObj->drawMonth( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the year view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawYear(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'year', '_year' );
    $content = $viewObj->drawYear( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the list view.
   *
   * @param
   *          object The events to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawList(&$master_array, $starttime, $endtime) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'list', '_list' );
    $content = $viewObj->drawList( $master_array, '', $starttime, $endtime );
    
    return $content;
  }

  /**
   * Draws the ics list view.
   *
   * @param
   *          object The categories to be shown.
   * @return string HTML output of the specified view.
   */
  function drawIcsList(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'ics', '_icslist' );
    $content = $viewObj->drawIcsList( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the admin view.
   *
   * @return string HTML output of the specified view.
   */
  function drawAdminPage() {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'admin', '_adminpage' );
    $content = $viewObj->drawAdminPage();
    
    return $content;
  }

  /**
   * Draws the subscription manager view.
   *
   * @return string HTML output of the specified view.
   */
  function drawSubscriptionManager() {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'subscription', '_subscription' );
    $content = $viewObj->drawSubscriptionManager();
    
    return $content;
  }

  /**
   * Draws the meeting manager view.
   *
   * @return string HTML output of the specified view.
   */
  function drawMeetingManager() {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'meeting', '_meeting' );
    $content = $viewObj->drawMeetingManager();
    
    return $content;
  }

  /**
   * Draws the month view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawEvent(&$event, $getdate, $relatedEvents = Array()) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'event', '_event' );
    $content = $viewObj->drawEvent( $event, $getdate, $relatedEvents );
    
    return $content;
  }

  /**
   * Draws the ics view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawIcs(&$master_array, $getdate, $sendHeaders = true, $limitAttendeeToThisEmail = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'ics', '_ics' );
    $content = $viewObj->drawIcs( $master_array, $getdate, $sendHeaders, $limitAttendeeToThisEmail );
    
    return $content;
  }

  /**
   * Draws the rss view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawRss(&$master_array, $getdate) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'rss', '_rss' );
    $content = $viewObj->drawRss( $master_array, $getdate );
    
    return $content;
  }

  /**
   * Draws the search view.
   *
   * @param
   *          object The events to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawSearchAllResult(&$master_array, $starttime, $endtime, $searchword, $locationIds = '', $organizerIds = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'search', '_searchall' );
    $content = $viewObj->drawSearchAllResult( $master_array, $starttime, $endtime, $searchword, $locationIds, $organizerIds );
    
    return $content;
  }

  /**
   * Draws the search view.
   *
   * @param
   *          object The events to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawSearchEventResult(&$master_array, $starttime, $endtime, $searchword, $locationIds = '', $organizerIds = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'search', '_searchevent' );
    $content = $viewObj->drawSearchEventResult( $master_array, $starttime, $endtime, $searchword, $locationIds, $organizerIds );
    
    return $content;
  }

  /**
   * Draws the search view.
   *
   * @param
   *          object The events to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawSearchLocationResult(&$master_array, $searchword) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'search', '_searchlocation' );
    $content = $viewObj->drawSearchLocationResult( $master_array, $searchword );
    
    return $content;
  }

  /**
   * Draws the search view.
   *
   * @param
   *          object The events to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawSearchOrganizerResult(&$master_array, $searchword) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'search', '_searchorganizer' );
    $content = $viewObj->drawSearchOrganizerResult( $master_array, $searchword );
    
    return $content;
  }

  /**
   * Draws the location view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawLocation(&$location, $relatedEvents = Array()) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'location', '_location' );
    $content = $viewObj->drawLocation( $location, $relatedEvents );
    
    return $content;
  }

  /**
   * Draws the organizer view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawOrganizer(&$organizer, $relatedEvents = Array()) {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'organizer', '_organizer' );
    $content = $viewObj->drawOrganizer( $organizer, $relatedEvents );
    
    return $content;
  }

  /**
   * Draws the create event view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawCreateEvent($getdate, $pidList = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_event', '_create_event' );
    $content = $viewObj->drawCreateEvent( $getdate, $pidList );
    
    return $content;
  }

  /**
   * Draws the confirm event view.
   *
   * @param
   *          object The event to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawConfirmEvent($pidList = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'confirm_event', '_confirm_event' );
    $content = $viewObj->drawConfirmEvent( $pidList );
    
    return $content;
  }

  /**
   * Draws the edit event view.
   *
   * @param
   *          object The event to be edited.
   * @return string HTML output of the specified view.
   */
  function drawEditEvent(&$event, $pidList = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_event', '_create_event' );
    $content = $viewObj->drawCreateEvent( $this->conf ['getdate'], $pidList, $event );
    
    return $content;
  }

  /**
   * Draws the delete event view.
   *
   * @param
   *          object The event to be deleted.
   * @return string HTML output of the specified view.
   */
  function drawDeleteEvent(&$event, $pidList = '') {

    /* Call the view and pass it the event to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'delete_event', '_delete_event' );
    $content = $viewObj->drawDeleteEvent( $event, $pidList );
    
    return $content;
  }

  /**
   * Draws the create location view.
   *
   * @param
   *          object The location to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawCreateLocation($getdate, $pidList = '') {

    /* Call the view and pass it the location to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_location', '_create_location' );
    $content = $viewObj->drawCreateLocationOrOrganizer( true, $pidList );
    return $content;
  }

  /**
   * Draws the confirm location view.
   *
   * @param
   *          object The location to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawConfirmLocation($pidList = '') {

    /* Call the view and pass it the location to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'confirm_location', '_confirm_location' );
    $content = $viewObj->drawConfirmLocationOrOrganizer( true, $pidList );
    
    return $content;
  }

  /**
   * Draws the edit location view.
   *
   * @param
   *          object The location to be edited.
   * @return string HTML output of the specified view.
   */
  function drawEditLocation(&$location, $pidList = '') {

    /* Call the view and pass it the location to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_location', '_create_location' );
    $content = $viewObj->drawCreateLocationOrOrganizer( true, $pidList, $location );
    
    return $content;
  }

  /**
   * Draws the delete location view.
   *
   * @param
   *          object The location to be deleted.
   * @return string HTML output of the specified view.
   */
  function drawDeleteLocation(&$location, $pidList = '') {

    /* Call the view and pass it the location to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'delete_location', '_delete_location' );
    $content = $viewObj->drawDeleteLocationOrOrganizer( true, $location );
    
    return $content;
  }

  /**
   * Draws the create organizer view.
   *
   * @param
   *          object The organizer to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawCreateOrganizer($getdate, $pidList = '') {

    /* Call the view and pass it the organizer to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_organizer', '_create_organizer' );
    $content = $viewObj->drawCreateLocationOrOrganizer( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the confirm organizer view.
   *
   * @param
   *          object The organizer to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawConfirmOrganizer($pidList = '') {

    /* Call the view and pass it the organizer to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'confirm_organizer', '_confirm_organizer' );
    $content = $viewObj->drawConfirmLocationOrOrganizer( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the edit event view.
   *
   * @param
   *          object The event to be edited.
   * @return string HTML output of the specified view.
   */
  function drawEditOrganizer(&$organizer, $pidList = '') {

    /* Call the view and pass it the organizer to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_organizer', '_create_organizer' );
    $content = $viewObj->drawCreateLocationOrOrganizer( false, $pidList, $organizer );
    
    return $content;
  }

  /**
   * Draws the delete organizer view.
   *
   * @param
   *          object The organizer to be deleted.
   * @return string HTML output of the specified view.
   */
  function drawDeleteOrganizer(&$organizer, $pidList = '') {

    /* Call the view and pass it the organizer to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'delete_organizer', '_delete_organizer' );
    $content = $viewObj->drawDeleteLocationOrOrganizer( false, $organizer );
    
    return $content;
  }

  /**
   * Draws the create calendar view.
   *
   * @param
   *          object The calendar to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawCreateCalendar($getdate, $pidList = '') {

    /* Call the view and pass it the calendar to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_calendar', '_create_calendar' );
    $content = $viewObj->drawCreateCalendar( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the confirm calendar view.
   *
   * @param
   *          object The calendar to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawConfirmCalendar($pidList = '') {

    /* Call the view and pass it the calendar to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'confirm_calendar', '_confirm_calendar' );
    $content = $viewObj->drawConfirmCalendar( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the edit event view.
   *
   * @param
   *          object The event to be edited.
   * @return string HTML output of the specified view.
   */
  function drawEditCalendar(&$calendar, $pidList = '') {

    /* Call the view and pass it the calendar to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_calendar', '_create_calendar' );
    $content = $viewObj->drawCreateCalendar( $pidList, $calendar );
    
    return $content;
  }

  /**
   * Draws the delete calendar view.
   *
   * @param
   *          object The calendar to be deleted.
   * @return string HTML output of the specified view.
   */
  function drawDeleteCalendar(&$calendar, $pidList = '') {

    /* Call the view and pass it the calendar to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'delete_calendar', '_delete_calendar' );
    $content = $viewObj->drawDeleteCalendar( $calendar, $pidList, $calendar );
    
    return $content;
  }

  /**
   * Draws the create category view.
   *
   * @param
   *          object The category to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawCreateCategory($getdate, $pidList = '') {

    /* Call the view and pass it the category to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_category', '_create_category' );
    $content = $viewObj->drawCreateCategory( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the confirm category view.
   *
   * @param
   *          object The category to be drawn.
   * @return string HTML output of the specified view.
   */
  function drawConfirmCategory($pidList = '') {

    /* Call the view and pass it the category to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'confirm_category', '_confirm_category' );
    $content = $viewObj->drawConfirmCategory( false, $pidList );
    
    return $content;
  }

  /**
   * Draws the edit event view.
   *
   * @param
   *          object The event to be edited.
   * @return string HTML output of the specified view.
   */
  function drawEditCategory(&$category, $pidList = '') {

    /* Call the view and pass it the category to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'create_category', '_create_category' );
    $content = $viewObj->drawCreateCategory( $pidList, $category );
    
    return $content;
  }

  /**
   * Draws the delete category view.
   *
   * @param
   *          object The category to be deleted.
   * @return string HTML output of the specified view.
   */
  function drawDeleteCategory(&$category, $pidList = '') {

    /* Call the view and pass it the category to draw */
    $viewObj = $this->getServiceObjByKey( 'cal_view', 'delete_category', '_delete_category' );
    $content = $viewObj->drawDeleteCategory( $category, $pidList, $category );
    
    return $content;
  }

  /**
   * Helper function to return a service object with the given type, subtype, and serviceKey
   *
   * @param
   *          string The type of the service.
   * @param
   *          string The subtype of the service.
   * @param
   *          string The serviceKey.
   * @return object service object.
   */
  function getServiceObjByKey($type, $subtype = '', $key) {

    $serviceChain = [ ];
    /* Loop over all services providign the specified service type and subtype */
    while ( is_object( $obj = &\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstanceService( $type, $subtype, $serviceChain ) ) ) {
      $serviceChain [] = $obj->getServiceKey();
      return $obj;
    }
    return;
  }
}

?>