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
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Service\IcsRecurrence;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(IcsRecurrence::class)]
final class IcsRecurrenceTest extends UnitTestCase
{
    private string $timezone = '';

    /**
     * @var list<string> YmdHis of the removed occurrences
     */
    private array $excluded = [];

    /**
     * @var array<string, EventDeviation>
     */
    private array $deviations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    private function subject(): IcsRecurrence
    {
        $generator = self::createStub(RecurrenceGenerator::class);
        $generator->method('findExcludedOccurrences')->willReturnCallback(fn(): array => array_map(
            static fn(string $start): CalendarDateTime => CalendarDateTime::generateFromDateTime(new \DateTime($start)),
            $this->excluded,
        ));
        $generator->method('getDeviationDates')->willReturnCallback(fn(): array => $this->deviations);
        return new IcsRecurrence($generator);
    }

    private static function event(array $properties, string $start = '2026-11-02 10:00', string $end = '2026-11-02 11:00'): Event
    {
        $event = new Event();
        $event->setTitle('Meeting');
        $event->setStartDate(new \DateTime($start));
        $event->setStartTime(new \DateTime($start));
        $event->setEndDate(new \DateTime($end));
        $event->setEndTime(new \DateTime($end));
        foreach ($properties as $property => $value) {
            $event->{'set' . ucfirst($property)}($value);
        }
        return $event;
    }

    #[Test]
    public function singleEventsHaveNoRecurrence(): void
    {
        $event = self::event(['freq' => 'none']);
        self::assertSame('', $this->subject()->getRecurrenceLines($event, 'Europe/Berlin'));
        self::assertSame([], $this->subject()->getOverrides($event, 'Europe/Berlin'));
    }

    #[Test]
    public function weeklyRuleWithTimeZoneAndExceptions(): void
    {
        $this->excluded = ['2026-11-09 10:00', '2026-11-16 10:00'];
        $event = self::event(['freq' => 'week', 'byday' => 'mo, WE,xx,all', 'intrval' => 2, 'until' => new \DateTime('2026-12-31'), 'cnt' => 3]);
        self::assertSame(
            "RRULE:FREQ=WEEKLY;INTERVAL=2;UNTIL=20261231T225959Z;BYDAY=MO,WE\nEXDATE;TZID=Europe/Berlin:20261109T100000,20261116T100000",
            $this->subject()->getRecurrenceLines($event, 'Europe/Berlin'),
        );
    }

    #[Test]
    public function rulesWithoutTimeZoneInUtc(): void
    {
        $this->excluded = ['2026-11-04 10:00'];
        $event = self::event(['freq' => 'day', 'byday' => 'MO', 'cnt' => 5, 'until' => null]);
        self::assertSame("RRULE:FREQ=DAILY;COUNT=5\nEXDATE:20261104T100000Z", $this->subject()->getRecurrenceLines($event, ''));

        $this->excluded = [];
        $event = self::event(['freq' => 'month', 'byday' => '-1FR', 'bymonthday' => '-1', 'bymonth' => '3']);
        self::assertSame('RRULE:FREQ=MONTHLY;BYDAY=-1FR;BYMONTHDAY=-1', $this->subject()->getRecurrenceLines($event, ''));

        // UNTIL of a time zone the server does not know: wall clock time of the server
        $event = self::event(['freq' => 'day', 'until' => new \DateTime('2026-11-30')]);
        self::assertSame('RRULE:FREQ=DAILY;UNTIL=20261130T235959Z', $this->subject()->getRecurrenceLines($event, 'Unknown/Zone'));
    }

    #[Test]
    public function allDayRules(): void
    {
        $this->excluded = ['2027-03-01 00:00'];
        $event = self::event(['freq' => 'year', 'allday' => true, 'bymonth' => '3,13,0', 'bymonthday' => '1,0,40', 'until' => new \DateTime('2030-12-31')], '2026-03-01 00:00', '2026-03-01 00:00');
        self::assertSame(
            "RRULE:FREQ=YEARLY;UNTIL=20301231;BYMONTHDAY=1;BYMONTH=3\nEXDATE;VALUE=DATE:20270301",
            $this->subject()->getRecurrenceLines($event, 'Europe/Berlin'),
        );
    }

    #[Test]
    public function additionalDates(): void
    {
        $subject = $this->subject();
        // dates get the time of the event, the first date is not repeated
        $event = self::event(['freq' => 'none', 'rdateType' => 'date', 'rdate' => '20261102,20261110,x,20261111']);
        self::assertSame('RDATE;TZID=Europe/Berlin:20261110T100000,20261111T100000', $subject->getRecurrenceLines($event, 'Europe/Berlin'));

        $event = self::event(['freq' => 'week', 'rdateType' => 'date_time', 'rdate' => '20261110T140000Z']);
        self::assertSame("RRULE:FREQ=WEEKLY\nRDATE:20261110T140000Z", $subject->getRecurrenceLines($event, ''));

        $event = self::event(['freq' => 'none', 'rdateType' => 'period', 'rdate' => '20261110T140000Z/PT2H,20261111T140000Z/20261111T180000Z,20261112T140000Z/PT,20261113T140000Z/P1X']);
        self::assertSame(
            "RDATE;VALUE=PERIOD;TZID=Europe/Berlin:20261110T140000/PT2H,20261111T140000/20261111T180000\nRDATE;TZID=Europe/Berlin:20261112T140000,20261113T140000",
            $subject->getRecurrenceLines($event, 'Europe/Berlin'),
        );

        // all-day events: dates
        $event = self::event(['freq' => 'none', 'allday' => true, 'rdateType' => 'period', 'rdate' => '20261110T140000Z/PT2H'], '2026-11-02 00:00', '2026-11-02 00:00');
        self::assertSame('RDATE;VALUE=DATE:20261110', $subject->getRecurrenceLines($event, ''));

        // unknown type
        $event = self::event(['freq' => 'day', 'rdateType' => 'none', 'rdate' => '20261110']);
        self::assertSame('RRULE:FREQ=DAILY', $subject->getRecurrenceLines($event, ''));
    }

    #[Test]
    public function deviationsAreOverrides(): void
    {
        $moved = new EventDeviation();
        $moved->setTitle('Moved');
        $moved->setLocation('Room 2');
        $moved->setStartTime(new \DateTime('14:00'));
        $moved->setEndTime(new \DateTime('16:00'));

        $otherDay = new EventDeviation();
        $otherDay->setAllday(true);
        $otherDay->setStartDate(new \DateTime('2026-11-20'));
        $otherDay->setEndDate(new \DateTime('2026-11-21'));
        $otherDay->setDescription("<p>Line 1\nLine 2; a,b \\ c</p>");

        $this->deviations = ['20261103100000' => $moved, '20261104100000' => $otherDay];
        $event = self::event(['freq' => 'day', 'location' => 'Hall', 'description' => 'Weekly meeting']);
        self::assertSame([
            [
                'recurrenceId' => ';TZID=Europe/Berlin:20261103T100000',
                'start' => ';TZID=Europe/Berlin:20261103T140000',
                'end' => ';TZID=Europe/Berlin:20261103T160000',
                'title' => 'Moved',
                'description' => 'Weekly meeting',
                'location' => 'Room 2',
            ],
            [
                'recurrenceId' => ';TZID=Europe/Berlin:20261104T100000',
                'start' => ';VALUE=DATE:20261120',
                'end' => ';VALUE=DATE:20261122',
                'title' => 'Meeting',
                'description' => 'Line 1\nLine 2\; a\,b \\\\ c',
                'location' => 'Hall',
            ],
        ], $this->subject()->getOverrides($event, 'Europe/Berlin'));

        // all-day event without time zone
        $this->deviations = ['20261103000000' => $moved];
        $overrides = $this->subject()->getOverrides(self::event(['freq' => 'day', 'allday' => true], '2026-11-02 00:00', '2026-11-02 00:00'), '');
        self::assertSame([';VALUE=DATE:20261103', ':20261103T140000Z'], [$overrides[0]['recurrenceId'], $overrides[0]['start']]);
    }

    #[Test]
    public function formatAndEscape(): void
    {
        $date = new \DateTimeImmutable('2026-11-10 14:30:00');
        self::assertSame(';VALUE=DATE:20261110', IcsRecurrence::formatDateTime($date, true, 'Europe/Berlin'));
        self::assertSame(';TZID=Europe/Berlin:20261110T143000', IcsRecurrence::formatDateTime($date, false, 'Europe/Berlin'));
        self::assertSame(':20261110T143000Z', IcsRecurrence::formatDateTime($date, false, ''));
        self::assertSame('a\\\\b\;c\,d\ne\nf\ng', IcsRecurrence::escapeText("a\\b;c,d\r\ne\nf\rg"));
    }

    #[Test]
    public function theStoredRuleOfACalDavClientIsExported(): void
    {
        $lines = fn(array $properties, string $timezone = 'Europe/Berlin'): string => $this->subject()->getRecurrenceLines(self::event(['freq' => 'month'] + $properties), $timezone);
        // UNTIL is stored as wall clock time and exported in UTC
        self::assertSame(
            'RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;UNTIL=20261231T090000Z',
            $lines(['rrule' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;UNTIL=20261231T100000']),
        );
        self::assertSame('RRULE:FREQ=HOURLY;INTERVAL=4;UNTIL=20261231T225959Z', $lines(['rrule' => 'FREQ=HOURLY;INTERVAL=4;UNTIL=20261231']));
        self::assertSame('RRULE:FREQ=WEEKLY;WKST=SU;COUNT=300', $lines(['rrule' => 'FREQ=WEEKLY;WKST=SU;COUNT=300']));
        // all-day events: UNTIL as date
        self::assertSame('RRULE:FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO;UNTIL=20301231', $lines(['allday' => true, 'rrule' => 'FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO;UNTIL=20301231T100000']));
        self::assertSame('RRULE:FREQ=YEARLY;BYYEARDAY=-1;UNTIL=20301231', $lines(['allday' => true, 'rrule' => 'FREQ=YEARLY;BYYEARDAY=-1;UNTIL=20301231']));
        // an invalid rule: the fields are exported
        self::assertSame('RRULE:FREQ=MONTHLY', $lines(['rrule' => 'FREQ=EVERY']));
    }
}
