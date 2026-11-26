<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

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
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;

/**
 * Recurrence of an event in iCalendar (RFC 5545): RRULE, RDATE, EXDATE (exception events) and the
 * deviations as overrides (VEVENT with RECURRENCE-ID).
 *
 * Dates and times of cal are wall clock times. With the time zone of the calendar they are exported
 * with TZID, otherwise in UTC (like DTSTART of the template Admin/Ics.html).
 */
class IcsRecurrence
{
    private const FREQUENCIES = ['day' => 'DAILY', 'week' => 'WEEKLY', 'month' => 'MONTHLY', 'year' => 'YEARLY'];

    public function __construct(
        private readonly RecurrenceGenerator $recurrenceGenerator,
    ) {}

    /**
     * RRULE, RDATE and EXDATE lines of the event (separated by line breaks), empty for a single event
     */
    public function getRecurrenceLines(Event $event, string $timezone): string
    {
        if (!$event->isRecurring()) {
            return '';
        }
        $allDay = (bool)$event->isAllday();
        $lines = [];
        $rule = $this->buildRule($event, $timezone);
        if ($rule !== '') {
            $lines[] = 'RRULE:' . $rule;
        }
        array_push($lines, ...self::groupDates('RDATE', $this->buildAdditionalDates($event, $timezone)));
        $excluded = array_map(
            static fn(CalendarDateTime $start): string => self::formatDateTime($start, $allDay, $timezone),
            $this->recurrenceGenerator->findExcludedOccurrences($event),
        );
        array_push($lines, ...self::groupDates('EXDATE', $excluded));
        return implode("\n", $lines);
    }

    /**
     * Occurrences changed by a deviation, for VEVENTs with RECURRENCE-ID. The date values contain the
     * parameters, e.g. ";TZID=Europe/Berlin:20261110T140000", the texts are escaped (RFC 5545).
     *
     * @return list<array{recurrenceId: string, start: string, end: string, title: string, description: string, location: string}>
     */
    public function getOverrides(Event $event, string $timezone): array
    {
        if (!$event->isRecurring()) {
            return [];
        }
        $duration = $event->getEnd()->getTimestamp() - $event->getStart()->getTimestamp();
        $overrides = [];
        foreach ($this->recurrenceGenerator->getDeviationDates($event) as $origStart => $deviation) {
            // numeric array keys are integers
            $origStart = (string)$origStart;
            $start = CalendarDateTime::generateFromDateTime(new \DateTime(substr($origStart, 0, 8) . 'T' . substr($origStart, 8, 6)));
            $end = CalendarDateTime::generateFromDateTime($start);
            $end->addSeconds($duration);
            [$newStart, $newEnd] = RecurrenceGenerator::getDeviationPeriod($deviation, $start, $end);
            $allDay = (bool)$deviation->isAllday();
            if ($allDay) {
                // the end of an all-day event is the day after its last day (DTEND is not included)
                $newEnd->setTime(0, 0);
                $newEnd->modify('+1 day');
            }
            $location = $deviation->getLocationObject()?->getName() ?: $deviation->getLocation()
                ?: ($event->getLocationObject()?->getName() ?: $event->getLocation());
            $overrides[] = [
                'recurrenceId' => self::formatDateTime($start, (bool)$event->isAllday(), $timezone),
                'start' => self::formatDateTime($newStart, $allDay, $timezone),
                'end' => self::formatDateTime($newEnd, $allDay, $timezone),
                'title' => self::escapeText((string)($deviation->getTitle() ?: $event->getTitle())),
                'description' => self::escapeText(trim(strip_tags((string)($deviation->getDescription() ?: $event->getDescription())))),
                'location' => self::escapeText((string)$location),
            ];
        }
        return $overrides;
    }

    /**
     * Parameters and value of a date (time): ";VALUE=DATE:20261110", ";TZID=Europe/Berlin:20261110T140000"
     * or ":20261110T130000Z" (UTC)
     */
    public static function formatDateTime(\DateTimeInterface $wallClock, bool $allDay, string $timezone): string
    {
        $date = $wallClock->format('Ymd');
        if ($allDay) {
            return ';VALUE=DATE:' . $date;
        }
        $time = $wallClock->format('His');
        if ($timezone !== '') {
            return ';TZID=' . $timezone . ':' . $date . 'T' . $time;
        }
        $utc = \DateTimeImmutable::createFromFormat('YmdHis', $date . $time)->setTimezone(new \DateTimeZone('UTC'));
        return ':' . $utc->format('Ymd\THis\Z');
    }

    /**
     * TEXT value (RFC 5545 3.3.11): backslash, semicolon, comma and line breaks are escaped
     */
    public static function escapeText(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', '\n'], $text);
    }

    private function buildRule(Event $event, string $timezone): string
    {
        $frequency = self::FREQUENCIES[(string)$event->getFreq()] ?? null;
        if ($frequency === null) {
            return '';
        }
        $parts = ['FREQ=' . $frequency];
        if ((int)$event->getIntrval() > 1) {
            $parts[] = 'INTERVAL=' . (int)$event->getIntrval();
        }
        if ($event->getUntil() instanceof \DateTimeInterface) {
            // the last day is included, cal stores the day only
            $parts[] = 'UNTIL=' . ($event->isAllday()
                ? $event->getUntil()->format('Ymd')
                : self::toUtc($event->getUntil()->format('Ymd') . '235959', $timezone));
        } elseif ((int)$event->getCnt() > 0) {
            $parts[] = 'COUNT=' . (int)$event->getCnt();
        }
        if ($frequency !== 'DAILY') {
            $byDay = array_filter(
                array_map(static fn(string $value): string => strtoupper(trim($value)), explode(',', (string)$event->getByday())),
                static fn(string $value): bool => (bool)preg_match('/^[+-]?([1-9]|[1-4]\d|5[0-3])?(MO|TU|WE|TH|FR|SA|SU)$/', $value),
            );
            if ($byDay !== []) {
                $parts[] = 'BYDAY=' . implode(',', $byDay);
            }
        }
        if ($frequency === 'MONTHLY' || $frequency === 'YEARLY') {
            $byMonthDay = array_filter(
                array_map('intval', explode(',', (string)$event->getBymonthday())),
                static fn(int $value): bool => $value !== 0 && abs($value) <= 31,
            );
            if ($byMonthDay !== []) {
                $parts[] = 'BYMONTHDAY=' . implode(',', $byMonthDay);
            }
        }
        if ($frequency === 'YEARLY') {
            $byMonth = array_filter(array_map('intval', explode(',', (string)$event->getBymonth())), static fn(int $value): bool => $value >= 1 && $value <= 12);
            if ($byMonth !== []) {
                $parts[] = 'BYMONTH=' . implode(',', $byMonth);
            }
        }
        return implode(';', $parts);
    }

    /**
     * Additional dates as written by the backend form (see RecurrenceGenerator::addRecurringInstances()):
     * dates get the time of the event (all-day events: dates), periods keep their duration or end
     *
     * @return list<string> formatted values with parameters
     */
    private function buildAdditionalDates(Event $event, string $timezone): array
    {
        $type = (string)$event->getRdateType();
        if (!in_array($type, ['date', 'date_time', 'period'], true)) {
            return [];
        }
        $allDay = (bool)$event->isAllday();
        $first = CalendarDateTime::generateFromDateTime($event->getStart());
        $values = [];
        foreach (explode(',', (string)$event->getRdate()) as $value) {
            [$date, $period] = array_pad(explode('/', trim($value), 2), 2, '');
            $start = RecurrenceGenerator::parseRdate($date, $first, $type !== 'date');
            if ($start === null || $start->equals($first)) {
                continue;
            }
            $formatted = self::formatDateTime($start, $allDay, $timezone);
            if ($type === 'period' && !$allDay) {
                $periodEnd = RecurrenceGenerator::parseRdate($period, $start, true);
                $duration = rtrim($period, 'T');
                if ($periodEnd !== null) {
                    $end = self::formatDateTime($periodEnd, false, $timezone);
                    $formatted = ';VALUE=PERIOD' . $formatted . '/' . substr($end, (int)strpos($end, ':') + 1);
                } elseif (preg_match('/^P(\d+[YMWD])*(T(\d+[HMS])+)?$/', $duration) && $duration !== 'P') {
                    $formatted = ';VALUE=PERIOD' . $formatted . '/' . $duration;
                }
            }
            $values[] = $formatted;
        }
        return $values;
    }

    /**
     * One line per parameter combination: "EXDATE;TZID=Europe/Berlin:20261110T140000,20261117T140000"
     *
     * @param list<string> $values formatted values with parameters
     * @return list<string>
     */
    private static function groupDates(string $property, array $values): array
    {
        $groups = [];
        foreach ($values as $value) {
            $separator = (int)strpos($value, ':');
            $groups[substr($value, 0, $separator)][] = substr($value, $separator + 1);
        }
        $lines = [];
        foreach ($groups as $parameters => $dates) {
            $lines[] = $property . $parameters . ':' . implode(',', array_values(array_unique($dates)));
        }
        return $lines;
    }

    /**
     * Wall clock time (YmdHis) in the time zone of the calendar or of the server as UTC value
     */
    private static function toUtc(string $wallClock, string $timezone): string
    {
        $zone = in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? new \DateTimeZone($timezone) : null;
        $dateTime = \DateTimeImmutable::createFromFormat('YmdHis', $wallClock, $zone);
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
