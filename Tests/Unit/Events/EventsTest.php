<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Events;

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
use MarioMatzulla\Cal\Events\FilterEventsEvent;
use MarioMatzulla\Cal\Events\GetEventEvent;
use MarioMatzulla\Cal\Events\GetLocationEvent;
use MarioMatzulla\Cal\Events\GetOrganizerEvent;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(FilterEventsEvent::class)]
#[CoversClass(GetEventEvent::class)]
#[CoversClass(GetLocationEvent::class)]
#[CoversClass(GetOrganizerEvent::class)]
#[CoversClass(SearchEventsEvent::class)]
final class EventsTest extends UnitTestCase
{
    #[Test]
    public function filterEventsEvent(): void
    {
        $event = new FilterEventsEvent(['a'], ['view' => 'list']);
        self::assertSame(['a'], $event->getEvents());
        self::assertSame(['view' => 'list'], $event->getSettings());
        $event->setEvents(['b']);
        self::assertSame(['b'], $event->getEvents());
    }

    #[Test]
    public function getEventEvent(): void
    {
        $date = new CalendarDateTime();
        $event = new GetEventEvent('tx_cal_phpicalendar', 5, $date, [1, 2], [3], [4]);
        self::assertSame('tx_cal_phpicalendar', $event->getType());
        self::assertSame(5, $event->getUid());
        self::assertSame($date, $event->getDate());
        self::assertSame([1, 2], $event->getCalendarIds());
        self::assertSame([3], $event->getExcludedCalendarIds());
        self::assertSame([4], $event->getStoragePageIds());
        self::assertNull($event->getEvent());
        $event->setEvent('found');
        self::assertSame('found', $event->getEvent());
        self::assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());

        $defaults = new GetEventEvent('type', 1, $date, []);
        self::assertSame([], $defaults->getExcludedCalendarIds());
        self::assertSame([], $defaults->getStoragePageIds());
    }

    #[Test]
    public function getLocationEvent(): void
    {
        $event = new GetLocationEvent('type', 7, [4]);
        self::assertSame('type', $event->getType());
        self::assertSame(7, $event->getUid());
        self::assertSame([4], $event->getStoragePageIds());
        self::assertNull($event->getLocation());
        $event->setLocation('location');
        self::assertSame('location', $event->getLocation());
        self::assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
        self::assertSame([], (new GetLocationEvent('type', 1))->getStoragePageIds());
    }

    #[Test]
    public function getOrganizerEvent(): void
    {
        $event = new GetOrganizerEvent('type', 8, [4]);
        self::assertSame('type', $event->getType());
        self::assertSame(8, $event->getUid());
        self::assertSame([4], $event->getStoragePageIds());
        self::assertNull($event->getOrganizer());
        $event->setOrganizer('organizer');
        self::assertSame('organizer', $event->getOrganizer());
        self::assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
        self::assertSame([], (new GetOrganizerEvent('type', 1))->getStoragePageIds());
    }

    #[Test]
    public function searchEventsEvent(): void
    {
        $start = new \DateTime('2026-11-01');
        $end = new \DateTime('2026-11-30');
        $event = new SearchEventsEvent($start, $end, [1], [2], [3]);
        self::assertSame($start, $event->getStart());
        self::assertSame($end, $event->getEnd());
        self::assertSame([1], $event->getCalendarIds());
        self::assertSame([2], $event->getExcludedCalendarIds());
        self::assertSame([3], $event->getStoragePageIds());
        self::assertSame([], $event->getEvents());
        $event->setEvents(['x']);
        self::assertSame(['x'], $event->getEvents());
        $defaults = new SearchEventsEvent($start, $end, []);
        self::assertSame([], $defaults->getExcludedCalendarIds());
        self::assertSame([], $defaults->getStoragePageIds());
    }
}
