<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

use MarioMatzulla\Cal\Utility\WeekStart;
use MarioMatzulla\Cal\Domain\Model\Event;

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
 * Base model for the day.
 */
class DayView extends \MarioMatzulla\Cal\Model\TimeView
{
    private $hasAlldayEvents = false;

    private $Ymd;

    private $time;

    private $events = [];

    /**
       * Constructor.
       * @param $day
       * @param $month
       * @param $year
       * @param int $parentMonth
       */
    public function __construct($day, $month, $year, $parentMonth = - 1)
    {
        parent::__construct();
        $this->setDay((int)$day);
        $this->setMonth((int)$month);
        $this->setYear((int)$year);
        $date = new CalendarDateTime();
        $date = $date->setDate($this->getYear(), $this->getMonth(), (int)$this->getDay());
        $date->setTime(0, 0);
        $this->setWeekdayNumber(WeekStart::indexOf($date->getDayOfWeek()));
        $this->setYmd($date->format('Ymd'));
        $this->time = $date;
        if ($parentMonth >= 0) {
            $this->setParentMonth((int)$parentMonth);
        } else {
            $this->setParentMonth($this->getMonth());
        }
    }

    /**
     * @param Event $event
     */
    public function addEvent($event)
    {
        $this->events [$event->getStart()->format('Hi')] [$event->getUid()] = &$event;
    }

    /**
     * @param $template
     * @param $sims
     * @param $rems
     * @param $wrapped
     * @param $view
     */
    public function getDayClasses()
    {

        $classes = 'day weekday' . $this->getTime()->getDayOfWeek();
        if ($this->current) {
            $classes .= ' currentDay';
        }
        if ($this->selected) {
            $classes .= ' selectedDay';
        }
        if (! empty($this->events) || $this->hasAlldayEvents) {
            $classes .= ' withEventDay';
        }
        if ((int)($this->getParentMonth()) != (int)($this->getMonth())) {
            $classes .= ' monthOff';
        }

        return $classes;
    }

    /**
     * @param $dateObject
     */
    public function setCurrent($dateObject)
    {

        if ($this->getDay() == $dateObject->getDay() && $this->getMonth() == $dateObject->getMonth() && $this->getYear() == $dateObject->getYear()) {
            $this->current = true;
        }
    }

    /**
     * @param $dateObject
     */
    public function setSelected($dateObject)
    {

        if ($this->getDay() == $dateObject->getDay() && $this->getMonth() == $dateObject->getMonth() && $this->getYear() == $dateObject->getYear()) {
            $this->selected = true;
        }
    }

    /**
     * @return CalendarDateTime
     */
    public function getTime(): CalendarDateTime
    {

        return $this->time;
    }

    /**
     * @return string
     */
    public function getYmd(): string
    {

        return $this->Ymd;
    }

    /**
     * @param string $ymd
     */
    public function setYmd($ymd)
    {

        $this->Ymd = $ymd;
    }

    /**
     * @return array
     */
    public function getEvents(): array
    {

        return $this->events;
    }

    /**
     * @param $events
     */
    public function setEvents($events)
    {

        $this->events = $events;
    }

    /**
     * @return bool
     */
    public function getHasAlldayEvents(): bool
    {

        return $this->hasAlldayEvents;
    }

    /**
     * @param $hasAlldayEvents
     */
    public function setHasAlldayEvents($hasAlldayEvents)
    {

        $this->hasAlldayEvents = $hasAlldayEvents;
    }

    /**
     * @return bool
     */
    public function hasEvents(): bool
    {

        return ! empty($this->getEvents()) || $this->getHasAlldayEvents();
    }

    /**
     * @return bool
     */
    public function isHasEvents(): bool
    {

        return $this->hasEvents();
    }
}
