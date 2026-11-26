<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Domain\Repository;

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
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(EventRepository::class)]
final class EventRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private EventRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');
        $this->subject = $this->get(EventRepository::class);
    }

    /**
     * @param iterable<Event> $events
     * @return list<int>
     */
    private static function uids(iterable $events): array
    {
        $uids = [];
        foreach ($events as $event) {
            $uids[] = (int)($event instanceof Event ? $event->getUid() : $event['uid']);
        }
        sort($uids);
        return $uids;
    }

    private static function november(): SearchEventsEvent
    {
        return new SearchEventsEvent(new \DateTime('2026-11-01'), new \DateTime('2026-11-30'), []);
    }

    #[Test]
    public function findAllForEventFindsTheEventsOfTheRange(): void
    {
        // single event in the range, long event around the range, recurring event by its index
        self::assertSame([1, 2, 6], self::uids($this->subject->findAllForEvent(self::november(), [1], [2])));
        self::assertSame([1, 6], self::uids($this->subject->findAllForEvent(self::november(), [1], [])));
        self::assertSame([1, 3, 5, 6], self::uids($this->subject->findAllForEvent(self::november(), [], [])));
        self::assertSame([3], self::uids($this->subject->findAllForEvent(self::november(), [], [], [6])));
    }

    #[Test]
    public function findEventRespectsCalendarsAndPages(): void
    {
        self::assertSame([1], self::uids($this->subject->findEvent(1, [1, 1])));
        self::assertSame([], self::uids($this->subject->findEvent(1, [2])));
        self::assertSame([], self::uids($this->subject->findEvent(1, [], [6])));
        self::assertSame([], self::uids($this->subject->findEvent(4, [])));
    }

    #[Test]
    public function findUidsAndCounts(): void
    {
        self::assertSame([3], array_map('intval', $this->subject->findUidsForEventsWithPage(6)));
        self::assertSame([1, 2, 6], $this->subject->findUidsForCalendar(1));
        self::assertSame([1, 2, 6], self::uids($this->subject->findAllForCalendar(1)));
        self::assertSame([1, 2, 6], self::uids($this->subject->findAllForCalendarNative(1)));
        self::assertSame(4, $this->subject->countWithPage(5));
        self::assertSame(5, $this->subject->countAll());
    }

    #[Test]
    public function findRecurringEventsIncludesEventsWithAdditionalDates(): void
    {
        self::assertSame([2, 3], self::uids($this->subject->findRecurringEvents()));
        $pids = array_map('intval', array_column($this->subject->findPidsOfRecurringEvents(), 'pid'));
        sort($pids);
        self::assertSame([5, 6], $pids);
    }

    #[Test]
    public function checkFindsEventsWithoutIcalendarData(): void
    {
        // the event of the imported calendar (type 1) is not checked
        self::assertSame([1], self::uids($this->subject->check()));
    }

    #[Test]
    public function caldavMethods(): void
    {
        $this->subject->updateWithIcsUid(1, 'new-uid');
        $this->subject->updateWithCaldavData(1, 'DATA');
        $row = $this->subject->findAllForCalendarAndUri(1, 'new-uid');
        self::assertSame(['Single', 'DATA'], [$row['title'], $row['caldav_data']]);
        self::assertSame(0, $this->subject->findIcsMetaData(1)['sequence']);
        $this->subject->updateWithCaldavData(1, 'DATA 2', 4);
        $meta = $this->subject->findIcsMetaData(1);
        self::assertSame([4, 'DATA 2'], [$meta['sequence'], $meta['data']]);
        self::assertIsInt($meta['crdate']);
        self::assertIsInt($meta['tstamp']);
        self::assertSame(['crdate' => 0, 'tstamp' => 0, 'sequence' => 0, 'data' => ''], $this->subject->findIcsMetaData(999));

        self::assertFalse($this->subject->findAllForCalendarAndUri(1, 'd'));
        self::assertSame('Deleted', $this->subject->findAllForCalendarAndUri(1, 'd', true)['title']);
        self::assertFalse($this->subject->findAllForCalendarAndUri(2, 'new-uid'));

        $this->subject->persistEvent(['pid' => 5, 'calendar_id' => 1, 'title' => 'Created', 'ics_uid' => 'g']);
        self::assertSame('Created', $this->subject->findAllForCalendarAndUri(1, 'g')['title']);

        $this->subject->updateEvent(1, 'd', ['title' => 'Restored', 'deleted' => 0]);
        self::assertSame('Restored', $this->subject->findAllForCalendarAndUri(1, 'd')['title']);
        // the calendar must match
        $this->subject->updateEvent(2, 'g', ['title' => 'Other']);
        self::assertSame('Created', $this->subject->findAllForCalendarAndUri(1, 'g')['title']);
    }

    #[Test]
    public function importedEvents(): void
    {
        self::assertTrue($this->subject->isTempEvent(5));
        self::assertFalse($this->subject->isTempEvent(1));
        self::assertSame([5], $this->subject->removeTempEventsOfCalendar(3));
        self::assertSame([], $this->subject->removeTempEventsOfCalendar(3));
        self::assertFalse($this->subject->isTempEvent(5));

        $this->subject->insertRows([]);
        $this->subject->insertRows([
            ['pid' => 5, 'calendar_id' => 3, 'title' => 'A', 'isTemp' => 1, 'ics_uid' => 'x'],
            ['pid' => 5, 'calendar_id' => 3, 'title' => 'B', 'isTemp' => 1, 'ics_uid' => 'y'],
        ]);
        self::assertCount(2, $this->subject->findUidsForCalendar(3));
    }
}
