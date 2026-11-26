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
use MarioMatzulla\Cal\Service\IcsParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(IcsParser::class)]
final class IcsParserTest extends UnitTestCase
{
    private static function calendar(string ...$events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\n" . implode('', array_map(static fn(string $event): string => "BEGIN:VEVENT\r\n" . $event . "END:VEVENT\r\n", $events)) . "END:VCALENDAR\r\n";
    }

    /**
     * Timestamp of a date (00:00) in the default time zone of PHP
     */
    private static function date(string $date): int
    {
        return (new \DateTimeImmutable($date . ' 00:00:00'))->getTimestamp();
    }

    #[Test]
    public function parseIcsContentReadsTheFirstEvent(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar(
            "UID:abc@example.org\r\nSUMMARY:Meeting\r\nDESCRIPTION:Text\r\nLOCATION:Hall\r\nDTSTART:20261125T090000Z\r\nDTEND:20261125T103000Z\r\n"
            . "RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=5;BYDAY=MO,WE\r\n",
            "UID:second\r\nDTSTART:20261126T090000Z\r\n",
        ), 'Europe/Berlin');
        self::assertSame('abc@example.org', $values['ics_uid']);
        self::assertSame(['Meeting', 'Text', 'Hall', 0], [$values['title'], $values['description'], $values['location'], $values['allday']]);
        // 09:00 UTC is 10:00 in Berlin
        self::assertSame([self::date('2026-11-25'), 36000, self::date('2026-11-25'), 41400], [$values['start_date'], $values['start_time'], $values['end_date'], $values['end_time']]);
        self::assertSame(['week', 2, 5, 'MO,WE', '', ''], [$values['freq'], $values['intrval'], $values['cnt'], $values['byday'], $values['bymonth'], $values['bymonthday']]);
        self::assertSame([0, 'none', ''], [$values['until'], $values['rdate_type'], $values['rdate']]);
        self::assertEqualsWithDelta(time(), $values['tstamp'], 5);
    }

    #[Test]
    public function parseIcsContentWithAllDayEventAndUntil(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar(
            "DTSTART;VALUE=DATE:20261124\r\nDTEND;VALUE=DATE:20261126\r\nRRULE:FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=24;UNTIL=20301124\r\n",
        ), 'Invalid/Zone');
        self::assertSame(['', '', '', '', 1], [$values['ics_uid'], $values['title'], $values['description'], $values['location'], $values['allday']]);
        // DTEND of an all-day event is exclusive
        self::assertSame([self::date('2026-11-24'), 0, self::date('2026-11-25'), 0], [$values['start_date'], $values['start_time'], $values['end_date'], $values['end_time']]);
        self::assertSame(['year', 1, 0, '11', '24'], [$values['freq'], $values['intrval'], $values['cnt'], $values['bymonth'], $values['bymonthday']]);
        self::assertSame((new \DateTime('2030-11-24 00:00:00'))->getTimestamp(), $values['until']);

        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000\r\nRRULE:FREQ=SECONDLY;UNTIL=20301124T100000Z\r\n"));
        // the fields contain an approximation, the dates are calculated with the rule (UNTIL as wall clock time)
        self::assertSame(['day', 'FREQ=SECONDLY;UNTIL=20301124T100000'], [$values['freq'], $values['rrule']]);
        // the last day of the recurrence
        self::assertSame(self::date('2030-11-24'), $values['until']);
    }

    #[Test]
    public function parseIcsContentWithDurationOrWithoutEnd(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000Z\r\nDURATION:PT1H30M\r\nRRULE:FREQ=DAILY\r\n"));
        self::assertSame([36000, 41400], [$values['start_time'], $values['end_time']]);
        self::assertSame('day', $values['freq']);

        $values = IcsParser::parseIcsContent(self::calendar("DTSTART;VALUE=DATE:20261124\r\nRRULE:FREQ=MONTHLY;UNTIL=x\r\n"));
        self::assertSame([self::date('2026-11-24'), self::date('2026-11-24')], [$values['start_date'], $values['end_date']]);
        self::assertSame('month', $values['freq']);
        self::assertSame(0, $values['until']);
    }

    #[Test]
    public function parseIcsContentWithoutRecurrenceResetsTheFields(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000Z\r\n"));
        self::assertSame(
            ['freq' => 'none', 'intrval' => 1, 'cnt' => 0, 'byday' => '', 'bymonth' => '', 'bymonthday' => '', 'until' => 0, 'rdate_type' => 'none', 'rdate' => ''],
            array_intersect_key($values, array_flip(['freq', 'intrval', 'cnt', 'byday', 'bymonth', 'bymonthday', 'until', 'rdate_type', 'rdate'])),
        );
    }

    #[Test]
    public function parseIcsContentReadsTheEventNotTheChangedOccurrences(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar(
            "UID:a\r\nRECURRENCE-ID:20261126T090000Z\r\nSUMMARY:Moved\r\nDTSTART:20261126T140000Z\r\n",
            "UID:a\r\nSUMMARY:Series\r\nDTSTART:20261125T090000Z\r\nRRULE:FREQ=DAILY\r\n",
        ));
        self::assertSame(['Series', 'day'], [$values['title'], $values['freq']]);
    }

    #[Test]
    public function parseIcsContentWithAdditionalDates(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART;VALUE=DATE:20261124\r\nRDATE;VALUE=DATE:20261201,20261208\r\n"), 'Europe/Berlin');
        self::assertSame(['date', '20261201,20261208'], [$values['rdate_type'], $values['rdate']]);

        // wall clock time of the calendar time zone
        $values = IcsParser::parseIcsContent(self::calendar(
            "DTSTART:20261124T090000Z\r\nRDATE:20261201T090000Z\r\nRDATE;TZID=America/New_York:20261202T080000\r\nRDATE:invalid\r\n",
        ), 'Europe/Berlin');
        self::assertSame(['date_time', '20261201T100000Z,20261202T140000Z'], [$values['rdate_type'], $values['rdate']]);

        $values = IcsParser::parseIcsContent(self::calendar(
            "DTSTART:20261124T090000Z\r\nRDATE;VALUE=PERIOD:20261201T090000Z/PT2H,20261202T090000Z/20261202T120000Z,20261203T090000Z/x\r\nRDATE;VALUE=DATE:20261210\r\n",
        ), 'Europe/Berlin');
        self::assertSame(['period', '20261201T100000Z/PT2H,20261202T100000Z/20261202T130000Z,20261203T100000Z,20261210'], [$values['rdate_type'], $values['rdate']]);
    }

    #[Test]
    public function parseRecurrenceDetails(): void
    {
        $details = IcsParser::parseRecurrenceDetails(self::calendar(
            "UID:a\r\nSUMMARY:Series\r\nDTSTART;TZID=Europe/Berlin:20261125T100000\r\nRRULE:FREQ=DAILY\r\n"
            . "EXDATE;TZID=Europe/Berlin:20261127T100000,20261126T100000\r\nEXDATE:20261128T090000Z\r\n",
            "UID:a\r\nRECURRENCE-ID;TZID=Europe/Berlin:20261129T100000\r\nSUMMARY:Moved\r\nDESCRIPTION:Other room\r\nLOCATION:Room 2\r\n"
            . "DTSTART;TZID=Europe/Berlin:20261129T140000\r\nDTEND;TZID=Europe/Berlin:20261129T160000\r\n",
            "UID:a\r\nRECURRENCE-ID;TZID=Europe/Berlin:20261130T100000\r\nSTATUS:CANCELLED\r\nDTSTART;TZID=Europe/Berlin:20261130T100000\r\n",
            "UID:a\r\nRECURRENCE-ID;VALUE=DATE:20261201\r\nDTSTART;VALUE=DATE:20261202\r\n",
            "UID:a\r\nRECURRENCE-ID:20261203T090000Z\r\n",
        ), 'Europe/Berlin');
        self::assertSame(['20261126', '20261127', '20261128', '20261130'], $details['exdates']);
        self::assertCount(2, $details['overrides']);
        self::assertSame([
            'orig_start_date' => self::date('2026-11-29'),
            'orig_start_time' => 36000,
            'title' => 'Moved',
            'description' => 'Other room',
            'location' => 'Room 2',
            'allday' => 0,
            'start_date' => self::date('2026-11-29'),
            'start_time' => 50400,
            'end_date' => self::date('2026-11-29'),
            'end_time' => 57600,
        ], $details['overrides'][0]);
        self::assertSame([self::date('2026-12-01'), 0, 1, self::date('2026-12-02')], [
            $details['overrides'][1]['orig_start_date'], $details['overrides'][1]['orig_start_time'], $details['overrides'][1]['allday'], $details['overrides'][1]['start_date'],
        ]);

        self::assertSame(['exdates' => [], 'overrides' => []], IcsParser::parseRecurrenceDetails(self::calendar("SUMMARY:No start\r\n")));
        self::assertSame(['exdates' => [], 'overrides' => []], IcsParser::parseRecurrenceDetails("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Test\r\nEND:VCARD\r\n"));
    }

    #[Test]
    public function parseIcsContentWithoutEvent(): void
    {
        self::assertSame([], IcsParser::parseIcsContent(self::calendar()));
        self::assertSame([], IcsParser::parseIcsContent("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Test\r\nEND:VCARD\r\n"));
        self::assertSame([], IcsParser::parseIcsContent(self::calendar("SUMMARY:No start\r\n")));
    }

    #[Test]
    public function parseEventsExpandsTheRecurrences(): void
    {
        $events = IcsParser::parseEvents(self::calendar(
            "UID:series\r\nSUMMARY:" . str_repeat('x', 140) . "\r\nLOCATION:Hall\r\nDESCRIPTION:Text\r\nDTSTART:20261102T090000Z\r\nDTEND:20261102T100000Z\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\n",
            "UID:series\r\nRECURRENCE-ID:20261109T090000Z\r\nSUMMARY:Moved\r\nDTSTART:20261110T090000Z\r\nDURATION:PT2H\r\n",
            "SUMMARY:Without uid\r\nDTSTART;VALUE=DATE:20261120\r\nDTEND;VALUE=DATE:20261121\r\n",
            "SUMMARY:Without end\r\nDTSTART:20261121T120000Z\r\n",
            "SUMMARY:Outside\r\nDTSTART:20270301T120000Z\r\n",
            "UID:invalid\r\nSUMMARY:Without start\r\n",
        ), new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), 'Europe/Berlin');
        self::assertCount(5, $events);
        // single events first, every instance of a series gets the date of its RECURRENCE-ID in the uid
        [$allDay, $withoutEnd, $first, $moved, $third] = $events;

        self::assertSame('series-20261102T090000Z', $first['ics_uid']);
        self::assertSame(128, mb_strlen($first['title']));
        self::assertSame(['Hall', 'Text', 0, 'none'], [$first['location'], $first['description'], $first['allday'], $first['freq']]);
        self::assertSame([self::date('2026-11-02'), 36000, 39600], [$first['start_date'], $first['start_time'], $first['end_time']]);
        self::assertStringContainsString('BEGIN:VEVENT', $first['caldav_data']);
        self::assertStringNotContainsString('RRULE', $first['caldav_data']);

        self::assertSame('series-20261109T090000Z', $moved['ics_uid']);
        self::assertSame('Moved', $moved['title']);
        self::assertSame([self::date('2026-11-10'), 36000, 43200], [$moved['start_date'], $moved['start_time'], $moved['end_time']]);

        self::assertSame('series-20261116T090000Z', $third['ics_uid']);
        self::assertSame(self::date('2026-11-16'), $third['start_date']);

        self::assertSame(32, strlen($allDay['ics_uid']));
        self::assertSame([1, self::date('2026-11-20'), self::date('2026-11-20'), 0], [$allDay['allday'], $allDay['start_date'], $allDay['end_date'], $allDay['end_time']]);

        self::assertSame([46800, 46800], [$withoutEnd['start_time'], $withoutEnd['end_time']]);
    }

    #[Test]
    public function parseEventsWithoutEvents(): void
    {
        self::assertSame([], IcsParser::parseEvents(self::calendar(), new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), 'Invalid/Zone'));
        // an invalid time zone falls back to the default time zone of PHP (UTC in the tests)
        $events = IcsParser::parseEvents(self::calendar("DTSTART:20261110T090000Z\r\n"), new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), 'Invalid/Zone');
        self::assertSame(32400, $events[0]['start_time']);
        self::assertSame([], IcsParser::parseEvents("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Test\r\nEND:VCARD\r\n", new \DateTime('2026-11-01'), new \DateTime('2026-12-01')));
    }

    #[Test]
    public function parseIcsContentStoresRulesTheFieldsCannotStore(): void
    {
        $rule = static fn(string $event): array => array_intersect_key(
            IcsParser::parseIcsContent(self::calendar($event), 'Europe/Berlin'),
            array_flip(['freq', 'cnt', 'intrval', 'rrule']),
        );
        // UNTIL as wall clock time of the calendar (09:00 UTC is 10:00 in Berlin)
        self::assertSame(
            ['freq' => 'month', 'intrval' => 1, 'cnt' => 0, 'rrule' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;UNTIL=20261231T100000'],
            $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;UNTIL=20261231T090000Z\r\n"),
        );
        self::assertSame('FREQ=WEEKLY;INTERVAL=2;WKST=SU', $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=WEEKLY;INTERVAL=2;WKST=SU\r\n")['rrule']);
        self::assertSame('', $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO\r\n")['rrule']);
        // the fields store at most 255
        self::assertSame(['freq' => 'day', 'intrval' => 255, 'cnt' => 255, 'rrule' => 'FREQ=DAILY;INTERVAL=300;COUNT=300'], $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=DAILY;INTERVAL=300;COUNT=300\r\n"));
        self::assertSame('FREQ=HOURLY;INTERVAL=4', $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=HOURLY;INTERVAL=4\r\n")['rrule']);
        self::assertSame('FREQ=DAILY;BYMONTH=1', $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=DAILY;BYMONTH=1\r\n")['rrule']);
        // all-day events: UNTIL as date
        self::assertSame('FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO;UNTIL=20301231', $rule("DTSTART;VALUE=DATE:20270517\r\nRRULE:FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO;UNTIL=20301231\r\n")['rrule']);
        self::assertSame('FREQ=YEARLY;BYYEARDAY=-1;UNTIL=20301231', $rule("DTSTART;VALUE=DATE:20271231\r\nRRULE:FREQ=YEARLY;BYYEARDAY=-1;UNTIL=20301231T120000Z\r\n")['rrule']);
        // UNTIL without time zone is a wall clock time
        self::assertSame('FREQ=MONTHLY;BYSETPOS=1;BYDAY=MO;UNTIL=20301124T100000', $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=MONTHLY;BYSETPOS=1;BYDAY=MO;UNTIL=20301124T100000\r\n")['rrule']);
        // an invalid rule is not stored, the fields are used
        self::assertSame(['freq' => 'day', 'intrval' => 1, 'cnt' => 2, 'rrule' => ''], $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=DAILY;COUNT=2;BYHOUR=24\r\n"));
        self::assertSame(['freq' => 'none', 'intrval' => 1, 'cnt' => 0, 'rrule' => ''], $rule("DTSTART:20261130T090000Z\r\nRRULE:FREQ=WEEKDAYS;BYSETPOS=1\r\n"));
    }

    #[Test]
    public function parseEventsCalculatesAllPartsOfARule(): void
    {
        $dates = static fn(array $events): array => array_map(
            static fn(array $event): string => date('Y-m-d', $event['start_date']) . ' ' . gmdate('H:i', $event['start_time']) . '-' . gmdate('H:i', $event['end_time']),
            $events,
        );
        // the last workday of the month (sabre/vobject supports BYSETPOS for monthly rules only)
        $events = IcsParser::parseEvents(self::calendar(
            "UID:last\r\nSUMMARY:Last workday\r\nDTSTART;TZID=Europe/Berlin:20260130T100000\r\nDTEND;TZID=Europe/Berlin:20260130T110000\r\n"
            . "RRULE:FREQ=YEARLY;BYMONTH=1,2,3,4,5,6,7,8,9,10,11,12;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1,-2\r\nEXDATE;TZID=Europe/Berlin:20261130T100000\r\n",
            "UID:last\r\nRECURRENCE-ID;TZID=Europe/Berlin:20261127T100000\r\nSUMMARY:Moved\r\nDTSTART;TZID=Europe/Berlin:20261127T140000\r\nDTEND;TZID=Europe/Berlin:20261127T150000\r\n",
        ), new \DateTime('2026-11-01'), new \DateTime('2027-01-01'), 'Europe/Berlin');
        // the year has 24 dates, November 30 is removed, November 27 is moved
        self::assertSame(['2026-11-27 14:00-15:00', '2026-12-30 10:00-11:00', '2026-12-31 10:00-11:00'], $dates($events));
        self::assertSame(['last-20261127T090000Z', 'Moved'], [$events[0]['ics_uid'], $events[0]['title']]);
        self::assertSame('last-20261230T090000Z', $events[1]['ics_uid']);

        // every 12 hours on weekends (MINUTELY), occurrences overlapping the start of the range are included
        $events = IcsParser::parseEvents(self::calendar(
            "UID:weekend\r\nDTSTART:20261121T000000\r\nDURATION:PT13H\r\nRRULE:FREQ=MINUTELY;INTERVAL=720;BYDAY=SA,SU\r\n",
        ), new \DateTime('2026-11-29 12:30:00', new \DateTimeZone('Europe/Berlin')), new \DateTime('2026-12-05 23:00:00', new \DateTimeZone('Europe/Berlin')), 'Europe/Berlin');
        self::assertSame(['2026-11-29 00:00-13:00', '2026-11-29 12:00-01:00', '2026-12-05 00:00-13:00', '2026-12-05 12:00-01:00'], $dates($events));

        // all-day events
        $events = IcsParser::parseEvents(self::calendar(
            "UID:week20\r\nDTSTART;VALUE=DATE:20250512\r\nDTEND;VALUE=DATE:20250513\r\nRRULE:FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO\r\n",
        ), new \DateTime('2026-01-01'), new \DateTime('2027-01-01'), 'Europe/Berlin');
        self::assertSame(['2026-05-11 00:00-00:00'], $dates($events));
        self::assertSame(1, $events[0]['allday']);

        // a rule without dates in the range, an event without end
        self::assertSame([], IcsParser::parseEvents(self::calendar("UID:past\r\nDTSTART:20200101T100000Z\r\nRRULE:FREQ=DAILY;COUNT=3\r\n"), new \DateTime('2026-01-01'), new \DateTime('2027-01-01'), 'Europe/Berlin'));

        // an invalid rule is passed to sabre/vobject
        $events = IcsParser::parseEvents(self::calendar("UID:invalid\r\nDTSTART:20261110T090000Z\r\nRRULE:FREQ=DAILY;COUNT=2;BYSETPOS=x\r\n"), new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), 'Europe/Berlin');
        self::assertCount(2, $events);
    }

    #[Test]
    public function statusAndClassification(): void
    {
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000Z\r\nSTATUS:cancelled\r\nCLASS:CONFIDENTIAL\r\n"));
        self::assertSame(['CANCELLED', 'CONFIDENTIAL'], [$values['status'], $values['classification']]);
        // PUBLIC is the default, unknown values are ignored
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000Z\r\nSTATUS:X-DONE\r\nCLASS:PUBLIC\r\n"));
        self::assertSame(['', ''], [$values['status'], $values['classification']]);
        $values = IcsParser::parseIcsContent(self::calendar("DTSTART:20261124T100000Z\r\n"));
        self::assertSame(['', ''], [$values['status'], $values['classification']]);

        $events = IcsParser::parseEvents(self::calendar("UID:a\r\nDTSTART:20261110T090000Z\r\nSTATUS:TENTATIVE\r\nCLASS:PRIVATE\r\n"), new \DateTime('2026-11-01'), new \DateTime('2026-12-01'), 'Europe/Berlin');
        self::assertSame(['TENTATIVE', 'PRIVATE'], [$events[0]['status'], $events[0]['classification']]);
    }
}
