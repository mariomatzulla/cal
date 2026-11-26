<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Utility;

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

/**
 * Recurrence rule (RRULE) of RFC 5545 with all its parts: FREQ (SECONDLY ... YEARLY), INTERVAL, COUNT,
 * UNTIL, BYSECOND, BYMINUTE, BYHOUR, BYDAY, BYMONTHDAY, BYYEARDAY, BYWEEKNO, BYMONTH, BYSETPOS and WKST.
 *
 * The occurrences are calculated with wall clock times (like the dates of cal): the returned dates are
 * DateTimeImmutable objects in UTC whose date and time are the wall clock time of the start.
 */
final class RecurrenceRule
{
    public const FREQUENCIES = ['SECONDLY', 'MINUTELY', 'HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    /**
     * Upper limit of the examined periods (e.g. rules which never occur, like the 30th of February)
     */
    private const MAX_PERIODS = 500000;

    private const WEEKDAYS = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];

    /**
     * The parts of the rule (upper case keys and values) in their original order
     *
     * @var array<string, string>
     */
    private array $parts = [];

    private string $frequency;
    private int $interval = 1;
    private ?int $count = null;
    /** @var list<int> */
    private array $bySecond = [];
    /** @var list<int> */
    private array $byMinute = [];
    /** @var list<int> */
    private array $byHour = [];
    /** @var list<array{0: int, 1: int}> [ordinal (0: every), weekday (0 = Sunday)] */
    private array $byDay = [];
    /** @var list<int> */
    private array $byMonthDay = [];
    /** @var list<int> */
    private array $byYearDay = [];
    /** @var list<int> */
    private array $byWeekNo = [];
    /** @var list<int> */
    private array $byMonth = [];
    /** @var list<int> */
    private array $bySetPos = [];
    private int $weekStart = 1;

    /**
     * @var array<int, \DateTimeImmutable> first day of week 1 by year
     */
    private array $firstWeeks = [];

    /**
     * @param string $rule e.g. "FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1" (with or without "RRULE:")
     * @throws \InvalidArgumentException if the rule is invalid
     */
    public function __construct(string $rule)
    {
        $rule = trim(preg_replace('/^RRULE:/i', '', trim($rule)) ?? '');
        foreach (explode(';', $rule) as $part) {
            if (trim($part) === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $this->parts[strtoupper(trim($key))] = strtoupper(trim($value));
        }
        $this->frequency = $this->parts['FREQ'] ?? '';
        if (!in_array($this->frequency, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('Invalid or missing FREQ in RRULE "' . $rule . '"', 1795000001);
        }
        foreach ($this->parts as $key => $value) {
            match ($key) {
                'INTERVAL' => $this->interval = self::parseInteger($key, $value, 1, PHP_INT_MAX),
                'COUNT' => $this->count = self::parseInteger($key, $value, 1, PHP_INT_MAX),
                'UNTIL' => self::checkUntil($value),
                'BYSECOND' => $this->bySecond = self::parseList($key, $value, 0, 60, false),
                'BYMINUTE' => $this->byMinute = self::parseList($key, $value, 0, 59, false),
                'BYHOUR' => $this->byHour = self::parseList($key, $value, 0, 23, false),
                'BYDAY' => $this->byDay = self::parseByDay($value),
                'BYMONTHDAY' => $this->byMonthDay = self::parseList($key, $value, 1, 31, true),
                'BYYEARDAY' => $this->byYearDay = self::parseList($key, $value, 1, 366, true),
                'BYWEEKNO' => $this->byWeekNo = self::parseList($key, $value, 1, 53, true),
                'BYMONTH' => $this->byMonth = self::parseList($key, $value, 1, 12, false),
                'BYSETPOS' => $this->bySetPos = self::parseList($key, $value, 1, 366, true),
                'WKST' => $this->weekStart = self::WEEKDAYS[$value] ?? throw new \InvalidArgumentException('Invalid WKST "' . $value . '"', 1795000002),
                // FREQ, extensions (e.g. RSCALE / SKIP of RFC 7529, X- parts) are kept, but not evaluated
                default => null,
            };
        }
    }

    /**
     * The parts of the rule, e.g. ['FREQ' => 'MONTHLY', 'BYSETPOS' => '-1']
     *
     * @return array<string, string>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function getUntil(): ?string
    {
        return $this->parts['UNTIL'] ?? null;
    }

    /**
     * The rule with another UNTIL value (null: without UNTIL)
     */
    public function withUntil(?string $until): self
    {
        $parts = $this->parts;
        unset($parts['UNTIL']);
        if ($until !== null) {
            $parts['UNTIL'] = $until;
        }
        return new self(self::buildString($parts));
    }

    public function __toString(): string
    {
        return self::buildString($this->parts);
    }

    /**
     * @param array<string, string> $parts
     */
    private static function buildString(array $parts): string
    {
        // RFC 5545 recommends FREQ as first part
        $parts = ['FREQ' => $parts['FREQ'] ?? ''] + $parts;
        return implode(';', array_map(static fn(string $key, string $value): string => $key . '=' . $value, array_keys($parts), $parts));
    }

    /**
     * Starts of the occurrences from $start (the first occurrence, also if it does not match the rule)
     * until $end (included), limited by COUNT and UNTIL.
     *
     * @param \DateTimeInterface $start start of the event (its date and time are used as wall clock time)
     * @param \DateTimeInterface $end the last possible start (wall clock time)
     * @param \DateTimeZone|null $timezone time zone of the wall clock times, converts an UNTIL in UTC ("...Z")
     * @return \Generator<int, \DateTimeImmutable> wall clock times (DateTimeImmutable in UTC)
     */
    public function getOccurrences(\DateTimeInterface $start, \DateTimeInterface $end, ?\DateTimeZone $timezone = null): \Generator
    {
        $start = self::wallClock($start);
        $limit = self::wallClock($end);
        $until = $this->resolveUntil($timezone);
        if ($until !== null && $until < $limit) {
            $limit = $until;
        }
        if ($start > $limit) {
            return;
        }
        yield $start;
        $defaults = $this->getDefaults($start);
        // RFC 5545 3.3.10: DTSTART counts as first occurrence. A start not matching the rule has an undefined
        // result (3.8.5.3): like most clients (e.g. Thunderbird) it is an additional date then, COUNT does not
        // include it.
        $count = $this->matchesStart($start, $defaults) ? 1 : 0;
        if ($this->count !== null && $count >= $this->count) {
            return;
        }
        $period = $this->getPeriodStart($start);
        for ($i = 0; $i < self::MAX_PERIODS && $period <= $limit; $i++, $period = $this->getNextPeriod($period)) {
            foreach ($this->expandPeriod($period, $defaults) as $occurrence) {
                if ($occurrence <= $start) {
                    continue;
                }
                if ($occurrence > $limit) {
                    return;
                }
                yield $occurrence;
                $count++;
                if ($this->count !== null && $count >= $this->count) {
                    return;
                }
            }
        }
    }

    /**
     * The start is a date of the rule (without COUNT and UNTIL)
     */
    public function matches(\DateTimeInterface $start): bool
    {
        $start = self::wallClock($start);
        return $this->matchesStart($start, $this->getDefaults($start));
    }

    /**
     * @param array{byMonth: list<int>, byMonthDay: list<int>, byDay: list<array{0: int, 1: int}>, byHour: list<int>, byMinute: list<int>, bySecond: list<int>} $defaults
     */
    private function matchesStart(\DateTimeImmutable $start, array $defaults): bool
    {
        foreach ($this->expandPeriod($this->getPeriodStart($start), $defaults) as $date) {
            if ($date == $start) {
                return true;
            }
        }
        return false;
    }

    /**
     * Date and time as wall clock time in UTC (no daylight saving time gaps)
     */
    public static function wallClock(\DateTimeInterface $dateTime): \DateTimeImmutable
    {
        // "YmdHis": CalendarDateTime formats other patterns with ICU
        return \DateTimeImmutable::createFromFormat('!YmdHis', $dateTime->format('YmdHis'), new \DateTimeZone('UTC'));
    }

    /**
     * UNTIL as wall clock time: a date includes the whole day, UTC values ("Z") are converted into the time zone
     */
    private function resolveUntil(?\DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $until = $this->getUntil();
        if ($until === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        if (strlen($until) === 8) {
            return \DateTimeImmutable::createFromFormat('YmdHis', $until . '235959', $utc) ?: null;
        }
        if (str_ends_with($until, 'Z')) {
            $date = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $until, $utc);
            return $date ? self::wallClock($date->setTimezone($timezone ?? $utc)) : null;
        }
        return \DateTimeImmutable::createFromFormat('Ymd\THis', $until, $utc) ?: null;
    }

    /**
     * Values taken from the start if the rule has none (RFC 5545 3.3.10: "information, not contained in the
     * rule, necessary to determine the various recurrence instance start time and dates are derived from
     * the Start Time ("DTSTART")")
     *
     * @return array{byMonth: list<int>, byMonthDay: list<int>, byDay: list<array{0: int, 1: int}>, byHour: list<int>, byMinute: list<int>, bySecond: list<int>}
     */
    private function getDefaults(\DateTimeImmutable $start): array
    {
        $defaults = [
            'byMonth' => $this->byMonth,
            'byMonthDay' => $this->byMonthDay,
            'byDay' => $this->byDay,
            'byHour' => $this->byHour,
            'byMinute' => $this->byMinute,
            'bySecond' => $this->bySecond,
        ];
        if ($this->byWeekNo === [] && $this->byYearDay === [] && $this->byMonthDay === [] && $this->byDay === []) {
            switch ($this->frequency) {
                case 'YEARLY':
                    if ($this->byMonth === []) {
                        $defaults['byMonth'] = [(int)$start->format('n')];
                    }
                    $defaults['byMonthDay'] = [(int)$start->format('j')];
                    break;
                case 'MONTHLY':
                    $defaults['byMonthDay'] = [(int)$start->format('j')];
                    break;
                case 'WEEKLY':
                    $defaults['byDay'] = [[0, (int)$start->format('w')]];
                    break;
            }
        }
        $rank = (int)array_search($this->frequency, self::FREQUENCIES, true);
        if ($defaults['byHour'] === [] && $rank > 2) {
            $defaults['byHour'] = [(int)$start->format('G')];
        }
        if ($defaults['byMinute'] === [] && $rank > 1) {
            $defaults['byMinute'] = [(int)$start->format('i')];
        }
        if ($defaults['bySecond'] === [] && $rank > 0) {
            $defaults['bySecond'] = [(int)$start->format('s')];
        }
        return $defaults;
    }

    private function getPeriodStart(\DateTimeImmutable $start): \DateTimeImmutable
    {
        return match ($this->frequency) {
            'YEARLY' => $start->setDate((int)$start->format('Y'), 1, 1)->setTime(0, 0),
            'MONTHLY' => $start->setDate((int)$start->format('Y'), (int)$start->format('n'), 1)->setTime(0, 0),
            'WEEKLY' => $start->setTime(0, 0)->modify('-' . (((int)$start->format('w') - $this->weekStart + 7) % 7) . ' days'),
            'DAILY' => $start->setTime(0, 0),
            'HOURLY' => $start->setTime((int)$start->format('G'), 0),
            'MINUTELY' => $start->setTime((int)$start->format('G'), (int)$start->format('i')),
            default => $start,
        };
    }

    private function getNextPeriod(\DateTimeImmutable $period): \DateTimeImmutable
    {
        return match ($this->frequency) {
            'YEARLY' => $period->setDate((int)$period->format('Y') + $this->interval, 1, 1),
            'MONTHLY' => $period->setDate((int)$period->format('Y'), (int)$period->format('n') + $this->interval, 1),
            'WEEKLY' => $period->modify('+' . (7 * $this->interval) . ' days'),
            'DAILY' => $period->modify('+' . $this->interval . ' days'),
            'HOURLY' => $period->modify('+' . $this->interval . ' hours'),
            'MINUTELY' => $period->modify('+' . $this->interval . ' minutes'),
            default => $period->modify('+' . $this->interval . ' seconds'),
        };
    }

    /**
     * The occurrences of a period (year, month, week, day, hour, minute or second) in chronological order
     *
     * @param array{byMonth: list<int>, byMonthDay: list<int>, byDay: list<array{0: int, 1: int}>, byHour: list<int>, byMinute: list<int>, bySecond: list<int>} $defaults
     * @return list<\DateTimeImmutable>
     */
    private function expandPeriod(\DateTimeImmutable $period, array $defaults): array
    {
        $days = match ($this->frequency) {
            'YEARLY' => self::getDays($period, $period->setDate((int)$period->format('Y'), 12, 31)),
            'MONTHLY' => self::getDays($period, $period->modify('last day of this month')),
            'WEEKLY' => self::getDays($period, $period->modify('+6 days')),
            default => [$period->setTime(0, 0)],
        };
        $hours = in_array($this->frequency, ['HOURLY', 'MINUTELY', 'SECONDLY'], true)
            ? self::limit([(int)$period->format('G')], $this->byHour)
            : $defaults['byHour'];
        $minutes = in_array($this->frequency, ['MINUTELY', 'SECONDLY'], true)
            ? self::limit([(int)$period->format('i')], $this->byMinute)
            : $defaults['byMinute'];
        $seconds = $this->frequency === 'SECONDLY'
            ? self::limit([(int)$period->format('s')], $this->bySecond)
            : $defaults['bySecond'];
        sort($hours);
        sort($minutes);
        sort($seconds);

        $occurrences = [];
        foreach ($days as $day) {
            if (!$this->matchesDay($day, $defaults)) {
                continue;
            }
            foreach ($hours as $hour) {
                foreach ($minutes as $minute) {
                    foreach ($seconds as $second) {
                        $occurrences[] = $day->setTime($hour, $minute, $second);
                    }
                }
            }
        }
        if ($this->bySetPos === [] || $occurrences === []) {
            return $occurrences;
        }
        $selected = [];
        foreach ($this->bySetPos as $position) {
            $index = $position > 0 ? $position - 1 : count($occurrences) + $position;
            if (isset($occurrences[$index])) {
                $selected[$index] = $occurrences[$index];
            }
        }
        ksort($selected);
        return array_values($selected);
    }

    /**
     * @param list<int> $values
     * @param list<int> $allowed empty: all values
     * @return list<int>
     */
    private static function limit(array $values, array $allowed): array
    {
        return $allowed === [] ? $values : array_values(array_intersect($values, $allowed));
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private static function getDays(\DateTimeImmutable $first, \DateTimeImmutable $last): array
    {
        $days = [];
        for ($day = $first->setTime(0, 0); $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = $day;
        }
        return $days;
    }

    /**
     * @param array{byMonth: list<int>, byMonthDay: list<int>, byDay: list<array{0: int, 1: int}>, byHour: list<int>, byMinute: list<int>, bySecond: list<int>} $defaults
     */
    private function matchesDay(\DateTimeImmutable $day, array $defaults): bool
    {
        $month = (int)$day->format('n');
        $dayOfMonth = (int)$day->format('j');
        if ($defaults['byMonth'] !== [] && !in_array($month, $defaults['byMonth'], true)) {
            return false;
        }
        if ($this->byWeekNo !== [] && !$this->matchesWeekNumber($day)) {
            return false;
        }
        if ($this->byYearDay !== []) {
            $dayOfYear = (int)$day->format('z') + 1;
            $daysInYear = 365 + (int)$day->format('L');
            if (!self::matchesPosition($dayOfYear, $daysInYear, $this->byYearDay)) {
                return false;
            }
        }
        if ($defaults['byMonthDay'] !== [] && !self::matchesPosition($dayOfMonth, (int)$day->format('t'), $defaults['byMonthDay'])) {
            return false;
        }
        if ($defaults['byDay'] === []) {
            return true;
        }
        $weekday = (int)$day->format('w');
        foreach ($defaults['byDay'] as [$ordinal, $byWeekday]) {
            if ($byWeekday !== $weekday) {
                continue;
            }
            // the ordinal ("2MO", "-1FR") counts in the month (MONTHLY, YEARLY with BYMONTH) or the year (YEARLY)
            if ($ordinal === 0 || !in_array($this->frequency, ['MONTHLY', 'YEARLY'], true)) {
                return true;
            }
            if ($this->frequency === 'YEARLY' && $this->byMonth === []) {
                $first = (int)$day->format('z');
                $last = 364 + (int)$day->format('L') - $first;
            } else {
                $first = $dayOfMonth - 1;
                $last = (int)$day->format('t') - $dayOfMonth;
            }
            if ($ordinal > 0 ? intdiv($first, 7) + 1 === $ordinal : -(intdiv($last, 7) + 1) === $ordinal) {
                return true;
            }
        }
        return false;
    }

    /**
     * $value (1 ... $max) matches a positive or negative (counted from the end) value of the list
     *
     * @param list<int> $values
     */
    private static function matchesPosition(int $value, int $max, array $values): bool
    {
        foreach ($values as $position) {
            if ($position === $value || $max + 1 + $position === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Week numbers (RFC 5545 3.3.10): week 1 is the first week (starting on WKST) with at least four days
     * in the year
     */
    private function matchesWeekNumber(\DateTimeImmutable $day): bool
    {
        $year = (int)$day->format('Y');
        $firstWeek = $this->getFirstWeek($year);
        if ($day < $firstWeek) {
            $year--;
            $firstWeek = $this->getFirstWeek($year);
        } elseif ($day >= $this->getFirstWeek($year + 1)) {
            $year++;
            $firstWeek = $this->getFirstWeek($year);
        }
        $week = intdiv((int)$firstWeek->diff($day)->days, 7) + 1;
        $weeks = intdiv((int)$firstWeek->diff($this->getFirstWeek($year + 1))->days, 7);
        return self::matchesPosition($week, $weeks, $this->byWeekNo);
    }

    private function getFirstWeek(int $year): \DateTimeImmutable
    {
        if (!isset($this->firstWeeks[$year])) {
            $january = new \DateTimeImmutable(sprintf('%04d-01-01', $year), new \DateTimeZone('UTC'));
            $offset = ((int)$january->format('w') - $this->weekStart + 7) % 7;
            // the week of January 1st is week 1 if it has at least four days in the year
            $this->firstWeeks[$year] = $january->modify($offset <= 3 ? '-' . $offset . ' days' : '+' . (7 - $offset) . ' days');
        }
        return $this->firstWeeks[$year];
    }

    private static function parseInteger(string $key, string $value, int $min, int $max): int
    {
        if (!preg_match('/^\d+$/', $value) || (int)$value < $min || (int)$value > $max) {
            throw new \InvalidArgumentException('Invalid ' . $key . ' "' . $value . '" in RRULE', 1795000003);
        }
        return (int)$value;
    }

    /**
     * @return list<int>
     */
    private static function parseList(string $key, string $value, int $min, int $max, bool $negative): array
    {
        $values = [];
        foreach (explode(',', $value) as $entry) {
            if (!preg_match($negative ? '/^[+-]?\d+$/' : '/^\+?\d+$/', trim($entry))) {
                throw new \InvalidArgumentException('Invalid ' . $key . ' "' . $value . '" in RRULE', 1795000004);
            }
            $number = (int)trim($entry);
            if (abs($number) < $min || abs($number) > $max) {
                throw new \InvalidArgumentException('Invalid ' . $key . ' "' . $value . '" in RRULE', 1795000004);
            }
            $values[] = $number;
        }
        return array_values(array_unique($values));
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function parseByDay(string $value): array
    {
        $values = [];
        foreach (explode(',', $value) as $entry) {
            if (!preg_match('/^([+-]?)(\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', trim($entry), $match) || (int)$match[2] > 53) {
                throw new \InvalidArgumentException('Invalid BYDAY "' . $value . '" in RRULE', 1795000005);
            }
            $values[] = [($match[1] === '-' ? -1 : 1) * (int)$match[2], self::WEEKDAYS[$match[3]]];
        }
        return $values;
    }

    private static function checkUntil(string $value): void
    {
        if (!preg_match('/^\d{8}(T\d{6}Z?)?$/', $value)) {
            throw new \InvalidArgumentException('Invalid UNTIL "' . $value . '" in RRULE', 1795000006);
        }
    }
}
