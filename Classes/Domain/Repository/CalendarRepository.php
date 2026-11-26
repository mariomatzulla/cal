<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CalendarRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function initializeObject()
    {
        parent::initializeObject();
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\Calendar';
        $this->tableName = 'tx_cal_domain_model_calendar';
    }

    public function findPublicCalendarList()
    {
        $query = $this->createQuery();
        $conditions = [ ];
        $conditions [] = $query->logicalAnd($query->equals('owner', 0));
        return $query->matching($query->logicalAnd(...$conditions))->execute(false);
    }

    public function findByUids(string $uids)
    {
        $uidList = array_values(array_filter(array_map('intval', explode(',', $uids)), static fn(int $uid): bool => $uid > 0));
        if ($uidList === []) {
            return [];
        }
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidList, \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function touch(int $calendarId)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
        ->update($this->tableName)
        ->set('tstamp', time())
        ->where(
            $queryBuilder->expr()->eq('uid', $calendarId),
        )
            ->executeStatement();
    }

    public function updateMd5(int $calendarId, string $md5): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->tableName)
            ->set('md5', $md5)
            ->set('tstamp', time())
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($calendarId, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeStatement();
    }

    public function findRawByUid(int $calendarId): ?array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from($this->tableName)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($calendarId, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row ?: null;
    }

    private function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
    }

}
