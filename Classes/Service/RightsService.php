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
 * This class handles all cal(endar)-rights of a current logged-in user
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class RightsService extends \TYPO3\CMS\Cal\Service\BaseService {

  var $confArr = array ();

  private $context;

  public function __construct() {

    parent::__construct();
    $this->confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
  }

  private function getContext() {

    if (! $this->context) {
      $this->context = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Context\Context::class );
    }
    return $this->context;
  }
  
  private function isPublicAllowedToCreateEvent() {
    
    return $this->isTrue4('rights.', 'create.', 'event.', 'public');
  }
  
  private function isTrue3($param1, $param2, $param3) {
    
    return isset($this->conf [$param1] [$param2] [$param3]) && $this->conf [$param1] [$param2] [$param3];
  }
  
  private function isTrue4($param1, $param2, $param3, $param4) {
    
    return isset($this->conf [$param1] [$param2] [$param3] [$param4]) && $this->conf [$param1] [$param2] [$param3] [$param4];
  }
  
  private function isTrue5($param1, $param2, $param3, $param4, $param5) {
    
    return isset($this->conf [$param1] [$param2] [$param3] [$param4] [$param5]) && $this->conf [$param1] [$param2] [$param3] [$param4] [$param5];
  }
  
  private function isTrue6($param1, $param2, $param3, $param4, $param5, $param6) {
    
    return isset($this->conf [$param1] [$param2] [$param3] [$param4] [$param5] [$param6]) && $this->conf [$param1] [$param2] [$param3] [$param4] [$param5] [$param6];
  }
  

  function isLoggedIn() {

    return $this->getContext()->getPropertyFromAspect( 'frontend.user', 'isLoggedIn' );
  }

  function getUserGroups() {

    if ($this->isLoggedIn()) {
      return $val = $this->getContext()->getPropertyFromAspect( 'frontend.user', 'groupIds' );
    }
    return array ();
  }

  function getUserId() {

    if ($this->isLoggedIn()) {
      $val = ( int ) $this->getContext()->getPropertyFromAspect( 'frontend.user', 'userId' );
      return $val;
    }
    return - 1;
  }

  function getUserName() {

    if ($this->isLoggedIn()) {
      $val = $GLOBALS ['TSFE']->fe_user->user ['username'];
      return $val;
    }
    return - 1;
  }

  function isCalEditable() {

    if ($this->conf ['rights.'] ['edit'] == 1)
      return true;
    return false;
  }

  function isCalAdmin() {

    if ($this->isLoggedIn()) {
      $users = explode( ',', $this->conf ['rights.'] ['admin.'] ['user'] ?? array() );
      $groups = explode( ',', $this->conf ['rights.'] ['admin.'] ['group'] ?? array() );
      if (array_search( $this->getUserId(), $users ) !== false)
        return true;
      $userGroups = $this->getUserGroups();
      foreach ( $groups as $key => $group ) {
        if (array_search( ltrim( $group ), $userGroups ) !== false)
          return true;
      }
    }
    return false;
  }

  function isAllowedToCreateEvent() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ?? array() );
  }

  function isAllowedToCreateEventInPast() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['inPast.'] ?? array() );
  }

  function isAllowedToCreateEventForTodayAndFuture() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['forTodayAndFuture.'] ?? array());
  }

  function isAllowedToEditEventInPast() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['event.'] ['inPast.'] ?? array() );
  }

  function isAllowedToDeleteEventInPast() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['event.'] ['inPast.'] ?? array() );
  }

  function isAllowedToCreateEventFields($field) {

    if ($this->isPublicAllowedToCreateEvent() && $this->isTrue6('rights.','create.','event.','fields.',$field,'public')) {
      return true;
    }
    if ($this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['enableAllFields.'] ?? array() ) || $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] [$field] ?? array() )) {
      return true;
    }
    return false;
  }
  
  function isAllowedToCreateEventHidden() {
    return $this->isAllowedToCreateEventFields('hidden.');
  }

  function isAllowedToCreateEventCategory() {
    $this->isAllowedToCreateEventFields('category.');
  }

  function isAllowedToCreateEventCalendar() {
    $this->isAllowedToCreateEventFields('calendar_id.');
  }

  function isAllowedToCreatePublicEvent() {
    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['publicEvents.'] ?? array());
  }
  
  function isAllowedToCreateEventDateTime() {
    if ($this->isPublicAllowedToCreateEvent() && ($this->isTrue6('rights.','create.','event.','fields.','startdate.','public') || $this->isTrue6('rights.','create.','event.','fields.','enddate.','public') || $this->isTrue6('rights.','create.','event.','fields.','starttime.','public') || $this->isTrue6('rights.','create.','event.','fields.','endtime.','public'))) {
      return true;
    }
    if ($this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['enableAllFields.'] ?? array()) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['startdate.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['enddate.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['starttime.'] ?? array()) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['endtime.'] ?? array() )) {
      return true;
    }
    return false;
  }

  function isAllowedToCreateEventTitle() {
    $this->isAllowedToCreateEventFields('title.');
  }
  
  function isAllowedToCreateEventOrganizer() {
    $this->isAllowedToCreateEventFields('organizer.');
  }

  function isAllowedToCreateEventLocation() {
    $this->isAllowedToCreateEventFields('location.');
  }

  function isAllowedToCreateEventDescription() {
    $this->isAllowedToCreateEventFields('description.');
  }

  function isAllowedToCreateEventTeaser() {
    $this->isAllowedToCreateEventFields('teaser.');
  }

  function isAllowedToCreateEventRecurring() {
    $this->isAllowedToCreateEventFields('recurring.');
  }

  function isAllowedToCreateEventNotify() {
    $this->isAllowedToCreateEventFields('notify.');
  }

  function isAllowedToCreateEventException() {
    $this->isAllowedToCreateEventFields('exception.');
  }

  function isAllowedToCreateEventShared() {
    $this->isAllowedToCreateEventFields('shared.');
  }

  function isAllowedToEditEvent() {
    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['event.'] ?? array() );
  }

  function isPublicAllowedToEditEvents() {
    return isset($this->conf ['rights.'] ['edit.'] ['event.'] ['public']) && $this->conf ['rights.'] ['edit.'] ['event.'] ['public'] == 1;
  }

  function isAllowedToEditStartedEvent() {
    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['event.'] ['startedEvents.'] ?? array() );
  }

  function isAllowedToEditOnlyOwnEvent() {
    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['event.'] ['onlyOwnEvents.'] ?? array() );
  }

  function isAllowedToEditEventHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'hidden.');
  }

  function isAllowedToEditEventCalendar() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'calendar_id.');
  }

  function isAllowedToEditEventCategory() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'category.');
  }

  function isAllowedToEditEventDateTime() {
    if ($this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['enableAllFields.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['startdate.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['enddate.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['starttime.'] ?? array() ) || 
        $this->checkRights( $this->conf ['rights.'] ['create.'] ['event.'] ['fields.'] ['endtime.'] ?? array() )) {
      return true;
    }
    return false;
  }

  function isAllowedToEditEventTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'title.');
  }

  function isAllowedToEditEventOrganizer() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'organizer.');
  }

  function isAllowedToEditEventLocation() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'location.');
  }

  function isAllowedToEditEventDescription() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'description.');
  }

  function isAllowedToEditEventTeaser() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'teaser.');
  }

  function isAllowedToEditEventRecurring() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'recurring.');
  }

  function isAllowedToEditEventNotify() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'notify.');
  }

  function isAllowedToEditEventException() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'event.', 'exception.');
  }

  function isAllowedToDeleteEvents() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['event.'] ?? array() );
  }

  function isAllowedToDeleteOnlyOwnEvents() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['event.'] ['onlyOwnEvents.'] ?? array() );
  }

  function isAllowedToDeleteStartedEvents() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['event.'] ['startedEvents.'] ?? array() );
  }

  function isAllowedToCreateExceptionEvent() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['exceptionEvent.'] ?? array() );
  }

  function isAllowedToEditExceptionEvent() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['exceptionEvent.'] ?? array() );
  }

  function isAllowedToDeleteExceptionEvents() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['exceptionEvent.'] ?? array() );
  }

  function isAllowedToCreateLocations() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['location.'] ?? array() );
  }
  
  private function isTrueForEnableFieldsOrObjectField($createEditDelete, $objectType, $field) {
    if ($this->checkRights( $this->conf ['rights.'] [$createEditDelete] [$objectType] ['enableAllFields.'] ?? array() ) || $this->checkRights( $this->conf ['rights.'] [$createEditDelete] [$objectType] ['fields.'] [$field] ?? array() )) {
      return true;
    }
    return false;
  }

  public function isAllowedToCreateLocationHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'hidden.');
  }

  function isAllowedToCreateLocationTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'title.');
  }

  function isAllowedToCreateLocationDescription() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'description.');
  }

  function isAllowedToCreateLocationName() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'name.');
  }

  function isAllowedToCreateLocationStreet() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'street.');
  }

  function isAllowedToCreateLocationZip() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'zip.');
  }

  function isAllowedToCreateLocationCity() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'city.');
  }

  function isAllowedToCreateLocationCountryZone() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'countryZone.');
  }

  function isAllowedToCreateLocationCountry() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'country.');
  }

  function isAllowedToCreateLocationPhone() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'phone.');
  }

  function isAllowedToCreateLocationEmail() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'email.');
  }

  function isAllowedToCreateLocationImage() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'image.');
  }

  function isAllowedToCreateLocationLink() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'location.', 'link.');
  }

  function isAllowedToEditLocation() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['location.'] ?? array());
  }
  
  function isAllowedToEditLocationHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'hidden.');
  }

  function isAllowedToEditLocationTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'title.');
  }

  function isAllowedToEditLocationDescription() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'description.');
  }

  function isAllowedToEditLocationName() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'name.');
  }

  function isAllowedToEditLocationStreet() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'street.');
  }

  function isAllowedToEditLocationZip() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'zip.');
  }

  function isAllowedToEditLocationCity() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'city.');
  }

  function isAllowedToEditLocationCountryZone() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'countryZone.');
  }

  function isAllowedToEditLocationCountry() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'country.');
  }

  function isAllowedToEditLocationPhone() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'phone.');
  }

  function isAllowedToEditLocationEmail() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'email.');
  }

  function isAllowedToEditLocationLogo() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'logo.');
  }

  function isAllowedToEditLocationHomepage() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'location.', 'homepage.');
  }

  function isAllowedToDeleteLocation() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['location.'] ?? array() );
  }
  
  // TODO: Remove for version 1.4.0, but keep this function for backwards compatibility until than
  function isAllowedToDeleteLocations() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['location.'] ?? array() );
  }

  function isAllowedToEditOnlyOwnLocation() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['location.'] ['onlyOwnLocation.'] ?? array() );
  }

  function isAllowedToDeleteOnlyOwnLocation() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['location.'] ['onlyOwnLocation.'] ?? array() );
  }

  function isAllowedToCreateOrganizer() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['organizer.'] ?? array() );
  }

  function isAllowedToCreateOrganizerHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'hidden.');
  }

  function isAllowedToCreateOrganizerTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'title.');
  }

  function isAllowedToCreateOrganizerDescription() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'description.');
  }

  function isAllowedToCreateOrganizerName() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'name.');
  }

  function isAllowedToCreateOrganizerStreet() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'street.');
  }

  function isAllowedToCreateOrganizerZip() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'zip.');
  }

  function isAllowedToCreateOrganizerCity() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'city.');
  }

  function isAllowedToCreateOrganizerPhone() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'phone.');
  }

  function isAllowedToCreateOrganizerEmail() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'email.');
  }

  function isAllowedToCreateOrganizerImage() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'image.');
  }

  function isAllowedToCreateOrganizerLink() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'organizer.', 'link.');
  }

  function isAllowedToEditOrganizer() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['organizer.'] ?? array() );
  }

  function isAllowedToEditOrganizerHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'hidden.');
  }

  function isAllowedToEditOrganizerTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'title.');
  }

  function isAllowedToEditOrganizerDescription() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'description.');
  }

  function isAllowedToEditOrganizerName() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'name.');
  }

  function isAllowedToEditOrganizerStreet() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'street.');
  }

  function isAllowedToEditOrganizerZip() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'zip.');
  }

  function isAllowedToEditOrganizerCity() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'city.');
  }

  function isAllowedToEditOrganizerPhone() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'phone.');
  }

  function isAllowedToEditOrganizerEmail() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'email.');
  }

  function isAllowedToEditOrganizerLogo() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'logo.');
  }

  function isAllowedToEditOrganizerHomepage() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'organizer.', 'homepage.');
  }

  function isAllowedToDeleteOrganizer() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['organizer.'] ?? array() );
  }

  function isAllowedToEditOnlyOwnOrganizer() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['organizer.'] ['onlyOwnOrganizer.'] ?? array() );
  }

  function isAllowedToDeleteOnlyOwnOrganizer() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['organizer.'] ['onlyOwnOrganizer.'] ?? array() );
  }

  function isAllowedToCreateCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['calendar.'] ?? array() );
  }

  function isAllowedToCreateCalendarHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'hidden.');
  }

  function isAllowedToCreateCalendarTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'title.');
  }

  function isAllowedToCreateCalendarOwner() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'owner.');
  }

  function isAllowedToCreateCalendarActivateFreeAndBusy() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'activateFreeAndBusy.');
  }

  function isAllowedToCreateCalendarFreeAndBusyUser() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'freeAndBusyUser.');
  }

  function isAllowedToCreateCalendarType() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'calendar.', 'type.');
  }

  function isAllowedToEditCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['calendar.'] ?? array() );
  }

  function isAllowedToEditOnlyOwnCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['calendar.'] ['onlyOwnCalendar.'] ?? array() );
  }

  function isAllowedToEditPublicCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['calendar.'] ['publicCalendar.'] ?? array() );
  }

  function isAllowedToEditCalendarHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'hidden.');
  }

  function isAllowedToEditCalendarType() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'type.');
  }

  function isAllowedToEditCalendarTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'title.');
  }

  function isAllowedToEditCalendarOwner() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'owner.');
  }

  function isAllowedToEditCalendarActivateFreeAndBusy() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'activateFreeAndBusy.');
  }

  function isAllowedToEditCalendarFreeAndBusyUser() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'calendar.', 'freeAndBusyUser.');
  }

  function isAllowedToDeleteCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['calendar.'] ?? array() );
  }

  function isAllowedToDeleteOnlyOwnCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['calendar.'] ['onlyOwnCalendar.'] ?? array() );
  }

  function isAllowedToDeletePublicCalendar() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['calendar.'] ['publicCalendar.'] ?? array() );
  }

  function isAllowedToCreateCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['category.'] ?? array() );
  }

  function isAllowedToCreateCategoryHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'hidden.');
  }

  function isAllowedToCreateCategoryTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'title.');
  }

  function isAllowedToCreateCategoryHeaderStyle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'headerstyle.');
  }

  function isAllowedToCreateCategoryBodyStyle() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'bodystyle.');
  }

  function isAllowedToCreateCategoryCalendar() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'calendar.');
  }

  function isAllowedToCreateCategoryParent() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'parent.');
  }
  
  function isAllowedToCreateCategorySharedUser() {
    return $this->isTrueForEnableFieldsOrObjectField('create.', 'category.', 'sharedUser.');
  }

  function isAllowedToCreateGeneralCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['category.'] ['generalCategory.'] ?? array() );
  }

  function isAllowedToCreatePublicCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['create.'] ['category.'] ['publicCategory.'] ?? array() );
  }

  function isAllowedToEditCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['category.'] ?? array() );
  }

  function isAllowedToEditOnlyOwnCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['category.'] ['onlyOwnCategory.'] ?? array() );
  }

  function isAllowedToEditGeneralCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['category.'] ['generalCategory.'] ?? array() );
  }

  function isAllowedToEditPublicCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['edit.'] ['category.'] ['publicCategory.'] ?? array() );
  }

  function isAllowedToEditCategoryHidden() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'hidden.');
  }

  function isAllowedToEditCategoryTitle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'title.');
  }

  function isAllowedToEditCategoryHeaderstyle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'headerstyle.');
  }

  function isAllowedToEditCategoryBodystyle() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'bodystyle.');
  }

  function isAllowedToEditCategoryCalendar() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'calendar.');
  }

  function isAllowedToEditCategoryParent() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'parent.');
  }

  function isAllowedToEditCategorySharedUser() {
    return $this->isTrueForEnableFieldsOrObjectField('edit.', 'category.', 'sharedUser.');
  }

  function isAllowedToDeleteCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['category.'] ?? array() );
  }

  function isAllowedToDeleteOnlyOwnCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['category.'] ['onlyOwnCategory.'] ?? array() );
  }

  function isAllowedToDeleteGeneralCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['category.'] ['generalCategory.'] ?? array() );
  }

  function isAllowedToDeletePublicCategory() {

    return $this->checkRights( $this->conf ['rights.'] ['delete.'] ['category.'] ['publicCategory.'] ?? array() );
  }

  function isAllowedToConfigure() {

    return ($this->isLoggedIn() && $this->isViewEnabled( 'admin' ) && ($this->isCalAdmin() || $this->isAllowedToCreateCalendar() || $this->isAllowedToEditCalendar() || $this->isAllowedToDeleteCalendar() || $this->isAllowedToCreateCategory() || $this->isAllowedToEditCategory() || $this->isAllowedToDeleteCategory() || $this->isAllowedTo( 'create', 'location' ) || $this->isAllowedTo( 'edit', 'location' ) || $this->isAllowedTo( 'delete', 'location' ) || $this->isAllowedToCreateOrganizer() || $this->isAllowedToEditOrganizer() || $this->isAllowedToDeleteOrganizer()));
  }

  function isAllowedTo($type, $object, $field = '') {

    $field = strtolower( $field );
    if ($field == '') {
      return $this->checkRights( $this->conf ['rights.'] [$type . '.'] [$object . '.'] ?? array() );
    } else if ($field == 'teaser' && ! $this->confArr ['useTeaser']) {
      return false;
    }
    
    if (($this->isTrue3('rights.',$type . '.','public') && 
        $this->isTrue6('rights.',$type . '.',$object . '.','fields.',$field . '.','public')) || 
        $this->checkRights( $this->conf ['rights.'] [$type . '.'] [$object . '.'] ['enableAllFields.'] ?? false ) || 
        $this->checkRights( $this->conf ['rights.'] [$type . '.'] [$object . '.'] ['fields.'] [$field . '.'] ?? array() )) {
      return true;
    }
    
    return false;
  }

  function checkRights($category) {

    if ($this->isCalAdmin()) {
      return true;
    }
    if ($this->isLoggedIn()) {
      $users = explode( ',', $category ['user'] ?? array() );
      $groups = explode( ',', $category ['group'] ?? array() );
      
      if (array_search( $this->getUserId(), $users ) !== false)
        return true;
      $userGroups = $this->getUserGroups();
      foreach ( $groups as $key => $group ) {
        if (array_search( ltrim( $group ), $userGroups ) !== false)
          return true;
      }
    }
    if (isset($category ['public']) && $category ['public'] == 1) {
      return true;
    }
    return false;
  }

  function checkView($view) {

    if ($view == 'day' || $view == 'week' || $view == 'month' || $view == 'year' || $view == 'event' || $view == 'todo' || $view == 'location' || $view == 'organizer' || $view == 'list' || $view == 'icslist' || $view == 'search_all' || $view == 'search_event' || $view == 'search_location' || $view == 'search_organizer') {
      // catch all allowed standard view types
    } else if (($view == 'ics' || $view == 'single_ics') && $this->conf ['view.'] ['ics.'] ['showIcsLinks'] && $this->isViewEnabled( $view )) {
      $this->conf ['view.'] ['allowedViews'] = array (
          
          0 => $view
      );
      return $view;
    } else if ($view == 'rss') {
      $this->conf ['view.'] ['allowedViews'] = array (
          
          0 => $view
      );
      return $view;
    } else if ($view == 'subscription' && $this->conf ['allowSubscribe'] && $this->isViewEnabled( $view )) {
    } else if ($view == 'translation' && $this->rightsObj->isAllowedTo( 'create', 'translation' ) && $this->isViewEnabled( $view )) {
    } else if ($view == 'meeting' && $this->isViewEnabled( $view )) {
    } else if ($view == 'admin' && $this->rightsObj->isAllowedToConfigure()) {
    } else if (($view == 'load_events' || $view == 'load_todos' || $view == 'load_calendars' || $view == 'load_categories' || $view == 'load_rights' || $view == 'load_locations' || $view == 'load_organizers' || $view == 'search_user_and_group') && $this->conf ['view.'] ['enableAjax']) {
      // catch all allowed standard view types
    } else if (($view == 'save_calendar' || $view == 'edit_calendar' || $view == 'confirm_calendar' || $view == 'delete_calendar' || $view == 'remove_calendar' || $view == 'create_calendar') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateCalendar() || $this->rightsObj->isAllowedToEditCalendar() || $this->rightsObj->isAllowedToDeleteCalendar())) {
    } else if (($view == 'save_category' || $view == 'edit_category' || $view == 'confirm_category' || $view == 'delete_category' || $view == 'remove_category' || $view == 'create_category') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateCalendar() || $this->rightsObj->isAllowedToEditCategory() || $this->rightsObj->isAllowedToDeleteCategory())) {
    } else if (($view == 'save_event' || $view == 'edit_event' || $view == 'confirm_event' || $view == 'delete_event' || $view == 'remove_event' || $view == 'create_event') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateEvent() || $this->rightsObj->isAllowedToEditEvent() || $this->rightsObj->isAllowedToDeleteEvents())) {
    } else if (($view == 'save_exception_event' || $view == 'edit_exception_event' || $view == 'confirm_exception_event' || $view == 'delete_exception_event' || $view == 'remove_exception_event' || $view == 'create_exception_event') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateExceptionEvent() || $this->rightsObj->isAllowedToEditExceptionEvent() || $this->rightsObj->isAllowedToDeleteExceptionEvents())) {
    } else if (($view == 'save_location' || $view == 'confirm_location' || $view == 'create_location' || $view == 'edit_location' || $view == 'delete_location' || $view == 'remove_location') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateLocations() || $this->rightsObj->isAllowedToEditLocation() || $this->rightsObj->isAllowedToDeleteLocation())) {
      // catch create_location view type and check all conditions
    } else if (($view == 'save_organizer' || $view == 'confirm_organizer' || $view == 'create_organizer' || $view == 'edit_organizer' || $view == 'delete_organizer' || $view == 'remove_organizer') && $this->rightsObj->isCalEditable() && ($this->rightsObj->isAllowedToCreateOrganizer() || $this->rightsObj->isAllowedToEditOrganizer() || $this->rightsObj->isAllowedToDeleteOrganizer())) {
      // catch create_organizer view type and check all conditions
      // I'm not sure why this is in here, but I think it shouldn't, b/c you will get an empty create_event view even if you are not allowed to create events
      // } else if ($this->isViewEnabled($view)){
    } else {
      // a not wanted view type -> convert it
      $view = $this->conf ['view.'] ['allowedViews'] [0];
      if ($view == '') {
        $view = 'month';
      }
      $this->conf ['type'] = '';
      $this->controller->piVars ['type'] = null;
    }
    if (count( $this->conf ['view.'] ['allowedViews'] ) == 1) {
      $view = $this->conf ['view.'] ['allowedViews'] [0];
      if (! in_array( $this->conf ['view.'] ['allowedViews'] [0], array (
          
          'event',
          'organizer',
          'location'
      ) )) {
        $this->conf ['uid'] = '';
        $this->piVars ['uid'] = null;
        $this->conf ['type'] = '';
        $this->piVars ['type'] = null;
      } else if ($this->conf ['view.'] ['allowedViews'] [0] == 'event' && (($this->piVars ['view'] == 'location' && ! in_array( 'location', $this->conf ['view.'] ['allowedViews'] )) || ($this->piVars ['view'] == 'organizer' && ! in_array( 'organizer', $this->conf ['view.'] ['allowedViews'] )))) {
        return;
      }
    } else if (! ($view == 'admin' && $this->rightsObj->isAllowedToConfigure()) && ! in_array( $view, $this->conf ['view.'] ['allowedViews'] )) {
      $view = $this->conf ['view.'] ['allowedViews'] [0];
    }
    if (! $view) {
      $view = $this->conf ['view.'] ['allowedViews'] [0];
    }
    return $view;
  }

  /* @todo Is there a way to check for allowed views on other pages that are specified by TS? */
  function isViewEnabled($view) {

    if (in_array( $view, $this->conf ['view.'] ['allowedViewsToLinkTo'] )) {
      return true;
    }
    return false;
  }

  /**
   * Sets the default pages for saving calendars, events, etc.
   * If the Typoscript
   * is not set and there's only one page in the pidList, then we can set this
   * page be default.
   *
   * @return none
   */
  function setDefaultSaveToPage() {

    $pagesArray = explode( ",", $this->conf ['pidList'] );
    
    /* If there's only one page in pidList */
    if (count( $pagesArray ) == 1) {
      $pid = $pagesArray [0];
      
      /* If a saveTo page does not have a value set, set a default */
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['calendar.'] ['saveCalendarToPid'], $pid );
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['category.'] ['saveCategoryToPid'], $pid );
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['event.'] ['saveEventToPid'], $pid );
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['exceptionEvent.'] ['saveExceptionEventToPid'], $pid );
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['location.'] ['saveLocationToPid'], $pid );
      $this->setPidIfEmpty( $this->conf ['rights.'] ['create.'] ['organizer.'] ['saveOrganizerToPid'], $pid );
    }
  }

  /**
   * Sets a conf value if it is currently empty.
   * Helper function for setDefaultSaveToPage().
   *
   * @param
   *          mixed The conf value to be set.
   * @param
   *          mixed The value to set.
   */
  function setPidIfEmpty(&$conf, $value) {

    if (! $conf) {
      $conf = $value;
    }
  }
}

?>