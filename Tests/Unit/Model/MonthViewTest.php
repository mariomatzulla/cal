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
use MarioMatzulla\Cal\Model\MonthView;
use MarioMatzulla\Cal\Model\WeekView;
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(MonthView::class)]
final class MonthViewTest extends UnitTestCase
{
    use ViewModelTestTrait;

    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    public static function weeksDataProvider(): array
    {
        return [
            'november 2026' => [11, 2026, 'monday', ['2026_44', '2026_45', '2026_46', '2026_47', '2026_48', '2026_49']],
            'february 2027, four weeks' => [2, 2027, 'monday', ['2027_5', '2027_6', '2027_7', '2027_8']],
            'december 2025, last week is week 1' => [12, 2025, 'monday', ['2025_49', '2025_50', '2025_51', '2025_52', '2026_1']],
            'january 2027, first week is week 53' => [1, 2027, 'monday', ['2026_53', '2027_1', '2027_2', '2027_3', '2027_4']],
            'january 2026' => [1, 2026, 'monday', ['2026_1', '2026_2', '2026_3', '2026_4', '2026_5']],
            'november 2026, sunday' => [11, 2026, 'sunday', ['2026_45', '2026_46', '2026_47', '2026_48', '2026_49']],
        ];
    }

    /**
     * @param list<string> $weeks
     */
    #[Test]
    #[DataProvider('weeksDataProvider')]
    public function constructorCreatesTheWeeks(int $month, int $year, string $weekStart, array $weeks): void
    {
        WeekStart::set($weekStart);
        $view = new MonthView($month, $year);
        self::assertSame($month, $view->getMonth());
        self::assertSame($year, $view->getYear());
        self::assertSame($weeks, array_keys($view->getWeeks()));
        self::assertContainsOnlyInstancesOf(WeekView::class, $view->getWeeks());
        self::assertFalse($view->hasEvents());
    }

    #[Test]
    public function eventsAreAddedToAllTheirWeeks(): void
    {
        $view = new MonthView(11, 2026);
        $view->addEvent(self::createEvent('202611251000', '202611251100'));
        $view->addEvent(self::createEvent('202611281000', '202612031100'));
        self::assertTrue($view->hasEvents());
        self::assertTrue($view->getWeeks()['2026_48']->hasEvents());
        self::assertTrue($view->getWeeks()['2026_49']->hasEvents());
        self::assertFalse($view->getWeeks()['2026_47']->hasEvents());
    }

    #[Test]
    public function eventsAroundTheTurnOfTheYear(): void
    {
        $view = new MonthView(1, 2027);
        $view->addEvent(self::createEvent('202701021000', '202701021100'));
        self::assertTrue($view->getWeeks()['2026_53']->hasEvents());

        $view = new MonthView(12, 2025);
        $view->addEvent(self::createEvent('202512311000', '202512311100'));
        self::assertTrue($view->getWeeks()['2026_1']->hasEvents());

        $view = new MonthView(12, 2026);
        $view->addEvent(self::createEvent('202612301000', '202701051100'));
        self::assertTrue($view->getWeeks()['2026_53']->hasEvents());
    }

    #[Test]
    public function currentAndSelectedDay(): void
    {
        $view = new MonthView(11, 2026);
        $view->setCurrent(CalendarDateTime::generateByYmd('20261201'));
        $view->setSelected(CalendarDateTime::generateByYmd('20251125'));
        self::assertFalse($view->isCurrent());
        self::assertFalse($view->isSelected());

        $view->setCurrent(CalendarDateTime::generateByYmd('20261125'));
        $view->setSelected(CalendarDateTime::generateByYmd('20261126'));
        self::assertTrue($view->isCurrent());
        self::assertTrue($view->isSelected());
        self::assertTrue($view->getWeeks()['2026_48']->isCurrent());
        self::assertTrue($view->getWeeks()['2026_48']->getDays()['20261126']->isSelected());
    }

    #[Test]
    public function currentAndSelectedDayWithoutWeek(): void
    {
        $view = new MonthView(1, 2027);
        // 2027-01-01 is in week 53 of 2026, the key of the month view is 2026_53
        $view->setCurrent(CalendarDateTime::generateByYmd('20270101'));
        $view->setSelected(CalendarDateTime::generateByYmd('20270101'));
        self::assertTrue($view->isCurrent());
        self::assertTrue($view->isSelected());
    }
}
