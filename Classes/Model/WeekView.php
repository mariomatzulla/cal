<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Utility\CalculateDateTime;

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
 *
 * @author Mario Matzulla <mario@matzullas.de>
 */
class WeekView extends TimeView
{
    /**
     * @var int
     */
    protected $week;

    /**
     * @var array
     */
    protected $days;

    protected $alldays;

    protected $dayNums;

    protected $weekStart;

    protected $weekEnd;

    /**
     * @var int
     */
    protected $rowspan = 0;

    protected $content = false;

    protected $weekHasEvent = false;

    protected $dayHasEvent = [

        false,
        false,
        false,
        false,
        false,
        false,
        false,
    ];

    protected $currentDayIndex = 9;

    protected $initialized = false;

    /**
     * Constructor.
     * @param int $week
     * @param int $year
     * @param int $parentMonth
     */
    public function __construct(int $week, int $year, int $parentMonth = - 1)
    {
        parent::__construct();
        $this->week = (int)$week;
        $this->setYear((int)$year);
        $this->setParentMonth($parentMonth);
        $this->generateDays();
    }

    public function getWeek(): int
    {
        return $this->week;
    }

    /**
     * @return bool
     */
    public function isWeekHasEvent()
    {
        return $this->weekHasEvent;
    }

    /**
     * @return bool
     */
    public function isDayHasEvent()
    {
        return $this->dayHasEvent;
    }

    public function getCurrentDayIndex()
    {
        return $this->currentDayIndex;
    }

    /**
     * @return string
     */
    public function getWeekStart()
    {
        return $this->weekStart;
    }

    /**
     * @return string
     */
    public function getWeekEnd()
    {
        return $this->weekEnd;
    }

    private function generateDays()
    {
        $weekStart = CalculateDateTime::getDayByWeek(
            $this->getYear(),
            $this->week,
            DATE_CALC_BEGIN_WEEKDAY,
        );
        $this->weekStart = $weekStart->format('Ymd');
        $this->setMonth($weekStart->getMonth());
        if ($this->getParentMonth() < 0) {
            $this->setParentMonth($this->getMonth());
        }

        $this->days = [];
        $this->alldays = [];
        $this->dayNums = [];
        for ($i = 0; $i < 7; $i++) {
            $this->dayNums [$i] = $weekStart->getDay();
            $weekStartFormated = $weekStart->format('Ymd');
            $this->days [$weekStartFormated] = new DayView($this->dayNums [$i], $weekStart->getMonth(), $weekStart->getYear(), (int)$this->getParentMonth());
            $this->alldays [$weekStartFormated] = [];
            $weekStart->modify('+1 day');
        }
        $this->weekEnd = $weekStart->format('Ymd');
    }

    /**
     * @param Event $event
     * @return mixed|void
     */
    public function addEvent($event)
    {

        $eventStart = new CalendarDateTime();
        $eventStart->copy($event->getStart());
        $eventStartFormatted = $eventStart->format('Ymd');
        $eventStartYear = $eventStart->getYear();
        $eventEndFormatted = $event->getEnd()->format('Ymd');
        $eventEndYear = $event->getEnd()->getYear();
        $eventStartWeek = $event->getStart()->getWeekOfYear();
        $eventEndWeek = $event->getEnd()->getWeekOfYear();
        if (($eventStartWeek === 52 || $eventStartWeek === 53) && $event->getStart()->getMonth() === 1) {
            $eventStartYear--;
        }
        if (($eventEndWeek === 52 || $eventEndWeek === 53) && $event->getEnd()->getMonth() === 1) {
            $eventEndYear--;
        }
        if ($eventStartWeek === 1 && $event->getStart()->getMonth() === 12) {
            $eventStartYear++;
        }
        if ($eventEndWeek === 1 && $event->getEnd()->getMonth() === 12) {
            $eventEndYear++;
        }
        if ($event->isAllday() || $eventStartFormatted != $eventEndFormatted) {
            $eventYearEnd = $event->getEnd()->getYear();
            if ($event->getEnd()->getMonth() === 12 && $event->getEnd()->getWeekOfYear() === 1) {
                $eventYearEnd++;
            }

            if (! ($eventStartYear === $this->getYear() && $eventStart->getWeekOfYear() === $this->week) && $eventStart->getYear() . sprintf('%02d', $eventStart->getWeekOfYear()) < $this->getYear() . sprintf('%02d', $this->week) && $eventYearEnd . sprintf('%02d', $event->getEnd()->getWeekOfYear()) >= $this->getYear() . sprintf('%02d', $this->week)) {
                do {
                    $eventStart->addSeconds(86400);
                    $eventStartYear = $eventStart->getYear();
                    $eventWeek = $eventStart->getWeekOfYear();
                    if ($eventStart->getMonth() == 1 && $eventWeek > 50) {
                        $eventStartYear--;
                    }
                } while ($eventStartYear . sprintf('%02d', $eventWeek) < $this->getYear() . sprintf('%02d', $this->week));
                $eventStartFormatted = $eventStart->format('Ymd');
            }
            if ($eventStartYear == $this->getYear() && $eventStart->getWeekOfYear() == $this->week) {
                $this->alldays [$eventStartFormatted] [] = $event;
                $this->weekHasEvent = true;
                $first = true;
                do {
                    $this->dayHasEvent [$eventStart->getDayOfWeek()] = true;
                    if (is_object($this->days [$eventStart->format('Ymd')] ?? null)) {
                        $this->days [$eventStart->format('Ymd')]->setHasAlldayEvents(true);
                        if ($first) {
                            $this->days [$eventStart->format('Ymd')]->addEvent($event);
                            $first = false;
                        }
                    }
                    $eventStart->addSeconds(86400);
                    $eventStartYear = $eventStart->getYear();
                    $eventWeek = $eventStart->getWeekOfYear();
                    if ($eventStart->getMonth() === 1 && $eventWeek > 50) {
                        $eventStartYear--;
                    }
                } while ($eventStart->format('Ymd') <= $eventEndFormatted && $eventStartYear . sprintf('%02d', $eventStart->getWeekOfYear()) <= $this->getYear() . sprintf('%02d', $this->week));
            }
        } else {
            do {
                if ($eventStartYear == $this->getYear() && $eventStartWeek == $this->week) {
                    $this->dayHasEvent [$eventStart->getDayOfWeek()] = true;
                    if (is_object($this->days [$eventStart->format('Ymd')] ?? null)) {
                        $this->days [$eventStart->format('Ymd')]->addEvent($event);
                    }
                    $this->weekHasEvent = true;
                }
                $eventStart->addSeconds(86400);
                $eventStartYear = $eventStart->getYear();
                $eventWeek = $eventStart->getWeekOfYear();
                if ($eventStart->getMonth() === 1 && $eventWeek > 50) {
                    $eventStartYear--;
                }
                $eventStartWeek = $eventStart->getWeekOfYear();
            } while ($eventStart->format('Ymd') <= $eventEndFormatted && $eventStartWeek <= $this->week);
        }
    }

    public function getDays()
    {
        return $this->days;
    }

    public function getAlldays()
    {
        return $this->alldays;
    }

    /**
     * @param CalendarDateTime $dateObject
     * @return mixed|void
     */
    public function setSelected($dateObject)
    {

        if ($dateObject->getWeekOfYear() === $this->week && $dateObject->getYear() === $this->getYear()) {
            $this->selected = true;

            $day = $this->days [$dateObject->format('Ymd')] ?? null;
            if (is_object($day)) {
                $day->setSelected($dateObject);
            }
        }
    }

    /**
     * @param CalendarDateTime $dateObject
     * @return mixed|void
     */
    public function setCurrent($dateObject)
    {

        if ($dateObject->getWeekOfYear() === $this->week && $dateObject->getYear() === $this->getYear()) {
            $this->current = true;

            $day = $this->days [$dateObject->format('Ymd')] ?? false;
            if (is_object($day)) {
                $this->currentDayIndex = $dateObject->getDayOfWeek();
                if (DATE_CALC_BEGIN_WEEKDAY === 1) {
                    $this->currentDayIndex--;
                    if ($this->currentDayIndex === - 1) {
                        $this->currentDayIndex = 6;
                    }
                }
                $day->setCurrent($dateObject);
            }
        }
    }

    /**
     * @return bool
     */
    public function hasEvents(): bool
    {

        return $this->weekHasEvent;
    }

    /**
     * @return bool
     */
    public function isHasEvents(): bool
    {

        return $this->hasEvents();
    }
}
