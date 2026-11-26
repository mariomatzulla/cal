<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Upgrades;

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
use MarioMatzulla\Cal\Task\ImportExternalCalendarTask;
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use MarioMatzulla\Cal\Upgrades\V4SchedulerUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(V4SchedulerUpgradeWizard::class)]
#[CoversClass(LegacySchema::class)]
final class V4SchedulerUpgradeWizardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative() ?: [];
    }

    #[Test]
    public function legacyTasksAreRemovedAndImportTasksCreated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/Scheduler.csv');
        $pool = $this->get(ConnectionPool::class);
        $tasks = $pool->getConnectionForTable('tx_scheduler_task');
        $tasks->insert('tx_scheduler_task', ['uid' => 10, 'pid' => 0, 'tasktype' => ImportExternalCalendarTask::class, 'description' => 'existing']);
        $tasks->insert('tx_scheduler_task', ['uid' => 11, 'pid' => 0, 'tasktype' => 'TYPO3\\CMS\\Cal\\Cron\\IndexerScheduler', 'description' => 'legacy']);
        $tasks->insert('tx_scheduler_task', ['uid' => 12, 'pid' => 0, 'tasktype' => 'TYPO3\\CMS\\Cal\\Cron\\ReminderScheduler', 'deleted' => 1, 'description' => 'already deleted']);
        $tasks->insert('tx_scheduler_task', ['uid' => 13, 'pid' => 0, 'tasktype' => 'Vendor\\Other\\Task', 'description' => 'other']);
        $pool->getConnectionForTable('tx_cal_domain_model_calendar')->insert('tx_cal_domain_model_calendar', [
            'uid' => 5, 'pid' => 5, 'title' => 'With task', 'type' => 1, 'ext_url' => 'https://example.org/c.ics', 'refresh' => 60, 'schedulerId' => 10,
        ]);

        $wizard = $this->get(V4SchedulerUpgradeWizard::class);
        self::assertStringStartsWith('Calendar Base (cal) 1.x => 4.0: ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
        self::assertTrue($wizard->updateNecessary());

        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();

        self::assertSame([1, 1], [(int)$this->row('tx_scheduler_task', 11)['disable'], (int)$this->row('tx_scheduler_task', 11)['deleted']]);
        self::assertSame(0, (int)$this->row('tx_scheduler_task', 12)['disable']);
        self::assertSame(0, (int)$this->row('tx_scheduler_task', 13)['deleted']);
        self::assertStringContainsString('tx_scheduler_task: 1 tasks of cal 1.x removed', $messages);
        // import tasks for the external calendars, calendar 5 already has one
        $taskUid = (int)$this->row('tx_cal_domain_model_calendar', 1)['schedulerId'];
        self::assertGreaterThan(13, $taskUid);
        self::assertSame(ImportExternalCalendarTask::class, $this->row('tx_scheduler_task', $taskUid)['tasktype']);
        self::assertStringContainsString('Calendar 1: import task ' . $taskUid . ' (every 60 minutes)', $messages);
        // hidden calendar: disabled task
        self::assertSame(1, (int)$this->row('tx_scheduler_task', (int)$this->row('tx_cal_domain_model_calendar', 2)['schedulerId'])['disable']);
        self::assertSame(10, (int)$this->row('tx_cal_domain_model_calendar', 5)['schedulerId']);
        self::assertSame(0, (int)$this->row('tx_cal_domain_model_calendar', 4)['schedulerId']);
        self::assertFalse($wizard->updateNecessary());

        // calendars of cal 1.x pointing to removed or missing tasks are only checked with the old table
        $calendars = $pool->getConnectionForTable('tx_cal_domain_model_calendar');
        $calendars->update('tx_cal_domain_model_calendar', ['schedulerId' => 12], ['uid' => 1]);
        $calendars->update('tx_cal_domain_model_calendar', ['schedulerId' => 99], ['uid' => 2]);
        self::assertFalse($wizard->updateNecessary());
        $calendars->executeStatement('CREATE TABLE tx_cal_calendar (uid int)');
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();
        self::assertStringNotContainsString('tasks of cal 1.x removed', $messages);
        self::assertStringContainsString('Calendar 1: import task', $messages);
        self::assertStringContainsString('Calendar 2: import task', $messages);
        self::assertNotContains((int)$this->row('tx_cal_domain_model_calendar', 1)['schedulerId'], [0, 12]);
        self::assertFalse($wizard->updateNecessary());
    }
}
