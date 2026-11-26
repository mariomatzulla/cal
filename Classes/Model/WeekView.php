<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Utility\CalculateDateTime;
use MarioMatzulla\Cal\Utility\WeekStart;

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
            WeekStart::get(),
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
        $thisWeek = sprintf('%04d%02d', $this->getYear(), $this->week);
        $eventStart = new CalendarDateTime();
        $eventStart->copy($event->getStart());
        $eventEndFormatted = $event->getEnd()->format('Ymd');

        if (! $event->isAllday() && $eventStart->format('Ymd') === $eventEndFormatted) {
            if (self::getWeekKey($eventStart) === $thisWeek) {
                $this->dayHasEvent [$eventStart->getDayOfWeek()] = true;
                if (is_object($this->days [$eventStart->format('Ymd')] ?? null)) {
                    $this->days [$eventStart->format('Ymd')]->addEvent($event);
                }
                $this->weekHasEvent = true;
            }
            return;
        }

        // An event starting before this week is shown from the first day of the week
        if (self::getWeekKey($eventStart) < $thisWeek) {
            if (self::getWeekKey($event->getEnd()) < $thisWeek) {
                return;
            }
            do {
                $eventStart->addSeconds(86400);
            } while (self::getWeekKey($eventStart) < $thisWeek);
        }
        if (self::getWeekKey($eventStart) !== $thisWeek) {
            return;
        }

        $this->alldays [$eventStart->format('Ymd')] [] = $event;
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
        } while ($eventStart->format('Ymd') <= $eventEndFormatted && self::getWeekKey($eventStart) === $thisWeek);
    }

    /**
     * Year and number of the week of a date (YYYYWW), e.g. 202653 for 2027-01-01
     * and 202601 for 2025-12-31.
     */
    private static function getWeekKey(CalendarDateTime $date): string
    {
        $week = $date->getWeekOfYear();
        $year = $date->getYear();
        if ($date->getMonth() === 1 && $week > 50) {
            $year--;
        } elseif ($date->getMonth() === 12 && $week === 1) {
            $year++;
        }
        return sprintf('%04d%02d', $year, $week);
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
                $this->currentDayIndex = WeekStart::indexOf($dateObject->getDayOfWeek());
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
