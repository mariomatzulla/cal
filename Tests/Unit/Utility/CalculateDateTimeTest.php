<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Utility;

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
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Utility\CalculateDateTime;
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CalculateDateTime::class)]
final class CalculateDateTimeTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    private static function date(string $ymdHis): CalendarDateTime
    {
        return CalendarDateTime::generateByYmdHis($ymdHis);
    }

    #[Test]
    public function calculateStartAndEndOfDay(): void
    {
        self::assertSame('20261125000000', CalculateDateTime::calculateStartOfDay(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20261125235959', CalculateDateTime::calculateEndOfDay(self::date('20261125153012'))->format('YmdHis'));
    }

    #[Test]
    public function calculateStartAndEndOfWeekForMonday(): void
    {
        WeekStart::set('monday');
        // Wednesday 25.11.2026
        self::assertSame('20261123000000', CalculateDateTime::calculateStartOfWeek(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20261129235959', CalculateDateTime::calculateEndOfWeek(self::date('20261125153012'))->format('YmdHis'));
        // a Sunday is the last day of the week
        self::assertSame('20261123000000', CalculateDateTime::calculateStartOfWeek(self::date('20261129100000'))->format('YmdHis'));
    }

    #[Test]
    public function calculateStartAndEndOfWeekForSunday(): void
    {
        WeekStart::set('sunday');
        self::assertSame('20261122000000', CalculateDateTime::calculateStartOfWeek(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20261128235959', CalculateDateTime::calculateEndOfWeek(self::date('20261125153012'))->format('YmdHis'));
        // a Sunday is the first day of the week
        self::assertSame('20261129000000', CalculateDateTime::calculateStartOfWeek(self::date('20261129100000'))->format('YmdHis'));
    }

    public static function monthDataProvider(): array
    {
        return [
            'month with 30 days' => ['20261115120000', '20261101000000', '20261130235959'],
            'month with 31 days' => ['20261215120000', '20261201000000', '20261231235959'],
            'february' => ['20260210120000', '20260201000000', '20260228235959'],
            'february of a leap year' => ['20280210120000', '20280201000000', '20280229235959'],
        ];
    }

    #[Test]
    #[DataProvider('monthDataProvider')]
    public function calculateStartAndEndOfMonth(string $date, string $start, string $end): void
    {
        self::assertSame($start, CalculateDateTime::calculateStartOfMonth(self::date($date))->format('YmdHis'));
        self::assertSame($end, CalculateDateTime::calculateEndOfMonth(self::date($date))->format('YmdHis'));
    }

    #[Test]
    public function calculateStartAndEndOfYear(): void
    {
        self::assertSame('20260101000000', CalculateDateTime::calculateStartOfYear(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20261231235959', CalculateDateTime::calculateEndOfYear(self::date('20261125153012'))->format('YmdHis'));
    }

    public static function quarterDataProvider(): array
    {
        $data = [];
        foreach ([1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4] as $index => $quarter) {
            $data['month ' . ($index + 1)] = [$index + 1, $quarter];
        }
        return $data;
    }

    #[Test]
    #[DataProvider('quarterDataProvider')]
    public function calculateQuarterReturnsTheQuarterOfTheMonth(int $month, int $quarter): void
    {
        self::assertSame($quarter, CalculateDateTime::calculateQuarter(self::date(sprintf('2026%02d15120000', $month))));
    }

    #[Test]
    public function calculateQuarterOfAnInvalidMonthIsZero(): void
    {
        $date = new class () extends CalendarDateTime {
            public function getMonth(): int
            {
                return 13;
            }
        };
        self::assertSame(0, CalculateDateTime::calculateQuarter($date));
    }

    #[Test]
    public function calculateStartAndEndOfQuarter(): void
    {
        self::assertSame('20261001000000', CalculateDateTime::calculateStartOfQuarter(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20261231235959', CalculateDateTime::calculateEndOfQuarter(self::date('20261125153012'))->format('YmdHis'));
        self::assertSame('20260401000000', CalculateDateTime::calculateStartOfQuarter(self::date('20260515153012'))->format('YmdHis'));
        self::assertSame('20260630235959', CalculateDateTime::calculateEndOfQuarter(self::date('20260515153012'))->format('YmdHis'));
    }

    public static function parseTcaStringDataProvider(): array
    {
        return [
            '+1 month' => ['+1 month', '20261225153012'],
            '+1 week' => ['+1 week', '20261202153012'],
            '+1 year' => ['+1 year', '20271125153012'],
            'monthend' => ['cal:monthend', '20261130235959'],
            'monthstart' => ['cal:monthstart', '20261101000000'],
            'quarterend' => ['cal:quarterend', '20261231235959'],
            'quarterstart' => ['cal:quarterstart', '20261001000000'],
            'today' => ['cal:today', '20261125000000'],
            'tomorrow' => ['cal:tomorrow', '20261126000000'],
            'weekend' => ['cal:weekend', '20261129235959'],
            'weekstart' => ['cal:weekstart', '20261123000000'],
            'yearend' => ['cal:yearend', '20261231235959'],
            'yearstart' => ['cal:yearstart', '20260101000000'],
            'yesterday' => ['cal:yesterday', '20261124000000'],
            'empty' => ['', '20261125153012'],
            'now' => ['now', '20261125153012'],
            'relative value' => ['-3 days', '20261122153012'],
        ];
    }

    #[Test]
    #[DataProvider('parseTcaStringDataProvider')]
    public function parseTcaStringModifiesTheGivenDate(string $value, string $expected): void
    {
        self::assertSame($expected, CalculateDateTime::parseTcaString($value, self::date('20261125153012'))->format('YmdHis'));
    }

    #[Test]
    public function parseTcaStringUsesTheCurrentTimeWithoutDate(): void
    {
        $result = CalculateDateTime::parseTcaString('cal:today');
        self::assertSame(gmdate('Ymd') . '000000', $result->format('YmdHis'));
    }

    public static function prevDayOfWeekDataProvider(): array
    {
        // 25.11.2026 is a Wednesday (3)
        return [
            'same weekday, before' => [3, false, '20261118'],
            'same weekday, on or before' => [3, true, '20261125'],
            'weekday later in the week' => [5, false, '20261120'],
            'weekday earlier in the week' => [1, false, '20261123'],
        ];
    }

    #[Test]
    #[DataProvider('prevDayOfWeekDataProvider')]
    public function prevDayOfWeekReturnsThePreviousWeekday(int $weekday, bool $onOrBefore, string $expected): void
    {
        self::assertSame($expected . '000000', CalculateDateTime::prevDayOfWeek($weekday, 25, 11, 2026, $onOrBefore)->format('YmdHis'));
    }

    public static function nextDayOfWeekDataProvider(): array
    {
        return [
            'same weekday, after' => [3, false, '20261202'],
            'same weekday, on or after' => [3, true, '20261125'],
            'weekday earlier in the week' => [1, false, '20261130'],
            'weekday later in the week' => [5, false, '20261127'],
        ];
    }

    #[Test]
    #[DataProvider('nextDayOfWeekDataProvider')]
    public function nextDayOfWeekReturnsTheNextWeekday(int $weekday, bool $onOrAfter, string $expected): void
    {
        self::assertSame($expected . '000000', CalculateDateTime::nextDayOfWeek($weekday, 25, 11, 2026, $onOrAfter)->format('YmdHis'));
    }

    #[Test]
    public function prevAndNextDayOfWeekUseTodayWithoutDate(): void
    {
        $today = CalendarDateTime::generateByYmd(gmdate('Ymd'));
        $previous = CalculateDateTime::prevDayOfWeek($today->getDayOfWeek(), 0, 0, 0, true);
        $next = CalculateDateTime::nextDayOfWeek($today->getDayOfWeek(), 0, 0, 0, true);
        self::assertSame($today->format('YmdHis'), $previous->format('YmdHis'));
        self::assertSame($today->format('YmdHis'), $next->format('YmdHis'));
    }

    #[Test]
    public function getDaysInMonthReturnsTheNumberOfDays(): void
    {
        self::assertSame(30, CalculateDateTime::getDaysInMonth(11, 2026));
        self::assertSame(28, CalculateDateTime::getDaysInMonth(2, 2026));
        self::assertSame(29, CalculateDateTime::getDaysInMonth(2, 2028));
        self::assertSame((int)gmdate('t'), CalculateDateTime::getDaysInMonth());
    }

    #[Test]
    public function getDayByWeekReturnsTheWeekdayOfTheWeek(): void
    {
        WeekStart::set('monday');
        self::assertSame('20260209000000', CalculateDateTime::getDayByWeek(2026, 7, 1)->format('YmdHis'));
        self::assertSame('20260215000000', CalculateDateTime::getDayByWeek(2026, 7, 0)->format('YmdHis'));

        WeekStart::set('sunday');
        self::assertSame('20260208000000', CalculateDateTime::getDayByWeek(2026, 7, 0)->format('YmdHis'));
        self::assertSame('20260214000000', CalculateDateTime::getDayByWeek(2026, 7, 6)->format('YmdHis'));
    }
}
