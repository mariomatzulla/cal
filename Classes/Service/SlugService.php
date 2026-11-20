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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Fills the URL segment (field "slug") of events, locations and organizers.
 *
 * The backend form (DataHandler) creates the slug itself. Records written directly to the
 * database (import of external calendars, CalDAV, upgrade from cal 1.x) get it here.
 * The slugs are used by the route enhancer in Configuration/Routes/Cal.yaml.
 */
final class SlugService
{
    public const SLUG_FIELD = 'slug';

    /**
     * Tables with a slug field
     */
    public const TABLES = [
        'tx_cal_domain_model_event',
        'tx_cal_domain_model_location',
        'tx_cal_domain_model_organizer',
    ];

    /**
     * @var array<string, bool>
     */
    private array $hasSlugField = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Number of records (not deleted) without slug
     */
    public function countMissing(string $table): int
    {
        if (!$this->hasSlugField($table)) {
            return 0;
        }
        $queryBuilder = $this->createQueryBuilder($table);
        return (int)$queryBuilder->count('uid')
            ->from($table)
            ->where(...$this->getMissingConditions($queryBuilder))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Creates the missing slugs of a table, optionally only for the events of one calendar.
     *
     * @return int number of updated records
     */
    public function fillMissing(string $table, ?int $calendarId = null): int
    {
        if (!$this->hasSlugField($table)) {
            return 0;
        }
        $fieldConfiguration = (array)$GLOBALS['TCA'][$table]['columns'][self::SLUG_FIELD]['config'];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, $table, self::SLUG_FIELD, $fieldConfiguration);
        $evaluations = GeneralUtility::trimExplode(',', (string)($fieldConfiguration['eval'] ?? ''), true);

        $queryBuilder = $this->createQueryBuilder($table);
        $conditions = $this->getMissingConditions($queryBuilder);
        if ($calendarId !== null) {
            $conditions[] = $queryBuilder->expr()->eq('calendar_id', $queryBuilder->createNamedParameter($calendarId, Connection::PARAM_INT));
        }
        $rows = $queryBuilder->select('*')->from($table)->where(...$conditions)->orderBy('uid')->executeQuery()->fetchAllAssociative();

        $connection = $this->connectionPool->getConnectionForTable($table);
        $updated = 0;
        foreach ($rows as $row) {
            $pid = (int)$row['pid'];
            $slug = $slugHelper->generate($row, $pid);
            if ($slug === '') {
                continue;
            }
            $state = RecordStateFactory::forName($table)->fromArray($row, $pid, (int)$row['uid']);
            try {
                if (in_array('uniqueInSite', $evaluations, true)) {
                    $slug = $slugHelper->buildSlugForUniqueInSite($slug, $state);
                } elseif (in_array('uniqueInPid', $evaluations, true)) {
                    $slug = $slugHelper->buildSlugForUniqueInPid($slug, $state);
                } elseif (in_array('unique', $evaluations, true)) {
                    $slug = $slugHelper->buildSlugForUniqueInTable($slug, $state);
                }
            } catch (\Throwable) {
                // e.g. the record is stored outside of a site
                $slug = $slugHelper->buildSlugForUniqueInTable($slug, $state);
            }
            $connection->update($table, [self::SLUG_FIELD => $slug], ['uid' => (int)$row['uid']]);
            $updated++;
        }
        return $updated;
    }

    public function hasSlugField(string $table): bool
    {
        return $this->hasSlugField[$table] ??= isset($GLOBALS['TCA'][$table]['columns'][self::SLUG_FIELD]['config'])
            && $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->introspectTable($table)->hasColumn(self::SLUG_FIELD);
    }

    /**
     * @return list<string>
     */
    private function getMissingConditions(\TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder): array
    {
        return [
            $queryBuilder->expr()->eq('deleted', 0),
            (string)$queryBuilder->expr()->or(
                $queryBuilder->expr()->eq(self::SLUG_FIELD, $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->isNull(self::SLUG_FIELD),
            ),
        ];
    }

    private function createQueryBuilder(string $table): \TYPO3\CMS\Core\Database\Query\QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        // hidden and timed records get a slug as well
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }
}
