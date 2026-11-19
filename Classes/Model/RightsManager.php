<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
class RightsManager
{
    private $confArr =  [];

    private $conf =  [];

    private $context;

    public function __construct(array $settings, array $extensionConfiguration)
    {
        $this->conf = $settings;
        $this->confArr = $extensionConfiguration;
        $this->context = GeneralUtility::makeInstance(Context::class);
    }

    private function getContext()
    {
        return $this->context;
    }

    private function isPublicAllowedToCreateEvent()
    {
        return $this->isTrue4('rights', 'create', 'event', 'public');
    }

    private function isTrue3($param1, $param2, $param3)
    {
        return isset($this->conf [$param1] [$param2] [$param3]) && $this->conf [$param1] [$param2] [$param3];
    }

    private function isTrue4($param1, $param2, $param3, $param4)
    {
        return isset($this->conf [$param1] [$param2] [$param3] [$param4]) && $this->conf [$param1] [$param2] [$param3] [$param4];
    }

    private function isTrue5($param1, $param2, $param3, $param4, $param5)
    {
        return isset($this->conf [$param1] [$param2] [$param3] [$param4] [$param5]) && $this->conf [$param1] [$param2] [$param3] [$param4] [$param5];
    }

    private function isTrue6($param1, $param2, $param3, $param4, $param5, $param6)
    {
        return isset($this->conf [$param1] [$param2] [$param3] [$param4] [$param5] [$param6]) && $this->conf [$param1] [$param2] [$param3] [$param4] [$param5] [$param6];
    }

    public function isLoggedIn()
    {
        return $this->getContext()->getPropertyFromAspect('frontend.user', 'isLoggedIn');
    }

    public function getUserGroups()
    {
        if ($this->isLoggedIn()) {
            return $this->getContext()->getPropertyFromAspect('frontend.user', 'groupIds');
        }
        return [];
    }

    public function getUserId()
    {
        if ($this->isLoggedIn()) {
            return (int)$this->getContext()->getPropertyFromAspect('frontend.user', 'id');
        }
        return - 1;
    }

    public function getUserName()
    {
        if ($this->isLoggedIn()) {
            return $this->getContext()->getPropertyFromAspect('frontend.user', 'username');
        }
        return null;
    }

    public function isCalEditable()
    {
        return $this->conf ['rights'] ['edit'] == 1;
    }

    public function isCalAdmin()
    {
        if ($this->isLoggedIn()) {
            $users = explode(',', (string)($this->conf ['rights'] ['admin'] ['user'] ?? ''));
            $groups = explode(',', (string)($this->conf ['rights'] ['admin'] ['group'] ?? ''));
            if (array_search($this->getUserId(), $users) !== false) {
                return true;
            }
            $userGroups = $this->getUserGroups();
            foreach ($groups as $key => $group) {
                if (array_search(ltrim($group), $userGroups) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    public function isAllowedToCreateEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['event'] ??  []);
    }

    public function isAllowedToCreateEventInPast()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['inPast'] ??  []);
    }

    public function isAllowedToCreateEventForTodayAndFuture()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['forTodayAndFuture'] ??  []);
    }

    public function isAllowedToEditEventInPast()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['event'] ['inPast'] ??  []);
    }

    public function isAllowedToDeleteEventInPast()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['event'] ['inPast'] ??  []);
    }

    public function isAllowedToCreateEventFields($field)
    {
        if ($this->isPublicAllowedToCreateEvent() && $this->isTrue6('rights', 'create', 'event', 'fields', $field, 'public')) {
            return true;
        }
        if ($this->checkRights($this->conf ['rights'] ['create'] ['event'] ['enableAllFields'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] [$field] ??  [])) {
            return true;
        }
        return false;
    }

    public function isAllowedToCreateEventHidden()
    {
        return $this->isAllowedToCreateEventFields('hidden');
    }

    public function isAllowedToCreateEventCategory()
    {
        return $this->isAllowedToCreateEventFields('category');
    }

    public function isAllowedToCreateEventCalendar()
    {
        return $this->isAllowedToCreateEventFields('calendar_id');
    }

    public function isAllowedToCreatePublicEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['publicEvents'] ??  []);
    }

    public function isAllowedToCreateEventDateTime()
    {
        if ($this->isPublicAllowedToCreateEvent() && ($this->isTrue6('rights', 'create', 'event', 'fields', 'startdate', 'public') || $this->isTrue6('rights', 'create', 'event', 'fields', 'enddate', 'public') || $this->isTrue6('rights', 'create', 'event', 'fields', 'starttime', 'public') || $this->isTrue6('rights', 'create', 'event', 'fields', 'endtime', 'public'))) {
            return true;
        }
        if ($this->checkRights($this->conf ['rights'] ['create'] ['event'] ['enableAllFields'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['startdate'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['enddate'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['starttime'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['endtime'] ??  [])) {
            return true;
        }
        return false;
    }

    public function isAllowedToCreateEventTitle()
    {
        return $this->isAllowedToCreateEventFields('title.');
    }

    public function isAllowedToCreateEventOrganizer()
    {
        return $this->isAllowedToCreateEventFields('organizer.');
    }

    public function isAllowedToCreateEventLocation()
    {
        return $this->isAllowedToCreateEventFields('location.');
    }

    public function isAllowedToCreateEventDescription()
    {
        return $this->isAllowedToCreateEventFields('description.');
    }

    public function isAllowedToCreateEventTeaser()
    {
        return $this->isAllowedToCreateEventFields('teaser.');
    }

    public function isAllowedToCreateEventRecurring()
    {
        return $this->isAllowedToCreateEventFields('recurring.');
    }

    public function isAllowedToCreateEventNotify()
    {
        return $this->isAllowedToCreateEventFields('notify.');
    }

    public function isAllowedToCreateEventException()
    {
        return $this->isAllowedToCreateEventFields('exception.');
    }

    public function isAllowedToCreateEventShared()
    {
        return $this->isAllowedToCreateEventFields('shared.');
    }

    public function isAllowedToEditEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['event'] ??  []);
    }

    public function isPublicAllowedToEditEvents()
    {
        return isset($this->conf ['rights'] ['edit'] ['event'] ['public']) && $this->conf ['rights'] ['edit'] ['event'] ['public'] == 1;
    }

    public function isAllowedToEditStartedEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['event'] ['startedEvents'] ??  []);
    }

    public function isAllowedToEditOnlyOwnEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['event'] ['onlyOwnEvents'] ??  []);
    }

    public function isAllowedToEditEventHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'hidden.');
    }

    public function isAllowedToEditEventCalendar()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'calendar_id.');
    }

    public function isAllowedToEditEventCategory()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'category.');
    }

    public function isAllowedToEditEventDateTime()
    {
        if ($this->checkRights($this->conf ['rights'] ['create'] ['event'] ['enableAllFields'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['startdate'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['enddate'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['starttime'] ??  []) || $this->checkRights($this->conf ['rights'] ['create'] ['event'] ['fields'] ['endtime'] ??  [])) {
            return true;
        }
        return false;
    }

    public function isAllowedToEditEventTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'title.');
    }

    public function isAllowedToEditEventOrganizer()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'organizer.');
    }

    public function isAllowedToEditEventLocation()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'location.');
    }

    public function isAllowedToEditEventDescription()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'description.');
    }

    public function isAllowedToEditEventTeaser()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'teaser.');
    }

    public function isAllowedToEditEventRecurring()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'recurring.');
    }

    public function isAllowedToEditEventNotify()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'notify.');
    }

    public function isAllowedToEditEventException()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'event', 'exception.');
    }

    public function isAllowedToDeleteEvents()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['event'] ??  []);
    }

    public function isAllowedToDeleteOnlyOwnEvents()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['event'] ['onlyOwnEvents'] ??  []);
    }

    public function isAllowedToDeleteStartedEvents()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['event'] ['startedEvents'] ??  []);
    }

    public function isAllowedToCreateExceptionEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['exceptionEvent'] ??  []);
    }

    public function isAllowedToEditExceptionEvent()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['exceptionEvent'] ??  []);
    }

    public function isAllowedToDeleteExceptionEvents()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['exceptionEvent'] ??  []);
    }

    public function isAllowedToCreateLocations()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['location'] ??  []);
    }

    private function isTrueForEnableFieldsOrObjectField($createEditDelete, $objectType, $field)
    {
        if ($this->checkRights($this->conf ['rights'] [$createEditDelete] [$objectType] ['enableAllFields'] ??  []) || $this->checkRights($this->conf ['rights'] [$createEditDelete] [$objectType] ['fields'] [$field] ??  [])) {
            return true;
        }
        return false;
    }

    public function isAllowedToCreateLocationHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'hidden.');
    }

    public function isAllowedToCreateLocationTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'title.');
    }

    public function isAllowedToCreateLocationDescription()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'description.');
    }

    public function isAllowedToCreateLocationName()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'name.');
    }

    public function isAllowedToCreateLocationStreet()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'street.');
    }

    public function isAllowedToCreateLocationZip()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'zip.');
    }

    public function isAllowedToCreateLocationCity()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'city.');
    }

    public function isAllowedToCreateLocationCountryZone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'countryZone.');
    }

    public function isAllowedToCreateLocationCountry()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'country.');
    }

    public function isAllowedToCreateLocationPhone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'phone.');
    }

    public function isAllowedToCreateLocationEmail()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'email.');
    }

    public function isAllowedToCreateLocationImage()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'image.');
    }

    public function isAllowedToCreateLocationLink()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'location', 'link.');
    }

    public function isAllowedToEditLocation()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['location'] ??  []);
    }

    public function isAllowedToEditLocationHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'hidden.');
    }

    public function isAllowedToEditLocationTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'title.');
    }

    public function isAllowedToEditLocationDescription()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'description.');
    }

    public function isAllowedToEditLocationName()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'name.');
    }

    public function isAllowedToEditLocationStreet()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'street.');
    }

    public function isAllowedToEditLocationZip()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'zip.');
    }

    public function isAllowedToEditLocationCity()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'city.');
    }

    public function isAllowedToEditLocationCountryZone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'countryZone.');
    }

    public function isAllowedToEditLocationCountry()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'country.');
    }

    public function isAllowedToEditLocationPhone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'phone.');
    }

    public function isAllowedToEditLocationEmail()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'email.');
    }

    public function isAllowedToEditLocationLogo()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'logo.');
    }

    public function isAllowedToEditLocationHomepage()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'location', 'homepage.');
    }

    public function isAllowedToDeleteLocation()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['location'] ??  []);
    }

    // TODO: Remove for version 1.4.0, but keep this function for backwards compatibility until than
    public function isAllowedToDeleteLocations()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['location'] ??  []);
    }

    public function isAllowedToEditOnlyOwnLocation()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['location'] ['onlyOwnLocation'] ??  []);
    }

    public function isAllowedToDeleteOnlyOwnLocation()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['location'] ['onlyOwnLocation'] ??  []);
    }

    public function isAllowedToCreateOrganizer()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['organizer'] ??  []);
    }

    public function isAllowedToCreateOrganizerHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'hidden.');
    }

    public function isAllowedToCreateOrganizerTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'title.');
    }

    public function isAllowedToCreateOrganizerDescription()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'description.');
    }

    public function isAllowedToCreateOrganizerName()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'name.');
    }

    public function isAllowedToCreateOrganizerStreet()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'street.');
    }

    public function isAllowedToCreateOrganizerZip()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'zip.');
    }

    public function isAllowedToCreateOrganizerCity()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'city.');
    }

    public function isAllowedToCreateOrganizerPhone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'phone.');
    }

    public function isAllowedToCreateOrganizerEmail()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'email.');
    }

    public function isAllowedToCreateOrganizerImage()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'image.');
    }

    public function isAllowedToCreateOrganizerLink()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'organizer', 'link.');
    }

    public function isAllowedToEditOrganizer()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['organizer'] ??  []);
    }

    public function isAllowedToEditOrganizerHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'hidden.');
    }

    public function isAllowedToEditOrganizerTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'title.');
    }

    public function isAllowedToEditOrganizerDescription()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'description.');
    }

    public function isAllowedToEditOrganizerName()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'name.');
    }

    public function isAllowedToEditOrganizerStreet()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'street.');
    }

    public function isAllowedToEditOrganizerZip()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'zip.');
    }

    public function isAllowedToEditOrganizerCity()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'city.');
    }

    public function isAllowedToEditOrganizerPhone()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'phone.');
    }

    public function isAllowedToEditOrganizerEmail()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'email.');
    }

    public function isAllowedToEditOrganizerLogo()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'logo.');
    }

    public function isAllowedToEditOrganizerHomepage()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'organizer', 'homepage.');
    }

    public function isAllowedToDeleteOrganizer()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['organizer'] ??  []);
    }

    public function isAllowedToEditOnlyOwnOrganizer()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['organizer'] ['onlyOwnOrganizer'] ??  []);
    }

    public function isAllowedToDeleteOnlyOwnOrganizer()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['organizer'] ['onlyOwnOrganizer'] ??  []);
    }

    public function isAllowedToCreateCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['calendar'] ??  []);
    }

    public function isAllowedToCreateCalendarHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'hidden.');
    }

    public function isAllowedToCreateCalendarTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'title.');
    }

    public function isAllowedToCreateCalendarOwner()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'owner.');
    }

    public function isAllowedToCreateCalendarActivateFreeAndBusy()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'activateFreeAndBusy.');
    }

    public function isAllowedToCreateCalendarFreeAndBusyUser()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'freeAndBusyUser.');
    }

    public function isAllowedToCreateCalendarType()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'calendar', 'type.');
    }

    public function isAllowedToEditCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['calendar'] ??  []);
    }

    public function isAllowedToEditOnlyOwnCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['calendar'] ['onlyOwnCalendar'] ??  []);
    }

    public function isAllowedToEditPublicCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['calendar'] ['publicCalendar'] ??  []);
    }

    public function isAllowedToEditCalendarHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'hidden.');
    }

    public function isAllowedToEditCalendarType()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'type.');
    }

    public function isAllowedToEditCalendarTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'title.');
    }

    public function isAllowedToEditCalendarOwner()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'owner.');
    }

    public function isAllowedToEditCalendarActivateFreeAndBusy()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'activateFreeAndBusy.');
    }

    public function isAllowedToEditCalendarFreeAndBusyUser()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'calendar', 'freeAndBusyUser.');
    }

    public function isAllowedToDeleteCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['calendar'] ??  []);
    }

    public function isAllowedToDeleteOnlyOwnCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['calendar'] ['onlyOwnCalendar'] ??  []);
    }

    public function isAllowedToDeletePublicCalendar()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['calendar'] ['publicCalendar'] ??  []);
    }

    public function isAllowedToCreateCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['category'] ??  []);
    }

    public function isAllowedToCreateCategoryHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'hidden.');
    }

    public function isAllowedToCreateCategoryTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'title.');
    }

    public function isAllowedToCreateCategoryHeaderStyle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'headerstyle.');
    }

    public function isAllowedToCreateCategoryBodyStyle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'bodystyle.');
    }

    public function isAllowedToCreateCategoryCalendar()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'calendar.');
    }

    public function isAllowedToCreateCategoryParent()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'parent.');
    }

    public function isAllowedToCreateCategorySharedUser()
    {
        return $this->isTrueForEnableFieldsOrObjectField('create', 'category', 'sharedUser.');
    }

    public function isAllowedToCreateGeneralCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['category'] ['generalCategory'] ??  []);
    }

    public function isAllowedToCreatePublicCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['create'] ['category'] ['publicCategory'] ??  []);
    }

    public function isAllowedToEditCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['category'] ??  []);
    }

    public function isAllowedToEditOnlyOwnCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['category'] ['onlyOwnCategory'] ??  []);
    }

    public function isAllowedToEditGeneralCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['category'] ['generalCategory'] ??  []);
    }

    public function isAllowedToEditPublicCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['edit'] ['category'] ['publicCategory'] ??  []);
    }

    public function isAllowedToEditCategoryHidden()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'hidden.');
    }

    public function isAllowedToEditCategoryTitle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'title.');
    }

    public function isAllowedToEditCategoryHeaderstyle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'headerstyle.');
    }

    public function isAllowedToEditCategoryBodystyle()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'bodystyle.');
    }

    public function isAllowedToEditCategoryCalendar()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'calendar.');
    }

    public function isAllowedToEditCategoryParent()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'parent.');
    }

    public function isAllowedToEditCategorySharedUser()
    {
        return $this->isTrueForEnableFieldsOrObjectField('edit', 'category', 'sharedUser.');
    }

    public function isAllowedToDeleteCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['category'] ??  []);
    }

    public function isAllowedToDeleteOnlyOwnCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['category'] ['onlyOwnCategory'] ??  []);
    }

    public function isAllowedToDeleteGeneralCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['category'] ['generalCategory'] ??  []);
    }

    public function isAllowedToDeletePublicCategory()
    {
        return $this->checkRights($this->conf ['rights'] ['delete'] ['category'] ['publicCategory'] ??  []);
    }

    public function isAllowedToConfigure()
    {
        return $this->isLoggedIn() && $this->isViewEnabled('admin') && ($this->isCalAdmin() || $this->isAllowedToCreateCalendar() || $this->isAllowedToEditCalendar() || $this->isAllowedToDeleteCalendar() || $this->isAllowedToCreateCategory() || $this->isAllowedToEditCategory() || $this->isAllowedToDeleteCategory() || $this->isAllowedTo('create', 'location') || $this->isAllowedTo('edit', 'location') || $this->isAllowedTo('delete', 'location') || $this->isAllowedToCreateOrganizer() || $this->isAllowedToEditOrganizer() || $this->isAllowedToDeleteOrganizer());
    }

    public function isAllowedTo($type, $object, $field = '')
    {
        $field = strtolower((string)$field);
        if ($field == '') {
            return $this->checkRights($this->conf ['rights'] [$type . ''] [$object . ''] ??  []);
        }
        if ($field == 'teaser' && ! $this->confArr ['useTeaser']) {
            return false;
        }

        if (($this->isTrue3('rights', $type, 'public') && $this->isTrue6('rights', $type, $object, 'fields', $field, 'public')) || $this->checkRights($this->conf ['rights'] [$type . ''] [$object . ''] ['enableAllFields'] ?? false) || $this->checkRights($this->conf ['rights'] [$type . ''] [$object . ''] ['fields'] [$field . ''] ??  [])) {
            return true;
        }

        return false;
    }

    public function checkRights($category)
    {
        if ($this->isCalAdmin()) {
            return true;
        }
        if ($this->isLoggedIn()) {
            $users = explode(',', (string)($category ['user'] ?? ''));
            $groups = explode(',', (string)($category ['group'] ?? ''));

            if (array_search($this->getUserId(), $users) !== false) {
                return true;
            }
            $userGroups = $this->getUserGroups();
            foreach ($groups as $key => $group) {
                if (array_search(ltrim($group), $userGroups) !== false) {
                    return true;
                }
            }
        }
        if (isset($category ['public']) && $category ['public'] == 1) {
            return true;
        }
        return false;
    }

    /* @todo Is there a way to check for allowed views on other pages that are specified by TS? */
    public function isViewEnabled($view)
    {
        if (in_array($view, $this->conf ['view'] ['allowedViewsToLinkTo'])) {
            return true;
        }
        return false;
    }

    /**
     * Sets the default pages for saving calendars, events, etc.
     * If the Typoscript
     * is not set and there's only one page in the pidList, then we can set this
     * page be default.
     */
    public function setDefaultSaveToPage()
    {
        $pagesArray = explode(',', (string)($this->conf ['pidList'] ?? ''));

        /* If there's only one page in pidList */
        if (count($pagesArray) == 1) {
            $pid = $pagesArray [0];

            /* If a saveTo page does not have a value set, set a default */
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['calendar'] ['saveCalendarToPid'], $pid);
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['category'] ['saveCategoryToPid'], $pid);
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['event'] ['saveEventToPid'], $pid);
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['exceptionEvent'] ['saveExceptionEventToPid'], $pid);
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['location'] ['saveLocationToPid'], $pid);
            $this->setPidIfEmpty($this->conf ['rights'] ['create'] ['organizer'] ['saveOrganizerToPid'], $pid);
        }
    }

    /**
     * Sets a conf value if it is currently empty.
     * Helper function for setDefaultSaveToPage().
     *
     * @param mixed $conf The conf value to be set.
     * @param mixed $value The value to set.
     */
    public function setPidIfEmpty(&$conf, $value)
    {
        if (! $conf) {
            $conf = $value;
        }
    }
}
