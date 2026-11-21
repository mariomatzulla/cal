<?php

declare(strict_types=1);

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

namespace MarioMatzulla\Cal\Utility;

use MarioMatzulla\Cal\Domain\Model\Event;

/**
 * Class RecurrenceValidatior
 */
class RecurrenceValidator
{
    private const allFrequecies = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];

    private const allWeekdays = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    private const allMonths = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    private const allMonthDays = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31];

    public static function validateEvent(Event $event)
    {
        RecurrenceValidator::normalizeAllValues($event);
        RecurrenceValidator::filterFalseCombinations($event);
        RecurrenceValidator::checkRecurringSettings($event);
    }

    /**
     * cal 1.x stored "all" for "every month" / "every day of the month" / "every weekday"
     */
    private static function normalizeAllValues(Event $event): void
    {
        if (strtolower(trim((string)$event->getByMonth())) === 'all') {
            $event->setByMonth(implode(',', RecurrenceValidator::allMonths));
        }
        if (strtolower(trim((string)$event->getByMonthDay())) === 'all') {
            $event->setByMonthDay('');
        }
        if (strtolower(trim((string)$event->getByDay())) === 'all') {
            $event->setByDay('');
        }
    }

    private static function filterFalseCombinations(Event $event)
    {

        switch ($event->getFreq()) {
            case '':
            case 'none':
                break;
            case 'day':
                $event->setByMonth('');
                $event->setByMonthDay('');
                $event->setByDay('');
                break;
            case 'week':
                $event->setByMonth('');
                $event->setByMonthDay('');
                break;
            case 'month':
                $event->setByMonth('');
                break;
            case 'year':
                // yearly: BYMONTH, BYMONTHDAY and BYDAY are kept. With BYMONTHDAY, BYDAY limits the days
                // (RFC 5545, e.g. BYMONTHDAY=13;BYDAY=FR: every Friday the 13th)
                break;
        }
    }

    private static function checkRecurringSettings(Event $event)
    {
        RecurrenceValidator::checkFrequency($event);
        if ($event->getFreq() == 'none') {
            return;
        }
        RecurrenceValidator::checkInterval($event);
        RecurrenceValidator::checkByMonth($event);
        RecurrenceValidator::checkByWeekno($event);
        RecurrenceValidator::checkByYearday($event);
        RecurrenceValidator::checkByMonthday($event);
        RecurrenceValidator::checkByDay($event);
        RecurrenceValidator::checkByHour($event);
        RecurrenceValidator::checkByMinute($event);
        RecurrenceValidator::checkBySecond($event);
        RecurrenceValidator::checkBySetpos($event);
        RecurrenceValidator::checkCount($event);
        RecurrenceValidator::checkWkst($event);
    }

    private static function checkCount($event)
    {

        if (! $event->getCnt() || $event->getCnt() < 1) {
            $event->setCnt(9999999);
        }
    }

    private static function checkBySecond($event)
    {

        if ((int)($event->getBysecond()) < 0 || (int)($event->getBysecond()) > 59) {
            $event->setBysecond($event->getStart()->getSecond());
        }
    }

    private static function checkByMinute($event)
    {

        if ((int)($event->getByminute()) < 0 || (int)($event->getByminute()) > 59) {
            $event->setByminute($event->getStart()->getMinute());
        }
    }

    private static function checkByHour($event)
    {

        if ((int)($event->getByhour()) < 0 || (int)($event->getByhour()) > 23) {
            $event->setByhour($event->getStart()->getHour());
        }
    }

    private static function checkByDay($event)
    {

        $allowedValues =  [];
        $allowedWeekdayValues = RecurrenceValidator::allWeekdays;
        $byDayArray = explode(',', (string)$event->getByday());
        if ($event->getFreq() == 'day') {
            $event->setByday(implode(',', RecurrenceValidator::allWeekdays));
            return;
        }
        for ($i = 0; $i < count($byDayArray); $i++) {
            $byDayArray [$i] = strtoupper($byDayArray [$i]);
            $byDaySplit = [];
            if (preg_match('/([-\+]{0,1})?([0-9]{1,2})?([A-Z]{2})/', (string)$byDayArray [$i], $byDaySplit)) {
                if (! in_array($byDaySplit [3], $allowedWeekdayValues)) {
                    continue;
                }
                if (! ($byDaySplit [2] > 0 && ($event->getFreq() == 'month' || $event->getFreq() == 'year'))) {
                    // n-th values are not allowed for monthly and yearly
                    unset($byDaySplit [1]);
                    unset($byDaySplit [2]);
                }
                unset($byDaySplit [0]);
                $allowedValues [] = implode('', $byDaySplit);
            }
            // the current byday setting is not valid

        }
        if (count($allowedValues) == 0) {
            if ($event->getFreq() == 'week') {
                $allowedValues = [$allowedWeekdayValues [$event->getStart()->getDayOfWeek()]];
            } else {
                $allowedValues = RecurrenceValidator::allWeekdays;
            }
        }
        $event->setByday(implode(',', $allowedValues));
    }

    private static function checkByMonth($event)
    {

        if ($event->getBymonth() == '') {
            if ($event->getFreq() == 'year') {
                // RFC 5545, yearly rule without BYMONTH:
                // - BYMONTHDAY: the days of every month (BYDAY limits them)
                // - only BYDAY: the days of the whole year, BYMONTH stays empty (RecurrenceGenerator::getYearDaysForByDay())
                // - neither: the month of the start (once a year on the start date)
                if (trim((string)$event->getBymonthday()) !== '') {
                    $event->setBymonth(implode(',', RecurrenceValidator::allMonths));
                } elseif (trim((string)$event->getByday()) === '') {
                    $event->setBymonth($event->getStart()->getMonth());
                }
            } else {
                $event->setBymonth(implode(',', RecurrenceValidator::allMonths));
            }
        } else {
            $byMonth = explode(',', (string)$event->getBymonth());
            $allowedValues = array_filter($byMonth, function ($value) {
                $value = (int)$value;
                if ($value < 0 || $value > 13) {
                    return false;
                }
                return true;
            });
            $allowedValues = array_unique($allowedValues);
            sort($allowedValues);
            $event->setBymonth(implode(',', $allowedValues));
        }
    }

    private static function checkBymonthday($event)
    {
        /* If there's not a monthday set, pick a default value */
        if ($event->getBymonthday() == null || $event->getBymonthday() == '') {

            /**
             * If there's no day of the week either, assume that we only want
             * to recur on the event start day.
             * If there is a day of the
             * week, assume that we want to recur anytime that day of the week
             * occurs.
             */
            if (($event->getByday() == null || $event->getByday() == '') && $event->getFreq() != 'week') {
                $eventStart = $event->getStart();
                $event->setBymonthday($eventStart->getDay());
            } else {
                $event->setBymonthday(implode(',', RecurrenceValidator::allMonthDays));
            }
        } else {
            // RFC 5545: 1 to 31 and -31 to -1 (counted from the end of the month, -1 = last day)
            $event->setBymonthday(implode(',', array_filter(explode(',', (string)$event->getBymonthday()), function ($value) {
                $value = (int)$value;
                return $value !== 0 && $value >= -31 && $value <= 31;
            })));
        }
    }

    private static function checkBySetpos($event)
    {

        $event->setBysetpos((int)($event->getBysetpos()));
    }

    private static function checkByYearday($event)
    {

        if ($event->getByyearday() == null || $event->getByyearday() == '') {
            // nothing
        } else {
            $event->setByyearday(implode(',', array_filter($event->getByyearday(), function ($value) {
                $value = (int)$value;
                if ($value < - 31 || $value > 31 || $value == 0) {
                    return false;
                }
                return true;
            })));
        }
    }

    private static function checkByWeekno(Event &$event)
    {

        if ($event->getFreq() == 'yearly') {
            $event->setByweekno(implode(',', array_filter(explode(',', (string)$event->getByweekno()), function ($value) {
                $value = (int)$value;
                if ($value < - 53 || $value > 53 || $value == 0) {
                    return false;
                }
                return true;
            })));
        } else {
            $event->setByweekno('');
        }
    }

    private static function checkFrequency(Event &$event)
    {

        if (! in_array($event->getFreq(), RecurrenceValidator::allFrequecies)) {
            $event->setFreq('none');
        }
    }

    private static function checkInterval(Event &$event)
    {

        if (! $event->getIntrval() || $event->getIntrval() < 1) {
            $event->setIntrval(1);
        }
    }

    private static function checkWkst(Event &$event)
    {

        $wkst = strtoupper($event->getWkst());
        if (! in_array($wkst, RecurrenceValidator::allWeekdays)) {
            $wkst = '';
        }
        $event->setWkst($wkst);
    }
}
