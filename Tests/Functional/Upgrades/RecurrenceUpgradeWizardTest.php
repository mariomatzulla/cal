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
use MarioMatzulla\Cal\Upgrades\RecurrenceUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(RecurrenceUpgradeWizard::class)]
final class RecurrenceUpgradeWizardTest extends FunctionalTestCase
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

    private function field(int $uid, string $field): string
    {
        return (string)$this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event')
            ->select([$field], 'tx_cal_domain_model_event', ['uid' => $uid])->fetchOne();
    }

    #[Test]
    public function indexAndIcalendarDataOfRecurringEventsAreRebuilt(): void
    {
        $wizard = $this->get(RecurrenceUpgradeWizard::class);
        self::assertStringStartsWith('Calendar Base (cal): ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
        self::assertFalse($wizard->updateNecessary());

        $day = (new \DateTimeImmutable('tomorrow'))->getTimestamp();
        $events = $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event');
        $cal = "BEGIN:VCALENDAR\r\nPRODID:-//TYPO3/NONSGML Calendar Base (cal) V4.0.0//EN\r\nEND:VCALENDAR";
        $client = "BEGIN:VCALENDAR\r\nPRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN\r\nEND:VCALENDAR";
        foreach ([
            ['uid' => 1, 'freq' => 'week', 'caldav_data' => $cal],
            ['uid' => 2, 'freq' => 'none', 'rdate_type' => 'date', 'rdate' => date('Ymd', $day + 7 * 86400), 'caldav_data' => $client],
            ['uid' => 3, 'freq' => 'none', 'caldav_data' => $cal],
            ['uid' => 4, 'freq' => 'day', 'deleted' => 1, 'caldav_data' => $cal],
        ] as $event) {
            $events->insert('tx_cal_domain_model_event', $event + ['pid' => 5, 'calendar_id' => 1, 'title' => 'Event', 'start_date' => $day, 'end_date' => $day, 'start_time' => 36000, 'end_time' => 39600, 'cnt' => 2]);
        }
        // index of an older version: without the first date
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_index')->insert('tx_cal_domain_model_index', [
            'pid' => 0, 'tablename' => 'tx_cal_event', 'event_uid' => 1, 'start_datetime' => (float)(date('Ymd', $day + 7 * 86400) . '100000'), 'end_datetime' => (float)(date('Ymd', $day + 7 * 86400) . '110000'),
        ]);
        self::assertTrue($wizard->updateNecessary());

        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        self::assertStringContainsString('2 recurring events: index rebuilt, iCalendar data of 1 events removed', $output->fetch());

        $index = $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_index');
        self::assertSame(2, $index->count('uid', 'tx_cal_domain_model_index', ['event_uid' => 1]));
        self::assertSame(2, $index->count('uid', 'tx_cal_domain_model_index', ['event_uid' => 2]));
        self::assertSame(['', $client, $cal], [$this->field(1, 'caldav_data'), $this->field(2, 'caldav_data'), $this->field(3, 'caldav_data')]);
        // once
        self::assertFalse($wizard->updateNecessary());
    }
}
