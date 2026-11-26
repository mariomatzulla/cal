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
use MarioMatzulla\Cal\Upgrades\Legacy\IcsFileMigration;
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use MarioMatzulla\Cal\Upgrades\V4RecordsUpgradeWizard;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(V4RecordsUpgradeWizard::class)]
#[CoversClass(IcsFileMigration::class)]
#[CoversClass(LegacySchema::class)]
final class V4RecordsUpgradeWizardTest extends FunctionalTestCase
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
        'SYS' => [
            'features' => [
                'security.system.enforceFileExtensionMimeTypeConsistency' => false,
            ],
        ],
    ];

    private function connection(string $table = 'tx_cal_domain_model_event'): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table);
    }

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative() ?: [];
    }

    private function rows(string $table, array $identifiers): array
    {
        return $this->connection($table)->select(['*'], $table, $identifiers)->fetchAllAssociative();
    }

    private function wizard(BufferedOutput $output, ?RecurrenceGenerator $recurrenceGenerator = null): V4RecordsUpgradeWizard
    {
        // new instance: LegacySchema caches the columns of the tables created by the tests
        $wizard = new V4RecordsUpgradeWizard(
            new LegacySchema($this->get(ConnectionPool::class)),
            $recurrenceGenerator ?? $this->get(RecurrenceGenerator::class),
            new IcsFileMigration(new LegacySchema($this->get(ConnectionPool::class)), $this->get(\TYPO3\CMS\Core\Resource\StorageRepository::class)),
            $this->get(\MarioMatzulla\Cal\Service\ExternalCalendarImporter::class),
            $this->get(\TYPO3\CMS\Core\Registry::class),
        );
        $wizard->setOutput($output);
        return $wizard;
    }

    private function createLegacyTables(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE TABLE tx_cal_calendar (uid int, pid int, crdate int, deleted int NOT NULL DEFAULT 0, hidden int NOT NULL DEFAULT 0, title varchar(255), type int, ext_url text, ics_file text, refresh int, cruser_id int)');
        $connection->executeStatement('CREATE TABLE tx_cal_event (uid int, pid int, crdate int, deleted int NOT NULL DEFAULT 0, title varchar(255), calendar_id int, start_date int, end_date int, start_time int, end_time int, allday int, freq varchar(20), until int, type int, tx_caldav_uid varchar(255), tx_caldav_data text, l18n_parent int, cruser_id int, image text, timezone varchar(5))');
        // already renamed by "Analyze Database Structure"
        $connection->executeStatement('CREATE TABLE zzz_deleted_tx_cal_location (uid int, pid int, crdate int, deleted int NOT NULL DEFAULT 0, name varchar(255), image text, latitude varchar(20))');
        $connection->executeStatement('CREATE TABLE tx_cal_exception_event (uid int, pid int, deleted int NOT NULL DEFAULT 0, title varchar(255), start_date int)');
        $connection->executeStatement('CREATE TABLE tx_cal_exception_event_mm (uid_local int, uid_foreign int, tablenames varchar(255), sorting int)');
    }

    #[Test]
    public function nothingToMigrate(): void
    {
        $output = new BufferedOutput();
        $wizard = $this->get(V4RecordsUpgradeWizard::class);
        $wizard->setOutput($output);
        self::assertStringStartsWith('Calendar Base (cal) 1.x => 4.0: ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('', $output->fetch());
        self::assertSame([], (new IcsFileMigration(new LegacySchema($this->get(ConnectionPool::class)), $this->get(\TYPO3\CMS\Core\Resource\StorageRepository::class)))->migrate());
    }

    #[Test]
    public function recordsRelationsAndFilesAreMigrated(): void
    {
        $this->createLegacyTables();
        $publicPath = Environment::getPublicPath();
        GeneralUtility::mkdir_deep($publicPath . '/' . IcsFileMigration::LEGACY_FOLDER);
        GeneralUtility::mkdir_deep($publicPath . '/fileadmin');
        $start = (new \DateTimeImmutable('+7 days'))->format('Ymd');
        file_put_contents(
            $publicPath . '/' . IcsFileMigration::LEGACY_FOLDER . 'feed.ics',
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:feed-1\r\nDTSTART;VALUE=DATE:$start\r\nSUMMARY:From the file\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
        );
        file_put_contents(
            $publicPath . '/' . IcsFileMigration::LEGACY_FOLDER . 'broken.ics',
            "This is not a calendar\r\n",
        );

        $calendars = [
            ['uid' => 1, 'pid' => 5, 'crdate' => 10, 'title' => 'Club', 'type' => 0, 'refresh' => 0, 'cruser_id' => 3],
            ['uid' => 2, 'pid' => 5, 'crdate' => 10, 'title' => 'Feed', 'type' => 1, 'ext_url' => 'http://127.0.0.1:1/feed.ics', 'refresh' => 60],
            ['uid' => 3, 'pid' => 5, 'crdate' => 10, 'title' => 'File', 'type' => 2, 'ics_file' => 'feed.ics,7'],
            ['uid' => 4, 'pid' => 5, 'crdate' => 10, 'title' => 'Missing file', 'type' => 2, 'ics_file' => 'missing.ics'],
            ['uid' => 5, 'pid' => 5, 'crdate' => 10, 'title' => 'No url', 'type' => 1, 'ext_url' => ''],
            ['uid' => 6, 'pid' => 5, 'crdate' => 10, 'title' => 'Broken file', 'type' => 2, 'ics_file' => 'broken.ics'],
            ['uid' => 7, 'pid' => 5, 'crdate' => 10, 'title' => 'Deleted', 'type' => 2, 'ics_file' => 'feed.ics', 'deleted' => 1],
        ];
        foreach ($calendars as $calendar) {
            $this->connection()->insert('tx_cal_calendar', $calendar);
        }
        $events = [
            [
                'uid' => 1, 'pid' => 5, 'crdate' => 20, 'title' => 'Meeting', 'calendar_id' => 1, 'start_date' => 20260625, 'end_date' => 20260625,
                'start_time' => 36000, 'end_time' => 39600, 'allday' => 0, 'freq' => 'week', 'until' => 20260731, 'type' => 3,
                'tx_caldav_uid' => 'abc.ics', 'tx_caldav_data' => 'BEGIN:VCALENDAR', 'l18n_parent' => 0, 'cruser_id' => 3, 'image' => 'old.jpg',
            ],
            ['uid' => 2, 'pid' => 5, 'crdate' => 20, 'title' => 'From feed', 'calendar_id' => 2, 'start_date' => 1782345600, 'type' => 0, 'image' => '0'],
            ['uid' => 3, 'pid' => 5, 'crdate' => 20, 'title' => 'From file', 'calendar_id' => 3, 'start_date' => 0, 'type' => 0],
            ['uid' => 6, 'pid' => 5, 'crdate' => 20, 'title' => 'From broken file', 'calendar_id' => 6, 'start_date' => 20261301, 'type' => 4],
            ['uid' => 10, 'pid' => 5, 'crdate' => 50, 'title' => 'Already migrated', 'calendar_id' => 1, 'start_date' => 20260101, 'type' => 0],
        ];
        foreach ($events as $event) {
            $this->connection()->insert('tx_cal_event', $event);
        }
        $this->connection()->insert('tx_cal_domain_model_event', ['uid' => 10, 'pid' => 5, 'crdate' => 50, 'title' => 'Already migrated', 'calendar_id' => 1]);
        $this->connection()->insert('zzz_deleted_tx_cal_location', ['uid' => 1, 'pid' => 5, 'crdate' => 30, 'name' => 'Hall', 'image' => '1', 'latitude' => '']);
        $this->connection()->insert('tx_cal_exception_event', ['uid' => 1, 'pid' => 5, 'title' => 'Christmas', 'start_date' => 20261224]);
        $this->connection()->insert('tx_cal_exception_event_mm', ['uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_exception_event', 'sorting' => 1]);
        $this->connection()->insert('tx_cal_exception_event_mm', ['uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_exception_event', 'sorting' => 2]);
        $this->connection('sys_file_reference')->insert('sys_file_reference', ['uid' => 1, 'pid' => 5, 'uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_location', 'fieldname' => 'image']);
        $this->connection('sys_category_record_mm')->insert('sys_category_record_mm', ['uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_event', 'fieldname' => 'category_id']);
        $this->connection('sys_category_record_mm')->insert('sys_category_record_mm', ['uid_local' => 2, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_event', 'fieldname' => 'category_id']);

        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();

        // records with their uids, renamed columns, dates and types
        $event = $this->row('tx_cal_domain_model_event', 1);
        self::assertSame('Meeting', $event['title']);
        self::assertSame((new \DateTimeImmutable('2026-06-25 00:00:00'))->getTimestamp(), (int)$event['start_date']);
        self::assertSame((new \DateTimeImmutable('2026-07-31 00:00:00'))->getTimestamp(), (int)$event['until']);
        self::assertSame(0, (int)$event['type']);
        self::assertSame('abc', $event['ics_uid']);
        self::assertSame('BEGIN:VCALENDAR', $event['caldav_data']);
        self::assertSame(0, (int)$event['image']);
        self::assertSame(2, (int)$event['categories']);
        self::assertSame(0, (int)$event['isTemp']);
        self::assertSame(1782345600, (int)$this->row('tx_cal_domain_model_event', 2)['start_date']);
        self::assertSame(20261301, (int)$this->row('tx_cal_domain_model_event', 6)['start_date']);
        self::assertSame(0, (int)$this->row('tx_cal_domain_model_event', 6)['type']);
        self::assertSame(50, (int)$this->row('tx_cal_domain_model_event', 10)['crdate']);
        self::assertSame('Hall', $this->row('tx_cal_domain_model_location', 1)['name']);
        self::assertSame(1, (int)$this->row('tx_cal_domain_model_location', 1)['image']);
        self::assertSame((new \DateTimeImmutable('2026-12-24 00:00:00'))->getTimestamp(), (int)$this->row('tx_cal_domain_model_exception_event', 1)['start_date']);
        self::assertCount(1, $this->rows('tx_cal_domain_model_exception_event_mm', ['uid_local' => 1, 'uid_foreign' => 1]));
        self::assertSame('Club', $this->row('tx_cal_domain_model_calendar', 1)['title']);

        // references and relations
        self::assertSame('tx_cal_domain_model_location', $this->row('sys_file_reference', 1)['tablenames']);
        self::assertCount(2, $this->rows('sys_category_record_mm', ['tablenames' => 'tx_cal_domain_model_event', 'fieldname' => 'categories']));

        // ICS files: copied into the default storage and imported
        self::assertCount(1, $this->rows('sys_file_reference', ['tablenames' => 'tx_cal_domain_model_calendar', 'fieldname' => 'ics_file', 'uid_foreign' => 3]));
        self::assertSame('1', (string)$this->row('tx_cal_domain_model_calendar', 3)['ics_file']);
        self::assertSame('0', (string)$this->row('tx_cal_domain_model_calendar', 4)['ics_file']);
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/cal/ics/feed.ics');
        $imported = $this->rows('tx_cal_domain_model_event', ['calendar_id' => 3, 'deleted' => 0]);
        self::assertSame(['From the file'], array_column($imported, 'title'));
        // the import of the broken file fails, the migrated events are kept as imported events
        self::assertSame(1, (int)$this->row('tx_cal_domain_model_event', 6)['isTemp']);

        self::assertStringContainsString('tx_cal_event => tx_cal_domain_model_event: 4 records copied', $messages);
        self::assertStringContainsString('zzz_deleted_tx_cal_location => tx_cal_domain_model_location: 1 records copied', $messages);
        self::assertStringContainsString('tx_cal_exception_event_mm => tx_cal_domain_model_exception_event_mm: 1 relations copied', $messages);
        self::assertStringContainsString('sys_file_reference: 1 file references moved', $messages);
        self::assertStringContainsString('WARNING tx_cal_domain_model_event: 1 records reference files of cal < 1.6', $messages);
        self::assertStringContainsString('1: old.jpg', $messages);
        self::assertStringContainsString('sys_category_record_mm: 2 category relations moved', $messages);
        self::assertStringContainsString('WARNING calendar 4: the ICS file uploads/tx_cal/ics/missing.ics does not exist', $messages);
        self::assertStringContainsString('ICS files: files of 2 calendars copied', $messages);
        self::assertStringContainsString('WARNING calendar 4 "Missing file": no URL / ICS file', $messages);
        self::assertStringContainsString('WARNING calendar 5 "No url"', $messages);
        self::assertStringContainsString('events of external / ICS file calendars are now imported (read-only) events', $messages);
        self::assertStringContainsString('Calendar 3 imported: 1 events', $messages);
        self::assertStringContainsString('WARNING calendar 6 could not be imported', $messages);
        self::assertMatchesRegularExpression('/Calendar 2 imported|WARNING calendar 2 could not be imported/', $messages);
        self::assertMatchesRegularExpression('/Recurrence index rebuilt|recurrence index could not be rebuilt/', $messages);

        // second run: nothing left
        $wizard = $this->wizard($output);
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();
        self::assertStringContainsString('tx_cal_event => tx_cal_domain_model_event: 0 records copied', $messages);
        // the event replaced by the import of calendar 3 is not copied again
        self::assertSame([], $this->row('tx_cal_domain_model_event', 3));
        self::assertStringNotContainsString('Recurrence index', $messages);
    }

    #[Test]
    public function conflictingUidsStopTheMigration(): void
    {
        $this->createLegacyTables();
        $location = $this->connection('tx_cal_domain_model_location');
        for ($uid = 1; $uid <= 21; $uid++) {
            $location->insert('zzz_deleted_tx_cal_location', ['uid' => $uid, 'pid' => 5, 'crdate' => 100, 'name' => 'Old ' . $uid]);
            $location->insert('tx_cal_domain_model_location', ['uid' => $uid, 'pid' => 5, 'crdate' => 200, 'name' => 'New ' . $uid]);
        }
        $this->connection()->insert('tx_cal_event', ['uid' => 1, 'pid' => 5, 'crdate' => 20, 'title' => 'Event']);

        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertFalse($wizard->executeUpdate());
        $messages = $output->fetch();
        self::assertStringContainsString('ERROR zzz_deleted_tx_cal_location: the uids 1, 2, 3', $messages);
        self::assertStringContainsString('20, ...', $messages);
        self::assertStringContainsString('Nothing was migrated.', $messages);
        self::assertSame([], $this->row('tx_cal_domain_model_event', 1));
        self::assertSame('New 1', $this->row('tx_cal_domain_model_location', 1)['name']);
    }

    #[Test]
    public function legacyReferencesAloneNeedAnUpdate(): void
    {
        $this->connection('sys_file_reference')->insert('sys_file_reference', ['uid' => 1, 'pid' => 5, 'uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_organizer', 'fieldname' => 'image']);
        $this->connection('tx_cal_domain_model_organizer')->insert('tx_cal_domain_model_organizer', ['uid' => 1, 'pid' => 5, 'name' => 'Club', 'image' => 0]);
        $this->connection('tx_cal_domain_model_organizer')->insert('tx_cal_domain_model_organizer', ['uid' => 2, 'pid' => 5, 'name' => 'Without image', 'image' => 3]);
        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame(1, (int)$this->row('tx_cal_domain_model_organizer', 1)['image']);
        self::assertSame(0, (int)$this->row('tx_cal_domain_model_organizer', 2)['image']);
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function icsFilesNeedADefaultStorage(): void
    {
        // a storage which is not the default one (otherwise TYPO3 creates the default storage "fileadmin")
        $this->connection('sys_file_storage')->insert('sys_file_storage', ['uid' => 1, 'pid' => 0, 'name' => 'Other', 'driver' => 'Local', 'is_default' => 0, 'is_online' => 1,
            'configuration' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
                . '<field index="basePath"><value index="vDEF">other/</value></field><field index="pathType"><value index="vDEF">relative</value></field>'
                . '</language></sheet></data></T3FlexForms>',
        ]);
        $this->createLegacyTables();
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/' . IcsFileMigration::LEGACY_FOLDER);
        file_put_contents(Environment::getPublicPath() . '/' . IcsFileMigration::LEGACY_FOLDER . 'feed.ics', "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");
        $this->connection()->insert('tx_cal_calendar', ['uid' => 3, 'pid' => 5, 'crdate' => 10, 'title' => 'File', 'type' => 2, 'ics_file' => 'feed.ics']);
        $this->connection()->insert('tx_cal_domain_model_calendar', ['uid' => 3, 'pid' => 5, 'crdate' => 10, 'title' => 'File', 'type' => 2]);
        $migration = new IcsFileMigration(new LegacySchema($this->get(ConnectionPool::class)), $this->get(\TYPO3\CMS\Core\Resource\StorageRepository::class));
        self::assertTrue($migration->findPending()[0]['exists']);
        self::assertSame(['ERROR ICS files: there is no default file storage, the ICS files of the calendars could not be migrated.'], $migration->migrate());
    }

    #[Test]
    public function relationsAndIcsFilesAloneNeedAnUpdate(): void
    {
        $this->createLegacyTables();
        $this->connection()->insert('tx_cal_calendar', ['uid' => 1, 'pid' => 5, 'crdate' => 10, 'title' => 'Club', 'type' => 0]);
        $this->connection()->insert('tx_cal_domain_model_calendar', ['uid' => 1, 'pid' => 5, 'crdate' => 10, 'title' => 'Club', 'type' => 0]);
        $this->connection()->insert('tx_cal_event', ['uid' => 1, 'pid' => 5, 'crdate' => 20, 'title' => 'Event', 'calendar_id' => 1]);
        $this->connection()->insert('tx_cal_domain_model_event', ['uid' => 1, 'pid' => 5, 'crdate' => 20, 'title' => 'Event', 'calendar_id' => 1]);

        // only an MM relation is missing
        $this->connection()->insert('tx_cal_exception_event_mm', ['uid_local' => 1, 'uid_foreign' => 2, 'tablenames' => 'tx_cal_exception_event_group', 'sorting' => 1]);
        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();
        self::assertStringContainsString('1 relations copied', $messages);
        self::assertStringNotContainsString('imported', $messages);
        self::assertCount(1, $this->rows('tx_cal_domain_model_exception_event_mm', ['uid_local' => 1, 'uid_foreign' => 2]));
        self::assertFalse($wizard->updateNecessary());

        // only the ICS file of an already migrated calendar is missing, the target folder exists already
        $publicPath = Environment::getPublicPath();
        GeneralUtility::mkdir_deep($publicPath . '/' . IcsFileMigration::LEGACY_FOLDER);
        GeneralUtility::mkdir_deep($publicPath . '/fileadmin/' . IcsFileMigration::TARGET_FOLDER);
        file_put_contents($publicPath . '/' . IcsFileMigration::LEGACY_FOLDER . 'club.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n");
        $this->connection()->insert('tx_cal_calendar', ['uid' => 2, 'pid' => 5, 'crdate' => 10, 'title' => 'File', 'type' => 2, 'ics_file' => 'club.ics']);
        $this->connection()->insert('tx_cal_domain_model_calendar', ['uid' => 2, 'pid' => 5, 'crdate' => 10, 'title' => 'File', 'type' => 2]);
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertStringContainsString('ICS files: files of 1 calendars copied', $output->fetch());
        self::assertFileExists($publicPath . '/fileadmin/' . IcsFileMigration::TARGET_FOLDER . 'club.ics');
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function failingIndexRebuildIsReported(): void
    {
        $this->createLegacyTables();
        $this->connection()->insert('tx_cal_event', ['uid' => 1, 'pid' => 5, 'crdate' => 20, 'title' => 'Event', 'calendar_id' => 1]);
        $recurrenceGenerator = self::createStub(RecurrenceGenerator::class);
        $recurrenceGenerator->method('setRange')->willReturnSelf();
        $recurrenceGenerator->method('generateIndex')->willThrowException(new \RuntimeException('index broken'));
        $output = new BufferedOutput();
        self::assertTrue($this->wizard($output, $recurrenceGenerator)->executeUpdate());
        self::assertStringContainsString('The recurrence index could not be rebuilt (index broken).', $output->fetch());
    }

    #[Test]
    public function ymdValuesAreConvertedToTimestamps(): void
    {
        self::assertSame((new \DateTimeImmutable('2026-06-25 00:00:00'))->getTimestamp(), V4RecordsUpgradeWizard::ymdToTimestamp('20260625'));
        self::assertSame('abc', V4RecordsUpgradeWizard::ymdToTimestamp('abc'));
        self::assertSame(0, V4RecordsUpgradeWizard::ymdToTimestamp(0));
        self::assertSame(1782345600, V4RecordsUpgradeWizard::ymdToTimestamp(1782345600));
        self::assertSame(20260230, V4RecordsUpgradeWizard::ymdToTimestamp(20260230));
    }
}
