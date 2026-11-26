<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Exception events (days on which a recurring event does not take place): field "exception_cnt" of the
 * event, single exception events and exception groups (field "exception_event_cnt" of the group).
 */
class ExceptionEventRepository
{
    public const TABLE = 'tx_cal_domain_model_exception_event';
    public const GROUP_TABLE = 'tx_cal_domain_model_exception_event_group';
    public const MM_TABLE = 'tx_cal_domain_model_exception_event_mm';
    public const GROUP_MM_TABLE = 'tx_cal_domain_model_exception_event_group_mm';
    private const EVENT_TABLE = 'tx_cal_domain_model_event';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Visible exception events of an event, linked directly or by a visible group
     *
     * @return list<array<string, mixed>>
     */
    public function findByEvent(int $eventUid): array
    {
        $links = $this->findLinks($eventUid);
        $uids = $links[self::TABLE];
        $groupUids = $this->filterVisible(self::GROUP_TABLE, $links[self::GROUP_TABLE]);
        if ($groupUids !== []) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::GROUP_MM_TABLE);
            $queryBuilder->getRestrictions()->removeAll();
            $uids = [...$uids, ...array_map('intval', $queryBuilder->select('uid_foreign')
                ->from(self::GROUP_MM_TABLE)
                ->where($queryBuilder->expr()->in('uid_local', $queryBuilder->createNamedParameter($groupUids, Connection::PARAM_INT_ARRAY)))
                ->executeQuery()
                ->fetchFirstColumn())];
        }
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        return $queryBuilder->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(array_values(array_unique($uids)), Connection::PARAM_INT_ARRAY)))
            ->orderBy('start_date')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Events using an exception event (directly or by a group) or an exception group
     *
     * @return list<int>
     */
    public function findEventUids(string $table, int $uid): array
    {
        $groupUids = [];
        if ($table === self::GROUP_TABLE) {
            $groupUids = [$uid];
        } elseif ($table === self::TABLE) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::GROUP_MM_TABLE);
            $queryBuilder->getRestrictions()->removeAll();
            $groupUids = array_map('intval', $queryBuilder->select('uid_local')
                ->from(self::GROUP_MM_TABLE)
                ->where($queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchFirstColumn());
        } else {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $constraints = [];
        if ($table === self::TABLE) {
            $constraints[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );
        }
        if ($groupUids !== []) {
            $constraints[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::GROUP_TABLE)),
                $queryBuilder->expr()->in('uid_foreign', $queryBuilder->createNamedParameter($groupUids, Connection::PARAM_INT_ARRAY)),
            );
        }
        $uids = array_map('intval', $queryBuilder->select('uid_local')
            ->from(self::MM_TABLE)
            ->where($queryBuilder->expr()->or(...$constraints))
            ->executeQuery()
            ->fetchFirstColumn());
        $uids = array_values(array_unique($uids));
        sort($uids);
        return $uids;
    }

    /**
     * Creates a single (all-day) exception event for the event, e.g. for an EXDATE of a CalDAV client
     */
    public function addSingleException(int $eventUid, int $pid, int $dayTimestamp, string $title): int
    {
        $now = time();
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'title' => $title,
            'start_date' => $dayTimestamp,
            'end_date' => $dayTimestamp,
        ]);
        $uid = (int)$connection->lastInsertId();
        $links = $this->findLinks($eventUid);
        $this->connectionPool->getConnectionForTable(self::MM_TABLE)->insert(self::MM_TABLE, [
            'uid_local' => $eventUid,
            'uid_foreign' => $uid,
            'tablenames' => self::TABLE,
            'sorting' => count($links[self::TABLE]) + count($links[self::GROUP_TABLE]) + 1,
        ]);
        $this->updateCounter($eventUid);
        return $uid;
    }

    /**
     * Removes a single exception event of the event. Exception events used by other events or groups
     * are only unlinked from this event, otherwise they are deleted.
     */
    public function removeSingleException(int $eventUid, int $exceptionUid): void
    {
        $mm = $this->connectionPool->getConnectionForTable(self::MM_TABLE);
        $mm->delete(self::MM_TABLE, ['uid_local' => $eventUid, 'uid_foreign' => $exceptionUid, 'tablenames' => self::TABLE]);
        $this->updateCounter($eventUid);
        $usedByGroups = $this->connectionPool->getConnectionForTable(self::GROUP_MM_TABLE)
            ->count('uid_local', self::GROUP_MM_TABLE, ['uid_foreign' => $exceptionUid]);
        $usedByEvents = $mm->count('uid_local', self::MM_TABLE, ['uid_foreign' => $exceptionUid, 'tablenames' => self::TABLE]);
        if ($usedByGroups === 0 && $usedByEvents === 0) {
            $this->connectionPool->getConnectionForTable(self::TABLE)
                ->update(self::TABLE, ['deleted' => 1, 'tstamp' => time()], ['uid' => $exceptionUid]);
        }
    }

    /**
     * Linked exception events and groups (uids) of an event
     *
     * @return array{tx_cal_domain_model_exception_event: list<int>, tx_cal_domain_model_exception_event_group: list<int>}
     */
    private function findLinks(int $eventUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('uid_foreign', 'tablenames')
            ->from(self::MM_TABLE)
            ->where($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)))
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
        $links = [self::TABLE => [], self::GROUP_TABLE => []];
        foreach ($rows as $row) {
            // records of cal 1.x may have no table name: single exception events
            $table = (string)$row['tablenames'] === self::GROUP_TABLE ? self::GROUP_TABLE : self::TABLE;
            $links[$table][] = (int)$row['uid_foreign'];
        }
        return $links;
    }

    /**
     * @param list<int> $uids
     * @return list<int>
     */
    private function filterVisible(string $table, array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        return array_map('intval', $queryBuilder->select('uid')
            ->from($table)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchFirstColumn());
    }

    private function updateCounter(int $eventUid): void
    {
        $count = $this->connectionPool->getConnectionForTable(self::MM_TABLE)->count('uid_local', self::MM_TABLE, ['uid_local' => $eventUid]);
        $this->connectionPool->getConnectionForTable(self::EVENT_TABLE)->update(self::EVENT_TABLE, ['exception_cnt' => $count], ['uid' => $eventUid]);
    }
}
