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
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Stores the removed dates (EXDATE) and changed occurrences (RECURRENCE-ID) of an event written by a
 * CalDAV client as exception events and deviations of cal, then rebuilds the recurrence index.
 *
 * - A new EXDATE becomes a single exception event of the event.
 * - A removed EXDATE removes the single exception event of that day. Recurring exception events and
 *   exception groups are not changed (they would remove the date again).
 * - Every RECURRENCE-ID is a deviation (identified by the original start), deviations without
 *   RECURRENCE-ID are deleted.
 */
class CalDavRecurrenceWriter
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const DEVIATION_TABLE = 'tx_cal_domain_model_eventdeviation';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExceptionEventRepository $exceptionEventRepository,
        private readonly RecurrenceGenerator $recurrenceGenerator,
    ) {}

    /**
     * @param array{exdates: list<string>, overrides: list<array<string, int|string>>} $details see IcsParser::parseRecurrenceDetails()
     */
    public function write(int $eventUid, array $details): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $event = $queryBuilder->select('uid', 'pid', 'title', 'description', 'location', 'start_date')
            ->from(self::EVENT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($event === false) {
            return;
        }
        $this->writeExceptions($event, $details['exdates']);
        $this->writeDeviations($event, $details['overrides']);
        $this->recurrenceGenerator->setRange()->generateIndexForEventUid($eventUid);
    }

    /**
     * @param array<string, mixed> $event
     * @param list<string> $exdates days (Ymd)
     */
    private function writeExceptions(array $event, array $exdates): void
    {
        $eventUid = (int)$event['uid'];
        $model = new Event();
        $model->_setProperty('uid', $eventUid);
        // the days of the exception events from the start of the event until the last removed date
        $starttime = $this->recurrenceGenerator->starttime;
        $endtime = $this->recurrenceGenerator->endtime;
        $first = date('Y-m-d', (int)$event['start_date']);
        $last = $exdates !== [] ? max(end($exdates), $endtime->format('Ymd')) : $endtime->format('Ymd');
        $this->recurrenceGenerator->setRange($first . ' 00:00:00', substr($last, 0, 4) . '-' . substr($last, 4, 2) . '-' . substr($last, 6, 2) . ' 23:59:59');
        try {
            $existing = $this->recurrenceGenerator->getExceptionDays($model);
        } finally {
            $this->recurrenceGenerator->starttime = $starttime;
            $this->recurrenceGenerator->endtime = $endtime;
        }

        $wanted = array_flip($exdates);
        foreach ($exdates as $day) {
            if (!isset($existing[$day])) {
                $timestamp = (int)\DateTimeImmutable::createFromFormat('!Ymd', $day)->getTimestamp();
                $this->exceptionEventRepository->addSingleException($eventUid, (int)$event['pid'], $timestamp, (string)$event['title']);
            }
        }
        foreach ($this->exceptionEventRepository->findByEvent($eventUid) as $exception) {
            $day = date('Ymd', (int)$exception['start_date']);
            $isSingle = in_array((string)$exception['freq'], ['', 'none'], true)
                && trim((string)$exception['rdate']) === ''
                && ((int)$exception['end_date'] === 0 || date('Ymd', (int)$exception['end_date']) === $day);
            if ($isSingle && !isset($wanted[$day])) {
                $this->exceptionEventRepository->removeSingleException($eventUid, (int)$exception['uid']);
            }
        }
    }

    /**
     * @param array<string, mixed> $event
     * @param list<array<string, int|string>> $overrides
     */
    private function writeDeviations(array $event, array $overrides): void
    {
        $eventUid = (int)$event['uid'];
        $connection = $this->connectionPool->getConnectionForTable(self::DEVIATION_TABLE);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $existing = [];
        foreach ($queryBuilder->select('uid', 'orig_start_date', 'orig_start_time')
            ->from(self::DEVIATION_TABLE)
            ->where(
                $queryBuilder->expr()->eq('parentid', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAllAssociative() as $row) {
            $existing[date('Ymd', (int)$row['orig_start_date']) . '-' . (int)$row['orig_start_time']] = (int)$row['uid'];
        }

        $now = time();
        foreach ($overrides as $override) {
            $key = date('Ymd', (int)$override['orig_start_date']) . '-' . (int)$override['orig_start_time'];
            $values = $override;
            // texts of the event are not repeated in the deviation
            foreach (['title', 'description', 'location'] as $field) {
                if ((string)($values[$field] ?? '') === (string)$event[$field]) {
                    $values[$field] = '';
                }
            }
            $values['tstamp'] = $now;
            if (isset($existing[$key])) {
                $connection->update(self::DEVIATION_TABLE, $values, ['uid' => $existing[$key]]);
                unset($existing[$key]);
            } else {
                $connection->insert(self::DEVIATION_TABLE, $values + ['pid' => (int)$event['pid'], 'parentid' => $eventUid, 'crdate' => $now]);
            }
        }
        foreach ($existing as $uid) {
            $connection->update(self::DEVIATION_TABLE, ['deleted' => 1, 'tstamp' => $now], ['uid' => $uid]);
        }
        $count = $connection->count('uid', self::DEVIATION_TABLE, ['parentid' => $eventUid, 'deleted' => 0]);
        $this->connectionPool->getConnectionForTable(self::EVENT_TABLE)->update(self::EVENT_TABLE, ['deviation' => $count], ['uid' => $eventUid]);
    }
}
