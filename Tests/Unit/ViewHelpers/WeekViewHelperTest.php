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
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Tests\Unit\Model\ViewModelTestTrait;
use MarioMatzulla\Cal\ViewHelpers\BaseWeekViewHelper;
use MarioMatzulla\Cal\ViewHelpers\WeekViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(WeekViewHelper::class)]
#[CoversClass(BaseWeekViewHelper::class)]
final class WeekViewHelperTest extends UnitTestCase
{
    use ViewHelperTestTrait;
    use ViewModelTestTrait;

    /**
     * Events of week 48/2026 (23.11. - 29.11.)
     *
     * @return list<Event>
     */
    private static function events(): array
    {
        $events = [];
        foreach ([
            'A' => ['202611251000', '202611251100'],
            'B' => ['202611251030', '202611251200'],
            'C' => ['202611250600', '202611250800'],
            'D' => ['202611262200', '202611270000'],
            'E' => ['202611251307', '202611251320'],
            'F' => ['202611240000', '202611260000', true],
            'G' => ['202611201000', '202612011000'],
            'H' => ['202611220000', '202611241000'],
            'I' => ['202611281000', '202612021000'],
        ] as $title => $times) {
            $event = self::createEvent(...$times);
            $event->setTitle($title);
            $events[] = $event;
        }
        return $events;
    }

    /**
     * @param array<string, mixed> $daySettings
     * @return array<string, mixed>
     */
    private function render(string $view, array $daySettings = ['dayStart' => '0700', 'dayEnd' => '23', 'gridLength' => 30], ?array $events = null): array
    {
        self::assertSame('|', $this->renderViewHelper(new WeekViewHelper(), [
            'events' => $events ?? self::events(),
            'dateObject' => new \DateTime('2026-11-25'),
            'getdate' => new \DateTime('2026-11-26'),
            'view' => $view,
        ], ['settings' => ['view' => ['day' => $daySettings]]]));
        return $this->renderedVariables[0];
    }

    /**
     * @param list<array<string, mixed>> $column
     * @return list<string>
     */
    private static function describeColumn(array $column): array
    {
        return array_map(static fn(array $entry): string => sprintf('%s %d+%d %s/%s', $entry['event']->getTitle(), $entry['offset'], $entry['duration'], $entry['columnLeft'], $entry['columnWidth']), $column);
    }

    /**
     * @param array<int, array<int, mixed>> $matrix
     * @return array<int, array<int, string>>
     */
    private static function describeMatrix(array $matrix): array
    {
        return array_map(static fn(array $rows): array => array_map(static fn($entry): string => is_object($entry) ? $entry->getTitle() . $entry->matrixValue : 'x', $rows), $matrix);
    }

    #[Test]
    public function weekView(): void
    {
        $variables = $this->render('week');
        self::assertSame(['20261123', '20261124', '20261125', '20261126', '20261127', '20261128', '20261129'], array_map('strval', array_keys($variables['days'])));
        self::assertTrue($variables['days']['20261126']->isSelected());
        self::assertSame([
            0 => [0 => 'G7', 2 => 'H2'],
            1 => [0 => 'x', 1 => 'F3', 2 => 'x'],
            2 => [0 => 'x', 1 => 'x'],
            3 => [0 => 'x', 1 => 'x'],
            4 => [0 => 'x'],
            5 => [0 => 'x', 1 => 'I2'],
            6 => [0 => 'x', 1 => 'x'],
        ], self::describeMatrix($variables['theMatrixAllday']));
        self::assertSame([0, 1, 2], $variables['rowspanArray']);

        // C starts before the grid, B overlaps A, D is cut at the end of the grid
        $columns = array_map(self::describeColumn(...), $variables['eventColumns']);
        self::assertSame(['C 0+60 0/100', 'A 180+60 0/50', 'B 210+90 50/50', 'E 367+13 0/100'], $columns[2]);
        self::assertSame(['D 900+60 0/100'], $columns[3]);
        self::assertSame([[], [], [], [], []], [$columns[0], $columns[1], $columns[4], $columns[5], $columns[6]]);

        self::assertCount(16, $variables['timeCells']);
        self::assertSame(7, $variables['timeCells'][0]['value']);
        self::assertSame('070000', $variables['timeCells'][0]['time']->format('His'));
        self::assertSame(22, $variables['timeCells'][15]['value']);
        self::assertArrayNotHasKey('alldayEvents', $variables);
    }

    #[Test]
    public function weekViewExtendsTheGridToTheEvents(): void
    {
        $variables = $this->render('week', ['dayStart' => '0700', 'dayEnd' => '2300', 'gridLength' => 30, 'extendGridToEvents' => 1]);
        // 06:00 (C) to 24:00 (D)
        self::assertCount(18, $variables['timeCells']);
        self::assertSame(6, $variables['timeCells'][0]['value']);
        self::assertSame(['D 960+120 0/100'], self::describeColumn($variables['eventColumns'][3]));
    }

    #[Test]
    public function dayView(): void
    {
        $variables = $this->render('day', ['dayStart' => '7', 'dayEnd' => '23:00', 'gridLength' => 0, 'extendGridToEvents' => 1]);
        self::assertSame('20261125', $variables['day']->getYmd());
        self::assertArrayNotHasKey('days', $variables);
        self::assertSame(['G', 'F'], array_map(static fn(Event $event): string => $event->getTitle(), $variables['alldayEvents']));
        // the grid starts at 06:00 because of C, the end of D on the next day does not count, 15 minutes grid
        self::assertCount(17, $variables['timeCells']);
        self::assertSame(['C 0+120 0/100', 'A 240+60 0/50', 'B 270+90 50/50', 'E 427+13 0/100'], self::describeColumn($variables['eventColumns'][0]));
    }

    #[Test]
    public function dayViewOfADayOutsideOfTheWeek(): void
    {
        $viewHelper = new WeekViewHelper();
        $this->renderViewHelper($viewHelper, ['events' => [], 'dateObject' => new \DateTime('2026-11-25'), 'view' => 'day']);
        $variables = $this->renderedVariables[0];
        self::assertSame([], $variables['alldayEvents']);
        self::assertSame([[]], $variables['eventColumns']);
        // default grid 07:00 - 23:00
        self::assertCount(16, $variables['timeCells']);
    }

    #[Test]
    public function defaultsWithoutSettings(): void
    {
        $this->renderViewHelper(new WeekViewHelper(), ['events' => [self::createEvent('202611251000', '202611251100')], 'dateObject' => new \DateTime('2026-11-25'), 'view' => 'week']);
        $variables = $this->renderedVariables[0];
        self::assertSame([[], [], ['20261125'], [], [], [], []], array_map(static fn(array $column): array => array_map(static fn(array $entry): string => $entry['event']->getStart()->format('Ymd'), $column), $variables['eventColumns']));
        self::assertSame(180, $variables['eventColumns'][2][0]['offset']);
        self::assertSame([], $variables['theMatrixAllday']);
        self::assertSame([0], $variables['rowspanArray']);
    }
}
