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
if (! defined('DATE_CALC_BEGIN_WEEKDAY')) {
    /**
     * Defines what day starts the week
     *
     * Monday (1) is the international standard.
     * Redefine this to 0 if you want weeks to begin on Sunday.
     */
    define('DATE_CALC_BEGIN_WEEKDAY', 1);
}

if (! defined('DATE_CALC_FORMAT')) {
    /**
     * The default value for each method's $format parameter
     *
     * The default is '%Y%m%d'. To override this default, define
     * this constant before including Calc.php.
     *
     * @since Constant available since Release 1.4.4
     */
    define('DATE_CALC_FORMAT', 'Ymd');
}

/**
 * This is supposed to be a wrapper class to imitate old PEAR::Date
 * functions in order to able to leave a lot of function calls untouched
 * for now.
 */
class CalendarDateTime extends \DateTime
{
    /**
     * define the default weekday abbreviation length
     * used by ::format()
     *
     * @var int
     */
    public $getWeekdayAbbrnameLength = 3;

    /**
     * define the default monthname abbreviation length
     * used by ::format()
     *
     * @var int
     */
    private $getMonthAbbrnameLength = 3;

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
        $dateTime->setYear((int)($object->format('Y')));
        $dateTime->setMonth((int)($object->format('m')));
        $dateTime->setDay((int)($object->format('d')));
        $dateTime->setHour((int)($object->format('H')));
        $dateTime->setMinute((int)($object->format('i')));
        $dateTime->setSecond((int)($object->format('s')));
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
     * @param CalendarDateTime $compareDateA
     * @param CalendarDateTime $compareDateB
     * @return int
     */
    public function compare(CalendarDateTime $compareDateA, CalendarDateTime $compareDateB): int
    {
        $a = (float)($compareDateA->format('YmdHis'));
        $b = (float)($compareDateB->format('YmdHis'));
        if ($a === $b) {
            return 0;
        }
        if ($a < $b) {
            return - 1;
        }
        return 1;
    }

    /**
     * @param int $sec
     * @throws \Exception
     */
    public function subtractSeconds($sec = 0)
    {
        settype($sec, 'int');

        // Negative value given.
        if ($sec < 0) {
            $this->addSeconds(abs($sec));
            return;
        }
        $this->sub(new \DateInterval('PT' . $sec . 'S'));
    }

    /**
     * @param int $sec
     * @throws \Exception
     */
    public function addSeconds($sec = 0)
    {
        settype($sec, 'int');

        // Negative value given.
        if ($sec < 0) {
            $this->subtractSeconds(abs($sec));
            return;
        }

        $this->add(new \DateInterval('PT' . $sec . 'S'));
    }

    /**
     * @param bool $abbr
     * @param bool $length
     * @return string
     */
    public function getDayName($abbr = false, $length = false): string
    {
        $dayName = $this->format('F');
        if ($abbr) {
            if ($length === false) {
                $length = $this->getWeekdayAbbreviationLength();
            }
            $dayName = $this->crop($dayName, $length);
        }
        return $this->applyStdWrap($dayName);
    }

    /**
     * @param bool $abbr
     * @param bool $length
     * @return string
     */
    public function getMonthName($abbr = false, $length = false): string
    {
        $monthName = $this->format('l');
        if ($abbr) {
            if ($length === false) {
                $length = $this->getMonthAbbreviationLength();
            }
            $monthName = $this->crop($monthName, $length);
        }
        return $this->applyStdWrap($monthName);
    }

    /**
     * Returns the length that should be used for month name abbreviation
     *
     * @return int
     */
    public function getMonthAbbreviationLength(): int
    {
        return (int)($this->getMonthAbbrnameLength);
    }

    /**
     * Returns the length that should be used for month name abbreviation
     *
     * @return int
     */
    public function getWeekdayAbbreviationLength(): int
    {
        return (int)($this->getWeekdayAbbrnameLength);
    }

    /**
     * Applys the default date_stdWrap to the given string.
     *
     * @param string $value
     * @return string
     */
    public function applyStdWrap($value = ''): string
    {
        return $value;
    }

    /**
     * uses a bytesafe cropping function if possible in order to not destroy multibyte chars from strings (e.g.
     * names in UTF-8)
     *
     * @param string $value
     * @param bool $length
     * @return string the cropped string
     */
    public function crop($value = '', $length = false): string
    {
        if ($length === false) {
            return $value;
        }
        return mb_substr($value, 0, (int)$length);
    }

    /**
     * @param mixed $id
     */
    public function setTZbyID($id)
    {
        $this->setTimezone(new \DateTimeZone((string)$id));
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
        return (int)($this->format('W'));
    }

    /**
     * @param CalendarDateTime $date
     */
    public function copy(CalendarDateTime $date)
    {
        $this->setYear($date->getYear());
        $this->setMonth($date->getMonth());
        $this->setDay($date->getDay());
        $this->setHour($date->getHour());
        $this->setMinute($date->getMinute());
        $this->setSecond($date->getSecond());
        $this->setTimezone($date->getTimezone());
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
