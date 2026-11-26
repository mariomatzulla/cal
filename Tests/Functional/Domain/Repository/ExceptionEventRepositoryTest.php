<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Domain\Repository;

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
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ExceptionEventRepository::class)]
final class ExceptionEventRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private ExceptionEventRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $pool = $this->get(ConnectionPool::class);
        $exceptions = $pool->getConnectionForTable(ExceptionEventRepository::TABLE);
        foreach ([
            ['uid' => 1, 'title' => 'Holiday', 'start_date' => mktime(0, 0, 0, 12, 24, 2026)],
            ['uid' => 2, 'title' => 'Hidden', 'start_date' => mktime(0, 0, 0, 12, 25, 2026), 'hidden' => 1],
            ['uid' => 3, 'title' => 'In group', 'start_date' => mktime(0, 0, 0, 12, 31, 2026)],
            ['uid' => 4, 'title' => 'In hidden group', 'start_date' => mktime(0, 0, 0, 1, 1, 2027)],
            ['uid' => 5, 'title' => 'Legacy relation', 'start_date' => mktime(0, 0, 0, 1, 6, 2027)],
        ] as $row) {
            $exceptions->insert(ExceptionEventRepository::TABLE, $row + ['pid' => 5]);
        }
        $groups = $pool->getConnectionForTable(ExceptionEventRepository::GROUP_TABLE);
        $groups->insert(ExceptionEventRepository::GROUP_TABLE, ['uid' => 1, 'pid' => 5, 'title' => 'Holidays']);
        $groups->insert(ExceptionEventRepository::GROUP_TABLE, ['uid' => 2, 'pid' => 5, 'title' => 'Hidden group', 'hidden' => 1]);
        $groupMm = $pool->getConnectionForTable(ExceptionEventRepository::GROUP_MM_TABLE);
        $groupMm->insert(ExceptionEventRepository::GROUP_MM_TABLE, ['uid_local' => 1, 'uid_foreign' => 3]);
        $groupMm->insert(ExceptionEventRepository::GROUP_MM_TABLE, ['uid_local' => 1, 'uid_foreign' => 1]);
        $groupMm->insert(ExceptionEventRepository::GROUP_MM_TABLE, ['uid_local' => 2, 'uid_foreign' => 4]);
        $mm = $pool->getConnectionForTable(ExceptionEventRepository::MM_TABLE);
        foreach ([
            [10, 1, ExceptionEventRepository::TABLE, 1],
            [10, 2, ExceptionEventRepository::TABLE, 2],
            [10, 1, ExceptionEventRepository::GROUP_TABLE, 3],
            [10, 2, ExceptionEventRepository::GROUP_TABLE, 4],
            // relations of cal 1.x without table name
            [11, 5, '', 1],
            [12, 1, ExceptionEventRepository::TABLE, 1],
        ] as [$event, $foreign, $table, $sorting]) {
            $mm->insert(ExceptionEventRepository::MM_TABLE, ['uid_local' => $event, 'uid_foreign' => $foreign, 'tablenames' => $table, 'sorting' => $sorting]);
        }
        $events = $pool->getConnectionForTable('tx_cal_domain_model_event');
        foreach ([10, 11, 12, 13] as $uid) {
            $events->insert('tx_cal_domain_model_event', ['uid' => $uid, 'pid' => 5, 'title' => 'Event ' . $uid]);
        }
        $this->subject = $this->get(ExceptionEventRepository::class);
    }

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative() ?: [];
    }

    #[Test]
    public function exceptionEventsOfAnEvent(): void
    {
        // direct and by the visible group, without hidden records and duplicates
        self::assertSame(['Holiday', 'In group'], array_column($this->subject->findByEvent(10), 'title'));
        self::assertSame(['Legacy relation'], array_column($this->subject->findByEvent(11), 'title'));
        self::assertSame([], $this->subject->findByEvent(13));
    }

    #[Test]
    public function eventsOfAnExceptionEventOrGroup(): void
    {
        self::assertSame([10, 12], $this->subject->findEventUids(ExceptionEventRepository::TABLE, 1));
        self::assertSame([10], $this->subject->findEventUids(ExceptionEventRepository::TABLE, 3));
        self::assertSame([10], $this->subject->findEventUids(ExceptionEventRepository::GROUP_TABLE, 1));
        self::assertSame([], $this->subject->findEventUids(ExceptionEventRepository::TABLE, 99));
        self::assertSame([], $this->subject->findEventUids('tx_cal_domain_model_location', 1));
    }

    #[Test]
    public function singleExceptionsAreAddedAndRemoved(): void
    {
        $uid = $this->subject->addSingleException(13, 5, mktime(0, 0, 0, 3, 1, 2027), 'Event 13');
        $exception = $this->row(ExceptionEventRepository::TABLE, $uid);
        self::assertSame(['Event 13', 5, mktime(0, 0, 0, 3, 1, 2027), mktime(0, 0, 0, 3, 1, 2027)], [$exception['title'], (int)$exception['pid'], (int)$exception['start_date'], (int)$exception['end_date']]);
        self::assertSame(['Event 13'], array_column($this->subject->findByEvent(13), 'title'));
        self::assertSame(1, (int)$this->row('tx_cal_domain_model_event', 13)['exception_cnt']);

        $this->subject->removeSingleException(13, $uid);
        self::assertSame([], $this->subject->findByEvent(13));
        self::assertSame(0, (int)$this->row('tx_cal_domain_model_event', 13)['exception_cnt']);
        self::assertSame(1, (int)$this->row(ExceptionEventRepository::TABLE, $uid)['deleted']);

        // used by another event (12) and a group: only the relation is removed
        $this->subject->removeSingleException(10, 1);
        self::assertSame(0, $this->get(ConnectionPool::class)->getConnectionForTable(ExceptionEventRepository::MM_TABLE)
            ->count('uid_local', ExceptionEventRepository::MM_TABLE, ['uid_local' => 10, 'uid_foreign' => 1, 'tablenames' => ExceptionEventRepository::TABLE]));
        // still part of the group of the event
        self::assertSame(['Holiday', 'In group'], array_column($this->subject->findByEvent(10), 'title'));
        self::assertSame(0, (int)$this->row(ExceptionEventRepository::TABLE, 1)['deleted']);
        self::assertSame(3, (int)$this->row('tx_cal_domain_model_event', 10)['exception_cnt']);
    }
}
