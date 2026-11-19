<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use MarioMatzulla\Cal\Domain\Model\Index;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class IndexRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function __construct(
        private readonly DataMapper $dataMapper,
    ) {
        parent::__construct();
    }

    public function initializeObject()
    {
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\Index';
        $this->tableName = 'tx_cal_domain_model_index';
    }

    public function deleteAllByEventUid(array $uids)
    {
        // The index stores the model type (tx_cal_event), not the table name
        $this->deleteByEventUidsAndTablename($uids, 'tx_cal_event');
    }

    public function deleteByEventUidsAndTablename(array $uids, string $tablename)
    {
        if (empty($uids)) {
            return;
        }
        $queryBuilder = $this->getQueryBuilder();

        $conditions [] = $queryBuilder->expr()->in('event_uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_STR_ARRAY));
        $conditions [] = $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($tablename, Connection::PARAM_STR));

        $queryBuilder->delete($this->tableName)->where(
            $queryBuilder->expr()->and(...$conditions),
        )->executeStatement();
    }

    public function findEventUids(\DateTime $start, \DateTime $end)
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];
        $conditionStartIsWithin = $queryBuilder->expr()->and($queryBuilder->expr()->gte('start_datetime', $start->format('YmdHis')), $queryBuilder->expr()->lte('start_datetime', $end->format('YmdHis')));
        $conditionEndIsWithin = $queryBuilder->expr()->and($queryBuilder->expr()->lte('end_datetime', $end->format('YmdHis')), $queryBuilder->expr()->gte('end_datetime', $start->format('YmdHis')));
        $conditionStartIsBeforeAndEndIsAfter = $queryBuilder->expr()->and($queryBuilder->expr()->gte('end_datetime', $end->format('YmdHis')), $queryBuilder->expr()->lte('start_datetime', $start->format('YmdHis')));
        $conditions [] = $queryBuilder->expr()->and($queryBuilder->expr()->or($conditionStartIsWithin, $conditionEndIsWithin, $conditionStartIsBeforeAndEndIsAfter));
        $result = $queryBuilder->select('event_uid')->distinct()->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();
        $list = [];
        foreach ($result as $uidArray) {
            $list[] = $uidArray['event_uid'];
        }
        return $list;
    }

    public function findByEventUidStartAndEnd(int $uid, \DateTime $start, \DateTime $end)
    {
        $queryBuilder = $this->getQueryBuilder();
        $conditions = [ ];
        $conditionStartIsWithin = $queryBuilder->expr()->and($queryBuilder->expr()->gte('start_datetime', $start->format('YmdHis')), $queryBuilder->expr()->lte('start_datetime', $end->format('YmdHis')));
        $conditionEndIsWithin = $queryBuilder->expr()->and($queryBuilder->expr()->lte('end_datetime', $end->format('YmdHis')), $queryBuilder->expr()->gte('end_datetime', $start->format('YmdHis')));
        $conditionStartIsBeforeAndEndIsAfter = $queryBuilder->expr()->and($queryBuilder->expr()->gte('end_datetime', $end->format('YmdHis')), $queryBuilder->expr()->lte('start_datetime', $start->format('YmdHis')));
        $conditions [] = $queryBuilder->expr()->or($conditionStartIsWithin, $conditionEndIsWithin, $conditionStartIsBeforeAndEndIsAfter);
        $conditions [] = $queryBuilder->expr()->eq('event_uid', $uid);
        $rows = $queryBuilder->select('*')->distinct()->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();

        return $this->dataMapper->map(Index::class, $rows);
    }

    private function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
    }
}
