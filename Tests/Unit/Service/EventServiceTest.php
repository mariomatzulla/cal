<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Service;

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
use MarioMatzulla\Cal\Domain\Model\Calendar;
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Model\EventRecDeviationModel;
use MarioMatzulla\Cal\Domain\Model\EventRecModel;
use MarioMatzulla\Cal\Domain\Model\Index;
use MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use MarioMatzulla\Cal\Events\GetEventEvent;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Service\EventService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(EventService::class)]
final class EventServiceTest extends UnitTestCase
{
    private EventRepository $eventRepository;
    private IndexRepository $indexRepository;
    private EventDeviationRepository $deviationRepository;

    /**
     * @var array{calendarIds: list<int>, recurringUids: list<int>}|null
     */
    private ?array $searchArguments = null;

    /**
     * @param list<int> $publicCalendars
     * @param list<int> $privateCalendars calendars of the logged in user (uid 9), calendar 7 belongs to group 3
     * @param list<Event> $events
     * @param array<int, list<Index>> $index index entries by event uid
     */
    private function createService(array $publicCalendars, array $privateCalendars = [], ?int $userId = null, array $events = [], array $index = [], array $deviations = [], array $groupIds = []): EventService
    {
        $calendars = array_map(static function (int $uid): Calendar {
            $calendar = new Calendar();
            $calendar->_setProperty('uid', $uid);
            return $calendar;
        }, $publicCalendars);
        $calendarRepository = $this->createStub(CalendarRepository::class);
        $calendarRepository->method('findPublicCalendarList')->willReturn($calendars);

        $context = $this->createStub(Context::class);
        $context->method('getPropertyFromAspect')->willReturnCallback(static fn(string $aspect, string $property): mixed => $property === 'id' ? ($userId ?? 0) : $groupIds);
        $ownerRepository = $this->createStub(CalendarOwnerRelationRepository::class);
        $ownerRepository->method('findCalendarListForFeUser')->willReturnCallback(static fn(int $uid, array $groups): array => array_merge($uid === 9 ? $privateCalendars : [], in_array(3, $groups, true) ? [7] : []));

        $result = $this->createStub(QueryResultInterface::class);
        $result->method('toArray')->willReturn($events);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->eventRepository->method('findAllForEvent')->willReturnCallback(function (SearchEventsEvent $event, array $calendarIds, array $recurringUids) use ($result): QueryResultInterface {
            $this->searchArguments = ['calendarIds' => $calendarIds, 'recurringUids' => $recurringUids];
            return $result;
        });
        $this->eventRepository->method('findEvent')->willReturnCallback(function (int $uid, array $calendarIds) use ($result): QueryResultInterface {
            $this->searchArguments = ['calendarIds' => $calendarIds, 'recurringUids' => [$uid]];
            return $result;
        });

        $this->indexRepository = $this->createStub(IndexRepository::class);
        $this->indexRepository->method('findEventUids')->willReturn(array_keys($index));
        $this->indexRepository->method('findByEventUidStartAndEnd')->willReturnCallback(static fn(int $uid): array => $index[$uid] ?? []);
        $this->deviationRepository = $this->createStub(EventDeviationRepository::class);
        $this->deviationRepository->method('findByUid')->willReturnCallback(static fn(int $uid): ?EventDeviation => $deviations[$uid] ?? null);

        return new EventService($context, $this->eventRepository, $calendarRepository, $ownerRepository, $this->indexRepository, $this->deviationRepository);
    }

    private static function event(int $uid, string $start): Event
    {
        $event = new Event();
        $event->_setProperty('uid', $uid);
        $event->setTitle('Event ' . $uid);
        $event->setStartDate(new \DateTime($start));
        $event->setStartTime(new \DateTime($start));
        $event->setEndDate(new \DateTime($start));
        $event->setEndTime((new \DateTime($start))->modify('+1 hour'));
        return $event;
    }

    private static function index(string $start, string $end, int $deviationUid = 0): Index
    {
        $index = new Index();
        $index->setStartDatetime((float)$start);
        $index->setEndDatetime((float)$end);
        $index->setEventDeviationUid($deviationUid);
        return $index;
    }

    private static function search(array $calendarIds = [], array $excluded = []): SearchEventsEvent
    {
        return new SearchEventsEvent(new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), $calendarIds, $excluded, [3]);
    }

    #[Test]
    public function searchFindsTheEventsWithTheirRecurringInstances(): void
    {
        $deviation = new EventDeviation();
        $deviation->_setProperty('uid', 4);
        $event = self::event(1, '2026-11-02 10:00');
        $service = $this->createService([1, 2], [], null, [$event], [1 => [
            self::index('20261103100000', '20261103110000'),
            self::index('20261104100000', '20261104110000', 4),
            self::index('20261105100000', '20261105110000', 99),
        ]], [4 => $deviation]);

        $search = self::search();
        $service($search);
        self::assertSame(['calendarIds' => [1, 2], 'recurringUids' => [1]], $this->searchArguments);
        $found = $search->getEvents();
        self::assertCount(4, $found);
        self::assertSame($event, $found[0]);
        self::assertInstanceOf(EventRecModel::class, $found[1]);
        self::assertSame('20261103100000', $found[1]->getStart()->format('YmdHis'));
        self::assertInstanceOf(EventRecDeviationModel::class, $found[2]);
        self::assertSame(4, $found[2]->getDeviationId());
        // the deviation does not exist (anymore)
        self::assertNotInstanceOf(EventRecDeviationModel::class, $found[3]);
    }

    #[Test]
    public function calendarsAreLimitedByTheSelectionAndTheLoggedInUser(): void
    {
        $service = $this->createService([1, 2, 3], [5, 6], 9);
        $service(self::search(['2', 5, 7], [5]));
        self::assertSame([2], $this->searchArguments['calendarIds']);

        $service(self::search([], [1]));
        self::assertSame([2, 3, 5, 6], $this->searchArguments['calendarIds']);
    }

    #[Test]
    public function calendarsOfTheGroupsOfTheUser(): void
    {
        // pseudo groups (-1, -2) of TYPO3 are ignored
        $service = $this->createService([1], [], 8, [], [], [], [-2, 0, 3]);
        $service(self::search());
        self::assertSame([1, 7], $this->searchArguments['calendarIds']);
    }

    #[Test]
    public function privateCalendarsNeedALoggedInUser(): void
    {
        $service = $this->createService([1], [5], 8);
        $service(self::search());
        self::assertSame([1], $this->searchArguments['calendarIds']);
    }

    #[Test]
    public function nothingIsSearchedWithoutAllowedCalendar(): void
    {
        $service = $this->createService([1], [], null, [self::event(1, '2026-11-02 10:00')]);
        $search = self::search([2]);
        $service($search);
        self::assertNull($this->searchArguments);
        self::assertSame([], $search->getEvents());

        $get = new GetEventEvent('tx_cal_event', 1, CalendarDateTime::generateByYmd('20261102'), [2]);
        $service($get);
        self::assertNull($this->searchArguments);
        self::assertNull($get->getEvent());
    }

    #[Test]
    public function getEventReturnsTheInstanceOfTheDate(): void
    {
        $event = self::event(1, '2026-11-02 10:00');
        $service = $this->createService([1], [], null, [$event], [1 => [self::index('20261103100000', '20261103110000')]]);

        $get = new GetEventEvent('tx_cal_event', 1, CalendarDateTime::generateByYmd('20261103'), []);
        $service($get);
        self::assertSame(['calendarIds' => [1], 'recurringUids' => [1]], $this->searchArguments);
        self::assertInstanceOf(EventRecModel::class, $get->getEvent());
        self::assertSame('20261103', $get->getEvent()->getStart()->format('Ymd'));
        self::assertTrue($get->isPropagationStopped());

        $get = new GetEventEvent('tx_cal_event', 1, CalendarDateTime::generateByYmd('20261102'), []);
        $service($get);
        self::assertSame($event, $get->getEvent());

        // another date: the event itself
        $get = new GetEventEvent('tx_cal_event', 1, CalendarDateTime::generateByYmd('20261110'), []);
        $service($get);
        self::assertSame($event, $get->getEvent());
        self::assertTrue($get->isPropagationStopped());
    }

    #[Test]
    public function getEventIgnoresOtherTypesAndMissingEvents(): void
    {
        $service = $this->createService([1]);
        $get = new GetEventEvent('tx_cal_event', 1, CalendarDateTime::generateByYmd('20261103'), []);
        $service($get);
        self::assertNull($get->getEvent());
        self::assertFalse($get->isPropagationStopped());

        $this->searchArguments = null;
        $other = new GetEventEvent('tx_other', 1, CalendarDateTime::generateByYmd('20261103'), []);
        $service($other);
        self::assertNull($this->searchArguments);
    }
}
