<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CalendarOwnerRelationRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function initializeObject()
    {
        parent::initializeObject();
        $this->connection = $this->getConnectionForTable($this->table);
    }

    protected Connection $connection;

    protected array $settings;

    private string $table = 'tx_cal_calendar_user_group_mm';

    private function getConnectionForTable(string $table): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    public function findCalendarListForFeUser(int $feUserUid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
        ->select('uid_local')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($feUserUid)),
                $queryBuilder->expr()->eq('tablenames', "'fe_users'"),
            ),
        );
        $rows = $query->executeQuery()->fetchAssociative();
        return is_array($rows) ? $rows : null;
    }

    public function setSettings($settings = [])
    {
        $this->settings = $settings;
    }
}
