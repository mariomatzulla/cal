<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;

/**
 * Converts the first VEVENT of an iCalendar string into a field array
 * for tx_cal_domain_model_event.
 */
class IcsParser
{
    public static function parseIcsContent(string $icsContent, ?string $timezone = null): array
    {
        $vcalendar = Reader::read($icsContent);
        if (!$vcalendar instanceof VCalendar || !isset($vcalendar->VEVENT)) {
            return [];
        }

        $calendarTimezone = null;
        if ($timezone !== null && $timezone !== '') {
            try {
                $calendarTimezone = new \DateTimeZone($timezone);
            } catch (\Exception) {
                $calendarTimezone = null;
            }
        }

        foreach ($vcalendar->VEVENT as $vEvent) {
            $dtStart = $vEvent->DTSTART;
            if ($dtStart === null) {
                continue;
            }

            // All-day detection: DTSTART is a pure date without time
            $isAllDay = !$dtStart->hasTime();

            $values = [
                'ics_uid' => isset($vEvent->UID) ? (string)$vEvent->UID : '',
                'title' => isset($vEvent->SUMMARY) ? (string)$vEvent->SUMMARY : '',
                'description' => isset($vEvent->DESCRIPTION) ? (string)$vEvent->DESCRIPTION : '',
                'location' => isset($vEvent->LOCATION) ? (string)$vEvent->LOCATION : '',
                'allday' => $isAllDay ? 1 : 0,
                'tstamp' => time(),
            ];

            $localTimezone = $calendarTimezone ?? new \DateTimeZone(date_default_timezone_get());
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

            $values = array_merge($values, self::splitDateAndTime($startDate, $isAllDay, 'start'));
            $values = array_merge($values, self::splitDateAndTime($endDate, $isAllDay, 'end'));

            if (isset($vEvent->RRULE)) {
                $rrule = $vEvent->RRULE->getParts();
                // cal stores the frequency as none|day|week|month|year
                $values['freq'] = match (strtoupper((string)($rrule['FREQ'] ?? ''))) {
                    'DAILY' => 'day',
                    'WEEKLY' => 'week',
                    'MONTHLY' => 'month',
                    'YEARLY' => 'year',
                    default => 'none',
                };
                $values['intrval'] = (int)($rrule['INTERVAL'] ?? 1);
                $values['cnt'] = (int)($rrule['COUNT'] ?? 0);
                $values['byday'] = implode(',', (array)($rrule['BYDAY'] ?? []));
                $values['bymonth'] = implode(',', (array)($rrule['BYMONTH'] ?? []));
                $values['bymonthday'] = implode(',', (array)($rrule['BYMONTHDAY'] ?? []));

                if (isset($rrule['UNTIL'])) {
                    $until = \DateTime::createFromFormat('Ymd\THis\Z', (string)$rrule['UNTIL'], new \DateTimeZone('UTC'))
                        ?: \DateTime::createFromFormat('!Ymd', (string)$rrule['UNTIL']);
                    $values['until'] = $until ? $until->getTimestamp() : 0;
                }
            }

            return $values;
        }

        return [];
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
            ];
            $values = array_merge($values, self::splitDateAndTime($startDate, $isAllDay, 'start'));
            $values = array_merge($values, self::splitDateAndTime($endDate, $isAllDay, 'end'));
            $events[] = $values;
        }

        return $events;
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
