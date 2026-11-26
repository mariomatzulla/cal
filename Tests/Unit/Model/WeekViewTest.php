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
use MarioMatzulla\Cal\Model\WeekView;
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(WeekView::class)]
final class WeekViewTest extends UnitTestCase
{
    use ViewModelTestTrait;

    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    /**
     * @return array<string, int> Ymd => number of events
     */
    private static function eventCounts(WeekView $week): array
    {
        return array_map(static fn(DayView $day): int => count($day->getEvents(), COUNT_RECURSIVE) - count($day->getEvents()), $week->getDays());
    }

    #[Test]
    public function constructorCreatesTheDaysOfTheWeek(): void
    {
        $week = new WeekView(48, 2026);
        self::assertSame(48, $week->getWeek());
        self::assertSame(2026, $week->getYear());
        self::assertSame(11, $week->getMonth());
        self::assertSame(11, $week->getParentMonth());
        self::assertSame('20261123', $week->getWeekStart());
        self::assertSame('20261130', $week->getWeekEnd());
        self::assertSame(['20261123', '20261124', '20261125', '20261126', '20261127', '20261128', '20261129'], array_map('strval', array_keys($week->getDays())));
        self::assertSame(array_fill_keys(array_keys($week->getDays()), []), $week->getAlldays());
        self::assertFalse($week->hasEvents());
        self::assertFalse($week->isHasEvents());
        self::assertFalse($week->isWeekHasEvent());
        self::assertSame(array_fill(0, 7, false), $week->isDayHasEvent());
        self::assertSame(9, $week->getCurrentDayIndex());
    }

    #[Test]
    public function weekStartingOnSunday(): void
    {
        WeekStart::set(WeekStart::SUNDAY);
        $week = new WeekView(48, 2026, 12);
        self::assertSame('20261122', $week->getWeekStart());
        self::assertSame(12, $week->getParentMonth());
        self::assertSame(12, $week->getDays()['20261122']->getParentMonth());
    }

    #[Test]
    public function eventOfOneDay(): void
    {
        $week = new WeekView(48, 2026);
        $week->addEvent(self::createEvent('202611251000', '202611251100'));
        $week->addEvent(self::createEvent('202612021000', '202612021100'));
        self::assertTrue($week->hasEvents());
        self::assertSame([false, false, false, true, false, false, false], $week->isDayHasEvent());
        self::assertSame(1, self::eventCounts($week)['20261125']);
        self::assertSame(1, array_sum(self::eventCounts($week)));
    }

    #[Test]
    public function alldayEventIsAddedOnceAndMarksAllItsDays(): void
    {
        $week = new WeekView(48, 2026);
        $event = self::createEvent('202611260000', '202611280000', true);
        $week->addEvent($event);
        self::assertSame([$event], $week->getAlldays()['20261126']);
        self::assertSame([false, false, false, false, true, true, true], $week->isDayHasEvent());
        self::assertTrue($week->getDays()['20261127']->getHasAlldayEvents());
        self::assertSame(1, array_sum(self::eventCounts($week)));
    }

    #[Test]
    public function eventFromThePreviousWeekStartsOnTheFirstDay(): void
    {
        $week = new WeekView(48, 2026);
        $event = self::createEvent('202611201000', '202611241200');
        $week->addEvent($event);
        self::assertSame([$event], $week->getAlldays()['20261123']);
        // indexed by the day of the week, 0 is Sunday
        self::assertSame([false, true, true, false, false, false, false], $week->isDayHasEvent());
        self::assertTrue($week->getDays()['20261124']->getHasAlldayEvents());
    }

    #[Test]
    public function eventIntoTheNextWeekEndsOnTheLastDay(): void
    {
        $week = new WeekView(48, 2026);
        $week->addEvent(self::createEvent('202611281000', '202612031200'));
        self::assertTrue($week->getDays()['20261129']->getHasAlldayEvents());
        self::assertSame(1, array_sum(self::eventCounts($week)));
    }

    #[Test]
    public function eventOutsideOfTheWeekIsIgnored(): void
    {
        $week = new WeekView(48, 2026);
        $week->addEvent(self::createEvent('202612051000', '202612061200'));
        $week->addEvent(self::createEvent('202611101000', '202611121200'));
        self::assertFalse($week->hasEvents());
    }

    #[Test]
    public function eventsAroundTheTurnOfTheYear(): void
    {
        // 2026-12-28 to 2027-01-03 is week 53 of 2026
        $week = new WeekView(53, 2026);
        self::assertSame('20261228', $week->getWeekStart());
        $week->addEvent(self::createEvent('202701021000', '202701021100'));
        $week->addEvent(self::createEvent('202612301000', '202701021100'));
        $week->addEvent(self::createEvent('202612201000', '202701031100'));
        $counts = self::eventCounts($week);
        self::assertSame([1, 1, 1], [$counts['20261228'], $counts['20261230'], $counts['20270102']]);
        self::assertSame(3, array_sum($counts));

        // 2025-12-29 to 2026-01-04 is week 1 of 2026
        $week = new WeekView(1, 2026);
        self::assertSame('20251229', $week->getWeekStart());
        $week->addEvent(self::createEvent('202512301000', '202512301100'));
        $week->addEvent(self::createEvent('202512311000', '202601021100'));
        $week->addEvent(self::createEvent('202512151000', '202512311100'));
        // the event from week 51 starts on the first day of the week, not in January
        $counts = self::eventCounts($week);
        self::assertSame([1, 1, 1], [$counts['20251229'], $counts['20251230'], $counts['20251231']]);
        self::assertSame(3, array_sum($counts));
    }

    #[Test]
    public function currentAndSelectedDay(): void
    {
        $week = new WeekView(48, 2026);
        $week->setCurrent(CalendarDateTime::generateByYmd('20261202'));
        $week->setSelected(CalendarDateTime::generateByYmd('20251126'));
        self::assertFalse($week->isCurrent());
        self::assertFalse($week->isSelected());

        $week->setCurrent(CalendarDateTime::generateByYmd('20261125'));
        $week->setSelected(CalendarDateTime::generateByYmd('20261126'));
        self::assertTrue($week->isCurrent());
        self::assertTrue($week->isSelected());
        self::assertSame(2, $week->getCurrentDayIndex());
        self::assertTrue($week->getDays()['20261125']->isCurrent());
        self::assertTrue($week->getDays()['20261126']->isSelected());
    }
}
