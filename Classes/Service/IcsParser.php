<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Utility\RecurrenceRule;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;

/**
 * Converts iCalendar data into field arrays for tx_cal_domain_model_event.
 */
class IcsParser
{
    /**
     * Recurrence fields of an event without recurrence. A CalDAV client removing the recurrence of a series
     * sends no RRULE / RDATE, the stored values must be reset.
     */
    private const NO_RECURRENCE = [
        'freq' => 'none',
        'intrval' => 1,
        'cnt' => 0,
        'byday' => '',
        'bymonth' => '',
        'bymonthday' => '',
        'until' => 0,
        'rdate_type' => 'none',
        'rdate' => '',
        'rrule' => '',
    ];

    /**
     * Parts of a rule the recurrence fields of cal can store, by frequency. Rules with other parts are
     * stored in the field "rrule" as well (see RecurrenceGenerator).
     */
    private const FIELD_RULE_PARTS = [
        'DAILY' => ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'WKST'],
        'WEEKLY' => ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'WKST', 'BYDAY'],
        'MONTHLY' => ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'WKST', 'BYDAY', 'BYMONTHDAY'],
        'YEARLY' => ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'WKST', 'BYDAY', 'BYMONTHDAY', 'BYMONTH'],
    ];

    /**
     * Largest value of the fields "cnt" and "intrval" (tinyint unsigned)
     */
    private const MAX_FIELD_NUMBER = 255;

    /**
     * The event (the VEVENT without RECURRENCE-ID) as field array, including its recurrence (RRULE, RDATE).
     * Removed dates (EXDATE) and changed occurrences (RECURRENCE-ID) are returned by parseRecurrenceDetails().
     */
    public static function parseIcsContent(string $icsContent, ?string $timezone = null): array
    {
        $vcalendar = self::read($icsContent);
        $vEvent = $vcalendar !== null ? self::findMasterEvent($vcalendar) : null;
        if ($vEvent === null) {
            return [];
        }
        $localTimezone = self::getTimezone($timezone);
        $values = ['ics_uid' => isset($vEvent->UID) ? (string)$vEvent->UID : '']
            + self::parseEventValues($vEvent, $localTimezone)
            + self::parseStatus($vEvent)
            + ['tstamp' => time()]
            + self::NO_RECURRENCE;

        if (isset($vEvent->RRULE)) {
            $rrule = array_change_key_case($vEvent->RRULE->getParts(), CASE_UPPER);
            $frequency = strtoupper((string)($rrule['FREQ'] ?? ''));
            // cal stores the frequency as none|day|week|month|year, rules with a shorter frequency (HOURLY ...)
            // are calculated with the field "rrule"
            $values['freq'] = match ($frequency) {
                'SECONDLY', 'MINUTELY', 'HOURLY', 'DAILY' => 'day',
                'WEEKLY' => 'week',
                'MONTHLY' => 'month',
                'YEARLY' => 'year',
                default => 'none',
            };
            $values['intrval'] = min(self::MAX_FIELD_NUMBER, (int)($rrule['INTERVAL'] ?? 1));
            $values['cnt'] = min(self::MAX_FIELD_NUMBER, (int)($rrule['COUNT'] ?? 0));
            $values['byday'] = implode(',', (array)($rrule['BYDAY'] ?? []));
            $values['bymonth'] = implode(',', (array)($rrule['BYMONTH'] ?? []));
            $values['bymonthday'] = implode(',', (array)($rrule['BYMONTHDAY'] ?? []));

            $until = null;
            if (isset($rrule['UNTIL'])) {
                // cal stores the last day (midnight, server time) of the recurrence
                $until = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', (string)$rrule['UNTIL'], new \DateTimeZone('UTC'))
                    ?: \DateTimeImmutable::createFromFormat('Ymd\THis', (string)$rrule['UNTIL'], $localTimezone)
                    ?: \DateTimeImmutable::createFromFormat('!Ymd', (string)$rrule['UNTIL'], $localTimezone);
                $values['until'] = $until ? self::midnight($until->setTimezone($localTimezone)) : 0;
            }
            if ($values['freq'] !== 'none' && self::needsRule($frequency, $rrule)) {
                $values['rrule'] = self::buildRule((string)$vEvent->RRULE->getValue(), $until ?: null, $localTimezone, (int)$values['allday'] === 1);
            }
        }
        return array_merge($values, self::parseAdditionalDates($vEvent, $localTimezone));
    }

    /**
     * Removed dates (EXDATE, days "Ymd" in the time zone of the calendar) and the changed occurrences
     * (VEVENTs with RECURRENCE-ID, cancelled occurrences are removed dates) of the event.
     *
     * @return array{exdates: list<string>, overrides: list<array<string, int|string>>}
     */
    public static function parseRecurrenceDetails(string $icsContent, ?string $timezone = null): array
    {
        $result = ['exdates' => [], 'overrides' => []];
        $vcalendar = self::read($icsContent);
        $master = $vcalendar !== null ? self::findMasterEvent($vcalendar) : null;
        if ($vcalendar === null || $master === null) {
            return $result;
        }
        $localTimezone = self::getTimezone($timezone);
        $exdates = [];
        foreach ($master->select('EXDATE') as $exdate) {
            foreach ($exdate->getDateTimes($localTimezone) as $dateTime) {
                $exdates[] = $dateTime->setTimezone($localTimezone)->format('Ymd');
            }
        }
        foreach ($vcalendar->select('VEVENT') as $vEvent) {
            if (!isset($vEvent->{'RECURRENCE-ID'}) || !isset($vEvent->DTSTART)) {
                continue;
            }
            $recurrenceId = $vEvent->{'RECURRENCE-ID'}->getDateTime($localTimezone)->setTimezone($localTimezone);
            if (strtoupper((string)($vEvent->STATUS ?? '')) === 'CANCELLED') {
                $exdates[] = $recurrenceId->format('Ymd');
                continue;
            }
            $orig = self::splitDateAndTime($recurrenceId, !$vEvent->{'RECURRENCE-ID'}->hasTime(), 'orig_start');
            // status and class of the occurrence, empty: those of the event
            $result['overrides'][] = $orig + self::parseEventValues($vEvent, $localTimezone) + self::parseStatus($vEvent, true);
        }
        $exdates = array_values(array_unique($exdates));
        sort($exdates);
        $result['exdates'] = $exdates;
        return $result;
    }

    private static function read(string $icsContent): ?VCalendar
    {
        $vcalendar = Reader::read($icsContent);
        return $vcalendar instanceof VCalendar ? $vcalendar : null;
    }

    /**
     * The VEVENT of the event: the first one without RECURRENCE-ID and with start
     */
    private static function findMasterEvent(VCalendar $vcalendar): ?VEvent
    {
        foreach ($vcalendar->select('VEVENT') as $vEvent) {
            if ($vEvent instanceof VEvent && isset($vEvent->DTSTART) && !isset($vEvent->{'RECURRENCE-ID'})) {
                return $vEvent;
            }
        }
        return null;
    }

    private static function getTimezone(?string $timezone): \DateTimeZone
    {
        if ($timezone !== null && $timezone !== '') {
            try {
                return new \DateTimeZone($timezone);
            } catch (\Exception) {
                // the default time zone of the server
            }
        }
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Title, texts, all-day flag, start and end (wall clock time of the calendar time zone) of a VEVENT
     *
     * @return array<string, int|string>
     */
    private static function parseEventValues(Component $vEvent, \DateTimeZone $localTimezone): array
    {
        /** @var \Sabre\VObject\Property\ICalendar\DateTime $dtStart */
        $dtStart = $vEvent->select('DTSTART')[0];
        // All-day detection: DTSTART is a pure date without time
        $isAllDay = !$dtStart->hasTime();
        $startDate = $dtStart->getDateTime($localTimezone)->setTimezone($localTimezone);
        if (isset($vEvent->DTEND)) {
            $endDate = $vEvent->DTEND->getDateTime($localTimezone)->setTimezone($localTimezone);
        } elseif (isset($vEvent->DURATION)) {
            $endDate = $startDate->add(DateTimeParser::parse((string)$vEvent->DURATION->getValue()));
        } else {
            // RFC 5545: an event without DTEND/DURATION ends on its start day / at DTSTART
            $endDate = $startDate;
        }
        $endDate = self::inclusiveAllDayEnd($startDate, $endDate, $isAllDay, isset($vEvent->DTEND) || isset($vEvent->DURATION));

        return [
            'title' => isset($vEvent->SUMMARY) ? (string)$vEvent->SUMMARY : '',
            'description' => isset($vEvent->DESCRIPTION) ? (string)$vEvent->DESCRIPTION : '',
            'location' => isset($vEvent->LOCATION) ? (string)$vEvent->LOCATION : '',
            'allday' => $isAllDay ? 1 : 0,
        ] + self::splitDateAndTime($startDate, $isAllDay, 'start') + self::splitDateAndTime($endDate, $isAllDay, 'end');
    }

    /**
     * STATUS (TENTATIVE, CONFIRMED, CANCELLED) and CLASS (PRIVATE, CONFIDENTIAL, PUBLIC with $keepPublic)
     * of the event, empty for other values (e.g. extensions like X-...)
     *
     * @return array{status: string, classification: string}
     */
    private static function parseStatus(Component $vEvent, bool $keepPublic = false): array
    {
        $status = strtoupper(trim((string)($vEvent->STATUS ?? '')));
        $classification = strtoupper(trim((string)($vEvent->CLASS ?? '')));
        return [
            'status' => in_array($status, ['TENTATIVE', 'CONFIRMED', 'CANCELLED'], true) ? $status : '',
            // PUBLIC is the default (empty) of an event, a changed occurrence keeps it (public occurrence of a private event)
            'classification' => in_array($classification, $keepPublic ? ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL'] : ['PRIVATE', 'CONFIDENTIAL'], true) ? $classification : '',
        ];
    }

    /**
     * RDATE in the format of the backend form (see RecurrenceGenerator::addRecurringInstances()):
     * dates "20261110", wall clock times "20261110T140000Z", periods "20261110T140000Z/PT2H"
     *
     * @return array{rdate_type?: string, rdate?: string}
     */
    private static function parseAdditionalDates(Component $vEvent, \DateTimeZone $localTimezone): array
    {
        $types = [];
        $values = [];
        foreach ($vEvent->select('RDATE') as $rdate) {
            $valueType = strtoupper((string)($rdate['VALUE'] ?? 'DATE-TIME'));
            $propertyTimezone = isset($rdate['TZID']) ? self::getTimezone((string)$rdate['TZID']) : $localTimezone;
            foreach ($rdate->getParts() as $part) {
                [$start, $end] = array_pad(explode('/', (string)$part, 2), 2, '');
                try {
                    $startDate = DateTimeParser::parse($start, $propertyTimezone)->setTimezone($localTimezone);
                } catch (\Exception) {
                    continue;
                }
                if ($valueType === 'DATE') {
                    $types['date'] = true;
                    $values[] = $startDate->format('Ymd');
                    continue;
                }
                $value = $startDate->format('Ymd\THis') . 'Z';
                if ($valueType === 'PERIOD' && $end !== '') {
                    $types['period'] = true;
                    if (preg_match('/^[+-]?P/', $end)) {
                        $value .= '/' . ltrim($end, '+');
                    } else {
                        try {
                            $value .= '/' . DateTimeParser::parse($end, $propertyTimezone)->setTimezone($localTimezone)->format('Ymd\THis') . 'Z';
                        } catch (\Exception) {
                            // the start only
                        }
                    }
                } else {
                    $types['date_time'] = true;
                }
                $values[] = $value;
            }
        }
        if ($values === []) {
            return [];
        }
        $type = isset($types['period']) ? 'period' : (isset($types['date_time']) ? 'date_time' : 'date');
        return ['rdate_type' => $type, 'rdate' => implode(',', $values)];
    }

    /**
     * The rule has parts the recurrence fields cannot store
     *
     * @param array<string, mixed> $rrule
     */
    private static function needsRule(string $frequency, array $rrule): bool
    {
        $allowed = self::FIELD_RULE_PARTS[$frequency] ?? [];
        if (array_diff(array_keys($rrule), $allowed) !== []) {
            return true;
        }
        return strtoupper((string)($rrule['WKST'] ?? 'MO')) !== 'MO'
            || (int)($rrule['COUNT'] ?? 0) > self::MAX_FIELD_NUMBER
            || (int)($rrule['INTERVAL'] ?? 1) > self::MAX_FIELD_NUMBER;
    }

    /**
     * The rule for the field "rrule": UNTIL as wall clock time of the calendar (like the dates of cal),
     * see IcsRecurrence for the export. An invalid rule is not stored (the fields are used).
     */
    private static function buildRule(string $value, ?\DateTimeImmutable $until, \DateTimeZone $localTimezone, bool $allDay): string
    {
        try {
            $rule = new RecurrenceRule($value);
        } catch (\InvalidArgumentException) {
            return '';
        }
        if ($until !== null) {
            $local = $until->setTimezone($localTimezone);
            $rule = $rule->withUntil($allDay || strlen((string)$rule->getUntil()) === 8 ? $local->format('Ymd') : $local->format('Ymd\THis'));
        }
        return (string)$rule;
    }

    /**
     * Timestamp of midnight (server time) of the day of a date
     */
    private static function midnight(\DateTimeInterface $date): int
    {
        return (new \DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00'))->getTimestamp();
    }

    /**
     * Converts all VEVENTs of an iCalendar string into field arrays for tx_cal_domain_model_event.
     *
     * Recurring events (RRULE/RDATE incl. EXDATE and RECURRENCE-ID overrides) are expanded
     * into single occurrences between $from and $to, so every returned entry is a simple,
     * non recurring event. Each entry additionally contains the key "caldav_data" holding
     * the occurrence as complete VCALENDAR.
     *
     * @return list<array<string, int|string>>
     */
    public static function parseEvents(string $icsContent, \DateTimeInterface $from, \DateTimeInterface $to, ?string $timezone = null): array
    {
        $vcalendar = Reader::read($icsContent, Reader::OPTION_FORGIVING);
        if (!$vcalendar instanceof VCalendar || !isset($vcalendar->VEVENT)) {
            return [];
        }

        $calendarTimezone = new \DateTimeZone(date_default_timezone_get());
        if ($timezone !== null && $timezone !== '') {
            try {
                $calendarTimezone = new \DateTimeZone($timezone);
            } catch (\Exception) {
                // keep the default timezone
            }
        }

        // events without start (invalid, but read in forgiving mode) would break expand()
        foreach ($vcalendar->select('VEVENT') as $vEvent) {
            if (!isset($vEvent->DTSTART)) {
                $vcalendar->remove($vEvent);
            } elseif ($vEvent instanceof VEvent && isset($vEvent->RRULE) && !isset($vEvent->{'RECURRENCE-ID'})) {
                self::replaceRuleByDates($vcalendar, $vEvent, $from, $to, $calendarTimezone);
            }
        }
        $expanded = $vcalendar->expand($from, $to, $calendarTimezone);

        $events = [];
        foreach ($expanded->VEVENT ?? [] as $vEvent) {
            $dtStart = $vEvent->DTSTART;
            $isAllDay = !$dtStart->hasTime();

            // expand() returns timed values in UTC, cal stores the wall clock time of the calendar timezone
            $startDate = $dtStart->getDateTime($calendarTimezone)->setTimezone($calendarTimezone);
            if (isset($vEvent->DTEND)) {
                $endDate = $vEvent->DTEND->getDateTime($calendarTimezone)->setTimezone($calendarTimezone);
            } elseif (isset($vEvent->DURATION)) {
                $endDate = $startDate->add(DateTimeParser::parse((string)$vEvent->DURATION->getValue()));
            } else {
                $endDate = $startDate;
            }
            $endDate = self::inclusiveAllDayEnd($startDate, $endDate, $isAllDay, isset($vEvent->DTEND) || isset($vEvent->DURATION));

            $uid = isset($vEvent->UID) ? (string)$vEvent->UID : md5((string)$vEvent->serialize());
            // Occurrences of a series share the UID, make it unique per occurrence
            if (isset($vEvent->{'RECURRENCE-ID'})) {
                $uid .= '-' . $vEvent->{'RECURRENCE-ID'}->getDateTime(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
            }

            $single = new VCalendar();
            $single->add(clone $vEvent);

            $values = [
                'ics_uid' => $uid,
                'title' => mb_substr(isset($vEvent->SUMMARY) ? (string)$vEvent->SUMMARY : '', 0, 128),
                'description' => isset($vEvent->DESCRIPTION) ? (string)$vEvent->DESCRIPTION : '',
                'location' => mb_substr(isset($vEvent->LOCATION) ? (string)$vEvent->LOCATION : '', 0, 128),
                'allday' => $isAllDay ? 1 : 0,
                'freq' => 'none',
                'caldav_data' => $single->serialize(),
            ] + self::parseStatus($vEvent);
            $values = array_merge($values, self::splitDateAndTime($startDate, $isAllDay, 'start'));
            $values = array_merge($values, self::splitDateAndTime($endDate, $isAllDay, 'end'));
            $events[] = $values;
        }

        return $events;
    }

    /**
     * sabre/vobject does not support all parts of a rule (e.g. MINUTELY, BYSETPOS of a yearly rule, the
     * 20th Monday of a year). The occurrences of the rule in the range are calculated by RecurrenceRule
     * and replace the rule as additional dates (RDATE), so EXDATE and RECURRENCE-ID still work.
     * An invalid rule is kept.
     */
    private static function replaceRuleByDates(VCalendar $vcalendar, VEvent $vEvent, \DateTimeInterface $from, \DateTimeInterface $to, \DateTimeZone $calendarTimezone): void
    {
        try {
            $rule = new RecurrenceRule((string)$vEvent->select('RRULE')[0]->getValue());
        } catch (\InvalidArgumentException) {
            return;
        }
        /** @var \Sabre\VObject\Property\ICalendar\DateTime $dtStart */
        $dtStart = $vEvent->select('DTSTART')[0];
        $allDay = !$dtStart->hasTime();
        // the rule is calculated in the time zone of the start (floating times: time zone of the calendar)
        $start = $dtStart->getDateTime($calendarTimezone);
        $timezone = $start->getTimezone();
        if (isset($vEvent->DTEND)) {
            $duration = max(0, $vEvent->DTEND->getDateTime($calendarTimezone)->getTimestamp() - $start->getTimestamp());
        } elseif (isset($vEvent->DURATION)) {
            $duration = max(0, $start->add(DateTimeParser::parse((string)$vEvent->DURATION->getValue()))->getTimestamp() - $start->getTimestamp());
        } else {
            $duration = 0;
        }
        // occurrences ending in the range are needed as well
        $first = RecurrenceRule::wallClock(\DateTimeImmutable::createFromInterface($from)->setTimezone($timezone))
            ->modify('-' . ($duration + 86400) . ' seconds');
        $last = RecurrenceRule::wallClock(\DateTimeImmutable::createFromInterface($to)->setTimezone($timezone));
        $dates = [];
        foreach ($rule->getOccurrences($start, $last, $timezone) as $index => $occurrence) {
            // the first occurrence is DTSTART
            if ($index > 0 && $occurrence >= $first) {
                $dates[] = new \DateTimeImmutable($occurrence->format('Y-m-d H:i:s'), $timezone);
            }
        }
        $vEvent->remove('RRULE');
        if ($dates === []) {
            return;
        }
        /** @var \Sabre\VObject\Property\ICalendar\DateTime $rdate */
        $rdate = $vcalendar->createProperty('RDATE', null, $allDay ? ['VALUE' => 'DATE'] : []);
        $rdate->setDateTimes($dates, $dtStart->isFloating());
        $vEvent->add($rdate);
    }

    /**
     * In iCalendar the DTEND of an all-day event is exclusive (an event on the 8th and 9th
     * ends on the 10th). cal stores the last day of the event, therefore one day is subtracted.
     */
    private static function inclusiveAllDayEnd(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $isAllDay, bool $hasExplicitEnd): \DateTimeImmutable
    {
        if (!$isAllDay || !$hasExplicitEnd) {
            return $end;
        }
        $lastDay = $end->modify('-1 day');
        return $lastDay < $start ? $start : $lastDay;
    }

    /**
     * cal stores date and time separately, following the TYPO3 conventions of TCA type "datetime":
     * - format "date": unix timestamp of midnight in server local time
     * - format "time": elapsed seconds since midnight (wall clock time, no timezone)
     */
    private static function splitDateAndTime(\DateTimeInterface $dateTime, bool $isAllDay, string $prefix): array
    {
        $date = new \DateTimeImmutable($dateTime->format('Y-m-d') . ' 00:00:00');
        $time = $isAllDay ? 0 : ((int)$dateTime->format('G') * 3600 + (int)$dateTime->format('i') * 60 + (int)$dateTime->format('s'));
        return [
            $prefix . '_date' => $date->getTimestamp(),
            $prefix . '_time' => $time,
        ];
    }
}
