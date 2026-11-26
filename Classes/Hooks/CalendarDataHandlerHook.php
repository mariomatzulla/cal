<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Hooks;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\ExceptionEventRepository;
use MarioMatzulla\Cal\Service\CalendarSchedulerService;
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Service\IcsGenerator;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use MarioMatzulla\Cal\Utility\RecurrenceValidator;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class CalendarDataHandlerHook
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';
    private const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';
    private const INDEX_MODEL_TYPE = 'tx_cal_event';
    private const DEVIATION_TABLE = 'tx_cal_domain_model_eventdeviation';

    /**
     * Fields of the backend form describing the recurrence rule
     */
    private const RECURRENCE_FIELDS = ['freq', 'intrval', 'cnt', 'until', 'byday', 'bymonthday', 'bymonth'];

    /**
     * @var array<int, int> calendars saved in the current DataHandler run
     */
    private array $savedCalendars = [];

    /**
     * @var array<int, int> events whose exception events or deviations were saved in the current DataHandler run
     */
    private array $changedEvents = [];

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly ExternalCalendarImporter $importer,
        private readonly CalendarSchedulerService $schedulerService,
        private readonly IcsGenerator $icsGenerator,
        private readonly RecurrenceGenerator $recurrenceGenerator,
        private readonly DataMapper $dataMapper,
        private readonly ConnectionPool $connectionPool,
        private readonly FlashMessageService $flashMessageService,
        private readonly SiteFinder $siteFinder,
        private readonly Context $context,
        private readonly ExceptionEventRepository $exceptionEventRepository,
    ) {}

    /**
     * Imported events (isTemp = 1) of external calendars must not be changed in the backend.
     */
    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, string $table, $id, DataHandler $dataHandler): void
    {
        if ($table !== self::EVENT_TABLE || !is_array($incomingFieldArray)) {
            return;
        }
        if (!is_numeric($id)) {
            // A new record must not be flagged as imported event
            unset($incomingFieldArray['isTemp']);
            return;
        }
        if ($this->eventRepository->isTempEvent((int)$id)) {
            // null skips the record completely
            $incomingFieldArray = null;
            $this->addFlashMessage('tx_cal_event.isTemp.locked', ContextualFeedbackSeverity::WARNING);
        }
    }

    /**
     * The recurrence rule of a CalDAV client (field "rrule") is replaced by the recurrence fields as soon as
     * the rule of the fields is changed in the backend. The field array only contains the changed values, but
     * the form fills empty fields with their defaults (e.g. all weekdays), therefore the rules are compared
     * after the validation.
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($status !== 'update' || $table !== self::EVENT_TABLE || array_key_exists('rrule', $fieldArray)
            || array_intersect_key($fieldArray, array_flip(self::RECURRENCE_FIELDS)) === []) {
            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder->select('*')->from(self::EVENT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$id, Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        if ($record === false || trim((string)$record['rrule']) === '') {
            return;
        }
        if (self::getRecurrence($record) !== self::getRecurrence(array_replace($record, $fieldArray))) {
            $fieldArray['rrule'] = '';
        }
    }

    /**
     * The validated recurrence fields of an event record
     *
     * @param array<string, mixed> $record
     * @return list<mixed>
     */
    private static function getRecurrence(array $record): array
    {
        $event = new Event();
        $event->setStartDate((new \DateTime())->setTimestamp((int)$record['start_date']));
        $event->setFreq((string)$record['freq']);
        $event->setIntrval((int)$record['intrval']);
        $event->setCnt((int)$record['cnt']);
        $event->setUntil((int)$record['until'] > 0 ? (new \DateTime())->setTimestamp((int)$record['until']) : null);
        $event->setByday((string)$record['byday']);
        $event->setBymonthday((string)$record['bymonthday']);
        $event->setBymonth((string)$record['bymonth']);
        RecurrenceValidator::validateEvent($event);
        return [
            $event->getFreq(),
            (int)$event->getIntrval(),
            (int)$event->getCnt(),
            $event->getUntil()?->format('Ymd'),
            self::sortList((string)$event->getByday()),
            self::sortList((string)$event->getBymonthday()),
            self::sortList((string)$event->getBymonth()),
        ];
    }

    /**
     * "TU,MO" => "MO,TU": the order of the values has no meaning
     */
    private static function sortList(string $list): string
    {
        $values = array_map('trim', explode(',', $list));
        sort($values);
        return implode(',', $values);
    }

    /**
     * Prevents delete, move, copy, localize etc. of imported events.
     */
    public function processCmdmap(string $command, string $table, $id, $value, bool &$commandIsProcessed, DataHandler $dataHandler, $pasteUpdate): void
    {
        if ($table === self::EVENT_TABLE && is_numeric($id) && $this->eventRepository->isTempEvent((int)$id)) {
            $commandIsProcessed = true;
            $this->addFlashMessage('tx_cal_event.isTemp.locked', ContextualFeedbackSeverity::WARNING);
        }
    }

    /**
     * Removes the imported events when an external calendar is deleted.
     */
    public function processCmdmap_postProcess(string $command, string $table, $id, $value, DataHandler $dataHandler, $pasteUpdate, $pasteDatamap): void
    {
        if ($command === 'undelete' && $table === self::EVENT_TABLE && is_numeric($id)) {
            // restored (recycler): recurrence index and iCalendar data are created again
            $this->processEvent((int)$id, []);
            return;
        }
        if ($command !== 'delete' || !is_numeric($id)) {
            return;
        }
        if ($table === self::CALENDAR_TABLE) {
            $this->recurrenceGenerator->cleanIndexTableOfCalendarUid((int)$id);
            $this->importer->removeImportedEvents((int)$id);
            $this->schedulerService->removeForCalendar((int)$id);
        } elseif ($table === self::EVENT_TABLE) {
            $this->recurrenceGenerator->cleanIndexTableOfUid((int)$id, self::INDEX_MODEL_TYPE);
        } else {
            // a deleted exception event or deviation: the index and the iCalendar data of its events change
            foreach ($this->findEventsOfRelatedRecord($table, (int)$id, []) as $eventUid) {
                $this->processEvent($eventUid, []);
            }
        }
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table === self::CALENDAR_TABLE) {
            $calendarUid = (int)($status === 'new' ? ($dataHandler->substNEWwithIDs[$id] ?? 0) : $id);
            if ($calendarUid > 0) {
                // The file references (ICS files) are written after the calendar record,
                // therefore the import runs in processDatamap_afterAllOperations()
                $this->savedCalendars[$calendarUid] = $calendarUid;
            }
            return;
        }

        // New records have a temporary id (NEW...), the real uid is in DataHandler::substNEWwithIDs
        $uid = (int)($status === 'new' ? ($dataHandler->substNEWwithIDs[$id] ?? 0) : $id);
        if ($uid <= 0) {
            return;
        }
        if ($table !== self::EVENT_TABLE) {
            // exception events and deviations: their events are updated after all records are saved
            foreach ($this->findEventsOfRelatedRecord($table, $uid, $fieldArray) as $eventUid) {
                $this->changedEvents[$eventUid] = $eventUid;
            }
            return;
        }
        $this->processEvent($uid, $fieldArray);
    }

    /**
     * Events of an exception event, an exception group or a deviation
     *
     * @return list<int>
     */
    private function findEventsOfRelatedRecord(string $table, int $uid, array $fieldArray): array
    {
        if ($table === self::DEVIATION_TABLE) {
            $parent = (int)($fieldArray['parentid'] ?? 0);
            if ($parent <= 0) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::DEVIATION_TABLE);
                $queryBuilder->getRestrictions()->removeAll();
                $parent = (int)$queryBuilder->select('parentid')
                    ->from(self::DEVIATION_TABLE)
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                    ->executeQuery()
                    ->fetchOne();
            }
            return $parent > 0 ? [$parent] : [];
        }
        return $this->exceptionEventRepository->findEventUids($table, $uid);
    }

    /**
     * Recurrence index and iCalendar data of a saved event
     */
    private function processEvent(int $uid, array $fieldArray): void
    {
        $targetTable = self::EVENT_TABLE;
        $this->updateRecurrenceIndex($uid, $this->isVisible($uid));

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($targetTable);
        $query = $queryBuilder->select('*')
                  ->from($targetTable)
                  ->where(
                      $queryBuilder->expr()->eq('uid', $uid),
                  );
        $rows = $query->executeQuery()->fetchAllAssociative();
        $events = $this->dataMapper->map(Event::class, $rows);
        if ($events === []) {
            // e.g. hidden or time-restricted records are filtered by the default restrictions
            return;
        }

        $this->icsGenerator->updateIcs($events[0], $this->resolveRequest($uid, $fieldArray, $targetTable));

        $calendar = $events[0]->getCalendarObject();
        if ($calendar !== null) {
            $this->calendarRepository->touch((int)$calendar->getUid());
        }
    }

    /**
     * Rebuilds the recurrence index of the saved event (was done in the FormEngine before,
     * i.e. every time the record was opened). Hidden / deleted events only lose their index entries.
     * Workspace versions are not indexed, the index is built when they are published.
     */
    private function updateRecurrenceIndex(int $uid, bool $visible): void
    {
        if ((int)$this->context->getPropertyFromAspect('workspace', 'id', 0) > 0) {
            return;
        }
        if (!$visible) {
            $this->recurrenceGenerator->cleanIndexTableOfUid($uid, self::INDEX_MODEL_TYPE);
            return;
        }
        try {
            $this->recurrenceGenerator->setRange()->generateIndexForEventUid($uid);
        } catch (\Throwable $e) {
            $this->addFlashMessage('tx_cal_event.index.error', ContextualFeedbackSeverity::ERROR, [$e->getMessage()]);
        }
    }

    /**
     * Hidden and deleted events have no index entries. Start and end time (publishing) are ignored: the
     * frontend checks them when the events are read, the index must exist when they become visible.
     */
    private function isVisible(int $uid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('hidden', 'deleted')
            ->from(self::EVENT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row !== false && (int)$row['hidden'] === 0 && (int)$row['deleted'] === 0;
    }

    /**
     * Imports the saved external / ICS file calendars and synchronizes their scheduler tasks.
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $calendars = $this->savedCalendars;
        $this->savedCalendars = [];
        foreach ($calendars as $calendarUid) {
            $this->importExternalCalendar($calendarUid);
            $this->synchronizeSchedulerTask($calendarUid);
        }
        $events = $this->changedEvents;
        $this->changedEvents = [];
        foreach ($events as $eventUid) {
            $this->processEvent($eventUid, []);
        }
    }

    private function importExternalCalendar(int $calendarUid): void
    {
        try {
            $result = $this->importer->importCalendar($calendarUid);
        } catch (\RuntimeException $e) {
            $this->addFlashMessage('tx_cal_calendar.import.error', ContextualFeedbackSeverity::ERROR, [$e->getMessage()]);
            return;
        }
        switch ($result['status']) {
            case ExternalCalendarImporter::STATUS_IMPORTED:
                $this->addFlashMessage('tx_cal_calendar.import.imported', ContextualFeedbackSeverity::OK, [$result['count']]);
                break;
            case ExternalCalendarImporter::STATUS_REMOVED:
                $this->addFlashMessage('tx_cal_calendar.import.removed', ContextualFeedbackSeverity::INFO, [$result['count']]);
                break;
        }
    }

    private function synchronizeSchedulerTask(int $calendarUid): void
    {
        try {
            $result = $this->schedulerService->synchronize($calendarUid);
        } catch (\Throwable $e) {
            $this->addFlashMessage('tx_cal_calendar.scheduler.error', ContextualFeedbackSeverity::ERROR, [$e->getMessage()]);
            return;
        }
        switch ($result['status']) {
            case CalendarSchedulerService::STATUS_CREATED:
                $this->addFlashMessage('tx_cal_calendar.scheduler.created', ContextualFeedbackSeverity::OK, [$result['taskUid'], $result['interval']]);
                break;
            case CalendarSchedulerService::STATUS_REMOVED:
                $this->addFlashMessage('tx_cal_calendar.scheduler.removed', ContextualFeedbackSeverity::INFO, [$result['taskUid']]);
                break;
            case CalendarSchedulerService::STATUS_SCHEMA_MISSING:
                $this->addFlashMessage('tx_cal_calendar.scheduler.schemaMissing', ContextualFeedbackSeverity::WARNING);
                break;
        }
    }

    private function addFlashMessage(string $key, ContextualFeedbackSeverity $severity, array $arguments = []): void
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        $message = $languageService instanceof LanguageService ? $languageService->sL(self::LLL . $key) : '';
        if ($message === '') {
            $message = $key;
        }
        if ($arguments !== []) {
            $message = vsprintf($message, $arguments);
        }
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', $severity, true);
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue($flashMessage);
    }

    /**
     * The current request, if it belongs to a site (e.g. frontend editing). Otherwise a request for the site
     * of the record is created. ServerRequestFactory::fromGlobals() is not used: it fails on CLI (scheduler,
     * console commands using the DataHandler).
     */
    private function resolveRequest(int $uid, array $fieldArray, string $targetTable): ServerRequestInterface
    {
        $current = ($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface ? $GLOBALS['TYPO3_REQUEST'] : null;
        if ($current?->getAttribute('site') !== null) {
            return $current;
        }
        $request = $current ?? new ServerRequest('https://localhost/', 'GET');
        if ($request->getAttribute('applicationType') === null) {
            $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        }

        // The site of the record is determined by its page
        $pid = (int)($fieldArray['pid'] ?? 0);
        if ($pid <= 0) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($targetTable);
            $queryBuilder->getRestrictions()->removeAll();
            $pid = (int)$queryBuilder
                ->select('pid')
                ->from($targetTable)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchOne();
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pid);
        } catch (SiteNotFoundException) {
            return $request;
        }
        return $request->withUri($site->getBase())->withAttribute('site', $site);
    }
}
