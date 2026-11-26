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
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Utility\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RecurrenceRule::class)]
final class RecurrenceRuleTest extends UnitTestCase
{
    /**
     * @return list<string> the occurrences as "YmdTHi"
     */
    private static function occurrences(string $rule, string $start, string $end = '2030-12-31 23:59:59', string $timezone = 'America/New_York'): array
    {
        $dates = [];
        foreach ((new RecurrenceRule($rule))->getOccurrences(new \DateTimeImmutable($start), new \DateTimeImmutable($end), new \DateTimeZone($timezone)) as $date) {
            $dates[] = $date->format('Ymd\THi');
        }
        return $dates;
    }

    /**
     * The examples of RFC 5545 3.8.5.3 (DTSTART;TZID=America/New_York)
     *
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: int, 4: list<string>}>
     */
    public static function rfcExamplesDataProvider(): array
    {
        return [
            'daily for 10 occurrences' => ['FREQ=DAILY;COUNT=10', '1997-09-02 09:00', null, 10, ['19970902T0900', '19970903T0900', '19970911T0900']],
            'daily until December 24, 1997' => ['FREQ=DAILY;UNTIL=19971224T000000Z', '1997-09-02 09:00', null, 113, ['19970902T0900', '19971223T0900']],
            'every 10 days, 5 occurrences' => ['FREQ=DAILY;INTERVAL=10;COUNT=5', '1997-09-02 09:00', null, 5, ['19970902T0900', '19970912T0900', '19971012T0900']],
            'every day in January, for 3 years (yearly)' => ['FREQ=YEARLY;UNTIL=20000131T140000Z;BYMONTH=1;BYDAY=SU,MO,TU,WE,TH,FR,SA', '1998-01-01 09:00', null, 93, ['19980101T0900', '19990101T0900', '20000131T0900']],
            'every day in January, for 3 years (daily)' => ['FREQ=DAILY;UNTIL=20000131T140000Z;BYMONTH=1', '1998-01-01 09:00', null, 93, ['19980101T0900', '19980131T0900', '19990101T0900', '20000131T0900']],
            'weekly for 10 occurrences' => ['FREQ=WEEKLY;COUNT=10', '1997-09-02 09:00', null, 10, ['19970902T0900', '19970909T0900', '19971104T0900']],
            'every other week' => ['FREQ=WEEKLY;INTERVAL=2;WKST=SU', '1997-09-02 09:00', '1998-02-17 09:00', 13, ['19970902T0900', '19970916T0900', '19980217T0900']],
            'weekly on Tuesday and Thursday' => ['FREQ=WEEKLY;UNTIL=19971007T000000Z;WKST=SU;BYDAY=TU,TH', '1997-09-02 09:00', null, 10, ['19970902T0900', '19970904T0900', '19971002T0900']],
            'every other week on Monday, Wednesday and Friday' => ['FREQ=WEEKLY;INTERVAL=2;UNTIL=19971224T000000Z;WKST=SU;BYDAY=MO,WE,FR', '1997-09-01 09:00', null, 25, ['19970901T0900', '19970903T0900', '19970905T0900', '19970915T0900', '19971222T0900']],
            'every other week on Tuesday and Thursday, for 8 occurrences' => ['FREQ=WEEKLY;INTERVAL=2;COUNT=8;WKST=SU;BYDAY=TU,TH', '1997-09-02 09:00', null, 8, ['19970902T0900', '19970904T0900', '19970916T0900', '19971016T0900']],
            'monthly on the first Friday' => ['FREQ=MONTHLY;COUNT=10;BYDAY=1FR', '1997-09-05 09:00', null, 10, ['19970905T0900', '19971003T0900', '19980605T0900']],
            'every other month on the first and last Sunday' => ['FREQ=MONTHLY;INTERVAL=2;COUNT=10;BYDAY=1SU,-1SU', '1997-09-07 09:00', null, 10, ['19970907T0900', '19970928T0900', '19971102T0900', '19980531T0900']],
            'monthly on the second-to-last Monday' => ['FREQ=MONTHLY;COUNT=6;BYDAY=-2MO', '1997-09-22 09:00', null, 6, ['19970922T0900', '19971020T0900', '19980216T0900']],
            'monthly on the third-to-the-last day' => ['FREQ=MONTHLY;BYMONTHDAY=-3', '1997-09-28 09:00', '1998-02-26 09:00', 6, ['19970928T0900', '19971029T0900', '19980226T0900']],
            'monthly on the first and last day' => ['FREQ=MONTHLY;COUNT=10;BYMONTHDAY=1,-1', '1997-09-30 09:00', null, 10, ['19970930T0900', '19971001T0900', '19971031T0900', '19980201T0900']],
            'every 18 months on the 10th thru 15th' => ['FREQ=MONTHLY;INTERVAL=18;COUNT=10;BYMONTHDAY=10,11,12,13,14,15', '1997-09-10 09:00', null, 10, ['19970910T0900', '19970915T0900', '19990310T0900', '19990313T0900']],
            'every Tuesday, every other month' => ['FREQ=MONTHLY;INTERVAL=2;BYDAY=TU', '1997-09-02 09:00', '1998-03-31 09:00', 18, ['19970902T0900', '19970930T0900', '19971104T0900', '19980331T0900']],
            'yearly in June and July' => ['FREQ=YEARLY;COUNT=10;BYMONTH=6,7', '1997-06-10 09:00', null, 10, ['19970610T0900', '19970710T0900', '20010710T0900']],
            'every other year on January, February, and March' => ['FREQ=YEARLY;INTERVAL=2;COUNT=10;BYMONTH=1,2,3', '1997-03-10 09:00', null, 10, ['19970310T0900', '19990110T0900', '20030310T0900']],
            'every third year on the 1st, 100th, and 200th day' => ['FREQ=YEARLY;INTERVAL=3;COUNT=10;BYYEARDAY=1,100,200', '1997-01-01 09:00', null, 10, ['19970101T0900', '19970410T0900', '19970719T0900', '20000101T0900', '20060101T0900']],
            'every 20th Monday of the year' => ['FREQ=YEARLY;BYDAY=20MO', '1997-05-19 09:00', '1999-12-31', 3, ['19970519T0900', '19980518T0900', '19990517T0900']],
            'Monday of week number 20' => ['FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO', '1997-05-12 09:00', '1999-12-31', 3, ['19970512T0900', '19980511T0900', '19990517T0900']],
            'every Thursday in March' => ['FREQ=YEARLY;BYMONTH=3;BYDAY=TH', '1997-03-13 09:00', '1999-12-31', 11, ['19970313T0900', '19970320T0900', '19990325T0900']],
            'every Thursday, but only during June, July, and August' => ['FREQ=YEARLY;BYDAY=TH;BYMONTH=6,7,8', '1997-06-05 09:00', '1999-12-31', 39, ['19970605T0900', '19990826T0900']],
            'every Friday the 13th' => ['FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13', '1998-02-13 09:00', '2000-10-31', 5, ['19980213T0900', '19980313T0900', '19981113T0900', '19990813T0900', '20001013T0900']],
            'the first Saturday that follows the first Sunday' => ['FREQ=MONTHLY;BYDAY=SA;BYMONTHDAY=7,8,9,10,11,12,13', '1997-09-13 09:00', '1998-06-30', 10, ['19970913T0900', '19971011T0900', '19980613T0900']],
            'U.S. Presidential Election day' => ['FREQ=YEARLY;INTERVAL=4;BYMONTH=11;BYDAY=TU;BYMONTHDAY=2,3,4,5,6,7,8', '1996-11-05 09:00', '2004-12-31', 3, ['19961105T0900', '20001107T0900', '20041102T0900']],
            'the third instance of Tuesday, Wednesday, or Thursday' => ['FREQ=MONTHLY;COUNT=3;BYDAY=TU,WE,TH;BYSETPOS=3', '1997-09-04 09:00', null, 3, ['19970904T0900', '19971007T0900', '19971106T0900']],
            'the second-to-last weekday of the month' => ['FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-2', '1997-09-29 09:00', '1998-03-31', 7, ['19970929T0900', '19971030T0900', '19971127T0900', '19980330T0900']],
            'every 3 hours (UNTIL 17:00 UTC is 13:00 EDT)' => ['FREQ=HOURLY;INTERVAL=3;UNTIL=19970902T170000Z', '1997-09-02 09:00', null, 2, ['19970902T0900', '19970902T1200']],
            'every 15 minutes for 6 occurrences' => ['FREQ=MINUTELY;INTERVAL=15;COUNT=6', '1997-09-02 09:00', null, 6, ['19970902T0900', '19970902T0915', '19970902T1015']],
            'every hour and a half for 4 occurrences' => ['FREQ=MINUTELY;INTERVAL=90;COUNT=4', '1997-09-02 09:00', null, 4, ['19970902T0900', '19970902T1030', '19970902T1200', '19970902T1330']],
            'every 20 minutes from 9:00 to 16:40 (daily)' => ['FREQ=DAILY;BYHOUR=9,10,11,12,13,14,15,16;BYMINUTE=0,20,40', '1997-09-02 09:00', '1997-09-03 16:40', 48, ['19970902T0900', '19970902T0920', '19970902T1640', '19970903T0900', '19970903T1640']],
            'every 20 minutes from 9:00 to 16:40 (minutely)' => ['FREQ=MINUTELY;INTERVAL=20;BYHOUR=9,10,11,12,13,14,15,16', '1997-09-02 09:00', '1997-09-03 16:40', 48, ['19970902T0900', '19970902T1640', '19970903T0900', '19970903T1640']],
            'WKST=MO' => ['FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=MO', '1997-08-05 09:00', null, 4, ['19970805T0900', '19970810T0900', '19970819T0900', '19970824T0900']],
            'WKST=SU' => ['FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=SU', '1997-08-05 09:00', null, 4, ['19970805T0900', '19970817T0900', '19970819T0900', '19970831T0900']],
            'invalid dates are ignored' => ['FREQ=MONTHLY;BYMONTHDAY=15,30;COUNT=5', '2007-01-15 09:00', null, 5, ['20070115T0900', '20070130T0900', '20070215T0900', '20070315T0900', '20070330T0900']],
        ];
    }

    /**
     * @param list<string> $expected dates contained in the result, the first and last date are the first and last occurrence
     */
    #[Test]
    #[DataProvider('rfcExamplesDataProvider')]
    public function rfcExamples(string $rule, string $start, ?string $end, int $count, array $expected): void
    {
        $dates = self::occurrences($rule, $start, $end ?? '2030-12-31 23:59:59');
        self::assertCount($count, $dates);
        self::assertSame($expected[0], $dates[0]);
        self::assertSame(end($expected), end($dates));
        foreach ($expected as $date) {
            self::assertContains($date, $dates);
        }
        self::assertSame($dates, array_values(array_unique($dates)));
        $sorted = $dates;
        sort($sorted);
        self::assertSame($sorted, $dates);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    public static function furtherRulesDataProvider(): array
    {
        return [
            'a start not matching the rule is an additional date (like Thunderbird)' => ['FREQ=MONTHLY;BYMONTHDAY=1;COUNT=3', '2026-01-15 10:00', '2026-12-31', ['20260115T1000', '20260201T1000', '20260301T1000', '20260401T1000']],
            'the last day of the month, start on the 1st' => ['FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=5', '2026-12-01 14:00', '2027-12-31', ['20261201T1400', '20261231T1400', '20270131T1400', '20270228T1400', '20270331T1400', '20270430T1400']],
            'a matching start counts as first occurrence' => ['FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=3', '2026-12-31 14:00', '2027-12-31', ['20261231T1400', '20270131T1400', '20270228T1400']],
            'COUNT=1' => ['FREQ=DAILY;COUNT=1', '2026-01-15 10:00', '2026-12-31', ['20260115T1000']],
            'the last workday of a quarter (yearly BYSETPOS)' => ['FREQ=YEARLY;BYMONTH=3,6,9,12;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1', '2026-03-31 10:00', '2026-12-31', ['20260331T1000']],
            'the last workday of every month' => ['FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1,-1,99', '2026-01-30 10:00', '2026-04-30 23:00', ['20260130T1000', '20260227T1000', '20260331T1000', '20260430T1000']],
            'the last week of the year' => ['FREQ=YEARLY;BYWEEKNO=-1;BYDAY=MO', '2026-12-28 10:00', '2028-12-31', ['20261228T1000', '20271227T1000', '20281225T1000']],
            'week 1 can start in December' => ['FREQ=YEARLY;BYWEEKNO=1;BYDAY=MO', '2024-12-30 10:00', '2026-12-31', ['20241230T1000', '20251229T1000']],
            'the last day of the year' => ['FREQ=YEARLY;BYYEARDAY=-1', '2027-12-31 10:00', '2028-12-31 23:00', ['20271231T1000', '20281231T1000']],
            'the 29th of February' => ['FREQ=YEARLY', '2024-02-29 10:00', '2032-12-31', ['20240229T1000', '20280229T1000', '20320229T1000']],
            'the 31st of the month' => ['FREQ=MONTHLY', '2026-01-31 10:00', '2026-05-31 23:00', ['20260131T1000', '20260331T1000', '20260531T1000']],
            'every second hour between 8 and 12 o\'clock' => ['FREQ=HOURLY;INTERVAL=2;BYHOUR=8,10,12;BYMINUTE=0,30', '2026-03-28 08:00', '2026-03-29 08:30', ['20260328T0800', '20260328T0830', '20260328T1000', '20260328T1030', '20260328T1200', '20260328T1230', '20260329T0800', '20260329T0830']],
            'hourly, no daylight saving time gap' => ['FREQ=HOURLY;COUNT=4', '2026-03-29 01:00', '2026-03-30', ['20260329T0100', '20260329T0200', '20260329T0300', '20260329T0400']],
            'secondly' => ['FREQ=SECONDLY;INTERVAL=20;BYSECOND=0,40;COUNT=4', '2026-01-01 10:00', '2026-01-02', ['20260101T1000', '20260101T1000', '20260101T1001', '20260101T1001']],
            'minutely on weekends' => ['FREQ=MINUTELY;INTERVAL=720;BYDAY=SA,SU;COUNT=4', '2026-11-28 00:00', '2026-12-31', ['20261128T0000', '20261128T1200', '20261129T0000', '20261129T1200']],
            'daily on the 1st of the month' => ['FREQ=DAILY;BYMONTHDAY=1;COUNT=3', '2026-01-01 10:00', '2026-12-31', ['20260101T1000', '20260201T1000', '20260301T1000']],
            'weekly in summer' => ['FREQ=WEEKLY;BYMONTH=7;BYDAY=WE;COUNT=3', '2026-07-01 10:00', '2026-12-31', ['20260701T1000', '20260708T1000', '20260715T1000']],
            'ordinals of a weekly rule are ignored' => ['FREQ=WEEKLY;BYDAY=1MO;COUNT=2', '2026-11-02 10:00', '2026-12-31', ['20261102T1000', '20261109T1000']],
            'monthly with BYYEARDAY' => ['FREQ=MONTHLY;BYYEARDAY=32;COUNT=2', '2026-01-01 10:00', '2027-12-31', ['20260101T1000', '20260201T1000', '20270201T1000']],
            'UNTIL as date' => ['FREQ=DAILY;UNTIL=20260103', '2026-01-01 10:00', '2026-12-31', ['20260101T1000', '20260102T1000', '20260103T1000']],
            'UNTIL as wall clock time' => ['FREQ=DAILY;UNTIL=20260103T095959', '2026-01-01 10:00', '2026-12-31', ['20260101T1000', '20260102T1000']],
            'the range ends before UNTIL' => ['FREQ=DAILY;UNTIL=20260110', '2026-01-01 10:00', '2026-01-02 10:00', ['20260101T1000', '20260102T1000']],
            'a start after the range' => ['FREQ=DAILY', '2027-01-01 10:00', '2026-12-31', []],
            'a rule without occurrence' => ['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30', '2026-01-01 10:00', '2040-12-31', ['20260101T1000']],
            'extensions are ignored' => ['RRULE:FREQ=DAILY;COUNT=2;X-NAME=1;;RSCALE=GREGORIAN', '2026-01-01 10:00', '2026-12-31', ['20260101T1000', '20260102T1000']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('furtherRulesDataProvider')]
    public function furtherRules(string $rule, string $start, string $end, array $expected): void
    {
        self::assertSame($expected, self::occurrences($rule, $start, $end, 'Europe/Berlin'));
    }

    #[Test]
    public function untilInUtcIsConvertedIntoTheTimeZone(): void
    {
        $rule = new RecurrenceRule('FREQ=DAILY;UNTIL=20260102T090000Z');
        $dates = static fn(?\DateTimeZone $timezone): array => array_map(
            static fn(\DateTimeImmutable $date): string => $date->format('Ymd\THi'),
            iterator_to_array($rule->getOccurrences(new \DateTimeImmutable('2026-01-01 10:00'), new \DateTimeImmutable('2026-12-31'), $timezone), false),
        );
        // 09:00 UTC is 10:00 in Berlin
        self::assertSame(['20260101T1000', '20260102T1000'], $dates(new \DateTimeZone('Europe/Berlin')));
        self::assertSame(['20260101T1000'], $dates(null));
    }

    #[Test]
    public function theWallClockTimeOfACalendarDateTimeIsUsed(): void
    {
        $start = CalendarDateTime::generateByYmdHis('20261102143000');
        $dates = iterator_to_array((new RecurrenceRule('FREQ=WEEKLY;COUNT=2'))->getOccurrences($start, new \DateTimeImmutable('2026-12-31')), false);
        self::assertSame(['20261102143000', '20261109143000'], array_map(static fn(\DateTimeImmutable $date): string => $date->format('YmdHis'), $dates));
        self::assertSame('UTC', $dates[0]->getTimezone()->getName());
    }

    #[Test]
    public function theStartMatchesTheRule(): void
    {
        self::assertTrue((new RecurrenceRule('FREQ=WEEKLY;BYDAY=MO,WE'))->matches(new \DateTimeImmutable('2026-11-30 10:00')));
        self::assertFalse((new RecurrenceRule('FREQ=WEEKLY;BYDAY=MO,WE'))->matches(new \DateTimeImmutable('2026-12-01 10:00')));
        // values of the start (here the weekday) are used if the rule has none
        self::assertTrue((new RecurrenceRule('FREQ=WEEKLY'))->matches(new \DateTimeImmutable('2026-12-01 10:00')));
        self::assertFalse((new RecurrenceRule('FREQ=DAILY;BYHOUR=9'))->matches(new \DateTimeImmutable('2026-12-01 10:00')));
    }

    #[Test]
    public function partsAndString(): void
    {
        $rule = new RecurrenceRule('rrule:byday=mo,tu;freq=weekly;until=20261231T235959Z;wkst=su');
        self::assertSame(['BYDAY' => 'MO,TU', 'FREQ' => 'WEEKLY', 'UNTIL' => '20261231T235959Z', 'WKST' => 'SU'], $rule->getParts());
        self::assertSame('WEEKLY', $rule->getFrequency());
        self::assertNull($rule->getCount());
        self::assertSame('20261231T235959Z', $rule->getUntil());
        // FREQ first
        self::assertSame('FREQ=WEEKLY;BYDAY=MO,TU;UNTIL=20261231T235959Z;WKST=SU', (string)$rule);
        self::assertSame('FREQ=WEEKLY;BYDAY=MO,TU;WKST=SU;UNTIL=20261231', (string)$rule->withUntil('20261231'));
        self::assertSame('FREQ=WEEKLY;BYDAY=MO,TU;WKST=SU', (string)$rule->withUntil(null));
        self::assertSame(3, (new RecurrenceRule('FREQ=DAILY;COUNT=3'))->getCount());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidRulesDataProvider(): array
    {
        return [
            'without FREQ' => ['COUNT=3'],
            'unknown FREQ' => ['FREQ=WEEKDAYS'],
            'INTERVAL=0' => ['FREQ=DAILY;INTERVAL=0'],
            'COUNT is no number' => ['FREQ=DAILY;COUNT=x'],
            'invalid UNTIL' => ['FREQ=DAILY;UNTIL=2026-12-31'],
            'BYHOUR=24' => ['FREQ=DAILY;BYHOUR=24'],
            'negative BYMONTH' => ['FREQ=YEARLY;BYMONTH=-1'],
            'BYMONTHDAY=0' => ['FREQ=MONTHLY;BYMONTHDAY=0'],
            'BYYEARDAY=367' => ['FREQ=YEARLY;BYYEARDAY=367'],
            'BYWEEKNO is no number' => ['FREQ=YEARLY;BYWEEKNO=x'],
            'invalid BYDAY' => ['FREQ=WEEKLY;BYDAY=XX'],
            'BYDAY ordinal 54' => ['FREQ=YEARLY;BYDAY=54MO'],
            'invalid WKST' => ['FREQ=WEEKLY;WKST=XX'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRulesDataProvider')]
    public function invalidRulesThrowAnException(string $rule): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecurrenceRule($rule);
    }
}
