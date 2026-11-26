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
use MarioMatzulla\Cal\Model\DayView;
use MarioMatzulla\Cal\Model\TimeView;
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(DayView::class)]
#[CoversClass(TimeView::class)]
final class DayViewTest extends UnitTestCase
{
    use ViewModelTestTrait;

    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    #[Test]
    public function constructorSetsTheDate(): void
    {
        $day = new DayView(29, 11, 2026);
        self::assertSame(29, $day->getDay());
        self::assertSame(11, $day->getMonth());
        self::assertSame(2026, $day->getYear());
        self::assertSame(11, $day->getParentMonth());
        self::assertSame('20261129', $day->getYmd());
        self::assertSame('20261129000000', $day->getTime()->format('YmdHis'));
        // Sunday is the last day of a week starting on Monday
        self::assertSame(6, $day->getWeekdayNumber());

        WeekStart::set(WeekStart::SUNDAY);
        $day = new DayView(29, 11, 2026, 12);
        self::assertSame(0, $day->getWeekdayNumber());
        self::assertSame(12, $day->getParentMonth());
    }

    #[Test]
    public function timeViewAccessors(): void
    {
        $day = new DayView(25, 11, 2026);
        self::assertFalse($day->isCurrent());
        self::assertFalse($day->isSelected());
        self::assertSame(100, $day->getWeekDayLength());
        self::assertSame('eee', $day->getWeekDayFormat());
        $day->setDay('3');
        $day->setMonth('4');
        $day->setYear('2027');
        $day->setWeekdayNumber('2');
        $day->setParentMonth(5);
        self::assertSame([3, 4, 2027, 2, 5], [$day->getDay(), $day->getMonth(), $day->getYear(), $day->getWeekdayNumber(), $day->getParentMonth()]);

        $date = CalendarDateTime::generateByYmd('20261125');
        self::assertSame('Wed', $day->getWeekdayString($date));
        $day->setWeekDayFormat('EEEE');
        $day->setWeekDayLength('2');
        self::assertSame('We', $day->getWeekdayString($date));
    }

    #[Test]
    public function eventsAreGroupedByStartTime(): void
    {
        $day = new DayView(25, 11, 2026);
        self::assertFalse($day->hasEvents());
        self::assertFalse($day->isHasEvents());
        $first = self::createEvent('202611251000', '202611251100');
        $second = self::createEvent('202611251000', '202611251200');
        $day->addEvent($first);
        $day->addEvent($second);
        self::assertSame(['1000' => [$first->getUid() => $first, $second->getUid() => $second]], $day->getEvents());
        self::assertTrue($day->hasEvents());
        self::assertTrue($day->isHasEvents());

        $day->setEvents([]);
        self::assertSame([], $day->getEvents());
        self::assertFalse($day->getHasAlldayEvents());
        $day->setHasAlldayEvents(true);
        self::assertTrue($day->getHasAlldayEvents());
        self::assertTrue($day->hasEvents());
    }

    #[Test]
    public function currentAndSelectedOnlyMatchTheSameDay(): void
    {
        $day = new DayView(25, 11, 2026);
        $day->setCurrent(CalendarDateTime::generateByYmd('20261126'));
        $day->setSelected(CalendarDateTime::generateByYmd('20251125'));
        self::assertFalse($day->isCurrent());
        self::assertFalse($day->isSelected());
        $day->setCurrent(CalendarDateTime::generateByYmd('20261125'));
        $day->setSelected(CalendarDateTime::generateByYmd('20261125'));
        self::assertTrue($day->isCurrent());
        self::assertTrue($day->isSelected());
    }

    #[Test]
    public function getDayClasses(): void
    {
        $day = new DayView(25, 11, 2026);
        self::assertSame('day weekday3', $day->getDayClasses());

        $day = new DayView(1, 12, 2026, 11);
        $day->setCurrent(CalendarDateTime::generateByYmd('20261201'));
        $day->setSelected(CalendarDateTime::generateByYmd('20261201'));
        $day->setHasAlldayEvents(true);
        self::assertSame('day weekday2 currentDay selectedDay withEventDay monthOff', $day->getDayClasses());

        $day = new DayView(1, 12, 2026);
        $day->addEvent(self::createEvent('202612011000', '202612011100'));
        self::assertSame('day weekday2 withEventDay', $day->getDayClasses());
    }
}
