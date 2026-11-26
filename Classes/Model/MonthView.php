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
 * Base model for the month.
 */
class MonthView extends TimeView
{
    /**
     * @var array<WeekView>
     */
    protected $weeks;

    protected $maxWeeksInYear = 52;

    protected $monthStartWeekdayNum;

    protected $monthLength;

    /**
     * @return array<WeekView>
     */
    public function getWeeks()
    {
        return $this->weeks;
    }

    /**
     * Constructor.
     *
     * @param mixed $month
     * @param mixed $year
     */
    public function __construct($month, $year)
    {
        parent::__construct();
        $this->setMonth((int)$month);
        $this->setYear((int)$year);
        $this->generateWeeks();
    }

    private function generateWeeks()
    {
        $date = new CalendarDateTime();
        $date = $date->setDate($this->getYear(), $this->getMonth(), 1);
        $date = $date->setTime(0, 0, 0, 0);

        $this->monthStartWeekdayNum = $date->getDayOfWeek(); // Weekday index
        $this->monthLength = CalculateDateTime::getDaysInMonth($this->getMonth(), $this->getYear()); // days in month

        $newDate = CalculateDateTime::calculateStartOfWeek(clone $date);

        $monthEnd = CalculateDateTime::calculateEndOfMonth(clone $date);
        $weekEnd = $monthEnd->getWeekOfYear();
        $this->weeks = [ ];
        $weekNumber = $newDate->getWeekOfYear();
        if ($weekEnd == 1 && $this->getMonth() == 12) {
            do {
                if ($weekNumber == $weekEnd) {
                    $this->weeks [($newDate->getYear() + 1) . '_' . $weekNumber] = new WeekView($weekNumber, $newDate->getYear() + 1, $this->getMonth());
                } else {
                    $this->weeks [$newDate->getYear() . '_' . $weekNumber] = new WeekView($weekNumber, $newDate->getYear(), $this->getMonth());
                }
                $newDate->modify('+7 days');
                $weekNumber = $newDate->getWeekOfYear();
                $weekNumberTmp = $weekNumber;
                if ($weekNumber != $weekEnd) {
                    $weekNumberTmp = 0;
                }
            } while ($weekNumberTmp <= $weekEnd && $newDate->getYear() === $this->getYear());
        } elseif ($this->getMonth() == 1) {
            do {
                if ($weekNumber > 6) {
                    $this->weeks [$newDate->getYear() . '_' . $weekNumber] = new WeekView($weekNumber, $newDate->getYear(), $this->getMonth());
                } else {
                    $this->weeks [$this->getYear() . '_' . $weekNumber] = new WeekView($weekNumber, $this->getYear(), $this->getMonth());
                }
                $newDate->modify('+7 days');
                $weekNumber = $newDate->getWeekOfYear();
            } while ($weekNumber <= $weekEnd && $newDate->getYear() == $this->getYear());
        } else {
            do {
                $this->weeks [$this->getYear() . '_' . $weekNumber] = new WeekView($weekNumber, $newDate->getYear(), $this->getMonth());
                $newDate->modify('+7 days');
                $weekNumber = $newDate->getWeekOfYear();
            } while ($weekNumber <= $weekEnd && $newDate->getYear() === $this->getYear());
        }
        $this->maxWeeksInYear = max($this->maxWeeksInYear, $weekNumber);
    }

    /**
     * @param Event $event
     */
    public function addEvent($event)
    {
        $eventStartWeek = $event->getStart()->getWeekOfYear();
        $eventEndWeek = $event->getEnd()->getWeekOfYear();
        $eventStartYear = $event->getStart()->getYear();
        $eventEndYear = $event->getEnd()->getYear();
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
        do {
            if (isset($this->weeks [$eventStartYear . '_' . $eventStartWeek])) {
                $this->weeks [$eventStartYear . '_' . $eventStartWeek]->addEvent($event);
            }
            $eventStartWeek++;
            if ($eventStartWeek > $this->maxWeeksInYear) {
                $eventStartWeek = 1;
                $eventStartYear++;
            }
        } while (! (($eventStartYear === $eventEndYear && $eventStartWeek > $eventEndWeek) || ($eventStartYear > $eventEndYear)));
    }

    public function setSelected($dateObject)
    {
        if ($dateObject->getYear() == $this->getYear() && $dateObject->getMonth() == $this->getMonth()) {
            $this->selected = true;

            $week = $this->weeks [$dateObject->getYear() . '_' . $dateObject->getWeekOfYear()] ?? null;
            if (is_object($week)) {
                $week->setSelected($dateObject);
            }
        }
    }

    /**
     * @param CalendarDateTime $dateObject
     */
    public function setCurrent($dateObject)
    {
        if ($dateObject->getYear() === $this->getYear() && $dateObject->getMonth() === $this->getMonth()) {
            $this->current = true;

            $week = $this->weeks [$dateObject->getYear() . '_' . $dateObject->getWeekOfYear()] ?? null;
            if ($week !== null) {
                $week->setCurrent($dateObject);
            }
        }
    }

    /**
     * @return bool
     */
    public function hasEvents(): bool
    {
        foreach ((array)$this->weeks as $week) {
            if ($week->hasEvents()) {
                return true;
            }
        }
        return false;
    }
}
