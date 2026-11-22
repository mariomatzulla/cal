<?php

declare(strict_types=1)
;

namespace MarioMatzulla\Cal\Utility;

use MarioMatzulla\Cal\Model\CalendarDateTime;

/**
 * This class offers a couple of helpful DateTime calculation
 * functions.
 */
class CalculateDateTime
{
    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateStartOfDay(CalendarDateTime $dateTime): CalendarDateTime
    {
        return $dateTime->modify('0:00:00');
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateEndOfDay(CalendarDateTime $dateTime): CalendarDateTime
    {
        return $dateTime->setTime(23, 59, 59);
    }

    /**
     * First day of the week (00:00:00), see WeekStart
     */
    public static function calculateStartOfWeek(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime->modify('-' . WeekStart::indexOf($dateTime->getDayOfWeek()) . ' days');
        $dateTime->setTime(0, 0, 0);
        return $dateTime;
    }

    /**
     * Last day of the week (23:59:59)
     */
    public static function calculateEndOfWeek(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime = self::calculateStartOfWeek($dateTime);
        $dateTime->modify('+6 days');
        $dateTime->setTime(23, 59, 59);
        return $dateTime;
    }

    /**
     * @param string $tcaString
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function parseTcaString(string $tcaString, $dateTime = null): CalendarDateTime
    {
        if ($dateTime === null) {
            $dateTime = new CalendarDateTime();
        }
        switch ($tcaString) {
            case '+1 month':
                $dateTime->modify('+1 month');
                break;
            case '+1 week':
                $dateTime->modify('+1 week');
                break;
            case '+1 year':
                $dateTime->modify('+1 year');
                break;
            case 'cal:monthend':
                $dateTime = self::calculateEndOfMonth($dateTime);
                break;
            case 'cal:monthstart':
                $dateTime = self::calculateStartOfMonth($dateTime);
                break;
            case 'cal:quarterend':
                $dateTime = self::calculateEndOfQuarter($dateTime);
                break;
            case 'cal:quarterstart':
                $dateTime = self::calculateStartOfQuarter($dateTime);
                break;
            case 'cal:today':
                $dateTime = self::calculateStartOfDay($dateTime);
                break;
            case 'cal:tomorrow':
                $dateTime = self::calculateStartOfDay($dateTime->modify('tomorrow'));
                break;
            case 'cal:weekend':
                $dateTime = self::calculateEndOfWeek($dateTime);
                break;
            case 'cal:weekstart':
                $dateTime = self::calculateStartOfWeek($dateTime);
                break;
            case 'cal:yearend':
                $dateTime = self::calculateEndOfYear($dateTime);
                break;
            case 'cal:yearstart':
                $dateTime = self::calculateStartOfYear($dateTime);
                break;
            case 'cal:yesterday':
                $dateTime = self::calculateStartOfDay($dateTime->modify('yesterday'));
                break;
            case '':
            case 'now':
            default:
                $dateTime->modify($tcaString);
        }
        return $dateTime;
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateStartOfMonth(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime = self::calculateStartOfDay($dateTime);
        $dateTime->setDay(1);
        return $dateTime;
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateEndOfMonth(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime = self::calculateEndOfDay($dateTime);
        $dateTime->setDay(CalculateDateTime::getDaysInMonth($dateTime->getYear(), $dateTime->getMonth()));
        return $dateTime;
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateStartOfYear(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime = self::calculateStartOfMonth($dateTime);
        return $dateTime->setMonth(1);
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateEndOfYear(CalendarDateTime $dateTime): CalendarDateTime
    {
        $dateTime = self::calculateStartOfYear($dateTime);
        return $dateTime->setYear($dateTime->getYear() + 1)->subtractSeconds(1);
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return int
     */
    public static function calculateQuarter(CalendarDateTime $dateTime): int
    {
        $month = $dateTime->getMonth();
        $quarter = 0;
        switch ($month) {
            case 1:
            case 2:
            case 3:
                $quarter = 1;
                break;
            case 4:
            case 5:
            case 6:
                $quarter = 2;
                break;
            case 7:
            case 8:
            case 9:
                $quarter = 3;
                break;
            case 10:
            case 11:
            case 12:
                $quarter = 4;
                break;
        }
        return $quarter;
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateStartOfQuarter(CalendarDateTime $dateTime): CalendarDateTime
    {
        $quarter = self::calculateQuarter($dateTime);
        $month = ($quarter - 1) * 3 + 1;
        $dateTime = $dateTime->setMonth($month);
        $dateTime = self::calculateStartOfMonth($dateTime);
        return $dateTime;
    }

    /**
     * @param CalendarDateTime $dateTime
     * @return CalendarDateTime
     */
    public static function calculateEndOfQuarter(CalendarDateTime $dateTime): CalendarDateTime
    {
        $quarter = self::calculateQuarter($dateTime);
        $month = $quarter * 3;
        $dateTime = $dateTime->setMonth($month);
        $dateTime = self::calculateEndOfMonth($dateTime);
        return $dateTime;
    }

    /**
     * Returns date of the previous specific day of the week
     * from the given date
     *
     * @param int $dow day of week, 0=Sunday
     * @param int $day
     *          the day of the month, default is current local day
     * @param int $month
     *          the month, default is current local month
     * @param int $year
     *          the year in four digit format, default is current local year
     * @param bool $onOrBefore
     *          if true and days are same, returns current day
     *
     * @return CalendarDateTime the date
     *
     * @static
     */
    public static function prevDayOfWeek($dow, $day = 0, $month = 0, $year = 0, $onOrBefore = false): CalendarDateTime
    {
        $today = new CalendarDateTime();
        if (empty($year)) {
            $year = $today->getYear();
        }
        if (empty($month)) {
            $month = $today->getMonth();
        }
        if (empty($day)) {
            $day = $today->getDay();
        }
        $date = new CalendarDateTime();
        $date->setDate((int)$year, (int)$month, (int)$day);
        $date->setTime(0, 0, 0);
        $curr_weekday = $date->getDayOfWeek();
        if ($curr_weekday == $dow) {
            if (! $onOrBefore) {
                $date->modify('-7 day 0:00:00');
            }
        } elseif ($curr_weekday < $dow) {
            $date->modify('-' . (7 - ($dow - $curr_weekday)) . ' day 0:00:00');
        } else {
            $date->modify('-' . ($curr_weekday - $dow) . ' day 0:00:00');
        }
        return $date;
    }

    /**
     * Returns date of the next specific day of the week
     * from the given date
     *
     * @param int $dow
     *          the day of the week (0 = Sunday)
     * @param int $day
     *          the day of the month, default is current local day
     * @param int $month
     *          the month, default is current local month
     * @param int $year
     *          the year in four digit format, default is current local year
     * @param bool $onOrAfter
     *          if true and days are same, returns current day
     *
     * @return CalendarDateTime the date
     *
     * @static
     */
    public static function nextDayOfWeek($dow, $day = 0, $month = 0, $year = 0, $onOrAfter = false): CalendarDateTime
    {
        $today = new CalendarDateTime();
        if (empty($year)) {
            $year = $today->getYear();
        }
        if (empty($month)) {
            $month = $today->getMonth();
        }
        if (empty($day)) {
            $day = $today->getDay();
        }

        $date = new CalendarDateTime();
        $date->setDate((int)$year, (int)$month, (int)$day);
        $date->setTime(0, 0, 0);
        $curr_weekday = $date->getDayOfWeek();

        if ($curr_weekday == $dow) {
            if (! $onOrAfter) {
                $date->modify('+7 day 0:00:00');
            }
        } elseif ($curr_weekday > $dow) {
            $date->modify('+' . (7 - ($curr_weekday - $dow)) . ' day 0:00:00');
        } else {
            $date->modify('+' . ($dow - $curr_weekday) . ' day 0:00:00');
        }

        return $date;
    }

    /**
     * Returns true for valid date, false for invalid date
     *
     * @param int $day
     *          the day of the month
     * @param int $month
     *          the month
     * @param int $year
     *          the year. Use the complete year instead of the
     *          abbreviated version. E.g. use 2005, not 05.
     *          Do not add leading 0's for years prior to 1000.
     *
     * @return bool
     *
     * @static
     */
    public static function isValidDate($day, $month, $year)
    {
        if ($year < 0 || $year > 9999) {
            return false;
        }
        if (! self::verifyDate($month, $day, $year)) {
            return false;
        }
        return true;
    }

    public static function verifyDate($month, $day, $year, $strict = true)
    {
        $dateTime = CalendarDateTime::createFromFormat('m/d/Y', $month . '/' . $day . '/' . $year);
        if ($strict) {
            $errors = CalendarDateTime::getLastErrors();
            if (! empty($errors ['warning_count'])) {
                return false;
            }
        }
        return $dateTime !== false;
    }

    /**
     * Find the number of days in the given month
     *
     * @param int $month
     *          the month, default is current local month
     * @param int $year
     *          the year in four digit format, default is current local year
     *
     * @return int the number of days the month has
     *
     * @static
     */
    public static function getDaysInMonth($month = 0, $year = 0): int
    {
        $today = new CalendarDateTime();
        if (empty($year)) {
            $year = $today->getYear();
        }
        if (empty($month)) {
            $month = $today->getMonth();
        }
        $today->setDate((int)$year, (int)$month, 1);
        return (int)($today->format('t'));
    }

    /**
     * @param $year
     * @param $week
     * @param $weekday
     * @return CalendarDateTime
     */
    /**
     * A weekday (0 = Sunday ... 6 = Saturday) of the week with the number $week of $year (see WeekStart)
     */
    public static function getDayByWeek(int $year, int $week, int $weekday): CalendarDateTime
    {
        $date = WeekStart::firstDayOfWeek($year, $week)->modify('+' . WeekStart::indexOf($weekday) . ' days');
        return CalendarDateTime::generateByYmd($date->format('Ymd'));
    }
}
