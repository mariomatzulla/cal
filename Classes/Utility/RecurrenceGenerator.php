<?php

declare(strict_types=1);

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */

namespace MarioMatzulla\Cal\Utility;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\Index;
use MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Class RecurrenceGenerator
 */
class RecurrenceGenerator
{
    /**
     * The table name of the index table
     */
    public const INDEX_TABLE = 'tx_cal_domain_model_index';

    /**
     * @var CalendarDateTime|null
     */
    public $starttime;

    /**
     * @var CalendarDateTime|null
     */
    public $endtime;

    /**
     * @var array
     */
    public $configuration;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventDeviationRepository $eventDeviationRepository,
        private readonly IndexRepository $indexRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        ExtensionConfiguration $extensionConfiguration,
    ) {
        $this->configuration = (array)$extensionConfiguration->get('cal');
        $this->setRange();
    }

    /**
     * Sets the time range of the index. Empty values use the extension configuration
     * (recurrenceStart / recurrenceEnd). Accepts YYYYMMDD or relative values like "-3 month".
     */
    public function setRange(int|string|null $starttime = null, int|string|null $endtime = null): self
    {
        $this->starttime = CalculateDateTime::parseTcaString(
            (string)($starttime === null || $starttime === '' ? ($this->configuration['recurrenceStart'] ?? '') : $starttime),
            null,
        );
        $this->endtime = CalculateDateTime::parseTcaString(
            (string)($endtime === null || $endtime === '' ? ($this->configuration['recurrenceEnd'] ?? '') : $endtime),
            null,
        );
        return $this;
    }

    /**
     * @param int $pageId
     */
    public function cleanIndexTable(int $pageId)
    {
        $uidList = $this->eventRepository->findUidsForEventsWithPage($pageId);
        $this->indexRepository->deleteByEventUidsAndTablename($uidList, 'tx_cal_event');
    }

    /**
     * @param int $uid
     * @param string $table
     */
    public function cleanIndexTableOfUid(int $uid, string $table)
    {
        $this->indexRepository->deleteByEventUidsAndTablename([$uid], $table);
    }

    /**
     * @param int $uid
     */
    public function cleanIndexTableOfCalendarUid(int $uid)
    {
        $uidList = $this->eventRepository->findUidsForCalendar($uid);
        $this->indexRepository->deleteByEventUidsAndTablename($uidList, 'tx_cal_event');
    }

    /**
     * @param int $eventPage
     *
     * @return int
     */
    public function countRecurringEvents($eventPage = 0): int
    {
        $count = 0;
        if ($eventPage > 0) {
            $count = $this->eventRepository->countWithPage($eventPage);
        } else {
            $count = $this->eventRepository->countAll();
        }

        return $count;
    }

    /**
     * Rebuilds the index of all recurring events of the given page (0 = all pages).
     *
     * @return list<string> titles of the indexed events
     */
    public function generateIndex(int $eventPage = 0): array
    {
        $titles = [];
        foreach ($this->eventRepository->findRecurringEvents($eventPage) as $event) {
            /** @var Event $event */
            $titles[] = (string)$event->getTitle();
            $this->createRecurringInstances($event);
        }
        return $titles;
    }

    /**
     * Generate index for UID of table tx_cal_event
     *
     * @param int $uid
     */
    public function generateIndexForEventUid(int $uid)
    {
        $this->cleanIndexTableOfUid($uid, 'tx_cal_event');
        $event = $this->eventRepository->findByUid($uid);
        if ($event instanceof Event) {
            $this->createRecurringInstances($event);
        }
    }

    /**
     * Generate index for the given calendar
     *
     * @param int $uid
     */
    public function generateIndexForCalendarUid(int $uid)
    {

        $this->cleanIndexTableOfCalendarUid($uid);
        $events = $this->eventRepository->findAllForCalendar($uid);

        foreach ($events as $event) {
            $this->createRecurringInstances($event);
        }
    }

    public function createRecurringInstances(Event $event)
    {
        RecurrenceValidator::validateEvent($event);

        $count = (int)($event->getCnt());
        $until = new CalendarDateTime();
        if ($event->getUntil() != null) {
            // the until date is included (wall clock time like the start of the event)
            $until = CalendarDateTime::generateFromDateTime($event->getUntil());
            $until->setTime(23, 59, 59);
            if ($this->endtime->before($until)) {
                $until->copy($this->endtime);
            }
        } else {
            $until->copy($this->endtime);
        }

        $byyear =  [];
        $eventStart = new CalendarDateTime();
        $eventStart->copy($event->getStart());
        $i = $eventStart->getYear();
        if ($event->getFreq() == 'year') {
            $i = (int)($this->starttime->getYear()) - (($this->starttime->getYear() - $eventStart->getYear()) % $event->getIntrval());
        }

        // RFC 5545: INTERVAL of a yearly rule counts years (every n-th year)
        $yearStep = $event->getFreq() == 'year' ? max(1, (int)$event->getIntrval()) : 1;
        for (; $i < (int)($until->getYear()) + 1; $i += $yearStep) {
            $byyear [] = $i;
        }
        // weekly, monthly and yearly rules: the interval counts weeks, months or years, not single dates
        $occurrenceInterval = $event->getFreq() == 'day' ? null : 1;

        $maxRecurringEvents = $count;

        $counter = 1;
        $total = 1;

        // if the 'parent' event is still in future, set $added to 1 (true), because we already have one instance of this event
        $added = (int)$eventStart->isFuture();
        $nextOccuranceTime = new CalendarDateTime();
        $nextOccuranceTime->copy($event->getStart());
        $nextOccuranceTime->modify('+1 day');

        if ($event->getRdateType() && $event->getRdateType() != 'none') {
            $this->addRecurringInstances($event);
        }
        switch ($event->getFreq()) {
            case 'day':
                $this->addDailyOccurrance($event, $nextOccuranceTime, $until, $event->getByday(), $count, $counter, $total, $added, $maxRecurringEvents);
                break;
            case 'week':
            case 'month':
            case 'year':
                $bymonth = explode(',', (string)$event->getBymonth());
                $byday = explode(',', (string)$event->getByday());
                $hour = $eventStart->getHour();
                $minute = $eventStart->getMinute();
                // 2007, 2008...
                foreach ($byyear as $year) {
                    $year = (int)$year;
                    if (!($counter < $count && $until->after($nextOccuranceTime) && $added < $maxRecurringEvents)) {
                        $this->persistenceManager->persistAll();
                        return;
                    }
                    foreach ($this->getOccurrenceDaysOfYear($event, $year, $bymonth) as [$month, $day]) {
                        $nextOccuranceTime->setTime((int)$hour, (int)$minute, 0);
                        $nextOccuranceTime->setDate($year, $month, $day);
                        if (!($counter < $count && ($until->after($nextOccuranceTime) || $until->equals($nextOccuranceTime)) && $added < $maxRecurringEvents)) {
                            $this->persistenceManager->persistAll();
                            return;
                        }
                        if (($eventStart->before($nextOccuranceTime) || $eventStart->equals($nextOccuranceTime)) && self::isInInterval($event, $eventStart, $nextOccuranceTime)) {
                            $currentUntil = new CalendarDateTime();
                            $currentUntil->copy($nextOccuranceTime);
                            $currentUntil->addSeconds(86399);
                            $this->addDailyOccurrance($event, $nextOccuranceTime, $currentUntil, $byday, $count, $counter, $total, $added, $maxRecurringEvents, $occurrenceInterval);
                        }
                    }
                }
                break; // switch-case break
        }
        $this->persistenceManager->persistAll();
    }

    /**
     * Weekly and monthly rules with an interval: only every n-th week (starting on Monday, RFC 5545
     * WKST=MO) or month counted from the start of the event has dates
     */
    private static function isInInterval(Event $event, CalendarDateTime $eventStart, CalendarDateTime $date): bool
    {
        $interval = max(1, (int)$event->getIntrval());
        if ($interval === 1) {
            return true;
        }
        if ($event->getFreq() === 'week') {
            $startMonday = \DateTimeImmutable::createFromFormat('!Ymd', $eventStart->format('Ymd'))->modify('monday this week');
            $dateMonday = \DateTimeImmutable::createFromFormat('!Ymd', $date->format('Ymd'))->modify('monday this week');
            $weeks = intdiv((int)$startMonday->diff($dateMonday)->days, 7);
            return $weeks % $interval === 0;
        }
        if ($event->getFreq() === 'month') {
            $months = ($date->getYear() * 12 + $date->getMonth()) - ($eventStart->getYear() * 12 + $eventStart->getMonth());
            return $months % $interval === 0;
        }
        return true;
    }

    /**
     * @param int|null $interval every n-th occurrence (default: INTERVAL of the event)
     */
    private function addDailyOccurrance($event, CalendarDateTime &$startRange, CalendarDateTime &$endRange, $weekdays, $maxCount, &$currentCount, &$totalCount, &$addedCount, &$maxRecurringEvents, ?int $interval = null)
    {
        $interval = max(1, $interval ?? (int)$event->getIntrval());
        $nextOccuranceTime = new CalendarDateTime();
        $nextOccuranceTime->copy($startRange);
        while ($currentCount < $maxCount && ($nextOccuranceTime->before($endRange) || $nextOccuranceTime->equals($endRange)) && $addedCount < $maxRecurringEvents) {
            if (! $nextOccuranceTime->equals($event->getStart())) {
                if (($totalCount % $interval) == 0) {
                    $nextOccuranceEndTime = new CalendarDateTime();
                    $nextOccuranceEndTime->copy($nextOccuranceTime);
                    $nextOccuranceEndTime->addSeconds($event->getEnd()->getTimestamp() -  $event->getStart()->getTimestamp());
                    if ($this->starttime->before($nextOccuranceEndTime) || $this->starttime->equals($nextOccuranceTime)) {
                        $this->createIndexEntry($nextOccuranceTime, $nextOccuranceEndTime, $event);
                        $addedCount++;
                    }
                    $currentCount++;
                }
                $totalCount++;

            }
            $nextOccuranceTime->modify('+1 day');
        }
    }

    private function createIndexEntry(CalendarDateTime $nextOccuranceTime, CalendarDateTime $nextOccuranceEndTime, Event $event)
    {
        $indexEntry = new Index();
        $indexEntry->setStartDatetime((float)$nextOccuranceTime->format('YmdHis'));
        $indexEntry->setEndDatetime((float)$nextOccuranceEndTime->format('YmdHis'));
        $indexEntry->setEventUid($event->getUid());
        $indexEntry->setTablename((string)$this->getModelType($event));
        $this->updateEventDataWithDeviations($event, $indexEntry, $nextOccuranceTime, $nextOccuranceEndTime);
        $this->indexRepository->add($indexEntry);
    }

    private function getModelType($event)
    {
        return $event->getModelType() == 'tx_cal_event' ? ($event->isException() ? 'tx_cal_exception_event' : 'tx_cal_event') : $event->getModelType();
    }

    private function getDeviationDates(Event $event)
    {
        $deviationResult = $this->eventDeviationRepository->findByEventUid($event->getuid());
        $deviations = [];
        if ($deviationResult) {
            foreach ($deviationResult as $deviation) {
                $t = new \DateTime();
                $t->setTimestamp((int)$deviation->getOrigStartDate());
                $origStartDate = CalendarDateTime::generateFromDateTime($t);
                $origStartDate->addSeconds((int)$deviation->getOrigStartTime());
                $deviations [(string)$origStartDate->format('YmdHis')] = $deviation;
            }
        }
        return $deviations;
    }

    private function updateEventDataWithDeviations(Event $event, Index $eventData, $nextOccuranceTime, $nextOccuranceEndTime)
    {

        $deviationDates = $this->getDeviationDates($event);
        $startDateTime = sprintf('%.0f', (float)$eventData->getStartDatetime());
        if (array_key_exists($startDateTime, $deviationDates)) {
            $deviation = $deviationDates[$startDateTime];
            if ($deviation->getStartDate() instanceof \DateTime) {
                $startDate = CalendarDateTime::generateFromDateTime($deviation->getStartDate());
            } else {
                $startDate = new CalendarDateTime();
                $startDate->copy($nextOccuranceTime);
            }
            if ($deviation->getEndDate() instanceof \DateTime) {
                $endDate = CalendarDateTime::generateFromDateTime($deviation->getEndDate());
            } else {
                $endDate = new CalendarDateTime();
                $endDate->copy($nextOccuranceEndTime);
            }

            if (! $deviation->isAllday() && $deviation->getStartTime() instanceof \DateTime && $deviation->getEndTime() instanceof \DateTime) {
                $startDate->setTime((int)$deviation->getStartTime()->format('H'), (int)$deviation->getStartTime()->format('i'));
                $endDate->setTime((int)$deviation->getEndTime()->format('H'), (int)$deviation->getEndTime()->format('i'));
            }

            $eventData->setEventDeviationUid((int)$deviation->getUid());
            $eventData->setStartDatetime((float)$startDate->format('YmdHis'));
            $eventData->setEndDatetime((float)$endDate->format('YmdHis'));
        }
    }

    /**
     * Additional dates (RDATE) as written by the backend form:
     * date "20261110", date with time "20261110T140000Z" or period "20261110T140000Z/P1DT2H"
     */
    private function addRecurringInstances(Event $event): void
    {
        $type = (string)$event->getRdateType();
        $duration = $event->getEnd()->getTimestamp() - $event->getStart()->getTimestamp();
        foreach (explode(',', (string)$event->getRdate()) as $value) {
            [$date, $period] = array_pad(explode('/', trim($value), 2), 2, '');
            $start = self::parseRdate($date, $event->getStart(), $type !== 'date');
            if ($start === null) {
                continue;
            }
            $end = new CalendarDateTime();
            $end->copy($start);
            if ($type === 'period') {
                $periodEnd = self::parseRdate($period, $start, true);
                if ($periodEnd !== null) {
                    $end = $periodEnd;
                } else {
                    $period = rtrim($period, 'T');
                    if ($period !== 'P' && $period !== '') {
                        try {
                            $end->add(new \DateInterval($period));
                        } catch (\Exception) {
                            $end->addSeconds($duration);
                        }
                    }
                }
            } else {
                $end->addSeconds($duration);
            }
            if ($end->after($this->starttime) && $start->before($this->endtime)) {
                $this->createIndexEntry($start, $end, $event);
            }
        }
    }

    /**
     * Date (and time) of an RDATE value, the time of $default if the value has no time or $withTime is false
     */
    private static function parseRdate(string $value, CalendarDateTime $default, bool $withTime): ?CalendarDateTime
    {
        if (! preg_match('/^(\d{4})-?(\d{2})-?(\d{2})(?:T(\d{2}):?(\d{2})(?::?(\d{2}))?)?/', trim($value), $match)) {
            return null;
        }
        $date = new CalendarDateTime();
        $date->copy($default);
        $date->setDate((int)$match[1], (int)$match[2], (int)$match[3]);
        if ($withTime) {
            $date->setTime((int)($match[4] ?? 0), (int)($match[5] ?? 0), (int)($match[6] ?? 0));
        }
        return $date;
    }

    /**
     * Days ([month, day]) of a year on which the rule (week, month, year) may occur, in chronological order.
     * The day is limited by addDailyOccurrance() (start of the event, COUNT, UNTIL).
     *
     * @param list<string> $bymonth
     * @return list<array{0: int, 1: int}>
     */
    private function getOccurrenceDaysOfYear(Event $event, int $year, array $bymonth): array
    {
        // RFC 5545: a yearly rule with BYDAY but without BYMONTH and BYMONTHDAY uses the days of the whole year
        // (e.g. BYDAY=20MO: the 20th Monday of the year), see RecurrenceValidator::checkByMonth()
        if ($event->getFreq() == 'year' && trim((string)$event->getBymonth()) === '') {
            return $this->getYearDaysForByDay($event, $year);
        }
        $days = [];
        foreach ($bymonth as $month) {
            // the months are validated by RecurrenceValidator::checkByMonth()
            $month = (int)$month;
            foreach ($this->getMonthDaysForMonth($event, $month, $year) as $day) {
                $days[] = [$month, (int)$day];
            }
        }
        return $days;
    }

    /**
     * Days of a year matching BYDAY: "MO" every Monday, "20MO" the 20th, "-1MO" the last Monday of the year.
     * BYMONTHDAY limits the days.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function getYearDaysForByDay(Event $event, int $year): array
    {
        $dates = [];
        foreach (explode(',', strtoupper((string)$event->getByday())) as $entry) {
            // the weekdays are validated by RecurrenceValidator::checkByDay()
            preg_match('/^([-+]?)(\d{0,2})([A-Z]{2})$/', trim($entry), $match);
            $weekday = (int)self::two2threeCharDays($match[3], false);
            $ordinal = (int)$match[2];
            $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
            $first = $first->modify('+' . (($weekday - (int)$first->format('w') + 7) % 7) . ' days');
            if ($ordinal === 0) {
                for ($date = $first; (int)$date->format('Y') === $year; $date = $date->modify('+7 days')) {
                    $dates[$date->format('md')] = $date;
                }
                continue;
            }
            if ($match[1] === '-') {
                $last = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
                $date = $last->modify('-' . (((int)$last->format('w') - $weekday + 7) % 7 + ($ordinal - 1) * 7) . ' days');
            } else {
                $date = $first->modify('+' . (($ordinal - 1) * 7) . ' days');
            }
            if ((int)$date->format('Y') === $year) {
                $dates[$date->format('md')] = $date;
            }
        }
        ksort($dates);
        $days = [];
        foreach ($dates as $date) {
            $month = (int)$date->format('n');
            $day = (int)$date->format('j');
            if (in_array($day, self::resolveMonthDays((string)$event->getBymonthday(), $month, $year), true)) {
                $days[] = [$month, $day];
            }
        }
        return $days;
    }

    /**
     * BYMONTHDAY for a month: negative values count from the end of the month (-1 = last day),
     * days the month does not have (e.g. 30 in February) are skipped (RFC 5545). Empty: all days.
     *
     * @return list<int>
     */
    public static function resolveMonthDays(string $byMonthDay, int $month, int $year): array
    {
        $daysInMonth = (int)date('t', (int)mktime(0, 0, 0, $month, 1, $year));
        $values = trim($byMonthDay) === '' ? range(1, $daysInMonth) : array_map('intval', explode(',', $byMonthDay));
        $days = [];
        foreach ($values as $value) {
            if ($value < 0) {
                $value = $daysInMonth + 1 + $value;
            }
            if ($value >= 1 && $value <= $daysInMonth) {
                $days[] = $value;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);
        return $days;
    }

    private function getMonthDaysForMonth($event, $month, $year)
    {

        $byDayArray = explode(',', (string)$event->getByday());
        $resultDays = [];

        $byMonthDays = self::resolveMonthDays((string)$event->getBymonthday(), (int)$month, (int)$year);

        $monthStartTime = CalendarDateTime::generateByYmdHis($year . sprintf('%02d', (int)$month) . '01000000');
        $monthEndTime = new CalendarDateTime();
        $monthEndTime->copy($monthStartTime);
        $monthEndTime = CalculateDateTime::calculateEndOfMonth($monthEndTime);
        for ($i = 0; $i < count($byDayArray); $i++) {
            $byDaySplit = [];
            if (preg_match('/([-\+]{0,1})?([0-9]{1,2})?([A-Z]{2})/', (string)$byDayArray [$i], $byDaySplit)) {
                $dayOfWeekday = (int)RecurrenceGenerator::two2threeCharDays($byDaySplit [3], false);

                if ($byDaySplit [2] > 0) {
                    if ($byDaySplit [1] == '-') {
                        $monthTime = CalculateDateTime::prevDayOfWeek($dayOfWeekday, $monthEndTime->getDay(), $monthEndTime->getMonth(), $monthEndTime->getYear(), true);
                        $monthTime->modify('-' . (((int)$byDaySplit [2] - 1) * 7) . ' day');
                    } else {
                        $monthTime = CalculateDateTime::nextDayOfWeek($dayOfWeekday, $monthStartTime->getDay(), $monthStartTime->getMonth(), $monthStartTime->getYear(), true);
                        $monthTime->modify('+' . (((int)$byDaySplit [2] - 1) * 7) . ' day');
                    }
                    if (($monthTime->getMonth() == $month) && in_array((int)($monthTime->getDay()), $byMonthDays)) {
                        $resultDays [] = $monthTime->getDay();
                    }
                } else {
                    $monthTime = CalculateDateTime::prevDayOfWeek($dayOfWeekday, $monthStartTime->getDay(), $monthStartTime->getMonth(), $monthStartTime->getYear(), true);
                    if ($monthTime->before($monthStartTime)) {
                        $monthTime->modify('+7 day');
                    }
                    while ($monthTime->before($monthEndTime)) {
                        $resultDays [] = $monthTime->getDay();
                        $monthTime->modify('+7 day');
                    }
                }
            }
        }
        $resultDays = array_intersect($resultDays, $byMonthDays);

        sort($resultDays);

        return $resultDays;
    }

    /**
     * Takes iCalendar 2 day format and makes it into 3 characters
     * if $txt is true, it returns the 3 letters, otherwise it returns the
     * integer of that day; 0=Sun, 1=Mon, etc.
     *
     * @param string $day
     * @param bool $txt
     * @return string
     */
    public static function two2threeCharDays($day, $txt = true): string
    {

        switch ($day) {
            case 'SU':
                return $txt ? 'sun' : '0';
            case 'MO':
                return $txt ? 'mon' : '1';
            case 'TU':
                return $txt ? 'tue' : '2';
            case 'WE':
                return $txt ? 'wed' : '3';
            case 'TH':
                return $txt ? 'thu' : '4';
            case 'FR':
                return $txt ? 'fri' : '5';
            case 'SA':
                return $txt ? 'sat' : '6';
        }
        return '';
    }

}
