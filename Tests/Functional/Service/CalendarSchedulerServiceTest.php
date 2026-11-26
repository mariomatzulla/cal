<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Service;

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
use MarioMatzulla\Cal\Task\ImportExternalCalendarTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalendarSchedulerService::class)]
final class CalendarSchedulerServiceTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private CalendarSchedulerService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Scheduler.csv');
        $this->subject = $this->get(CalendarSchedulerService::class);
    }

    private function task(int $uid): array
    {
        // also deleted and disabled tasks
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from('tx_scheduler_task')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative() ?: [];
    }

    private function schedulerId(int $calendarUid): int
    {
        return (int)$this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_calendar')->select(['schedulerId'], 'tx_cal_domain_model_calendar', ['uid' => $calendarUid])->fetchOne();
    }

    private function setCalendar(int $uid, array $values): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_calendar')->update('tx_cal_domain_model_calendar', $values, ['uid' => $uid]);
    }

    #[Test]
    public function taskIsCreatedUpdatedAndRemoved(): void
    {
        self::assertTrue($this->subject->isAvailable());
        $result = $this->subject->synchronize(1);
        self::assertSame('created', $result['status']);
        self::assertSame(60, $result['interval']);
        $taskUid = $result['taskUid'];
        self::assertSame($taskUid, $this->schedulerId(1));
        $task = $this->task($taskUid);
        self::assertSame(ImportExternalCalendarTask::class, $task['tasktype']);
        self::assertSame('cal: Holidays [1]', $task['description']);
        self::assertSame(1, (int)$task[ImportExternalCalendarTask::FIELD]);
        self::assertSame(0, (int)$task['disable']);
        self::assertSame(3600, json_decode($task['execution_details'], true)['interval']);

        // unchanged interval
        self::assertSame(['status' => 'updated', 'taskUid' => $taskUid, 'interval' => 60], $this->subject->synchronize(1));

        // new interval and title
        $this->setCalendar(1, ['refresh' => 15, 'title' => 'Feiertage']);
        self::assertSame('updated', $this->subject->synchronize(1)['status']);
        $task = $this->task($taskUid);
        self::assertSame(900, json_decode($task['execution_details'], true)['interval']);
        self::assertSame('cal: Feiertage [1]', $task['description']);

        // the stored uid is lost: the task is found by the calendar
        $this->setCalendar(1, ['schedulerId' => 0]);
        self::assertSame($taskUid, $this->subject->synchronize(1)['taskUid']);

        // no refresh: the task is removed
        $this->setCalendar(1, ['refresh' => 0]);
        self::assertSame(['status' => 'removed', 'taskUid' => $taskUid, 'interval' => 0], $this->subject->synchronize(1));
        self::assertSame(1, (int)$this->task($taskUid)['deleted']);
        self::assertSame(0, $this->schedulerId(1));
        self::assertSame('none', $this->subject->synchronize(1)['status']);
    }

    #[Test]
    public function hiddenCalendarsGetADisabledTask(): void
    {
        $result = $this->subject->synchronize(2);
        self::assertSame(1, (int)$this->task($result['taskUid'])['disable']);
    }

    #[Test]
    public function calendarsWithoutImportGetNoTask(): void
    {
        // a stored uid of a task that does not exist is reset
        self::assertSame('none', $this->subject->synchronize(3)['status']);
        self::assertSame(0, $this->schedulerId(3));
        self::assertSame('none', $this->subject->synchronize(4)['status']);
        self::assertSame('none', $this->subject->synchronize(99)['status']);
    }

    #[Test]
    public function removeForCalendar(): void
    {
        $taskUid = $this->subject->synchronize(1)['taskUid'];
        $this->subject->removeForCalendar(1);
        self::assertSame(1, (int)$this->task($taskUid)['deleted']);
        self::assertSame(0, $this->schedulerId(1));
        // nothing to remove
        $this->subject->removeForCalendar(3);
        self::assertSame(0, $this->schedulerId(3));
    }

    #[Test]
    public function missingDatabaseFieldIsReported(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')->executeStatement('ALTER TABLE tx_scheduler_task DROP COLUMN ' . ImportExternalCalendarTask::FIELD);
        self::assertSame('schema_missing', $this->subject->synchronize(1)['status']);
        $this->subject->removeForCalendar(1);

        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')->executeStatement('DROP TABLE tx_scheduler_task');
        self::assertSame('schema_missing', $this->subject->synchronize(1)['status']);
    }
}
