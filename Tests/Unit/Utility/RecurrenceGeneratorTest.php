<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Utility;

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
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Model\Index;
use MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RecurrenceGenerator::class)]
final class RecurrenceGeneratorTest extends UnitTestCase
{
    /**
     * @var list<Index>
     */
    private array $entries = [];

    /**
     * @var list<EventDeviation>
     */
    private array $deviations = [];

    /**
     * @var list<array<string, mixed>> exception events (records) of the event
     */
    private array $exceptions = [];

    private function createGenerator(?EventRepository $eventRepository = null, ?IndexRepository $indexRepository = null, array $configuration = []): RecurrenceGenerator
    {
        if ($indexRepository === null) {
            $indexRepository = $this->createStub(IndexRepository::class);
            $indexRepository->method('add')->willReturnCallback(function (Index $entry): void {
                $this->entries[] = $entry;
            });
        }
        $deviationRepository = $this->createStub(EventDeviationRepository::class);
        $deviationRepository->method('findByEventUid')->willReturnCallback(fn(): array => $this->deviations);
        $exceptionRepository = $this->createStub(ExceptionEventRepository::class);
        $exceptionRepository->method('findByEvent')->willReturnCallback(fn(): array => $this->exceptions);
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuration + ['recurrenceStart' => '2026-01-01 00:00:00', 'recurrenceEnd' => '2028-01-01 00:00:00']);
        return new RecurrenceGenerator(
            $eventRepository ?? $this->createStub(EventRepository::class),
            $deviationRepository,
            $indexRepository,
            $this->createStub(PersistenceManagerInterface::class),
            $extensionConfiguration,
            $exceptionRepository,
        );
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function createEvent(string $start, string $end, array $rule = []): Event
    {
        $event = new Event();
        $event->_setProperty('uid', 7);
        $start = new \DateTime($start, new \DateTimeZone('UTC'));
        $end = new \DateTime($end, new \DateTimeZone('UTC'));
        $event->setStartDate($start);
        $event->setStartTime($start);
        $event->setEndDate($end);
        $event->setEndTime($end);
        foreach ($rule as $property => $value) {
            $event->{'set' . ucfirst($property)}($value);
        }
        return $event;
    }

    /**
     * The index entry of the first date of the event
     */
    private static function first(Event $event): string
    {
        return substr($event->getStart()->format('YmdHis'), 0, 12) . '-' . substr($event->getEnd()->format('YmdHis'), 0, 12);
    }

    /**
     * @return list<string> start and end of the index entries as "YmdHi-YmdHi"
     */
    private function generate(Event $event, ?string $rangeStart = null, ?string $rangeEnd = null): array
    {
        $this->entries = [];
        $generator = $this->createGenerator();
        $generator->setRange($rangeStart, $rangeEnd);
        $generator->createRecurringInstances($event);
        return array_map(static fn(Index $entry): string => substr(sprintf('%.0f', $entry->getStartDatetime()), 0, 12) . '-' . substr(sprintf('%.0f', $entry->getEndDatetime()), 0, 12), $this->entries);
    }

    public static function ruleDataProvider(): array
    {
        return [
            'daily, every second day, 4 times' => [
                ['2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'intrval' => 2, 'cnt' => 4]],
                ['202611041000-202611041100', '202611061000-202611061100', '202611081000-202611081100'],
            ],
            'daily until' => [
                ['2026-11-02 22:00', '2026-11-03 01:00', ['freq' => 'day', 'until' => new \DateTime('2026-11-04')]],
                ['202611032200-202611040100', '202611042200-202611050100'],
            ],
            'weekly on monday and wednesday' => [
                ['2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'week', 'byday' => 'MO,WE', 'until' => new \DateTime('2026-11-15')]],
                ['202611041000-202611041100', '202611091000-202611091100', '202611111000-202611111100'],
            ],
            'every second week on monday and wednesday' => [
                ['2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'week', 'byday' => 'MO,WE', 'intrval' => 2, 'until' => new \DateTime('2026-12-03')]],
                ['202611041000-202611041100', '202611161000-202611161100', '202611181000-202611181100', '202611301000-202611301100', '202612021000-202612021100'],
            ],
            'every second week, starting on a sunday' => [
                ['2026-11-01 10:00', '2026-11-01 11:00', ['freq' => 'week', 'byday' => 'SU', 'intrval' => 2, 'cnt' => 3]],
                ['202611151000-202611151100', '202611291000-202611291100'],
            ],
            'monthly on the third wednesday' => [
                ['2026-11-18 10:00', '2026-11-18 11:00', ['freq' => 'month', 'byday' => '3WE', 'cnt' => 4]],
                ['202612161000-202612161100', '202701201000-202701201100', '202702171000-202702171100'],
            ],
            'monthly on the last friday' => [
                ['2026-11-27 10:00', '2026-11-27 11:00', ['freq' => 'month', 'byday' => '-1FR', 'cnt' => 3]],
                ['202612251000-202612251100', '202701291000-202701291100'],
            ],
            'monthly on the 31st skips shorter months' => [
                ['2026-10-31 10:00', '2026-10-31 11:00', ['freq' => 'month', 'cnt' => 4]],
                ['202612311000-202612311100', '202701311000-202701311100', '202703311000-202703311100'],
            ],
            'monthly on the last day' => [
                ['2026-11-30 10:00', '2026-11-30 11:00', ['freq' => 'month', 'bymonthday' => '-1', 'cnt' => 3]],
                ['202612311000-202612311100', '202701311000-202701311100'],
            ],
            'every second month on all mondays' => [
                ['2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'month', 'byday' => 'MO', 'intrval' => 2, 'until' => new \DateTime('2027-01-12')]],
                ['202611091000-202611091100', '202611161000-202611161100', '202611231000-202611231100', '202611301000-202611301100', '202701041000-202701041100', '202701111000-202701111100'],
            ],
            'yearly on the day of the start' => [
                ['2026-02-28 00:00', '2026-02-28 00:00', ['freq' => 'year', 'cnt' => 3, 'allday' => true]],
                ['202702280000-202702280000'],
            ],
            'yearly on the 20th monday of the year' => [
                ['2026-05-18 09:00', '2026-05-18 10:00', ['freq' => 'year', 'byday' => '20MO']],
                ['202705170900-202705171000'],
            ],
            'yearly on the last sunday of the year' => [
                ['2026-12-27 09:00', '2026-12-27 10:00', ['freq' => 'year', 'byday' => '-1SU']],
                ['202712260900-202712261000'],
            ],
            'yearly on all fridays the 13th' => [
                ['2026-02-13 09:00', '2026-02-13 10:00', ['freq' => 'year', 'byday' => 'FR', 'bymonthday' => '13', 'bymonth' => '']],
                ['202603130900-202603131000', '202611130900-202611131000', '202708130900-202708131000'],
            ],
            'yearly in march and september on the 1st' => [
                ['2026-03-01 09:00', '2026-03-01 10:00', ['freq' => 'year', 'bymonth' => '3,9', 'bymonthday' => '1', 'cnt' => 4]],
                ['202609010900-202609011000', '202703010900-202703011000', '202709010900-202709011000'],
            ],
            'yearly on every monday' => [
                ['2026-11-02 09:00', '2026-11-02 10:00', ['freq' => 'year', 'byday' => 'MO', 'cnt' => 3]],
                ['202611090900-202611091000', '202611160900-202611161000'],
            ],
            'no recurrence' => [
                ['2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'none']],
                [],
            ],
        ];
    }

    /**
     * @param array{string, string, array<string, mixed>} $event
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('ruleDataProvider')]
    public function createRecurringInstances(array $event, array $expected): void
    {
        $event = self::createEvent(...$event);
        self::assertSame([self::first($event), ...$expected], $this->generate($event));
    }

    #[Test]
    public function everySecondYear(): void
    {
        $event = self::createEvent('2026-03-01 09:00', '2026-03-01 10:00', ['freq' => 'year', 'intrval' => 2]);
        self::assertSame(['202803010900-202803011000', '203003010900-203003011000'], $this->generate($event, '2027-01-01', '2031-01-01'));
    }

    #[Test]
    public function datesBeforeTheRangeAreSkipped(): void
    {
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 6]);
        self::assertSame(['202611061000-202611061100', '202611071000-202611071100'], $this->generate($event, '2026-11-05 12:00:00', '2027-01-01'));
    }

    #[Test]
    public function untilAfterTheRangeEndsAtTheRange(): void
    {
        $event = self::createEvent('2027-12-30 10:00', '2027-12-30 11:00', ['freq' => 'day', 'until' => new \DateTime('2030-01-01')]);
        self::assertSame(['202712301000-202712301100', '202712311000-202712311100'], $this->generate($event));
    }

    #[Test]
    public function additionalDates(): void
    {
        $dates = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', ['rdateType' => 'date', 'rdate' => '20261110,20251110,20300101']);
        // the first date (2026-11-02) is not added twice
        $dates = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', ['rdateType' => 'date', 'rdate' => '20261102,20261110,20251110,20300101']);
        self::assertSame(['202611021000-202611021130', '202611101000-202611101130'], $this->generate($dates));

        $dateTimes = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', ['rdateType' => 'date_time', 'rdate' => '20261110T140000Z,20261111T150000Z']);
        self::assertSame(['202611021000-202611021130', '202611101400-202611101530', '202611111500-202611111630'], $this->generate($dateTimes));

        $periods = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', [
            'rdateType' => 'period',
            'rdate' => '20261110T140000Z/PT2H30M,20261112T080000Z/P1DT2H,20261113T080000Z/P1WT0H,20261114T080000Z/P1MT10S,20261115T080000Z/P1YT0H,20300101T080000Z/PT1H',
        ]);
        self::assertSame([
            '202611021000-202611021130',
            '202611101400-202611101630',
            '202611120800-202611131000',
            '202611130800-202611200800',
            '202611140800-202612140800',
            '202611150800-202711150800',
        ], $this->generate($periods));

        $invalid = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', [
            'rdateType' => 'period',
            'rdate' => ',x,20261110T140000Z/20261110T180000Z,20261111T140000Z/P1X,20261112T140000Z/PT,20261113T140000Z/P1DT',
        ]);
        self::assertSame([
            '202611021000-202611021130',
            '202611101400-202611101800',
            '202611111400-202611111530',
            '202611121400-202611121400',
            '202611131400-202611141400',
        ], $this->generate($invalid));

        $none = self::createEvent('2026-11-02 10:00', '2026-11-02 11:30', ['rdateType' => 'none', 'rdate' => '20261110']);
        self::assertSame(['202611021000-202611021130'], $this->generate($none));
    }

    #[Test]
    public function deviationsChangeSingleDates(): void
    {
        $moved = new EventDeviation();
        $moved->_setProperty('uid', 11);
        $moved->setOrigStartDate((int)gmmktime(0, 0, 0, 11, 3, 2026));
        $moved->setOrigStartTime(36000);
        $moved->setStartDate(new \DateTime('2026-11-03'));
        $moved->setEndDate(new \DateTime('2026-11-03'));
        $moved->setStartTime(new \DateTime('14:00'));
        $moved->setEndTime(new \DateTime('16:00'));

        $otherDay = new EventDeviation();
        $otherDay->_setProperty('uid', 12);
        $otherDay->setOrigStartDate((int)gmmktime(0, 0, 0, 11, 4, 2026));
        $otherDay->setOrigStartTime(36000);
        $otherDay->setAllday(true);
        $otherDay->setStartDate(new \DateTime('2026-11-20'));
        $otherDay->setEndDate(new \DateTime('2026-11-21'));

        $titleOnly = new EventDeviation();
        $titleOnly->_setProperty('uid', 13);
        $titleOnly->setOrigStartDate((int)gmmktime(0, 0, 0, 11, 5, 2026));
        $titleOnly->setOrigStartTime(36000);

        $this->deviations = [$moved, $otherDay, $titleOnly];
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 5]);
        self::assertSame([
            '202611021000-202611021100',
            '202611031400-202611031600',
            '202611200000-202611210000',
            '202611051000-202611051100',
            '202611061000-202611061100',
        ], $this->generate($event));
        self::assertSame([null, 11, 12, 13, null], array_map(static fn(Index $entry): ?int => $entry->getEventDeviationUid(), $this->entries));

        // a deviation of the first date
        $first = new EventDeviation();
        $first->_setProperty('uid', 14);
        $first->setOrigStartDate((int)gmmktime(0, 0, 0, 11, 2, 2026));
        $first->setOrigStartTime(36000);
        $first->setStartTime(new \DateTime('08:00'));
        $first->setEndTime(new \DateTime('09:00'));
        $this->deviations = [$first];
        self::assertSame(['202611020800-202611020900', '202611031000-202611031100'], $this->generate(self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 2])));
    }

    #[Test]
    public function exceptionEventsRemoveDates(): void
    {
        $this->exceptions = [
            // two days, including the first date of the event
            ['uid' => 1, 'start_date' => mktime(0, 0, 0, 11, 1, 2026), 'end_date' => mktime(0, 0, 0, 11, 2, 2026)],
            ['uid' => 2, 'start_date' => mktime(0, 0, 0, 11, 9, 2026), 'end_date' => 0],
            // recurring exception event: two Mondays
            ['uid' => 3, 'start_date' => mktime(0, 0, 0, 11, 23, 2026), 'freq' => 'week', 'byday' => 'MO', 'cnt' => 2],
        ];
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'week', 'byday' => 'MO', 'cnt' => 6]);
        // COUNT includes the removed dates (RFC 5545)
        self::assertSame(['202611161000-202611161100', '202612071000-202612071100'], $this->generate($event));

        $this->entries = [];
        $generator = $this->createGenerator();
        $generator->setRange('2026-12-01', '2027-01-01');
        $excluded = $generator->findExcludedOccurrences(self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'week', 'byday' => 'MO', 'cnt' => 6]));
        self::assertSame(['20261102100000', '20261109100000', '20261123100000', '20261130100000'], array_map(static fn($start): string => $start->format('YmdHis'), $excluded));
        // the range of the index is not changed
        self::assertSame(['20261201', '20270101'], [$generator->starttime->format('Ymd'), $generator->endtime->format('Ymd')]);
        self::assertSame([], $this->entries);

        // the exception dates after the range of the index, until the end of the recurrence
        $this->exceptions = [['uid' => 4, 'start_date' => mktime(0, 0, 0, 6, 5, 2028)]];
        $excluded = $generator->findExcludedOccurrences(self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'week', 'byday' => 'MO', 'until' => new \DateTime('2029-01-01')]));
        self::assertSame(['20280605100000'], array_map(static fn($start): string => $start->format('YmdHis'), $excluded));
    }

    #[Test]
    public function createExceptionEvent(): void
    {
        $exception = RecurrenceGenerator::createExceptionEvent(['uid' => 3, 'start_date' => mktime(0, 0, 0, 11, 23, 2026)]);
        self::assertSame([3, true, true, 'none', 'none', null], [$exception->getUid(), $exception->isException(), $exception->isAllday(), $exception->getFreq(), $exception->getRdateType(), $exception->getUntil()]);
        self::assertSame('20261123', $exception->getEndDate()->format('Ymd'));

        $exception = RecurrenceGenerator::createExceptionEvent([
            'uid' => 4, 'start_date' => mktime(0, 0, 0, 11, 23, 2026), 'end_date' => mktime(0, 0, 0, 11, 24, 2026),
            'freq' => 'month', 'byday' => 'MO', 'bymonthday' => '1', 'bymonth' => '2', 'rdate_type' => 'date', 'rdate' => '20261201',
            'until' => mktime(0, 0, 0, 12, 31, 2027), 'cnt' => 3, 'intrval' => 2,
        ]);
        self::assertSame(
            ['20261124', 'month', 'MO', '1', '2', 'date', '20261201', '20271231', 3, 2],
            [$exception->getEndDate()->format('Ymd'), $exception->getFreq(), $exception->getByday(), $exception->getBymonthday(), $exception->getBymonth(), $exception->getRdateType(), $exception->getRdate(), $exception->getUntil()->format('Ymd'), $exception->getCnt(), $exception->getIntrval()],
        );
    }

    #[Test]
    public function tablenameOfTheIndexEntries(): void
    {
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 2]);
        $this->generate($event);
        self::assertSame('tx_cal_event', $this->entries[0]->getTablename());
        self::assertSame(7, $this->entries[0]->getEventUid());

        $event->setException(true);
        $this->generate($event);
        self::assertSame('tx_cal_exception_event', $this->entries[0]->getTablename());

        $deviation = new EventDeviation();
        $deviation->_setProperty('uid', 8);
        $deviation->setStartDate(new \DateTime('2026-11-02 10:00'));
        $deviation->setFreq('day');
        $deviation->setCnt(2);
        $this->generate($deviation);
        self::assertSame('tx_cal_event_deviation', $this->entries[0]->getTablename());
    }

    #[Test]
    public function setRangeUsesTheExtensionConfiguration(): void
    {
        $generator = $this->createGenerator(null, null, ['recurrenceStart' => 'cal:yearstart', 'recurrenceEnd' => '+1 year']);
        self::assertSame(gmdate('Y') . '0101000000', $generator->starttime->format('YmdHis'));
        self::assertSame((string)((int)gmdate('Y') + 1), $generator->endtime->format('Y'));
        self::assertSame($generator, $generator->setRange('2026-03-01 00:00:00', ''));
        self::assertSame('20260301000000', $generator->starttime->format('YmdHis'));
        self::assertSame((string)((int)gmdate('Y') + 1), $generator->endtime->format('Y'));
    }

    #[Test]
    public function cleanIndexTable(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())->method('findUidsForEventsWithPage')->with(5)->willReturn([1, 2]);
        $eventRepository->expects(self::once())->method('findUidsForCalendar')->with(3)->willReturn([4]);
        $indexRepository = $this->createMock(IndexRepository::class);
        $indexRepository->expects(self::exactly(3))->method('deleteByEventUidsAndTablename')->willReturnCallback(function (array $uids, string $table): void {
            $this->entries[] = implode(',', $uids) . ':' . $table;
        });
        $generator = $this->createGenerator($eventRepository, $indexRepository);
        $generator->cleanIndexTable(5);
        $generator->cleanIndexTableOfUid(9, 'tx_cal_exception_event');
        $generator->cleanIndexTableOfCalendarUid(3);
        self::assertSame(['1,2:tx_cal_event', '9:tx_cal_exception_event', '4:tx_cal_event'], $this->entries);
    }

    #[Test]
    public function countRecurringEvents(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())->method('countWithPage')->with(5)->willReturn(3);
        $eventRepository->expects(self::once())->method('countAll')->willReturn(10);
        $generator = $this->createGenerator($eventRepository);
        self::assertSame(3, $generator->countRecurringEvents(5));
        self::assertSame(10, $generator->countRecurringEvents());
    }

    #[Test]
    public function generateIndexForAllEventsOfAPage(): void
    {
        $first = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 2, 'title' => 'First']);
        $second = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 3, 'title' => 'Second']);
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())->method('findRecurringEvents')->with(5)->willReturn([$first, $second]);
        self::assertSame(['First', 'Second'], $this->createGenerator($eventRepository)->generateIndex(5));
        self::assertCount(5, $this->entries);
    }

    #[Test]
    public function generateIndexForEventUid(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::exactly(3))->method('findByUid')->willReturnOnConsecutiveCalls(
            self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 2]),
            null,
            // events without recurrence have no index
            self::createEvent('2026-11-02 10:00', '2026-11-02 11:00'),
        );
        $indexRepository = $this->createMock(IndexRepository::class);
        $indexRepository->expects(self::exactly(3))->method('deleteByEventUidsAndTablename')->with([7], 'tx_cal_event');
        $indexRepository->expects(self::exactly(2))->method('add');
        $generator = $this->createGenerator($eventRepository, $indexRepository);
        $generator->generateIndexForEventUid(7);
        $generator->generateIndexForEventUid(7);
        $generator->generateIndexForEventUid(7);
    }

    #[Test]
    public function generateIndexForCalendarUid(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())->method('findUidsForCalendar')->with(3)->willReturn([7]);
        $eventRepository->expects(self::once())->method('findAllForCalendar')->with(3)->willReturn([
            self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 3]),
            self::createEvent('2026-11-02 10:00', '2026-11-02 11:00'),
        ]);
        $this->createGenerator($eventRepository)->generateIndexForCalendarUid(3);
        self::assertCount(3, $this->entries);
    }

    #[Test]
    public function twoCharDays(): void
    {
        self::assertSame(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', ''], array_map(RecurrenceGenerator::two2threeCharDays(...), ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'XX']));
        self::assertSame('3', RecurrenceGenerator::two2threeCharDays('WE', false));
    }

    #[Test]
    public function resolveMonthDays(): void
    {
        self::assertSame(range(1, 28), RecurrenceGenerator::resolveMonthDays('', 2, 2026));
        self::assertSame([1, 15, 29], RecurrenceGenerator::resolveMonthDays('15,-1,1,1,31', 2, 2028));
    }

    #[Test]
    public function aRuleTheFieldsCannotStoreIsUsed(): void
    {
        // the last workday of the month, the fields contain an approximation (monthly)
        $event = self::createEvent('2026-01-30 10:00', '2026-01-30 11:00', ['freq' => 'month', 'rrule' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;COUNT=4']);
        self::assertSame(
            [self::first($event), '202602271000-202602271100', '202603311000-202603311100', '202604301000-202604301100'],
            $this->generate($event),
        );
        // occurrences ending before the range are not written
        self::assertSame(['202603311000-202603311100', '202604301000-202604301100'], $this->generate($event, '2026-03-01 00:00:00'));
    }

    #[Test]
    public function anInvalidRuleUsesTheFields(): void
    {
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 11:00', ['freq' => 'day', 'cnt' => 2, 'rrule' => 'FREQ=EVERY']);
        self::assertSame([self::first($event), '202611031000-202611031100'], $this->generate($event));
    }

    #[Test]
    public function theNumberOfOccurrencesOfARuleIsLimited(): void
    {
        $event = self::createEvent('2026-11-02 10:00', '2026-11-02 10:01', ['freq' => 'day', 'rrule' => 'FREQ=MINUTELY']);
        $entries = $this->generate($event);
        self::assertCount(RecurrenceGenerator::MAX_RULE_OCCURRENCES + 1, $entries);
        self::assertSame('202611090840-202611090841', end($entries));
    }

    #[Test]
    public function aStartNotMatchingTheRuleIsAnAdditionalDate(): void
    {
        // the last day of the month, 5 times, start on the 1st: like Thunderbird, COUNT does not include the start
        $event = self::createEvent('2026-12-01 14:00', '2026-12-01 15:00', ['freq' => 'month', 'bymonthday' => '-1', 'cnt' => 5]);
        self::assertSame(
            [self::first($event), '202612311400-202612311500', '202701311400-202701311500', '202702281400-202702281500', '202703311400-202703311500', '202704301400-202704301500'],
            $this->generate($event),
        );
        // weekly on Monday and Wednesday, start on a Tuesday
        $event = self::createEvent('2026-12-01 14:00', '2026-12-01 15:00', ['freq' => 'week', 'byday' => '1MO,WE', 'cnt' => 2]);
        self::assertSame([self::first($event), '202612021400-202612021500', '202612071400-202612071500'], $this->generate($event));
        // a matching start counts as first date
        $event = self::createEvent('2026-12-02 14:00', '2026-12-02 15:00', ['freq' => 'week', 'byday' => 'MO,WE', 'cnt' => 2]);
        self::assertSame([self::first($event), '202612071400-202612071500'], $this->generate($event));
        // an ordinal the rule does not allow: the start counts
        $event = self::createEvent('2026-12-02 14:00', '2026-12-02 15:00', ['freq' => 'month', 'byday' => '99WE', 'cnt' => 1]);
        self::assertSame([self::first($event)], $this->generate($event));
    }
}
