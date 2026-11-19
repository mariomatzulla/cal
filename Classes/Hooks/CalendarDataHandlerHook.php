<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Hooks;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Service\CalendarSchedulerService;
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Service\IcsGenerator;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class CalendarDataHandlerHook
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';
    private const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';
    private const INDEX_MODEL_TYPE = 'tx_cal_event';

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
        if ($command !== 'delete' || !is_numeric($id)) {
            return;
        }
        if ($table === self::CALENDAR_TABLE) {
            $this->recurrenceGenerator->cleanIndexTableOfCalendarUid((int)$id);
            $this->importer->removeImportedEvents((int)$id);
            $this->schedulerService->removeForCalendar((int)$id);
        } elseif ($table === self::EVENT_TABLE) {
            $this->recurrenceGenerator->cleanIndexTableOfUid((int)$id, self::INDEX_MODEL_TYPE);
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
                $this->importExternalCalendar($calendarUid);
                $this->synchronizeSchedulerTask($calendarUid);
            }
            return;
        }

        $targetTable = self::EVENT_TABLE;

        if ($table !== $targetTable) {
            return;
        }

        // New records have a temporary id (NEW...), the real uid is in DataHandler::substNEWwithIDs
        $uid = (int)($status === 'new' ? ($dataHandler->substNEWwithIDs[$id] ?? 0) : $id);

        if ($uid <= 0) {
            return;
        }

        $this->updateRecurrenceIndex($uid);

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
    private function updateRecurrenceIndex(int $uid): void
    {
        if ((int)$this->context->getPropertyFromAspect('workspace', 'id', 0) > 0) {
            return;
        }
        try {
            $this->recurrenceGenerator->setRange()->generateIndexForEventUid($uid);
        } catch (\Throwable $e) {
            $this->addFlashMessage('tx_cal_event.index.error', ContextualFeedbackSeverity::ERROR, [$e->getMessage()]);
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
     * Ermittelt den existierenden Request oder baut einen passenden Site-Request zusammen
     */
    private function resolveRequest(int $uid, array $fieldArray, string $targetTable): ServerRequestInterface
    {
        // Wenn bereits ein Request da ist (z. B. bei Frontend-Aktionen), nutzen wir diesen
        if (isset($GLOBALS['TYPO3_REQUEST']) && $GLOBALS['TYPO3_REQUEST'] instanceof ServerRequestInterface) {
            // Prüfen, ob es ein Frontend-Request ist (hat meist das 'site'-Attribut)
            if ($GLOBALS['TYPO3_REQUEST']->getAttribute('site') !== null) {
                return $GLOBALS['TYPO3_REQUEST'];
            }
        }

        // Da wir im Backend sind, brauchen wir die Page-ID (pid) des Datensatzes,
        // um herauszufinden, zu welcher Site/Domain der Eintrag gehört.
        $pid = (int)($fieldArray['pid'] ?? 0);
        if ($pid <= 0) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($targetTable);
            $pid = (int)$queryBuilder
            ->select('pid')
            ->from($targetTable)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        }

        try {
            // Site über den SiteFinder anhand der PID ermitteln
            $site = $this->siteFinder->getSiteByPageId($pid > 0 ? $pid : 1);

            // Einen valides PSR-7 Request-Objekt für diese Site erzeugen
            $baseUrl = (string)$site->getBase();
            return ServerRequestFactory::fromGlobals()
            ->withUri(GeneralUtility::makeInstance(Uri::class, $baseUrl))
            ->withAttribute('site', $site);
        } catch (\Throwable) {
            // Fallback, falls absolut keine Site ermittelt werden kann
            return ServerRequestFactory::fromGlobals();
        }
    }
}
