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
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Task\ImportExternalCalendarTask;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Scheduler\Execution;

/**
 * Keeps one scheduler task per external calendar in sync with the calendar record:
 * - external calendar (URL) or ICS file calendar with "Refresh Interval" > 0: task is created or updated (interval = refresh minutes)
 * - other calendar type, interval 0 or deleted calendar: task is removed
 * The uid of the task is stored in tx_cal_domain_model_calendar.schedulerId.
 *
 * The task record is written directly (not via DataHandler), because tx_scheduler_task is
 * "adminOnly" and editors must still be able to save their calendars.
 */
#[Autoconfigure(public: true)]
final class CalendarSchedulerService
{
    public const STATUS_NONE = 'none';
    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_REMOVED = 'removed';
    public const STATUS_SCHEMA_MISSING = 'schema_missing';

    private const TASK_TABLE = 'tx_scheduler_task';
    private const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';

    // Note: Connection::insert()/update() convert the values according to the column types,
    // the JSON columns "execution_details" and "parameters" therefore get PHP arrays.

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CalendarRepository $calendarRepository,
        private readonly ExternalCalendarImporter $importer,
    ) {}

    public function isAvailable(): bool
    {
        return ExtensionManagementUtility::isLoaded('scheduler')
            && isset($GLOBALS['TCA'][self::TASK_TABLE]['columns'][ImportExternalCalendarTask::FIELD]);
    }

    /**
     * @return array{status: string, taskUid: int, interval: int}
     */
    public function synchronize(int $calendarUid): array
    {
        if (!$this->isAvailable()) {
            return ['status' => self::STATUS_NONE, 'taskUid' => 0, 'interval' => 0];
        }
        if (!$this->isSchemaUpToDate()) {
            return ['status' => self::STATUS_SCHEMA_MISSING, 'taskUid' => 0, 'interval' => 0];
        }

        $calendar = $this->calendarRepository->findRawByUid($calendarUid);
        $taskUid = $this->findTaskUid($calendarUid, (int)($calendar['schedulerId'] ?? 0));
        $intervalMinutes = max(0, (int)($calendar['refresh'] ?? 0));
        $needsTask = $calendar !== null
            && $intervalMinutes > 0
            && ExternalCalendarImporter::isImportedType((int)$calendar['type'])
            && $this->importer->hasImportSource($calendar);

        if (!$needsTask) {
            if ($taskUid > 0) {
                $this->removeTask($taskUid);
                $this->storeTaskUid($calendarUid, 0);
                return ['status' => self::STATUS_REMOVED, 'taskUid' => $taskUid, 'interval' => 0];
            }
            if ((int)($calendar['schedulerId'] ?? 0) !== 0) {
                $this->storeTaskUid($calendarUid, 0);
            }
            return ['status' => self::STATUS_NONE, 'taskUid' => 0, 'interval' => 0];
        }

        $interval = $intervalMinutes * 60;
        $description = 'cal: ' . $calendar['title'] . ' [' . $calendarUid . ']';
        $hidden = !empty($calendar['hidden']);

        if ($taskUid > 0) {
            $existing = $this->getTaskRow($taskUid);
            $existingExecution = json_decode((string)($existing['execution_details'] ?? ''), true);
            $existingExecution = is_array($existingExecution) ? $existingExecution : [];
            $fields = [
                'description' => $description,
                'disable' => (int)$hidden,
                'parameters' => [],
                ImportExternalCalendarTask::FIELD => $calendarUid,
            ];
            if ((int)($existingExecution['interval'] ?? 0) !== $interval || !empty($existingExecution['cronCmd'])) {
                // Interval changed: restart the series now
                $execution = Execution::createRecurringExecution(time(), $interval);
                $fields['execution_details'] = $execution->toArray();
                $fields['nextexecution'] = time() + $interval;
            }
            $this->getConnection()->update(self::TASK_TABLE, $fields, ['uid' => $taskUid]);
            $this->storeTaskUid($calendarUid, $taskUid);
            return ['status' => self::STATUS_UPDATED, 'taskUid' => $taskUid, 'interval' => $intervalMinutes];
        }

        // The import just ran on save, so the first scheduled run is one interval later
        $execution = Execution::createRecurringExecution(time(), $interval);
        $connection = $this->getConnection();
        $connection->insert(self::TASK_TABLE, [
            'pid' => 0,
            'crdate' => time(),
            'tasktype' => ImportExternalCalendarTask::class,
            'description' => $description,
            'disable' => (int)$hidden,
            'nextexecution' => time() + $interval,
            'execution_details' => $execution->toArray(),
            'parameters' => [],
            ImportExternalCalendarTask::FIELD => $calendarUid,
        ]);
        $taskUid = (int)$connection->lastInsertId();
        $this->storeTaskUid($calendarUid, $taskUid);
        return ['status' => self::STATUS_CREATED, 'taskUid' => $taskUid, 'interval' => $intervalMinutes];
    }

    /**
     * Removes the task of a calendar (e.g. when the calendar is deleted).
     */
    public function removeForCalendar(int $calendarUid): void
    {
        if (!$this->isAvailable() || !$this->isSchemaUpToDate()) {
            return;
        }
        $calendar = $this->calendarRepository->findRawByUid($calendarUid);
        $taskUid = $this->findTaskUid($calendarUid, (int)($calendar['schedulerId'] ?? 0));
        if ($taskUid > 0) {
            $this->removeTask($taskUid);
        }
        $this->storeTaskUid($calendarUid, 0);
    }

    /**
     * The stored schedulerId is only trusted if it points to an existing import task of this calendar,
     * otherwise (e.g. an old value from cal 1.x, or the task was deleted in the scheduler module)
     * an existing task is looked up by the calendar uid.
     */
    private function findTaskUid(int $calendarUid, int $storedTaskUid): int
    {
        if ($storedTaskUid > 0) {
            $row = $this->getTaskRow($storedTaskUid);
            if ($row !== null
                && $row['tasktype'] === ImportExternalCalendarTask::class
                && (int)$row[ImportExternalCalendarTask::FIELD] === $calendarUid
            ) {
                return $storedTaskUid;
            }
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TASK_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('uid')
            ->from(self::TASK_TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('tasktype', $queryBuilder->createNamedParameter(ImportExternalCalendarTask::class)),
                $queryBuilder->expr()->eq(ImportExternalCalendarTask::FIELD, $queryBuilder->createNamedParameter($calendarUid, Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * The column tx_scheduler_task.tx_cal_calendar is created by "Analyze Database Structure".
     */
    private function isSchemaUpToDate(): bool
    {
        $schemaManager = $this->getConnection()->createSchemaManager();
        if (!$schemaManager->tablesExist([self::TASK_TABLE])) {
            return false;
        }
        $columns = $schemaManager->listTableColumns(self::TASK_TABLE);
        foreach ($columns as $column) {
            if (strtolower($column->getName()) === strtolower(ImportExternalCalendarTask::FIELD)) {
                return true;
            }
        }
        return false;
    }

    private function getTaskRow(int $taskUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TASK_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')
            ->from(self::TASK_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($taskUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row ?: null;
    }

    private function removeTask(int $taskUid): void
    {
        // Same as the scheduler module: the record is marked as deleted
        $this->getConnection()->update(self::TASK_TABLE, ['deleted' => 1, 'disable' => 1], ['uid' => $taskUid]);
    }

    private function storeTaskUid(int $calendarUid, int $taskUid): void
    {
        $this->connectionPool->getConnectionForTable(self::CALENDAR_TABLE)
            ->update(self::CALENDAR_TABLE, ['schedulerId' => $taskUid], ['uid' => $calendarUid]);
    }

    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TASK_TABLE);
    }
}
