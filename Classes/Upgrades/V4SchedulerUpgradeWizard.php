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
use MarioMatzulla\Cal\Service\CalendarSchedulerService;
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * cal 1.x => 4.0: the scheduler tasks of cal 1.x (TYPO3\CMS\Cal\Cron\CalendarScheduler, IndexerScheduler,
 * ReminderScheduler) do not exist anymore. They are removed (marked as deleted, like the scheduler module does)
 * and every external calendar with a refresh interval gets a task "Import external calendar".
 */
#[UpgradeWizard('cal_v4Scheduler')]
final class V4SchedulerUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const TASK_TABLE = 'tx_scheduler_task';
    private const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';
    private const LEGACY_NAMESPACE = 'TYPO3\\CMS\\Cal\\';

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly LegacySchema $legacySchema,
        private readonly CalendarSchedulerService $calendarSchedulerService,
        private readonly ExternalCalendarImporter $importer,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal) 1.x => 4.0: replace the scheduler tasks';
    }

    public function getDescription(): string
    {
        return 'Removes the scheduler tasks of cal 1.x (their classes do not exist anymore) and creates a task '
            . '"Import external calendar" for every external or ICS file calendar with a refresh interval. Run the wizard '
            . '"migrate calendars, events, locations and organizers" first.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        if (!$this->legacySchema->tableExists(self::TASK_TABLE)) {
            return false;
        }
        if ($this->findLegacyTasks() !== []) {
            return true;
        }
        // Only for installations updated from cal 1.x, not for calendars of cal 4 without task
        return $this->legacySchema->findLegacyTable('tx_cal_calendar') !== null && $this->findCalendarsWithoutTask() !== [];
    }

    public function executeUpdate(): bool
    {
        $legacyTasks = $this->findLegacyTasks();
        if ($legacyTasks !== []) {
            $fields = ['disable' => 1];
            if ($this->legacySchema->findColumn(self::TASK_TABLE, 'deleted') === 'deleted') {
                $fields['deleted'] = 1;
            }
            $connection = $this->legacySchema->getConnection(self::TASK_TABLE);
            foreach ($legacyTasks as $uid) {
                $connection->update(self::TASK_TABLE, $fields, ['uid' => $uid]);
            }
            $this->write(sprintf('tx_scheduler_task: %d tasks of cal 1.x removed', count($legacyTasks)));
        }

        if (!$this->calendarSchedulerService->isAvailable()) {
            $this->write('EXT:scheduler is not installed or "Analyze Database Structure" was not executed, no import tasks created.');
            return true;
        }
        foreach ($this->findCalendarsWithoutTask() as $calendarUid) {
            $result = $this->calendarSchedulerService->synchronize($calendarUid);
            if ($result['status'] === CalendarSchedulerService::STATUS_CREATED || $result['status'] === CalendarSchedulerService::STATUS_UPDATED) {
                $this->write(sprintf('Calendar %d: import task %d (every %d minutes)', $calendarUid, $result['taskUid'], $result['interval']));
            }
        }
        return true;
    }

    /**
     * @return list<int>
     */
    private function findLegacyTasks(): array
    {
        $columns = array_filter(
            ['tasktype', 'serialized_task_object'],
            fn(string $column): bool => $this->legacySchema->findColumn(self::TASK_TABLE, $column) === $column,
        );
        if ($columns === []) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder(self::TASK_TABLE);
        $queryBuilder->select('uid', ...$columns)->from(self::TASK_TABLE);
        if ($this->legacySchema->findColumn(self::TASK_TABLE, 'deleted') === 'deleted') {
            $queryBuilder->where($queryBuilder->expr()->eq('deleted', 0));
        }
        $uids = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            foreach ($columns as $column) {
                if (str_contains((string)$row[$column], self::LEGACY_NAMESPACE)) {
                    $uids[] = (int)$row['uid'];
                    break;
                }
            }
        }
        return $uids;
    }

    /**
     * External calendars with a refresh interval whose schedulerId does not point to an import task
     *
     * @return list<int>
     */
    private function findCalendarsWithoutTask(): array
    {
        if (!$this->legacySchema->tableExists(self::CALENDAR_TABLE)
            || $this->legacySchema->findColumn(self::TASK_TABLE, 'tasktype') !== 'tasktype'
        ) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder(self::CALENDAR_TABLE);
        $calendars = $queryBuilder->select('*')
            ->from(self::CALENDAR_TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->in('type', [ExternalCalendarImporter::CALENDAR_TYPE_EXTERNAL, ExternalCalendarImporter::CALENDAR_TYPE_FILE]),
                $queryBuilder->expr()->gt('refresh', 0),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $result = [];
        foreach ($calendars as $calendar) {
            $taskUid = (int)$calendar['schedulerId'];
            $task = $taskUid > 0 ? $this->legacySchema->fetchRow(self::TASK_TABLE, ['tasktype', 'deleted'], ['uid' => $taskUid]) : null;
            if (!$this->importer->hasImportSource($calendar)) {
                continue;
            }
            if ($task === null || (int)$task['deleted'] === 1 || !str_starts_with((string)$task['tasktype'], 'MarioMatzulla\\Cal\\')) {
                $result[] = (int)$calendar['uid'];
            }
        }
        return $result;
    }

    private function write(string $message): void
    {
        $this->output?->writeln($message);
    }
}
