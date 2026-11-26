<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Owners (frontend users and groups) of the calendars, field "owner" of tx_cal_domain_model_calendar
 */
class CalendarOwnerRelationRepository
{
    private const TABLE = 'tx_cal_calendar_user_group_mm';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Uids of the calendars owned by the frontend user or one of the groups
     *
     * @param list<int> $groupUids
     * @return list<int>
     */
    public function findCalendarListForFeUser(int $feUserUid, array $groupUids = []): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $owners = [
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('fe_users')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($feUserUid, Connection::PARAM_INT)),
            ),
        ];
        if ($groupUids !== []) {
            $owners[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('fe_groups')),
                $queryBuilder->expr()->in('uid_foreign', $queryBuilder->createNamedParameter(array_map('intval', $groupUids), Connection::PARAM_INT_ARRAY)),
            );
        }
        $uids = $queryBuilder->select('uid_local')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->or(...$owners))
            ->executeQuery()
            ->fetchFirstColumn();
        return array_values(array_unique(array_map('intval', $uids)));
    }
}
