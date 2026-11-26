<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;

class CalDavBackend extends AbstractBackend
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly FrontendUserRepository $feuserRepository,
        private readonly SlugService $slugService,
        private readonly CalDavRecurrenceWriter $recurrenceWriter,
    ) {}

    public function getCalendarsForUser($principalUri)
    {
        $username = basename($principalUri);

        $feUser = $this->feuserRepository->findByName($username);

        $calendarList = $this->calendarRepository->findByUids((string)($feUser['tx_cal_calendar'] ?? ''));

        $calendars = [];
        foreach ($calendarList as $calendar) {
            $calendars[] = [
                'id' => $calendar['uid'],
                'uri' => $calendar['title'],
                'principaluri' => $principalUri,
                '{http://calendarserver.org/}getctag' => 'W/"' . $calendar['tstamp'] . '"',
                '{http://sabredav.org/ns}sync-token' => 'http://sabredav.org/ns/sync-token/' . $calendar['tstamp'],
                '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
                '{DAV:}displayname' => $calendar['title'],
                '{http://sabredav.org/ns}read-only' => ExternalCalendarImporter::isImportedType((int)$calendar['type']),
            ];
        }
        return $calendars;
    }

    public function getCalendarObjects($calendarId)
    {
        $events = [];
        foreach ($this->eventRepository->findAllForCalendarNative((int)$calendarId) as $record) {
            // Events without iCalendar data (not generated yet, see backend module "Cal ICS Generator") are skipped
            if (trim((string)$record['caldav_data']) === '') {
                continue;
            }
            $events[] = $this->buildCalendarObject((int)$calendarId, $record);
        }
        return $events;
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        $record = $this->findRecord((int)$calendarId, (string)$objectUri);
        return $record ? $this->buildCalendarObject((int)$calendarId, $record) : null;
    }

    /**
     * A client creates an object (PUT on a new URI). If an event with the same UID exists already
     * (e.g. stored before with the URI suffix, or soft deleted), it is updated / restored instead
     * of creating a duplicate.
     */
    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $calendarId = (int)$calendarId;
        $this->assertCalendarIsWritable($calendarId);
        $calendar = $this->calendarRepository->findRawByUid($calendarId);
        if ($calendar === null) {
            throw new NotFound('Calendar ' . $calendarId . ' not found');
        }

        $existing = $this->findRecord($calendarId, (string)$objectUri, true);
        if ($existing) {
            $values = $this->buildValues($calendarData, $calendar);
            $values['deleted'] = 0;
            $this->eventRepository->updateEvent($calendarId, (string)$existing['ics_uid'], $values);
        } else {
            $values = $this->buildValues($calendarData, $calendar);
            $values['pid'] = (int)$calendar['pid'];
            $values['calendar_id'] = $calendarId;
            $values['ics_uid'] = self::uriToIcsUid((string)$objectUri);
            $values['crdate'] = $values['tstamp'];
            $this->eventRepository->persistEvent($values);
        }
        $this->slugService->fillMissing('tx_cal_domain_model_event', $calendarId);
        $this->writeRecurrence($calendarId, (string)$objectUri, $calendarData, $calendar);

        $this->calendarRepository->touch($calendarId);
        return self::etag($calendarData);
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $calendarId = (int)$calendarId;
        $this->assertCalendarIsWritable($calendarId);
        $calendar = $this->calendarRepository->findRawByUid($calendarId);
        $record = $this->findRecord($calendarId, (string)$objectUri);
        if ($calendar === null || !$record) {
            throw new NotFound('Calendar object ' . $objectUri . ' not found');
        }

        $this->eventRepository->updateEvent($calendarId, (string)$record['ics_uid'], $this->buildValues($calendarData, $calendar));
        $this->writeRecurrence($calendarId, (string)$objectUri, $calendarData, $calendar);
        $this->calendarRepository->touch($calendarId);
        return self::etag($calendarData);
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $calendarId = (int)$calendarId;
        $this->assertCalendarIsWritable($calendarId);
        $record = $this->findRecord($calendarId, (string)$objectUri);
        if (!$record) {
            return;
        }
        // Soft delete (deleted = 1), so the record can still be restored in the backend recycler
        $this->eventRepository->updateEvent($calendarId, (string)$record['ics_uid'], ['deleted' => 1, 'tstamp' => time()]);
        $this->calendarRepository->touch($calendarId);
    }

    /**
     * The URI of a calendar object is always "<ics_uid>.ics"; the database stores the UID without suffix.
     */
    public static function uriToIcsUid(string $objectUri): string
    {
        return str_ends_with(strtolower($objectUri), '.ics') ? substr($objectUri, 0, -4) : $objectUri;
    }

    public static function icsUidToUri(string $icsUid): string
    {
        return self::uriToIcsUid($icsUid) . '.ics';
    }

    /**
     * Finds the event of a URI. Older records may have been stored with the ".ics" suffix in ics_uid,
     * they are found as well.
     */
    private function findRecord(int $calendarId, string $objectUri, bool $includeDeleted = false): array|false
    {
        $icsUid = self::uriToIcsUid($objectUri);
        return $this->eventRepository->findAllForCalendarAndUri($calendarId, $icsUid, $includeDeleted)
            ?: $this->eventRepository->findAllForCalendarAndUri($calendarId, $icsUid . '.ics', $includeDeleted);
    }

    private function buildCalendarObject(int $calendarId, array $record): array
    {
        $calendarData = (string)$record['caldav_data'];
        return [
            'id' => (int)$record['uid'],
            'uri' => self::icsUidToUri((string)$record['ics_uid']),
            'lastmodified' => (int)$record['tstamp'],
            'calendarid' => $calendarId,
            'calendardata' => $calendarData,
            'etag' => self::etag($calendarData),
            'size' => strlen($calendarData),
            'component' => 'vevent',
        ];
    }

    private function buildValues(string $calendarData, array $calendar): array
    {
        $values = IcsParser::parseIcsContent($calendarData, (string)($calendar['timezone'] ?? ''));
        // The key of an object is its URI, not the UID inside the data (clients may choose any URI)
        unset($values['ics_uid']);
        $values['caldav_data'] = rtrim($calendarData);
        $values['tstamp'] = time();
        return $values;
    }

    /**
     * Removed dates and changed occurrences of the series as exception events and deviations,
     * then the recurrence index of the event is rebuilt
     */
    private function writeRecurrence(int $calendarId, string $objectUri, string $calendarData, array $calendar): void
    {
        $record = $this->findRecord($calendarId, $objectUri);
        if ($record) {
            $this->recurrenceWriter->write((int)$record['uid'], IcsParser::parseRecurrenceDetails($calendarData, (string)($calendar['timezone'] ?? '')));
        }
    }

    private static function etag(string $calendarData): string
    {
        return '"' . md5(rtrim($calendarData)) . '"';
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        // We handle calendar names like ids. Until we change this we can not hanlde this feature
        throw new NotImplemented('Not implemented: createCalendar');
    }

    /**
     * Delete a calendar and all it's objects
     *
     * @param mixed $calendarId
     */
    public function deleteCalendar($calendarId)
    {

        throw new NotImplemented('Not implemented: deleteCalendar');
    }

    /**
     * External calendars are read-only: their events are replaced by the import.
     */
    private function assertCalendarIsWritable(int $calendarId): void
    {
        $calendar = $this->calendarRepository->findRawByUid($calendarId);
        if ($calendar !== null && ExternalCalendarImporter::isImportedType((int)$calendar['type'])) {
            throw new Forbidden('This calendar is imported from an external source and is read-only.');
        }
    }
}
