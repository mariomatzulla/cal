<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace MarioMatzulla\Cal\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal this is a concrete TYPO3 implementation and solely used for EXT:felogin and not part of TYPO3's Core API.
 */
class FrontendUserRepository
{
    protected readonly Connection $connection;

    protected array $settings;

    public function __construct(
    ) {
        $this->connection = $this->getConnectionForTable('fe_users');
    }

    private function getConnectionForTable(string $table): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    public function findByUid($uid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
        ->select('*')
        ->from('fe_users')
        ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
        ->setMaxResults(1);
        $row = $query->executeQuery()->fetchAssociative();
        return is_array($row) ? $row : null;
    }

    public function findByName(string $name)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
        ->select('*')
        ->from('fe_users')
        ->where($queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($name)))
        ->setMaxResults(1);
        $row = $query->executeQuery()->fetchAssociative();
        return is_array($row) ? $row : null;
    }

    public function findByCalendarId(int $calendarId)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
        ->select('*')
        ->from('fe_users')
        ->where($queryBuilder->expr()->inSet('tx_cal_calendar', $queryBuilder->createNamedParameter($calendarId)))
        ->setMaxResults(1);
        $row = $query->executeQuery()->fetchAssociative();
        return is_array($row) ? $row : null;
    }

    public function setSettings($settings = [])
    {
        $this->settings = $settings;
    }

}
