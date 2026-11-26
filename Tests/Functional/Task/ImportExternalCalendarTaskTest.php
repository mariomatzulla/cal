<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Task;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ImportExternalCalendarTask::class)]
final class ImportExternalCalendarTaskTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_calendar');
        // an ICS file calendar without file: the import removes the imported events
        $connection->insert('tx_cal_domain_model_calendar', ['uid' => 1, 'pid' => 5, 'title' => 'Holidays', 'type' => 2]);
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event')
            ->insert('tx_cal_domain_model_event', ['uid' => 1, 'pid' => 5, 'calendar_id' => 1, 'title' => 'Imported', 'isTemp' => 1]);
    }

    #[Test]
    public function parameters(): void
    {
        $task = new ImportExternalCalendarTask();
        self::assertSame(0, $task->getCalendarUid());
        $task->setTaskParameters([ImportExternalCalendarTask::FIELD => '1']);
        self::assertSame(1, $task->getCalendarUid());
        self::assertSame([ImportExternalCalendarTask::FIELD => 1], $task->getTaskParameters());
        $task->setCalendarUid(2);
        self::assertSame(2, $task->getCalendarUid());
        $task->setTaskParameters([]);
        self::assertSame(0, $task->getCalendarUid());
    }

    #[Test]
    public function additionalInformationShowsTheCalendar(): void
    {
        $task = new ImportExternalCalendarTask();
        $task->setCalendarUid(1);
        self::assertSame('Holidays [1]', $task->getAdditionalInformation());
        $task->setCalendarUid(9);
        self::assertSame('Calendar [9] not found', $task->getAdditionalInformation());
    }

    #[Test]
    public function executeImportsTheCalendar(): void
    {
        $task = new ImportExternalCalendarTask();
        $task->setCalendarUid(1);
        self::assertTrue($task->execute());
        self::assertSame(0, $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event')->count('uid', 'tx_cal_domain_model_event', ['calendar_id' => 1]));
    }

    #[Test]
    public function executeWithoutCalendarFails(): void
    {
        $this->expectExceptionCode(1794945001);
        (new ImportExternalCalendarTask())->execute();
    }
}
