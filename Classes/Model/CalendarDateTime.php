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

/**
 * This is supposed to be a wrapper class to imitate old PEAR::Date
 * functions in order to able to leave a lot of function calls untouched
 * for now.
 */
class CalendarDateTime extends \DateTime
{

    public function __construct()
    {
        parent::__construct('', new \DateTimeZone('UTC'));
    }

    /**
     * @param \DateTime $object
     * @return CalendarDateTime
     */
    public static function generateFromDateTime(\DateTime $object): CalendarDateTime
    {
        $dateTime = new CalendarDateTime();
        $dateTime->setDate((int)$object->format('Y'), (int)$object->format('m'), (int)$object->format('d'));
        $dateTime->setTime((int)$object->format('H'), (int)$object->format('i'), (int)$object->format('s'));
        return $dateTime;
    }

    /**
     * {@inheritdoc}
     * @see \DateTime::format()
     * @return string
     */
    public function format(string $string, string $locale = 'en_US'): string
    {
        if ($string == 't' || $string == 'Y' || $string == 'm' || $string == 'd' || $string == 'H' || $string == 'i' || $string == 's' || $string == 'U' || $string == 'W' || $string == 'w' || $string == 'Ymd' || $string == 'Hi' || $string == 'His' || $string == 'YmdHis') {
            return parent::format($string);
        }
        $formatter = datefmt_create($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $this->getTimezone(), \IntlDateFormatter::GREGORIAN, $string);

        return $formatter->format($this);
    }

    /**
     * @param string $YmdHis
     * @return CalendarDateTime
     */
    public static function generateByYmdHis(string $YmdHis): CalendarDateTime
    {
        $a = new CalendarDateTime();
        if (strlen($YmdHis) == 14) {
            $a = $a->setDate((int)(substr($YmdHis, 0, 4)), (int)(substr($YmdHis, 4, 2)), (int)(substr($YmdHis, 6, 2)));
            $a->setTime((int)(substr($YmdHis, 8, 2)), (int)(substr($YmdHis, 10, 2)), (int)(substr($YmdHis, 12, 2)));
        }
        return $a;
    }

    /**
     * @param string $Ymd
     * @return CalendarDateTime
     */
    public static function generateByYmd(string $Ymd): CalendarDateTime
    {
        $a = new CalendarDateTime();
        if (strlen($Ymd) == 8) {
            $a = $a->setDate((int)(substr($Ymd, 0, 4)), (int)(substr($Ymd, 4, 2)), (int)(substr($Ymd, 6, 2)));
        }

        $a = $a->setTime(0, 0, 0, 0);
        return $a;
    }

    /**
     * @param CalendarDateTime $compareDate
     * @return bool
     */
    public function equals(CalendarDateTime $compareDate): bool
    {
        $a = (float)($compareDate->format('YmdHis'));
        $b = (float)($this->format('YmdHis'));
        return $a === $b;
    }

    /**
     * @param CalendarDateTime $compareDate
     * @return bool
     */
    public function before(CalendarDateTime $compareDate): bool
    {
        $a = (float)($compareDate->format('YmdHis'));
        $b = (float)($this->format('YmdHis'));
        return $a > $b;
    }

    /**
     * @param CalendarDateTime $compareDate
     * @return bool
     */
    public function after(CalendarDateTime $compareDate): bool
    {
        $a = (float)($compareDate->format('YmdHis'));
        $b = (float)($this->format('YmdHis'));
        return $a < $b;
    }

    /**
     * Subtracts seconds, a negative value adds them.
     */
    public function subtractSeconds(int $sec = 0): static
    {
        if ($sec < 0) {
            return $this->addSeconds(-$sec);
        }
        $this->sub(new \DateInterval('PT' . $sec . 'S'));
        return $this;
    }

    /**
     * Adds seconds, a negative value subtracts them.
     */
    public function addSeconds(int $sec = 0): static
    {
        if ($sec < 0) {
            return $this->subtractSeconds(-$sec);
        }
        $this->add(new \DateInterval('PT' . $sec . 'S'));
        return $this;
    }

    /**
     * @return int
     */
    public function getDayOfWeek(): int
    {
        return (int)($this->format('w'));
    }

    /**
     * @return int
     */
    public function getWeekOfYear(): int
    {
        return \MarioMatzulla\Cal\Utility\WeekStart::weekOfYear($this);
    }

    /**
     * @param CalendarDateTime $date
     */
    public function copy(CalendarDateTime $date)
    {
        $this->setTimezone($date->getTimezone());
        $this->setDate($date->getYear(), $date->getMonth(), $date->getDay());
        $this->setTime($date->getHour(), $date->getMinute(), $date->getSecond());
    }

    /**
     * @return int
     */
    public function getYear(): int
    {
        return (int)($this->format('Y'));
    }

    /**
     * @return int
     */
    public function getMonth(): int
    {
        return (int)($this->format('m'));
    }

    /**
     * @return int
     */
    public function getDay(): int
    {
        return (int)($this->format('d'));
    }

    /**
     * @return int
     */
    public function getHour(): int
    {
        return (int)($this->format('H'));
    }

    /**
     * @return int
     */
    public function getMinute(): int
    {
        return (int)($this->format('i'));
    }

    /**
     * @return int
     */
    public function getSecond(): int
    {
        return (int)($this->format('s'));
    }

    /**
     * @param int $y
     */
    public function setYear(int $y)
    {
        $this->setDate($y, (int)($this->format('m')), (int)($this->format('d')));
        return $this;
    }

    /**
     * @param int $m
     */
    public function setMonth(int $m)
    {
        $this->setDate((int)($this->format('Y')), $m, (int)($this->format('d')));
        return $this;
    }

    /**
     * @param int $d
     */
    public function setDay(int $d)
    {
        $this->setDate((int)($this->format('Y')), (int)($this->format('m')), $d);
        return $this;
    }

    /**
     * @param int $h
     */
    public function setHour(int $h = 0)
    {
        $this->setTime($h, (int)($this->format('i')), (int)($this->format('s')));
        return $this;
    }

    /**
     * @param int $m
     */
    public function setMinute(int $m = 0)
    {
        $this->setTime((int)($this->format('H')), $m, (int)($this->format('s')));
        return $this;
    }

    /**
     * @param int $s
     */
    public function setSecond(int $s = 0)
    {
        $this->setTime((int)($this->format('H')), (int)($this->format('i')), $s);
        return $this;
    }

    /**
     * Determine if this date is in the future
     *
     * Determine if this date is in the future
     *
     * @return bool true if this date is in the future
     */
    public function isFuture()
    {

        $now = new CalendarDateTime();
        if ($this->after($now)) {
            return true;
        }
        return false;

    }
}
