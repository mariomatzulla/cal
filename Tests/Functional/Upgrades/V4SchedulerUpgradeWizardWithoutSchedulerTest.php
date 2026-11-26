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
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use MarioMatzulla\Cal\Upgrades\V4SchedulerUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(V4SchedulerUpgradeWizard::class)]
#[CoversClass(LegacySchema::class)]
final class V4SchedulerUpgradeWizardWithoutSchedulerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    #[Test]
    public function legacyTasksAreRemovedWithoutCreatingImportTasks(): void
    {
        $wizard = $this->get(V4SchedulerUpgradeWizard::class);
        self::assertFalse($wizard->updateNecessary());

        // the task table of an old installation, EXT:scheduler is not installed anymore
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task');
        $connection->executeStatement('CREATE TABLE tx_scheduler_task (uid int, disable int NOT NULL DEFAULT 0, deleted int NOT NULL DEFAULT 0, serialized_task_object text)');
        $connection->insert('tx_scheduler_task', ['uid' => 1, 'serialized_task_object' => 'O:36:"TYPO3\\CMS\\Cal\\Cron\\CalendarScheduler":0:{}']);
        $wizard = $this->get(V4SchedulerUpgradeWizard::class);
        self::assertTrue($wizard->updateNecessary());

        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();
        self::assertStringContainsString('1 tasks of cal 1.x removed', $messages);
        self::assertStringContainsString('EXT:scheduler is not installed', $messages);
        self::assertSame(1, (int)$connection->select(['disable'], 'tx_scheduler_task', ['uid' => 1])->fetchOne());
        self::assertFalse($wizard->updateNecessary());

        // calendars of cal 1.x: without the column tasktype (TYPO3 < 12) no import tasks are possible
        $connection->executeStatement('CREATE TABLE tx_cal_calendar (uid int)');
        self::assertFalse($wizard->updateNecessary());
    }
}
