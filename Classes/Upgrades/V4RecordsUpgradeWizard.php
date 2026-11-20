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
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Upgrades\Legacy\IcsFileMigration;
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * cal 1.x => 4.0: copies the records of the old tables (tx_cal_event, tx_cal_calendar, ...) into the
 * new tables (tx_cal_domain_model_*). The uids are kept, so all relations (calendar of an event, FlexForm
 * calendar selection, fe_users.tx_cal_calendar, MM tables, ...) stay valid. Afterwards the file references
 * and category relations are moved to the new table names and the recurrence index is rebuilt.
 *
 * The old tables are not changed, they can be removed with "Analyze Database Structure" afterwards.
 */
#[UpgradeWizard('cal_v4Records')]
final class V4RecordsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const BATCH_SIZE = 500;

    /**
     * Values of the column "tablenames" in MM tables
     */
    private const MM_TABLENAMES = [
        'tx_cal_exception_event' => 'tx_cal_domain_model_exception_event',
        'tx_cal_exception_event_group' => 'tx_cal_domain_model_exception_event_group',
        'tx_cal_unknown_users' => 'tx_cal_domain_model_unknown_users',
    ];

    /**
     * Tables with FAL fields (image, attachment)
     */
    private const FILE_REFERENCE_TABLES = ['tx_cal_event', 'tx_cal_event_deviation', 'tx_cal_location', 'tx_cal_organizer'];

    /**
     * cal 1.x stored these dates as integer "Ymd" (20260625), cal 4 as timestamp of midnight (server time zone,
     * like TCA type "datetime" with format "date"). Values which are already timestamps are kept.
     */
    private const DATE_COLUMNS = [
        'tx_cal_domain_model_event' => ['start_date', 'end_date', 'until'],
        'tx_cal_domain_model_eventdeviation' => ['orig_start_date', 'start_date', 'end_date'],
        'tx_cal_domain_model_exception_event' => ['start_date', 'end_date', 'until'],
    ];

    /**
     * FAL fields: the column contains the number of file references
     */
    private const FILE_COLUMNS = ['image', 'attachment'];

    private ?OutputInterface $output = null;

    /**
     * @var array<string, list<string>> table => "uid: file name" of files of cal < 1.6 (before FAL)
     */
    private array $legacyFiles = [];

    /**
     * @var array<string, int> numeric columns of the current target table (MySQL strict mode rejects '' there)
     */
    private array $numericColumns = [];

    public function __construct(
        private readonly LegacySchema $legacySchema,
        private readonly RecurrenceGenerator $recurrenceGenerator,
        private readonly IcsFileMigration $icsFileMigration,
        private readonly ExternalCalendarImporter $importer,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal) 1.x => 4.0: migrate calendars, events, locations and organizers';
    }

    public function getDescription(): string
    {
        return 'Copies the records of the tables of cal 1.x (tx_cal_event, tx_cal_calendar, ...) with their uids into the new '
            . 'tables tx_cal_domain_model_*, including the MM relations, the data of EXT:caldav (tx_caldav_uid, tx_caldav_data), '
            . 'file references and sys_category relations. The recurrence index is rebuilt afterwards. '
            . 'The old tables are not changed and can be removed with "Analyze Database Structure" when everything is fine.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        foreach (LegacySchema::RECORD_TABLES as $oldTable => $newTable) {
            $source = $this->legacySchema->findLegacyTable($oldTable);
            if ($source !== null && $this->legacySchema->tableExists($newTable) && $this->findMissingUids($source, $newTable) !== []) {
                return true;
            }
        }
        foreach (LegacySchema::MM_TABLES as $oldTable => $newTable) {
            $source = $this->legacySchema->findLegacyTable($oldTable);
            if ($source !== null && $this->legacySchema->tableExists($newTable) && $this->findMissingMmRows($source, $newTable) !== []) {
                return true;
            }
        }
        foreach ($this->icsFileMigration->findPending() as $item) {
            if ($item['exists']) {
                return true;
            }
        }
        return $this->countLegacyReferences() > 0;
    }

    public function executeUpdate(): bool
    {
        // Check all tables first: a partial migration would leave relations pointing to wrong records
        $conflicts = false;
        foreach ($this->getTablePairs() as $source => $target) {
            $uids = $this->findConflictingUids($source, $target);
            if ($uids !== []) {
                $conflicts = true;
                $this->write(sprintf(
                    'ERROR %s: the uids %s exist already in %s with other records (created after the update). '
                    . 'Move these records to other uids or delete them, then run the wizard again.',
                    $source,
                    implode(', ', array_slice($uids, 0, 20)) . (count($uids) > 20 ? ', ...' : ''),
                    $target,
                ));
            }
        }
        if ($conflicts) {
            $this->write('Nothing was migrated.');
            return false;
        }

        $migratedEvents = false;
        foreach ($this->getTablePairs() as $source => $newTable) {
            $result = $this->migrateRecordTable($source, $newTable);
            $this->write(sprintf('%s => %s: %d records copied', $source, $newTable, $result));
            $migratedEvents = $migratedEvents || ($newTable === 'tx_cal_domain_model_event' && $result > 0);
        }
        foreach (LegacySchema::MM_TABLES as $oldTable => $newTable) {
            $source = $this->legacySchema->findLegacyTable($oldTable);
            if ($source === null || !$this->legacySchema->tableExists($newTable)) {
                continue;
            }
            $rows = $this->findMissingMmRows($source, $newTable);
            $this->insertRows($newTable, $rows);
            $this->write(sprintf('%s => %s: %d relations copied', $source, $newTable, count($rows)));
        }
        $this->renameTablenames();
        $this->migrateFileReferences();
        $this->updateFileCounters();
        $this->reportLegacyFiles();
        $this->migrateCategoryRelations();
        $this->updateCategoryCounters();
        foreach ($this->icsFileMigration->migrate() as $message) {
            $this->write($message);
        }
        $this->importCalendars($this->markImportedEvents());

        if ($migratedEvents) {
            $this->rebuildIndex();
        }
        return true;
    }

    /**
     * cal 1.x stored the events of external (URL) and ICS file calendars as normal events. cal 4 imports them
     * as read-only events (isTemp = 1) and replaces them with every import. The migrated events of these
     * calendars are therefore flagged, if the calendar still has a source (URL or file), otherwise they stay
     * normal events.
     *
     * @return list<int> calendars with flagged events
     */
    private function markImportedEvents(): array
    {
        $oldEvents = $this->legacySchema->findLegacyTable('tx_cal_event');
        $events = 'tx_cal_domain_model_event';
        $calendars = ExternalCalendarImporter::CALENDAR_TABLE;
        if ($oldEvents === null || !$this->legacySchema->tableExists($events) || !$this->legacySchema->tableExists($calendars)) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder($calendars);
        $rows = $queryBuilder->select('*')
            ->from($calendars)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->in('type', [ExternalCalendarImporter::CALENDAR_TYPE_EXTERNAL, ExternalCalendarImporter::CALENDAR_TYPE_FILE]),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $calendarUids = [];
        foreach ($rows as $calendar) {
            $hasSource = (int)$calendar['type'] === ExternalCalendarImporter::CALENDAR_TYPE_EXTERNAL
                ? trim((string)$calendar['ext_url']) !== ''
                : $this->legacySchema->count('sys_file_reference', ['tablenames' => $calendars, 'fieldname' => 'ics_file', 'uid_foreign' => (int)$calendar['uid'], 'deleted' => 0]) > 0;
            if ($hasSource) {
                $calendarUids[] = (int)$calendar['uid'];
            } else {
                $this->write(sprintf('WARNING calendar %d "%s": no URL / ICS file, its events stay normal (editable) events.', (int)$calendar['uid'], (string)$calendar['title']));
            }
        }
        if ($calendarUids === []) {
            return [];
        }
        $oldUids = array_flip($this->fetchUids($oldEvents));
        $queryBuilder = $this->legacySchema->createQueryBuilder($events);
        $candidates = $queryBuilder->select('uid')
            ->from($events)
            ->where(
                $queryBuilder->expr()->in('calendar_id', $queryBuilder->createNamedParameter($calendarUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('isTemp', 0),
            )
            ->executeQuery()
            ->fetchFirstColumn();
        $uids = array_values(array_filter(array_map('intval', $candidates), static fn(int $uid): bool => isset($oldUids[$uid])));
        foreach (array_chunk($uids, self::BATCH_SIZE) as $chunk) {
            $queryBuilder = $this->legacySchema->createQueryBuilder($events);
            $queryBuilder->update($events)
                ->set('isTemp', 1)
                ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($chunk, Connection::PARAM_INT_ARRAY)))
                ->executeStatement();
        }
        if ($uids !== []) {
            $this->write(sprintf('%d events of external / ICS file calendars are now imported (read-only) events', count($uids)));
        }
        return $calendarUids;
    }

    /**
     * Runs the import once, so the events of cal 1.x are replaced by the imported ones. If the import fails,
     * the migrated events are kept.
     *
     * @param list<int> $calendarUids
     */
    private function importCalendars(array $calendarUids): void
    {
        foreach ($calendarUids as $calendarUid) {
            try {
                $result = $this->importer->importCalendar($calendarUid, true);
                $this->write(sprintf('Calendar %d imported: %d events', $calendarUid, $result['count']));
            } catch (\Throwable $e) {
                $this->write(sprintf('WARNING calendar %d could not be imported (%s). The migrated events are kept until the next successful import.', $calendarUid, $e->getMessage()));
            }
        }
    }

    /**
     * @return array<string, string> existing old table => new table
     */
    private function getTablePairs(): array
    {
        $pairs = [];
        foreach (LegacySchema::RECORD_TABLES as $oldTable => $newTable) {
            $source = $this->legacySchema->findLegacyTable($oldTable);
            if ($source !== null && $this->legacySchema->tableExists($newTable)) {
                $pairs[$source] = $newTable;
            }
        }
        return $pairs;
    }

    /**
     * @return int number of copied records
     */
    private function migrateRecordTable(string $source, string $target): int
    {
        $missingUids = $this->findMissingUids($source, $target);
        if ($missingUids === []) {
            return 0;
        }
        $mapping = $this->getColumnMapping($source, $target);
        $defaults = $this->legacySchema->getNotNullDefaults($target);
        $this->numericColumns = array_flip($this->legacySchema->getNumericColumns($target));
        $count = 0;
        foreach (array_chunk($missingUids, self::BATCH_SIZE) as $uids) {
            $queryBuilder = $this->legacySchema->createQueryBuilder($source);
            $rows = $queryBuilder->select('*')
                ->from($source)
                ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
                ->executeQuery()
                ->fetchAllAssociative();
            $newRows = [];
            foreach ($rows as $row) {
                $newRows[] = $this->mapRow($row, $mapping, $defaults, $target);
            }
            $this->insertRows($target, $newRows);
            $count += count($newRows);
        }
        $this->resetSequence($target);
        return $count;
    }

    /**
     * PostgreSQL: records were inserted with explicit uids, the sequence has to continue after them.
     */
    private function resetSequence(string $table): void
    {
        $connection = $this->legacySchema->getConnection($table);
        if (!$connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            return;
        }
        $connection->executeQuery(sprintf(
            'SELECT setval(pg_get_serial_sequence(%s, %s), (SELECT COALESCE(MAX(uid), 1) FROM %s))',
            $connection->quote($table),
            $connection->quote('uid'),
            $connection->quoteIdentifier($table),
        ));
    }

    /**
     * @return array<string, list<string>> target column => source columns (first non empty value wins)
     */
    private function getColumnMapping(string $source, string $target): array
    {
        $sourceColumns = $this->legacySchema->getColumns($source);
        $renames = (LegacySchema::COLUMN_RENAMES[$target] ?? []) + LegacySchema::COLUMN_RENAMES['*'];
        $mapping = [];
        foreach ($this->legacySchema->getColumns($target) as $lowerName => $targetColumn) {
            if (in_array($lowerName, LegacySchema::SKIPPED_COLUMNS, true)) {
                continue;
            }
            $candidates = [];
            foreach ($renames[$targetColumn] ?? [] as $oldColumn) {
                if (isset($sourceColumns[strtolower($oldColumn)])) {
                    $candidates[] = $sourceColumns[strtolower($oldColumn)];
                }
            }
            if (isset($sourceColumns[$lowerName])) {
                $candidates[] = $sourceColumns[$lowerName];
            }
            if ($candidates !== []) {
                $mapping[$targetColumn] = array_values(array_unique($candidates));
            }
        }
        return $mapping;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, list<string>> $mapping
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $mapping, array $defaults, string $target = ''): array
    {
        $newRow = [];
        foreach ($mapping as $targetColumn => $sourceColumns) {
            $value = null;
            foreach ($sourceColumns as $sourceColumn) {
                $candidate = $row[$sourceColumn] ?? null;
                if ($candidate !== null && $candidate !== '') {
                    $value = $candidate;
                    break;
                }
                $value ??= $candidate;
            }
            if ($value === null && array_key_exists($targetColumn, $defaults)) {
                $value = $defaults[$targetColumn];
            }
            if ($targetColumn === 'tablenames' && is_string($value)) {
                $value = self::MM_TABLENAMES[$value] ?? $value;
            }
            if ($targetColumn === 'ics_uid' && is_string($value)) {
                // EXT:caldav stored the object URI ("<uid>.ics"), cal 4 stores the UID only
                $value = preg_replace('/\.ics$/i', '', $value);
            }
            if (in_array($targetColumn, self::DATE_COLUMNS[$target] ?? [], true)) {
                $value = self::ymdToTimestamp($value);
            }
            if ($targetColumn === 'ics_file' && !is_numeric($value)) {
                // file names of cal 1.x, migrated to file references by IcsFileMigration
                $value = '0';
            }
            if (in_array($targetColumn, self::FILE_COLUMNS, true) && !is_numeric($value)) {
                if (is_string($value) && trim($value) !== '') {
                    $this->legacyFiles[$target][] = ($row['uid'] ?? '?') . ': ' . trim($value);
                }
                $value = 0;
            }
            if (isset($this->numericColumns[$targetColumn]) && ($value === '' || (is_string($value) && !is_numeric($value)))) {
                $value = (int)$value;
            }
            $newRow[$targetColumn] = $value;
        }
        return $newRow;
    }

    /**
     * 20260625 => timestamp of 2026-06-25 00:00 in the server time zone. 0 and timestamps are kept.
     */
    public static function ymdToTimestamp(mixed $value): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }
        $number = (int)$value;
        if ($number < 10000101 || $number > 99991231) {
            return $value;
        }
        $year = intdiv($number, 10000);
        $month = intdiv($number % 10000, 100);
        $day = $number % 100;
        if (!checkdate($month, $day, $year)) {
            return $value;
        }
        return (new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day)))->getTimestamp();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertRows(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $connection = $this->legacySchema->getConnection($table);
        $columns = array_keys($rows[0]);
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            $connection->bulkInsert(
                $table,
                array_map(static fn(array $row): array => array_values(array_replace(array_fill_keys($columns, null), $row)), $chunk),
                $columns,
            );
        }
    }

    /**
     * @return list<int>
     */
    private function findMissingUids(string $source, string $target): array
    {
        $existing = array_flip($this->fetchUids($target));
        return array_values(array_filter($this->fetchUids($source), static fn(int $uid): bool => !isset($existing[$uid])));
    }

    /**
     * Records with the same uid in both tables are already migrated if their crdate is the same.
     *
     * @return list<int>
     */
    private function findConflictingUids(string $source, string $target): array
    {
        if ($this->legacySchema->findColumn($source, 'crdate') === null || $this->legacySchema->findColumn($target, 'crdate') === null) {
            return [];
        }
        $sourceCrdates = $this->fetchCrdates($source);
        $conflicts = [];
        foreach ($this->fetchCrdates($target) as $uid => $crdate) {
            if (isset($sourceCrdates[$uid]) && $sourceCrdates[$uid] !== $crdate) {
                $conflicts[] = $uid;
            }
        }
        return $conflicts;
    }

    /**
     * @return list<int>
     */
    private function fetchUids(string $table): array
    {
        $queryBuilder = $this->legacySchema->createQueryBuilder($table);
        return array_map('intval', $queryBuilder->select('uid')->from($table)->executeQuery()->fetchFirstColumn());
    }

    /**
     * @return array<int, int>
     */
    private function fetchCrdates(string $table): array
    {
        $queryBuilder = $this->legacySchema->createQueryBuilder($table);
        $result = [];
        foreach ($queryBuilder->select('uid', 'crdate')->from($table)->executeQuery()->fetchAllAssociative() as $row) {
            $result[(int)$row['uid']] = (int)$row['crdate'];
        }
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findMissingMmRows(string $source, string $target): array
    {
        $targetColumns = $this->legacySchema->getColumns($target);
        $mapping = $this->getColumnMapping($source, $target);
        $defaults = $this->legacySchema->getNotNullDefaults($target);
        $this->numericColumns = array_flip($this->legacySchema->getNumericColumns($target));
        $key = static fn(array $row): string => ($row['uid_local'] ?? '') . '|' . ($row['uid_foreign'] ?? '') . '|' . ($row['tablenames'] ?? '');

        $existing = [];
        $queryBuilder = $this->legacySchema->createQueryBuilder($target);
        $keyColumns = array_values(array_intersect(['uid_local', 'uid_foreign', 'tablenames'], array_keys($targetColumns)));
        foreach ($queryBuilder->select(...$keyColumns)->from($target)->executeQuery()->fetchAllAssociative() as $row) {
            $existing[$key($row)] = true;
        }
        $missing = [];
        $queryBuilder = $this->legacySchema->createQueryBuilder($source);
        foreach ($queryBuilder->select('*')->from($source)->executeQuery()->fetchAllAssociative() as $row) {
            $newRow = $this->mapRow($row, $mapping, $defaults, $target);
            if (!isset($existing[$key($newRow)])) {
                $existing[$key($newRow)] = true;
                $missing[] = $newRow;
            }
        }
        return $missing;
    }

    /**
     * Relations in the MM tables which kept their name (fe_users / fe_groups need no change) and the
     * subscriptions to unknown users.
     */
    private function renameTablenames(): void
    {
        foreach (['tx_cal_domain_model_fe_user_event_monitor_mm', 'tx_cal_event_shared_user_mm'] as $table) {
            if (!$this->legacySchema->tableExists($table) || $this->legacySchema->findColumn($table, 'tablenames') === null) {
                continue;
            }
            foreach (self::MM_TABLENAMES as $old => $new) {
                $this->legacySchema->getConnection($table)->update($table, ['tablenames' => $new], ['tablenames' => $old]);
            }
        }
    }

    private function migrateFileReferences(): void
    {
        $connection = $this->legacySchema->getConnection('sys_file_reference');
        $count = 0;
        foreach (self::FILE_REFERENCE_TABLES as $oldTable) {
            $count += $connection->update('sys_file_reference', ['tablenames' => LegacySchema::RECORD_TABLES[$oldTable]], ['tablenames' => $oldTable]);
        }
        if ($count > 0) {
            $this->write(sprintf('sys_file_reference: %d file references moved to the new tables', $count));
        }
    }

    /**
     * The FAL fields store the number of file references
     */
    private function updateFileCounters(): void
    {
        foreach (self::FILE_REFERENCE_TABLES as $oldTable) {
            $table = LegacySchema::RECORD_TABLES[$oldTable];
            if (!$this->legacySchema->tableExists($table)) {
                continue;
            }
            $connection = $this->legacySchema->getConnection($table);
            foreach (self::FILE_COLUMNS as $field) {
                if ($this->legacySchema->findColumn($table, $field) !== $field) {
                    continue;
                }
                $counts = [];
                $queryBuilder = $this->legacySchema->createQueryBuilder('sys_file_reference');
                $rows = $queryBuilder->select('uid_foreign')
                    ->addSelectLiteral('COUNT(*) AS ' . $queryBuilder->quoteIdentifier('cnt'))
                    ->from('sys_file_reference')
                    ->where(
                        $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($table)),
                        $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($field)),
                        $queryBuilder->expr()->eq('deleted', 0),
                    )
                    ->groupBy('uid_foreign')
                    ->executeQuery()
                    ->fetchAllAssociative();
                foreach ($rows as $row) {
                    $counts[(int)$row['uid_foreign']] = (int)$row['cnt'];
                }
                // records without file references
                $queryBuilder = $this->legacySchema->createQueryBuilder($table);
                $queryBuilder->update($table)->set($field, 0)->where($queryBuilder->expr()->neq($field, $queryBuilder->createNamedParameter(0)));
                if ($counts !== []) {
                    $queryBuilder->andWhere($queryBuilder->expr()->notIn('uid', $queryBuilder->createNamedParameter(array_keys($counts), Connection::PARAM_INT_ARRAY)));
                }
                $queryBuilder->executeStatement();
                foreach ($counts as $uid => $count) {
                    $connection->update($table, [$field => $count], ['uid' => $uid]);
                }
            }
        }
    }

    private function reportLegacyFiles(): void
    {
        foreach ($this->legacyFiles as $table => $files) {
            $this->write(sprintf(
                'WARNING %s: %d records reference files of cal < 1.6 (uploads/tx_cal/, no file references). '
                . 'Please add these files again: %s',
                $table,
                count($files),
                implode(', ', array_slice($files, 0, 20)) . (count($files) > 20 ? ', ...' : ''),
            ));
        }
    }

    /**
     * cal 1.x with categoryService = sys_category stored the categories of an event in
     * sys_category_record_mm with tablenames "tx_cal_event" and fieldname "category_id".
     */
    private function migrateCategoryRelations(): void
    {
        $count = $this->legacySchema->getConnection('sys_category_record_mm')->update(
            'sys_category_record_mm',
            ['tablenames' => 'tx_cal_domain_model_event', 'fieldname' => 'categories'],
            ['tablenames' => 'tx_cal_event'],
        );
        if ($count > 0) {
            $this->write(sprintf('sys_category_record_mm: %d category relations moved to the new event table', $count));
        }
    }

    /**
     * The field "categories" stores the number of categories of an event.
     */
    private function updateCategoryCounters(): void
    {
        $table = 'tx_cal_domain_model_event';
        if ($this->legacySchema->findColumn($table, 'categories') === null) {
            return;
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('sys_category_record_mm');
        $rows = $queryBuilder->select('uid_foreign')
            ->addSelectLiteral('COUNT(*) AS ' . $queryBuilder->quoteIdentifier('cnt'))
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories')),
            )
            ->groupBy('uid_foreign')
            ->executeQuery()
            ->fetchAllAssociative();
        $connection = $this->legacySchema->getConnection($table);
        foreach ($rows as $row) {
            $connection->update($table, ['categories' => (int)$row['cnt']], ['uid' => (int)$row['uid_foreign']]);
        }
    }

    private function countLegacyReferences(): int
    {
        $count = 0;
        foreach (self::FILE_REFERENCE_TABLES as $oldTable) {
            $count += $this->legacySchema->count('sys_file_reference', ['tablenames' => $oldTable]);
        }
        $count += $this->legacySchema->count('sys_category_record_mm', ['tablenames' => 'tx_cal_event']);
        return $count;
    }

    private function rebuildIndex(): void
    {
        try {
            $titles = $this->recurrenceGenerator->setRange()->generateIndex();
            $this->write(sprintf('Recurrence index rebuilt for %d recurring events', count($titles)));
        } catch (\Throwable $e) {
            $this->write('The recurrence index could not be rebuilt (' . $e->getMessage() . '). Please use the backend module "Cal Indexer".');
        }
    }

    private function write(string $message): void
    {
        $this->output?->writeln($message);
    }
}
