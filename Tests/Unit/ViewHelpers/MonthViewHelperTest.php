<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\ViewHelpers;

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
use MarioMatzulla\Cal\Model\MonthView;
use MarioMatzulla\Cal\Tests\Unit\Model\ViewModelTestTrait;
use MarioMatzulla\Cal\Utility\WeekStart;
use MarioMatzulla\Cal\ViewHelpers\BaseWeekViewHelper;
use MarioMatzulla\Cal\ViewHelpers\MonthViewHelper;
use MarioMatzulla\Cal\ViewHelpers\MonthWeekViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(MonthViewHelper::class)]
#[CoversClass(MonthWeekViewHelper::class)]
#[CoversClass(BaseWeekViewHelper::class)]
final class MonthViewHelperTest extends UnitTestCase
{
    use ViewHelperTestTrait;
    use ViewModelTestTrait;

    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    #[Test]
    public function monthViewHelperCreatesTheMonthWithTheEvents(): void
    {
        WeekStart::set(WeekStart::SUNDAY);
        $event = self::createEvent('202611251000', '202611251100');
        self::assertSame('|', $this->renderViewHelper(new MonthViewHelper(), [
            'events' => [$event],
            'dateObject' => new \DateTime('2026-11-15'),
        ]));
        $variables = $this->renderedVariables[0];
        $month = $variables['monthObject'];
        self::assertInstanceOf(MonthView::class, $month);
        self::assertSame([11, 2026], [$month->getMonth(), $month->getYear()]);
        self::assertTrue($month->hasEvents());
        // the first week starts on sunday, 1.11.2026
        self::assertSame('20261101', $variables['weekdayObject0']->format('Ymd'));
        self::assertSame('20261107', $variables['weekdayObject6']->format('Ymd'));
    }

    #[Test]
    public function monthViewHelperUsesTheCurrentMonthWithoutDate(): void
    {
        $this->renderViewHelper(new MonthViewHelper(), ['events' => []]);
        $month = $this->renderedVariables[0]['monthObject'];
        self::assertSame([(int)gmdate('n'), (int)gmdate('Y')], [$month->getMonth(), $month->getYear()]);
        self::assertTrue($month->isCurrent());
    }

    #[Test]
    public function monthWeekViewHelperRendersEachWeek(): void
    {
        $month = new MonthView(11, 2026);
        $month->addEvent(self::createEvent('202611251000', '202611251100'));
        $month->addEvent(self::createEvent('202611240000', '202611260000', true));
        $month->addEvent(self::createEvent('202611260000', '202611270000', true));
        $month->setSelected(\MarioMatzulla\Cal\Model\CalendarDateTime::generateByYmd('20261103'));
        $month->getWeeks()['2026_47']->setCurrent(\MarioMatzulla\Cal\Model\CalendarDateTime::generateByYmd('20261118'));

        self::assertSame('||||||', $this->renderViewHelper(new MonthWeekViewHelper(), ['month' => $month]));
        self::assertCount(6, $this->renderedVariables);

        [$first, $second, , $fourth, $fifth] = $this->renderedVariables;
        self::assertSame('emptyWeek', $first['weekClasses']);
        self::assertSame('selectedWeek emptyWeek', $second['weekClasses']);
        self::assertSame('currentWeek emptyWeek', $fourth['weekClasses']);
        self::assertSame('withEventWeek', $fifth['weekClasses']);
        self::assertSame($month->getWeeks()['2026_48'], $fifth['week']);

        // week 48: the all-day events in row 0 (24.-26. and 26.-27. overlap on the 26th), the timed event below
        self::assertSame(2, $fifth['rowspan']);
        self::assertSame([0, 1], $fifth['rowspanArray']);
        $matrix = $fifth['theMatrix'];
        self::assertSame(3, $matrix[1][0]->matrixValue);
        self::assertTrue($matrix[2][0]);
        self::assertSame(2, $matrix[3][1]->matrixValue);
        self::assertSame(1, $matrix[2][1]->matrixValue);

        self::assertSame(1, $first['rowspan']);
        self::assertSame([0], $first['rowspanArray']);
        self::assertSame([], $first['theMatrix']);
    }

    #[Test]
    public function eventsEndingAtMidnightBelongToTheDayBefore(): void
    {
        $month = new MonthView(11, 2026);
        // ends at the start of week 48, shown in week 47 only
        $month->addEvent(self::createEvent('202611222000', '202611230000'));
        // ends at midnight of the next day: one day
        $month->addEvent(self::createEvent('202611262200', '202611270000'));
        $this->renderViewHelper(new MonthWeekViewHelper(), ['month' => $month]);
        $week47 = $this->renderedVariables[3]['theMatrix'];
        $week48 = $this->renderedVariables[4]['theMatrix'];
        self::assertSame(1, $week47[6][0]->matrixValue);
        self::assertSame([3], array_keys($week48));
        self::assertSame(1, $week48[3][0]->matrixValue);
    }
}
