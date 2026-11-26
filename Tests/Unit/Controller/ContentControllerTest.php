<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Controller;

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
use MarioMatzulla\Cal\Controller\ContentController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Helpers of the controller which the frontend tests (Tests/Functional/Controller) do not reach with every case
 */
#[CoversClass(ContentController::class)]
final class ContentControllerTest extends UnitTestCase
{
    private static function controller(array $settings): object
    {
        $controller = new class () extends ContentController {
            public function call(string $method, mixed ...$arguments): mixed
            {
                return $this->$method(...$arguments);
            }
        };
        $controller->setSettings($settings);
        return $controller;
    }

    private static function event(int $uid, string $start, string $end, string $title = 'Event'): object
    {
        return new class ($uid, new \DateTime($start), new \DateTime($end), $title) {
            public function __construct(private int $uid, private \DateTime $start, private \DateTime $end, private string $title) {}

            public function getUid(): int
            {
                return $this->uid;
            }

            public function getModelType(): string
            {
                return 'tx_cal_event';
            }

            public function getStart(): \DateTime
            {
                return $this->start;
            }

            public function getEnd(): \DateTime
            {
                return $this->end;
            }

            public function getTitle(): string
            {
                return $this->title;
            }
        };
    }

    #[Test]
    public function viewsWithOwnPageCanBeLinked(): void
    {
        self::assertSame([], self::controller([])->call('getAllowedViewsByViewPid'));
        self::assertSame(['event', 'organizer'], self::controller(['view' => [
            'event' => ['eventViewPid' => '12'],
            'location' => ['locationViewPid' => ''],
            'organizer' => ['organizerViewPid' => '13'],
        ]])->call('getAllowedViewsByViewPid'));
    }

    #[Test]
    public function listEventsInTheRange(): void
    {
        $events = [
            self::event(1, '2026-06-10 10:00', '2026-06-10 11:00', 'B'),
            // base record of a recurring event before the range
            self::event(2, '2026-05-01 10:00', '2026-05-01 11:00'),
            // after the range
            self::event(3, '2026-07-02 10:00', '2026-07-02 11:00'),
            // started before, ends in the range
            self::event(4, '2026-05-30 10:00', '2026-06-02 11:00'),
            self::event(5, '2026-06-10 10:00', '2026-06-10 11:00', 'A'),
            // duplicate
            self::event(1, '2026-06-10 10:00', '2026-06-10 11:00', 'B'),
        ];
        $start = new \DateTime('2026-06-01 00:00');
        $end = new \DateTime('2026-06-30 23:59:59');
        $list = self::controller([])->call('prepareListEvents', $events, $start, $end);
        self::assertSame([4, 5, 1], array_map(static fn($event): int => $event->getUid(), $list));

        $list = self::controller(['view' => ['list' => ['maxEvents' => '2']]])->call('prepareListEvents', $events, $start, $end);
        self::assertSame([4, 5], array_map(static fn($event): int => $event->getUid(), $list));
    }
}
