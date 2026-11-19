<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Upgrades;

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
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The translation fields of the cal tables were renamed from "l18n_parent" / "l18n_diffsource"
 * to the TYPO3 standard "l10n_parent" / "l10n_diffsource". This wizard copies the values of the
 * old columns (also if the database compare renamed them to "zzz_deleted_...") into the new ones.
 */
#[UpgradeWizard('cal_l10nParent')]
final readonly class L10nParentUpgradeWizard implements UpgradeWizardInterface
{
    private const TABLES = [
        'tx_cal_domain_model_calendar',
        'tx_cal_domain_model_event',
        'tx_cal_domain_model_eventdeviation',
        'tx_cal_domain_model_location',
        'tx_cal_domain_model_organizer',
    ];

    /**
     * new column => old column
     */
    private const FIELDS = [
        'l10n_parent' => 'l18n_parent',
        'l10n_diffsource' => 'l18n_diffsource',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Calendar Base (cal): migrate l18n_parent to l10n_parent';
    }

    public function getDescription(): string
    {
        return 'Copies the translation parent and diff source of calendars, events, event deviations, locations '
            . 'and organizers from the old columns "l18n_parent" / "l18n_diffsource" to "l10n_parent" / "l10n_diffsource". '
            . 'Afterwards the old columns can be removed with the database analyzer.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        foreach (self::TABLES as $table) {
            $oldColumn = $this->findOldColumn($table, self::FIELDS['l10n_parent']);
            if ($oldColumn !== null && $this->hasColumn($table, 'l10n_parent') && $this->countRecordsToMigrate($table, $oldColumn) > 0) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        foreach (self::TABLES as $table) {
            foreach (self::FIELDS as $newColumn => $oldColumnName) {
                $oldColumn = $this->findOldColumn($table, $oldColumnName);
                if ($oldColumn === null || !$this->hasColumn($table, $newColumn)) {
                    continue;
                }
                $connection = $this->connectionPool->getConnectionForTable($table);
                $quotedNew = $connection->quoteIdentifier($newColumn);
                $quotedOld = $connection->quoteIdentifier($oldColumn);
                $quotedTable = $connection->quoteIdentifier($table);
                if ($newColumn === 'l10n_parent') {
                    $connection->executeStatement(
                        'UPDATE ' . $quotedTable . ' SET ' . $quotedNew . ' = ' . $quotedOld
                        . ' WHERE ' . $quotedNew . ' = 0 AND ' . $quotedOld . ' > 0'
                    );
                } else {
                    $connection->executeStatement(
                        'UPDATE ' . $quotedTable . ' SET ' . $quotedNew . ' = ' . $quotedOld
                        . ' WHERE (' . $quotedNew . ' IS NULL OR ' . $quotedNew . ' = ?) AND ' . $quotedOld . ' IS NOT NULL AND ' . $quotedOld . ' <> ?',
                        ['', ''],
                        [Connection::PARAM_STR, Connection::PARAM_STR],
                    );
                }
            }
        }
        return true;
    }

    private function countRecordsToMigrate(string $table, string $oldColumn): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', 0),
                $queryBuilder->expr()->gt($oldColumn, 0),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * The old column, or the one renamed by the database analyzer ("zzz_deleted_l18n_parent").
     */
    private function findOldColumn(string $table, string $column): ?string
    {
        foreach ([$column, 'zzz_deleted_' . $column] as $candidate) {
            if ($this->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }
        foreach ($schemaManager->listTableColumns($table) as $tableColumn) {
            if (strtolower($tableColumn->getName()) === strtolower($column)) {
                return true;
            }
        }
        return false;
    }
}
