<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

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
abstract class TimeView
{
    /**
     * @var int
     */
    private $day = 0;

    /**
     * @var int
     */
    private $month = 0;

    /**
     * @var int
     */
    private $year = 0;

    /**
     * @var int
     */
    private $minute = 0;

    /**
     * @var int
     */
    private $hour = 0;

    /**
     * @var int
     */
    private $weekdayNumber = 0;

    /**
     * @var int
     */
    private $weekDayLength = 100;

    /**
     * @var string
     */
    private $weekDayFormat = 'eee';

    /**
     * @var bool
     */
    protected $current = false;

    protected $selected = false;

    private $parentMonth;

    /**
     * Constructor.
     */
    public function __construct() {}

    /**
     * @return bool
     */
    public function isCurrent()
    {
        return $this->current;
    }

    /**
     * @return bool
     */
    public function isSelected()
    {
        return $this->selected;
    }

    /**
     * @param mixed $event
     * @return mixed
     */
    abstract public function addEvent($event);

    /**
     * @param CalendarDateTime $dateTime
     * @return string
     */
    public function getWeekdayString(CalendarDateTime $dateTime): string
    {
        return mb_substr($dateTime->format($this->getWeekDayFormat()), 0, $this->getWeekDayLength(), 'UTF-8');
    }

    abstract public function hasEvents();

    /**
     * @param mixed $dateObject
     */
    abstract public function setCurrent($dateObject);

    /**
     * @param mixed $dateObject
     */
    abstract public function setSelected($dateObject);

    public function getParentMonth()
    {
        return $this->parentMonth;
    }

    /**
     * @param mixed $parentMonth
     */
    public function setParentMonth($parentMonth)
    {
        $this->parentMonth = $parentMonth;
    }

    public function getDay()
    {
        return $this->day;
    }

    /**
     * @param mixed $day
     */
    public function setDay($day)
    {
        $this->day = (int)$day;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @param mixed $month
     */
    public function setMonth($month)
    {
        $this->month = (int)$month;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @param mixed $year
     */
    public function setYear($year)
    {
        $this->year = (int)$year;
    }

    public function getWeekdayNumber()
    {
        return $this->weekdayNumber;
    }

    /**
     * @param mixed $weekdayNumber
     */
    public function setWeekdayNumber($weekdayNumber)
    {
        $this->weekdayNumber = (int)$weekdayNumber;
    }

    /**
     * @return int
     */
    public function getWeekDayLength(): int
    {
        return $this->weekDayLength;
    }

    /**
     * @param mixed $length
     */
    public function setWeekDayLength($length)
    {
        $this->weekDayLength = (int)$length;
    }

    /**
     * @return string
     */
    public function getWeekDayFormat(): string
    {
        return $this->weekDayFormat;
    }

    /**
     * @param mixed $format
     */
    public function setWeekDayFormat($format)
    {
        $this->weekDayFormat = (string)$format;
    }
}
