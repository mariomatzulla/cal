<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class EventRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    /**
     * @var \TYPO3\CMS\Core\Http\Dispatcher
     */
    protected $dispatcher;

    public function __construct(
        private readonly DataMapper $dataMapper,
    ) {
        parent::__construct();
    }

    public function initializeObject()
    {
        parent::initializeObject();
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\Event';
        $this->tableName = 'tx_cal_domain_model_event';
    }

    /**
     * @param array<int> $storagePageIds only events stored in these pages (empty: no limitation)
     */
    public function findAllForEvent(SearchEventsEvent $event, array $calendarLimitationIds, array $recurringEventIds, array $storagePageIds = [])
    {
        $query = $this->createQuery();
        $conditions = [ ];

        if ($event->getStart() != null && $event->getEnd() != null) {
            $conditionStartIsWithin = $query->logicalAnd($query->greaterThanOrEqual('start_date', $event->getStart()), $query->lessThanOrEqual('start_date', $event->getEnd()));
            $conditionEndIsWithin = $query->logicalAnd($query->lessThanOrEqual('end_date', $event->getEnd()), $query->greaterThanOrEqual('end_date', $event->getStart()));
            $conditionStartIsBeforeAndEndIsAfter = $query->logicalAnd($query->greaterThanOrEqual('end_date', $event->getEnd()), $query->lessThanOrEqual('start_date', $event->getStart()));
            if (count($recurringEventIds) > 0) {
                $conditionRecurringUid = $query->in('uid', $recurringEventIds);
                $conditions [] = $query->logicalAnd($query->logicalOr($conditionStartIsWithin, $conditionEndIsWithin, $conditionStartIsBeforeAndEndIsAfter, $conditionRecurringUid));
            } else {
                $conditions [] = $query->logicalAnd($query->logicalOr($conditionStartIsWithin, $conditionEndIsWithin, $conditionStartIsBeforeAndEndIsAfter));
            }
        }

        if (! empty($calendarLimitationIds)) {
            $conditions [] = $query->logicalAnd($query->in('calendar_id', array_unique($calendarLimitationIds)));
        }
        if ($storagePageIds !== []) {
            $conditions [] = $query->in('pid', $storagePageIds);
        }
        $query->matching($query->logicalAnd(...$conditions));
        return $query->execute(false);

    }

    /**
     * @param array<int> $storagePageIds only events stored in these pages (empty: no limitation)
     */
    public function findEvent(int $uid, array $calendarLimitationIds, array $storagePageIds = [])
    {
        $query = $this->createQuery();
        $conditions = [ ];
        $conditions [] = $query->logicalAnd($query->equals('uid', $uid));

        if (! empty($calendarLimitationIds)) {
            $conditions [] = $query->logicalAnd($query->in('calendar_id', array_unique($calendarLimitationIds)));
        }
        if ($storagePageIds !== []) {
            $conditions [] = $query->in('pid', $storagePageIds);
        }
        return $query->matching($query->logicalAnd(...$conditions))->execute(false);
    }

    public function findUidsForEventsWithPage(int $pageId)
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];
        $conditions [] = $queryBuilder->expr()->eq('pid', $pageId);
        $result = $queryBuilder->select('uid')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();
        $list = [];
        foreach ($result as $uidArray) {
            $list[] = $uidArray['uid'];
        }
        return $list;
    }

    /**
     * @return int[]
     */
    public function findUidsForCalendar(int $calendarId): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $rows = $queryBuilder->select('uid')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq('calendar_id', $queryBuilder->createNamedParameter($calendarId, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map('intval', $rows);
    }

    public function findAllForCalendar(int $calendarId)
    {
        $query = $this->createQuery();
        $conditions = [ ];
        $conditions [] = $query->logicalAnd($query->equals('calendar_id', $calendarId));
        return $query->matching($query->logicalAnd(...$conditions))->execute(false);
    }

    public function findAllForCalendarNative(int $calendarId)
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];

        $conditions [] = $queryBuilder->expr()->eq('deleted', 0);
        $conditions [] = $queryBuilder->expr()->eq('calendar_id', $calendarId);
        return $queryBuilder->select('*')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();
    }

    public function countWithPage(int $pid): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];
        $conditions [] = $queryBuilder->expr()->and($queryBuilder->expr()->eq('pid', $pid));
        return (int)$queryBuilder->count('uid')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchOne();
    }

    public function countAll(): int
    {
        $query = $this->createQuery();
        return $query->execute()->count();
    }

    public function findRecurringEvents(int $page = 0)
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];

        $conditions [] = $queryBuilder->expr()->eq('deleted', 0);
        $conditions [] = $queryBuilder->expr()->or(
            $queryBuilder->expr()->in(
                'freq',
                $queryBuilder->createNamedParameter(['day', 'week', 'month', 'year'], Connection::PARAM_STR_ARRAY),
            ),
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('rdate', '1'),
                $queryBuilder->expr()->in(
                    'rdate_type',
                    $queryBuilder->createNamedParameter(['date_time', 'date', 'period'], Connection::PARAM_STR_ARRAY),
                ),
            ),
        );
        $rows = $queryBuilder->select('*')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();

        return $this->dataMapper->map(Event::class, $rows);
    }

    public function findPidsOfRecurringEvents()
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];

        $conditions [] = $queryBuilder->expr()->eq('deleted', 0);
        $conditions [] = $queryBuilder->expr()->or(
            $queryBuilder->expr()->in(
                'freq',
                $queryBuilder->createNamedParameter(['day', 'week', 'month', 'year'], Connection::PARAM_STR_ARRAY),
            ),
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('rdate', '1'),
                $queryBuilder->expr()->in(
                    'rdate_type',
                    $queryBuilder->createNamedParameter(['date_time', 'date', 'period'], Connection::PARAM_STR_ARRAY),
                ),
            ),
        );
        return $queryBuilder->select('pid')->distinct()->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();
    }

    public function check()
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $conditions = [];
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.type', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.nearby', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->in('tx_cal_domain_model_event.type', $queryBuilder->createNamedParameter([0, 1, 2, 3], Connection::PARAM_INT_ARRAY));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.deleted', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_event.deleted', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.hidden', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_event.hidden', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->or(
            $queryBuilder->expr()->eq('tx_cal_domain_model_event.caldav_data', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->isNull('tx_cal_domain_model_event.caldav_data'),
            $queryBuilder->expr()->eq('tx_cal_domain_model_event.ics_uid', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->isNull('tx_cal_domain_model_event.ics_uid'),
        );

        $joinCondition = $queryBuilder->expr()->eq('tx_cal_domain_model_event.calendar_id', $queryBuilder->quoteIdentifier('tx_cal_domain_model_calendar.uid'));
        $query = $queryBuilder->select('tx_cal_domain_model_event.*')
        ->from($this->tableName)
        ->join('tx_cal_domain_model_event', 'tx_cal_domain_model_calendar', 'tx_cal_domain_model_calendar', $joinCondition)
        ->where($queryBuilder->expr()->and(...$conditions));
        $rows = $query->executeQuery()->fetchAllAssociative();

        return $this->dataMapper->map(Event::class, $rows);
    }

    public function countEventsWithoutIcs()
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $conditions = [];
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.type', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.nearby', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->in('tx_cal_domain_model_event.type', $queryBuilder->createNamedParameter([0, 1, 2, 3], Connection::PARAM_INT_ARRAY));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.deleted', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_event.deleted', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_calendar.hidden', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->eq('tx_cal_domain_model_event.hidden', $queryBuilder->createNamedParameter(0));
        $conditions[] = $queryBuilder->expr()->or(
            $queryBuilder->expr()->eq('tx_cal_domain_model_event.caldav_data', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->isNull('tx_cal_domain_model_event.caldav_data'),
            $queryBuilder->expr()->eq('tx_cal_domain_model_event.ics_uid', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->isNull('tx_cal_domain_model_event.ics_uid'),
        );

        $joinCondition = $queryBuilder->expr()->eq('tx_cal_domain_model_event.calendar_id', $queryBuilder->quoteIdentifier('tx_cal_domain_model_calendar.uid'));
        $query = $queryBuilder->select('count(*)')
        ->from($this->tableName)
        ->join('tx_cal_domain_model_event', 'tx_cal_domain_model_calendar', 'tx_cal_domain_model_calendar', $joinCondition)
        ->where($queryBuilder->expr()->and(...$conditions));
        return $query->executeQuery()->fetchAssociative();
    }

    public function updateWithCaldavData(int $uid, string $caldavData)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
        ->update($this->tableName)
        ->set('caldav_data', $caldavData)
        ->where(
            $queryBuilder->expr()->eq('uid', $uid),
        )
            ->executeStatement();
    }

    public function updateWithIcsUid(int $uid, string $caldavUid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
        ->update($this->tableName)
        ->set('ics_uid', $caldavUid)
        ->where(
            $queryBuilder->expr()->eq('uid', $uid),
        )
            ->executeStatement();
    }

    /**
     * @param bool $includeDeleted also find soft deleted events (deleted = 1), e.g. to restore them
     */
    public function findAllForCalendarAndUri(int $calendarId, string $caldavUid, bool $includeDeleted = false)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $conditions = [ ];

        if (!$includeDeleted) {
            $conditions [] = $queryBuilder->expr()->eq('deleted', 0);
        }
        $conditions [] = $queryBuilder->expr()->eq('calendar_id', $calendarId);
        $conditions [] = $queryBuilder->expr()->eq('ics_uid', $queryBuilder->createNamedParameter($caldavUid));
        return $queryBuilder->select('*')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAssociative();
    }

    public function persistEvent(array $values)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
        ->insert('tx_cal_domain_model_event')
        ->values($values)
        ->executeStatement();
    }

    public function updateEvent(int $calendarId, string $caldavUid, array $values)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->update('tx_cal_domain_model_event');

        // Jedes Element aus dem Array per Schleife setzen
        foreach ($values as $field => $value) {
            $queryBuilder->set($field, $value);
        }

        $conditions = [ ];

        $conditions [] = $queryBuilder->expr()->eq('ics_uid', $queryBuilder->createNamedParameter($caldavUid));
        $conditions [] = $queryBuilder->expr()->eq('calendar_id', $queryBuilder->createNamedParameter($calendarId));
        $queryBuilder->where($queryBuilder->expr()->and(...$conditions));

        $queryBuilder->executeStatement();
    }

    /**
     * Removes all imported (isTemp = 1) events of a calendar from the database.
     *
     * @return int[] uids of the removed events
     */
    public function removeTempEventsOfCalendar(int $calendarId): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $condition = $queryBuilder->expr()->and(
            $queryBuilder->expr()->eq('calendar_id', $queryBuilder->createNamedParameter($calendarId, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('isTemp', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
        );
        $uids = array_map('intval', $queryBuilder->select('uid')->from($this->tableName)->where($condition)->executeQuery()->fetchFirstColumn());
        if ($uids !== []) {
            $queryBuilder = $this->getQueryBuilder();
            $queryBuilder->delete($this->tableName)->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
            )->executeStatement();
        }
        return $uids;
    }

    /**
     * Inserts imported events in one transaction.
     *
     * @param list<array<string, int|string>> $rows
     */
    public function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->tableName);
        $connection->transactional(function () use ($connection, $rows): void {
            foreach ($rows as $row) {
                $connection->insert($this->tableName, $row);
            }
        });
    }

    public function isTempEvent(int $uid): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        return (bool)$queryBuilder->select('isTemp')->from($this->tableName)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
    }
}
