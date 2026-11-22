<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Utility;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * First day of the week of the calendar views (setting plugin.tx_cal.settings.view.weekStartDay).
 *
 * The weeks keep the numbers of ISO 8601. With Sunday as first day, a week starts on the Sunday before
 * the Monday of the ISO week, so a Sunday belongs to the week of the following Monday.
 * The recurrence rules are not affected (RFC 5545, WKST=MO).
 */
final class WeekStart
{
    public const SUNDAY = 0;
    public const MONDAY = 1;

    private static int $firstDay = self::MONDAY;

    /**
     * @param int|string|null $value 0 / "sunday" or 1 / "monday" (default)
     */
    public static function set(int|string|null $value): void
    {
        $value = strtolower(trim((string)$value));
        self::$firstDay = in_array($value, ['0', 'sunday', 'su', 'sun'], true) ? self::SUNDAY : self::MONDAY;
    }

    /**
     * @return int 0 = Sunday, 1 = Monday
     */
    public static function get(): int
    {
        return self::$firstDay;
    }

    /**
     * Position of a weekday (0 = Sunday ... 6 = Saturday) in the week, 0 = first day of the week
     */
    public static function indexOf(int $dayOfWeek): int
    {
        return ($dayOfWeek - self::$firstDay + 7) % 7;
    }

    /**
     * Number of the week of a date (ISO 8601, a Sunday counts to the following week if weeks start on Sunday)
     */
    public static function weekOfYear(\DateTimeInterface $date): int
    {
        if (self::$firstDay === self::SUNDAY && (int)$date->format('w') === 0) {
            return (int)\DateTimeImmutable::createFromInterface($date)->modify('+1 day')->format('W');
        }
        return (int)$date->format('W');
    }

    /**
     * First day (00:00) of the week with the number $week of $year (see weekOfYear())
     */
    public static function firstDayOfWeek(int $year, int $week): \DateTimeImmutable
    {
        $monday = (new \DateTimeImmutable('today'))->setISODate($year, $week, 1)->setTime(0, 0);
        return self::$firstDay === self::SUNDAY ? $monday->modify('-1 day') : $monday;
    }
}
