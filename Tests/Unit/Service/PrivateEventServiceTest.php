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
use MarioMatzulla\Cal\Domain\Model\EventBusyModel;
use MarioMatzulla\Cal\Domain\Model\EventRecModel;
use MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use MarioMatzulla\Cal\Events\FilterEventsEvent;
use MarioMatzulla\Cal\Service\PrivateEventService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(PrivateEventService::class)]
final class PrivateEventServiceTest extends UnitTestCase
{
    private static function event(string $title, string $classification = ''): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setClassification($classification);
        $start = new \DateTime('2026-12-02 14:00');
        $end = new \DateTime('2026-12-02 15:30');
        $event->setStartDate($start);
        $event->setStartTime($start);
        $event->setEndDate($end);
        $event->setEndTime($end);
        return $event;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @param list<int> $ownCalendars calendars of the logged in user (0: nobody logged in)
     */
    private function subject(int $userId = 0, array $ownCalendars = []): PrivateEventService
    {
        $context = self::createStub(Context::class);
        $context->method('getPropertyFromAspect')->willReturnCallback(static fn(string $aspect, string $property): mixed => $property === 'id' ? $userId : [0, 2, -1, 3]);
        $repository = $this->createMock(CalendarOwnerRelationRepository::class);
        $repository->expects($userId > 0 ? self::once() : self::never())->method('findCalendarListForFeUser')->with($userId, [2, 3])->willReturn($ownCalendars);
        $userRepository = self::createStub(FrontendUserRepository::class);
        $userRepository->method('findByUid')->willReturn($userId === 6 ? null : ['tx_cal_calendar' => 'tx_cal_domain_model_calendar_2, 7,x']);
        return new PrivateEventService($context, $repository, $userRepository);
    }

    private static function calendarEvent(string $title, string $classification, int $calendarUid): Event
    {
        $event = self::event($title, $classification);
        $calendar = new Calendar();
        $calendar->_setProperty('uid', $calendarUid);
        $storage = new ObjectStorage();
        $storage->attach($calendar);
        $event->setCalendarId($storage);
        return $event;
    }

    #[Test]
    public function privateEventsAreShownAsBusy(): void
    {
        $public = self::event('Concert');
        $private = self::calendarEvent('Doctor', 'PRIVATE', 1);
        $occurrence = new EventRecModel(self::calendarEvent('Therapy', 'CONFIDENTIAL', 1), 20261209140000, 20261209153000);
        $busy = new EventBusyModel(self::event('Busy before', 'PRIVATE'), 20261210140000, 20261210150000, 'Already busy');
        $filter = new FilterEventsEvent([$public, $private, $occurrence, $busy, 'other'], ['view' => ['privateEvents' => ['busyTitle' => ' Belegt ']]]);
        $this->subject()($filter);
        $events = $filter->getEvents();

        self::assertSame($public, $events[0]);
        self::assertInstanceOf(EventBusyModel::class, $events[1]);
        // the text of the TypoScript setting
        self::assertSame(['Belegt', '20261202140000', '20261202153000'], [$events[1]->getTitle(), $events[1]->getStart()->format('YmdHis'), $events[1]->getEnd()->format('YmdHis')]);
        self::assertSame($private, $events[1]->getParentEvent());
        self::assertInstanceOf(EventBusyModel::class, $events[2]);
        self::assertSame(['Belegt', '20261209140000'], [$events[2]->getTitle(), $events[2]->getStart()->format('YmdHis')]);
        self::assertSame($busy, $events[3]);
        self::assertSame('other', $events[4]);
    }

    #[Test]
    public function theOwnersOfTheCalendarSeeTheCompleteEvent(): void
    {
        $cacheInstruction = new CacheInstruction();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('frontend.cache.instruction', $cacheInstruction);
        $own = self::calendarEvent('Doctor', 'PRIVATE', 1);
        $other = self::calendarEvent('Meeting', 'PRIVATE', 4);
        $filter = new FilterEventsEvent([$own, $other], []);
        $service = new class (self::createStub(Context::class), self::createStub(CalendarOwnerRelationRepository::class), self::createStub(FrontendUserRepository::class)) extends PrivateEventService {
            protected function getOwnCalendarIds(): array
            {
                return [1, 2];
            }

            protected function getBusyTitle(array $settings): string
            {
                return 'Busy';
            }
        };
        $service($filter);
        self::assertSame($own, $filter->getEvents()[0]);
        self::assertSame('Busy', $filter->getEvents()[1]->getTitle());
        // the page contains private data: not cached
        self::assertFalse($cacheInstruction->isCachingAllowed());

        // without request (e.g. command line) nothing happens
        unset($GLOBALS['TYPO3_REQUEST']);
        $service(new FilterEventsEvent([$own], []));
    }

    #[Test]
    public function calendarsOfTheLoggedInUserAndItsGroups(): void
    {
        $method = new \ReflectionMethod(PrivateEventService::class, 'getOwnCalendarIds');
        self::assertSame([], $method->invoke($this->subject()));
        // owner of the calendars 1 and 2, CalDAV calendars 2 and 7
        self::assertSame([1, 2, 7], $method->invoke($this->subject(5, [1, 2])));
        // user without CalDAV calendars
        self::assertSame([1], $method->invoke($this->subject(6, [1])));
    }
}
