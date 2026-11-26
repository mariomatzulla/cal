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
 * Normalizes the recurrence rule of an event before the index is generated:
 * removes invalid values and fills the values the rule needs.
 */
class RecurrenceValidator
{
    private const FREQUENCIES = ['day', 'week', 'month', 'year'];

    private const WEEKDAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    private const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    private const MONTH_DAYS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31];

    public static function validateEvent(Event $event): void
    {
        self::normalizeAllValues($event);
        self::filterFalseCombinations($event);
        if (! in_array($event->getFreq(), self::FREQUENCIES, true)) {
            $event->setFreq('none');
            return;
        }
        self::checkInterval($event);
        self::checkByMonth($event);
        self::checkByMonthday($event);
        self::checkByDay($event);
        self::checkCount($event);
    }

    /**
     * The value "all" of the old backend form
     */
    private static function normalizeAllValues(Event $event): void
    {
        if (strtolower(trim((string)$event->getBymonth())) === 'all') {
            $event->setBymonth(implode(',', self::MONTHS));
        }
        if (strtolower(trim((string)$event->getBymonthday())) === 'all') {
            $event->setBymonthday('');
        }
        if (strtolower(trim((string)$event->getByday())) === 'all') {
            $event->setByday('');
        }
    }

    /**
     * Removes the values the frequency does not use
     */
    private static function filterFalseCombinations(Event $event): void
    {
        switch ($event->getFreq()) {
            case 'day':
                $event->setBymonth('');
                $event->setBymonthday('');
                $event->setByday('');
                break;
            case 'week':
                $event->setBymonth('');
                $event->setBymonthday('');
                break;
            case 'month':
                $event->setBymonth('');
                break;
        }
    }

    private static function checkInterval(Event $event): void
    {
        if ((int)$event->getIntrval() < 1) {
            $event->setIntrval(1);
        }
    }

    private static function checkCount(Event $event): void
    {
        if ((int)$event->getCnt() < 1) {
            $event->setCnt(9999999);
        }
    }

    private static function checkByMonth(Event $event): void
    {
        if (trim((string)$event->getBymonth()) === '') {
            if ($event->getFreq() !== 'year' || trim((string)$event->getBymonthday()) !== '') {
                $event->setBymonth(implode(',', self::MONTHS));
            } elseif (trim((string)$event->getByday()) === '') {
                $event->setBymonth($event->getStart()->getMonth());
            }
            return;
        }
        $months = array_unique(array_map('intval', explode(',', (string)$event->getBymonth())));
        $months = array_filter($months, static fn(int $month): bool => $month >= 1 && $month <= 12);
        sort($months);
        $event->setBymonth(implode(',', $months));
    }

    private static function checkByMonthday(Event $event): void
    {
        if (trim((string)$event->getBymonthday()) === '') {
            if (trim((string)$event->getByday()) === '' && $event->getFreq() !== 'week') {
                $event->setBymonthday($event->getStart()->getDay());
            } else {
                $event->setBymonthday(implode(',', self::MONTH_DAYS));
            }
            return;
        }
        $days = array_filter(explode(',', (string)$event->getBymonthday()), static function (string $value): bool {
            $value = (int)$value;
            return $value !== 0 && $value >= -31 && $value <= 31;
        });
        $event->setBymonthday(implode(',', $days));
    }

    /**
     * Weekdays with an optional ordinal number for monthly and yearly rules, e.g. "MO", "2WE" or "-1FR"
     */
    private static function checkByDay(Event $event): void
    {
        if ($event->getFreq() === 'day') {
            $event->setByday(implode(',', self::WEEKDAYS));
            return;
        }
        $allowedValues = [];
        foreach (explode(',', strtoupper((string)$event->getByday())) as $value) {
            if (! preg_match('/^\s*([-+]?)(\d{0,2})([A-Z]{2})\s*$/', $value, $parts) || ! in_array($parts[3], self::WEEKDAYS, true)) {
                continue;
            }
            if ((int)$parts[2] > 0 && ($event->getFreq() === 'month' || $event->getFreq() === 'year')) {
                $allowedValues[] = ($parts[1] === '-' ? '-' : '') . (int)$parts[2] . $parts[3];
            } else {
                $allowedValues[] = $parts[3];
            }
        }
        if ($allowedValues === []) {
            $allowedValues = $event->getFreq() === 'week' ? [self::WEEKDAYS[$event->getStart()->getDayOfWeek()]] : self::WEEKDAYS;
        }
        $event->setByday(implode(',', $allowedValues));
    }
}
