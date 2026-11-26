<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Hooks;

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
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use MarioMatzulla\Cal\Hooks\CalendarDataHandlerHook;
use MarioMatzulla\Cal\Service\CalendarSchedulerService;
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Service\IcsGenerator;
use MarioMatzulla\Cal\Task\ImportExternalCalendarTask;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalendarDataHandlerHook::class)]
final class CalendarDataHandlerHookTest extends FunctionalTestCase
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';

    protected array $coreExtensionsToLoad = ['scheduler'];

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
                'recurrenceStart' => '2026-01-01 00:00:00',
                'recurrenceEnd' => '+3 years',
            ],
        ],
        'SYS' => [
            'features' => [
                'security.system.enforceFileExtensionMimeTypeConsistency' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandler.csv');
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'https://example.org/');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    private function process(array $data, array $commands = []): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    /**
     * @return list<string> "SEVERITY: message"
     */
    private function messages(): array
    {
        return array_map(
            static fn($message): string => $message->getSeverity()->name . ': ' . $message->getMessage(),
            $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessagesAndFlush(),
        );
    }

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative() ?: [];
    }

    private function indexCount(int $eventUid): int
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_index')
            ->count('uid', 'tx_cal_domain_model_index', ['event_uid' => $eventUid]);
    }

    private function hook(?RecurrenceGenerator $recurrenceGenerator = null): CalendarDataHandlerHook
    {
        return new CalendarDataHandlerHook(
            $this->get(EventRepository::class),
            $this->get(CalendarRepository::class),
            $this->get(ExternalCalendarImporter::class),
            $this->get(CalendarSchedulerService::class),
            $this->get(IcsGenerator::class),
            $recurrenceGenerator ?? $this->get(RecurrenceGenerator::class),
            $this->get(DataMapper::class),
            $this->get(ConnectionPool::class),
            $this->get(FlashMessageService::class),
            $this->get(SiteFinder::class),
            $this->get(Context::class),
            $this->get(ExceptionEventRepository::class),
        );
    }

    private function addIcsFile(int $calendarUid, string $name, string $content): void
    {
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/fileadmin');
        $path = Environment::getVarPath() . '/' . $name;
        file_put_contents($path, $content);
        $storage = $this->get(StorageRepository::class)->getDefaultStorage();
        $file = $storage->addFile($path, $storage->getRootLevelFolder(), $name);
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', [
            'pid' => 5,
            'uid_local' => $file->getUid(),
            'uid_foreign' => $calendarUid,
            'tablenames' => self::CALENDAR_TABLE,
            'fieldname' => 'ics_file',
            'sorting_foreign' => 1,
        ]);
        $this->get(ConnectionPool::class)->getConnectionForTable(self::CALENDAR_TABLE)->update(self::CALENDAR_TABLE, ['ics_file' => 1], ['uid' => $calendarUid]);
    }

    #[Test]
    public function newEventsAreIndexedAndExported(): void
    {
        $start = (new \DateTimeImmutable('today +7 days', new \DateTimeZone('UTC')))->getTimestamp();
        $this->process([
            self::EVENT_TABLE => [
                'NEW1' => [
                    'pid' => 5,
                    'title' => 'New event',
                    'calendar_id' => 1,
                    'start_date' => $start,
                    'end_date' => $start,
                    'start_time' => 36000,
                    'end_time' => 39600,
                    'freq' => 'week',
                    'until' => $start + 21 * 86400,
                    // must not be possible
                    'isTemp' => 1,
                ],
            ],
            self::CALENDAR_TABLE => [
                'NEW2' => ['pid' => 5, 'title' => 'New calendar', 'type' => 0],
            ],
            // other tables are ignored by the hook
            'tx_cal_domain_model_location' => [1 => ['name' => 'Town hall (new)']],
        ]);
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::EVENT_TABLE);
        $uid = (int)$queryBuilder->select('uid')->from(self::EVENT_TABLE)
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('New event')))
            ->executeQuery()->fetchOne();
        self::assertGreaterThan(9, $uid);
        $event = $this->row(self::EVENT_TABLE, $uid);
        self::assertSame(0, (int)$event['isTemp']);
        self::assertNotSame('', (string)$event['ics_uid']);
        self::assertStringContainsString('BEGIN:VCALENDAR', (string)$event['caldav_data']);
        self::assertStringContainsString('New event', (string)$event['caldav_data']);
        self::assertGreaterThan(1, $this->indexCount($uid));
        self::assertGreaterThan(1, (int)$this->row(self::CALENDAR_TABLE, 1)['tstamp']);
        self::assertSame('Town hall (new)', $this->row('tx_cal_domain_model_location', 1)['name']);
        self::assertSame([], $this->messages());
    }

    #[Test]
    public function hiddenEventsAndEventsWithoutCalendarAreNotExported(): void
    {
        $this->process([self::EVENT_TABLE => [1 => ['title' => 'Meeting (updated)']]]);
        self::assertGreaterThan(0, $this->indexCount(1));
        $this->process([self::EVENT_TABLE => [1 => ['hidden' => 1, 'title' => 'Meeting (hidden)']], ]);
        self::assertSame(0, $this->indexCount(1));
        self::assertStringNotContainsString('Meeting (hidden)', (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);

        $this->process([self::EVENT_TABLE => [9 => ['title' => 'Still without calendar']]]);
        self::assertSame('Still without calendar', $this->row(self::EVENT_TABLE, 9)['title']);
        self::assertSame('', (string)$this->row(self::EVENT_TABLE, 9)['caldav_data']);
        self::assertSame([], $this->messages());
    }

    #[Test]
    public function exceptionEventsAndDeviationsUpdateTheirEvent(): void
    {
        // event 1: weekly, three dates
        $this->process([self::EVENT_TABLE => [1 => ['title' => 'Meeting']]]);
        self::assertSame(3, $this->indexCount(1));
        $start = (int)$this->row(self::EVENT_TABLE, 1)['start_date'];
        $secondWeek = date('Ymd', $start + 7 * 86400);
        $thirdWeek = date('Ymd', $start + 14 * 86400);
        $data = (string)$this->row(self::EVENT_TABLE, 1)['caldav_data'];
        self::assertStringContainsString("RRULE:FREQ=WEEKLY;UNTIL=", $data);
        self::assertStringNotContainsString('EXDATE', $data);

        // an exception event of the second week
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable(ExceptionEventRepository::TABLE)->insert(ExceptionEventRepository::TABLE, ['uid' => 1, 'pid' => 5, 'title' => 'Holiday', 'start_date' => $start + 7 * 86400]);
        $pool->getConnectionForTable(ExceptionEventRepository::MM_TABLE)->insert(ExceptionEventRepository::MM_TABLE, ['uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => ExceptionEventRepository::TABLE]);
        $this->process([ExceptionEventRepository::TABLE => [1 => ['title' => 'Holidays']]]);
        self::assertSame(2, $this->indexCount(1));
        self::assertStringContainsString('EXDATE;TZID=Europe/Berlin:' . $secondWeek . 'T100000', (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);

        // a deviation of the third week
        $this->process(['tx_cal_domain_model_eventdeviation' => ['NEW1' => [
            'pid' => 5, 'parentid' => 1, 'title' => 'Moved', 'orig_start_date' => $start + 14 * 86400, 'orig_start_time' => 36000,
            'start_time' => 50400, 'end_time' => 57600,
        ]]]);
        $data = (string)$this->row(self::EVENT_TABLE, 1)['caldav_data'];
        self::assertStringContainsString('RECURRENCE-ID;TZID=Europe/Berlin:' . $thirdWeek . 'T100000', $data);
        self::assertStringContainsString('DTSTART;TZID=Europe/Berlin:' . $thirdWeek . 'T140000', $data);
        self::assertStringContainsString("SUMMARY:Moved\r\n", $data);

        // changed without its event (the parent is read from the database), deleted
        $queryBuilder = $pool->getQueryBuilderForTable('tx_cal_domain_model_eventdeviation');
        $deviationUid = (int)$queryBuilder->select('uid')->from('tx_cal_domain_model_eventdeviation')->executeQuery()->fetchOne();
        $this->process(['tx_cal_domain_model_eventdeviation' => [$deviationUid => ['title' => 'Moved again']]]);
        self::assertStringContainsString("SUMMARY:Moved again\r\n", (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);
        $this->process([], ['tx_cal_domain_model_eventdeviation' => [$deviationUid => ['delete' => 1]]]);
        self::assertStringNotContainsString('RECURRENCE-ID', (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);

        // the exception event is deleted
        $this->process([], [ExceptionEventRepository::TABLE => [1 => ['delete' => 1]]]);
        self::assertSame(3, $this->indexCount(1));
        self::assertStringNotContainsString('EXDATE', (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);
        self::assertSame([], $this->messages());
    }

    #[Test]
    public function importedEventsAreLocked(): void
    {
        // separate runs: the DataHandler drops the data of records which are deleted in the same run
        $this->process([self::EVENT_TABLE => [2 => ['title' => 'Changed']]]);
        $this->process([], [self::EVENT_TABLE => [2 => ['delete' => 1]]]);
        $event = $this->row(self::EVENT_TABLE, 2);
        self::assertSame(['Imported', 0], [$event['title'], (int)$event['deleted']]);
        self::assertSame(
            array_fill(0, 2, 'WARNING: Imported events of external calendars cannot be changed, moved or deleted in the backend.'),
            $this->messages(),
        );
    }

    #[Test]
    public function deletingRemovesIndexAndImportedEvents(): void
    {
        $this->process([self::EVENT_TABLE => [1 => ['title' => 'Meeting (updated)']]]);
        self::assertGreaterThan(0, $this->indexCount(1));
        $this->process([], [self::EVENT_TABLE => [1 => ['delete' => 1]], self::CALENDAR_TABLE => [3 => ['delete' => 1]]]);
        self::assertSame(0, $this->indexCount(1));
        self::assertSame(1, (int)$this->row(self::EVENT_TABLE, 1)['deleted']);
        self::assertSame([], $this->row(self::EVENT_TABLE, 2));
        self::assertSame([], $this->row(self::EVENT_TABLE, 4));
        self::assertSame(1, (int)$this->row(self::CALENDAR_TABLE, 3)['deleted']);
    }

    #[Test]
    public function savedCalendarsAreImported(): void
    {
        $start = (new \DateTimeImmutable('+7 days'))->format('Ymd');
        $this->addIcsFile(4, 'good.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:good-1\r\nDTSTART;VALUE=DATE:$start\r\nSUMMARY:From the file\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $this->addIcsFile(5, 'broken.ics', "This is not a calendar\r\n");
        $this->process([self::CALENDAR_TABLE => [
            4 => ['title' => 'Good file (saved)'],
            5 => ['title' => 'Broken file (saved)'],
            6 => ['title' => 'Without url (saved)'],
        ]]);
        $messages = $this->messages();
        self::assertSame('OK: Calendar imported: 1 events.', $messages[0]);
        self::assertStringStartsWith('ERROR: The calendar could not be imported: ', $messages[1]);
        self::assertSame('INFO: 1 imported events of this calendar have been removed.', $messages[2]);
        self::assertCount(3, $messages);
        self::assertSame([], $this->row(self::EVENT_TABLE, 6));
    }

    #[Test]
    public function schedulerTasksAreSynchronized(): void
    {
        $this->process([self::CALENDAR_TABLE => [7 => ['title' => 'With task (saved)']]]);
        $messages = $this->messages();
        self::assertStringStartsWith('ERROR: The calendar could not be imported: ', $messages[0]);
        $taskUid = (int)$this->row(self::CALENDAR_TABLE, 7)['schedulerId'];
        self::assertSame('OK: Scheduler task #' . $taskUid . ' created: the calendar is synchronized every 60 minutes.', $messages[1]);

        $this->process([self::CALENDAR_TABLE => [7 => ['refresh' => 0]]]);
        self::assertSame('INFO: Scheduler task #' . $taskUid . ' has been removed.', $this->messages()[1]);

        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')
            ->executeStatement('ALTER TABLE tx_scheduler_task DROP COLUMN ' . ImportExternalCalendarTask::FIELD);
        $this->process([self::CALENDAR_TABLE => [7 => ['refresh' => 60]]]);
        self::assertStringStartsWith('WARNING: The scheduler task could not be created', $this->messages()[1]);
    }

    #[Test]
    public function failingSchedulerTasksAreReported(): void
    {
        // the task cannot be stored anymore
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')
            ->executeStatement('ALTER TABLE tx_scheduler_task DROP COLUMN execution_details');
        $this->process([self::CALENDAR_TABLE => [7 => ['title' => 'With task (saved)']]]);
        self::assertStringStartsWith('ERROR: The scheduler task could not be updated: ', $this->messages()[1]);
    }

    #[Test]
    public function requestsForTheIcsTemplate(): void
    {
        $hook = $this->get(CalendarDataHandlerHook::class);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        // record outside of a site: plain request, the page is read from the record
        $hook->processDatamap_afterDatabaseOperations('update', self::EVENT_TABLE, 8, [], $dataHandler);
        self::assertStringContainsString('Outside of the site', (string)$this->row(self::EVENT_TABLE, 8)['caldav_data']);

        // the current request is used if it has a site (e.g. a backend module of a page)
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $this->get(SiteFinder::class)->getSiteByIdentifier('main'));
        $hook->processDatamap_afterDatabaseOperations('update', self::EVENT_TABLE, 1, ['title' => 'Meeting'], $dataHandler);
        self::assertStringContainsString('Meeting', (string)$this->row(self::EVENT_TABLE, 1)['caldav_data']);
    }

    #[Test]
    public function edgeCasesOfTheHookMethods(): void
    {
        $hook = $this->get(CalendarDataHandlerHook::class);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        // unknown new records
        $hook->processDatamap_afterDatabaseOperations('new', self::EVENT_TABLE, 'NEW404', [], $dataHandler);
        $hook->processDatamap_afterDatabaseOperations('new', self::CALENDAR_TABLE, 'NEW404', [], $dataHandler);
        $hook->processDatamap_afterAllOperations($dataHandler);
        $fields = null;
        $hook->processDatamap_preProcessFieldArray($fields, self::EVENT_TABLE, 1, $dataHandler);
        self::assertNull($fields);
        // other commands than delete
        $hook->processCmdmap_postProcess('copy', self::EVENT_TABLE, 1, 5, $dataHandler, false, []);
        $hook->processCmdmap_postProcess('delete', self::EVENT_TABLE, 'NEW1', 1, $dataHandler, false, []);
        $hook->processCmdmap_postProcess('delete', 'tx_cal_domain_model_location', 1, 1, $dataHandler, false, []);
        self::assertSame([], $this->messages());

        // workspace versions are not indexed
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));
        $hook->processDatamap_afterDatabaseOperations('update', self::EVENT_TABLE, 1, [], $dataHandler);
        self::assertSame(0, $this->indexCount(1));
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(0));

        // without language service the key of the message is shown
        unset($GLOBALS['LANG']);
        $fields = ['title' => 'Changed'];
        $hook->processDatamap_preProcessFieldArray($fields, self::EVENT_TABLE, 2, $dataHandler);
        self::assertNull($fields);
        $processed = false;
        $hook->processCmdmap('delete', self::EVENT_TABLE, 2, 1, $processed, $dataHandler, false);
        self::assertTrue($processed);
        self::assertSame(['WARNING: tx_cal_event.isTemp.locked', 'WARNING: tx_cal_event.isTemp.locked'], $this->messages());
    }

    #[Test]
    public function failingIndexIsReported(): void
    {
        $recurrenceGenerator = $this->createStub(RecurrenceGenerator::class);
        $recurrenceGenerator->method('setRange')->willReturnSelf();
        $recurrenceGenerator->method('generateIndexForEventUid')->willThrowException(new \RuntimeException('index broken'));
        $this->hook($recurrenceGenerator)->processDatamap_afterDatabaseOperations('update', self::EVENT_TABLE, 1, [], GeneralUtility::makeInstance(DataHandler::class));
        self::assertSame(['ERROR: The recurrence index of the event could not be generated: index broken'], $this->messages());
    }

    #[Test]
    public function theRuleOfACalDavClientIsReplacedWhenTheRecurrenceFieldsAreChanged(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable(self::EVENT_TABLE)
            ->update(self::EVENT_TABLE, ['rrule' => 'FREQ=DAILY;COUNT=5'], ['uid' => 1]);
        // other fields: the rule is kept and used for the index
        $this->process([self::EVENT_TABLE => [1 => ['title' => 'Daily meeting', 'freq' => 'week']]]);
        self::assertSame('FREQ=DAILY;COUNT=5', $this->row(self::EVENT_TABLE, 1)['rrule']);
        self::assertSame(5, $this->indexCount(1));
        // the defaults of the form (weekday of the start) do not change the rule
        $this->process([self::EVENT_TABLE => [1 => ['byday' => 'TH', 'intrval' => 1]]]);
        self::assertSame('FREQ=DAILY;COUNT=5', $this->row(self::EVENT_TABLE, 1)['rrule']);

        // a recurrence field: the fields are used
        $this->process([self::EVENT_TABLE => [1 => ['cnt' => 2]]]);
        self::assertSame('', $this->row(self::EVENT_TABLE, 1)['rrule']);
        self::assertSame(2, $this->indexCount(1));

        // new records and other tables are not changed
        $fieldArray = ['freq' => 'day'];
        $this->hook()->processDatamap_postProcessFieldArray('new', self::EVENT_TABLE, 'NEW1', $fieldArray, GeneralUtility::makeInstance(DataHandler::class));
        $this->hook()->processDatamap_postProcessFieldArray('update', 'tx_cal_domain_model_exception_event', 1, $fieldArray, GeneralUtility::makeInstance(DataHandler::class));
        self::assertSame(['freq' => 'day'], $fieldArray);
        // events without rule or unknown events
        $this->hook()->processDatamap_postProcessFieldArray('update', self::EVENT_TABLE, 1, $fieldArray, GeneralUtility::makeInstance(DataHandler::class));
        $this->hook()->processDatamap_postProcessFieldArray('update', self::EVENT_TABLE, 999, $fieldArray, GeneralUtility::makeInstance(DataHandler::class));
        self::assertSame(['freq' => 'day'], $fieldArray);
        // the rule written together with the fields is kept
        $fieldArray = ['freq' => 'day', 'rrule' => 'FREQ=HOURLY'];
        $this->hook()->processDatamap_postProcessFieldArray('update', self::EVENT_TABLE, 1, $fieldArray, GeneralUtility::makeInstance(DataHandler::class));
        self::assertSame('FREQ=HOURLY', $fieldArray['rrule']);
    }
}
