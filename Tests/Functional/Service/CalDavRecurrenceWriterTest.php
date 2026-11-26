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
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use MarioMatzulla\Cal\Service\CalDavRecurrenceWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalDavRecurrenceWriter::class)]
final class CalDavRecurrenceWriterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'cal' => [
                'hideLocationTextfield' => '0',
                'hideOrganizerTextfield' => '0',
                'useLocationStructure' => 'tx_cal_domain_model_location',
                'useOrganizerStructure' => 'tx_cal_domain_model_organizer',
                'showTimes' => '1',
                'useTeaser' => '0',
                'hideDeviationRecords' => '1',
                'recurrenceStart' => 'cal:yearstart',
                'recurrenceEnd' => '+3 years',
            ],
        ],
    ];

    /**
     * Mondays of the series, starting next week (the index covers the current and the next years)
     *
     * @var list<\DateTimeImmutable>
     */
    private array $mondays = [];

    protected function setUp(): void
    {
        parent::setUp();
        $first = new \DateTimeImmutable('monday next week');
        for ($i = 0; $i < 10; $i++) {
            $this->mondays[] = $first->modify('+' . $i . ' weeks');
        }
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable('tx_cal_domain_model_event')->insert('tx_cal_domain_model_event', [
            'uid' => 1, 'pid' => 5, 'calendar_id' => 1, 'title' => 'Weekly', 'description' => 'Text', 'location' => 'Hall',
            'start_date' => $first->getTimestamp(), 'end_date' => $first->getTimestamp(), 'start_time' => 36000, 'end_time' => 39600,
            'freq' => 'week', 'byday' => 'MO', 'cnt' => 10,
        ]);
        $exceptions = $pool->getConnectionForTable(ExceptionEventRepository::TABLE);
        $exceptions->insert(ExceptionEventRepository::TABLE, ['uid' => 1, 'pid' => 5, 'title' => 'Kept', 'start_date' => $this->mondays[1]->getTimestamp()]);
        $exceptions->insert(ExceptionEventRepository::TABLE, ['uid' => 2, 'pid' => 5, 'title' => 'Removed', 'start_date' => $this->mondays[2]->getTimestamp()]);
        // recurring exception event (two Mondays): not changed by the client
        $exceptions->insert(ExceptionEventRepository::TABLE, ['uid' => 3, 'pid' => 5, 'title' => 'Recurring', 'start_date' => $this->mondays[3]->getTimestamp(), 'freq' => 'week', 'byday' => 'MO', 'cnt' => 2]);
        $mm = $pool->getConnectionForTable(ExceptionEventRepository::MM_TABLE);
        foreach ([1, 2, 3] as $uid) {
            $mm->insert(ExceptionEventRepository::MM_TABLE, ['uid_local' => 1, 'uid_foreign' => $uid, 'tablenames' => ExceptionEventRepository::TABLE, 'sorting' => $uid]);
        }
        $deviations = $pool->getConnectionForTable('tx_cal_domain_model_eventdeviation');
        $deviations->insert('tx_cal_domain_model_eventdeviation', ['uid' => 1, 'pid' => 5, 'parentid' => 1, 'title' => 'Old', 'orig_start_date' => $this->mondays[6]->getTimestamp(), 'orig_start_time' => 36000]);
        $deviations->insert('tx_cal_domain_model_eventdeviation', ['uid' => 2, 'pid' => 5, 'parentid' => 1, 'title' => 'Gone', 'orig_start_date' => $this->mondays[7]->getTimestamp(), 'orig_start_time' => 36000]);
    }

    private function rows(string $table, array $identifiers = []): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('*')->from($table)->orderBy('uid');
        foreach ($identifiers as $field => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)));
        }
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    #[Test]
    public function removedDatesAndChangedOccurrencesAreStored(): void
    {
        $day = fn(int $week): string => $this->mondays[$week]->format('Ymd');
        $this->get(CalDavRecurrenceWriter::class)->write(1, [
            'exdates' => [$day(1), $day(5)],
            'overrides' => [
                ['orig_start_date' => $this->mondays[6]->getTimestamp(), 'orig_start_time' => 36000, 'title' => 'Moved', 'description' => 'Text', 'location' => 'Room',
                    'allday' => 0, 'start_date' => $this->mondays[6]->getTimestamp(), 'start_time' => 50400, 'end_date' => $this->mondays[6]->getTimestamp(), 'end_time' => 57600],
                ['orig_start_date' => $this->mondays[8]->getTimestamp(), 'orig_start_time' => 36000, 'title' => 'Weekly', 'description' => 'Other', 'location' => 'Hall',
                    'allday' => 0, 'start_date' => $this->mondays[8]->getTimestamp(), 'start_time' => 39600, 'end_date' => $this->mondays[8]->getTimestamp(), 'end_time' => 43200],
            ],
        ]);

        // exception events: the removed date is a new single exception event, the single exception event of a date
        // the client does not remove anymore is deleted, the recurring exception event is kept
        $exceptions = $this->get(ExceptionEventRepository::class)->findByEvent(1);
        self::assertSame(['Kept', 'Recurring', 'Weekly'], array_column($exceptions, 'title'));
        self::assertSame($this->mondays[5]->getTimestamp(), (int)$exceptions[2]['start_date']);
        self::assertSame(1, (int)$this->rows(ExceptionEventRepository::TABLE, ['uid' => 2])[0]['deleted']);

        // deviations: updated, new and deleted, texts of the event are not repeated
        $deviations = $this->rows('tx_cal_domain_model_eventdeviation', ['parentid' => 1]);
        self::assertSame(['Moved', '', 'Room', 50400, 0], [$deviations[0]['title'], $deviations[0]['description'], $deviations[0]['location'], (int)$deviations[0]['start_time'], (int)$deviations[0]['deleted']]);
        self::assertSame(1, (int)$deviations[1]['deleted']);
        self::assertSame(['', 'Other', '', 5, $this->mondays[8]->getTimestamp(), 36000], [$deviations[2]['title'], $deviations[2]['description'], $deviations[2]['location'], (int)$deviations[2]['pid'], (int)$deviations[2]['orig_start_date'], (int)$deviations[2]['orig_start_time']]);
        self::assertSame(2, (int)$this->rows('tx_cal_domain_model_event', ['uid' => 1])[0]['deviation']);

        // the recurrence index: without the removed dates, the changed occurrences at their new time
        $index = array_map(static fn(array $row): string => sprintf('%.0f', (float)$row['start_datetime']), $this->rows('tx_cal_domain_model_index', ['event_uid' => 1]));
        sort($index);
        $expected = [
            $day(0) . '100000',
            $day(2) . '100000',
            $day(6) . '140000',
            $day(7) . '100000',
            $day(8) . '110000',
            $day(9) . '100000',
        ];
        self::assertSame($expected, $index);
    }

    #[Test]
    public function withoutRemovedDatesTheSingleExceptionEventsAreDeleted(): void
    {
        $this->get(CalDavRecurrenceWriter::class)->write(1, ['exdates' => [], 'overrides' => []]);
        self::assertSame(['Recurring'], array_column($this->get(ExceptionEventRepository::class)->findByEvent(1), 'title'));
        self::assertSame(0, (int)$this->rows('tx_cal_domain_model_event', ['uid' => 1])[0]['deviation']);
    }

    #[Test]
    public function unknownEventsAreIgnored(): void
    {
        $this->get(CalDavRecurrenceWriter::class)->write(99, ['exdates' => ['20261201'], 'overrides' => []]);
        self::assertCount(3, $this->rows(ExceptionEventRepository::TABLE));
    }
}
