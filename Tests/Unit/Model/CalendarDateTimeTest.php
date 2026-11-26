<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Model;

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
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CalendarDateTime::class)]
final class CalendarDateTimeTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    #[Test]
    public function constructorCreatesTheCurrentTimeInUtc(): void
    {
        $before = time();
        $date = new CalendarDateTime();
        self::assertSame('UTC', $date->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $date->getTimestamp());
        self::assertLessThanOrEqual(time(), $date->getTimestamp());
    }

    #[Test]
    public function generateFromDateTimeTakesOverTheWallClockTime(): void
    {
        $source = new \DateTime('2026-02-28 13:14:15', new \DateTimeZone('Europe/Berlin'));
        $date = CalendarDateTime::generateFromDateTime($source);
        self::assertSame('20260228131415', $date->format('YmdHis'));
        self::assertSame('UTC', $date->getTimezone()->getName());
    }

    #[Test]
    public function generateByYmdHisParsesTheString(): void
    {
        self::assertSame('20261125153012', CalendarDateTime::generateByYmdHis('20261125153012')->format('YmdHis'));
    }

    #[Test]
    public function generateByYmdHisWithAnInvalidStringReturnsTheCurrentTime(): void
    {
        $date = CalendarDateTime::generateByYmdHis('2026');
        self::assertEqualsWithDelta(time(), $date->getTimestamp(), 5);
    }

    #[Test]
    public function generateByYmdParsesTheStringAtMidnight(): void
    {
        self::assertSame('20261125000000', CalendarDateTime::generateByYmd('20261125')->format('YmdHis'));
    }

    #[Test]
    public function generateByYmdWithAnInvalidStringReturnsTodayAtMidnight(): void
    {
        self::assertSame(gmdate('Ymd') . '000000', CalendarDateTime::generateByYmd('x')->format('YmdHis'));
    }

    #[Test]
    public function formatPassesPhpFormatsThrough(): void
    {
        $date = CalendarDateTime::generateByYmdHis('20261125153012');
        $expected = [
            't' => '30', 'Y' => '2026', 'm' => '11', 'd' => '25', 'H' => '15', 'i' => '30', 's' => '12',
            'W' => '48', 'w' => '3', 'Ymd' => '20261125', 'Hi' => '1530', 'His' => '153012',
            'YmdHis' => '20261125153012', 'U' => (string)$date->getTimestamp(),
        ];
        foreach ($expected as $format => $value) {
            self::assertSame($value, $date->format($format), $format);
        }
    }

    #[Test]
    public function formatUsesIcuPatternsForOtherFormats(): void
    {
        $date = CalendarDateTime::generateByYmdHis('20261125153012');
        self::assertSame('Wednesday, 25. November 2026', $date->format('EEEE, dd. MMMM yyyy'));
        self::assertSame('Mittwoch', $date->format('EEEE', 'de_DE'));
    }

    #[Test]
    public function compareMethods(): void
    {
        $early = CalendarDateTime::generateByYmdHis('20261125100000');
        $late = CalendarDateTime::generateByYmdHis('20261125100001');
        self::assertTrue($early->equals(CalendarDateTime::generateByYmdHis('20261125100000')));
        self::assertFalse($early->equals($late));
        self::assertTrue($early->before($late));
        self::assertFalse($late->before($early));
        self::assertTrue($late->after($early));
        self::assertFalse($early->after($late));
    }

    #[Test]
    public function addAndSubtractSeconds(): void
    {
        $date = CalendarDateTime::generateByYmdHis('20261125235959');
        self::assertSame($date, $date->addSeconds(1));
        self::assertSame('20261126000000', $date->format('YmdHis'));
        self::assertSame($date, $date->subtractSeconds(1));
        self::assertSame('20261125235959', $date->format('YmdHis'));
        $date->addSeconds(-59);
        self::assertSame('20261125235900', $date->format('YmdHis'));
        $date->subtractSeconds(-60);
        self::assertSame('20261126000000', $date->format('YmdHis'));
        $date->addSeconds();
        self::assertSame('20261126000000', $date->format('YmdHis'));
    }

    #[Test]
    public function getDayOfWeekAndWeekOfYear(): void
    {
        $sunday = CalendarDateTime::generateByYmd('20261129');
        self::assertSame(0, $sunday->getDayOfWeek());
        self::assertSame(48, $sunday->getWeekOfYear());
        WeekStart::set(WeekStart::SUNDAY);
        self::assertSame(49, $sunday->getWeekOfYear());
    }

    #[Test]
    public function copyTakesOverDateTimeAndTimezone(): void
    {
        $source = CalendarDateTime::generateByYmdHis('20260228131415');
        $source->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $date = CalendarDateTime::generateByYmdHis('20260131000000');
        $date->copy($source);
        self::assertSame($source->format('YmdHis'), $date->format('YmdHis'));
        self::assertSame('Europe/Berlin', $date->getTimezone()->getName());
    }

    #[Test]
    public function gettersAndSetters(): void
    {
        $date = CalendarDateTime::generateByYmdHis('20261125153012');
        self::assertSame(2026, $date->getYear());
        self::assertSame(11, $date->getMonth());
        self::assertSame(25, $date->getDay());
        self::assertSame(15, $date->getHour());
        self::assertSame(30, $date->getMinute());
        self::assertSame(12, $date->getSecond());

        self::assertSame($date, $date->setYear(2027));
        self::assertSame($date, $date->setMonth(3));
        self::assertSame($date, $date->setDay(4));
        self::assertSame($date, $date->setHour(5));
        self::assertSame($date, $date->setMinute(6));
        self::assertSame($date, $date->setSecond(7));
        self::assertSame('20270304050607', $date->format('YmdHis'));

        $date->setHour();
        $date->setMinute();
        $date->setSecond();
        self::assertSame('20270304000000', $date->format('YmdHis'));
    }

    #[Test]
    public function isFuture(): void
    {
        self::assertTrue(CalendarDateTime::generateByYmd((string)((int)gmdate('Y') + 1) . '0101')->isFuture());
        self::assertFalse(CalendarDateTime::generateByYmd('20000101')->isFuture());
    }
}
