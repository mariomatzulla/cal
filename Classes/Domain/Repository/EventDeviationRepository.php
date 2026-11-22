<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class EventDeviationRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function __construct(
        private readonly DataMapper $dataMapper,
    ) {
        parent::__construct();
    }

    public function initializeObject()
    {
        parent::initializeObject();
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\EventDeviation';
        $this->tableName = 'tx_cal_domain_model_eventdeviation';
    }

    public function findByEventUid(int $uid)
    {

        $queryBuilder = $this->getQueryBuilder();

        $conditions [] = $queryBuilder->expr()->eq('parentid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT));

        $rows = $queryBuilder->select('*')->from($this->tableName)->where($queryBuilder->expr()->and(...$conditions))->executeQuery()->fetchAllAssociative();

        return $this->dataMapper->map(EventDeviation::class, $rows);
    }

    private function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
    }
}
