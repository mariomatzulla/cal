<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Upgrades\Legacy;

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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Knowledge about the database of cal 1.x (TYPO3 <= 11) and helpers for the upgrade wizards.
 *
 * The tables of cal 1.x are not defined anymore. Depending on whether "Analyze Database Structure"
 * already removed them, they exist with their old name or with the prefix "zzz_deleted_".
 *
 * @internal
 */
final class LegacySchema
{
    public const DELETED_PREFIX = 'zzz_deleted_';

    /**
     * Record tables: cal 1.x => cal 4.x
     */
    public const RECORD_TABLES = [
        'tx_cal_calendar' => 'tx_cal_domain_model_calendar',
        'tx_cal_organizer' => 'tx_cal_domain_model_organizer',
        'tx_cal_location' => 'tx_cal_domain_model_location',
        'tx_cal_event' => 'tx_cal_domain_model_event',
        'tx_cal_event_deviation' => 'tx_cal_domain_model_eventdeviation',
        'tx_cal_exception_event' => 'tx_cal_domain_model_exception_event',
        'tx_cal_exception_event_group' => 'tx_cal_domain_model_exception_event_group',
    ];

    /**
     * MM tables which got a new name. The MM table tx_cal_calendar_user_group_mm keeps its name.
     * Attendees, subscriptions (monitoring), shared users and the free/busy view of cal 1.x are not migrated.
     */
    public const MM_TABLES = [
        'tx_cal_exception_event_mm' => 'tx_cal_domain_model_exception_event_mm',
        'tx_cal_exception_event_group_mm' => 'tx_cal_domain_model_exception_event_group_mm',
    ];

    /**
     * Columns which got a new name: new column => old columns (the first non empty value wins)
     */
    public const COLUMN_RENAMES = [
        '*' => [
            'l10n_parent' => ['l18n_parent'],
            'l10n_diffsource' => ['l18n_diffsource'],
        ],
        'tx_cal_domain_model_event' => [
            // EXT:caldav stored the UID and the ICS data of an event in own columns
            'ics_uid' => ['tx_caldav_uid', 'icsUid'],
            'caldav_data' => ['tx_caldav_data'],
        ],
    ];

    /**
     * Columns which are never copied (TYPO3 removed them or they are rebuilt)
     */
    public const SKIPPED_COLUMNS = ['cruser_id', 't3ver_id', 't3ver_count', 't3ver_tstamp', 't3ver_move_id', 't3_origuid'];

    /**
     * @var array<string, array<string, string>> table => [lower case column name => column name]
     */
    private array $columns = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Table names (old and new) as they are referenced in sys_file_reference, MM tables, be_groups ...
     *
     * @return array<string, string>
     */
    public static function getTableNameMap(): array
    {
        return self::RECORD_TABLES + self::MM_TABLES + ['tx_cal_category' => 'sys_category'];
    }

    /**
     * The existing name of a table of cal 1.x (with or without "zzz_deleted_"), null if it does not exist.
     */
    public function findLegacyTable(string $table): ?string
    {
        foreach ([$table, self::DELETED_PREFIX . $table] as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * The existing name of a column (with or without "zzz_deleted_"), null if it does not exist.
     */
    public function findColumn(string $table, string $column): ?string
    {
        $columns = $this->getColumns($table);
        foreach ([$column, self::DELETED_PREFIX . $column] as $candidate) {
            if (isset($columns[strtolower($candidate)])) {
                return $columns[strtolower($candidate)];
            }
        }
        return null;
    }

    public function tableExists(string $table): bool
    {
        return $this->getConnection($table)->createSchemaManager()->tablesExist([$table]);
    }

    /**
     * @return array<string, string> lower case column name => column name
     */
    public function getColumns(string $table): array
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = [];
            if ($this->tableExists($table)) {
                foreach ($this->getConnection($table)->createSchemaManager()->listTableColumns($table) as $column) {
                    $this->columns[$table][strtolower($column->getName())] = $column->getName();
                }
            }
        }
        return $this->columns[$table];
    }

    /**
     * Default values of the NOT NULL columns of a table, used for NULL values of the old table.
     *
     * @return array<string, mixed> column name => default value
     */
    public function getNotNullDefaults(string $table): array
    {
        $defaults = [];
        foreach ($this->getConnection($table)->createSchemaManager()->listTableColumns($table) as $column) {
            if (!$column->getNotnull()) {
                continue;
            }
            $default = $column->getDefault();
            if ($default === null) {
                $typeName = strtolower((new \ReflectionClass($column->getType()))->getShortName());
                $default = preg_match('/int|float|decimal|bool|double|smallint|bigint/', $typeName) ? 0 : '';
            }
            $defaults[$column->getName()] = $default;
        }
        return $defaults;
    }

    /**
     * Number of rows matching the given column values (without restrictions)
     *
     * @param array<string, int|string> $identifiers
     */
    public function count(string $table, array $identifiers = []): int
    {
        $queryBuilder = $this->createQueryBuilder($table);
        $queryBuilder->count('*')->from($table);
        foreach ($identifiers as $column => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($value)));
        }
        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * @param list<string> $columns
     * @param array<string, int|string> $identifiers
     * @return array<string, mixed>|null
     */
    public function fetchRow(string $table, array $columns, array $identifiers): ?array
    {
        $queryBuilder = $this->createQueryBuilder($table);
        $queryBuilder->select(...$columns)->from($table)->setMaxResults(1);
        foreach ($identifiers as $column => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($value)));
        }
        $row = $queryBuilder->executeQuery()->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * @return list<string> names of the integer / float columns of a table
     */
    public function getNumericColumns(string $table): array
    {
        $columns = [];
        foreach ($this->getConnection($table)->createSchemaManager()->listTableColumns($table) as $column) {
            $typeName = strtolower((new \ReflectionClass($column->getType()))->getShortName());
            if (preg_match('/int|float|decimal|double|smallint|bigint/', $typeName)) {
                $columns[] = $column->getName();
            }
        }
        return $columns;
    }

    public function countRows(string $table): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('*')->from($table)->executeQuery()->fetchOne();
    }

    /**
     * Query builder without restrictions: deleted / hidden records are migrated as well, and the
     * old tables have no TCA anymore.
     */
    public function createQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }

    public function getConnection(string $table): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }

    public function resetColumnCache(): void
    {
        $this->columns = [];
    }
}
