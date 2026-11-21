<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

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
use MarioMatzulla\Cal\Model\CalendarDateTime;

/**
 * A concrete model for the calendar.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class EventRecModel
{
    protected $parentEvent;

    protected $startdate;

    protected $starttime;

    protected $enddate;

    protected $endtime;

    public $matrixValue;

    public function __construct(Event $event, float $start, float $end)
    {
        $this->parentEvent = $event;
        $startObject = CalendarDateTime::generateByYmdHis(sprintf('%.0f', $start));
        $this->startdate = new \DateTime();
        $this->startdate->setDate($startObject->getYear(), $startObject->getMonth(), $startObject->getDay());
        $this->starttime = new \DateTime();
        $this->starttime->setDate($startObject->getYear(), $startObject->getMonth(), $startObject->getDay());
        $this->starttime->setTime($startObject->getHour(), $startObject->getMinute(), $startObject->getSecond(), 0);
        $endObject = CalendarDateTime::generateByYmdHis(sprintf('%.0f', $end));
        $this->enddate = new \DateTime();
        $this->enddate->setDate($endObject->getYear(), $endObject->getMonth(), $endObject->getDay());
        $this->endtime = new \DateTime();
        $this->endtime->setDate($endObject->getYear(), $endObject->getMonth(), $endObject->getDay());
        $this->endtime->setTime($endObject->getHour(), $endObject->getMinute(), $endObject->getSecond(), 0);
    }

    public function getStart()
    {
        $start = new \MarioMatzulla\Cal\Model\CalendarDateTime();
        $start->setDate((int)$this->getStartDate()->format('Y'), (int)$this->getStartDate()->format('m'), (int)$this->getStartDate()->format('d'));
        if ($this->getStartTime() !== null) {
            $start->setTime((int)$this->getStartTime()->format('H'), (int)$this->getStartTime()->format('i'));
        } else {
            $start->setTime(0, 0);
        }
        return $start;
    }

    public function getEnd()
    {
        $end = new \MarioMatzulla\Cal\Model\CalendarDateTime();
        $end->setDate((int)$this->getEndDate()->format('Y'), (int)$this->getEndDate()->format('m'), (int)$this->getEndDate()->format('d'));
        if ($this->getEndTime() !== null) {
            $end->setTime((int)$this->getEndTime()->format('H'), (int)$this->getEndTime()->format('i'));
        } else {
            $end->setTime(0, 0);
        }
        // An end time of 00:00 on the start day means midnight at the end of the day
        if (!$this->isAllday() && $this->getStartDate() !== null && $end->format('YmdHis') === $this->getStart()->format('Ymd') . '000000' && $this->getStart()->format('His') !== '000000') {
            $end->modify('+1 day');
        }
        return $end;
    }

    /**
     * End for iCalendar (DTEND): for all-day events the day after the last day (DTEND is exclusive),
     * otherwise the end (an end at 00:00 on the start day is midnight, see getEnd()).
     */
    public function getIcsEnd(): \MarioMatzulla\Cal\Model\CalendarDateTime
    {
        $end = $this->getEnd();
        if ($this->isAllday()) {
            $end->setTime(0, 0);
            $end->modify('+1 day');
        }
        return $end;
    }

    /**
     * Gets the location of the event.
     * Location does not exist in the default
     * model, only in calexampl3.
     *
     * @return string location.
     */
    public function getLocation()
    {
        return $this->parentEvent->getLocation();
    }

    public function getOrganizer()
    {
        return $this->parentEvent->getOrganizer();
    }

    public function getLocationId()
    {
        return $this->parentEvent->getLocationId();
    }

    public function getOrganizerId()
    {
        return $this->parentEvent->getOrganizerId();
    }

    public function getLocationObject()
    {
        return $this->parentEvent->getLocationObject();
    }

    public function getOrganizerObject()
    {
        return $this->parentEvent->getOrganizerObject();
    }

    /**
     * Gets the teaser of the event.
     *
     *
     * @return string teaser.
     */
    public function getCaldavData()
    {
        return $this->parentEvent->getCaldavData();
    }

    public function getDescription()
    {
        return $this->parentEvent->getDescription();
    }

    public function getTeaser()
    {
        return $this->parentEvent->getTeaser();
    }

    /**
     * Returns the headerstyle name
     */
    public function getHeaderStyle()
    {
        return $this->parentEvent->getHeaderStyle();
    }

    /**
     * Returns the bodystyle name
     */
    public function getBodyStyle()
    {
        return $this->parentEvent->getBodyStyle();
    }

    /**
     * Gets the createUserId of the event.
     *
     * @return string create user id.
     */
    public function getCreateUserId()
    {
        return $this->parentEvent->getCreateUserId();
    }

    public function getTimezone()
    {
        return $this->parentEvent->getTimezone();
    }

    public function getTitle()
    {
        return $this->parentEvent->getTitle();
    }

    public function getStartdate()
    {
        return $this->startdate;
    }

    public function getStarttime()
    {
        return $this->starttime;
    }

    public function getEnddate()
    {
        return $this->enddate;
    }

    public function getEndtime()
    {
        return $this->endtime;
    }

    public function getAttendees()
    {
        return $this->parentEvent->getAttendees();
    }

    public function getUid()
    {
        return $this->parentEvent->getUid();
    }

    public function isAllday()
    {
        return $this->parentEvent->isAllday();
    }

    public function getEventOwner()
    {
        return $this->parentEvent->getEventOwner();
    }

    public function getCalendarUid()
    {
        return $this->parentEvent->getCalendarUid();
    }

    public function getModelType()
    {
        return $this->parentEvent->getModelType();
    }

    public function getEventType()
    {
        return $this->parentEvent->getEventType();
    }

    public function getCnt()
    {
        return $this->parentEvent->getCnt();
    }

    public function getCategories()
    {
        return $this->parentEvent->getCategories();
    }

    public function getColor(): string
    {
        return $this->parentEvent->getColor();
    }

    public function getColorStyle(): string
    {
        return $this->parentEvent->getColorStyle();
    }

    public function getUntil()
    {
        return $this->parentEvent->getUntil();
    }

    public function getImage()
    {
        return $this->parentEvent->getImage();
    }

    public function getCalendarObject()
    {
        return $this->parentEvent->getCalendarObject();
    }

    public function getFreq()
    {
        return $this->parentEvent->getFreq();
    }
}
